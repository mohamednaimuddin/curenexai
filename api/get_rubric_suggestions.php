<?php
/**
 * API: Get AI-powered rubric suggestions from natural language symptoms
 * This API converts natural language symptom descriptions to matching repertory rubrics
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/gemini_api.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $symptom = trim($_POST['symptom'] ?? $_GET['symptom'] ?? '');
    $category = trim($_POST['category'] ?? $_GET['category'] ?? '');
    
    if (empty($symptom)) {
        echo json_encode(['success' => false, 'error' => 'No symptom provided']);
        exit;
    }

    // Step 1: Direct keyword search (with synonyms)
    $directResults = searchWithSynonyms($symptom, $category);
    
    // Step 2: Semantic search using embeddings (if available)
    $semanticResults = searchWithEmbeddings($symptom, $category);
    
    // Step 3: If AI is enabled and results are few, use AI to find matching rubrics
    $aiSuggestions = [];
    if (isAiEnabled() && (count($directResults) + count($semanticResults)) < 5) {
        $aiSuggestions = getAiRubricSuggestions($symptom, $category);
    }

    // Combine results, prioritize direct matches, then semantic, then AI
    $allResults = mergeResults($directResults, $semanticResults, $aiSuggestions);

    echo json_encode([
        'success' => true,
        'symptom' => $symptom,
        'rubrics' => array_slice($allResults, 0, 20),
        'direct_count' => count($directResults),
        'ai_enhanced' => count($aiSuggestions) > 0
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
exit;

/**
 * Search with synonyms and related terms
 */
function searchWithSynonyms($symptom, $category = '') {
    // Comprehensive symptom synonym map for homeopathic rubrics
    $synonymMap = [
        // Anger-related
        'angry' => ['anger', 'irritability', 'rage', 'wrath', 'fury', 'vexation', 'choleric', 'cross', 'peevish'],
        'anger' => ['angry', 'irritable', 'rage', 'wrath', 'fury', 'vexation', 'cross', 'peevish'],
        'irritable' => ['irritability', 'angry', 'cross', 'peevish', 'touchy', 'snappish', 'fretful'],
        'contradiction' => ['contradict', 'opposed', 'disagree', 'dispute', 'intolerant', 'contradicted'],
        'contradict' => ['contradiction', 'opposed', 'disagree', 'dispute', 'intolerant', 'contradicted'],
        'contradicted' => ['contradiction', 'contradict', 'intolerant', 'opposed'],
        
        // Colors/Discoloration
        'blue' => ['blueness', 'cyanosis', 'cyanotic', 'discoloration', 'livid', 'purple'],
        'blueness' => ['blue', 'cyanosis', 'cyanotic', 'discoloration', 'livid'],
        'cyanosis' => ['blue', 'blueness', 'cyanotic', 'discoloration'],
        'pale' => ['pallor', 'paleness', 'white', 'discoloration', 'blanched'],
        'red' => ['redness', 'flushed', 'erythema', 'discoloration', 'congested'],
        'yellow' => ['yellowness', 'jaundice', 'icterus', 'discoloration'],
        'black' => ['blackness', 'dark', 'discoloration', 'gangrenous'],
        'purple' => ['purplish', 'livid', 'blue', 'discoloration'],
        
        // Body parts - Face
        'lip' => ['lips', 'labial', 'mouth'],
        'lips' => ['lip', 'labial', 'mouth'],
        'face' => ['facial', 'countenance', 'cheek', 'cheeks'],
        'cheek' => ['cheeks', 'face', 'facial'],
        'nose' => ['nasal', 'nares', 'nostrils'],
        'eye' => ['eyes', 'ocular', 'optic'],
        'eyes' => ['eye', 'ocular', 'optic'],
        'ear' => ['ears', 'aural', 'auditory'],
        'tongue' => ['lingual', 'glossal'],
        
        // Fear-related
        'fear' => ['anxiety', 'fright', 'dread', 'terror', 'apprehension', 'fearful', 'afraid'],
        'afraid' => ['fear', 'anxiety', 'fright', 'apprehensive', 'fearful'],
        'anxiety' => ['anxious', 'fear', 'worry', 'apprehension', 'restlessness', 'nervousness'],
        'anxious' => ['anxiety', 'worried', 'fearful', 'apprehensive', 'nervous', 'restless'],
        
        // Sadness-related
        'sad' => ['sadness', 'grief', 'sorrow', 'melancholy', 'depression', 'dejection', 'weeping'],
        'sadness' => ['sad', 'grief', 'sorrow', 'melancholy', 'depression', 'dejection'],
        'depressed' => ['depression', 'sad', 'melancholy', 'dejected', 'despondent', 'gloomy'],
        'weeping' => ['crying', 'tears', 'lachrymation', 'sobbing', 'weeps'],
        'crying' => ['weeping', 'tears', 'weeps', 'sobbing'],
        
        // Sleep-related
        'sleepless' => ['insomnia', 'sleeplessness', 'wakefulness', 'restless sleep'],
        'insomnia' => ['sleepless', 'sleeplessness', 'wakefulness', 'cannot sleep'],
        'drowsy' => ['drowsiness', 'somnolence', 'sleepy', 'stupor'],
        
        // Pain-related
        'headache' => ['head pain', 'cephalalgia', 'pain in head', 'head ache'],
        'stomach pain' => ['gastralgia', 'stomach ache', 'epigastric pain', 'abdominal pain'],
        'burning' => ['burnt', 'scalding', 'heat sensation', 'hot'],
        'throbbing' => ['pulsating', 'pulsation', 'beating', 'hammering'],
        'sharp' => ['stitching', 'stabbing', 'lancinating', 'cutting', 'shooting'],
        'dull' => ['aching', 'heavy', 'pressing', 'bearing down'],
        
        // Temperature modalities
        'cold' => ['chilly', 'coldness', 'chill', 'frigid', 'freezing'],
        'hot' => ['heat', 'warm', 'warmth', 'burning', 'feverish'],
        'chilly' => ['cold', 'coldness', 'shivering', 'chill'],
        
        // Thirst
        'thirsty' => ['thirst', 'thirstless', 'desire for water', 'drinks'],
        'thirstless' => ['thirsty', 'no thirst', 'absence of thirst'],
        
        // General
        'tired' => ['fatigue', 'exhaustion', 'weakness', 'prostration', 'lassitude'],
        'weak' => ['weakness', 'debility', 'prostration', 'exhaustion', 'feeble'],
        'restless' => ['restlessness', 'cannot rest', 'tossing', 'moving', 'fidgety'],
        'sensitive' => ['sensitivity', 'oversensitive', 'hypersensitive', 'touch agg'],
        
        // Appetite/digestion
        'hungry' => ['hunger', 'appetite', 'ravenous', 'voracious'],
        'nausea' => ['nauseous', 'sickness', 'sick', 'qualmish'],
        'vomiting' => ['vomit', 'emesis', 'throwing up', 'retching'],
        
        // Mental states
        'forgetful' => ['forgetfulness', 'memory weak', 'memory loss', 'absent minded'],
        'confused' => ['confusion', 'bewildered', 'disoriented', 'dazed'],
        'jealous' => ['jealousy', 'envy', 'suspicious'],
        'jealousy' => ['jealous', 'envy', 'suspicious', 'possessive'],
        'suspicious' => ['suspicion', 'distrust', 'mistrustful', 'jealous'],
        'suspicion' => ['suspicious', 'distrust', 'mistrustful', 'jealous'],
        'weeps' => ['weeping', 'crying', 'tears', 'sobbing', 'tearful'],
        'weeping' => ['weeps', 'crying', 'tears', 'sobbing', 'tearful', 'lachrymation'],
        'consoled' => ['consolation', 'comfort', 'sympathy'],
        'consolation' => ['consoled', 'comfort', 'sympathy'],
        'carried' => ['carry', 'desires to be carried', 'wants to be carried'],
        'darkness' => ['dark', 'night', 'in the dark'],
        'dark' => ['darkness', 'night', 'obscurity'],
        
        // Extended mental synonyms for improved matching
        'elderly' => ['old', 'aged', 'senile', 'senility', 'old age'],
        'old' => ['elderly', 'aged', 'senile', 'senility'],
        'forgetfulness' => ['forgetful', 'memory', 'memory weak', 'absent minded', 'amnesia'],
        'talks' => ['talking', 'speech', 'somniloquy', 'spoken', 'speaks', 'talk'],
        'speaks' => ['speaking', 'speech', 'talks', 'talking', 'spoken', 'hasty speech'],
        'speech' => ['speaks', 'talks', 'talking', 'loquacity', 'voice'],
        'laughter' => ['laughing', 'laugh', 'hilarity', 'mirth', 'giggling'],
        'laughing' => ['laughter', 'laugh', 'hilarity', 'mirth'],
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
        'reason' => ['causeless', 'without cause', 'without reason'],
        'timid' => ['timidity', 'shy', 'bashful', 'cowardice', 'fearful'],
        'shy' => ['shyness', 'timid', 'bashful', 'retiring'],
        
        // Extended synonyms for more queries
        'manic' => ['mania', 'excitement', 'frenzy', 'madness', 'exaltation'],
        'grandiosity' => ['grandiose', 'megalomania', 'delusions of grandeur'],
        'cruelty' => ['cruel', 'malicious', 'inhumanity', 'brutality'],
        'remorse' => ['guilt', 'penitence', 'conscience', 'regret'],
        'sweats' => ['sweat', 'perspiration', 'sweating', 'diaphoresis'],
        'loss' => ['lost', 'losing', 'absent', 'want of', 'diminished'],
        'smell' => ['olfaction', 'anosmia', 'odor', 'scent'],
        'clearing' => ['clear', 'hawking', 'scraping'],
        'hoarseness' => ['hoarse', 'voice lost', 'voice rough', 'husky'],
        'diarrhoea' => ['diarrhea', 'loose stool', 'watery stool', 'flux'],
        'fruits' => ['fruit', 'acid fruits', 'citrus'],
        'daybreak' => ['dawn', 'morning', 'sunrise', 'early morning'],
        'religious' => ['religion', 'prayer', 'god', 'piety'],
        'ecstasy' => ['exaltation', 'rapture', 'bliss', 'euphoria'],
        'exaltation' => ['ecstasy', 'rapture', 'elation', 'excitement'],
        
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
        
        // Round 4 - Remaining failed query synonyms
        'ingrowing' => ['ingrown', 'in-grown', 'inward growing'],
        'toenail' => ['toe nail', 'nail', 'nails'],
        'suppuration' => ['suppurating', 'pus', 'abscess', 'purulent'],
        'pus' => ['suppuration', 'purulent', 'discharge'],
        'anaemia' => ['anemia', 'bloodless', 'pale', 'pallor'],
        'anemia' => ['anaemia', 'bloodless', 'pale', 'pallor'],
        'pallor' => ['pale', 'paleness', 'white', 'blanched', 'anaemia'],
        'atherosclerosis' => ['arteriosclerosis', 'hardening', 'arteries'],
        'hardening' => ['hard', 'indurated', 'induration', 'sclerosis'],
        'arteries' => ['arterial', 'artery', 'blood vessels'],
        'thoughts' => ['thinking', 'mind active', 'mental activity'],
        'asleep' => ['sleep', 'sleeping', 'drowsy'],
        'hawking' => ['hawked', 'clearing throat', 'scraping'],
        
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
        
        // Emotional triggers
        'grief' => ['sorrow', 'mourning', 'loss', 'bereavement', 'sadness'],
        'fright' => ['fear', 'scared', 'terror', 'shock', 'startle'],
        'mortification' => ['humiliation', 'embarrassment', 'shame', 'wounded pride'],
        'humiliation' => ['mortification', 'embarrassed', 'shame', 'indignation'],
        'indignation' => ['anger', 'resentment', 'mortification', 'humiliation'],
        
        // Body parts - General
        'hand' => ['hands', 'palm', 'palms', 'fingers'],
        'finger' => ['fingers', 'digit', 'digits'],
        'foot' => ['feet', 'sole', 'soles', 'toes'],
        'leg' => ['legs', 'lower limb', 'thigh', 'calf'],
        'arm' => ['arms', 'upper limb', 'forearm'],
        'back' => ['spine', 'spinal', 'lumbar', 'dorsal', 'cervical'],
        'chest' => ['thorax', 'thoracic', 'pectoral', 'breast'],
        'stomach' => ['gastric', 'epigastric', 'abdomen', 'belly'],
        'abdomen' => ['abdominal', 'stomach', 'belly', 'intestinal'],
        'throat' => ['pharynx', 'larynx', 'gullet', 'fauces'],
        'head' => ['cephalic', 'cranial', 'vertex', 'occiput', 'forehead'],
        'skin' => ['cutaneous', 'dermal', 'epidermis'],
        
        // Time modalities  
        'morning' => ['am', 'waking', 'on rising', 'forenoon'],
        'evening' => ['pm', 'afternoon', 'twilight', 'dusk'],
        'night' => ['midnight', 'during sleep', 'nocturnal'],
        
        // Common symptoms
        'cough' => ['coughing', 'tussis', 'expectoration'],
        'fever' => ['pyrexia', 'febrile', 'temperature', 'hot'],
        'diarrhea' => ['loose stool', 'watery stool', 'dysentery', 'flux'],
        'constipation' => ['constipated', 'hard stool', 'difficult stool', 'no stool'],
        'sneezing' => ['sneeze', 'sneezes', 'sternutation', 'coryza'],
        'sneeze' => ['sneezing', 'sneezes', 'sternutation'],
        'runny nose' => ['coryza', 'rhinorrhea', 'nasal discharge', 'fluent coryza'],
        'stuffy nose' => ['nasal obstruction', 'nose obstruction', 'blocked nose'],
        'hay fever' => ['allergic rhinitis', 'seasonal allergy', 'pollen allergy'],
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
    
    // Extract keywords from symptom
    $symptomLower = mb_strtolower($symptom);
    $words = preg_split('/[\s,\-]+/', $symptomLower);
    
    $searchTerms = [];
    
    // Add synonyms for each word (filtering stop words)
    foreach ($words as $word) {
        $word = trim($word);
        // Skip stop words and short words
        if (strlen($word) < 3 || in_array($word, $stopWords)) {
            continue;
        }
        
        $searchTerms[] = $word;
        if (isset($synonymMap[$word])) {
            $searchTerms = array_merge($searchTerms, $synonymMap[$word]);
        }
    }
    
    // Check for phrase patterns
    $phrasePatterns = [
        'gets angry' => ['anger', 'irritability', 'anger easily'],
        'becomes angry' => ['anger', 'irritability', 'anger easily'],
        'easily angered' => ['anger easily', 'irritability'],
        'when contradicted' => ['contradiction', 'intolerant of contradiction'],
        'intolerant of contradiction' => ['contradiction'],
        'cannot bear contradiction' => ['contradiction', 'intolerant'],
        'worse from anger' => ['anger agg', 'anger from'],
        'after anger' => ['anger from', 'anger agg'],
        'ailments from anger' => ['anger from', 'anger ailments'],
        'suppressed anger' => ['anger suppressed', 'indignation suppressed'],
        'cannot cry' => ['weeping impossible', 'tearless'],
        'wants to be alone' => ['company aversion', 'solitude desire'],
        'fear of death' => ['death fear', 'death thoughts'],
        'fear of disease' => ['disease fear', 'health anxiety'],
        // Physical symptoms with triggers
        'blue lip' => ['lips', 'blueness', 'discoloration', 'cyanosis'],
        'blue lips' => ['lips', 'blueness', 'discoloration', 'cyanosis'],
        'turns blue' => ['blueness', 'cyanosis', 'discoloration'],
        'gets blue' => ['blueness', 'cyanosis', 'discoloration'],
        'when angry' => ['anger', 'anger from', 'anger agg'],
        'from anger' => ['anger', 'anger from', 'anger agg'],
        'during anger' => ['anger', 'anger from', 'anger during'],
        'pale face' => ['face', 'pale', 'pallor', 'discoloration'],
        'red face' => ['face', 'red', 'flushed', 'discoloration'],
        'cold hands' => ['hands', 'coldness', 'cold extremities'],
        'cold feet' => ['feet', 'coldness', 'cold extremities'],
        'numb fingers' => ['fingers', 'numbness', 'tingling'],
        'cracked lips' => ['lips', 'cracked', 'fissured'],
        // Sneezing patterns (common homeopathic symptom)
        'sneezing in morning' => ['sneezing morning', 'sneezing, morning', 'morning sneezing'],
        'morning sneezing' => ['sneezing morning', 'sneezing, morning'],
        'sneezing morning' => ['sneezing, morning', 'morning sneezing'],
        'sneezing on waking' => ['sneezing morning waking', 'sneezing, morning, waking'],
        'sneezing on rising' => ['sneezing morning rising', 'sneezing, morning, on rising'],
        'sneezing in bed' => ['sneezing bed', 'sneezing, morning, bed'],
        'constant sneezing' => ['sneezing constant', 'sneezing, constant'],
        'violent sneezing' => ['sneezing violent', 'sneezing, violent'],
        'paroxysmal sneezing' => ['sneezing paroxysmal', 'sneezing, paroxysmal'],
        'hay fever' => ['sneezing hay fever', 'hay fever', 'allergic rhinitis'],
        'sneezing with runny nose' => ['sneezing coryza', 'sneezing, coryza, with', 'fluent coryza'],
        'sneezing in sun' => ['sneezing sun', 'sneezing, sun, exposure'],
        // Cough patterns
        'cough in morning' => ['cough morning', 'cough, morning'],
        'morning cough' => ['cough morning', 'cough, morning'],
        'dry cough at night' => ['cough night', 'cough dry', 'cough, night'],
        // Diarrhea patterns
        'diarrhea in morning' => ['diarrhea morning', 'diarrhea, morning'],
        'morning diarrhea' => ['diarrhea morning', 'diarrhea, morning'],
    ];
    
    foreach ($phrasePatterns as $phrase => $rubricTerms) {
        if (strpos($symptomLower, $phrase) !== false) {
            $searchTerms = array_merge($searchTerms, $rubricTerms);
        }
    }
    
    $searchTerms = array_unique($searchTerms);
    
    // Build SQL query with all search terms
    $conditions = [];
    $params = [];
    
    foreach ($searchTerms as $term) {
        $conditions[] = "(LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?)";
        $params[] = '%' . $term . '%';
        $params[] = '%' . $term . '%';
    }
    
    $sql = "SELECT DISTINCT r.id, r.rubric, r.complete_rubric, r.category, 
                   COUNT(rr.remedy_id) as remedy_count
            FROM repertory r 
            LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id 
            WHERE (" . implode(' OR ', $conditions) . ")";
    
    if (!empty($category)) {
        $sql .= " AND LOWER(r.category) = ?";
        $params[] = strtolower($category);
    }
    
    $sql .= " GROUP BY r.id ORDER BY remedy_count DESC, r.rubric LIMIT 30";
    
    $results = DB::query($sql, $params);
    
    // Add relevance score using filtered search terms
    foreach ($results as &$result) {
        $result['source'] = 'database';
        $result['match_score'] = calculateMatchScore($symptomLower, $result['rubric'], $result['complete_rubric'], $searchTerms);
    }
    
    // Filter out low-scoring results (minimum threshold)
    $results = array_filter($results, function($r) {
        return $r['match_score'] >= 10; // Minimum score threshold
    });
    
    // Sort by match score
    usort($results, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return array_values($results);
}

/**
 * Calculate match score based on how well the rubric matches the symptom
 * Now accepts searchTerms (filtered keywords) for more accurate scoring
 */
function calculateMatchScore($symptom, $rubric, $completeRubric, $searchTerms = []) {
    $score = 0;
    $rubricLower = mb_strtolower($rubric);
    $completeRubricLower = mb_strtolower($completeRubric ?? '');
    
    // Stop words to ignore in scoring
    $stopWords = ['when', 'where', 'while', 'patient', 'patients', 'gets', 'get', 
                  'the', 'a', 'an', 'is', 'are', 'was', 'were', 'from', 'with',
                  'and', 'or', 'but', 'for', 'to', 'of', 'in', 'on', 'at', 'by'];
    
    // If searchTerms provided (filtered), use those for scoring
    if (!empty($searchTerms)) {
        foreach ($searchTerms as $term) {
            $termLower = mb_strtolower($term);
            // Skip very short terms
            if (strlen($termLower) < 3) continue;
            
            if (strpos($rubricLower, $termLower) !== false) {
                $score += 15; // Higher score for rubric match
            }
            if (strpos($completeRubricLower, $termLower) !== false) {
                $score += 10;
            }
        }
    } else {
        // Fallback to original word matching - but filter stop words
        $symptomWords = preg_split('/[\s,\-]+/', $symptom);
        foreach ($symptomWords as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                if (strpos($rubricLower, $word) !== false) {
                    $score += 10;
                }
                if (strpos($completeRubricLower, $word) !== false) {
                    $score += 5;
                }
            }
        }
    }
    
    // Bonus for rubric being in MIND category for mental symptoms
    $mentalKeywords = ['angry', 'anger', 'fear', 'anxiety', 'sad', 'jealous', 'grief', 'irritable', 'contradiction', 'irritability'];
    foreach ($mentalKeywords as $keyword) {
        if (strpos($symptom, $keyword) !== false && stripos($completeRubric ?? '', 'mind') !== false) {
            $score += 20; // Higher bonus for correct category match
            break;
        }
    }
    
    // Penalty for body-part rubrics matching mental symptom searches
    $physicalCategories = ['eye', 'ear', 'nose', 'face', 'mouth', 'throat', 'chest', 'back', 'extremities', 'skin', 'vision', 'vertigo', 'fever', 'urine', 'bladder'];
    foreach ($mentalKeywords as $keyword) {
        if (strpos($symptom, $keyword) !== false) {
            foreach ($physicalCategories as $physCat) {
                if (stripos($completeRubric ?? '', $physCat) === 0 || stripos($rubricLower, $physCat) === 0) {
                    $score -= 10; // Reduce score for wrong category
                    break 2;
                }
            }
        }
    }
    
    return max(0, $score);
}

/**
 * Use AI to suggest matching rubrics for natural language symptoms
 */
function getAiRubricSuggestions($symptom, $category = '') {
    try {
        // Get sample rubrics from the database for context
        $sampleSql = "SELECT rubric, complete_rubric, category FROM repertory ORDER BY RAND() LIMIT 50";
        if (!empty($category)) {
            $sampleSql = "SELECT rubric, complete_rubric, category FROM repertory WHERE LOWER(category) = ? ORDER BY RAND() LIMIT 50";
            $sampleRubrics = DB::query($sampleSql, [strtolower($category)]);
        } else {
            $sampleRubrics = DB::query($sampleSql);
        }
        
        $rubricExamples = [];
        foreach ($sampleRubrics as $r) {
            $rubricExamples[] = $r['complete_rubric'] ?: $r['rubric'];
        }
        
        $prompt = "You are an expert in homeopathic repertory. A user is searching for the symptom: \"{$symptom}\"

Convert this natural language symptom into standard homeopathic repertory rubric terms. Homeopathic rubrics use specific clinical terminology.

Example rubrics from our database:
" . implode("\n", array_slice($rubricExamples, 0, 20)) . "

Provide up to 5 likely rubric search terms that would match this symptom in a repertory database. Focus on:
1. Standard repertory terminology (Kent's, Murphy's style)
2. Key symptoms words used in homeopathic rubrics
3. Related modalities and characteristics

Return ONLY a JSON array of strings with suggested search terms, like:
[\"anger easily\", \"contradiction intolerant\", \"irritability\"]

Do not include explanations, just the JSON array.";

        $gemini = new GeminiAPI();
        $result = $gemini->generateContent($prompt, ['temperature' => 0.3, 'maxTokens' => 500]);
        
        if (!empty($result['text'])) {
            // Extract JSON array from response
            if (preg_match('/\[.*?\]/s', $result['text'], $matches)) {
                $suggestions = json_decode($matches[0], true);
                if (is_array($suggestions)) {
                    // Search database for each suggestion
                    $rubrics = [];
                    foreach ($suggestions as $searchTerm) {
                        $searchTerm = trim($searchTerm);
                        if (strlen($searchTerm) >= 2) {
                            $sql = "SELECT DISTINCT r.id, r.rubric, r.complete_rubric, r.category,
                                           COUNT(rr.remedy_id) as remedy_count
                                    FROM repertory r 
                                    LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id 
                                    WHERE (LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?)";
                            $params = ['%' . mb_strtolower($searchTerm) . '%', '%' . mb_strtolower($searchTerm) . '%'];
                            
                            if (!empty($category)) {
                                $sql .= " AND LOWER(r.category) = ?";
                                $params[] = strtolower($category);
                            }
                            
                            $sql .= " GROUP BY r.id ORDER BY remedy_count DESC LIMIT 10";
                            
                            $found = DB::query($sql, $params);
                            foreach ($found as $f) {
                                $f['source'] = 'ai';
                                $f['ai_search_term'] = $searchTerm;
                                $f['match_score'] = 30; // AI suggestions get base score
                                $rubrics[$f['id']] = $f;
                            }
                        }
                    }
                    return array_values($rubrics);
                }
            }
        }
    } catch (Exception $e) {
        error_log('AI Rubric Suggestion Error: ' . $e->getMessage());
    }
    
    return [];
}

/**
 * Merge results from direct search, semantic search, and AI suggestions
 */
function mergeResults($direct, $semantic, $ai = []) {
    $merged = [];
    $seenIds = [];
    
    // Add direct results first (highest priority)
    foreach ($direct as $result) {
        if (!isset($seenIds[$result['id']])) {
            $seenIds[$result['id']] = true;
            $result['match_source'] = 'direct';
            $merged[] = $result;
        }
    }
    
    // Add semantic results second
    foreach ($semantic as $result) {
        if (!isset($seenIds[$result['id']])) {
            $seenIds[$result['id']] = true;
            $result['match_source'] = 'semantic';
            $merged[] = $result;
        }
    }
    
    // Add AI results that aren't duplicates
    foreach ($ai as $result) {
        if (!isset($seenIds[$result['id']])) {
            $seenIds[$result['id']] = true;
            $result['match_source'] = 'ai';
            $merged[] = $result;
        }
    }
    
    return $merged;
}

/**
 * Semantic search using FULLTEXT index on embeddings table
 */
function searchWithEmbeddings($symptom, $category = '') {
    $results = [];
    
    try {
        // Check if embeddings table exists and has data
        $check = DB::queryOne("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'embeddings'");
        if (!$check || $check['cnt'] == 0) {
            return [];
        }
        
        // Extract keywords from symptom
        $symptomLower = mb_strtolower(trim($symptom));
        $words = preg_split('/[\s,\-]+/', $symptomLower);
        $keywords = array_filter($words, function($w) {
            return strlen(trim($w)) >= 3 && !in_array($w, ['the', 'and', 'for', 'that', 'this', 'with', 'from', 'when', 'gets']);
        });
        
        if (empty($keywords)) {
            return [];
        }
        
        $searchTerms = implode(' ', $keywords);
        
        // Use FULLTEXT search on embeddings
        $sql = "SELECT e.source_id as id, r.rubric, r.complete_rubric, r.category,
                       COUNT(rr.remedy_id) as remedy_count,
                       MATCH(e.content_text, e.keywords) AGAINST (? IN NATURAL LANGUAGE MODE) as relevance
                FROM embeddings e
                INNER JOIN repertory r ON e.source_type = 'rubric' AND e.source_id = r.id
                LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id
                WHERE MATCH(e.content_text, e.keywords) AGAINST (? IN NATURAL LANGUAGE MODE)";
        
        $params = [$searchTerms, $searchTerms];
        
        if (!empty($category)) {
            $sql .= " AND LOWER(r.category) = ?";
            $params[] = strtolower($category);
        }
        
        $sql .= " GROUP BY e.source_id ORDER BY relevance DESC LIMIT 15";
        
        $results = DB::query($sql, $params);
        
        // Add metadata
        foreach ($results as &$result) {
            $result['source'] = 'semantic';
            $result['match_score'] = round($result['relevance'] * 20, 1);
        }
        
    } catch (Exception $e) {
        error_log('Semantic search error: ' . $e->getMessage());
    }
    
    return $results ?: [];
}
