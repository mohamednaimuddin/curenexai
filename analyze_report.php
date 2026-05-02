<?php
/**
 * Analyze QA Report to understand RAG issues
 */

require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/tests/reports/QA_Report_2026-04-19_15-23-56.xlsx';
$spreadsheet = IOFactory::load($file);

$sheetNames = $spreadsheet->getSheetNames();
echo "=== SHEETS ===\n";
print_r($sheetNames);

// Helper to find sheet by partial name
function findSheet($spreadsheet, $partial) {
    foreach ($spreadsheet->getSheetNames() as $name) {
        if (stripos($name, $partial) !== false) {
            return $spreadsheet->getSheetByName($name);
        }
    }
    return null;
}

// Read Summary
$sheet = findSheet($spreadsheet, 'Summary');
if ($sheet) {
    echo "\n=== SUMMARY ===\n";
    for ($row = 1; $row <= 25; $row++) {
        $a = $sheet->getCell('A' . $row)->getValue();
        $b = $sheet->getCell('B' . $row)->getValue();
        if ($a !== null || $b !== null) {
            echo ($a ?? '') . ': ' . ($b ?? '') . "\n";
        }
    }
}

// Read Issues
$sheet = findSheet($spreadsheet, 'Issues');
if ($sheet) {
    echo "\n=== ISSUES ===\n";
    $highestRow = min(25, $sheet->getHighestRow());
    for ($row = 1; $row <= $highestRow; $row++) {
        $data = [];
        for ($col = 'A'; $col <= 'H'; $col++) {
            $val = $sheet->getCell($col . $row)->getValue();
            if ($val !== null && $val !== '') {
                $data[] = substr((string)$val, 0, 35);
            }
        }
        if (!empty($data)) {
            echo implode(' | ', $data) . "\n";
        }
    }
}

// Read AI Diagnosis
$sheet = findSheet($spreadsheet, 'AI');
if ($sheet) {
    echo "\n=== AI DIAGNOSIS (first 15 rows) ===\n";
    $highestRow = min(15, $sheet->getHighestRow());
    for ($row = 1; $row <= $highestRow; $row++) {
        $data = [];
        for ($col = 'A'; $col <= 'L'; $col++) {
            $val = $sheet->getCell($col . $row)->getValue();
            if ($val !== null && $val !== '') {
                $data[] = substr((string)$val, 0, 30);
            }
        }
        if (!empty($data)) {
            echo implode(' | ', $data) . "\n";
        }
    }
}

// Read Remedy Validation
$sheet = findSheet($spreadsheet, 'Remedy');
if ($sheet) {
    echo "\n=== REMEDY VALIDATION (first 15 rows) ===\n";
    $highestRow = min(15, $sheet->getHighestRow());
    for ($row = 1; $row <= $highestRow; $row++) {
        $data = [];
        for ($col = 'A'; $col <= 'H'; $col++) {
            $val = $sheet->getCell($col . $row)->getValue();
            if ($val !== null && $val !== '') {
                $data[] = substr((string)$val, 0, 35);
            }
        }
        if (!empty($data)) {
            echo implode(' | ', $data) . "\n";
        }
    }
}

// Read Improvements
$sheet = findSheet($spreadsheet, 'Improvement');
if ($sheet) {
    echo "\n=== IMPROVEMENT SUGGESTIONS ===\n";
    $highestRow = min(20, $sheet->getHighestRow());
    for ($row = 1; $row <= $highestRow; $row++) {
        $data = [];
        for ($col = 'A'; $col <= 'E'; $col++) {
            $val = $sheet->getCell($col . $row)->getValue();
            if ($val !== null && $val !== '') {
                $data[] = substr((string)$val, 0, 50);
            }
        }
        if (!empty($data)) {
            echo implode(' | ', $data) . "\n";
        }
    }
}
