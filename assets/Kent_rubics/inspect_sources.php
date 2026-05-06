<?php
define('APP_ACCESS', true);
require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../includes/database.php';
$c = Database::getInstance()->getConnection();

echo "--- Distinct sources where category='mind' ---\n";
$q = $c->query("SELECT DISTINCT verified_source, repertory_source, COUNT(*) AS c
                  FROM repertory WHERE LOWER(category)='mind'
                 GROUP BY verified_source, repertory_source ORDER BY c DESC");
foreach ($q as $r) {
    echo str_pad((string)$r['c'], 6) .
         "v=" . ($r['verified_source'] ?? 'NULL') .
         " | r=" . ($r['repertory_source'] ?? 'NULL') . PHP_EOL;
}

echo "\n--- Sample ANXIETY rows ---\n";
$q = $c->query("SELECT id, rubric, category, sub_category, complete_rubric, repertory_source, verified_source, verified_page, is_verified
                  FROM repertory
                 WHERE UPPER(TRIM(rubric))='ANXIETY' AND LOWER(category)='mind'");
foreach ($q as $r) {
    print_r($r);
}

echo "\n--- All distinct sources in repertory (top 20) ---\n";
$q = $c->query("SELECT verified_source, repertory_source, COUNT(*) c FROM repertory
                 GROUP BY verified_source, repertory_source ORDER BY c DESC LIMIT 20");
foreach ($q as $r) {
    echo str_pad((string)$r['c'], 8) .
         "v=" . ($r['verified_source'] ?? 'NULL') .
         " | r=" . ($r['repertory_source'] ?? 'NULL') . PHP_EOL;
}
