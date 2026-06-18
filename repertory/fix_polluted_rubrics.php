<?php
// Step D: clean polluted rubric columns by extracting headword before ':'
// Merges into existing (category, headword, sub_category) row if one exists.
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/database.php';

$apply = in_array('--apply', $argv ?? [], true);
echo "Mode: " . ($apply ? "APPLY (will commit)" : "DRY-RUN") . "\n\n";

$pdo = Database::getInstance()->getConnection();
$pdo->beginTransaction();

try {
    $re = ':[[:space:]]+[A-Za-z]+[.,]';
    $stmt = $pdo->prepare("SELECT id,category,rubric,sub_category FROM repertory WHERE rubric REGEXP ?");
    $stmt->execute([$re]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $findTarget = $pdo->prepare(
        "SELECT id FROM repertory
         WHERE LOWER(category)=LOWER(?) AND LOWER(TRIM(rubric))=LOWER(?)
           AND LOWER(TRIM(IFNULL(sub_category,'')))=LOWER(TRIM(IFNULL(?,'')))
           AND id<>? LIMIT 1"
    );
    $moveMap = $pdo->prepare(
        "INSERT INTO repertory_remedies (repertory_id, remedy_id, grade, is_verified, verified_source, verified_page, verified_at)
         SELECT ?, src.remedy_id, src.grade, src.is_verified, src.verified_source, src.verified_page, src.verified_at
         FROM repertory_remedies src WHERE src.repertory_id = ?
         ON DUPLICATE KEY UPDATE grade = GREATEST(repertory_remedies.grade, VALUES(grade))"
    );
    $deleteMap = $pdo->prepare("DELETE FROM repertory_remedies WHERE repertory_id=?");
    $deleteRub = $pdo->prepare("DELETE FROM repertory WHERE id=?");
    $updateRub = $pdo->prepare("UPDATE repertory SET rubric=?, complete_rubric=? WHERE id=?");

    $cleaned = $merged = 0;
    foreach ($rows as $r) {
        if (!preg_match('/^\s*([^:]{2,80}?)\s*:/', $r['rubric'], $m)) continue;
        $head = trim($m[1]);
        $sub = trim((string)$r['sub_category']);
        $cat = $r['category'];
        $title = ($cat === 'mind') ? 'Mind' : ucfirst($cat);
        $label = $title . ', ' . $head . ($sub !== '' ? ', ' . $sub : '');

        $findTarget->execute([$cat, $head, $sub, (int)$r['id']]);
        $targetId = $findTarget->fetchColumn();

        if ($targetId) {
            // merge into existing clean row
            if ($apply) {
                $moveMap->execute([(int)$targetId, (int)$r['id']]);
                $deleteMap->execute([(int)$r['id']]);
                $pdo->prepare("DELETE FROM repertory WHERE id=?")->execute([(int)$r['id']]);
            }
            $merged++;
        } else {
            // just clean the rubric column in place
            if ($apply) {
                $updateRub->execute([$head, $label, (int)$r['id']]);
            }
            $cleaned++;
        }
    }

    echo "Polluted rubrics cleaned in place : $cleaned\n";
    echo "Polluted rows merged into clean   : $merged\n";

    if ($apply) { $pdo->commit(); echo "\n[COMMITTED]\n"; }
    else { $pdo->rollBack(); echo "\n[DRY-RUN] re-run with --apply\n"; }
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
