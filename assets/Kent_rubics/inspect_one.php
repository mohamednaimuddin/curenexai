<?php
define('APP_ACCESS', true);
require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../includes/database.php';
$c = Database::getInstance()->getConnection();

$pattern = $argv[1] ?? '%nxiety%';
$q = $c->prepare("SELECT id, rubric, sub_category, complete_rubric, verified_source, repertory_source
                    FROM repertory
                   WHERE LOWER(category) = 'mind'
                     AND rubric LIKE ?
                ORDER BY rubric, id LIMIT 200");
$q->execute([$pattern]);
foreach ($q as $r) {
    echo $r['id'] . " | " . $r['rubric'] .
         " | sub=" . $r['sub_category'] .
         " | full=" . $r['complete_rubric'] .
         " | v=" . ($r['verified_source'] ?? 'NULL') .
         " | r=" . $r['repertory_source'] . PHP_EOL;
}
