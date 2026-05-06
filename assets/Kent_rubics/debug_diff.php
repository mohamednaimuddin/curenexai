<?php
define('APP_ACCESS', true);
require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../includes/database.php';

$pdfRubrics = json_decode(file_get_contents(__DIR__ . '/extracted_ocr/rubrics_pdf.json'), true);
$pdfRubrics = array_map(fn($r) => $r === 'NTHROPOPHOBIA' ? 'ANTHROPOPHOBIA' : $r, $pdfRubrics);
$pdfRubrics = array_values(array_unique(array_map('strtoupper', $pdfRubrics)));

$c = Database::getInstance()->getConnection();

// Variant A: same SQL as execute_delete_ruleB
$qA = $c->prepare(
    "SELECT id, rubric, verified_source FROM repertory
      WHERE LOWER(category)='mind'
        AND (UPPER(TRIM(rubric))=? OR UPPER(TRIM(rubric)) LIKE ? OR UPPER(TRIM(rubric)) LIKE ?)
        AND (verified_source IS NULL OR verified_source NOT LIKE 'Kent_Mind_%')"
);

// Variant B: filter applied in PHP
$qB = $c->prepare(
    "SELECT id, rubric, verified_source FROM repertory
      WHERE LOWER(category)='mind'
        AND (UPPER(TRIM(rubric))=? OR UPPER(TRIM(rubric)) LIKE ? OR UPPER(TRIM(rubric)) LIKE ?)"
);

$totalA = 0; $totalB = 0;
foreach ($pdfRubrics as $name) {
    $qA->execute([$name, $name . ',%', $name . ' %']);
    $a = $qA->fetchAll(PDO::FETCH_ASSOC);
    $qB->execute([$name, $name . ',%', $name . ' %']);
    $b = array_filter($qB->fetchAll(PDO::FETCH_ASSOC), function ($r) {
        $v = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($r['verified_source'] ?? '')));
        return strpos($v, 'kentmind') === false;
    });
    if (count($a) !== count($b)) {
        echo "DIFF $name : A=" . count($a) . " B=" . count($b) . "\n";
        echo "  Sample A row: "; print_r($a[0] ?? null);
        echo "  Sample B row: "; print_r(array_values($b)[0] ?? null);
    }
    $totalA += count($a);
    $totalB += count($b);
}
echo "Total A: $totalA  Total B: $totalB\n";
