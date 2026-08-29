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

// Only show the Kent verified badge for rubrics cross-checked against
// Homeoint's Kent Repertory source.
function isKentMindRubrics1To10VerifiedSource(array $row): bool {
    if (!empty($row['is_verified'])) {
        $normalizedSource = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($row['verified_source'] ?? '')));
        return strpos($normalizedSource, 'homeointkentrepertory') !== false;
    }

    $normalizedSource = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($row['verified_source'] ?? '')));

    return strpos($normalizedSource, 'homeointkentrepertory') !== false;
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
    'mind' => 'Mind', 'vertigo' => 'Vertigo', 'head' => 'Head',
    'eye' => 'Eye', 'vision' => 'Vision', 'ear' => 'Ear', 'hearing' => 'Hearing',
    'nose' => 'Nose', 'face' => 'Face', 'mouth' => 'Mouth', 'teeth' => 'Teeth',
    'throat' => 'Throat', 'stomach' => 'Stomach', 'abdomen' => 'Abdomen',
    'rectum' => 'Rectum', 'stool' => 'Stool', 'bladder' => 'Bladder',
    'kidneys' => 'Kidneys', 'urine' => 'Urine', 'urinary' => 'Urinary',
    'male' => 'Male', 'female' => 'Female', 'larynx' => 'Larynx',
    'respiration' => 'Respiration', 'respiratory' => 'Respiratory',
    'cough' => 'Cough', 'expectoration' => 'Expectoration',
    'chest' => 'Chest', 'heart' => 'Heart', 'back' => 'Back',
    'extremities' => 'Extremities', 'skin' => 'Skin', 'sleep' => 'Sleep',
    'perspiration' => 'Perspiration', 'fever' => 'Fever',
    'general' => 'General', 'generalities' => 'Generalities'
];

// Pagination for rubrics search
$rubrics = [];
$rubricPage = isset($_GET['rubric_page']) ? max(1, intval($_GET['rubric_page'])) : 1;
$rubricsPerPage = 25;
$rubricOffset = ($rubricPage - 1) * $rubricsPerPage;
$totalRubrics = 0;
$totalRubricPages = 0;

// =========================================================================
// SYNONYM MAP (preserved from legacy backend) - kept identical to maintain
// search relevance and 100-query regression test coverage.
// =========================================================================
$synonymMap = [
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
    'headache' => ['head pain', 'cephalalgia', 'head ache', 'migraine', 'pain in head'],
    'migraine' => ['headache', 'head pain', 'one-sided headache', 'hemicrania'],
    'burning' => ['burnt', 'scalding', 'heat sensation', 'smarting'],
    'throbbing' => ['pulsating', 'pulsation', 'beating', 'hammering', 'pounding'],
    'sharp' => ['stitching', 'stabbing', 'lancinating', 'cutting', 'shooting', 'pricking'],
    'dull' => ['aching', 'heavy', 'pressing', 'bearing down', 'dragging'],
    'cramping' => ['cramp', 'spasm', 'spasmodic', 'griping', 'colic'],
    'neuralgic' => ['neuralgia', 'nerve pain', 'shooting', 'radiating'],
    'tired' => ['fatigue', 'exhaustion', 'weakness', 'prostration', 'lassitude', 'weary'],
    'weak' => ['weakness', 'debility', 'prostration', 'exhaustion', 'feeble', 'powerless'],
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
    'blue' => ['blueness', 'cyanosis', 'cyanotic', 'discoloration', 'livid', 'purple', 'dusky'],
    'blueness' => ['blue', 'cyanosis', 'discoloration', 'livid', 'cyanotic'],
    'pale' => ['pallor', 'paleness', 'white', 'discoloration', 'blanched', 'anemic'],
    'red' => ['redness', 'flushed', 'erythema', 'discoloration', 'congested', 'crimson'],
    'yellow' => ['yellowness', 'jaundice', 'icterus', 'discoloration', 'sallow'],
    'black' => ['blackness', 'dark', 'discoloration', 'gangrenous', 'ecchymosis'],
    'purple' => ['purplish', 'livid', 'blue', 'discoloration', 'violaceous'],
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
    'knee' => ['knees', 'patella', 'patellar'],
    'ankle' => ['ankles', 'malleolus', 'tarsal'],
    'wrist' => ['wrists', 'carpal'],
    'elbow' => ['elbows', 'cubital'],
    'shoulder' => ['shoulders', 'deltoid', 'scapula'],
    'hip' => ['hips', 'coxal', 'iliac', 'acetabulum'],
    'joint' => ['joints', 'articular', 'articulation', 'arthritis'],
    'stiff' => ['stiffness', 'rigid', 'tight', 'cannot move'],
    'swollen' => ['swelling', 'edema', 'oedema', 'tumefaction', 'puffiness'],
    'itching' => ['itch', 'itchy', 'pruritus', 'scratching'],
    'eruption' => ['rash', 'eruptions', 'exanthema', 'skin eruption'],
    'eczema' => ['dermatitis', 'skin eruption', 'itching'],
    'urticaria' => ['hives', 'wheals', 'nettle rash'],
    'boil' => ['furuncle', 'abscess', 'carbuncle'],
    'dry' => ['dryness', 'parched', 'rough'],
    'sneezing' => ['sneeze', 'sneezes', 'sternutation', 'paroxysmal'],
    'sneeze' => ['sneezing', 'sneezes'],
    'coryza' => ['runny nose', 'nasal discharge', 'rhinitis', 'cold'],
    'stuffy' => ['stuffiness', 'obstruction', 'blocked', 'congestion'],
    'discharge' => ['secretion', 'flow', 'drainage', 'exudate'],
    'morning' => ['am', 'waking', 'rising', 'forenoon', 'on waking', 'sunrise'],
    'afternoon' => ['pm', 'post meridian', '4pm', '4 pm'],
    'evening' => ['pm', 'twilight', 'dusk', 'sunset'],
    'night' => ['midnight', 'nocturnal', 'during sleep', 'after midnight'],
    'worse' => ['aggravation', 'agg', 'aggravated', 'exacerbated'],
    'better' => ['amelioration', 'amel', 'ameliorated', 'improved', 'relief'],
    'motion' => ['movement', 'moving', 'walking', 'exercise'],
    'rest' => ['resting', 'lying', 'stillness', 'repose'],
    'cold' => ['coldness', 'chill', 'chilly', 'frigid', 'freezing'],
    'heat' => ['hot', 'warmth', 'warm', 'temperature'],
    'pressure' => ['pressing', 'hard pressure', 'touch'],
    'touch' => ['touching', 'pressure', 'contact'],
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
    'jerking' => ['jerk', 'jerks', 'twitching', 'startling', 'starts'],
    'starts' => ['starting', 'startle', 'jerking', 'twitching'],
    'twitching' => ['twitch', 'twitches', 'jerking', 'convulsive'],
    'falling' => ['fall', 'going to sleep', 'on going to sleep'],
    'cradle' => ['scalp', 'head', 'infant'],
    'cap' => ['crust', 'scab', 'eruption'],
    'babies' => ['baby', 'infant', 'infants', 'child', 'children'],
    'infant' => ['infants', 'baby', 'babies', 'newborn', 'child'],
    'child' => ['children', 'infant', 'baby', 'babies'],
    'restlessness' => ['restless', 'cannot rest', 'tossing', 'fidgety', 'agitation', 'cannot sit still'],
    'anxious' => ['anxiety', 'fear', 'worry', 'apprehension', 'restlessness', 'nervous', 'tension'],
    'irritability' => ['irritable', 'angry', 'cross', 'peevish', 'touchy', 'snappish', 'fretful', 'impatient'],
    'fearful' => ['fear', 'anxiety', 'fright', 'dread', 'terror', 'apprehensive', 'afraid', 'phobia'],
    'sadness' => ['sad', 'grief', 'sorrow', 'melancholy', 'depression', 'dejection', 'gloomy'],
    'depression' => ['depressed', 'sad', 'melancholy', 'despondent', 'hopeless', 'despair'],
    'eating' => ['meals', 'food', 'after eating', 'dinner'],
    'food' => ['meals', 'eating', 'diet'],
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
    'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
    'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
    'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used',
    'and', 'but', 'or', 'nor', 'for', 'yet', 'so', 'both', 'either', 'neither',
    'not', 'only', 'own', 'same', 'than', 'too', 'very', 's', 't', 'just',
    'of', 'to', 'in', 'on', 'at', 'by', 'from', 'up', 'about', 'into', 'over',
    'after', 'before', 'during', 'without', 'under', 'around', 'among', 'between',
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
    'patient', 'patients', 'person', 'people', 'he', 'she', 'it', 'they',
    'i', 'we', 'you', 'my', 'your', 'his', 'her', 'its', 'our', 'their',
    'me', 'him', 'them', 'us', 'this', 'that', 'these', 'those',
    'who', 'whom', 'which', 'what', 'whose', 'whoever', 'whatever', 'whichever',
    'some', 'any', 'no', 'every', 'each', 'all', 'many', 'much', 'more', 'most',
    'few', 'little', 'less', 'least', 'other', 'another', 'such',
    'when', 'where', 'why', 'how', 'while', 'as', 'if', 'then', 'else',
    'always', 'never', 'sometimes', 'often', 'usually', 'still', 'already',
    'even', 'also', 'again', 'further', 'once', 'twice', 'here', 'there',
    'now', 'then', 'today', 'yesterday', 'tomorrow', 'ago', 'later', 'earlier',
    'sometimes', 'perhaps', 'maybe', 'probably', 'possibly', 'certainly',
    'really', 'actually', 'especially', 'particularly', 'mainly', 'mostly',
    'because', 'since', 'although', 'though', 'unless', 'until', 'whereas',
    'however', 'therefore', 'thus', 'hence', 'otherwise', 'instead', 'besides',
    'like', 'likes', 'liked', 'liking',
];

function expandSearchTerms($query, $synonymMap, $stopWords = []) {
    $queryLower = mb_strtolower(trim($query));
    $words = preg_split('/[\s,\-]+/', $queryLower);
    $searchTerms = [];
    $originalTerms = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) < 3 || in_array($word, $stopWords)) continue;
        $originalTerms[] = $word;
        $searchTerms[] = $word;
        if (isset($synonymMap[$word])) {
            $searchTerms = array_merge($searchTerms, $synonymMap[$word]);
        }
    }
    if (empty($searchTerms)) {
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 4 && !in_array($word, $stopWords)) {
                $searchTerms[] = $word;
                $originalTerms[] = $word;
            }
        }
    }
    return [
        'terms'     => array_values(array_unique($searchTerms)),
        'originals' => array_values(array_unique($originalTerms)),
    ];
}

// Search when there's a search query OR a category selected
if (!empty($searchQuery) || !empty($category)) {
    $params = [];
    if (!empty($searchQuery)) {
        $expansion     = expandSearchTerms($searchQuery, $synonymMap, $stopWords);
        $searchTerms   = $expansion['terms'];
        $originalTerms = $expansion['originals'];
        $stopWordsOnlyQuery = (empty($searchTerms) && !empty(trim($searchQuery)));

        if (empty($searchTerms)) {
            $rubrics = []; $totalRubrics = 0; $totalRubricPages = 0;
        } else {
            $conditions = [];
            foreach ($searchTerms as $term) {
                $conditions[] = "(LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?)";
                $params[] = '%' . $term . '%';
                $params[] = '%' . $term . '%';
            }
            $originalSet = array_flip($originalTerms);
            $relevanceParts = [];
            foreach ($searchTerms as $term) {
                if (strlen($term) < 3) continue;
                $weight = isset($originalSet[$term]) ? 2 : 1;
                $relevanceParts[] = "CASE WHEN LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ? THEN $weight ELSE 0 END";
                $params[] = '%' . $term . '%';
                $params[] = '%' . $term . '%';
            }
            $relevanceCase = empty($relevanceParts)
                ? "0 as relevance_score"
                : "(" . implode(" + ", $relevanceParts) . ") as relevance_score";

            $sqlBase = "FROM repertory r LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id WHERE (" . implode(' OR ', $conditions) . ")";
            if (!empty($category)) {
                $sqlBase .= " AND LOWER(r.category) = ?";
                $params[] = strtolower($category);
            }
            $relevanceParams = [];
            foreach ($searchTerms as $term) {
                if (strlen($term) < 3) continue;
                $relevanceParams[] = '%' . $term . '%';
                $relevanceParams[] = '%' . $term . '%';
            }
            $countParams = [];
            foreach ($searchTerms as $term) {
                $countParams[] = '%' . $term . '%';
                $countParams[] = '%' . $term . '%';
            }
            if (!empty($category)) $countParams[] = strtolower($category);

            $countSql = "SELECT COUNT(DISTINCT r.id) as total " . $sqlBase;
            $countResult = DB::queryOne($countSql, $countParams);
            $totalRubrics = $countResult['total'] ?? 0;
            $totalRubricPages = ceil($totalRubrics / $rubricsPerPage);

            $selectParams = array_merge($relevanceParams, $countParams);
            $safeRubricOffset = (int)$rubricOffset;
            $safeRubricsPerPage = (int)$rubricsPerPage;
            $sql = "SELECT r.*, COUNT(rr.remedy_id) as remedy_count, $relevanceCase " . $sqlBase . " GROUP BY r.id ORDER BY relevance_score DESC, r.category, r.rubric COLLATE utf8mb4_unicode_ci LIMIT $safeRubricOffset, $safeRubricsPerPage";
            $rubrics = DB::query($sql, $selectParams);
        }
    } else {
        // === BROWSE MODE: chapter selected, no search query ===
        // Fetch ALL rubrics in the chapter alphabetically (Synthesis-style).
        $sqlBase = "FROM repertory r LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id WHERE LOWER(r.category) = ?";
        $countParams = [strtolower($category)];
        $relevanceCase = "0 as relevance_score";

        $countSql = "SELECT COUNT(DISTINCT r.id) as total " . $sqlBase;
        $countResult = DB::queryOne($countSql, $countParams);
        $totalRubrics = $countResult['total'] ?? 0;
        $totalRubricPages = 1; // browse view: no pagination

        $selectParams = $countParams;
        // No LIMIT — we want the full alphabetical listing
        $sql = "SELECT r.*, COUNT(rr.remedy_id) as remedy_count, $relevanceCase " . $sqlBase . " GROUP BY r.id ORDER BY r.rubric COLLATE utf8mb4_unicode_ci ASC";
        $rubrics = DB::query($sql, $selectParams);
    }
}

// Browse-mode flag (chapter selected, no free-text search) — used by template
$browseMode = empty($searchQuery) && !empty($category);

// In browse mode, group rubrics by first alphabetic letter for the A–Z jump bar
$browseGroups = [];
$browseLetters = [];
if ($browseMode && !empty($rubrics)) {
    foreach ($rubrics as $r) {
        $first = strtoupper(mb_substr(trim($r['rubric']), 0, 1));
        if (!preg_match('/[A-Z]/', $first)) $first = '#';
        $browseGroups[$first][] = $r;
    }
    $browseLetters = array_keys($browseGroups);
    sort($browseLetters);
}

// Repertorization - find remedies for selected rubrics
$repertorization = [];
$rubricRemedyGrid = []; // [rubricId][remedyId] => grade  (for Synthesis-style matrix view)
if (!empty($selectedRubrics)) {
    $placeholders = implode(',', array_fill(0, count($selectedRubrics), '?'));
    $sql = "SELECT rem.id, rem.remedy_name as name, rem.common_name,
                   GROUP_CONCAT(CONCAT(rr.repertory_id, ':', rr.grade) SEPARATOR ',') as rubric_grades
            FROM remedies rem
            INNER JOIN repertory_remedies rr ON rem.id = rr.remedy_id
            WHERE rr.repertory_id IN ($placeholders)
            GROUP BY rem.id
            ORDER BY COUNT(DISTINCT rr.repertory_id) DESC, rem.remedy_name";
    $remedies = DB::query($sql, $selectedRubrics);

    foreach ($remedies as $remedy) {
        $grades = [];
        foreach (explode(',', $remedy['rubric_grades']) as $rg) {
            list($rubricId, $grade) = explode(':', $rg);
            $g = max(1, min(4, (int)$grade));
            $grades[(int)$rubricId] = $g;
            $rubricRemedyGrid[(int)$rubricId][(int)$remedy['id']] = $g;
        }
        $totalScore = 0; $rubricCount = 0; $gradeBreakdown = [];
        foreach ($selectedRubrics as $rubricId) {
            if (isset($grades[$rubricId])) {
                $g = $grades[$rubricId];
                $totalScore += $g;
                $rubricCount++;
                $gradeBreakdown[] = $g;
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
    usort($repertorization, function($a, $b) {
        if ($a['total_score'] == $b['total_score']) return $b['rubric_count'] - $a['rubric_count'];
        return $b['total_score'] - $a['total_score'];
    });
}

// Get selected rubric details
$selectedRubricDetails = [];
if (!empty($selectedRubrics)) {
    $placeholders = implode(',', array_fill(0, count($selectedRubrics), '?'));
    $selectedRubricDetails = DB::query("SELECT * FROM repertory WHERE id IN ($placeholders)", $selectedRubrics);
}

// Top remedies (Synthesis grid columns) - cap at 12 to keep matrix readable
$topRemedies = array_slice($repertorization, 0, 12);

// Helper to build a query-string carrying selected rubrics (preserves clipboard across navigation)
function syn_qs(array $extra = [], array $selected = []): string {
    $base = $_GET;
    foreach ($extra as $k => $v) { $base[$k] = $v; }
    if (!isset($base['rubrics']) && !empty($selected)) $base['rubrics'] = $selected;
    return http_build_query($base);
}

// Category icon mapping
function syn_cat_icon(string $cat): string {
    return match($cat) {
        'mind' => 'fa-brain',
        'head' => 'fa-head-side-virus',
        'eye', 'vision' => 'fa-eye',
        'ear', 'hearing' => 'fa-ear-listen',
        'nose' => 'fa-wind',
        'face' => 'fa-face-meh',
        'mouth' => 'fa-comment-medical',
        'teeth' => 'fa-tooth',
        'throat', 'larynx' => 'fa-microphone',
        'stomach' => 'fa-utensils',
        'abdomen' => 'fa-circle-dot',
        'rectum', 'stool' => 'fa-toilet',
        'bladder', 'kidneys', 'urine', 'urinary' => 'fa-droplet',
        'male' => 'fa-mars',
        'female' => 'fa-venus',
        'respiration', 'respiratory', 'cough', 'expectoration' => 'fa-lungs',
        'chest' => 'fa-shirt',
        'heart' => 'fa-heart-pulse',
        'back' => 'fa-person-rays',
        'extremities' => 'fa-hand',
        'skin' => 'fa-hand-dots',
        'sleep' => 'fa-bed',
        'perspiration' => 'fa-water',
        'fever' => 'fa-temperature-high',
        'vertigo' => 'fa-arrows-spin',
        'general', 'generalities' => 'fa-circle-user',
        default => 'fa-folder'
    };
}

$pageTitle = 'Repertory Search';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
/* =====================================================================
   SYNTHESIS-INSPIRED REPERTORY UI
   Three-pane workspace: Chapters | Rubrics | Clipboard / Analysis
   ===================================================================== */
:root {
    --syn-bg: #f3f5f9;
    --syn-surface: #ffffff;
    --syn-surface-2: #f8fafc;
    --syn-border: #e2e8f0;
    --syn-border-strong: #cbd5e1;
    --syn-text: #0f172a;
    --syn-text-muted: #64748b;
    --syn-text-dim: #94a3b8;
    --syn-accent: #1e3a8a;          /* Synthesis-like deep blue */
    --syn-accent-2: #3b82f6;
    --syn-accent-soft: #dbeafe;
    --syn-success: #047857;
    --syn-warning: #b45309;
    --syn-danger: #b91c1c;
    --syn-grade-1: #94a3b8;
    --syn-grade-2: #2563eb;
    --syn-grade-3: #b91c1c;
    --syn-grade-4: #6d28d9;
}

/* Make the Synthesis workspace fill all available space inside .main-content,
   negate its 0.75rem padding so the UI is edge-to-edge, and lock its height
   to the viewport so the 3 panes scroll independently like a desktop app. */
.syn-app, .syn-app * { box-sizing: border-box; }
/* Zero out .main-content's variable padding (0.5rem - 2rem across breakpoints)
   so our app is truly edge-to-edge regardless of viewport size. */
body:has(.syn-app) .main-content { padding: 0 !important; overflow: hidden; }
body:has(.syn-app) { overflow: hidden; }
.syn-app {
    /* Fill the full viewport below the site header */
    height: calc(100vh - var(--header-height, 64px));
    width: 100%;
    max-width: 100%;
    background: var(--syn-bg);
    color: var(--syn-text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}
@supports not selector(:has(*)) {
    /* Fallback: scope a body class via JS-less cue */
    .syn-app { contain: layout; }
}

/* Toolbar */
.syn-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
    border-bottom: 1px solid var(--syn-border);
    flex-shrink: 0;
    flex-wrap: wrap;
    row-gap: 8px;
}
.syn-brand {
    display: flex; align-items: center; gap: 8px;
    font-weight: 700; color: var(--syn-accent);
    font-size: 15px; padding-right: 12px;
    border-right: 1px solid var(--syn-border);
}
.syn-brand i { font-size: 18px; }
.syn-search-form {
    display: flex; align-items: center; gap: 8px;
    flex: 1; max-width: 880px;
}
.syn-search-box {
    position: relative; flex: 1;
}
.syn-search-box input {
    width: 100%;
    height: 36px;
    padding: 0 36px 0 38px;
    border: 1px solid var(--syn-border-strong);
    border-radius: 6px;
    background: #fff;
    font-size: 14px;
    color: var(--syn-text);
    transition: border-color .15s, box-shadow .15s;
}
.syn-search-box input:focus {
    outline: none;
    border-color: var(--syn-accent-2);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.syn-search-box .syn-search-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--syn-text-dim); pointer-events: none;
}
.syn-search-box .syn-clear-btn {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; border-radius: 50%;
    border: none; background: var(--syn-border); color: var(--syn-text-muted);
    cursor: pointer; display: none; align-items: center; justify-content: center;
    font-size: 11px;
}
.syn-search-box input:not(:placeholder-shown) ~ .syn-clear-btn { display: flex; }

.syn-cat-select {
    height: 36px;
    padding: 0 28px 0 10px;
    border: 1px solid var(--syn-border-strong);
    border-radius: 6px;
    background: #fff;
    font-size: 13px;
    color: var(--syn-text);
    min-width: 170px;
}

.syn-btn {
    height: 36px; padding: 0 14px;
    border: 1px solid var(--syn-border-strong); background: #fff;
    color: var(--syn-text); border-radius: 6px;
    font-size: 13px; font-weight: 500;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: background .12s, border-color .12s, color .12s;
    text-decoration: none; white-space: nowrap;
}
.syn-btn:hover { background: var(--syn-surface-2); border-color: var(--syn-accent-2); color: var(--syn-accent); }
.syn-btn-primary { background: var(--syn-accent); color: #fff; border-color: var(--syn-accent); }
.syn-btn-primary:hover { background: #1e40af; color: #fff; border-color: #1e40af; }
.syn-btn-accent { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: #fff; border-color: transparent; }
.syn-btn-accent:hover { filter: brightness(1.08); color: #fff; }
.syn-btn-ghost { background: transparent; border-color: transparent; color: var(--syn-text-muted); }
.syn-btn-ghost:hover { background: var(--syn-surface-2); color: var(--syn-accent); border-color: transparent; }
.syn-btn-sm { height: 28px; padding: 0 10px; font-size: 12px; }
.syn-btn-icon { width: 32px; padding: 0; justify-content: center; }
.syn-btn:disabled { opacity: .55; cursor: not-allowed; }

.syn-toolbar-meta {
    margin-left: auto;
    display: flex; align-items: center; gap: 14px;
    font-size: 12px; color: var(--syn-text-muted);
    flex-shrink: 0;
}
.syn-toolbar-meta strong { color: var(--syn-text); font-weight: 600; }
@media (max-width: 1280px) { .syn-toolbar-meta { gap: 10px; font-size: 11px; } }
@media (max-width: 1100px) { .syn-toolbar-meta { display: none; } }

/* Main 3-pane layout */
.syn-workspace {
    flex: 1;
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr) 380px;
    min-height: 0;
    background: var(--syn-bg);
    position: relative;
}
@media (max-width: 1400px) { .syn-workspace { grid-template-columns: 240px minmax(0, 1fr) 360px; } }
@media (max-width: 1280px) { .syn-workspace { grid-template-columns: 220px minmax(0, 1fr) 320px; } }
@media (max-width: 1100px) { .syn-workspace { grid-template-columns: 200px minmax(0, 1fr) 300px; } }

/* Tablet: hide right pane, show as drawer */
@media (max-width: 1024px) {
    .syn-workspace { grid-template-columns: 220px minmax(0, 1fr); }
    .syn-pane-right {
        position: absolute;
        top: 0; bottom: 0; right: 0;
        width: min(380px, 90vw);
        transform: translateX(100%);
        transition: transform .25s ease;
        z-index: 30;
        box-shadow: -8px 0 24px rgba(15,23,42,.18);
        display: flex;
    }
    .syn-pane-right.syn-mobile-open { transform: translateX(0); }
}

/* Mobile: hide both side panes, show as drawers */
@media (max-width: 768px) {
    .syn-workspace { grid-template-columns: minmax(0, 1fr); }
    .syn-pane-left {
        position: absolute;
        top: 0; bottom: 0; left: 0;
        width: min(280px, 85vw);
        transform: translateX(-100%);
        transition: transform .25s ease;
        z-index: 30;
        box-shadow: 8px 0 24px rgba(15,23,42,.18);
        display: flex;
    }
    .syn-pane-left.syn-mobile-open { transform: translateX(0); }
}

/* Backdrop while drawer is open */
.syn-backdrop {
    position: absolute; inset: 0;
    background: rgba(15,23,42,.4);
    z-index: 25;
    display: none;
}
.syn-backdrop.syn-show { display: block; }

.syn-pane {
    background: var(--syn-surface);
    border-right: 1px solid var(--syn-border);
    display: flex; flex-direction: column;
    min-height: 0;
    overflow: hidden;
}
.syn-pane:last-child { border-right: none; border-left: 1px solid var(--syn-border); }
.syn-pane-header {
    padding: 10px 14px;
    background: var(--syn-surface-2);
    border-bottom: 1px solid var(--syn-border);
    display: flex; align-items: center; justify-content: space-between;
    font-size: 12px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--syn-text-muted);
    flex-shrink: 0;
}
.syn-pane-body { flex: 1; overflow-y: auto; padding: 8px; min-height: 0; }
.syn-pane-footer {
    padding: 8px 12px;
    background: var(--syn-surface-2);
    border-top: 1px solid var(--syn-border);
    font-size: 12px;
    color: var(--syn-text-muted);
    flex-shrink: 0;
}

/* === LEFT PANE: Chapters tree === */
.syn-chapter-list { list-style: none; padding: 0; margin: 0; }
.syn-chapter-list li a {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px;
    border-radius: 5px;
    text-decoration: none;
    color: var(--syn-text);
    font-size: 13px;
    transition: background .12s;
    margin-bottom: 1px;
}
.syn-chapter-list li a:hover { background: var(--syn-surface-2); color: var(--syn-accent); }
.syn-chapter-list li a.syn-active { background: var(--syn-accent-soft); color: var(--syn-accent); font-weight: 600; }
.syn-chapter-list .syn-cat-icon {
    width: 24px; text-align: center;
    color: var(--syn-text-dim);
    font-size: 14px;
}
.syn-chapter-list li a.syn-active .syn-cat-icon { color: var(--syn-accent); }
.syn-chapter-list .syn-cat-name { flex: 1; }
.syn-chapter-list .syn-cat-count {
    font-size: 11px; color: var(--syn-text-dim);
    background: var(--syn-surface-2);
    padding: 2px 8px; border-radius: 999px;
    border: 1px solid var(--syn-border);
}
.syn-chapter-list li a.syn-active .syn-cat-count {
    background: #fff; color: var(--syn-accent); border-color: var(--syn-accent-soft);
}
.syn-chapter-list-all {
    display: block; padding: 8px 10px;
    border-radius: 5px; text-decoration: none;
    background: var(--syn-surface-2); color: var(--syn-text);
    font-size: 13px; font-weight: 600;
    margin-bottom: 6px;
    border: 1px solid var(--syn-border);
}
.syn-chapter-list-all:hover { background: var(--syn-accent-soft); color: var(--syn-accent); }

/* === CENTER PANE: rubric results === */
.syn-results-toolbar {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px;
    background: var(--syn-surface);
    border-bottom: 1px solid var(--syn-border);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.syn-results-title {
    font-size: 14px; font-weight: 600; color: var(--syn-text);
}
.syn-results-meta {
    font-size: 12px; color: var(--syn-text-muted);
}
.syn-results-actions { margin-left: auto; display: flex; gap: 6px; }

.syn-rubric-group {
    margin-bottom: 18px;
}
.syn-rubric-group-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    font-weight: 700;
    color: var(--syn-accent);
    padding: 6px 10px;
    border-bottom: 1px solid var(--syn-border);
    margin-bottom: 6px;
    display: flex; align-items: center; gap: 8px;
}

.syn-rubric-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px;
    border-radius: 6px;
    background: var(--syn-surface);
    border: 1px solid transparent;
    margin-bottom: 4px;
    transition: background .12s, border-color .12s;
}
.syn-rubric-row:hover {
    background: var(--syn-surface-2);
    border-color: var(--syn-border);
}

/* === Synthesis-style alphabetical BROWSE view === */
.syn-az-bar {
    position: sticky; top: 0; z-index: 5;
    display: flex; flex-wrap: wrap; gap: 2px;
    padding: 6px 8px;
    background: #fff;
    border-bottom: 1px solid var(--syn-border);
    margin: -12px -12px 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.syn-az-bar a {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px;
    padding: 0 4px;
    font-size: 11px; font-weight: 700;
    color: var(--syn-text-muted);
    background: transparent;
    border-radius: 4px;
    text-decoration: none;
    transition: all .12s;
}
.syn-az-bar a:hover { background: var(--syn-accent); color: #fff; }
.syn-az-bar a.syn-az-disabled {
    color: #cbd5e1; pointer-events: none; opacity: .6;
}
.syn-az-bar .syn-az-meta {
    margin-left: auto; font-size: 11px; color: var(--syn-text-dim);
    align-self: center; font-weight: 500;
}

.syn-browse-letter {
    margin-top: 14px;
    scroll-margin-top: 50px;
}
.syn-browse-letter:first-of-type { margin-top: 0; }
.syn-browse-letter h3 {
    font-size: 22px; font-weight: 800;
    color: var(--syn-accent);
    margin: 0 0 4px;
    padding-bottom: 4px;
    border-bottom: 2px solid var(--syn-accent);
    letter-spacing: .5px;
}
.syn-browse-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 24px;
}
@media (max-width: 900px) { .syn-browse-list { grid-template-columns: 1fr; } }
.syn-browse-row {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 8px;
    border-bottom: 1px dotted #e2e8f0;
    font-size: 13px;
    cursor: pointer;
    transition: background .1s;
    min-width: 0;
}
.syn-browse-row:hover { background: #eff6ff; }
.syn-browse-row.syn-selected {
    background: #dbeafe; font-weight: 600;
}
.syn-browse-row .syn-bw-name {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    color: var(--syn-text);
    min-width: 0;
}
.syn-browse-row .syn-bw-main {
    flex: 1;
    min-width: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.syn-browse-row .syn-bw-tools {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.syn-browse-row.syn-zero .syn-bw-name { color: var(--syn-text-dim); }
.syn-browse-row .syn-bw-count {
    flex-shrink: 0;
    min-width: 32px; text-align: right;
    font-variant-numeric: tabular-nums;
    font-size: 12px; font-weight: 600;
    color: var(--syn-text-muted);
    padding: 0 4px;
}
.syn-browse-row .syn-bw-kent {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 1px 6px;
    border-radius: 999px;
    border: 1px solid #6ee7b7;
    background: #d1fae5;
    color: #065f46;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.4;
}
.syn-clip-kent {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 5px;
    padding: 1px 6px;
    border-radius: 999px;
    border: 1px solid #6ee7b7;
    background: #d1fae5;
    color: #065f46;
    font-size: 9px;
    font-weight: 700;
    vertical-align: middle;
}
.syn-browse-row.syn-zero .syn-bw-count { color: #cbd5e1; }
.syn-browse-row .syn-bw-add {
    flex-shrink: 0;
    background: transparent; border: none;
    color: var(--syn-text-dim);
    cursor: pointer; padding: 2px 6px; border-radius: 3px;
    font-size: 11px; opacity: 0;
    transition: opacity .12s, color .12s;
}
.syn-browse-row:hover .syn-bw-add,
.syn-browse-row.syn-selected .syn-bw-add { opacity: 1; }
.syn-browse-row .syn-bw-add:hover { color: var(--syn-accent); background: #fff; }
.syn-browse-row.syn-selected .syn-bw-add { color: var(--syn-danger); }
.syn-rubric-row.syn-selected {
    background: #ecfdf5;
    border-color: #a7f3d0;
}
.syn-rubric-row.syn-selected .syn-add-btn {
    background: var(--syn-success); color: #fff; border-color: var(--syn-success);
}

.syn-rubric-checkbox {
    margin-top: 3px;
    width: 16px; height: 16px;
    accent-color: var(--syn-accent);
    cursor: pointer;
    flex-shrink: 0;
}
.syn-rubric-main { flex: 1; min-width: 0; }
.syn-rubric-text {
    font-size: 14px; color: var(--syn-text); font-weight: 500;
    line-height: 1.4;
    cursor: pointer;
}
.syn-rubric-text:hover { color: var(--syn-accent); }
.syn-rubric-text mark {
    background: #fef08a; color: inherit; padding: 0 2px; border-radius: 2px;
}
.syn-rubric-sub {
    font-size: 12px; color: var(--syn-text-muted); margin-top: 2px;
}
.syn-rubric-meta {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 6px;
    font-size: 11px;
}
.syn-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--syn-surface-2);
    border: 1px solid var(--syn-border);
    color: var(--syn-text-muted);
    font-size: 11px;
}
.syn-tag-remedy { background: #fef3c7; border-color: #fde68a; color: #92400e; }
.syn-tag-source { background: var(--syn-accent-soft); border-color: #bfdbfe; color: var(--syn-accent); }
.syn-tag-verified { background: #d1fae5; border-color: #6ee7b7; color: #065f46; }
.syn-tag-cat { background: #fff; border-color: var(--syn-border); color: var(--syn-text-muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; font-size: 10px; }

.syn-rubric-actions {
    display: flex; gap: 4px; align-items: center;
    flex-shrink: 0;
}

.syn-add-btn {
    width: 30px; height: 30px;
    padding: 0; border-radius: 6px;
    background: #fff; border: 1px solid var(--syn-border-strong);
    color: var(--syn-text-muted); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all .12s;
}
.syn-add-btn:hover { border-color: var(--syn-accent); color: var(--syn-accent); background: var(--syn-accent-soft); }

/* Empty / welcome */
.syn-empty {
    text-align: center;
    padding: 60px 24px;
    color: var(--syn-text-muted);
}
.syn-empty i { font-size: 48px; color: var(--syn-text-dim); margin-bottom: 14px; opacity: .65; }
.syn-empty h3 { color: var(--syn-text); margin: 0 0 6px; font-size: 16px; }
.syn-empty p { margin: 0; font-size: 13px; }

.syn-quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
    margin: 24px auto 0;
    max-width: 720px;
}
.syn-quick-grid a {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 14px 10px; border: 1px solid var(--syn-border);
    border-radius: 8px; background: var(--syn-surface);
    text-decoration: none; color: var(--syn-text);
    transition: all .15s;
    font-size: 12px;
}
.syn-quick-grid a:hover {
    border-color: var(--syn-accent); background: var(--syn-accent-soft);
    color: var(--syn-accent); transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30,58,138,.08);
}
.syn-quick-grid a i { font-size: 22px; color: var(--syn-accent); }
.syn-quick-grid a .syn-q-name { font-weight: 600; text-transform: capitalize; }
.syn-quick-grid a .syn-q-count { font-size: 10px; color: var(--syn-text-dim); }

/* Pagination */
.syn-pagination {
    display: flex; justify-content: center; gap: 4px; margin: 18px 0 8px;
    flex-wrap: wrap;
}
.syn-pagination a, .syn-pagination span {
    min-width: 32px; height: 32px;
    padding: 0 10px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--syn-border); background: #fff;
    color: var(--syn-text); border-radius: 5px;
    text-decoration: none; font-size: 13px;
}
.syn-pagination a:hover { background: var(--syn-accent-soft); color: var(--syn-accent); border-color: var(--syn-accent-soft); }
.syn-pagination .syn-page-active {
    background: var(--syn-accent); color: #fff; border-color: var(--syn-accent); font-weight: 600;
}

/* === RIGHT PANE: Clipboard / Repertorization === */
/* Clipboards bar (multi-patient switcher) */
.syn-cb-bar {
    display: flex; align-items: center; gap: 4px;
    padding: 6px 8px;
    background: linear-gradient(180deg, #fafbfc 0%, #f1f5f9 100%);
    border-bottom: 1px solid var(--syn-border);
    flex-shrink: 0; overflow-x: auto;
    scrollbar-width: thin;
}
.syn-cb-bar::-webkit-scrollbar { height: 4px; }
.syn-cb-bar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }
.syn-cb-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 10px; padding-right: 6px;
    background: #fff; border: 1px solid var(--syn-border);
    border-radius: 14px;
    font-size: 11px; font-weight: 600; color: var(--syn-text);
    cursor: pointer; white-space: nowrap;
    transition: all .12s;
    max-width: 180px;
}
.syn-cb-tab:hover { border-color: var(--syn-accent); color: var(--syn-accent); }
.syn-cb-tab.syn-active {
    background: var(--syn-accent); color: #fff; border-color: var(--syn-accent);
    box-shadow: 0 1px 4px rgba(37,99,235,.25);
}
.syn-cb-tab .syn-cb-name {
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    max-width: 110px;
}
.syn-cb-tab .syn-cb-count {
    background: rgba(0,0,0,.08); color: inherit;
    padding: 0 6px; border-radius: 8px; font-size: 10px;
}
.syn-cb-tab.syn-active .syn-cb-count { background: rgba(255,255,255,.25); }
.syn-cb-tab .syn-cb-menu {
    background: transparent; border: none; color: inherit;
    padding: 0 2px; cursor: pointer; opacity: .55;
    border-radius: 3px; font-size: 10px;
}
.syn-cb-tab .syn-cb-menu:hover { opacity: 1; background: rgba(0,0,0,.08); }
.syn-cb-add {
    flex-shrink: 0;
    width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #fff; border: 1px dashed var(--syn-border);
    border-radius: 50%; cursor: pointer;
    color: var(--syn-accent);
    transition: all .12s;
}
.syn-cb-add:hover { background: var(--syn-accent); color: #fff; border-style: solid; }

.syn-clip-tabs {
    display: flex; padding: 0 8px;
    border-bottom: 1px solid var(--syn-border);
    background: var(--syn-surface-2);
    flex-shrink: 0;
}
.syn-clip-tab {
    padding: 10px 14px;
    background: transparent; border: none;
    border-bottom: 2px solid transparent;
    color: var(--syn-text-muted);
    font-size: 12px; font-weight: 600;
    cursor: pointer; text-transform: uppercase; letter-spacing: .5px;
    transition: color .12s, border-color .12s;
}
.syn-clip-tab:hover { color: var(--syn-accent); }
.syn-clip-tab.syn-active {
    color: var(--syn-accent);
    border-bottom-color: var(--syn-accent);
}
.syn-clip-pane { display: none; flex: 1; overflow-y: auto; padding: 12px; min-height: 0; }
.syn-clip-pane.syn-active { display: block; }

/* Selected rubric chip list */
.syn-clip-rubric {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 8px 10px;
    background: var(--syn-surface-2);
    border: 1px solid var(--syn-border);
    border-radius: 6px;
    margin-bottom: 6px;
    font-size: 12px;
}
.syn-clip-rubric .syn-clip-num {
    flex-shrink: 0;
    width: 22px; height: 22px;
    background: var(--syn-accent); color: #fff;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700;
}
.syn-clip-rubric .syn-clip-text { flex: 1; line-height: 1.4; color: var(--syn-text); }
.syn-clip-rubric .syn-clip-cat {
    text-transform: uppercase; font-size: 9px; font-weight: 700;
    color: var(--syn-accent); letter-spacing: .5px; display: block; margin-bottom: 2px;
}
.syn-clip-remove {
    background: transparent; border: none; color: var(--syn-text-dim);
    cursor: pointer; padding: 2px 4px; border-radius: 3px;
    transition: all .12s;
}
.syn-clip-remove:hover { color: var(--syn-danger); background: #fee2e2; }

/* Repertorization analysis grid (Synthesis-style matrix) */
.syn-grid-wrap { overflow: auto; border: 1px solid var(--syn-border); border-radius: 6px; background: #fff; }
.syn-grid {
    width: 100%; border-collapse: collapse;
    font-size: 12px;
}
.syn-grid th, .syn-grid td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--syn-border);
    border-right: 1px solid var(--syn-border);
    text-align: center;
    white-space: nowrap;
}
.syn-grid thead th {
    background: var(--syn-accent);
    color: #fff;
    font-weight: 600;
    font-size: 11px;
    position: sticky;
    top: 0; z-index: 2;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    height: 88px;
    padding: 6px 4px;
    cursor: help;
}
.syn-grid thead th.syn-grid-rubric-col {
    writing-mode: horizontal-tb;
    transform: none;
    text-align: left;
    height: auto;
    width: 200px;
    position: sticky;
    left: 0; z-index: 3;
    background: var(--syn-accent);
}
.syn-grid tbody td.syn-grid-rubric-cell {
    text-align: left;
    background: var(--syn-surface-2);
    position: sticky; left: 0; z-index: 1;
    max-width: 220px;
    white-space: normal;
    line-height: 1.3;
    font-size: 11px;
    color: var(--syn-text);
    border-right: 2px solid var(--syn-border-strong);
}
.syn-grid tbody td.syn-grid-rubric-cell .syn-grid-rubric-cat {
    display: block;
    font-size: 9px;
    text-transform: uppercase;
    color: var(--syn-accent);
    font-weight: 700;
    letter-spacing: .5px;
}
.syn-grid tbody tr:hover td { background: #fafbfd; }
.syn-grid tbody tr:hover td.syn-grid-rubric-cell { background: #eef2ff; }

.syn-grade {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 11px;
    color: #fff;
    line-height: 1;
}
.syn-grade-1 { background: var(--syn-grade-1); }
.syn-grade-2 { background: var(--syn-grade-2); }
.syn-grade-3 { background: var(--syn-grade-3); }
.syn-grade-4 { background: var(--syn-grade-4); }
.syn-grade-empty { color: var(--syn-text-dim); font-size: 14px; }

.syn-grid tfoot td {
    background: var(--syn-surface-2);
    font-weight: 700;
    color: var(--syn-text);
    border-top: 2px solid var(--syn-border-strong);
    border-bottom: none;
    position: sticky; bottom: 0;
}
.syn-grid tfoot td.syn-grid-rubric-cell { color: var(--syn-accent); }

/* Top-remedies list (alternative analysis view) */
.syn-remedy-list { display: flex; flex-direction: column; gap: 6px; }
.syn-remedy-row {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    background: var(--syn-surface-2);
    border: 1px solid var(--syn-border);
    border-radius: 6px;
}
.syn-remedy-rank {
    width: 26px; height: 26px;
    background: var(--syn-accent); color: #fff;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.syn-remedy-rank.syn-rank-1 { background: linear-gradient(135deg, #f59e0b, #d97706); }
.syn-remedy-rank.syn-rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
.syn-remedy-rank.syn-rank-3 { background: linear-gradient(135deg, #b45309, #78350f); }
.syn-remedy-info { flex: 1; min-width: 0; }
.syn-remedy-name { font-weight: 600; color: var(--syn-text); font-size: 13px; }
.syn-remedy-common { font-size: 11px; color: var(--syn-text-muted); }
.syn-remedy-stats { font-size: 11px; color: var(--syn-text-muted); margin-top: 2px; }
.syn-remedy-score {
    font-weight: 700; color: var(--syn-accent);
    background: var(--syn-accent-soft);
    padding: 4px 10px; border-radius: 6px; font-size: 13px;
    flex-shrink: 0;
}

/* Modal */
.syn-modal {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.55);
    display: none;
    align-items: center; justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(2px);
}
.syn-modal.syn-open { display: flex; }
.syn-modal-content {
    background: #fff;
    border-radius: 10px;
    width: 90%; max-width: 720px;
    max-height: 85vh;
    display: flex; flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,.3);
}
.syn-modal-head {
    padding: 14px 18px;
    background: var(--syn-accent); color: #fff;
    display: flex; align-items: center; justify-content: space-between;
}
.syn-modal-head h3 { margin: 0; font-size: 15px; font-weight: 600; }
.syn-modal-body { padding: 18px; overflow-y: auto; }
.syn-modal-close {
    background: rgba(255,255,255,.2); border: none; color: #fff;
    width: 30px; height: 30px; border-radius: 50%;
    cursor: pointer; font-size: 16px;
}

/* Smart suggestions dropdown */
.syn-suggestions {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: #fff;
    border: 1px solid var(--syn-border-strong);
    border-radius: 6px;
    box-shadow: 0 10px 30px rgba(15,23,42,.15);
    max-height: 380px; overflow-y: auto;
    z-index: 50;
    display: none;
}
.syn-suggestions.syn-open { display: block; }
.syn-suggestion-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid var(--syn-border);
    font-size: 13px;
}
.syn-suggestion-item:hover { background: var(--syn-accent-soft); }
.syn-suggestion-item:last-child { border-bottom: none; }
.syn-suggestion-rubric { font-weight: 600; color: var(--syn-text); }
.syn-suggestion-meta { font-size: 11px; color: var(--syn-text-muted); margin-top: 3px; display: flex; gap: 8px; }

/* AI panel */
.syn-ai-panel { padding: 14px; }
.syn-ai-card {
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border: 1px solid var(--syn-border);
    border-left: 3px solid var(--syn-accent);
    padding: 12px 14px; border-radius: 6px; margin-bottom: 10px;
    font-size: 13px;
}
.syn-ai-card .syn-ai-name { font-weight: 700; color: var(--syn-accent); font-size: 14px; margin-bottom: 6px; }
.syn-ai-card .syn-ai-line { color: var(--syn-text-muted); font-size: 12px; line-height: 1.5; }
.syn-ai-card .syn-ai-line strong { color: var(--syn-text); }

.syn-loading {
    text-align: center; padding: 30px;
    color: var(--syn-text-muted);
}
.syn-loading i { font-size: 28px; color: var(--syn-accent); margin-bottom: 10px; }

/* Mobile toggle visibility */
.syn-mobile-toggle { display: none; }
.syn-mobile-toggle-left { display: none; }
.syn-mobile-toggle-right { display: none; }
@media (max-width: 1024px) {
    .syn-mobile-toggle, .syn-mobile-toggle-right { display: inline-flex; }
}
@media (max-width: 768px) {
    .syn-mobile-toggle-left { display: inline-flex; }
}
.syn-divider { width: 1px; background: var(--syn-border); height: 24px; margin: 0 4px; }

/* Search form responsive */
@media (max-width: 1100px) {
    .syn-cat-select { min-width: 130px; }
    .syn-btn span, .syn-search-form .syn-btn { font-size: 12px; }
}
@media (max-width: 768px) {
    .syn-brand span { display: none; }
    .syn-brand { padding-right: 6px; }
    .syn-search-form {
        order: 10;
        flex: 0 0 100%;
        max-width: none;
        flex-wrap: wrap;
        gap: 6px;
    }
    .syn-search-box { flex: 1 1 100%; min-width: 0; }
    .syn-cat-select { flex: 1 1 50%; min-width: 0; }
    .syn-search-form .syn-btn { flex: 1 1 auto; justify-content: center; }
    .syn-toolbar { padding: 8px; gap: 6px; }
}
@media (max-width: 480px) {
    .syn-search-box input { font-size: 16px; /* prevent iOS zoom */ }
    .syn-pane-header { padding: 8px 10px; font-size: 11px; }
    .syn-pane-body { padding: 6px; }
    .syn-rubric-row { padding: 8px 10px; gap: 8px; }
    .syn-rubric-text { font-size: 13px; }
    .syn-rubric-meta { font-size: 10px; gap: 5px; }
    .syn-tag { font-size: 10px; padding: 1px 6px; }
    .syn-add-btn { width: 28px; height: 28px; }
    .syn-clip-tab { padding: 8px 8px; font-size: 11px; letter-spacing: 0; }
    .syn-clip-pane { padding: 8px; }
    .syn-modal-content { width: 96%; max-height: 92vh; }
    .syn-modal-body { padding: 12px; }
    .syn-quick-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Grid responsive */
@media (max-width: 1024px) {
    .syn-grid thead th { height: 70px; font-size: 10px; }
    .syn-grid thead th.syn-grid-rubric-col { width: 160px; }
    .syn-grid tbody td.syn-grid-rubric-cell { max-width: 180px; font-size: 10px; }
}

/* Hide heavy decorations on small screens */
@media (max-width: 768px) {
    .syn-rubric-group-title { font-size: 10px; padding: 4px 8px; }
}

/* Touch-friendly tap targets */
@media (hover: none) and (pointer: coarse) {
    .syn-add-btn, .syn-btn-icon { min-width: 36px; min-height: 36px; }
    .syn-rubric-checkbox { width: 20px; height: 20px; }
    .syn-clip-remove { padding: 6px 8px; }
}
</style>

<div class="syn-app" id="synApp">

    <!-- ============================ TOOLBAR ============================ -->
    <div class="syn-toolbar">
        <div class="syn-brand">
            <i class="fas fa-book-medical"></i>
            <span>Repertory</span>
        </div>

        <button class="syn-btn syn-btn-ghost syn-btn-icon syn-mobile-toggle-left" type="button" onclick="synToggleMobile('left')" title="Chapters">
            <i class="fas fa-list-tree"></i>
        </button>

        <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" class="syn-search-form" id="synSearchForm">
            <div class="syn-search-box">
                <i class="fas fa-search syn-search-icon"></i>
                <input
                    type="text"
                    name="q"
                    id="synSearchInput"
                    placeholder="Search rubrics — e.g. 'fear of darkness', 'angry when contradicted'…"
                    value="<?php echo htmlspecialchars($searchQuery); ?>"
                    autocomplete="off"
                    autofocus
                >
                <button type="button" class="syn-clear-btn" onclick="synClearSearch()" title="Clear"><i class="fas fa-times"></i></button>
                <div class="syn-suggestions" id="synSuggestions"></div>
            </div>

            <select name="category" id="synCategorySelect" class="syn-cat-select">
                <option value="">All Chapters</option>
                <?php foreach ($categories as $key => $label):
                    $count = $categoryCounts[$key] ?? 0;
                    if ($count > 0): ?>
                    <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?> (<?php echo $count; ?>)
                    </option>
                <?php endif; endforeach; ?>
            </select>

            <button type="submit" class="syn-btn syn-btn-primary" title="Search (Enter)">
                <i class="fas fa-search"></i> Search
            </button>
            <button type="button" class="syn-btn syn-btn-accent" id="synSmartBtn" title="AI-powered natural language search">
                <i class="fas fa-magic"></i> Smart
            </button>

            <?php foreach ($selectedRubrics as $rubricId): ?>
                <input type="hidden" name="rubrics[]" value="<?php echo (int)$rubricId; ?>">
            <?php endforeach; ?>
        </form>

        <div class="syn-toolbar-meta">
            <span><strong><?php echo number_format($totalRubricsInDB); ?></strong> rubrics</span>
            <span><strong><?php echo number_format($totalMappings); ?></strong> mappings</span>
            <span><strong><?php echo count($selectedRubrics); ?></strong> selected</span>
        </div>

        <button class="syn-btn syn-btn-ghost syn-btn-icon syn-mobile-toggle-right" type="button" onclick="synToggleMobile('right')" title="Clipboard">
            <i class="fas fa-clipboard-list"></i>
        </button>
    </div>

    <!-- ============================ WORKSPACE ============================ -->
    <div class="syn-workspace">

        <!-- Backdrop for mobile drawers -->
        <div class="syn-backdrop" id="synBackdrop" onclick="synCloseDrawers()"></div>

        <!-- ===== LEFT PANE: Chapter tree ===== -->
        <aside class="syn-pane syn-pane-left" id="synLeftPane">
            <div class="syn-pane-header">
                <span><i class="fas fa-list-tree"></i> Chapters</span>
                <span><?php echo count(array_filter($categoryCounts, fn($c)=>$c>0)); ?></span>
            </div>
            <div class="syn-pane-body">
                <a href="?<?php echo http_build_query(array_filter(['q'=>$searchQuery])); ?><?php foreach($selectedRubrics as $r) echo '&rubrics[]='.(int)$r; ?>"
                   class="syn-chapter-list-all<?php echo empty($category) ? ' syn-active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> All Chapters
                </a>
                <ul class="syn-chapter-list">
                    <?php
                    // Use ordered category list, preserving Synthesis chapter order
                    $orderedCats = array_keys($categories);
                    // Append any DB categories not in the canonical list
                    foreach (array_keys($categoryCounts) as $dbcat) {
                        if (!in_array($dbcat, $orderedCats)) $orderedCats[] = $dbcat;
                    }
                    $activeCatLower = strtolower($category);
                    foreach ($orderedCats as $cat):
                        $count = $categoryCounts[$cat] ?? 0;
                        if ($count <= 0) continue;
                        $label = $categories[$cat] ?? ucfirst($cat);
                        $isActive = $activeCatLower === $cat;
                        $href = '?' . http_build_query(array_filter(['q'=>$searchQuery, 'category'=>$cat]));
                        foreach ($selectedRubrics as $r) $href .= '&rubrics[]=' . (int)$r;
                    ?>
                    <li>
                        <a href="<?php echo $href; ?>" class="<?php echo $isActive ? 'syn-active' : ''; ?>">
                            <i class="fas <?php echo syn_cat_icon($cat); ?> syn-cat-icon"></i>
                            <span class="syn-cat-name"><?php echo htmlspecialchars($label); ?></span>
                            <span class="syn-cat-count"><?php echo number_format($count); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="syn-pane-footer">
                <i class="fas fa-info-circle"></i> Click a chapter to browse its rubrics.
            </div>
        </aside>

        <!-- ===== CENTER PANE: Rubric search results ===== -->
        <main class="syn-pane">
            <div class="syn-results-toolbar">
                <span class="syn-results-title">
                    <?php if (!empty($searchQuery)): ?>
                        Search: <em><?php echo htmlspecialchars($searchQuery); ?></em>
                    <?php elseif (!empty($category)): ?>
                        <?php echo htmlspecialchars($categories[$category] ?? ucfirst($category)); ?>
                    <?php else: ?>
                        Welcome
                    <?php endif; ?>
                </span>
                <?php if (!empty($searchQuery) || !empty($category)): ?>
                    <span class="syn-results-meta">
                        <?php echo number_format($totalRubrics); ?> rubric<?php echo $totalRubrics != 1 ? 's' : ''; ?> found
                    </span>
                <?php endif; ?>

                <div class="syn-results-actions">
                    <?php if ($searchQuery || $category): ?>
                        <a href="<?php echo APP_URL; ?>/repertory/search.php<?php
                            if (!empty($selectedRubrics)) {
                                echo '?' . http_build_query(['rubrics' => $selectedRubrics]);
                            }
                        ?>" class="syn-btn syn-btn-sm syn-btn-ghost" title="Clear search">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="syn-pane-body">
                <?php if (empty($searchQuery) && empty($category)): ?>
                    <!-- Welcome state -->
                    <div class="syn-empty">
                        <i class="fas fa-book-medical"></i>
                        <h3>Search the Repertory</h3>
                        <p>Type a symptom above or pick a chapter from the left to begin.</p>

                        <div class="syn-quick-grid">
                            <?php foreach (array_slice($categoryCounts, 0, 12) as $cat => $count):
                                if ($count <= 0) continue; ?>
                            <a href="?category=<?php echo urlencode($cat); ?><?php foreach($selectedRubrics as $r) echo '&rubrics[]='.(int)$r; ?>">
                                <i class="fas <?php echo syn_cat_icon($cat); ?>"></i>
                                <span class="syn-q-name"><?php echo ucfirst($cat); ?></span>
                                <span class="syn-q-count"><?php echo number_format($count); ?> rubrics</span>
                            </a>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top:30px; font-size:12px; color:var(--syn-text-dim);">
                            <i class="fas fa-keyboard"></i>
                            Press <kbd style="padding:2px 6px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;font-family:monospace;">/</kbd>
                            to focus search, <kbd style="padding:2px 6px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;font-family:monospace;">Esc</kbd> to clear
                        </div>
                    </div>

                <?php elseif (empty($rubrics)): ?>
                    <?php if (!empty($stopWordsOnlyQuery)): ?>
                        <div class="syn-empty">
                            <i class="fas fa-exclamation-circle" style="color:var(--syn-warning);"></i>
                            <h3>Query too generic</h3>
                            <p>Every word in your query was filtered as a stop-word.</p>
                            <p style="margin-top:6px;">Include a symptom word — e.g. <strong>fear</strong>, <strong>headache</strong>, <strong>burning</strong>.</p>
                        </div>
                    <?php else: ?>
                        <div class="syn-empty">
                            <i class="fas fa-search"></i>
                            <h3>No rubrics found</h3>
                            <p>Try different keywords, broaden the chapter, or use Smart Search.</p>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($browseMode): ?>
                        <!-- ====================================================
                             SYNTHESIS-STYLE ALPHABETICAL BROWSE
                             Shows ALL rubrics in the chapter A-Z with remedy
                             counts and an A-Z jump strip at the top.
                             ==================================================== -->
                        <div class="syn-az-bar" id="synAzBar">
                            <?php foreach (range('A', 'Z') as $L):
                                $hasIt = isset($browseGroups[$L]); ?>
                                <a href="#syn-az-<?php echo $L; ?>"
                                   class="<?php echo $hasIt ? '' : 'syn-az-disabled'; ?>"
                                   <?php if ($hasIt): ?>onclick="event.preventDefault(); document.getElementById('syn-az-<?php echo $L; ?>')?.scrollIntoView({behavior:'smooth', block:'start'});"<?php endif; ?>>
                                    <?php echo $L; ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (isset($browseGroups['#'])): ?>
                                <a href="#syn-az-num" onclick="event.preventDefault(); document.getElementById('syn-az-num')?.scrollIntoView({behavior:'smooth', block:'start'});">#</a>
                            <?php endif; ?>
                            <span class="syn-az-meta"><?php echo number_format($totalRubrics); ?> rubrics</span>
                        </div>

                        <?php foreach ($browseLetters as $letter):
                            $anchorId = $letter === '#' ? 'syn-az-num' : 'syn-az-' . $letter;
                        ?>
                        <section class="syn-browse-letter" id="<?php echo $anchorId; ?>">
                            <h3><?php echo htmlspecialchars($letter); ?></h3>
                            <div class="syn-browse-list">
                            <?php foreach ($browseGroups[$letter] as $rubric):
                                $isSelected = in_array($rubric['id'], $selectedRubrics);
                                $count = (int)$rubric['remedy_count'];
                                $rubricJson = htmlspecialchars(json_encode($rubric['rubric']), ENT_QUOTES);
                            ?>
                                <div class="syn-browse-row <?php echo $isSelected ? 'syn-selected' : ''; ?> <?php echo $count === 0 ? 'syn-zero' : ''; ?>"
                                     id="syn-rubric-<?php echo (int)$rubric['id']; ?>"
                                     onclick="synShowRemedies(<?php echo (int)$rubric['id']; ?>, <?php echo $rubricJson; ?>)"
                                    title="<?php echo htmlspecialchars($rubric['rubric']); ?> &mdash; <?php echo $count; ?> remedies">
                                    <span class="syn-bw-main">
                                        <span class="syn-bw-name"><?php echo htmlspecialchars($rubric['rubric']); ?></span>
                                        <?php if (isKentMindRubrics1To10VerifiedSource($rubric)): ?>
                                        <span class="syn-bw-kent" title="Verified from <?php echo htmlspecialchars($rubric['verified_source'] ?? 'Homeoint Kent Repertory'); ?>">
                                            <i class="fas fa-check-circle"></i> Kent
                                        </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="syn-bw-tools">
                                        <span class="syn-bw-count"><?php echo $count; ?></span>
                                        <button type="button" class="syn-bw-add"
                                                onclick="event.stopPropagation(); synToggleRubric(<?php echo (int)$rubric['id']; ?>)"
                                                title="<?php echo $isSelected ? 'Remove from clipboard' : 'Add to clipboard'; ?>">
                                            <i class="fas <?php echo $isSelected ? 'fa-times' : 'fa-plus'; ?>"></i>
                                        </button>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endforeach; ?>

                    <?php else: ?>
                    <?php
                    $currentCategory = '';
                    foreach ($rubrics as $rubric):
                        if ($currentCategory !== $rubric['category']):
                            if ($currentCategory !== '') echo '</div>';
                            $currentCategory = $rubric['category'];
                    ?>
                        <div class="syn-rubric-group">
                            <div class="syn-rubric-group-title">
                                <i class="fas <?php echo syn_cat_icon(strtolower($currentCategory)); ?>"></i>
                                <?php echo htmlspecialchars(strtoupper($currentCategory)); ?>
                            </div>
                    <?php endif;

                        $isSelected = in_array($rubric['id'], $selectedRubrics);
                        // Highlight matched terms
                        $highlightedText = htmlspecialchars($rubric['rubric']);
                        if (!empty($searchQuery)) {
                            $words = preg_split('/[\s,\-]+/', mb_strtolower(trim($searchQuery)));
                            foreach ($words as $w) {
                                $w = trim($w);
                                if (strlen($w) >= 3 && !in_array($w, $stopWords)) {
                                    $highlightedText = preg_replace(
                                        '/(' . preg_quote($w, '/') . ')/i',
                                        '<mark>$1</mark>',
                                        $highlightedText
                                    );
                                }
                            }
                        }
                    ?>
                    <div class="syn-rubric-row <?php echo $isSelected ? 'syn-selected' : ''; ?>" id="syn-rubric-<?php echo (int)$rubric['id']; ?>">
                        <input
                            type="checkbox"
                            class="syn-rubric-checkbox"
                            <?php echo $isSelected ? 'checked' : ''; ?>
                            onchange="synToggleRubric(<?php echo (int)$rubric['id']; ?>)"
                            title="Add to clipboard"
                        >
                        <div class="syn-rubric-main">
                            <div class="syn-rubric-text" onclick="synShowRemedies(<?php echo (int)$rubric['id']; ?>, <?php echo htmlspecialchars(json_encode($rubric['rubric']), ENT_QUOTES); ?>)">
                                <?php echo $highlightedText; ?>
                            </div>
                            <?php if (!empty($rubric['sub_category'])): ?>
                                <div class="syn-rubric-sub"><?php echo htmlspecialchars($rubric['sub_category']); ?></div>
                            <?php endif; ?>
                            <div class="syn-rubric-meta">
                                <span class="syn-tag syn-tag-cat"><?php echo htmlspecialchars($rubric['category']); ?></span>
                                <span class="syn-tag syn-tag-remedy">
                                    <i class="fas fa-capsules"></i> <?php echo (int)$rubric['remedy_count']; ?> remedies
                                </span>
                                <?php if (isKentMindRubrics1To10VerifiedSource($rubric)): ?>
                                <span class="syn-tag syn-tag-verified" title="Verified from <?php echo htmlspecialchars($rubric['verified_source'] ?? 'Kent'); ?>">
                                    <i class="fas fa-check-circle"></i> Kent
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($rubric['repertory_source'])): ?>
                                <span class="syn-tag syn-tag-source">
                                    <i class="fas fa-book"></i> <?php echo htmlspecialchars($rubric['repertory_source']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="syn-rubric-actions">
                            <button type="button" class="syn-add-btn" onclick="synShowRemedies(<?php echo (int)$rubric['id']; ?>, <?php echo htmlspecialchars(json_encode($rubric['rubric']), ENT_QUOTES); ?>)" title="View remedies">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="syn-add-btn" onclick="synToggleRubric(<?php echo (int)$rubric['id']; ?>)" title="<?php echo $isSelected ? 'Remove from clipboard' : 'Add to clipboard'; ?>">
                                <i class="fas <?php echo $isSelected ? 'fa-minus' : 'fa-plus'; ?>"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach;
                    if ($currentCategory !== '') echo '</div>';
                    ?>

                    <?php if ($totalRubricPages > 1): ?>
                    <div class="syn-pagination">
                        <?php
                        $pageBase = ['q'=>$searchQuery, 'category'=>$category];
                        foreach ($selectedRubrics as $r) { $pageBase['rubrics'][] = $r; }

                        $mkLink = function($pg) use ($pageBase) {
                            $q = $pageBase; $q['rubric_page'] = $pg;
                            return '?' . http_build_query($q);
                        };

                        if ($rubricPage > 1): ?>
                            <a href="<?php echo $mkLink(1); ?>" title="First"><i class="fas fa-angle-double-left"></i></a>
                            <a href="<?php echo $mkLink($rubricPage - 1); ?>" title="Previous"><i class="fas fa-angle-left"></i></a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $rubricPage - 2);
                        $endPage = min($totalRubricPages, $rubricPage + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="<?php echo $mkLink($i); ?>" class="<?php echo $i == $rubricPage ? 'syn-page-active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($rubricPage < $totalRubricPages): ?>
                            <a href="<?php echo $mkLink($rubricPage + 1); ?>" title="Next"><i class="fas fa-angle-right"></i></a>
                            <a href="<?php echo $mkLink($totalRubricPages); ?>" title="Last"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <span style="display:block;text-align:center;font-size:11px;color:var(--syn-text-dim);">
                        Page <?php echo $rubricPage; ?> of <?php echo $totalRubricPages; ?>
                    </span>
                    <?php endif; ?>
                    <?php endif; /* /search-results branch */ ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- ===== RIGHT PANE: Clipboard / Repertorization ===== -->
        <aside class="syn-pane syn-pane-right" id="synRightPane">
            <!-- Clipboards bar (multi-patient switcher) -->
            <div class="syn-cb-bar" id="synCbBar" title="Clipboards — switch between patients">
                <!-- Populated by JS -->
                <button type="button" class="syn-cb-add" id="synCbAdd" onclick="synCB.create()" title="New clipboard">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="syn-clip-tabs">
                <button class="syn-clip-tab syn-active" data-tab="clipboard" onclick="synSwitchTab('clipboard')">
                    <i class="fas fa-clipboard-list"></i> Clipboard <span style="background:var(--syn-accent);color:#fff;padding:1px 7px;border-radius:10px;font-size:10px;margin-left:4px;"><?php echo count($selectedRubrics); ?></span>
                </button>
                <button class="syn-clip-tab" data-tab="analysis" onclick="synSwitchTab('analysis')">
                    <i class="fas fa-table-cells"></i> Analysis
                </button>
                <button class="syn-clip-tab" data-tab="remedies" onclick="synSwitchTab('remedies')">
                    <i class="fas fa-capsules"></i> Top
                </button>
                <button class="syn-clip-tab" data-tab="ai" onclick="synSwitchTab('ai'); synLoadAIRepertory();">
                    <i class="fas fa-robot"></i> AI
                </button>
            </div>

            <!-- CLIPBOARD TAB -->
            <div class="syn-clip-pane syn-active" id="synClipPane-clipboard">
                <?php if (empty($selectedRubrics)): ?>
                    <div class="syn-empty" style="padding:30px 16px;">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No rubrics selected</h3>
                        <p>Add rubrics from the search results to start a repertorization.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; gap:6px; margin-bottom:10px;">
                        <button class="syn-btn syn-btn-sm syn-btn-ghost" onclick="synClearAll()" title="Remove all rubrics">
                            <i class="fas fa-trash"></i> Clear all
                        </button>
                        <button class="syn-btn syn-btn-sm" onclick="synExportCSV()" title="Export to CSV">
                            <i class="fas fa-file-csv"></i> Export
                        </button>
                    </div>
                    <?php foreach ($selectedRubricDetails as $idx => $rubric): ?>
                    <div class="syn-clip-rubric">
                        <span class="syn-clip-num"><?php echo $idx + 1; ?></span>
                        <div class="syn-clip-text">
                            <span class="syn-clip-cat"><?php echo htmlspecialchars($rubric['category']); ?></span>
                            <?php echo htmlspecialchars($rubric['rubric']); ?>
                            <?php if (isKentMindRubrics1To10VerifiedSource($rubric)): ?>
                                <span class="syn-clip-kent" title="Verified from <?php echo htmlspecialchars($rubric['verified_source'] ?? 'Homeoint Kent Repertory'); ?>">
                                    <i class="fas fa-check-circle"></i> Kent
                                </span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="syn-clip-remove" onclick="synToggleRubric(<?php echo (int)$rubric['id']; ?>)" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ANALYSIS / GRID TAB -->
            <div class="syn-clip-pane" id="synClipPane-analysis">
                <?php if (empty($repertorization)): ?>
                    <div class="syn-empty" style="padding:30px 16px;">
                        <i class="fas fa-table-cells"></i>
                        <h3>No analysis yet</h3>
                        <p>Add at least 2 rubrics to see the matrix.</p>
                    </div>
                <?php else: ?>
                    <div style="font-size:11px; color:var(--syn-text-muted); margin-bottom:8px;">
                        <strong><?php echo count($topRemedies); ?></strong> top remedies × <strong><?php echo count($selectedRubricDetails); ?></strong> rubrics &middot;
                        Grade dot: <span class="syn-grade syn-grade-1" style="width:14px;height:14px;font-size:9px;">1</span>
                        <span class="syn-grade syn-grade-2" style="width:14px;height:14px;font-size:9px;">2</span>
                        <span class="syn-grade syn-grade-3" style="width:14px;height:14px;font-size:9px;">3</span>
                        <span class="syn-grade syn-grade-4" style="width:14px;height:14px;font-size:9px;">4</span>
                    </div>
                    <div class="syn-grid-wrap">
                        <table class="syn-grid">
                            <thead>
                                <tr>
                                    <th class="syn-grid-rubric-col">Rubric</th>
                                    <?php foreach ($topRemedies as $rem): ?>
                                        <th title="<?php echo htmlspecialchars($rem['name'] . ' — total ' . $rem['total_score']); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($rem['name'], 0, 16, '…')); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedRubricDetails as $idx => $rubric): ?>
                                <tr>
                                    <td class="syn-grid-rubric-cell" title="<?php echo htmlspecialchars($rubric['rubric']); ?>">
                                        <span class="syn-grid-rubric-cat"><?php echo htmlspecialchars($rubric['category']); ?></span>
                                        <?php echo htmlspecialchars(mb_strimwidth($rubric['rubric'], 0, 60, '…')); ?>
                                    </td>
                                    <?php foreach ($topRemedies as $rem):
                                        $g = $rubricRemedyGrid[(int)$rubric['id']][(int)$rem['id']] ?? null; ?>
                                        <td>
                                            <?php if ($g): ?>
                                                <span class="syn-grade syn-grade-<?php echo $g; ?>" title="Grade <?php echo $g; ?>"><?php echo $g; ?></span>
                                            <?php else: ?>
                                                <span class="syn-grade-empty">·</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="syn-grid-rubric-cell">TOTAL</td>
                                    <?php foreach ($topRemedies as $rem): ?>
                                        <td><?php echo $rem['total_score']; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="syn-grid-rubric-cell">Coverage</td>
                                    <?php foreach ($topRemedies as $rem): ?>
                                        <td><?php echo $rem['rubric_count']; ?>/<?php echo count($selectedRubrics); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TOP REMEDIES TAB -->
            <div class="syn-clip-pane" id="synClipPane-remedies">
                <?php if (empty($repertorization)): ?>
                    <div class="syn-empty" style="padding:30px 16px;">
                        <i class="fas fa-capsules"></i>
                        <h3>No remedies yet</h3>
                        <p>Add rubrics to view top remedy matches.</p>
                    </div>
                <?php else: ?>
                    <div class="syn-remedy-list">
                        <?php foreach ($repertorization as $idx => $rem):
                            if ($idx >= 30) break; ?>
                        <div class="syn-remedy-row">
                            <span class="syn-remedy-rank syn-rank-<?php echo min($idx + 1, 3); ?>"><?php echo $idx + 1; ?></span>
                            <div class="syn-remedy-info">
                                <div class="syn-remedy-name"><?php echo htmlspecialchars($rem['name']); ?></div>
                                <?php if (!empty($rem['common_name'])): ?>
                                    <div class="syn-remedy-common"><?php echo htmlspecialchars($rem['common_name']); ?></div>
                                <?php endif; ?>
                                <div class="syn-remedy-stats">
                                    <?php echo $rem['rubric_count']; ?>/<?php echo count($selectedRubrics); ?> rubrics &middot; <?php echo $rem['coverage']; ?>%
                                </div>
                            </div>
                            <span class="syn-remedy-score" title="Total grade score"><?php echo $rem['total_score']; ?></span>
                            <a href="<?php echo APP_URL; ?>/materia-medica/view.php?id=<?php echo (int)$rem['id']; ?>"
                               class="syn-btn syn-btn-sm syn-btn-icon" target="_blank" title="Materia Medica">
                                <i class="fas fa-book"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- AI TAB -->
            <div class="syn-clip-pane" id="synClipPane-ai">
                <?php if (empty($selectedRubrics)): ?>
                    <div class="syn-empty" style="padding:30px 16px;">
                        <i class="fas fa-robot"></i>
                        <h3>AI repertorisation</h3>
                        <p>Add rubrics, then open this tab to run AI analysis.</p>
                    </div>
                <?php else: ?>
                    <div class="syn-ai-panel" id="synAIPanel">
                        <button class="syn-btn syn-btn-accent" onclick="synLoadAIRepertory(true)">
                            <i class="fas fa-robot"></i> Run AI Analysis
                        </button>
                        <p style="font-size:11px; color:var(--syn-text-muted); margin-top:8px;">
                            Uses your selected rubrics to query the AI suggestion engine.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<!-- ============================ MODALS ============================ -->
<div class="syn-modal" id="synModal">
    <div class="syn-modal-content">
        <div class="syn-modal-head">
            <h3 id="synModalTitle">Remedies</h3>
            <button class="syn-modal-close" onclick="synCloseModal()" title="Close (Esc)">&times;</button>
        </div>
        <div class="syn-modal-body" id="synModalBody"></div>
    </div>
</div>

<div class="syn-modal" id="synSmartModal">
    <div class="syn-modal-content">
        <div class="syn-modal-head">
            <h3><i class="fas fa-magic"></i> Smart Search Results</h3>
            <button class="syn-modal-close" onclick="synCloseSmart()" title="Close (Esc)">&times;</button>
        </div>
        <div class="syn-modal-body" id="synSmartBody"></div>
    </div>
</div>

<script>
const APP_URL = '<?php echo APP_URL; ?>';

/* ====================== Multi-Clipboard Manager ======================
 * Clipboards live in localStorage under 'syn_clipboards'. Each clipboard:
 *   { id: string, name: string, rubrics: number[], updatedAt: number }
 * The currently active clipboard's `rubrics` are mirrored into the page's
 * form (hidden inputs name="rubrics[]"). Switching clipboards reloads the
 * page with that clipboard's rubrics in the URL.
 * Active clipboard id is stored in localStorage 'syn_active_cb'.
 */
const synCB = (function() {
    const STORE_KEY = 'syn_clipboards';
    const ACTIVE_KEY = 'syn_active_cb';

    function load() {
        try {
            const raw = localStorage.getItem(STORE_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function save(list) {
        try { localStorage.setItem(STORE_KEY, JSON.stringify(list)); } catch (e) {}
    }
    function getActiveId() { return localStorage.getItem(ACTIVE_KEY) || ''; }
    function setActiveId(id) { localStorage.setItem(ACTIVE_KEY, id); }
    function uid() { return 'cb_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }

    function ensureAtLeastOne(currentRubrics) {
        let list = load();
        if (list.length === 0) {
            const cb = { id: uid(), name: 'Clipboard 1', rubrics: currentRubrics.slice(), updatedAt: Date.now() };
            list = [cb];
            save(list);
            setActiveId(cb.id);
        }
        if (!list.find(c => c.id === getActiveId())) setActiveId(list[0].id);
        return list;
    }

    function getCurrentRubricsFromForm() {
        return Array.from(document.querySelectorAll('input[name="rubrics[]"]'))
            .map(i => parseInt(i.value, 10)).filter(n => !isNaN(n));
    }

    function syncCurrent() {
        // Persist current page's rubrics into the active clipboard
        const list = load();
        const id = getActiveId();
        const cb = list.find(c => c.id === id);
        if (!cb) return;
        cb.rubrics = getCurrentRubricsFromForm();
        cb.updatedAt = Date.now();
        save(list);
    }

    function navigateWithRubrics(rubrics) {
        const url = new URL(window.location.href);
        url.searchParams.delete('rubrics[]');
        url.searchParams.delete('rubrics');
        // http_build_query in PHP receives rubrics[]=ID
        rubrics.forEach(r => url.searchParams.append('rubrics[]', r));
        window.location.href = url.toString();
    }

    function render() {
        const bar = document.getElementById('synCbBar');
        if (!bar) return;
        const list = load();
        const activeId = getActiveId();
        // Clear existing tabs (keep the + button at end)
        bar.querySelectorAll('.syn-cb-tab').forEach(el => el.remove());
        const addBtn = document.getElementById('synCbAdd');
        list.forEach(cb => {
            const tab = document.createElement('div');
            tab.className = 'syn-cb-tab' + (cb.id === activeId ? ' syn-active' : '');
            tab.title = cb.name + ' \u2022 ' + cb.rubrics.length + ' rubric(s)';
            tab.innerHTML =
                '<span class="syn-cb-name"></span>' +
                '<span class="syn-cb-count">' + cb.rubrics.length + '</span>' +
                '<button type="button" class="syn-cb-menu" title="Options"><i class="fas fa-ellipsis-v"></i></button>';
            tab.querySelector('.syn-cb-name').textContent = cb.name;
            tab.addEventListener('click', e => {
                if (e.target.closest('.syn-cb-menu')) return;
                if (cb.id === activeId) return; // already active
                synCB.switchTo(cb.id);
            });
            tab.querySelector('.syn-cb-menu').addEventListener('click', e => {
                e.stopPropagation();
                synCB.menu(cb.id, e.currentTarget);
            });
            bar.insertBefore(tab, addBtn);
        });
    }

    return {
        init: function() {
            const current = getCurrentRubricsFromForm();
            ensureAtLeastOne(current);
            // Mirror current URL rubrics into the active clipboard
            syncCurrent();
            render();
        },
        create: function() {
            const list = load();
            const defaultName = 'Clipboard ' + (list.length + 1);
            const name = (prompt('Name for the new clipboard (e.g. patient name):', defaultName) || '').trim();
            if (name === null) return;
            const cb = { id: uid(), name: name || defaultName, rubrics: [], updatedAt: Date.now() };
            list.push(cb);
            save(list);
            setActiveId(cb.id);
            navigateWithRubrics([]);
        },
        switchTo: function(id) {
            // Save current first
            syncCurrent();
            const list = load();
            const cb = list.find(c => c.id === id);
            if (!cb) return;
            setActiveId(id);
            navigateWithRubrics(cb.rubrics);
        },
        rename: function(id) {
            const list = load();
            const cb = list.find(c => c.id === id);
            if (!cb) return;
            const name = (prompt('Rename clipboard:', cb.name) || '').trim();
            if (!name) return;
            cb.name = name; cb.updatedAt = Date.now();
            save(list); render();
        },
        remove: function(id) {
            let list = load();
            const cb = list.find(c => c.id === id);
            if (!cb) return;
            if (!confirm('Delete clipboard "' + cb.name + '"? This cannot be undone.')) return;
            list = list.filter(c => c.id !== id);
            save(list);
            if (getActiveId() === id) {
                if (list.length) {
                    setActiveId(list[0].id);
                    navigateWithRubrics(list[0].rubrics);
                } else {
                    localStorage.removeItem(ACTIVE_KEY);
                    navigateWithRubrics([]);
                }
            } else {
                render();
            }
        },
        clearActive: function() {
            const list = load();
            const cb = list.find(c => c.id === getActiveId());
            if (!cb) return;
            if (!confirm('Remove all rubrics from "' + cb.name + '"?')) return;
            cb.rubrics = []; cb.updatedAt = Date.now();
            save(list);
            navigateWithRubrics([]);
        },
        menu: function(id, anchor) {
            // Simple menu: rename / delete via prompts (avoids extra DOM)
            const choice = prompt('Type "r" to rename or "d" to delete this clipboard:', 'r');
            if (choice === null) return;
            const c = choice.trim().toLowerCase();
            if (c === 'r') synCB.rename(id);
            else if (c === 'd') synCB.remove(id);
        },
        syncCurrent: syncCurrent
    };
})();
document.addEventListener('DOMContentLoaded', () => synCB.init());

/* ====================== Form / clipboard handling ====================== */
function synToggleRubric(rubricId) {
    const form = document.getElementById('synSearchForm');
    const inputs = form.querySelectorAll('input[name="rubrics[]"]');
    let removed = false;
    inputs.forEach(i => { if (parseInt(i.value) === rubricId) { i.remove(); removed = true; } });
    if (!removed) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'rubrics[]'; inp.value = rubricId;
        form.appendChild(inp);
    }
    // Persist into the active clipboard before navigating
    synCB.syncCurrent();
    form.submit();
}
function synClearAll() {
    // Delegates to clipboard manager so the active clipboard is also cleared
    synCB.clearActive();
}
function synClearSearch() {
    document.getElementById('synSearchInput').value = '';
    document.getElementById('synSearchInput').focus();
}

/* ====================== Right pane tabs ====================== */
function synSwitchTab(tab) {
    document.querySelectorAll('.syn-clip-tab').forEach(b => b.classList.toggle('syn-active', b.dataset.tab === tab));
    document.querySelectorAll('.syn-clip-pane').forEach(p => p.classList.toggle('syn-active', p.id === 'synClipPane-' + tab));
}

/* ====================== Mobile drawer toggle ====================== */
function synToggleMobile(side) {
    const id = side === 'left' ? 'synLeftPane' : 'synRightPane';
    const pane = document.getElementById(id);
    const backdrop = document.getElementById('synBackdrop');
    const isOpen = pane.classList.toggle('syn-mobile-open');
    // Close the other drawer if opening one
    if (isOpen) {
        const otherId = side === 'left' ? 'synRightPane' : 'synLeftPane';
        document.getElementById(otherId)?.classList.remove('syn-mobile-open');
    }
    backdrop.classList.toggle('syn-show',
        document.getElementById('synLeftPane').classList.contains('syn-mobile-open') ||
        document.getElementById('synRightPane').classList.contains('syn-mobile-open')
    );
}
function synCloseDrawers() {
    document.getElementById('synLeftPane')?.classList.remove('syn-mobile-open');
    document.getElementById('synRightPane')?.classList.remove('syn-mobile-open');
    document.getElementById('synBackdrop')?.classList.remove('syn-show');
}
/* Close drawers when viewport grows back to desktop */
let synLastWidth = window.innerWidth;
window.addEventListener('resize', () => {
    if (window.innerWidth > 1024 && synLastWidth <= 1024) synCloseDrawers();
    synLastWidth = window.innerWidth;
});

/* ====================== Rubric remedies modal ====================== */
function synShowRemedies(rubricId, rubricName) {
    const modal = document.getElementById('synModal');
    document.getElementById('synModalTitle').textContent = rubricName;
    document.getElementById('synModalBody').innerHTML =
        '<div class="syn-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading remedies…</p></div>';
    modal.classList.add('syn-open');

    fetch(APP_URL + '/api/get_rubric_remedies.php?rubric_id=' + rubricId)
        .then(r => r.json())
        .then(data => {
            if (data.success && Array.isArray(data.remedies)) {
                if (!data.remedies.length) {
                    document.getElementById('synModalBody').innerHTML =
                        '<div class="syn-empty"><i class="fas fa-flask"></i><p>No remedies mapped yet.</p></div>';
                    return;
                }
                let html = '<div class="syn-remedy-list">';
                data.remedies.forEach((r, idx) => {
                    html += `<div class="syn-remedy-row">
                        <span class="syn-remedy-rank syn-rank-${Math.min(idx+1,3)}">${idx+1}</span>
                        <div class="syn-remedy-info">
                            <div class="syn-remedy-name">${synEscape(r.remedy_name)}</div>
                            ${r.common_name ? `<div class="syn-remedy-common">${synEscape(r.common_name)}</div>` : ''}
                        </div>
                        <span class="syn-grade syn-grade-${r.grade}" title="Grade ${r.grade}">${r.grade}</span>
                    </div>`;
                });
                html += '</div>';
                document.getElementById('synModalBody').innerHTML = html;
            } else {
                document.getElementById('synModalBody').innerHTML =
                    '<div class="syn-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to load.</p></div>';
            }
        })
        .catch(err => {
            document.getElementById('synModalBody').innerHTML =
                '<div class="syn-empty"><i class="fas fa-exclamation-triangle"></i><p>' + synEscape(err.message) + '</p></div>';
        });
}
function synCloseModal() { document.getElementById('synModal').classList.remove('syn-open'); }

/* ====================== Smart Search ====================== */
document.getElementById('synSmartBtn').addEventListener('click', synRunSmart);

function synRunSmart() {
    const symptom = document.getElementById('synSearchInput').value.trim();
    const cat = document.getElementById('synCategorySelect').value;
    if (!symptom) {
        alert('Please type a symptom first.');
        document.getElementById('synSearchInput').focus();
        return;
    }
    const modal = document.getElementById('synSmartModal');
    document.getElementById('synSmartBody').innerHTML =
        '<div class="syn-loading"><i class="fas fa-brain fa-spin"></i><p>Analysing your symptom…</p></div>';
    modal.classList.add('syn-open');

    const fd = new FormData();
    fd.append('symptom', symptom);
    if (cat) fd.append('category', cat);

    fetch(APP_URL + '/api/get_rubric_suggestions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.rubrics && data.rubrics.length) {
                let html = `<div style="background:var(--syn-accent-soft); padding:10px 12px; border-radius:6px; margin-bottom:12px; font-size:12px;">
                    <strong>${data.rubrics.length} matches</strong> for "<em>${synEscape(data.symptom)}</em>"
                    ${data.ai_enhanced ? '<span class="syn-tag" style="margin-left:6px; background:#fff;">✨ AI</span>' : ''}
                </div>`;
                data.rubrics.forEach(rb => {
                    const aiBadge = rb.source === 'ai' ? '<span class="syn-tag syn-tag-source"><i class="fas fa-robot"></i> AI</span>' : '';
                    html += `<div class="syn-rubric-row" style="margin-bottom:6px;">
                        <div class="syn-rubric-main">
                            <div class="syn-rubric-text">${synEscape(rb.rubric)}</div>
                            ${rb.complete_rubric ? `<div class="syn-rubric-sub">${synEscape(rb.complete_rubric)}</div>` : ''}
                            <div class="syn-rubric-meta">
                                <span class="syn-tag syn-tag-cat">${synEscape(rb.category)}</span>
                                <span class="syn-tag syn-tag-remedy"><i class="fas fa-capsules"></i> ${rb.remedy_count || 0}</span>
                                ${aiBadge}
                            </div>
                        </div>
                        <button class="syn-add-btn" onclick="synAddFromSmart(${rb.id})" title="Add"><i class="fas fa-plus"></i></button>
                    </div>`;
                });
                document.getElementById('synSmartBody').innerHTML = html;
            } else {
                document.getElementById('synSmartBody').innerHTML =
                    '<div class="syn-empty"><i class="fas fa-search"></i><h3>No matches</h3><p>Try simpler keywords.</p></div>';
            }
        })
        .catch(err => {
            document.getElementById('synSmartBody').innerHTML =
                '<div class="syn-empty"><i class="fas fa-exclamation-triangle"></i><p>' + synEscape(err.message) + '</p></div>';
        });
}
function synCloseSmart() { document.getElementById('synSmartModal').classList.remove('syn-open'); }
function synAddFromSmart(id) {
    const form = document.getElementById('synSearchForm');
    const exists = Array.from(form.querySelectorAll('input[name="rubrics[]"]')).some(i => parseInt(i.value) === id);
    if (exists) { alert('Already in clipboard.'); return; }
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'rubrics[]'; inp.value = id;
    form.appendChild(inp);
    form.submit();
}

/* ====================== AI Repertory ====================== */
let synAILoaded = false;
function synLoadAIRepertory(force) {
    const panel = document.getElementById('synAIPanel');
    if (!panel) return;
    if (synAILoaded && !force) return;
    const rubricInputs = document.querySelectorAll('#synSearchForm input[name="rubrics[]"]');
    const rubricIds = Array.from(rubricInputs).map(i => i.value);
    if (!rubricIds.length) return;

    panel.innerHTML = '<div class="syn-loading"><i class="fas fa-brain fa-spin"></i><p>Analysing rubrics with AI…</p></div>';

    fetch(APP_URL + '/api/get_ai_repertory_suggestions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'rubric_ids=' + encodeURIComponent(JSON.stringify(rubricIds))
    })
    .then(r => r.json())
    .then(data => {
        synAILoaded = true;
        if (data.success && data.suggestions && data.suggestions.remedies) {
            let html = '';
            data.suggestions.remedies.forEach((rem, idx) => {
                html += `<div class="syn-ai-card">
                    <div class="syn-ai-name">${idx+1}. ${synEscape(rem.name)}</div>
                    <div class="syn-ai-line"><strong>Potency:</strong> ${synEscape(rem.potency || '—')}</div>
                    <div class="syn-ai-line"><strong>Match:</strong> ${synEscape(String(rem.match_percentage || ''))}%</div>
                    <div class="syn-ai-line"><strong>Reasoning:</strong> ${synEscape(rem.reasoning || '')}</div>
                    <div class="syn-ai-line"><strong>Reference:</strong> ${synEscape(rem.reference || 'Not specified')}</div>
                    ${rem.matching_rubrics ? `<div class="syn-ai-line"><strong>Matching rubrics:</strong> ${synEscape(rem.matching_rubrics.join(', '))}</div>` : ''}
                </div>`;
            });
            if (data.suggestions.case_analysis) {
                html += `<div class="syn-ai-card" style="border-left-color:#0891b2;"><strong>Case analysis:</strong><div class="syn-ai-line">${synEscape(data.suggestions.case_analysis)}</div></div>`;
            }
            if (data.suggestions.cautions) {
                html += `<div class="syn-ai-card" style="border-left-color:#b45309; background:#fffbeb;"><strong>Cautions:</strong><div class="syn-ai-line">${synEscape(data.suggestions.cautions)}</div></div>`;
            }
            panel.innerHTML = html;
        } else {
            panel.innerHTML = '<div class="syn-empty"><i class="fas fa-exclamation-triangle"></i><p>' + synEscape(data.error || 'No AI suggestions returned.') + '</p></div>';
        }
    })
    .catch(err => {
        panel.innerHTML = '<div class="syn-empty"><i class="fas fa-exclamation-triangle"></i><p>' + synEscape(err.message) + '</p></div>';
    });
}

/* ====================== CSV export ====================== */
function synExportCSV() {
    let csv = 'Rank,Remedy,Common Name,Total Score,Coverage,Rubric Count\n';
    const rows = document.querySelectorAll('#synClipPane-remedies .syn-remedy-row');
    if (!rows.length) {
        // fallback: pull from server-rendered repertorization data via simple rows
        return alert('No remedy data to export.');
    }
    rows.forEach((row, idx) => {
        const name = row.querySelector('.syn-remedy-name')?.textContent.trim() || '';
        const common = row.querySelector('.syn-remedy-common')?.textContent.trim() || '';
        const stats = row.querySelector('.syn-remedy-stats')?.textContent.trim() || '';
        const score = row.querySelector('.syn-remedy-score')?.textContent.trim() || '';
        csv += `${idx+1},"${name.replace(/"/g,'""')}","${common.replace(/"/g,'""')}",${score},"${stats.replace(/"/g,'""')}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'repertorization_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

/* ====================== Helpers ====================== */
function synEscape(s) {
    if (s == null) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

/* ====================== Keyboard shortcuts ====================== */
document.addEventListener('keydown', e => {
    // Esc closes modals + mobile drawers
    if (e.key === 'Escape') {
        synCloseModal();
        synCloseSmart();
        synCloseDrawers();
        document.getElementById('synSuggestions').classList.remove('syn-open');
    }
    // "/" focuses search (unless typing in another input)
    if (e.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        document.getElementById('synSearchInput').focus();
    }
    // Ctrl+Enter triggers smart search
    if (e.key === 'Enter' && e.ctrlKey) {
        e.preventDefault();
        synRunSmart();
    }
});

/* ====================== Live suggestions (lightweight) ====================== */
const synSearchInput = document.getElementById('synSearchInput');
let synSuggestTimer = null;
synSearchInput.addEventListener('input', function() {
    clearTimeout(synSuggestTimer);
    const q = this.value.trim();
    const drop = document.getElementById('synSuggestions');
    if (q.length < 3) { drop.classList.remove('syn-open'); return; }
    synSuggestTimer = setTimeout(() => {
        fetch(APP_URL + '/api/get_rubric_suggestions.php?symptom=' + encodeURIComponent(q) + '&quick=1')
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data || !data.rubrics || !data.rubrics.length) { drop.classList.remove('syn-open'); return; }
                drop.innerHTML = data.rubrics.slice(0, 8).map(rb => `
                    <div class="syn-suggestion-item" onmousedown="synAddFromSmart(${rb.id})">
                        <div class="syn-suggestion-rubric">${synEscape(rb.rubric)}</div>
                        <div class="syn-suggestion-meta">
                            <span class="syn-tag syn-tag-cat">${synEscape(rb.category)}</span>
                            <span>${rb.remedy_count || 0} remedies</span>
                        </div>
                    </div>`).join('');
                drop.classList.add('syn-open');
            })
            .catch(() => drop.classList.remove('syn-open'));
    }, 250);
});
synSearchInput.addEventListener('blur', () => {
    setTimeout(() => document.getElementById('synSuggestions').classList.remove('syn-open'), 150);
});

/* ====================== Click-outside modal ====================== */
document.getElementById('synModal').addEventListener('click', e => { if (e.target.id === 'synModal') synCloseModal(); });
document.getElementById('synSmartModal').addEventListener('click', e => { if (e.target.id === 'synSmartModal') synCloseSmart(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
