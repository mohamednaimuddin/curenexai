<?php
/**
 * Rule B execution: For each rubric heading found in Kent_Mind_Rubrics 1-30.pdf,
 * delete every `repertory` row in category='mind' whose rubric name matches the
 * heading exactly OR begins with "HEADING," / "HEADING " (i.e. all legacy
 * sub-rubrics under that family) -- but ONLY when verified_source is NOT a
 * Kent_Mind_* PDF source. repertory_remedies mappings are cascade-deleted.
 *
 * Run:
 *   php execute_delete_ruleB.php             (dry run)
 *   php execute_delete_ruleB.php --delete    (actually delete)
 */
define('APP_ACCESS', true);
require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../includes/database.php';

$doDelete = in_array('--delete', $argv ?? [], true);

$pdfRubrics = json_decode(file_get_contents(__DIR__ . '/extracted_ocr/rubrics_pdf.json'), true);
$pdfRubrics = array_map(fn($r) => $r === 'NTHROPOPHOBIA' ? 'ANTHROPOPHOBIA' : $r, $pdfRubrics);
$pdfRubrics = array_values(array_unique(array_map('strtoupper', $pdfRubrics)));

$c = Database::getInstance()->getConnection();

$qFind = $c->prepare(
    "SELECT id, rubric, verified_source, repertory_source
       FROM repertory
      WHERE LOWER(category) = 'mind'
        AND ( UPPER(TRIM(rubric)) = ?
           OR UPPER(TRIM(rubric)) LIKE ?
           OR UPPER(TRIM(rubric)) LIKE ? )
        AND ( verified_source IS NULL
           OR verified_source NOT LIKE 'Kent_Mind_%' )"
);

$idsToDelete = [];
foreach ($pdfRubrics as $name) {
    $qFind->execute([$name, $name . ',%', $name . ' %']);
    foreach ($qFind->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idsToDelete[(int)$row['id']] = $row;
    }
}
$ids = array_keys($idsToDelete);
echo "Rows queued for deletion: " . count($ids) . "\n";

if (!$ids) { echo "Nothing to do.\n"; exit(0); }

if (!$doDelete) {
    echo "Dry run (re-run with --delete to commit). Full list:\n";
    foreach ($idsToDelete as $id => $row) {
        echo "  #$id  '{$row['rubric']}'  v=" . ($row['verified_source'] ?? 'NULL') .
             "  r=" . $row['repertory_source'] . "\n";
    }
    exit(0);
}

$conn = $c;
$conn->beginTransaction();
try {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $del1 = $conn->prepare("DELETE FROM repertory_remedies WHERE repertory_id IN ($place)");
    $del1->execute($ids);
    $mapCount = $del1->rowCount();
    $del2 = $conn->prepare("DELETE FROM repertory WHERE id IN ($place)");
    $del2->execute($ids);
    $rubCount = $del2->rowCount();
    $conn->commit();
    echo "Deleted $rubCount repertory rows and $mapCount repertory_remedies rows.\n";
} catch (Throwable $e) {
    $conn->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(2);
}
