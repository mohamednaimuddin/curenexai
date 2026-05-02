<?php
/**
 * Add High-Grade Keynote Mappings for Polychrest Remedies
 * This improves accuracy by ensuring characteristic symptoms map strongly to their corresponding remedies
 */

require_once __DIR__ . '/config/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Adding Keynote-Specific High-Grade Mappings ===\n\n";

// Define keynote symptoms for major polychrest remedies
// These are the characteristic symptoms that should strongly indicate each remedy
$polychrestKeynotes = [
    'Arsenicum Album' => [
        'anxiety', 'restlessness', 'fear of death', 'midnight aggravation',
        'thirst for small sips', 'burning pain', 'prostration', 'fastidious',
        'chilly', 'wants warmth', 'diarrhea after midnight', 'food poisoning'
    ],
    'Aconitum Napellus' => [
        'sudden onset', 'fright', 'fear of death', 'cold wind exposure',
        'panic', 'restlessness', 'high fever', 'dry heat', 'thirst',
        'after shock', 'ailments from fright', 'intense fear'
    ],
    'Belladonna' => [
        'sudden onset', 'throbbing pain', 'hot face', 'dilated pupils',
        'high fever', 'delirium', 'red face', 'congestion', 'headache',
        'worse light', 'worse noise', 'worse jarring', 'right sided'
    ],
    'Bryonia Alba' => [
        'worse motion', 'better rest', 'irritable', 'thirst for large quantities',
        'dry mucous membranes', 'stitching pain', 'wants to be alone',
        'worse slightest motion', 'lies on painful side'
    ],
    'Pulsatilla' => [
        'weeping', 'changeable symptoms', 'better open air', 'thirstless',
        'worse heat', 'mild', 'yielding', 'desire consolation',
        'wandering pain', 'bland discharge', 'hormonal'
    ],
    'Ignatia Amara' => [
        'grief', 'sadness', 'sighing', 'disappointment', 'contradiction',
        'hysteria', 'globus', 'lump in throat', 'ailments from grief',
        'emotional', 'cannot cry', 'suppressed grief'
    ],
    'Sulphur' => [
        'burning', 'itching', 'worse heat', 'offensive odor', 'discharges',
        'skin eruption', 'philosophical', 'worse bathing', 'worse warmth of bed',
        'red orifices', 'hot feet', 'standing aggravates'
    ],
    'Lachesis' => [
        'jealousy', 'loquacity', 'talkative', 'left sided', 'worse sleep',
        'cannot bear tight clothing', 'choking', 'purple discoloration',
        'worse touch', 'worse pressure', 'suspicious'
    ],
    'Calcarea Carbonica' => [
        'chilly', 'perspiration', 'head sweats', 'sour secretions',
        'slow development', 'overweight', 'craves eggs', 'stubborn',
        'fear of insanity', 'cold damp feet', 'profuse sweating'
    ],
    'Cantharis' => [
        'burning urination', 'frequent urination', 'bladder pain',
        'urging to urinate', 'cutting pain', 'bloody urine', 'cystitis',
        'scalding urine', 'dysuria', 'inflammation'
    ],
    'Nux Vomica' => [
        'irritable', 'impatient', 'oversensitive', 'chilly',
        'worse morning', 'hangover', 'constipation', 'urging ineffectual',
        'spasmodic', 'competitive', 'overindulgence'
    ],
    'Sepia' => [
        'indifferent', 'aversion to family', 'hormonal', 'bearing down',
        'worse cold', 'better exercise', 'irritable', 'yellow complexion',
        'empty feeling', 'sensation of prolapse', 'menstrual'
    ],
    'Natrum Muriaticum' => [
        'grief', 'silent grief', 'cannot cry', 'reserved', 'desires salt',
        'worse consolation', 'headache worse sun', 'cold sores',
        'mapped tongue', 'oily skin', 'ailments from disappointment'
    ],
    'Phosphorus' => [
        'fear of thunder', 'sympathetic', 'open', 'hemorrhage',
        'burning', 'thirst for cold water', 'desires ice', 'worse twilight',
        'expressive', 'sensitive', 'anxiety about health'
    ],
    'Lycopodium' => [
        'right sided', 'worse 4-8pm', 'bloating', 'anticipation anxiety',
        'flatulence', 'craves sweets', 'irritable on waking',
        'domineering', 'low self-confidence', 'liver complaints'
    ],
    'Rhus Toxicodendron' => [
        'better motion', 'restless', 'stiffness', 'worse first motion',
        'better continued motion', 'worse damp', 'worse cold wet',
        'red triangle tip of tongue', 'joint pain'
    ],
    'Gelsemium' => [
        'weakness', 'trembling', 'drowsy', 'anticipation anxiety',
        'dull', 'heavy', 'flu', 'chills up spine', 'droopy eyelids',
        'thirstless', 'stage fright', 'diarrhea from anticipation'
    ],
    'Apis Mellifica' => [
        'stinging pain', 'better cold', 'worse heat', 'edema',
        'red swelling', 'jealousy', 'busy', 'thirstless',
        'sudden swelling', 'allergic reaction', 'urticaria'
    ],
    'Arnica Montana' => [
        'trauma', 'bruising', 'sore', 'bed feels hard', 'shock',
        'injury', 'overexertion', 'muscle soreness', 'sprains',
        'says nothing wrong', 'refuses help', 'fear of touch'
    ],
    'China Officinalis' => [
        'weakness from loss of fluids', 'bloating', 'debility',
        'periodicity', 'hemorrhage', 'anemia', 'sensitive to touch',
        'flatulence', 'diarrhea', 'after fluid loss'
    ],
    'Mercurius' => [
        'worse night', 'sweating without relief', 'offensive odor',
        'salivation', 'metallic taste', 'trembling', 'ulceration',
        'worse extremes of temperature', 'glandular swelling'
    ],
    'Silicea' => [
        'delicate', 'chilly', 'sweaty feet', 'suppuration',
        'lack of stamina', 'yielding', 'refined', 'splinters',
        'slow healing', 'offensive foot sweat'
    ],
    'Thuja Occidentalis' => [
        'warts', 'fixed ideas', 'secretive', 'left sided',
        'worse damp', 'vaccination effects', 'oily skin',
        'secretes at night', 'closed personality'
    ]
];

$totalAdded = 0;

foreach ($polychrestKeynotes as $remedyName => $keynotes) {
    // Find remedy ID
    $stmt = $conn->prepare("SELECT id FROM remedies WHERE remedy_name LIKE ? OR remedy_name LIKE ? LIMIT 1");
    $searchName1 = "%{$remedyName}%";
    $searchName2 = "%" . explode(' ', $remedyName)[0] . "%";
    $stmt->bind_param("ss", $searchName1, $searchName2);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "WARNING: Remedy not found: {$remedyName}\n";
        continue;
    }
    
    $remedy = $result->fetch_assoc();
    $remedyId = $remedy['id'];
    echo "Processing {$remedyName} (ID: {$remedyId}):\n";
    
    $addedForRemedy = 0;
    
    foreach ($keynotes as $keynote) {
        // Find matching rubrics
        $stmt2 = $conn->prepare("SELECT id, rubric FROM repertory WHERE rubric LIKE ? OR complete_rubric LIKE ? LIMIT 10");
        $searchKeynote = "%{$keynote}%";
        $stmt2->bind_param("ss", $searchKeynote, $searchKeynote);
        $stmt2->execute();
        $rubricResult = $stmt2->get_result();
        
        while ($rubric = $rubricResult->fetch_assoc()) {
            // Check if mapping exists
            $checkStmt = $conn->prepare("SELECT id, grade FROM repertory_remedies WHERE repertory_id = ? AND remedy_id = ?");
            $checkStmt->bind_param("ii", $rubric['id'], $remedyId);
            $checkStmt->execute();
            $existingResult = $checkStmt->get_result();
            
            if ($existingResult->num_rows > 0) {
                // Update to grade 3 if lower
                $existing = $existingResult->fetch_assoc();
                if ((int)$existing['grade'] < 3) {
                    $updateStmt = $conn->prepare("UPDATE repertory_remedies SET grade = '3' WHERE id = ?");
                    $updateStmt->bind_param("i", $existing['id']);
                    $updateStmt->execute();
                    $addedForRemedy++;
                }
            } else {
                // Add new high-grade mapping
                $insertStmt = $conn->prepare("INSERT INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES (?, ?, '3')");
                $insertStmt->bind_param("ii", $rubric['id'], $remedyId);
                $insertStmt->execute();
                $addedForRemedy++;
            }
        }
    }
    
    echo "  Added/upgraded {$addedForRemedy} mappings\n";
    $totalAdded += $addedForRemedy;
}

echo "\n=== SUMMARY ===\n";
echo "Total high-grade mappings added/upgraded: {$totalAdded}\n";

// Now let's also add some specific rubrics if they don't exist
echo "\n=== Adding Specific Keynote Rubrics ===\n";

$keynoteLRubrics = [
    ['Anxiety from midnight', 'mind', 'anxiety', 'MIND - ANXIETY - midnight; after'],
    ['Fear of death', 'mind', 'fear', 'MIND - FEAR - death; of'],
    ['Restlessness with anxiety', 'mind', 'restlessness', 'MIND - RESTLESSNESS - anxiety; with'],
    ['Thirst for small quantities', 'stomach', 'thirst', 'STOMACH - THIRST - small quantities; for'],
    ['Burning pain ameliorated by heat', 'generals', 'pain', 'GENERALS - BURNING PAIN - heat; ameliorated by'],
    ['Sudden onset of fever', 'fever', 'fever', 'FEVER - SUDDEN onset'],
    ['Fear after fright', 'mind', 'fear', 'MIND - FEAR - fright; after'],
    ['Throbbing headache', 'head', 'pain', 'HEAD - PAIN - throbbing'],
    ['Worse from motion', 'generals', 'modalities', 'GENERALS - MOTION - aggravates'],
    ['Better open air', 'generals', 'modalities', 'GENERALS - OPEN AIR - ameliorates'],
    ['Weeping easily', 'mind', 'weeping', 'MIND - WEEPING - easily'],
    ['Changeable symptoms', 'generals', 'generals', 'GENERALS - SYMPTOMS - changeable'],
    ['Grief ailments from', 'mind', 'grief', 'MIND - AILMENTS FROM - grief'],
    ['Sighing', 'respiration', 'respiration', 'RESPIRATION - SIGHING'],
    ['Jealousy', 'mind', 'jealousy', 'MIND - JEALOUSY'],
    ['Loquacity', 'mind', 'loquacity', 'MIND - LOQUACITY'],
    ['Worse after sleep', 'generals', 'modalities', 'GENERALS - SLEEP - after; aggravates'],
    ['Left sided complaints', 'generals', 'sides', 'GENERALS - SIDE - left'],
    ['Right sided complaints', 'generals', 'sides', 'GENERALS - SIDE - right'],
    ['Burning urination', 'bladder', 'urination', 'BLADDER - URINATION - burning'],
    ['Frequent urination', 'bladder', 'urination', 'BLADDER - URINATION - frequent'],
    ['Worse at 4pm', 'generals', 'time', 'GENERALS - TIME - 4 pm; at - aggravates'],
    ['Worse at midnight', 'generals', 'time', 'GENERALS - TIME - midnight; at - aggravates'],
    ['Better continued motion', 'generals', 'modalities', 'GENERALS - MOTION - continued - ameliorates'],
    ['Stiffness first motion', 'generals', 'motion', 'GENERALS - STIFFNESS - first motion; on'],
    ['Head sweat during sleep', 'perspiration', 'perspiration', 'PERSPIRATION - HEAD - sleep; during'],
    ['Sweat without relief', 'perspiration', 'perspiration', 'PERSPIRATION - relief; without'],
];

$rubricAdded = 0;
foreach ($keynoteLRubrics as $rubricData) {
    $rubric = $rubricData[0];
    $category = $rubricData[1];
    $subCategory = $rubricData[2];
    $completeRubric = $rubricData[3];
    
    // Check if exists
    $checkStmt = $conn->prepare("SELECT id FROM repertory WHERE rubric = ? OR complete_rubric = ?");
    $checkStmt->bind_param("ss", $rubric, $completeRubric);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $insertStmt = $conn->prepare("INSERT INTO repertory (rubric, category, sub_category, complete_rubric, repertory_source) VALUES (?, ?, ?, ?, 'Kent Repertory - Keynotes')");
        $insertStmt->bind_param("ssss", $rubric, $category, $subCategory, $completeRubric);
        $insertStmt->execute();
        $rubricAdded++;
        
        // Map to appropriate remedies with grade 3
        $rubricId = $conn->insert_id;
        
        // Map based on rubric content
        $remedyMappings = [];
        if (stripos($rubric, 'midnight') !== false || stripos($rubric, 'anxiety') !== false) {
            $remedyMappings[] = 'Arsenicum';
        }
        if (stripos($rubric, 'fright') !== false || stripos($rubric, 'sudden') !== false) {
            $remedyMappings[] = 'Aconitum';
        }
        if (stripos($rubric, 'throbbing') !== false) {
            $remedyMappings[] = 'Belladonna';
        }
        if (stripos($rubric, 'motion') !== false && stripos($rubric, 'worse') !== false) {
            $remedyMappings[] = 'Bryonia';
        }
        if (stripos($rubric, 'open air') !== false || stripos($rubric, 'weeping') !== false || stripos($rubric, 'changeable') !== false) {
            $remedyMappings[] = 'Pulsatilla';
        }
        if (stripos($rubric, 'grief') !== false || stripos($rubric, 'sighing') !== false) {
            $remedyMappings[] = 'Ignatia';
        }
        if (stripos($rubric, 'jealousy') !== false || stripos($rubric, 'loquacity') !== false || stripos($rubric, 'left side') !== false) {
            $remedyMappings[] = 'Lachesis';
        }
        if (stripos($rubric, 'urination') !== false || stripos($rubric, 'burning urin') !== false || stripos($rubric, 'bladder') !== false) {
            $remedyMappings[] = 'Cantharis';
        }
        if (stripos($rubric, 'right side') !== false || stripos($rubric, '4pm') !== false) {
            $remedyMappings[] = 'Lycopodium';
        }
        if (stripos($rubric, 'continued motion') !== false || stripos($rubric, 'stiffness') !== false) {
            $remedyMappings[] = 'Rhus';
        }
        if (stripos($rubric, 'head sweat') !== false) {
            $remedyMappings[] = 'Calcarea';
        }
        if (stripos($rubric, 'without relief') !== false) {
            $remedyMappings[] = 'Mercurius';
        }
        
        foreach ($remedyMappings as $remedySearch) {
            $findStmt = $conn->prepare("SELECT id FROM remedies WHERE remedy_name LIKE ? LIMIT 1");
            $search = "%{$remedySearch}%";
            $findStmt->bind_param("s", $search);
            $findStmt->execute();
            $remedyResult = $findStmt->get_result();
            
            if ($remedyResult->num_rows > 0) {
                $remedyRow = $remedyResult->fetch_assoc();
                $mapStmt = $conn->prepare("INSERT IGNORE INTO repertory_remedies (repertory_id, remedy_id, grade) VALUES (?, ?, '3')");
                $mapStmt->bind_param("ii", $rubricId, $remedyRow['id']);
                $mapStmt->execute();
            }
        }
    }
}

echo "New specific keynote rubrics added: {$rubricAdded}\n";
echo "\n=== COMPLETE ===\n";

$conn->close();
