<?php
/**
 * Kent Mind Rubrics Importer
 *
 * Source: assets/Kent_rubics/kent_mind_rubrics.csv
 *   columns: rubric, complete_rubric, category, sub_category,
 *            remedy_short_name, grade, page
 *
 * Idempotent:
 *   - upserts each distinct (complete_rubric, sub_category) into `repertory`
 *     (category = 'mind', verified, source = Kent_Mind_1-10)
 *   - matches remedy by exact (case-insensitive) `remedy_short_name`;
 *     skips and logs unknown remedies (does NOT auto-create remedies)
 *   - inserts into `repertory_remedies` via INSERT ... ON DUPLICATE KEY UPDATE
 *
 * Usage:
 *   php repertory/import_kent_mind.php           # dry run
 *   php repertory/import_kent_mind.php --apply   # commit changes
 */

define('APP_ACCESS', true);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/database.php';

$apply = in_array('--apply', $argv ?? [], true);

// Optional: pass --csv=<path>; defaults to the small 1-19 CSV.
$csvPath = __DIR__ . '/../assets/Kent_rubics/kent_mind_rubrics.csv';
foreach (($argv ?? []) as $a) {
    if (str_starts_with($a, '--csv=')) {
        $candidate = substr($a, 6);
        if (!str_starts_with($candidate, '/') && !preg_match('#^[A-Za-z]:#', $candidate)) {
            $candidate = __DIR__ . '/../' . $candidate;
        }
        $csvPath = $candidate;
    }
}
echo "CSV: $csvPath\n";
if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV not found: $csvPath\n");
    exit(1);
}

$pdo = Database::getInstance()->getConnection();

// -------------------------------------------------------------------------
// Load remedies map: short_name (lower) -> id
// -------------------------------------------------------------------------
$remedyMap = [];
$normalizeShort = static function (string $s): string {
    $s = strtolower(trim($s));
    $s = rtrim($s, '.');           // strip trailing dot ("Ign." -> "ign")
    $s = preg_replace('/\s+/', '', $s); // collapse whitespace
    return $s;
};
$res = $pdo->query("SELECT id, remedy_short_name FROM remedies");
while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
    $key = $normalizeShort((string)$r['remedy_short_name']);
    if ($key !== '' && !isset($remedyMap[$key])) {
        $remedyMap[$key] = (int)$r['id'];
    }
}
echo "Loaded remedies: " . count($remedyMap) . "\n";

// -------------------------------------------------------------------------
// Load existing mind rubrics map: lower(complete_rubric)|lower(sub_category) -> id
// -------------------------------------------------------------------------
$rubricMap = [];
$res = $pdo->query("SELECT id, complete_rubric, sub_category FROM repertory WHERE LOWER(category)='mind'");
while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
    $key = strtolower(trim($r['complete_rubric'])) . '|' . strtolower(trim($r['sub_category']));
    $rubricMap[$key] = (int)$r['id'];
}
echo "Existing mind rubrics: " . count($rubricMap) . "\n";

// -------------------------------------------------------------------------
// Read CSV
// -------------------------------------------------------------------------
$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$idx = array_flip($header);

// Determine which CSV schema we're reading.
//   Old: rubric, complete_rubric, category, sub_category, remedy_short_name, grade, page
//   New: page, rubric, sub_rubric, complete_rubric, remedy, grade
$isNewSchema = isset($idx['sub_rubric']) && isset($idx['remedy']);

// Pick a source label that reflects the data source (page range).
$verifiedSourceLabel = $isNewSchema ? 'Kent_Mind_1-95' : 'Kent_Mind_1-10';
echo "Schema: " . ($isNewSchema ? 'A-Z (1-95)' : 'legacy (1-10)') . "\n";

$rows = [];
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < count($header)) continue;
    if ($isNewSchema) {
        $rows[] = [
            'rubric'           => trim($r[$idx['rubric']]),
            'complete_rubric'  => trim($r[$idx['complete_rubric']]),
            'category'         => 'mind',
            'sub_category'     => trim($r[$idx['sub_rubric']]),
            'remedy_short_name'=> $normalizeShort((string)$r[$idx['remedy']]),
            'grade'            => trim($r[$idx['grade']]),
            'page'             => (int)$r[$idx['page']],
        ];
    } else {
        $rows[] = [
            'rubric'           => trim($r[$idx['rubric']]),
            'complete_rubric'  => trim($r[$idx['complete_rubric']]),
            'category'         => strtolower(trim($r[$idx['category']])),
            'sub_category'     => trim($r[$idx['sub_category']]),
            'remedy_short_name'=> $normalizeShort((string)$r[$idx['remedy_short_name']]),
            'grade'            => trim($r[$idx['grade']]),
            'page'             => (int)$r[$idx['page']],
        ];
    }
}
fclose($fh);
echo "CSV rows: " . count($rows) . "\n";

// -------------------------------------------------------------------------
// Pass 1: build distinct rubric set
// -------------------------------------------------------------------------
$distinctRubrics = [];
foreach ($rows as $r) {
    $key = strtolower($r['complete_rubric']) . '|' . strtolower($r['sub_category']);
    if (!isset($distinctRubrics[$key])) {
        $distinctRubrics[$key] = [
            'rubric'          => $r['rubric'],
            'complete_rubric' => $r['complete_rubric'],
            'sub_category'    => $r['sub_category'],
            'page'            => $r['page'],
        ];
    }
}
echo "Distinct rubrics in CSV: " . count($distinctRubrics) . "\n";

// -------------------------------------------------------------------------
// Apply changes
// -------------------------------------------------------------------------
$pdo->beginTransaction();

$insertedRubrics = 0;
$reusedRubrics   = 0;
$insRubric = $pdo->prepare(
    "INSERT INTO repertory
       (rubric, category, sub_category, complete_rubric, repertory_source,
        is_verified, verified_source, verified_page, verified_at)
     VALUES (?, 'mind', ?, ?, 'Kent''s Repertory', 1, ?, ?, NOW())"
);
$updateVerified = $pdo->prepare(
    "UPDATE repertory
        SET is_verified = 1,
            verified_source = COALESCE(NULLIF(verified_source,''), ?),
            verified_page = COALESCE(verified_page, ?),
            verified_at = COALESCE(verified_at, NOW())
      WHERE id = ?"
);

foreach ($distinctRubrics as $key => $info) {
    if (isset($rubricMap[$key])) {
        $reusedRubrics++;
        $updateVerified->execute([$verifiedSourceLabel, $info['page'], $rubricMap[$key]]);
        continue;
    }
    $insRubric->execute([
        $info['rubric'],
        $info['sub_category'],
        $info['complete_rubric'],
        $verifiedSourceLabel,
        $info['page'],
    ]);
    $newId = (int)$pdo->lastInsertId();
    $rubricMap[$key] = $newId;
    $insertedRubrics++;
}

// -------------------------------------------------------------------------
// Mappings (repertory_remedies)
// -------------------------------------------------------------------------
$insMap = $pdo->prepare(
    "INSERT INTO repertory_remedies
       (repertory_id, remedy_id, grade, is_verified, verified_source, verified_page, verified_at)
     VALUES (?, ?, ?, 1, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
       grade = VALUES(grade),
       is_verified = 1,
       verified_source = VALUES(verified_source),
       verified_page = VALUES(verified_page),
       verified_at = NOW()"
);

$mappingsInserted = 0;
$mappingsSkippedNoRemedy = 0;
$missingRemedies = [];

foreach ($rows as $r) {
    $rubKey = strtolower($r['complete_rubric']) . '|' . strtolower($r['sub_category']);
    $repertoryId = $rubricMap[$rubKey] ?? null;
    if (!$repertoryId) continue; // shouldn't happen

    $shortName = $r['remedy_short_name'];
    if (!isset($remedyMap[$shortName])) {
        $mappingsSkippedNoRemedy++;
        $missingRemedies[$shortName] = ($missingRemedies[$shortName] ?? 0) + 1;
        continue;
    }
    $remedyId = $remedyMap[$shortName];
    $grade = in_array($r['grade'], ['1','2','3','4'], true) ? $r['grade'] : '2';

    $insMap->execute([$repertoryId, $remedyId, $grade, $verifiedSourceLabel, $r['page']]);
    $mappingsInserted++;
}

if ($apply) {
    $pdo->commit();
    echo "COMMITTED.\n";
} else {
    $pdo->rollBack();
    echo "DRY RUN -- rolled back. Re-run with --apply to commit.\n";
}

echo "----- Summary -----\n";
echo "Distinct rubrics in CSV : " . count($distinctRubrics) . "\n";
echo "  inserted              : $insertedRubrics\n";
echo "  reused (existed)      : $reusedRubrics\n";
echo "Mappings processed      : " . count($rows) . "\n";
echo "  upserted              : $mappingsInserted\n";
echo "  skipped (no remedy)   : $mappingsSkippedNoRemedy\n";
echo "Distinct missing remedies: " . count($missingRemedies) . "\n";
if ($missingRemedies) {
    arsort($missingRemedies);
    $top = array_slice($missingRemedies, 0, 50, true);
    foreach ($top as $name => $cnt) {
        echo sprintf("  %-15s  %d\n", $name, $cnt);
    }
    if (count($missingRemedies) > 50) {
        echo "  ... (+" . (count($missingRemedies) - 50) . " more)\n";
    }
}
