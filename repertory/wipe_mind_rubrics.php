<?php
/**
 * Wipe all `category = mind` rubrics and their mappings, after taking a
 * SQL backup. Used before re-importing a clean A-Z dataset.
 *
 * Usage:
 *   php repertory/wipe_mind_rubrics.php           # dry-run + write backup
 *   php repertory/wipe_mind_rubrics.php --apply   # commit deletions
 */
define('APP_ACCESS', true);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/database.php';

$apply = in_array('--apply', $argv ?? [], true);

$pdo = Database::getInstance()->getConnection();

$rubricCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM repertory WHERE LOWER(category) = 'mind'"
)->fetchColumn();
$mapCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM repertory_remedies rr
       JOIN repertory r ON r.id = rr.repertory_id
      WHERE LOWER(r.category) = 'mind'"
)->fetchColumn();

echo "About to delete:\n";
echo "  mind rubrics  : $rubricCount\n";
echo "  mind mappings : $mapCount\n";

// Backup as INSERT statements
$backupDir = __DIR__ . '/../vpsbackup';
if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);
$ts = date('Ymd_His');
$backupPath = $backupDir . "/mind_rubrics_backup_$ts.sql";
$bf = fopen($backupPath, 'w');
fwrite($bf, "-- Mind rubrics & mappings backup taken " . date('c') . "\n");
fwrite($bf, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$res = $pdo->query("SELECT * FROM repertory WHERE LOWER(category)='mind' ORDER BY id");
$cols = null;
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    if ($cols === null) {
        $cols = array_keys($row);
        fwrite($bf, "-- Table: repertory (mind only)\n");
    }
    $vals = array_map(function ($v) use ($pdo) {
        return $v === null ? 'NULL' : $pdo->quote((string)$v);
    }, $row);
    fwrite($bf, "INSERT INTO `repertory` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n");
}

$res = $pdo->query(
    "SELECT rr.* FROM repertory_remedies rr
       JOIN repertory r ON r.id = rr.repertory_id
      WHERE LOWER(r.category)='mind' ORDER BY rr.id"
);
$cols = null;
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    if ($cols === null) {
        $cols = array_keys($row);
        fwrite($bf, "\n-- Table: repertory_remedies (mind only)\n");
    }
    $vals = array_map(function ($v) use ($pdo) {
        return $v === null ? 'NULL' : $pdo->quote((string)$v);
    }, $row);
    fwrite($bf, "INSERT INTO `repertory_remedies` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n");
}
fwrite($bf, "\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($bf);
echo "Backup written: $backupPath (" . number_format(filesize($backupPath)) . " bytes)\n";

if (!$apply) {
    echo "DRY RUN -- no deletions. Re-run with --apply to commit.\n";
    exit(0);
}

$pdo->beginTransaction();
$d1 = $pdo->exec(
    "DELETE rr FROM repertory_remedies rr
       JOIN repertory r ON r.id = rr.repertory_id
      WHERE LOWER(r.category) = 'mind'"
);
$d2 = $pdo->exec("DELETE FROM repertory WHERE LOWER(category) = 'mind'");
$pdo->commit();

echo "Deleted mappings: $d1\n";
echo "Deleted rubrics : $d2\n";
echo "DONE.\n";
