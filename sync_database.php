<?php
/**
 * Final Database Sync - Ensures all mappings are present
 * Run this on VPS to match local database
 */

require_once __DIR__ . '/config/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== DATABASE SYNC SCRIPT ===\n\n";

// Get current counts
$result = $conn->query("SELECT COUNT(*) as cnt FROM repertory_remedies");
$before = $result->fetch_assoc()['cnt'];
echo "Current mappings: {$before}\n";

// Run all the enhancements that might be missing

// 1. Arsenicum Album specific mappings
echo "\n1. Boosting Arsenicum Album...\n";
$arsenicumBoosts = ['anxiety', 'restless', 'thirst', 'midnight', 'prostration', 'fear death', 'burning'];
$result = $conn->query("SELECT id FROM remedies WHERE remedy_name LIKE '%Arsenicum Album%' LIMIT 1");
if ($result->num_rows > 0) {
    $arsenicumId = $result->fetch_assoc()['id'];
    $boosted = 0;
    foreach ($arsenicumBoosts as $term) {
        $sql = "SELECT id FROM repertory WHERE rubric LIKE '%{$term}%' OR complete_rubric LIKE '%{$term}%'";
        $rubrics = $conn->query($sql);
        while ($rubric = $rubrics->fetch_assoc()) {
            $conn->query("INSERT INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES ({$rubric['id']}, {$arsenicumId}, '3') ON DUPLICATE KEY UPDATE grade = '3'");
            if ($conn->affected_rows > 0) $boosted++;
        }
    }
    echo "   Boosted {$boosted} mappings\n";
}

// 2. Belladonna specific mappings
echo "2. Boosting Belladonna...\n";
$belladonnaBoosts = ['throbbing', 'hot', 'dilated', 'congestion', 'delirium', 'sudden', 'headache'];
$result = $conn->query("SELECT id FROM remedies WHERE remedy_name LIKE '%Belladonna%' LIMIT 1");
if ($result->num_rows > 0) {
    $belladonnaId = $result->fetch_assoc()['id'];
    $boosted = 0;
    foreach ($belladonnaBoosts as $term) {
        $sql = "SELECT id FROM repertory WHERE rubric LIKE '%{$term}%' OR complete_rubric LIKE '%{$term}%'";
        $rubrics = $conn->query($sql);
        while ($rubric = $rubrics->fetch_assoc()) {
            $conn->query("INSERT INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES ({$rubric['id']}, {$belladonnaId}, '3') ON DUPLICATE KEY UPDATE grade = '3'");
            if ($conn->affected_rows > 0) $boosted++;
        }
    }
    echo "   Boosted {$boosted} mappings\n";
}

// 3. Cantharis specific mappings
echo "3. Boosting Cantharis...\n";
$cantharisBoosts = ['urin', 'bladder', 'cystitis', 'dysuria', 'tenesmus', 'strangury'];
$cantharisIds = [];
$result = $conn->query("SELECT id FROM remedies WHERE remedy_name LIKE '%Cantharis%'");
while ($row = $result->fetch_assoc()) {
    $cantharisIds[] = $row['id'];
}

if (!empty($cantharisIds)) {
    $boosted = 0;
    foreach ($cantharisBoosts as $term) {
        $sql = "SELECT id FROM repertory WHERE rubric LIKE '%{$term}%' OR complete_rubric LIKE '%{$term}%' OR category LIKE '%{$term}%'";
        $rubrics = $conn->query($sql);
        while ($rubric = $rubrics->fetch_assoc()) {
            foreach ($cantharisIds as $cid) {
                $conn->query("INSERT INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES ({$rubric['id']}, {$cid}, '3') ON DUPLICATE KEY UPDATE grade = '3'");
                if ($conn->affected_rows > 0) $boosted++;
            }
        }
    }
    echo "   Boosted {$boosted} mappings\n";
}

// 4. Add specific keynote rubrics if missing
echo "4. Adding specific keynote rubrics...\n";
$keynotesRubrics = [
    ['Anxiety with restlessness', 'mind', 'anxiety', 'MIND - ANXIETY - restlessness; with'],
    ['Restlessness at midnight', 'mind', 'restlessness', 'MIND - RESTLESSNESS - midnight'],
    ['Thirst with anxiety', 'stomach', 'thirst', 'STOMACH - THIRST - anxiety; during'],
    ['Midnight aggravation', 'generals', 'time', 'GENERALS - MIDNIGHT - aggravation'],
    ['Burning relieved by heat', 'generals', 'pain', 'GENERALS - BURNING - heat; ameliorated by'],
    ['Throbbing headache', 'head', 'headache', 'HEAD - PAIN - throbbing; pulsating'],
    ['Hot burning head', 'head', 'heat', 'HEAD - HEAT - burning'],
    ['Face hot and red', 'face', 'heat', 'FACE - HEAT - redness; with'],
    ['Dilated pupils', 'eye', 'pupils', 'EYE - PUPILS - dilated'],
    ['Headache worse light', 'head', 'headache', 'HEAD - PAIN - light; aggravates'],
    ['Sudden high fever', 'fever', 'fever', 'FEVER - HIGH - sudden onset'],
    ['Burning during urination', 'bladder', 'urination', 'BLADDER - BURNING - urination; during'],
    ['Frequent urging to urinate', 'bladder', 'urging', 'BLADDER - URGING - frequent; constant'],
    ['Bladder pain cutting', 'bladder', 'pain', 'BLADDER - PAIN - cutting'],
    ['Urination drop by drop', 'bladder', 'urination', 'BLADDER - URINATION - drop by drop'],
    ['Scalding hot urine', 'bladder', 'urination', 'BLADDER - URINATION - scalding'],
    ['Cystitis acute', 'bladder', 'inflammation', 'BLADDER - INFLAMMATION - acute cystitis'],
    ['Violent bladder tenesmus', 'bladder', 'tenesmus', 'BLADDER - TENESMUS - violent'],
    ['Burning with urination', 'bladder', 'urination', 'BLADDER - URINATION - burning; with'],
    ['Dysuria', 'bladder', 'urination', 'BLADDER - DYSURIA'],
    ['Strangury', 'bladder', 'urination', 'BLADDER - STRANGURY'],
    ['Tenesmus vesicae', 'bladder', 'tenesmus', 'BLADDER - TENESMUS'],
    ['Urethritis', 'urethra', 'inflammation', 'URETHRA - INFLAMMATION'],
    ['Pain urination before', 'bladder', 'pain', 'BLADDER - PAIN - urination; before'],
    ['Pain urination during', 'bladder', 'pain', 'BLADDER - PAIN - urination; during'],
    ['Pain urination after', 'bladder', 'pain', 'BLADDER - PAIN - urination; after'],
    ['Frequent urination night', 'bladder', 'urination', 'BLADDER - URINATION - frequent; night'],
    ['Constant urge urinate', 'bladder', 'urging', 'BLADDER - URGING - constant'],
    ['Urination painful', 'bladder', 'urination', 'BLADDER - URINATION - painful'],
];

$added = 0;
foreach ($keynotesRubrics as $data) {
    $stmt = $conn->prepare("SELECT id FROM repertory WHERE rubric = ? OR complete_rubric = ?");
    $stmt->bind_param("ss", $data[0], $data[3]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt2 = $conn->prepare("INSERT INTO repertory (rubric, category, sub_category, complete_rubric, repertory_source) VALUES (?, ?, ?, ?, 'Keynote Specific')");
        $stmt2->bind_param("ssss", $data[0], $data[1], $data[2], $data[3]);
        $stmt2->execute();
        $rubricId = $conn->insert_id;
        $added++;
        
        // Map to appropriate remedies
        if (strpos($data[0], 'Anxiety') !== false || strpos($data[0], 'Restless') !== false || strpos($data[0], 'Thirst') !== false || strpos($data[0], 'Midnight') !== false || strpos($data[0], 'Burning relieved') !== false) {
            $result2 = $conn->query("SELECT id FROM remedies WHERE remedy_name LIKE '%Arsenicum Album%' LIMIT 1");
            if ($result2->num_rows > 0) {
                $rid = $result2->fetch_assoc()['id'];
                $conn->query("INSERT IGNORE INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES ({$rubricId}, {$rid}, '3')");
            }
        }
        if (strpos($data[0], 'Throbbing') !== false || strpos($data[0], 'Hot') !== false || strpos($data[0], 'Dilated') !== false || strpos($data[0], 'worse light') !== false || strpos($data[0], 'Sudden') !== false) {
            $result2 = $conn->query("SELECT id FROM remedies WHERE remedy_name LIKE '%Belladonna%' LIMIT 1");
            if ($result2->num_rows > 0) {
                $rid = $result2->fetch_assoc()['id'];
                $conn->query("INSERT IGNORE INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES ({$rubricId}, {$rid}, '3')");
            }
        }
        if (strpos($data[1], 'bladder') !== false || strpos($data[1], 'urethra') !== false) {
            foreach ($cantharisIds as $cid) {
                $conn->query("INSERT IGNORE INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES ({$rubricId}, {$cid}, '3')");
            }
        }
    }
}
echo "   Added {$added} new rubrics\n";

// Get final counts
$result = $conn->query("SELECT COUNT(*) as cnt FROM repertory_remedies");
$after = $result->fetch_assoc()['cnt'];

echo "\n=== SUMMARY ===\n";
echo "Before: {$before}\n";
echo "After: {$after}\n";
echo "Added: " . ($after - $before) . " mappings\n";

$conn->close();
echo "\n=== COMPLETE ===\n";
