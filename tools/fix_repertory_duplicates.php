<?php
/**
 * Merge exact duplicate repertory rows by normalized complete_rubric.
 *
 * Dry run:
 *   C:\xampp\php\php.exe tools\fix_repertory_duplicates.php
 *
 * Apply:
 *   C:\xampp\php\php.exe tools\fix_repertory_duplicates.php --apply
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

$apply = in_array('--apply', $argv, true);

$groups = DB::query(
    "SELECT LOWER(TRIM(complete_rubric)) AS duplicate_key, COUNT(*) AS total
     FROM repertory
     GROUP BY LOWER(TRIM(complete_rubric))
     HAVING total > 1
     ORDER BY total DESC, duplicate_key"
);

if (!$groups) {
    echo "No exact duplicate repertory rows found." . PHP_EOL;
    exit(0);
}

echo "Duplicate complete_rubric groups: " . count($groups) . PHP_EOL;

$planned = [];
foreach ($groups as $group) {
    $rows = DB::query(
        "SELECT r.*,
                (SELECT COUNT(*) FROM repertory_remedies rr WHERE rr.repertory_id = r.id) AS remedy_count
         FROM repertory r
         WHERE LOWER(TRIM(r.complete_rubric)) = ?
         ORDER BY r.is_verified DESC, remedy_count DESC, r.id ASC",
        [$group['duplicate_key']]
    );

    if (!$rows || count($rows) < 2) {
        continue;
    }

    $keeper = $rows[0];
    $remove = array_slice($rows, 1);
    $planned[] = [
        'key' => $group['duplicate_key'],
        'keep' => (int) $keeper['id'],
        'remove' => array_map(static fn($row) => (int) $row['id'], $remove),
    ];

    echo "KEEP {$keeper['id']} for {$keeper['complete_rubric']}" . PHP_EOL;
    echo "  remove: " . implode(', ', array_map(static fn($row) => $row['id'], $remove)) . PHP_EOL;
}

if (!$apply) {
    echo "Dry run only. Re-run with --apply to merge duplicates." . PHP_EOL;
    exit(0);
}

DB::beginTransaction();
try {
    foreach ($planned as $plan) {
        foreach ($plan['remove'] as $removeId) {
            DB::execute(
                "INSERT INTO repertory_remedies
                    (repertory_id, remedy_id, grade, created_at, is_verified, verified_source, verified_page, verified_at)
                 SELECT ?, rr.remedy_id, rr.grade, rr.created_at, rr.is_verified, rr.verified_source, rr.verified_page, rr.verified_at
                 FROM repertory_remedies rr
                 WHERE rr.repertory_id = ?
                   AND NOT EXISTS (
                       SELECT 1
                       FROM repertory_remedies existing
                       WHERE existing.repertory_id = ?
                         AND existing.remedy_id = rr.remedy_id
                   )",
                [$plan['keep'], $removeId, $plan['keep']]
            );

            DB::execute("DELETE FROM repertory_remedies WHERE repertory_id = ?", [$removeId]);
            DB::execute("DELETE FROM repertory WHERE id = ?", [$removeId]);
        }
    }

    DB::commit();
    echo "Duplicate merge complete." . PHP_EOL;
} catch (Throwable $e) {
    DB::rollback();
    throw $e;
}
