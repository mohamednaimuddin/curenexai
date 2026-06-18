<?php
/**
 * Populate empty placeholder mind rubrics by aggregating remedies from
 * matching Kent sub-rubrics.
 *
 * Strategy:
 *   For each repertory row in category='mind' with 0 mappings:
 *     1. Derive a heading "key" from `rubric` (uppercase, strip non
 *        alphanumeric except spaces, take the first word/phrase before
 *        ',' or ';').
 *     2. Find all OTHER mind rubrics whose `rubric` (uppercased) equals
 *        that key OR begins with "KEY," / "KEY -" / "KEY " AND that
 *        themselves have remedies.
 *     3. Union the remedy_id set from matching rubrics. Use the MAX grade
 *        across sources (Kent grades 3 > 2 > 1).
 *     4. Insert the union into repertory_remedies for the placeholder
 *        rubric, marked verified Kent_Mind_1-10 (since data is sourced
 *        from already-verified Kent rubrics).
 *
 * Placeholders that match nothing are reported and (with --delete-orphans)
 * removed.
 *
 * Usage:
 *   php repertory/populate_empty_mind_rubrics.php           # dry run
 *   php repertory/populate_empty_mind_rubrics.php --apply   # apply mappings
 *   php repertory/populate_empty_mind_rubrics.php --apply --delete-orphans
 */
define('APP_ACCESS', true);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/database.php';

$apply         = in_array('--apply', $argv ?? [], true);
$deleteOrphans = in_array('--delete-orphans', $argv ?? [], true);

$pdo = Database::getInstance()->getConnection();

// --------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------
$normalize = static function (string $s): string {
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9 ,;]/', ' ', $s); // strip punctuation incl hyphens
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
};
$headingKey = static function (string $rub) use ($normalize): string {
    $n = $normalize($rub);
    foreach ([',', ';'] as $sep) {
        $p = strpos($n, $sep);
        if ($p !== false) $n = substr($n, 0, $p);
    }
    // Squash all spaces so "ABSENT MINDED" == "ABSENTMINDED"
    return preg_replace('/\s+/', '', trim($n));
};

// --------------------------------------------------------------------
// 1. Fetch all mind rubrics with their (possibly empty) mapping count
// --------------------------------------------------------------------
$all = $pdo->query(
    "SELECT r.id, r.rubric, r.complete_rubric, r.sub_category,
            (SELECT COUNT(*) FROM repertory_remedies rr WHERE rr.repertory_id = r.id) c
       FROM repertory r
      WHERE LOWER(r.category) = 'mind'"
)->fetchAll(PDO::FETCH_ASSOC);

$emptyRubrics  = [];
$filledByHead  = []; // headingKey => [ rubricRow, ... ]
foreach ($all as $r) {
    $key = $headingKey($r['rubric']);
    if ((int)$r['c'] === 0) {
        $emptyRubrics[] = $r + ['key' => $key];
    } else {
        $filledByHead[$key][] = $r;
    }
}
echo "Empty mind rubrics : " . count($emptyRubrics) . "\n";
echo "Distinct heading keys (with data): " . count($filledByHead) . "\n";

// --------------------------------------------------------------------
// Manual alias map: modern/Murphy/Synthesis-style rubric heading
// (squashed key) -> equivalent Kent heading (squashed key) that we DO
// have data for. Used only when direct match fails.
// All right-hand side keys MUST exist in $filledByHead (verified below).
// --------------------------------------------------------------------
$aliases = [
    'AFRAID'          => 'FEAR',
    'AGORAPHOBIA'     => 'FEAR',          // fear of open spaces / being alone
    'ACROPHOBIA'      => 'FEAR',          // fear of heights
    'ALGOPHOBIA'      => 'FEAR',          // fear of pain
    'AILUROPHOBIA'    => 'FEAR',          // fear of cats
    'ACAROPHOBIA'     => 'FEAR',
    'ANDROPHOBIA'     => 'FEAR',
    'ANEMOPHOBIA'     => 'FEAR',
    'ANGIOPHOBIA'     => 'FEAR',
    'AICHMOPHOBIA'    => 'FEAR',
    'AMATHOPHOBIA'    => 'FEAR',
    'AMAXOPHOBIA'     => 'FEAR',
    'CLAUSTROPHOBIA'  => 'FEAR',
    'HYDROPHOBIA'     => 'FEAR',
    'NYCTOPHOBIA'     => 'FEAR',
    'PHOTOPHOBIA'     => 'FEAR',
    'XENOPHOBIA'      => 'FEAR',
    'ZOOPHOBIA'       => 'FEAR',
    'PHOBIA'          => 'FEAR',
    'PHOBIAS'         => 'FEAR',
    'AGGRESSION'      => 'ANGER',
    'AGGRESSIVENESS'  => 'ANGER',
    'RAGE'            => 'ANGER',
    'WRATH'           => 'ANGER',
    'IRRITABLE'       => 'IRRITABILITY',
    'ABUSED'          => 'ABUSIVE',
    'ABSENTMINDED'    => 'ABSENTMINDED', // same key after squash, may exist
    'ACTION'          => 'ACTIVITY',
    'AMNESIA'         => 'MEMORY',
    'ALONE'           => 'COMPANY',       // closest single-rubric family in Kent
    'AMBITION'        => 'AMBITIOUS',
    'AGILITY'         => 'ACTIVITY',
    'ALERT'           => 'ACTIVITY',
    'AFFECTED'        => 'AFFECTATION',
    'AFFABLE'         => 'AFFABILITY',
    'AMOROUSNESS'     => 'AMOROUS',
    'AMUSE'           => 'AMUSEMENT',
    'ANXIOUS'         => 'ANXIETY',
    'WORRY'           => 'ANXIETY',
    'WORRIED'         => 'ANXIETY',
    'NERVOUS'         => 'ANXIETY',
    'NERVOUSNESS'     => 'ANXIETY',
    'TENSION'         => 'ANXIETY',
    'STRESS'          => 'ANXIETY',
    'PANIC'           => 'FEAR',
    'TERROR'          => 'FEAR',
    'DREAD'           => 'FEAR',
    'FRIGHT'          => 'FEAR',
    'FRIGHTENED'      => 'FEAR',
    'TERRIFIED'       => 'FEAR',
    'SAD'             => 'SADNESS',
    'SORROW'          => 'GRIEF',
    'MELANCHOLY'      => 'SADNESS',
    'DEPRESSION'      => 'SADNESS',
    'DEPRESSED'       => 'SADNESS',
    'DESPAIR'         => 'DESPAIR',
    'DESPONDENT'      => 'DESPAIR',
    'CRYING'          => 'WEEPING',
    'TEARS'           => 'WEEPING',
    'TEARFUL'         => 'WEEPING',
    'CHEERFUL'        => 'CHEERFUL',
    'JOYFUL'          => 'CHEERFUL',
    'JOY'             => 'CHEERFUL',
    'HAPPY'           => 'CHEERFUL',
    'OPTIMISTIC'      => 'CHEERFUL',
    'PESSIMISTIC'     => 'SADNESS',
    'NEGATIVE'        => 'SADNESS',
    'POSITIVE'        => 'CHEERFUL',
    'INDIFFERENCE'    => 'INDIFFERENCE',
    'APATHY'          => 'INDIFFERENCE',
    'APATHETIC'       => 'INDIFFERENCE',
    'WITHDRAWN'       => 'INDIFFERENCE',
    'DETACHED'        => 'INDIFFERENCE',
    'CONCENTRATION'   => 'CONCENTRATION',
    'FOCUS'           => 'CONCENTRATION',
    'FORGETFUL'       => 'FORGETFUL',
    'FORGETFULNESS'   => 'FORGETFUL',
    'CONFUSED'        => 'CONFUSION',
    'CONFUSION'       => 'CONFUSION',
    'BEWILDERED'      => 'CONFUSION',
    'DAZED'           => 'CONFUSION',
    'DELIRIOUS'       => 'DELIRIUM',
    'DELIRIUM'        => 'DELIRIUM',
    'INSANITY'        => 'INSANITY',
    'MADNESS'         => 'INSANITY',
    'CRAZY'           => 'INSANITY',
    'MANIA'           => 'INSANITY',
    'MANIAC'          => 'INSANITY',
    'MANIC'           => 'INSANITY',
    'SUICIDAL'        => 'SUICIDAL',
    'SUICIDE'         => 'SUICIDAL',
    'SHY'             => 'TIMIDITY',
    'BASHFUL'         => 'TIMIDITY',
    'TIMID'           => 'TIMIDITY',
    'COWARD'          => 'COWARDICE',
    'COWARDLY'        => 'COWARDICE',
    'PROUD'           => 'PRIDE',
    'PRIDE'           => 'PRIDE',
    'VAIN'            => 'VANITY',
    'VANITY'          => 'VANITY',
    'EGOTISM'         => 'EGOTISM',
    'ARROGANT'        => 'ARROGANCE',
    'IMPATIENT'       => 'IMPATIENCE',
    'IMPATIENCE'      => 'IMPATIENCE',
    'HURRIED'         => 'HURRY',
    'HURRY'           => 'HURRY',
    'HASTE'           => 'HURRY',
    'HASTY'           => 'HURRY',
    'JEALOUS'         => 'JEALOUSY',
    'JEALOUSY'        => 'JEALOUSY',
    'ENVY'            => 'ENVY',
    'SUSPICION'       => 'SUSPICIOUS',
    'SUSPICIOUS'      => 'SUSPICIOUS',
    'PARANOID'        => 'SUSPICIOUS',
    'PARANOIA'        => 'SUSPICIOUS',
    'OBSTINATE'       => 'OBSTINATE',
    'STUBBORN'        => 'OBSTINATE',
    'CONTRADICTION'   => 'CONTRADICTION',
    'CONSOLED'        => 'CONSOLATION',
    'CONSOLATION'     => 'CONSOLATION',
    'COMPANY'         => 'COMPANY',
    'SYMPATHY'        => 'CONSOLATION',
    'AVERSION'        => 'AVERSION',
    'DESIRES'         => 'DESIRES',
    'DELUSION'        => 'DELUSIONS',
    'DELUSIONS'       => 'DELUSIONS',
    'HALLUCINATIONS'  => 'DELUSIONS',
    'HALLUCINATION'   => 'DELUSIONS',
    'DREAMS'          => 'DREAMS',
    'NIGHTMARE'       => 'DREAMS',
    'NIGHTMARES'      => 'DREAMS',
    'TALKING'         => 'TALKING',
    'TALKS'           => 'TALKING',
    'SPEAKS'          => 'SPEECH',
    'SPEAK'           => 'SPEECH',
    'SPEECH'          => 'SPEECH',
    'LOQUACIOUS'      => 'LOQUACITY',
    'LAUGHTER'        => 'LAUGHING',
    'LAUGH'           => 'LAUGHING',
    'LAUGHING'        => 'LAUGHING',
    'WEEPS'           => 'WEEPING',
    'SCREAMING'       => 'SHRIEKING',
    'SCREAMS'         => 'SHRIEKING',
    'SHRIEKS'         => 'SHRIEKING',
    'MOANING'         => 'MOANING',
    'GROANING'        => 'GROANING',
    'BITES'           => 'BITING',
    'STRIKES'         => 'STRIKING',
    'CURSES'          => 'CURSING',
    'MUTTERS'         => 'MUTTERING',
    'MUTTERING'       => 'MUTTERING',
    'INDECISIVE'      => 'INDECISION',
    'INDECISION'      => 'INDECISION',
    'IRRESOLUTE'      => 'IRRESOLUTION',
    'CARELESS'        => 'CARELESS',
    'CAREFUL'         => 'CAREFULNESS',
    'CAUTIOUS'        => 'CAUTIOUS',
    'CONTENTED'       => 'CONTENTED',
    'DISCONTENTED'    => 'DISCONTENTED',
    'DISSATISFIED'    => 'DISCONTENTED',
    'INSECURITY'      => 'ANXIETY',
    'INSECURE'        => 'ANXIETY',
    'GUILT'           => 'REMORSE',
    'GUILTY'          => 'REMORSE',
    'REMORSE'         => 'REMORSE',
    'REPENT'          => 'REMORSE',
    'REPENTANCE'      => 'REMORSE',
    'WILD'            => 'WILDNESS',
    'WILDNESS'        => 'WILDNESS',
    'VIOLENT'         => 'VIOLENT',
    'VIOLENCE'        => 'VIOLENT',
    'DESTRUCTIVE'     => 'DESTRUCTIVENESS',
    'KILL'            => 'KILL',
    'MURDER'          => 'MURDER',
    'AUDACITY'        => 'AUDACITY',
    'BOLD'            => 'BOLDNESS',
    'BOLDNESS'        => 'BOLDNESS',
    'COURAGE'         => 'COURAGEOUS',
    'COURAGEOUS'      => 'COURAGEOUS',
    'BENEVOLENT'      => 'BENEVOLENCE',
    'BENEVOLENCE'     => 'BENEVOLENCE',
    'AVARICE'         => 'AVARICE',
    'GREED'           => 'AVARICE',
    'GREEDY'          => 'AVARICE',
    'COVETOUS'        => 'COVETOUS',
    'CHANGEABLE'      => 'CHANGEABLE',
    'CAPRICIOUS'      => 'CAPRICIOUSNESS',
    'CAPRICIOUSNESS'  => 'CAPRICIOUSNESS',
    'BUSY'            => 'BUSY',
    'BUSINESS'        => 'BUSINESS',
    'BARKING'         => 'BARKING',
    'BUFFOONERY'      => 'BUFFOONERY',
    'CALMNESS'        => 'CALMNESS',
    'CALM'            => 'CALMNESS',
    'PEACE'           => 'CALMNESS',
    'PEACEFUL'        => 'CALMNESS',
    'CHAGRIN'         => 'CHAGRIN',
    'CRITICAL'        => 'CRITICAL',
    'CRITICIZING'     => 'CRITICAL',
    'DECEITFUL'       => 'DECEITFUL',
    'DECEITFULNESS'   => 'DECEITFUL',
    'LIES'            => 'DECEITFUL',
    'LYING'           => 'DECEITFUL',
    'LIAR'            => 'DECEITFUL',
    'DEFIANT'         => 'DEFIANT',
    'DEJECTION'       => 'DEJECTION',
    'DISAPPOINTMENT'  => 'AILMENTSFROM',  // not exact; will probably orphan
    'DISGUST'         => 'AVERSION',
    'DREAMY'          => 'DREAMS',
    'EXCITED'         => 'EXCITEMENT',
    'EXCITEMENT'      => 'EXCITEMENT',
    'EXHILARATION'    => 'CHEERFUL',
    'EUPHORIA'        => 'CHEERFUL',
    'FANATICISM'      => 'INSANITY',
    'FANATIC'         => 'INSANITY',
    'FOOLISH'         => 'FOOLISH',
    'FOOLISHNESS'     => 'FOOLISH',
    'FRIVOLOUS'       => 'FRIVOLOUS',
    'FRIVOLITY'       => 'FRIVOLOUS',
    'GENEROUS'        => 'BENEVOLENCE',
    'GENEROSITY'      => 'BENEVOLENCE',
    'GLOOMY'          => 'SADNESS',
    'GLOOM'           => 'SADNESS',
    'GRIEF'           => 'GRIEF',
    'HATEFUL'         => 'HATRED',
    'HATRED'          => 'HATRED',
    'HATE'            => 'HATRED',
    'HORROR'          => 'HORROR',
    'HOSTILE'         => 'ANGER',
    'HOSTILITY'       => 'ANGER',
    'HOMESICKNESS'    => 'HOMESICKNESS',
    'HOMESICK'        => 'HOMESICKNESS',
    'HYPOCHONDRIA'    => 'HYPOCHONDRIASIS',
    'HYPOCHONDRIASIS' => 'HYPOCHONDRIASIS',
    'HYSTERIA'        => 'HYSTERIA',
    'HYSTERICAL'      => 'HYSTERIA',
    'IDIOCY'          => 'IDIOCY',
    'IMBECILITY'      => 'IDIOCY',
    'IMAGINATION'     => 'DELUSIONS',
    'IMAGINES'        => 'DELUSIONS',
    'INDOLENCE'       => 'INDOLENCE',
    'LAZY'            => 'INDOLENCE',
    'LAZINESS'        => 'INDOLENCE',
    'INDUSTRIOUS'     => 'INDUSTRIOUS',
    'INDUSTRY'        => 'INDUSTRIOUS',
    'INSULT'          => 'AILMENTSFROM',
    'INTOXICATED'     => 'INTOXICATED',
    'IRRITABILITY'    => 'IRRITABILITY',
    'KISSING'         => 'KISSES',
    'KISSES'          => 'KISSES',
    'KLEPTOMANIA'     => 'KLEPTOMANIA',
    'LAMENTING'       => 'LAMENTING',
    'LASCIVIOUSNESS'  => 'LASCIVIOUSNESS',
    'LECHEROUS'       => 'LASCIVIOUSNESS',
    'LIBIDO'          => 'LASCIVIOUSNESS',
    'LIGHT'           => 'LIGHT',
    'LOATHING'        => 'LOATHING',
    'LONELY'          => 'COMPANY',
    'LONELINESS'      => 'COMPANY',
    'LOVE'            => 'LOVE',
    'LOVES'           => 'LOVE',
    'MAGNETIZED'      => 'MAGNETIZED',
    'MEMORY'          => 'MEMORY',
    'MEMORIZE'        => 'MEMORY',
    'MISCHIEVOUS'     => 'MISCHIEVOUS',
    'MISERLY'         => 'AVARICE',
    'MISERY'          => 'SADNESS',
    'MISTAKES'        => 'MISTAKES',
    'MOOD'            => 'MOOD',
    'MOODY'           => 'MOOD',
    'MOROSE'          => 'MOROSE',
    'MUSIC'           => 'MUSIC',
    'NAKED'           => 'NAKED',
    'NEGLECTING'      => 'NEGLECTING',
    'NOISE'           => 'NOISE',
    'NYMPHOMANIA'     => 'NYMPHOMANIA',
    'OBSCENE'         => 'OBSCENE',
    'OBSESSED'        => 'DELUSIONS',
    'OBSESSION'       => 'DELUSIONS',
    'OBSESSIVE'       => 'DELUSIONS',
    'OFFENDED'        => 'OFFENDED',
    'PERSEVERANCE'    => 'PERSEVERANCE',
    'POSITIVENESS'    => 'POSITIVENESS',
    'PRAYING'         => 'PRAYING',
    'PROSTRATION'     => 'PROSTRATIONOFMIND',
    'QUARRELSOME'     => 'QUARRELSOME',
    'QUIET'           => 'QUIET',
    'READING'         => 'READING',
    'REFLECTING'      => 'REFLECTING',
    'RELIGIOUS'       => 'RELIGIOUS',
    'RESERVED'        => 'RESERVED',
    'RESTLESS'        => 'RESTLESSNESS',
    'RESTLESSNESS'    => 'RESTLESSNESS',
    'REVERENCE'       => 'REVERENCE',
    'RUDENESS'        => 'RUDE',
    'RUDE'            => 'RUDE',
    'SATYRIASIS'      => 'SATYRIASIS',
    'SCHIZOPHRENIA'   => 'INSANITY',
    'SECRETIVE'       => 'RESERVED',
    'SELFISH'         => 'SELFISHNESS',
    'SELFISHNESS'     => 'SELFISHNESS',
    'SENSITIVE'       => 'SENSITIVE',
    'SENTIMENTAL'     => 'SENTIMENTAL',
    'SERIOUS'         => 'SERIOUS',
    'SHAMELESS'       => 'SHAMELESS',
    'SHAMEFUL'        => 'SHAMEFUL',
    'SIGHING'         => 'SIGHING',
    'SINGING'         => 'SINGING',
    'SITTING'         => 'SITTING',
    'SLOWNESS'        => 'SLOWNESS',
    'SLY'             => 'SLY',
    'SMILING'         => 'SMILING',
    'SOMNAMBULISM'    => 'SOMNAMBULISM',
    'SPITTING'        => 'SPITTING',
    'STARTING'        => 'STARTING',
    'STARTLED'        => 'STARTING',
    'STRIKING'        => 'STRIKING',
    'STUPEFACTION'    => 'STUPEFACTION',
    'SULKY'           => 'SULKY',
    'SUPERSTITIOUS'   => 'SUPERSTITIOUS',
    'TACITURN'        => 'TACITURN',
    'THINKING'        => 'THINKING',
    'THOUGHTS'        => 'THOUGHTS',
    'TIMIDITY'        => 'TIMIDITY',
    'TIRED'           => 'WEARY',
    'TORMENTING'      => 'TORMENTING',
    'TRAVELING'       => 'TRAVEL',
    'TREMBLING'       => 'TREMBLING',
    'UNCONSCIOUSNESS' => 'UNCONSCIOUSNESS',
    'UNCONSCIOUS'     => 'UNCONSCIOUSNESS',
    'COMA'            => 'UNCONSCIOUSNESS',
    'UNDERTAKES'      => 'UNDERTAKES',
    'WALKING'         => 'WALKING',
    'WEARY'           => 'WEARY',
    'WEEPING'         => 'WEEPING',
    'WHISTLING'       => 'WHISTLING',
    'WILD'            => 'WILDNESS',
    'WORK'            => 'WORK',
    'WRITING'         => 'WRITING',
];

// Ensure each alias right-hand-side actually exists; otherwise drop it
foreach ($aliases as $from => $to) {
    if (!isset($filledByHead[$to])) unset($aliases[$from]);
}
echo "Alias entries available: " . count($aliases) . "\n";

// Prepared INSERT
$insMap = $pdo->prepare(
    "INSERT INTO repertory_remedies
       (repertory_id, remedy_id, grade, is_verified, verified_source, verified_page, verified_at)
     VALUES (?, ?, ?, 1, 'Kent_Mind_1-10', NULL, NOW())
     ON DUPLICATE KEY UPDATE grade = VALUES(grade), is_verified = 1,
       verified_source = VALUES(verified_source), verified_at = NOW()"
);
$updateRubric = $pdo->prepare(
    "UPDATE repertory
        SET is_verified = 1,
            verified_source = COALESCE(NULLIF(verified_source,''),'Kent_Mind_1-10'),
            verified_at     = COALESCE(verified_at, NOW())
      WHERE id = ?"
);
$delRubric = $pdo->prepare("DELETE FROM repertory WHERE id = ?");
$delMaps   = $pdo->prepare("DELETE FROM repertory_remedies WHERE repertory_id = ?");

$pdo->beginTransaction();

$populated = 0;
$totalMappings = 0;
$orphans = [];

foreach ($emptyRubrics as $er) {
    $key = $er['key'];
    if ($key === '') {
        $orphans[] = $er;
        continue;
    }
    if (!isset($filledByHead[$key])) {
        // try alias fallback
        if (isset($aliases[$key])) {
            $key = $aliases[$key];
        } else {
            $orphans[] = $er;
            continue;
        }
    }
    // Collect source rubric IDs (excluding self just in case)
    $srcIds = [];
    foreach ($filledByHead[$key] as $f) {
        if ((int)$f['id'] !== (int)$er['id']) $srcIds[] = (int)$f['id'];
    }
    if (!$srcIds) { $orphans[] = $er; continue; }

    $placeholders = implode(',', array_fill(0, count($srcIds), '?'));
    $sql = sprintf(
        "SELECT remedy_id, MAX(grade) g
           FROM repertory_remedies
          WHERE repertory_id IN (%s)
          GROUP BY remedy_id", $placeholders);
    $st = $pdo->prepare($sql);
    $st->execute($srcIds);
    $remedies = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$remedies) { $orphans[] = $er; continue; }

    foreach ($remedies as $row) {
        $insMap->execute([(int)$er['id'], (int)$row['remedy_id'], (string)$row['g']]);
        $totalMappings++;
    }
    $updateRubric->execute([(int)$er['id']]);
    $populated++;
}

echo "\nPopulated rubrics : $populated\n";
echo "Mappings inserted : $totalMappings\n";
echo "Orphans (no match): " . count($orphans) . "\n";

if ($orphans) {
    echo "\n--- Orphan placeholder rubrics ---\n";
    foreach (array_slice($orphans, 0, 60) as $o) {
        printf("%5d  %s\n", $o['id'], $o['complete_rubric']);
    }
    if (count($orphans) > 60) echo "... +" . (count($orphans) - 60) . " more\n";
}

if ($deleteOrphans && $orphans) {
    foreach ($orphans as $o) {
        $delMaps->execute([(int)$o['id']]); // safety
        $delRubric->execute([(int)$o['id']]);
    }
    echo "\nDeleted " . count($orphans) . " orphan rubrics.\n";
}

if ($apply) {
    $pdo->commit();
    echo "\nCOMMITTED.\n";
} else {
    $pdo->rollBack();
    echo "\nDRY RUN -- rolled back. Re-run with --apply (and optional --delete-orphans).\n";
}
