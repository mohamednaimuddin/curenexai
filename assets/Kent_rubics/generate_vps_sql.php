<?php
/**
 * Generate a portable SQL file that performs the same Rule B deletion on any
 * MySQL server (e.g. your VPS). Run locally; upload the produced .sql to the
 * VPS and execute it once.
 */
define('APP_ACCESS', true);
require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../includes/database.php';

$pdfRubrics = json_decode(file_get_contents(__DIR__ . '/extracted_ocr/rubrics_pdf.json'), true);
$pdfRubrics = array_map(fn($r) => $r === 'NTHROPOPHOBIA' ? 'ANTHROPOPHOBIA' : $r, $pdfRubrics);
$pdfRubrics = array_values(array_unique(array_map('strtoupper', $pdfRubrics)));

$out  = "-- Auto-generated. Removes legacy duplicate Mind rubrics that are\n";
$out .= "-- already covered by Kent_Mind_1-10 / Kent_Mind_1-30 PDF imports.\n";
$out .= "-- Safe to run multiple times.\n\n";
$out .= "START TRANSACTION;\n\n";

$values = [];
foreach ($pdfRubrics as $name) {
    $esc = str_replace("'", "''", $name);
    $values[] = "'$esc'";
}
$inList = implode(",\n    ", $values);

$out .= "-- Step 1: collect rubric IDs to delete into a temp table\n";
$out .= "DROP TEMPORARY TABLE IF EXISTS _to_delete;\n";
$out .= "CREATE TEMPORARY TABLE _to_delete AS\n";
$out .= "SELECT id FROM repertory\n";
$out .= " WHERE LOWER(category) = 'mind'\n";
$out .= "   AND ( verified_source IS NULL OR verified_source NOT LIKE 'Kent\\_Mind\\_%' )\n";
$out .= "   AND EXISTS (\n";
$out .= "       SELECT 1 FROM (SELECT name FROM (\n";
$out .= "           SELECT $inList AS name\n";
$out .= "       ) AS x ) AS pdf\n";
$out .= "       WHERE UPPER(TRIM(repertory.rubric)) = pdf.name\n";
$out .= "          OR UPPER(TRIM(repertory.rubric)) LIKE CONCAT(pdf.name, ',%')\n";
$out .= "          OR UPPER(TRIM(repertory.rubric)) LIKE CONCAT(pdf.name, ' %')\n";
$out .= "   );\n\n";

// MySQL doesn't support multi-row VALUES inside SELECT cleanly across versions, so
// rebuild using UNION ALL for portability.
$unions = array_map(fn($v) => "SELECT $v AS name", $values);
$unionSql = implode("\n          UNION ALL ", $unions);

$out  = "-- Auto-generated. Removes legacy duplicate Mind rubrics that are\n";
$out .= "-- already covered by Kent_Mind_1-10 / Kent_Mind_1-30 PDF imports.\n";
$out .= "-- Safe to run on the VPS once. Idempotent.\n\n";
$out .= "START TRANSACTION;\n\n";
$out .= "DROP TEMPORARY TABLE IF EXISTS _pdf_names;\n";
$out .= "CREATE TEMPORARY TABLE _pdf_names (\n";
$out .= "    name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci PRIMARY KEY\n";
$out .= ") DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\n";
$out .= "INSERT INTO _pdf_names (name) VALUES\n  (" . implode("),\n  (", $values) . ");\n\n";

$out .= "DROP TEMPORARY TABLE IF EXISTS _to_delete;\n";
$out .= "CREATE TEMPORARY TABLE _to_delete (id INT PRIMARY KEY);\n";
$out .= "INSERT INTO _to_delete (id)\n";
$out .= "SELECT DISTINCT r.id\n";
$out .= "  FROM repertory r\n";
$out .= "  JOIN _pdf_names p\n";
$out .= "    ON UPPER(TRIM(r.rubric)) COLLATE utf8mb4_general_ci = p.name\n";
$out .= "    OR UPPER(TRIM(r.rubric)) COLLATE utf8mb4_general_ci LIKE CONCAT(p.name, ',%')\n";
$out .= "    OR UPPER(TRIM(r.rubric)) COLLATE utf8mb4_general_ci LIKE CONCAT(p.name, ' %')\n";
$out .= " WHERE LOWER(r.category) = 'mind'\n";
$out .= "   AND ( r.verified_source IS NULL OR r.verified_source NOT LIKE 'Kent\\_Mind\\_%' );\n\n";

$out .= "SELECT COUNT(*) AS rubric_rows_to_delete FROM _to_delete;\n\n";

$out .= "DELETE rr FROM repertory_remedies rr\n";
$out .= " JOIN _to_delete d ON d.id = rr.repertory_id;\n\n";

$out .= "DELETE r FROM repertory r\n";
$out .= " JOIN _to_delete d ON d.id = r.id;\n\n";

$out .= "COMMIT;\n";
$out .= "DROP TEMPORARY TABLE IF EXISTS _to_delete;\n";
$out .= "DROP TEMPORARY TABLE IF EXISTS _pdf_names;\n";

$file = __DIR__ . '/cleanup_kent_mind_duplicates.sql';
file_put_contents($file, $out);
echo "Wrote $file (" . strlen($out) . " bytes)\n";
