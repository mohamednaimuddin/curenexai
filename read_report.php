<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'C:\xampp\htdocs\homeo.naimu.space\tests\reports\QA_Report_2026-04-19_16-08-59.xlsx';
$spreadsheet = IOFactory::load($file);

// Sheets: 0=Summary, 1=Patients, 2=Consultations, 3=AI Diagnosis, 4=Remedy Validation, 5=Issues, 6=Improvements

// Read Summary sheet (index 0)
$summary = $spreadsheet->getSheet(0);
echo "=== SUMMARY ===\n";
foreach ($summary->getRowIterator(1, 25) as $row) {
    $cells = [];
    foreach ($row->getCellIterator('A', 'B') as $cell) {
        $cells[] = $cell->getValue();
    }
    if (!empty($cells[0])) echo implode(': ', array_filter($cells)) . "\n";
}

// Check AI Diagnosis sheet (index 3)
$aiSheet = $spreadsheet->getSheet(3);
echo "\n=== AI DIAGNOSIS (First 15 rows) ===\n";
$rowCount = 0;
foreach ($aiSheet->getRowIterator() as $row) {
    if ($rowCount++ > 15) break;
    $cells = [];
    foreach ($row->getCellIterator('A', 'H') as $cell) {
        $val = $cell->getValue() ?? '';
        $cells[] = substr($val, 0, 25);
    }
    echo implode(' | ', $cells) . "\n";
}

// Read Remedy Validation sheet (index 4)
$remedySheet = $spreadsheet->getSheet(4);
echo "\n=== REMEDY VALIDATION (First 15 rows) ===\n";
$rowCount = 0;
foreach ($remedySheet->getRowIterator() as $row) {
    if ($rowCount++ > 15) break;
    $cells = [];
    foreach ($row->getCellIterator('A', 'G') as $cell) {
        $val = $cell->getValue() ?? '';
        $cells[] = substr($val, 0, 30);
    }
    echo implode(' | ', $cells) . "\n";
}

// Count matches
$totalMatches = 0;
$totalRows = 0;
foreach ($remedySheet->getRowIterator(2) as $row) {
    $totalRows++;
    $matchScore = $remedySheet->getCell('F' . $row->getRowIndex())->getValue();
    if (is_numeric($matchScore) && $matchScore >= 50) {
        $totalMatches++;
    }
}
echo "\n=== REMEDY MATCH STATS ===\n";
echo "Total Validations: $totalRows\n";
echo "Good Matches (>=50%): $totalMatches\n";
if ($totalRows > 0) {
    echo "Good Match Rate: " . round($totalMatches / $totalRows * 100, 1) . "%\n";
}

// Issues sheet (index 5)
$issuesSheet = $spreadsheet->getSheet(5);
if ($issuesSheet) {
    echo "\n=== ISSUES ===\n";
    $rowCount = 0;
    foreach ($issuesSheet->getRowIterator() as $row) {
        if ($rowCount++ > 10) break;
        $cells = [];
        foreach ($row->getCellIterator('A', 'C') as $cell) {
            $val = $cell->getValue() ?? '';
            $cells[] = substr($val, 0, 50);
        }
        echo implode(' | ', $cells) . "\n";
    }
}
