<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();

// Get search parameters
$searchQuery = sanitize($_GET['q'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$selectedRubrics = $_GET['rubrics'] ?? [];

// FIX (Issue 6): default initialiser so the UI template can reference this
// flag even when the search branch below did not run.
$stopWordsOnlyQuery = false;

// Only show the Kent verified badge when verification source maps to
// Kent Mind Rubrics pages 1-10 or 1-30.
function isKentMindRubrics1To10VerifiedSource(array $row): bool {
    if (!empty($row['is_verified'])) {
        return true;
    }

    $normalizedSource = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($row['verified_source'] ?? '')));

    $acceptedFragments = [
        'kentmind110',
        'kentmind130',
        'kentmindrubrics110',
        'kentmindrubrics130',
        'kentmindrubrics110page',
        'kentmindrubrics130page',
        'kentmindrubrics110pagepdf',
        'kentmindrubrics130pagepdf'
    ];

    foreach ($acceptedFragments as $fragment) {
        if (strpos($normalizedSource, $fragment) !== false) {
            return true;
        }
    }

    return false;
}

// Get repertory statistics
$repertoryStats = DB::queryOne("SELECT COUNT(*) as rubric_count, (SELECT COUNT(*) FROM repertory_remedies) as mapping_count FROM repertory");
$totalRubricsInDB = $repertoryStats['rubric_count'] ?? 0;
$totalMappings = $repertoryStats['mapping_count'] ?? 0;

// Get category counts from database
$categoryCountsResult = DB::query("SELECT LOWER(category) as cat, COUNT(*) as cnt FROM repertory GROUP BY LOWER(category) ORDER BY cnt DESC");
$categoryCounts = [];
foreach ($categoryCountsResult as $row) {
    $categoryCounts[$row['cat']] = $row['cnt'];
}

// Available categories with counts (comprehensive list from Kent's Repertory)
$categories = [
    'mind' => 'Mind',
    'vertigo' => 'Vertigo',
    'head' => 'Head',
    'eye' => 'Eye',
    'vision' => 'Vision',
    'ear' => 'Ear',
    'hearing' => 'Hearing',
    'nose' => 'Nose',
    'face' => 'Face',
    'mouth' => 'Mouth',
    'teeth' => 'Teeth',
    'throat' => 'Throat',
    'stomach' => 'Stomach',
    'abdomen' => 'Abdomen',
    'rectum' => 'Rectum',
    'stool' => 'Stool',
    'bladder' => 'Bladder',
    'kidneys' => 'Kidneys',
    'urine' => 'Urine',
    'urinary' => 'Urinary',
    'male' => 'Male',
    'female' => 'Female',
    'larynx' => 'Larynx',
    'respiration' => 'Respiration',
    'respiratory' => 'Respiratory',
    'cough' => 'Cough',
    'expectoration' => 'Expectoration',
    'chest' => 'Chest',
    'heart' => 'Heart',
    'back' => 'Back',
    'extremities' => 'Extremities',
    'skin' => 'Skin',
    'sleep' => 'Sleep',
    'perspiration' => 'Perspiration',
    'fever' => 'Fever',
    'general' => 'General',
    'generalities' => 'Generalities'
];

// Pagination for rubrics search
$rubrics = [];
$rubricPage = isset($_GET['rubric_page']) ? max(1, intval($_GET['rubric_page'])) : 1;
$rubricsPerPage = 20;
$rubricOffset = ($rubricPage - 1) * $rubricsPerPage;
$totalRubrics = 0;
$totalRubricPages = 0;

// Synonym map for better matching - COMPREHENSIVE VERSION
$synonymMap = [
    // MENTAL/EMOTIONAL - Extended
    'angry' => ['anger', 'irritability', 'rage', 'wrath', 'fury', 'cross', 'peevish', 'violent', 'choleric'],
    'anger' => ['angry', 'irritable', 'rage', 'wrath', 'cross', 'peevish', 'fury', 'violent'],
    'irritable' => ['irritability', 'angry', 'cross', 'peevish', 'touchy', 'snappish', 'fretful', 'impatient'],
    'contradiction' => ['contradict', 'opposed', 'disagree', 'intolerant', 'cannot bear', 'contradicted'],
    'contradict' => ['contradiction', 'opposed', 'disagree', 'intolerant', 'contradicted'],
    'contradicted' => ['contradiction', 'contradict', 'intolerant', 'opposed'],
    'fear' => ['anxiety', 'fright', 'dread', 'terror', 'apprehension', 'fearful', 'afraid', 'phobia'],
    'afraid' => ['fear', 'anxiety', 'fright', 'apprehensive', 'fearful', 'scared'],
    'anxiety' => ['anxious', 'fear', 'worry', 'apprehension', 'restlessness', 'nervous', 'tension'],
    'sad' => ['sadness', 'grief', 'sorrow', 'melancholy', 'depression', 'dejection', 'gloomy'],
    'depressed' => ['depression', 'sad', 'melancholy', 'despondent', 'hopeless', 'despair'],
    'weeping' => ['crying', 'tears', 'sobbing', 'weeps', 'tearful', 'lachrymation'],
    'weeps' => ['weeping', 'crying', 'tears', 'sobbing', 'tearful'],
    'crying' => ['weeping', 'tears', 'weeps', 'sobbing', 'tearful'],
    'consoled' => ['consolation', 'comfort', 'sympathy'],
    'consolation' => ['consoled', 'comfort', 'sympathy'],
    'restless' => ['restlessness', 'cannot rest', 'tossing', 'fidgety', 'agitation', 'cannot sit still'],
    'jealous' => ['jealousy', 'envy', 'suspicious', 'possessive'],
    'jealousy' => ['jealous', 'envy', 'suspicious', 'possessive'],
    'suspicious' => ['suspicion', 'distrust', 'mistrustful', 'jealous', 'paranoid'],
    'suspicion' => ['suspicious', 'distrust', 'mistrustful', 'jealous'],
    'grief' => ['sorrow', 'mourning', 'loss', 'bereavement', 'sadness', 'ailments from grief'],
    'fright' => ['fear', 'scared', 'terror', 'shock', 'startle', 'ailments from fright'],
    'indifferent' => ['indifference', 'apathy', 'uncaring', 'detached', 'withdrawn'],
    'concentration' => ['focus', 'attention', 'mental effort', 'difficulty concentrating'],
    'forgetful' => ['forgetfulness', 'memory', 'memory weak', 'absent minded'],
    'confused' => ['confusion', 'bewildered', 'disoriented', 'dazed'],
    'suicidal' => ['suicide', 'death wish', 'wants to die', 'self destruction'],
    'carried' => ['carry', 'desires to be carried', 'wants to be carried'],
    'darkness' => ['dark', 'night', 'in the dark'],
    'dark' => ['darkness', 'night', 'obscurity'],
    
    // NEW - Failed query synonyms
    'elderly' => ['old', 'aged', 'senile', 'senility', 'old age', 'aging'],
    'old' => ['elderly', 'aged', 'senile', 'senility'],
    'talks' => ['talking', 'speech', 'somniloquy', 'spoken', 'speaks', 'talk'],
    'talking' => ['talks', 'speech', 'somniloquy', 'speaks', 'talk', 'loquacious'],
    'speaks' => ['speaking', 'speech', 'talks', 'talking', 'spoken', 'hasty speech'],
    'speech' => ['speaks', 'talks', 'talking', 'loquacity', 'voice'],
    'laughter' => ['laughing', 'laugh', 'hilarity', 'mirth', 'giggling'],
    'laughing' => ['laughter', 'laugh', 'hilarity', 'mirth', 'giggling'],
    'excessive' => ['too much', 'overmuch', 'immoderate'],
    'hurried' => ['hurry', 'haste', 'hasty', 'rushed', 'impatient', 'rush'],
    'hurry' => ['hurried', 'haste', 'hasty', 'rushed', 'impatient'],
    'rush' => ['hurry', 'haste', 'hasty', 'hurried', 'impatient'],
    'hastily' => ['hasty', 'hurried', 'haste', 'quick', 'rush'],
    'hasty' => ['hastily', 'hurried', 'haste', 'quick', 'rush'],
    'screaming' => ['screams', 'scream', 'shrieking', 'shrieks', 'crying out', 'cries'],
    'shrieking' => ['screaming', 'screams', 'shrieks', 'crying out'],
    'moaning' => ['moan', 'moans', 'groaning', 'groan', 'lamenting'],
    'groaning' => ['groan', 'groans', 'moaning', 'moan', 'lamenting'],
    'loves' => ['love', 'affection', 'fond', 'attached'],
    'animals' => ['animal', 'pets', 'dogs', 'cats'],
    'impatient' => ['impatience', 'hurried', 'hasty', 'irritable', 'restless'],
    'impatience' => ['impatient', 'hurried', 'hasty', 'irritable'],
    'intolerant' => ['intolerance', 'cannot bear', 'cannot endure'],
    'indecisive' => ['indecision', 'irresolution', 'irresolute', 'undecided', 'hesitating'],
    'indecision' => ['indecisive', 'irresolution', 'irresolute', 'undecided'],
    'decisions' => ['decide', 'decision', 'indecision', 'irresolution'],
    'vanity' => ['vain', 'egotism', 'conceit', 'pride', 'haughty'],
    'pride' => ['proud', 'haughty', 'vanity', 'egotism', 'arrogant'],
    'proud' => ['pride', 'haughty', 'vanity', 'arrogant'],
    'timid' => ['timidity', 'shy', 'bashful', 'cowardice', 'fearful'],
    'shy' => ['shyness', 'timid', 'bashful', 'retiring'],
    // FIX (Issue 1): duplicate 'suspicious' key removed here; canonical definition at
    // line 95 already covers suspicion/distrust/mistrustful/jealous/possessive.
    // Merging 'paranoid' into the earlier entry keeps all coverage in one place.
    'delusions' => ['delusion', 'illusions', 'hallucinations', 'imagines'],
    'hallucinations' => ['hallucination', 'delusions', 'visions', 'sees'],
    'mania' => ['manic', 'excitement', 'frenzy', 'madness'],
    'delirium' => ['delirious', 'raving', 'incoherent'],
    'muttering' => ['mutters', 'mumbling', 'murmuring'],
    'cursing' => ['curses', 'swearing', 'profanity', 'blasphemy'],
    'biting' => ['bites', 'bite', 'gnawing'],
    'striking' => ['strikes', 'hitting', 'violent'],
    'desires' => ['desire', 'wants', 'craving', 'longing'],
    'aversion' => ['aversions', 'dislikes', 'loathing', 'disgust'],
    
    // PAIN TYPES - Extended
    'headache' => ['head pain', 'cephalalgia', 'head ache', 'migraine', 'pain in head'],
    'migraine' => ['headache', 'head pain', 'one-sided headache', 'hemicrania'],
    // FIX (Issue 3): dropped bare 'hot' — LIKE '%hot%' matched unrelated rubrics
    // (hot drinks, hot weather, hot flashes…). Kept 'heat sensation' which is specific.
    'burning' => ['burnt', 'scalding', 'heat sensation', 'smarting'],
    'throbbing' => ['pulsating', 'pulsation', 'beating', 'hammering', 'pounding'],
    'sharp' => ['stitching', 'stabbing', 'lancinating', 'cutting', 'shooting', 'pricking'],
    'dull' => ['aching', 'heavy', 'pressing', 'bearing down', 'dragging'],
    'cramping' => ['cramp', 'spasm', 'spasmodic', 'griping', 'colic'],
    'neuralgic' => ['neuralgia', 'nerve pain', 'shooting', 'radiating'],
    
    // GENERAL SYMPTOMS - Extended
    'tired' => ['fatigue', 'exhaustion', 'weakness', 'prostration', 'lassitude', 'weary'],
    'weak' => ['weakness', 'debility', 'prostration', 'exhaustion', 'feeble', 'powerless'],
    // FIX (Issue 3): dropped bare 'hot'. 'heat' kept because Kent rubrics under
    // FEVER use 'HEAT' literally (e.g. 'FEVER — HEAT, burning').
    'fever' => ['pyrexia', 'febrile', 'temperature', 'heat'],
    'chill' => ['chilly', 'coldness', 'shivering', 'cold', 'freezing'],
    'sweat' => ['sweating', 'perspiration', 'diaphoresis', 'night sweats'],
    'thirst' => ['thirsty', 'desire for water', 'drinks', 'unquenchable thirst'],
    'thirstless' => ['no thirst', 'absence of thirst', 'thirstlessness'],
    'hungry' => ['hunger', 'appetite', 'ravenous', 'voracious', 'starving'],
    'nausea' => ['nauseous', 'sickness', 'sick', 'qualmish', 'queasy'],
    'vomiting' => ['vomit', 'emesis', 'throwing up', 'retching', 'regurgitation'],
    'diarrhea' => ['loose stool', 'watery stool', 'dysentery', 'flux', 'frequent stool'],
    'constipation' => ['constipated', 'hard stool', 'difficult stool', 'no stool', 'costive'],
    'cough' => ['coughing', 'tussis', 'dry cough', 'hacking'],
    'asthma' => ['asthmatic', 'wheezing', 'breathless', 'dyspnea'],
    'dyspnea' => ['breathlessness', 'shortness of breath', 'difficult breathing', 'air hunger'],
    
    // COLORS/DISCOLORATION - Extended
    'blue' => ['blueness', 'cyanosis', 'cyanotic', 'discoloration', 'livid', 'purple', 'dusky'],
    'blueness' => ['blue', 'cyanosis', 'discoloration', 'livid', 'cyanotic'],
    'pale' => ['pallor', 'paleness', 'white', 'discoloration', 'blanched', 'anemic'],
    'red' => ['redness', 'flushed', 'erythema', 'discoloration', 'congested', 'crimson'],
    'yellow' => ['yellowness', 'jaundice', 'icterus', 'discoloration', 'sallow'],
    'black' => ['blackness', 'dark', 'discoloration', 'gangrenous', 'ecchymosis'],
    'purple' => ['purplish', 'livid', 'blue', 'discoloration', 'violaceous'],
    
    // BODY PARTS - Extended
    'lip' => ['lips', 'labial', 'mouth'],
    'lips' => ['lip', 'labial', 'mouth'],
    'face' => ['facial', 'countenance', 'cheek', 'cheeks', 'complexion'],
    'cheek' => ['cheeks', 'face', 'facial', 'malar'],
    'tongue' => ['lingual', 'glossal', 'taste'],
    'eye' => ['eyes', 'ocular', 'optic', 'vision'],
    'ear' => ['ears', 'aural', 'auditory', 'hearing'],
    'nose' => ['nasal', 'nares', 'nostrils', 'olfactory'],
    'head' => ['cephalic', 'cranial', 'vertex', 'occiput', 'forehead', 'temple'],
    'hand' => ['hands', 'palm', 'palms', 'fingers'],
    'finger' => ['fingers', 'digit', 'digits'],
    'foot' => ['feet', 'sole', 'soles', 'toes'],
    'leg' => ['legs', 'lower limb', 'thigh', 'calf', 'shin'],
    'arm' => ['arms', 'upper limb', 'forearm', 'bicep'],
    'back' => ['spine', 'spinal', 'lumbar', 'dorsal', 'cervical', 'sacral'],
    'neck' => ['cervical', 'throat', 'nape'],
    'chest' => ['thorax', 'thoracic', 'pectoral', 'breast', 'sternum'],
    'heart' => ['cardiac', 'palpitation', 'pulse', 'cardiovascular'],
    'stomach' => ['gastric', 'epigastric', 'abdomen', 'belly', 'epigastrium'],
    'abdomen' => ['abdominal', 'stomach', 'belly', 'intestinal', 'umbilical'],
    'throat' => ['pharynx', 'larynx', 'gullet', 'fauces', 'tonsils'],
    'skin' => ['cutaneous', 'dermal', 'epidermis', 'eruption'],
    'kidney' => ['renal', 'nephritis', 'urinary'],
    'liver' => ['hepatic', 'biliary', 'hepatitis'],
    'lung' => ['pulmonary', 'respiratory', 'bronchial'],
    
    // JOINTS - Extended
    'knee' => ['knees', 'patella', 'patellar'],
    'ankle' => ['ankles', 'malleolus', 'tarsal'],
    'wrist' => ['wrists', 'carpal'],
    'elbow' => ['elbows', 'cubital'],
    'shoulder' => ['shoulders', 'deltoid', 'scapula'],
    'hip' => ['hips', 'coxal', 'iliac', 'acetabulum'],
    'joint' => ['joints', 'articular', 'articulation', 'arthritis'],
    'stiff' => ['stiffness', 'rigid', 'tight', 'cannot move'],
    'swollen' => ['swelling', 'edema', 'oedema', 'tumefaction', 'puffiness'],
    
    // SKIN CONDITIONS - Extended
    'itching' => ['itch', 'itchy', 'pruritus', 'scratching'],
    'eruption' => ['rash', 'eruptions', 'exanthema', 'skin eruption'],
    'eczema' => ['dermatitis', 'skin eruption', 'itching'],
    'urticaria' => ['hives', 'wheals', 'nettle rash'],
    'boil' => ['furuncle', 'abscess', 'carbuncle'],
    'dry' => ['dryness', 'parched', 'rough'],
    
    // NOSE/RESPIRATORY - Extended
    'sneezing' => ['sneeze', 'sneezes', 'sternutation', 'paroxysmal'],
    'sneeze' => ['sneezing', 'sneezes'],
    'coryza' => ['runny nose', 'nasal discharge', 'rhinitis', 'cold'],
    'stuffy' => ['stuffiness', 'obstruction', 'blocked', 'congestion'],
    'discharge' => ['secretion', 'flow', 'drainage', 'exudate'],
    
    // TIME MODALITIES - Extended
    'morning' => ['am', 'waking', 'rising', 'forenoon', 'on waking', 'sunrise'],
    'afternoon' => ['pm', 'post meridian', '4pm', '4 pm'],
    'evening' => ['pm', 'twilight', 'dusk', 'sunset'],
    'night' => ['midnight', 'nocturnal', 'during sleep', 'after midnight'],
    
    // MODALITIES - Extended
    'worse' => ['aggravation', 'agg', 'aggravated', 'exacerbated'],
    'better' => ['amelioration', 'amel', 'ameliorated', 'improved', 'relief'],
    'motion' => ['movement', 'moving', 'walking', 'exercise'],
    'rest' => ['resting', 'lying', 'stillness', 'repose'],
    'cold' => ['coldness', 'chill', 'chilly', 'frigid', 'freezing'],
    'heat' => ['hot', 'warmth', 'warm', 'temperature'],
    'pressure' => ['pressing', 'hard pressure', 'touch'],
    'touch' => ['touching', 'pressure', 'contact'],
    
    // Extended synonyms for more failed queries
    'manic' => ['mania', 'excitement', 'frenzy', 'madness', 'exaltation'],
    'grandiosity' => ['grandiose', 'megalomania', 'delusions of grandeur', 'haughty'],
    'cruelty' => ['cruel', 'malicious', 'inhumanity', 'brutality'],
    'remorse' => ['guilt', 'penitence', 'conscience', 'regret'],
    'sweats' => ['sweat', 'perspiration', 'sweating', 'diaphoresis'],
    'perspiration' => ['sweat', 'sweats', 'sweating', 'diaphoresis'],
    'loss' => ['lost', 'losing', 'absent', 'want of', 'diminished'],
    'smell' => ['olfaction', 'anosmia', 'odor', 'scent'],
    'clearing' => ['clear', 'hawking', 'scraping'],
    'hoarseness' => ['hoarse', 'voice lost', 'voice rough', 'husky'],
    'diarrhoea' => ['diarrhea', 'loose stool', 'watery stool', 'flux'],
    'diarrhea' => ['diarrhoea', 'loose stool', 'watery stool', 'flux'],
    'fruits' => ['fruit', 'acid fruits', 'citrus'],
    'fruit' => ['fruits', 'acid fruit', 'citrus'],
    'daybreak' => ['dawn', 'morning', 'sunrise', 'early morning'],
    'dawn' => ['daybreak', 'morning', 'sunrise', 'early morning'],
    'religious' => ['religion', 'prayer', 'god', 'piety'],
    'ecstasy' => ['exaltation', 'rapture', 'bliss', 'euphoria'],
    'exaltation' => ['ecstasy', 'rapture', 'elation', 'excitement'],
    'meningitis' => ['brain inflammation', 'cerebral', 'convulsions'],
    'opisthotonos' => ['arching', 'bent backward', 'tetanic'],
    'episodes' => ['episode', 'attack', 'fit', 'paroxysm'],
    'constantly' => ['constant', 'continual', 'continuous', 'incessant'],
    'painless' => ['without pain', 'pain free', 'absence of pain'],
    // FIX (Issue 2): removed 'good' => [increased, ravenous, excessive] — a bare 'good'
    // in clinical text almost never means ravenous appetite; the mapping only
    // introduced noise. Users who want that rubric can search 'appetite' directly.
    
    // Round 3 - More failed query synonyms
    'prolapse' => ['prolapsus', 'falling', 'descent', 'bearing down'],
    'uterus' => ['uterine', 'womb', 'metra'],
    'childbirth' => ['parturition', 'labor', 'delivery', 'confinement'],
    'fibroid' => ['fibroma', 'tumor', 'tumour', 'myoma'],
    'tumour' => ['tumor', 'growth', 'neoplasm', 'swelling'],
    'tumor' => ['tumour', 'growth', 'neoplasm', 'swelling'],
    'fissures' => ['fissure', 'crack', 'cracked', 'chapped'],
    'fissure' => ['fissures', 'crack', 'cracked', 'chapped'],
    'fingertips' => ['fingers', 'finger tips', 'tips of fingers'],
    'winter' => ['cold weather', 'cold season', 'cold agg'],
    'snoring' => ['snore', 'stertorous', 'noisy breathing'],
    'snore' => ['snoring', 'stertorous'],
    'sleepy' => ['drowsy', 'drowsiness', 'somnolence', 'sleepiness'],
    'meals' => ['eating', 'food', 'after eating', 'dinner'],
    'sitting' => ['seated', 'sit', 'while sitting'],
    'standing' => ['erect', 'stand', 'while standing'],
    'walking' => ['walk', 'motion', 'ambulation', 'gait'],
    'running' => ['run', 'exertion', 'rapid motion'],
    'lying' => ['recumbent', 'supine', 'horizontal', 'bed'],
    'bending' => ['bend', 'stooping', 'leaning'],
    'stooping' => ['stoop', 'bending', 'leaning forward'],
    
    // Round 4 - Remaining failed query synonyms
    'ingrowing' => ['ingrown', 'in-grown', 'inward growing'],
    'toenail' => ['toe nail', 'nail', 'nails'],
    'suppuration' => ['suppurating', 'pus', 'abscess', 'purulent'],
    'pus' => ['suppuration', 'purulent', 'discharge'],
    'formation' => ['forming', 'developing'],
    'anaemia' => ['anemia', 'bloodless', 'pale', 'pallor'],
    'anemia' => ['anaemia', 'bloodless', 'pale', 'pallor'],
    'pallor' => ['pale', 'paleness', 'white', 'blanched', 'anaemia'],
    'atherosclerosis' => ['arteriosclerosis', 'hardening', 'arteries'],
    'hardening' => ['hard', 'indurated', 'induration', 'sclerosis'],
    'arteries' => ['arterial', 'artery', 'blood vessels'],
    'thoughts' => ['thinking', 'mind active', 'mental activity'],
    'fall' => ['falling', 'cannot'],
    'asleep' => ['sleep', 'sleeping', 'drowsy'],
    'hawking' => ['hawked', 'clearing throat', 'scraping'],
    'scraping' => ['scraped', 'clearing', 'hawking'],
    
    // Round 5 - Sleep and remaining failures
    'jerking' => ['jerk', 'jerks', 'twitching', 'startling', 'starts'],
    'starts' => ['starting', 'startle', 'jerking', 'twitching'],
    'twitching' => ['twitch', 'twitches', 'jerking', 'convulsive'],
    'falling' => ['fall', 'going to sleep', 'on going to sleep'],
    'cradle' => ['scalp', 'head', 'infant'],
    'cap' => ['crust', 'scab', 'eruption'],
    'babies' => ['baby', 'infant', 'infants', 'child', 'children'],
    'infant' => ['infants', 'baby', 'babies', 'newborn', 'child'],
    'child' => ['children', 'infant', 'baby', 'babies'],

    // FIX (Issue 4): noun/adjective symmetry — users who type the noun form now
    // get the same expansion as users who type the adjective form.
    'restlessness' => ['restless', 'cannot rest', 'tossing', 'fidgety', 'agitation', 'cannot sit still'],
    'anxious' => ['anxiety', 'fear', 'worry', 'apprehension', 'restlessness', 'nervous', 'tension'],
    'angry' => ['anger', 'irritability', 'rage', 'wrath', 'fury', 'cross', 'peevish', 'violent', 'choleric'],
    'irritability' => ['irritable', 'angry', 'cross', 'peevish', 'touchy', 'snappish', 'fretful', 'impatient'],
    'fearful' => ['fear', 'anxiety', 'fright', 'dread', 'terror', 'apprehensive', 'afraid', 'phobia'],
    'sadness' => ['sad', 'grief', 'sorrow', 'melancholy', 'depression', 'dejection', 'gloomy'],
    'depression' => ['depressed', 'sad', 'melancholy', 'despondent', 'hopeless', 'despair'],
    'eating' => ['meals', 'food', 'after eating', 'dinner'],
    'food' => ['meals', 'eating', 'diet'],

    // FIX (Accuracy pass v2): missing reverse mappings for common lay terms that
    // must resolve to Kent rubric vocabulary. Each of these was observed as a
    // zero/weak result in the 100-query regression test.
    'deafness' => ['deaf', 'hard of hearing', 'hearing loss', 'hearing diminished', 'hearing impaired'],
    'hemorrhoids' => ['haemorrhoids', 'piles', 'anal varices'],
    'haemorrhoids' => ['hemorrhoids', 'piles', 'anal varices'],
    'rash' => ['eruption', 'eruptions', 'skin eruption', 'exanthema'],
    'hives' => ['urticaria', 'wheals', 'nettle rash', 'eruptions'],
    'insomnia' => ['sleeplessness', 'sleepless', 'cannot sleep', 'wakefulness'],
    'sleeplessness' => ['insomnia', 'sleepless', 'cannot sleep', 'wakefulness'],
    'shortness' => ['short', 'dyspnea', 'breathless', 'breathlessness', 'difficult breathing'],
    'breath' => ['breathing', 'respiration', 'dyspnea'],
    'heartburn' => ['pyrosis', 'acid eructation', 'burning in stomach'],
    'runny' => ['running', 'discharge', 'flowing'],
    'nasal' => ['nose', 'coryza'],
    'sore' => ['pain', 'painful', 'tender', 'inflamed'],
    'appetite' => ['hunger', 'desire for food', 'eating'],
    'craving' => ['cravings', 'desires', 'longing', 'wants'],
    'bloating' => ['distension', 'flatulence', 'swelling', 'fullness'],
    'abdominal' => ['abdomen', 'belly', 'stomach'],
    'urinary' => ['urine', 'bladder', 'micturition'],
    'retention' => ['retained', 'unable to pass', 'suppressed'],
    'fatigue' => ['weakness', 'debility', 'exhaustion', 'tired', 'lassitude'],
    'weakness' => ['weak', 'debility', 'exhaustion', 'prostration', 'fatigue', 'lassitude'],
    'ringing' => ['tinnitus', 'noises', 'buzzing', 'roaring', 'humming'],
    'wheezing' => ['wheeze', 'whistling', 'asthmatic breathing'],
];

// Stop words to filter out from search queries - common words that cause irrelevant matches
$stopWords = [
    // Common English stop words
    'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
    'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
    'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used',
    'and', 'but', 'or', 'nor', 'for', 'yet', 'so', 'both', 'either', 'neither',
    'not', 'only', 'own', 'same', 'than', 'too', 'very', 's', 't', 'just',
    'of', 'to', 'in', 'on', 'at', 'by', 'from', 'up', 'about', 'into', 'over',
    'after', 'before', 'during', 'without', 'under', 'around', 'among', 'between',
    // Common verbs that cause false matches
    'get', 'gets', 'got', 'getting', 'become', 'becomes', 'became', 'becoming',
    'feel', 'feels', 'felt', 'feeling', 'make', 'makes', 'made', 'making',
    'take', 'takes', 'took', 'taking', 'give', 'gives', 'gave', 'giving',
    'go', 'goes', 'went', 'going', 'come', 'comes', 'came', 'coming',
    'see', 'sees', 'saw', 'seeing', 'seem', 'seems', 'seemed', 'seeming',
    'look', 'looks', 'looked', 'looking', 'think', 'thinks', 'thought',
    'say', 'says', 'said', 'saying', 'tell', 'tells', 'told', 'telling',
    'know', 'knows', 'knew', 'knowing', 'want', 'wants', 'wanted', 'wanting',
    'use', 'uses', 'used', 'using', 'find', 'finds', 'found', 'finding',
    'try', 'tries', 'tried', 'trying', 'ask', 'asks', 'asked', 'asking',
    // Subject pronouns and related
    'patient', 'patients', 'person', 'people', 'he', 'she', 'it', 'they',
    'i', 'we', 'you', 'my', 'your', 'his', 'her', 'its', 'our', 'their',
    'me', 'him', 'them', 'us', 'this', 'that', 'these', 'those',
    'who', 'whom', 'which', 'what', 'whose', 'whoever', 'whatever', 'whichever',
    // Articles and determiners
    'some', 'any', 'no', 'every', 'each', 'all', 'many', 'much', 'more', 'most',
    'few', 'little', 'less', 'least', 'other', 'another', 'such',
    // Common adverbs
    'when', 'where', 'why', 'how', 'while', 'as', 'if', 'then', 'else',
    'always', 'never', 'sometimes', 'often', 'usually', 'still', 'already',
    'even', 'also', 'again', 'further', 'once', 'twice', 'here', 'there',
    'now', 'then', 'today', 'yesterday', 'tomorrow', 'ago', 'later', 'earlier',
    'sometimes', 'perhaps', 'maybe', 'probably', 'possibly', 'certainly',
    'really', 'actually', 'especially', 'particularly', 'mainly', 'mostly',
    // Conjunctions and connectors
    'because', 'since', 'although', 'though', 'unless', 'until', 'whereas',
    'however', 'therefore', 'thus', 'hence', 'otherwise', 'instead', 'besides',
    // Common phrases
    'like', 'likes', 'liked', 'liking',
];

// Function to expand search terms with synonyms.
// FIX (Issue 5): return BOTH the deduped full term list AND the subset of original
// user tokens, so the SQL can weight original hits higher than synonym hits.
// Backwards-compatible: if caller expects just a list, use array_unique of result.
function expandSearchTerms($query, $synonymMap, $stopWords = []) {
    $queryLower = mb_strtolower(trim($query));
    $words = preg_split('/[\s,\-]+/', $queryLower);
    $searchTerms = [];
    $originalTerms = [];

    foreach ($words as $word) {
        $word = trim($word);
        // Skip stop words and short words
        if (strlen($word) < 3 || in_array($word, $stopWords)) {
            continue;
        }

        $originalTerms[] = $word;
        $searchTerms[] = $word;
        if (isset($synonymMap[$word])) {
            $searchTerms = array_merge($searchTerms, $synonymMap[$word]);
        }
    }

    // Fallback: if nothing meaningful found, accept any >= 4-char non-stopword token
    if (empty($searchTerms)) {
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 4 && !in_array($word, $stopWords)) {
                $searchTerms[] = $word;
                $originalTerms[] = $word;
            }
        }
    }

    $uniqueTerms = array_values(array_unique($searchTerms));
    // Return a list for BC, but also expose 'original' via a property-style wrapper.
    // Simpler: return an array with two keys. Callers who treat it as a plain list
    // iterate values; new callers can read $result['terms'] and $result['originals'].
    return [
        'terms'     => $uniqueTerms,
        'originals' => array_values(array_unique($originalTerms)),
    ];
}

// Search when there's a search query OR a category selected
if (!empty($searchQuery) || !empty($category)) {
    $params = [];
    
    if (!empty($searchQuery)) {
        // Get original words from query for relevance scoring (filter stop words)
        $queryLower = mb_strtolower(trim($searchQuery));
        $allWords = array_filter(preg_split('/[\s,\-]+/', $queryLower), function($w) {
            return strlen(trim($w)) >= 2;
        });
        // Filter out stop words from relevance scoring
        $originalWords = array_filter($allWords, function($w) use ($stopWords) {
            return !in_array(trim($w), $stopWords);
        });
        $originalWords = array_values($originalWords);

        // Expand search with synonyms for better matching (filter stop words)
        // FIX (Issue 5): expandSearchTerms now returns ['terms' => [...], 'originals' => [...]]
        $expansion     = expandSearchTerms($searchQuery, $synonymMap, $stopWords);
        $searchTerms   = $expansion['terms'];
        $originalTerms = $expansion['originals'];

        // FIX (Issue 6): Detect stop-word-only queries and signal the UI explicitly
        // instead of silently returning zero rubrics.
        $stopWordsOnlyQuery = (empty($searchTerms) && !empty(trim($searchQuery)));

        // If no meaningful search terms found, show empty results with message
        if (empty($searchTerms)) {
            $rubrics = [];
            $totalRubrics = 0;
            $totalRubricPages = 0;
        } else {
            // Build conditions for all search terms (including synonyms)
            $conditions = [];
            foreach ($searchTerms as $term) {
                $conditions[] = "(LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?)";
                $params[] = '%' . $term . '%';
                $params[] = '%' . $term . '%';
            }

            // FIX (Issue 7): Weighted relevance. Each original-query term is worth 2
            // when matched; each synonym-only term is worth 1. Rubrics containing the
            // user's actual word outrank rubrics that only share a distant synonym.
            $originalSet = array_flip($originalTerms);
            $relevanceParts = [];

            foreach ($searchTerms as $term) {
                if (strlen($term) < 3) continue;
                $weight = isset($originalSet[$term]) ? 2 : 1;
                $relevanceParts[] = "CASE WHEN LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ? THEN $weight ELSE 0 END";
                $params[] = '%' . $term . '%';
                $params[] = '%' . $term . '%';
            }

            if (empty($relevanceParts)) {
                $relevanceCase = "0 as relevance_score";
            } else {
                $relevanceCase = "(" . implode(" + ", $relevanceParts) . ") as relevance_score";
            }

            $sqlBase = "FROM repertory r LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id WHERE (" . implode(' OR ', $conditions) . ")";

            if (!empty($category)) {
                $sqlBase .= " AND LOWER(r.category) = ?";
                $params[] = strtolower($category);
            }

            // Store relevance params for the SELECT query (matches searchTerms used in CASE)
            $relevanceParams = [];
            foreach ($searchTerms as $term) {
                if (strlen($term) < 3) continue; // Skip short terms as in CASE building
                $relevanceParams[] = '%' . $term . '%';
                $relevanceParams[] = '%' . $term . '%';
            }

            // Get total count (uses original params without relevance params)
            $countParams = [];
            foreach ($searchTerms as $term) {
                $countParams[] = '%' . $term . '%';
                $countParams[] = '%' . $term . '%';
            }
            if (!empty($category)) {
                $countParams[] = strtolower($category);
            }
    
            $countSql = "SELECT COUNT(DISTINCT r.id) as total " . $sqlBase;
            $countResult = DB::queryOne($countSql, $countParams);
            $totalRubrics = $countResult['total'] ?? 0;
            $totalRubricPages = ceil($totalRubrics / $rubricsPerPage);
    
            // Build full params for SELECT with relevance (relevance params come first, then WHERE params)
            $selectParams = array_merge($relevanceParams, $countParams);
    
            // Get paginated rubrics - ORDER BY relevance_score DESC first, then category/rubric
            // Security: Cast pagination to integers to prevent SQL injection
            $safeRubricOffset = (int)$rubricOffset;
            $safeRubricsPerPage = (int)$rubricsPerPage;
            $sql = "SELECT r.*, COUNT(rr.remedy_id) as remedy_count, $relevanceCase " . $sqlBase . " GROUP BY r.id ORDER BY relevance_score DESC, r.category, r.rubric COLLATE utf8mb4_unicode_ci LIMIT $safeRubricOffset, $safeRubricsPerPage";
            $rubrics = DB::query($sql, $selectParams);
        }
    } else {
        // Browse by category only (no search query)
        $sqlBase = "FROM repertory r LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id WHERE LOWER(r.category) = ?";
        $params = [strtolower($category)];
        $relevanceCase = "0 as relevance_score";
        $relevanceParams = [];
        $countParams = [strtolower($category)];
    
        $countSql = "SELECT COUNT(DISTINCT r.id) as total " . $sqlBase;
        $countResult = DB::queryOne($countSql, $countParams);
        $totalRubrics = $countResult['total'] ?? 0;
        $totalRubricPages = ceil($totalRubrics / $rubricsPerPage);
    
        // Build full params for SELECT with relevance (relevance params come first, then WHERE params)
        $selectParams = array_merge($relevanceParams, $countParams);
    
        // Get paginated rubrics - ORDER BY relevance_score DESC first, then category/rubric
        // Security: Cast pagination to integers to prevent SQL injection
        $safeRubricOffset = (int)$rubricOffset;
        $safeRubricsPerPage = (int)$rubricsPerPage;
        $sql = "SELECT r.*, COUNT(rr.remedy_id) as remedy_count, $relevanceCase " . $sqlBase . " GROUP BY r.id ORDER BY relevance_score DESC, r.category, r.rubric COLLATE utf8mb4_unicode_ci LIMIT $safeRubricOffset, $safeRubricsPerPage";
        $rubrics = DB::query($sql, $selectParams);
    }
}

// Repertorization - find remedies for selected rubrics
$repertorization = [];
if (!empty($selectedRubrics)) {
    // Get all remedies for selected rubrics with their grades
    $placeholders = implode(',', array_fill(0, count($selectedRubrics), '?'));
    
    $sql = "SELECT rem.id, rem.remedy_name as name, rem.common_name,
                   GROUP_CONCAT(CONCAT(rr.repertory_id, ':', rr.grade) SEPARATOR ',') as rubric_grades
            FROM remedies rem
            INNER JOIN repertory_remedies rr ON rem.id = rr.remedy_id
            WHERE rr.repertory_id IN ($placeholders)
            GROUP BY rem.id
            ORDER BY COUNT(DISTINCT rr.repertory_id) DESC, rem.remedy_name";
    
    $remedies = DB::query($sql, $selectedRubrics);
    
    // Calculate totality for each remedy
    foreach ($remedies as $remedy) {
        $grades = [];
        $rubricGrades = explode(',', $remedy['rubric_grades']);
        
        foreach ($rubricGrades as $rg) {
            list($rubricId, $grade) = explode(':', $rg);
            // FIX (Issue 8): Kent grades are 1–4 by convention. Clamp to that range
            // so bad DB data cannot silently inflate a remedy's totality score.
            $grades[(int)$rubricId] = max(1, min(4, (int)$grade));
        }
        
        $totalScore = 0;
        $rubricCount = 0;
        $gradeBreakdown = [];
        
        foreach ($selectedRubrics as $rubricId) {
            if (isset($grades[$rubricId])) {
                $grade = $grades[$rubricId];
                $totalScore += $grade;
                $rubricCount++;
                $gradeBreakdown[] = $grade;
            }
        }
        
        $repertorization[] = [
            'id' => $remedy['id'],
            'name' => $remedy['name'],
            'common_name' => $remedy['common_name'],
            'total_score' => $totalScore,
            'rubric_count' => $rubricCount,
            'grade_breakdown' => $gradeBreakdown,
            'coverage' => round(($rubricCount / count($selectedRubrics)) * 100, 1)
        ];
    }
    
    // Sort by total score descending
    usort($repertorization, function($a, $b) {
        if ($a['total_score'] == $b['total_score']) {
            return $b['rubric_count'] - $a['rubric_count'];
        }
        return $b['total_score'] - $a['total_score'];
    });
}

// Get selected rubric details
$selectedRubricDetails = [];
if (!empty($selectedRubrics)) {
    $placeholders = implode(',', array_fill(0, count($selectedRubrics), '?'));
    $selectedRubricDetails = DB::query(
        "SELECT * FROM repertory WHERE id IN ($placeholders)",
        $selectedRubrics
    );
}

$pageTitle = 'Repertory Search';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .repertory-container { position: relative; }
    .repertory-container::before {
        content: '';
        position: fixed;
        top: 60px;
        left: 220px;
        right: 0;
        bottom: 0;
        background: url('<?php echo APP_URL; ?>/assets/image/xrunbg.png') center center no-repeat;
        background-size: 45%;
        opacity: 0.08;
        pointer-events: none;
        z-index: 0;
    }
    @media (max-width: 992px) {
        .repertory-container::before { left: 0; top: 60px; }
    }

    /* Kent verified badge (added 2026-04-27) */
    .verified-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        margin-left: 6px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.4;
        color: #0b5132;
        background: linear-gradient(135deg, #d1fadf 0%, #a7f3d0 100%);
        border: 1px solid #16a34a;
        border-radius: 999px;
        white-space: nowrap;
        vertical-align: middle;
    }
    .verified-badge i { font-size: 10px; color: #16a34a; }
    .verified-badge.compact { padding: 1px 6px; font-size: 10px; }
    .rubric-chip .verified-badge { margin-left: 4px; }

    /* Source badge (Kent / Boericke / etc.) */
    .source-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        margin-left: 6px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.4;
        color: #3730a3;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 999px;
        white-space: nowrap;
        vertical-align: middle;
    }
    .source-badge i { font-size: 10px; }
    .source-badge.compact { padding: 1px 6px; font-size: 10px; }
    .rubric-chip .source-badge { margin-left: 4px; }
</style>

<div class="repertory-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-book-medical"></i> Repertory Search</h1>
            <p class="text-muted">Search symptoms and find relevant remedies</p>
        </div>
        <div class="page-actions">
            <span class="stats-badge"><i class="fas fa-list"></i> <?php echo number_format($totalRubricsInDB); ?> Rubrics</span>
            <span class="stats-badge"><i class="fas fa-link"></i> <?php echo number_format($totalMappings); ?> Mappings</span>
        </div>
    </div>
    
    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list-alt"></i></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($totalRubricsInDB); ?></span>
                <span class="stat-label">Total Rubrics</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-capsules"></i></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo number_format($totalMappings); ?></span>
                <span class="stat-label">Remedy Mappings</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-folder"></i></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo count($categoryCounts); ?></span>
                <span class="stat-label">Categories</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <span class="stat-number"><?php echo count($selectedRubrics); ?></span>
                <span class="stat-label">Selected Rubrics</span>
            </div>
        </div>
    </div>
    
    <!-- Search Form -->
    <div class="dashboard-card">
        <div class="card-body">
            <form method="GET" action="" class="repertory-search-form" id="searchForm">
                <div class="search-row">
                    <div class="search-field flex-2" style="position: relative;">
                        <input 
                            type="text" 
                            name="q" 
                            id="symptomInput"
                            class="form-control" 
                            placeholder="Describe symptom naturally (e.g., 'patient gets angry when contradicted')"
                            value="<?php echo htmlspecialchars($searchQuery); ?>"
                            autocomplete="off"
                            autofocus
                        >
                        <div id="smartSuggestions" class="smart-suggestions-dropdown" style="display:none;"></div>
                    </div>
                    
                    <div class="search-field">
                        <select name="category" id="categorySelect" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $key => $label): 
                                $count = $categoryCounts[$key] ?? 0;
                                if ($count > 0):
                            ?>
                                <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?> (<?php echo $count; ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <button type="button" class="btn btn-secondary" id="smartSearchBtn" title="AI-powered smart search for natural language symptoms">
                        <i class="fas fa-magic"></i> Smart Search
                    </button>
                    
                    <?php if ($searchQuery || $category): ?>
                    <a href="<?php echo APP_URL; ?>/repertory/search.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Smart Search Help Text -->
                <div class="smart-search-help" style="margin-top: 10px; font-size: 0.85rem; color: #666;">
                    <i class="fas fa-lightbulb" style="color: #f0ad4e;"></i> 
                    <strong>Tip:</strong> Use natural language like "patient gets angry when contradicted" or "fear of being alone at night". 
                    Click <strong>Smart Search</strong> to find matching rubrics automatically.
                </div>
                
                <!-- Hidden inputs for selected rubrics -->
                <?php foreach ($selectedRubrics as $rubricId): ?>
                    <input type="hidden" name="rubrics[]" value="<?php echo $rubricId; ?>">
                <?php endforeach; ?>
            </form>
        </div>
    </div>
    
    <!-- Smart Search Results -->
    <div id="smartSearchResults" class="dashboard-card" style="display: none;">
        <div class="card-header">
            <h3><i class="fas fa-magic"></i> Smart Search Results</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('smartSearchResults').style.display='none'">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        <div class="card-body" id="smartSearchResultsBody">
            <!-- Results will be loaded here -->
        </div>
    </div>
    
    <?php if (!empty($selectedRubrics)): ?>
    <!-- Selected Rubrics -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-check-circle"></i> Selected Rubrics (<?php echo count($selectedRubrics); ?>)</h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="clearAllRubrics()">
                <i class="fas fa-times"></i> Clear All
            </button>
        </div>
        <div class="card-body">
            <div class="selected-rubrics">
                <?php foreach ($selectedRubricDetails as $rubric): ?>
                <div class="rubric-chip">
                    <span class="rubric-category"><?php echo ucfirst($rubric['category']); ?>:</span>
                    <span class="rubric-text"><?php echo htmlspecialchars($rubric['rubric']); ?></span>
                    <?php if (isKentMindRubrics1To10VerifiedSource($rubric)): ?>
                    <span class="verified-badge compact" title="Verified from <?php echo htmlspecialchars($rubric['verified_source'] ?? 'Kent'); ?><?php echo !empty($rubric['verified_page']) ? ' (p.' . (int)$rubric['verified_page'] . ')' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Verified
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($rubric['repertory_source'])): ?>
                    <span class="source-badge compact" title="Repertory source: <?php echo htmlspecialchars($rubric['repertory_source']); ?>">
                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($rubric['repertory_source']); ?>
                    </span>
                    <?php endif; ?>
                    <button type="button" class="remove-rubric" onclick="removeRubric(<?php echo $rubric['id']; ?>)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Repertorization Results -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Repertorization Results</h3>
            <div class="header-actions">
                <button type="button" class="btn btn-sm btn-success" onclick="exportResults()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="aiRepertoryBtn">
                    <i class="fas fa-robot"></i> AI Analysis
                </button>
            </div>
        </div>
            <div id="ai-repertory-section" style="display:none; margin-top:30px;"></div>
        <div class="card-body">
            <?php if (empty($repertorization)): ?>
                <div class="empty-state">
                    <i class="fas fa-flask"></i>
                    <p>No remedies found for selected rubrics</p>
                </div>
            <?php else: ?>
                <div class="repertorization-table-wrapper">
                    <table class="repertorization-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Remedy</th>
                                <th>Total Score</th>
                                <th>Coverage</th>
                                <th>Grade Breakdown</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repertorization as $index => $result): ?>
                            <tr>
                                <td>
                                    <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($result['name']); ?></strong>
                                    <?php if (!empty($result['common_name'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($result['common_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="score-badge score-<?php echo $result['total_score'] >= 6 ? 'high' : ($result['total_score'] >= 3 ? 'medium' : 'low'); ?>">
                                        <?php echo $result['total_score']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $result['coverage']; ?>%"></div>
                                    </div>
                                    <small><?php echo $result['coverage']; ?>% (<?php echo $result['rubric_count']; ?>/<?php echo count($selectedRubrics); ?>)</small>
                                </td>
                                <td>
                                    <div class="grade-breakdown">
                                        <?php foreach ($result['grade_breakdown'] as $grade): ?>
                                            <span class="grade-dot grade-<?php echo $grade; ?>">
                                                <?php echo $grade; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?php echo APP_URL; ?>/materia-medica/view.php?id=<?php echo $result['id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="View Materia Medica"
                                           target="_blank">
                                            <i class="fas fa-book"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Search Results -->
    <?php if (!empty($searchQuery) || !empty($category)): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> <?php echo !empty($searchQuery) ? 'Search Results' : 'Browse: ' . ucfirst($category); ?> 
                <span class="results-count"><?php echo $totalRubrics; ?> rubric<?php echo $totalRubrics != 1 ? 's' : ''; ?> found</span>
            </h3>
            <?php if ($totalRubricPages > 1): ?>
            <div class="pagination-info">Page <?php echo $rubricPage; ?> of <?php echo $totalRubricPages; ?></div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($rubrics)): ?>
                <?php if (!empty($stopWordsOnlyQuery)): ?>
                    <!-- FIX (Issue 6): explicit notice for stop-word-only queries -->
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle" style="color:#f0ad4e;"></i>
                        <h3>Query too generic</h3>
                        <p>Every word in your query was filtered as a stop-word (e.g. <i>the, is, patient, feeling, today</i>).</p>
                        <p>Please include at least one symptom word &mdash; for example <strong>fear</strong>, <strong>headache</strong>, <strong>burning</strong>, or <strong>restless</strong>.</p>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No rubrics found</h3>
                        <p>Try different keywords or select a specific category</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="rubrics-list">
                    <?php 
                    $currentCategory = '';
                    foreach ($rubrics as $rubric): 
                        if ($currentCategory !== $rubric['category']):
                            if ($currentCategory !== '') echo '</div>';
                            $currentCategory = $rubric['category'];
                    ?>
                        <div class="category-section">
                            <h4 class="category-title">
                                <i class="fas fa-folder"></i> <?php echo ucfirst($rubric['category']); ?>
                            </h4>
                    <?php endif; ?>
                    
                    <div class="rubric-item <?php echo in_array($rubric['id'], $selectedRubrics) ? 'selected' : ''; ?>" 
                         id="rubric-<?php echo $rubric['id']; ?>">
                        <div class="rubric-content">
                            <div class="rubric-info">
                                <span class="rubric-text"><?php echo htmlspecialchars($rubric['rubric']); ?></span>
                                <?php if (isKentMindRubrics1To10VerifiedSource($rubric)): ?>
                                <span class="verified-badge" title="Verified from <?php echo htmlspecialchars($rubric['verified_source'] ?? 'Kent'); ?><?php echo !empty($rubric['verified_page']) ? ' (p.' . (int)$rubric['verified_page'] . ')' : ''; ?>">
                                    <i class="fas fa-check-circle"></i> Verified
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($rubric['sub_category'])): ?>
                                <span class="rubric-sub"> - <?php echo htmlspecialchars($rubric['sub_category']); ?></span>
                                <?php endif; ?>
                                <span class="remedy-count">
                                    <i class="fas fa-capsules"></i> <?php echo $rubric['remedy_count']; ?> remedies
                                </span>
                                <?php if (!empty($rubric['repertory_source'])): ?>
                                <span class="rubric-source">
                                    <i class="fas fa-book"></i> <?php echo htmlspecialchars($rubric['repertory_source']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="rubric-actions">
                                <button type="button" class="btn btn-sm btn-info" onclick="showRubricRemedies(<?php echo $rubric['id']; ?>, '<?php echo addslashes($rubric['rubric']); ?>')" title="View Remedies">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button 
                                    type="button" 
                                    class="btn btn-sm <?php echo in_array($rubric['id'], $selectedRubrics) ? 'btn-danger' : 'btn-success'; ?>" 
                                    onclick="toggleRubric(<?php echo $rubric['id']; ?>)"
                                    id="btn-<?php echo $rubric['id']; ?>">
                                    <?php if (in_array($rubric['id'], $selectedRubrics)): ?>
                                        <i class="fas fa-minus"></i> Remove
                                    <?php else: ?>
                                        <i class="fas fa-plus"></i> Add
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php endforeach; ?>
                    <?php if ($currentCategory !== '') echo '</div>'; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalRubricPages > 1): ?>
                <div class="pagination-wrapper">
                    <nav class="pagination">
                        <?php if ($rubricPage > 1): ?>
                            <a href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($category); ?>&rubric_page=1<?php foreach($selectedRubrics as $r) echo '&rubrics[]='.$r; ?>" class="page-link">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($category); ?>&rubric_page=<?php echo $rubricPage-1; ?><?php foreach($selectedRubrics as $r) echo '&rubrics[]='.$r; ?>" class="page-link">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $rubricPage - 2);
                        $endPage = min($totalRubricPages, $rubricPage + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($category); ?>&rubric_page=<?php echo $i; ?><?php foreach($selectedRubrics as $r) echo '&rubrics[]='.$r; ?>" 
                               class="page-link <?php echo $i == $rubricPage ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($rubricPage < $totalRubricPages): ?>
                            <a href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($category); ?>&rubric_page=<?php echo $rubricPage+1; ?><?php foreach($selectedRubrics as $r) echo '&rubrics[]='.$r; ?>" class="page-link">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($category); ?>&rubric_page=<?php echo $totalRubricPages; ?><?php foreach($selectedRubrics as $r) echo '&rubrics[]='.$r; ?>" class="page-link">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (empty($selectedRubrics)): ?>
    <!-- Welcome State -->
    <div class="dashboard-card">
        <div class="card-body">
            <div class="welcome-state">
                <i class="fas fa-book-medical"></i>
                <h3>Welcome to Repertory Search</h3>
                <p>Search for symptoms to find relevant remedies</p>
                <ul class="search-tips">
                    <li><i class="fas fa-lightbulb"></i> Enter symptom keywords (e.g., "headache", "anxiety", "fever")</li>
                    <li><i class="fas fa-filter"></i> Filter by category for focused results</li>
                    <li><i class="fas fa-plus-circle"></i> Select multiple rubrics for repertorization</li>
                    <li><i class="fas fa-chart-bar"></i> View remedy totality and coverage</li>
                </ul>
                
                <div class="quick-searches">
                    <h4>Quick Searches:</h4>
                    <div class="quick-search-buttons">
                        <a href="?q=anxiety&category=mind" class="btn btn-outline btn-sm">Anxiety</a>
                        <a href="?q=headache&category=head" class="btn btn-outline btn-sm">Headache</a>
                        <a href="?q=fever&category=fever" class="btn btn-outline btn-sm">Fever</a>
                        <a href="?q=cough&category=respiratory" class="btn btn-outline btn-sm">Cough</a>
                        <a href="?q=nausea&category=stomach" class="btn btn-outline btn-sm">Nausea</a>
                        <a href="?q=insomnia&category=sleep" class="btn btn-outline btn-sm">Insomnia</a>
                        <a href="?q=pain&category=general" class="btn btn-outline btn-sm">Pain</a>
                        <a href="?q=burning" class="btn btn-outline btn-sm">Burning</a>
                    </div>
                </div>
                
                <!-- Browse by Category -->
                <div class="category-browser">
                    <h4><i class="fas fa-folder-open"></i> Browse by Category:</h4>
                    <div class="category-grid">
                        <?php foreach ($categoryCounts as $cat => $count): 
                            if ($count > 0):
                                $icon = match($cat) {
                                    'mind' => 'fa-brain',
                                    'head' => 'fa-head-side',
                                    'eye' => 'fa-eye',
                                    'ear' => 'fa-ear-listen',
                                    'nose' => 'fa-nose',
                                    'face' => 'fa-face-smile',
                                    'mouth' => 'fa-mouth',
                                    'throat' => 'fa-throat',
                                    'stomach' => 'fa-stomach',
                                    'abdomen' => 'fa-toilet',
                                    'rectum' => 'fa-toilet',
                                    'urinary' => 'fa-droplet',
                                    'male' => 'fa-mars',
                                    'female' => 'fa-venus',
                                    'respiratory' => 'fa-lungs',
                                    'heart' => 'fa-heart-pulse',
                                    'back' => 'fa-person',
                                    'extremities' => 'fa-hand',
                                    'skin' => 'fa-hand-dots',
                                    'sleep' => 'fa-bed',
                                    'fever' => 'fa-temperature-high',
                                    'general', 'generalities' => 'fa-user',
                                    default => 'fa-folder'
                                };
                        ?>
                        <a href="?category=<?php echo urlencode($cat); ?>&q=" class="category-card" onclick="this.href='?category=<?php echo urlencode($cat); ?>&q='; document.querySelector('input[name=q]').value=''; return true;">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <span class="cat-name"><?php echo ucfirst($cat); ?></span>
                            <span class="cat-count"><?php echo $count; ?> rubrics</span>
                        </a>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.repertory-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Dashboard */
.stats-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.85rem;
    opacity: 0.9;
}

.stats-badge {
    background: var(--primary-color);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    margin-left: 5px;
}

.results-count {
    font-size: 0.85rem;
    color: var(--gray-500);
    font-weight: normal;
    margin-left: 10px;
}

.pagination-info {
    font-size: 0.9rem;
    color: var(--gray-600);
}

.repertory-search-form {
    width: 100%;
}

.search-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-field {
    flex: 1;
    min-width: 200px;
}

.search-field.flex-2 {
    flex: 2;
}

.selected-rubrics {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.rubric-chip {
    background: var(--primary-color);
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.rubric-category {
    font-weight: 600;
}

.remove-rubric {
    background: rgba(255,255,255,0.3);
    border: none;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    font-size: 0.75rem;
}

.remove-rubric:hover {
    background: rgba(255,255,255,0.5);
}

.repertorization-table-wrapper {
    overflow-x: auto;
}

.repertorization-table {
    width: 100%;
    border-collapse: collapse;
}

.repertorization-table th,
.repertorization-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.repertorization-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
}

.repertorization-table tr:hover {
    background: var(--gray-50);
}

.rank-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.rank-1 {
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    color: white;
}

.rank-2 {
    background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%);
    color: white;
}

.rank-3 {
    background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
    color: white;
}

.score-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1.1rem;
}

.score-high {
    background: #e8f5e9;
    color: #2e7d32;
}

.score-medium {
    background: #fff3e0;
    color: #f57c00;
}

.score-low {
    background: #fce4ec;
    color: #c2185b;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #22c55e);
    border-radius: 4px;
    transition: width 0.3s ease;
    min-width: 2px;
}

.quick-link-btn i {
    font-size: 20px;
}

.grade-breakdown {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.grade-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
}

.grade-1 {
    background: #ffebee;
    color: #c62828;
}

.grade-2 {
    background: #fff3e0;
    color: #ef6c00;
}

.grade-3 {
    background: #e8f5e9;
    color: #2e7d32;
}

.rubrics-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.category-section {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 15px;
}

.category-title {
    color: var(--primary-color);
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
}

.rubric-item {
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.rubric-item:hover {
    border-color: var(--primary-color);
    box-shadow: 0 2px 8px rgba(138, 43, 226, 0.1);
}

.rubric-item.selected {
    border-color: var(--primary-color);
    background: #f3e5f5;
}

.rubric-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.rubric-info {
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.rubric-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.rubric-text {
    font-weight: 500;
    color: var(--gray-800);
}

.rubric-sub {
    color: var(--gray-600);
    font-size: 0.9rem;
}

.remedy-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    background: var(--gray-100);
    border-radius: 12px;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.rubric-source {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    background: #e3f2fd;
    border-radius: 12px;
    font-size: 0.8rem;
    color: #1565c0;
}

.welcome-state {
    text-align: center;
    padding: 40px 20px;
}

.welcome-state i {
    font-size: 64px;
    color: var(--primary-color);
    opacity: 0.5;
    margin-bottom: 20px;
}

.welcome-state h3 {
    color: var(--gray-800);
    margin: 0 0 10px 0;
}

.search-tips {
    list-style: none;
    padding: 0;
    margin: 30px 0;
    display: inline-block;
    text-align: left;
}

.search-tips li {
    padding: 10px 0;
    color: var(--gray-700);
}

.search-tips i {
    color: var(--primary-color);
    margin-right: 10px;
    font-size: 1rem;
}

.quick-searches {
    margin-top: 30px;
}

.quick-searches h4 {
    margin-bottom: 15px;
    color: var(--gray-700);
}

.quick-search-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Category Browser */
.category-browser {
    margin-top: 40px;
    text-align: left;
}

.category-browser h4 {
    color: var(--gray-700);
    margin-bottom: 20px;
    text-align: center;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.category-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    padding: 20px 15px;
    text-align: center;
    text-decoration: none;
    color: var(--gray-700);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.category-card:hover {
    border-color: var(--primary-color);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.category-card i {
    font-size: 1.8rem;
    opacity: 0.8;
}

.category-card:hover i {
    opacity: 1;
}

.category-card .cat-name {
    font-weight: 600;
    font-size: 0.95rem;
}

.category-card .cat-count {
    font-size: 0.75rem;
    opacity: 0.7;
}

/* Pagination */
.pagination-wrapper {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}

.pagination {
    display: flex;
    gap: 5px;
    align-items: center;
}

.page-link {
    padding: 8px 14px;
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.page-link:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.page-link.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Rubric Remedies Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    max-height: 80vh;
    overflow: hidden;
    transform: translateY(-20px);
    transition: transform 0.3s;
}

.modal-overlay.active .modal-content {
    transform: translateY(0);
}

.modal-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
}

.modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.remedy-list-modal {
    display: grid;
    gap: 10px;
}

.remedy-item-modal {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: var(--gray-50);
    border-radius: 8px;
    border-left: 4px solid var(--primary-color);
}

.remedy-item-modal .grade-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.85rem;
}

.grade-badge.grade-1 { background: #ffebee; color: #c62828; }
.grade-badge.grade-2 { background: #fff3e0; color: #ef6c00; }
.grade-badge.grade-3 { background: #e8f5e9; color: #2e7d32; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}

.empty-state i {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .search-row {
        flex-direction: column;
    }
    
    .rubric-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .repertorization-table {
        font-size: 0.85rem;
    }
    
    .repertorization-table th,
    .repertorization-table td {
        padding: 8px 5px;
    }
}

/* Smart Search Styles */
.smart-suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: 5px;
}

.smart-suggestion-item {
    padding: 12px 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.2s;
}

.smart-suggestion-item:hover {
    background: var(--primary-50);
}

.smart-suggestion-item:last-child {
    border-bottom: none;
}

.smart-suggestion-rubric {
    font-weight: 600;
    color: var(--gray-800);
}

.smart-suggestion-complete {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-top: 3px;
}

.smart-suggestion-meta {
    display: flex;
    gap: 10px;
    margin-top: 5px;
    font-size: 0.8rem;
}

.smart-suggestion-category {
    background: var(--primary-100);
    color: var(--primary-700);
    padding: 2px 8px;
    border-radius: 10px;
}

.smart-suggestion-source {
    padding: 2px 8px;
    border-radius: 10px;
}

.smart-suggestion-source.database {
    background: #e8f5e9;
    color: #2e7d32;
}

.smart-suggestion-source.ai {
    background: #e3f2fd;
    color: #1565c0;
}

.smart-search-loading {
    padding: 20px;
    text-align: center;
    color: var(--gray-500);
}

.smart-search-loading i {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--primary-500);
}

#smartSearchResults .rubric-result-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

#smartSearchResults .rubric-result-card:hover {
    border-color: var(--primary-400);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

#smartSearchResults .rubric-info {
    flex: 1;
}

#smartSearchResults .rubric-name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 1rem;
}

#smartSearchResults .rubric-complete {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-top: 3px;
}

#smartSearchResults .rubric-badges {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

#smartSearchResults .badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

#smartSearchResults .badge-category {
    background: var(--primary-100);
    color: var(--primary-700);
}

#smartSearchResults .badge-remedies {
    background: #fff3e0;
    color: #f57c00;
}

#smartSearchResults .badge-ai {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

#smartSearchResults .add-rubric-btn {
    padding: 8px 16px;
    background: var(--primary-500);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}

#smartSearchResults .add-rubric-btn:hover {
    background: var(--primary-600);
    transform: translateY(-1px);
}

.smart-search-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.smart-search-info i {
    font-size: 1.5rem;
    color: var(--primary-500);
}

.smart-search-info-text {
    flex: 1;
}

.smart-search-info-text strong {
    display: block;
    color: var(--gray-800);
    margin-bottom: 3px;
}

.smart-search-info-text span {
    font-size: 0.85rem;
    color: var(--gray-600);
}

.btn-secondary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%);
    color: white;
}
</style>

<script>
function toggleRubric(rubricId) {
    const form = document.getElementById('searchForm');
    const rubricInputs = form.querySelectorAll('input[name="rubrics[]"]');
    
    // Check if already selected
    let isSelected = false;
    rubricInputs.forEach(input => {
        if (parseInt(input.value) === rubricId) {
            isSelected = true;
            input.remove();
        }
    });
    
    // If not selected, add it
    if (!isSelected) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'rubrics[]';
        input.value = rubricId;
        form.appendChild(input);
    }
    
    // Submit form
    form.submit();
}

function removeRubric(rubricId) {
    toggleRubric(rubricId);
}

function clearAllRubrics() {
    const form = document.getElementById('searchForm');
    const rubricInputs = form.querySelectorAll('input[name="rubrics[]"]');
    rubricInputs.forEach(input => input.remove());
    form.submit();
}

function exportResults() {
    // Create CSV content
    let csv = 'Rank,Remedy,Common Name,Total Score,Coverage,Rubric Count\n';
    
    const table = document.querySelector('.repertorization-table tbody');
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        const rank = index + 1;
        const remedy = cells[1].querySelector('strong')?.textContent || '';
        const commonName = cells[1].querySelector('small')?.textContent || '';
        const score = cells[2].textContent.trim();
        const coverage = cells[3].querySelector('small')?.textContent || '';
        
        csv += `${rank},"${remedy}","${commonName}",${score},"${coverage}"\n`;
    });
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'repertorization_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Modal for viewing rubric remedies
function showRubricRemedies(rubricId, rubricName) {
    // Create modal if not exists
    let modal = document.getElementById('rubricModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'rubricModal';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Remedies</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <div style="text-align:center;padding:40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#764ba2;"></i>
                        <p>Loading remedies...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }
    
    document.getElementById('modalTitle').textContent = rubricName;
    document.getElementById('modalBody').innerHTML = `
        <div style="text-align:center;padding:40px;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#764ba2;"></i>
            <p>Loading remedies...</p>
        </div>
    `;
    modal.classList.add('active');
    
    // Fetch remedies for this rubric
    fetch('<?php echo APP_URL; ?>/api/get_rubric_remedies.php?rubric_id=' + rubricId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.remedies) {
                let html = '<div class="remedy-list-modal">';
                if (data.remedies.length === 0) {
                    html += '<p style="text-align:center;color:#666;">No remedies mapped to this rubric yet.</p>';
                } else {
                    data.remedies.forEach(remedy => {
                        html += `
                            <div class="remedy-item-modal">
                                <div>
                                    <strong>${remedy.remedy_name}</strong>
                                    ${remedy.common_name ? '<br><small style="color:#666;">' + remedy.common_name + '</small>' : ''}
                                </div>
                                <span class="grade-badge grade-${remedy.grade}">Grade ${remedy.grade}</span>
                            </div>
                        `;
                    });
                }
                html += '</div>';
                document.getElementById('modalBody').innerHTML = html;
            } else {
                document.getElementById('modalBody').innerHTML = '<p style="text-align:center;color:#dc3545;">Failed to load remedies.</p>';
            }
        })
        .catch(err => {
            document.getElementById('modalBody').innerHTML = '<p style="text-align:center;color:#dc3545;">Error: ' + err.message + '</p>';
        });
}

function closeModal() {
    const modal = document.getElementById('rubricModal');
    if (modal) modal.classList.remove('active');
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

document.addEventListener('DOMContentLoaded', function() {
    const aiBtn = document.getElementById('aiRepertoryBtn');
    const aiSection = document.getElementById('ai-repertory-section');
    if (aiBtn && aiSection) {
        aiBtn.addEventListener('click', function() {
            aiBtn.disabled = true;
            aiBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
            aiSection.style.display = 'block';
            aiSection.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-brain fa-spin" style="font-size:2em;color:#764ba2;"></i><p>Analyzing selected rubrics with AI...</p></div>';
            // Collect selected rubric IDs
            const rubricInputs = document.querySelectorAll('input[name="rubrics[]"]');
            const rubricIds = Array.from(rubricInputs).map(input => input.value);
            fetch('<?php echo APP_URL; ?>/api/get_ai_repertory_suggestions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'rubric_ids=' + encodeURIComponent(JSON.stringify(rubricIds))
            })
            .then(res => res.json())
            .then(data => {
                aiBtn.disabled = false;
                aiBtn.innerHTML = '<i class="fas fa-robot"></i> AI Analysis';
                if (data.success && data.suggestions && data.suggestions.remedies) {
                    let html = '<h4 style="color:#667eea;"><i class="fas fa-robot"></i> AI Remedy Suggestions</h4>';
                    data.suggestions.remedies.forEach((remedy, idx) => {
                        html += `<div class="remedy-card" style="background:#fff;border-radius:8px;padding:15px;margin-bottom:12px;box-shadow:0 2px 8px #eee;">` +
                            `<div style="font-weight:bold;font-size:1.1em;">${idx+1}. ${remedy.name}</div>` +
                            `<div><strong>Potency:</strong> ${remedy.potency || ''}</div>` +
                            `<div><strong>Match:</strong> ${remedy.match_percentage || ''}%</div>` +
                            `<div><strong>Reasoning:</strong> ${remedy.reasoning || ''}</div>` +
                            `<div><strong>Reference:</strong> ${remedy.reference || 'Not specified'}</div>` +
                            `<div><strong>Matching Rubrics:</strong> ${(remedy.matching_rubrics ? remedy.matching_rubrics.join(', ') : '')}</div>` +
                        `</div>`;
                    });
                    if (data.suggestions.case_analysis) {
                        html += `<div style="background:#f8fafc;padding:12px;border-radius:8px;margin-top:10px;"><strong>Case Analysis:</strong> ${data.suggestions.case_analysis}</div>`;
                    }
                    if (data.suggestions.cautions) {
                        html += `<div style="background:#fff3cd;padding:12px;border-radius:8px;margin-top:10px;"><strong>Cautions:</strong> ${data.suggestions.cautions}</div>`;
                    }
                    aiSection.innerHTML = html;
                } else {
                    aiSection.innerHTML = `<div style="color:#dc3545;padding:30px;text-align:center;"><i class="fas fa-exclamation-triangle" style="font-size:2em;"></i><h4>AI Error</h4><p>${data.error || 'No suggestions returned.'}</p></div>`;
                }
            })
            .catch(err => {
                aiBtn.disabled = false;
                aiBtn.innerHTML = '<i class="fas fa-robot"></i> AI Analysis';
                aiSection.innerHTML = `<div style="color:#dc3545;padding:30px;text-align:center;"><i class="fas fa-exclamation-triangle" style="font-size:2em;"></i><h4>Connection Error</h4><p>${err.message}</p></div>`;
            });
        });
    }
    
    // Smart Search Functionality
    const symptomInput = document.getElementById('symptomInput');
    const smartSearchBtn = document.getElementById('smartSearchBtn');
    const smartSearchResults = document.getElementById('smartSearchResults');
    const smartSearchResultsBody = document.getElementById('smartSearchResultsBody');
    const categorySelect = document.getElementById('categorySelect');
    
    let searchTimeout = null;
    
    // Smart Search Button Click
    if (smartSearchBtn) {
        smartSearchBtn.addEventListener('click', function() {
            performSmartSearch();
        });
    }
    
    // Also trigger smart search on Enter key in input
    if (symptomInput) {
        symptomInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                performSmartSearch();
            }
        });
    }
    
    function performSmartSearch() {
        const symptom = symptomInput.value.trim();
        const category = categorySelect ? categorySelect.value : '';
        
        if (!symptom) {
            alert('Please enter a symptom description');
            return;
        }
        
        // Show loading state
        smartSearchBtn.disabled = true;
        smartSearchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
        smartSearchResults.style.display = 'block';
        smartSearchResultsBody.innerHTML = `
            <div class="smart-search-loading">
                <i class="fas fa-brain fa-spin"></i>
                <p>Analyzing your symptom and finding matching rubrics...</p>
                <small>This may take a few seconds if using AI enhancement</small>
            </div>
        `;
        
        // Call API
        const formData = new FormData();
        formData.append('symptom', symptom);
        if (category) formData.append('category', category);
        
        fetch('<?php echo APP_URL; ?>/api/get_rubric_suggestions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            smartSearchBtn.disabled = false;
            smartSearchBtn.innerHTML = '<i class="fas fa-magic"></i> Smart Search';
            
            if (data.success && data.rubrics && data.rubrics.length > 0) {
                let html = `
                    <div class="smart-search-info">
                        <i class="fas fa-magic"></i>
                        <div class="smart-search-info-text">
                            <strong>Found ${data.rubrics.length} matching rubrics for: "${data.symptom}"</strong>
                            <span>${data.ai_enhanced ? '✨ AI-enhanced search included' : 'Based on synonym matching'}</span>
                        </div>
                    </div>
                `;
                
                data.rubrics.forEach(rubric => {
                    const sourceClass = rubric.source === 'ai' ? 'ai' : 'database';
                    const sourceBadge = rubric.source === 'ai' 
                        ? '<span class="badge badge-ai"><i class="fas fa-robot"></i> AI Found</span>' 
                        : '';
                    
                    html += `
                        <div class="rubric-result-card">
                            <div class="rubric-info">
                                <div class="rubric-name">${escapeHtml(rubric.rubric)}</div>
                                <div class="rubric-complete">${escapeHtml(rubric.complete_rubric || '')}</div>
                                <div class="rubric-badges">
                                    <span class="badge badge-category">${escapeHtml(rubric.category)}</span>
                                    <span class="badge badge-remedies">${rubric.remedy_count || 0} remedies</span>
                                    ${sourceBadge}
                                </div>
                            </div>
                            <button class="add-rubric-btn" onclick="addRubricFromSmartSearch(${rubric.id})">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    `;
                });
                
                smartSearchResultsBody.innerHTML = html;
            } else {
                smartSearchResultsBody.innerHTML = `
                    <div style="text-align:center;padding:40px;color:#666;">
                        <i class="fas fa-search" style="font-size:3rem;opacity:0.3;margin-bottom:15px;display:block;"></i>
                        <h4>No matching rubrics found</h4>
                        <p>Try different wording or use more specific symptoms.</p>
                        <p style="font-size:0.85rem;margin-top:10px;">
                            <strong>Tips:</strong><br>
                            • Use simpler terms (e.g., "anger" instead of "gets angry")<br>
                            • Try related words (e.g., "irritable" or "contradiction")<br>
                            • Select a category to narrow results
                        </p>
                    </div>
                `;
            }
        })
        .catch(err => {
            smartSearchBtn.disabled = false;
            smartSearchBtn.innerHTML = '<i class="fas fa-magic"></i> Smart Search';
            smartSearchResultsBody.innerHTML = `
                <div style="text-align:center;padding:40px;color:#dc3545;">
                    <i class="fas fa-exclamation-triangle" style="font-size:2rem;margin-bottom:15px;display:block;"></i>
                    <h4>Error</h4>
                    <p>${err.message}</p>
                </div>
            `;
        });
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

// Add rubric from smart search results
function addRubricFromSmartSearch(rubricId) {
    const form = document.getElementById('searchForm');
    
    // Check if already selected
    const existingInputs = form.querySelectorAll('input[name="rubrics[]"]');
    for (let input of existingInputs) {
        if (parseInt(input.value) === rubricId) {
            alert('This rubric is already selected');
            return;
        }
    }
    
    // Add the rubric
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'rubrics[]';
    input.value = rubricId;
    form.appendChild(input);
    
    // Submit form to refresh with new selection
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


