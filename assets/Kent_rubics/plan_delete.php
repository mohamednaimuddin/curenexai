<?php
/**
 * Show what would be deleted under different rules.
 * Rule A (narrow): only rows where rubric NAME (uppercased, trimmed) exactly equals
 *                  a heading from the 1-30 PDF AND verified_source is NOT a Kent_Mind_* PDF.
 * Rule B (broad): also delete rows whose rubric name STARTS WITH the heading (so
 *                  legacy rows like "Anxiety about future" get cleaned for ANXIETY),
 *                  excluding any row from a Kent_Mind_* PDF.
 */
define('APP_ACCESS', true);
require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../includes/database.php';

$pdfRubrics = json_decode(file_get_contents(__DIR__ . '/extracted_ocr/rubrics_pdf.json'), true);
$pdfRubrics = array_map(fn($r) => $r === 'NTHROPOPHOBIA' ? 'ANTHROPOPHOBIA' : $r, $pdfRubrics);
$pdfRubrics = array_values(array_unique(array_map('strtoupper', $pdfRubrics)));

$c = Database::getInstance()->getConnection();

function isPdfSource($v) {
    $v = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)$v));
    return strpos($v, 'kentmind') !== false; // matches Kent_Mind_1-10 and Kent_Mind_1-30
}

$narrowIds = [];
$broadIds  = [];
$narrowByRubric = [];
$broadByRubric  = [];

$qExact = $c->prepare("SELECT id, rubric, verified_source FROM repertory
                        WHERE LOWER(category)='mind' AND UPPER(TRIM(rubric)) = ?");
$qPrefix = $c->prepare("SELECT id, rubric, verified_source FROM repertory
                         WHERE LOWER(category)='mind'
                           AND (UPPER(TRIM(rubric)) = ?
                                OR UPPER(TRIM(rubric)) LIKE ?
                                OR UPPER(TRIM(rubric)) LIKE ?)");

foreach ($pdfRubrics as $name) {
    $qExact->execute([$name]);
    foreach ($qExact->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isPdfSource($row['verified_source'])) {
            $narrowIds[] = (int)$row['id'];
            $narrowByRubric[$name][] = $row;
        }
    }
    $qPrefix->execute([$name, $name . ',%', $name . ' %']);
    foreach ($qPrefix->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isPdfSource($row['verified_source'])) {
            $broadIds[] = (int)$row['id'];
            $broadByRubric[$name][] = $row;
        }
    }
}

$narrowIds = array_values(array_unique($narrowIds));
$broadIds  = array_values(array_unique($broadIds));

echo "Rule A (exact name match) -> would delete " . count($narrowIds) . " rows.\n";
echo "Rule B (exact OR 'NAME, ...' OR 'NAME ...' prefix) -> would delete " . count($broadIds) . " rows.\n\n";

echo "--- Rule A breakdown by rubric ---\n";
foreach ($narrowByRubric as $name => $rows) {
    echo str_pad($name, 25) . count($rows) . " rows\n";
}
echo "\n--- Rule B breakdown by rubric (top 30 by count) ---\n";
$counts = [];
foreach ($broadByRubric as $name => $rows) $counts[$name] = count($rows);
arsort($counts);
$shown = 0;
foreach ($counts as $name => $n) {
    echo str_pad($name, 25) . $n . " rows\n";
    if (++$shown >= 30) break;
}

// also show which Kent_Mind PDF rows remain for each
echo "\n--- For each PDF rubric: remaining PDF-sourced rows that we KEEP ---\n";
$qKeep = $c->prepare("SELECT verified_source, COUNT(*) c FROM repertory
                       WHERE LOWER(category)='mind'
                         AND (UPPER(TRIM(rubric)) = ?
                              OR UPPER(TRIM(rubric)) LIKE ?
                              OR UPPER(TRIM(rubric)) LIKE ?)
                         AND verified_source LIKE 'Kent_Mind_%'
                       GROUP BY verified_source");
foreach ($pdfRubrics as $name) {
    $qKeep->execute([$name, $name . ',%', $name . ' %']);
    $rows = $qKeep->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo str_pad($name, 25) . " (none)\n";
        continue;
    }
    $parts = [];
    foreach ($rows as $r) $parts[] = $r['verified_source'] . ':' . $r['c'];
    echo str_pad($name, 25) . implode(', ', $parts) . "\n";
}
