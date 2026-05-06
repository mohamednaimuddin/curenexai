<?php
/**
 * Preview duplicate rubrics in `repertory` table that share a name with
 * a rubric heading from Kent_Mind_Rubrics 1-30.pdf, where one row is
 * sourced from that PDF and others are not.
 *
 * Usage:
 *   php preview_duplicates.php           -> preview only
 *   php preview_duplicates.php --delete  -> actually DELETE non-PDF duplicates
 *                                            and cascade their repertory_remedies.
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';

$doDelete = in_array('--delete', $argv ?? [], true);

// ---------- Load rubric names from OCR ----------
$jsonPath = __DIR__ . '/extracted_ocr/rubrics_pdf.json';
if (!file_exists($jsonPath)) {
    fwrite(STDERR, "Missing $jsonPath - run extract_rubrics.py first.\n");
    exit(1);
}
$pdfRubrics = json_decode(file_get_contents($jsonPath), true);
if (!is_array($pdfRubrics)) {
    fwrite(STDERR, "Bad JSON in rubrics_pdf.json\n");
    exit(1);
}
// OCR fix-ups for known bad reads
$pdfRubrics = array_map(function ($r) {
    if ($r === 'NTHROPOPHOBIA') return 'ANTHROPOPHOBIA';
    return $r;
}, $pdfRubrics);
$pdfRubrics = array_values(array_unique(array_map('strtoupper', $pdfRubrics)));

echo "PDF rubric headings: " . count($pdfRubrics) . "\n";

// ---------- Helper: detect PDF-sourced row ----------
function isPdfSource(array $row): bool {
    $v = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($row['verified_source'] ?? '')));
    $r = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($row['repertory_source'] ?? '')));
    foreach (['kentmindrubrics130', 'kentmind130', 'kentmindrubrics110', 'kentmind110'] as $tag) {
        if ($v && strpos($v, $tag) !== false) return true;
        if ($r && strpos($r, $tag) !== false) return true;
    }
    return false;
}

$conn = Database::getInstance()->getConnection();

// ---------- For each PDF rubric, find Mind-category rows ----------
$stmt = $conn->prepare(
    "SELECT id, rubric, category, sub_category, complete_rubric,
            repertory_source, verified_source, verified_page, is_verified
       FROM repertory
      WHERE LOWER(category) = 'mind'
        AND UPPER(TRIM(rubric)) = ?"
);

$totalDupGroups = 0;
$totalToDelete  = 0;
$idsToDelete    = [];
$missingInDb    = [];
$noPdfSource    = [];

foreach ($pdfRubrics as $name) {
    $stmt->execute([$name]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $missingInDb[] = $name;
        continue;
    }
    $pdfRows  = array_values(array_filter($rows, 'isPdfSource'));
    $kentRows = array_values(array_filter($rows, fn($r) => !isPdfSource($r)));

    if (!$pdfRows) {
        // No PDF-sourced row exists for this rubric -> do NOT delete anything.
        $noPdfSource[] = $name . " (DB rows: " . count($rows) . ")";
        continue;
    }
    if (!$kentRows) {
        continue; // nothing to remove
    }
    $totalDupGroups++;
    echo "\n=== $name ===\n";
    echo "  KEEP (PDF):\n";
    foreach ($pdfRows as $r) {
        echo "    #{$r['id']} src='{$r['verified_source']}' rep='{$r['repertory_source']}' complete='{$r['complete_rubric']}'\n";
    }
    echo "  DELETE (non-PDF):\n";
    foreach ($kentRows as $r) {
        echo "    #{$r['id']} src='{$r['verified_source']}' rep='{$r['repertory_source']}' complete='{$r['complete_rubric']}'\n";
        $idsToDelete[] = (int)$r['id'];
        $totalToDelete++;
    }
}

echo "\n----- SUMMARY -----\n";
echo "PDF rubric names found in DB with both PDF + non-PDF rows: $totalDupGroups\n";
echo "Total non-PDF rows queued for deletion: $totalToDelete\n";
echo "PDF rubrics with NO row in DB at all: " . count($missingInDb) . "\n";
if ($missingInDb) echo "  -> " . implode(', ', $missingInDb) . "\n";
echo "PDF rubrics in DB but NO PDF-sourced row (skipped): " . count($noPdfSource) . "\n";
if ($noPdfSource) echo "  -> " . implode(', ', $noPdfSource) . "\n";

if (!$doDelete) {
    echo "\nDry run only. Re-run with --delete to remove the rows above.\n";
    exit(0);
}

if (!$idsToDelete) {
    echo "\nNothing to delete.\n";
    exit(0);
}

echo "\nDeleting " . count($idsToDelete) . " rows...\n";
$conn->beginTransaction();
try {
    $place = implode(',', array_fill(0, count($idsToDelete), '?'));
    // Cascade: delete mappings first
    $del1 = $conn->prepare("DELETE FROM repertory_remedies WHERE rubric_id IN ($place)");
    $del1->execute($idsToDelete);
    $mapCount = $del1->rowCount();
    $del2 = $conn->prepare("DELETE FROM repertory WHERE id IN ($place)");
    $del2->execute($idsToDelete);
    $rubCount = $del2->rowCount();
    $conn->commit();
    echo "Deleted $rubCount repertory rows and $mapCount repertory_remedies rows.\n";
} catch (Throwable $e) {
    $conn->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(2);
}
