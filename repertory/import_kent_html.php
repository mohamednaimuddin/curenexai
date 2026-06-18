<?php
/**
 * Kent Repertory HTML Importer
 *
 * Parses the Kent Repertory MIND chapter HTML files in
 *   assets/Kent_rubics/_kent_html/kent0000.htm ... kent0095.htm
 * and imports rubrics + remedies into:
 *   - `repertory`             (rubric, sub_category, complete_rubric, ...)
 *   - `repertory_remedies`    (repertory_id, remedy_id, grade)
 *
 * Grade convention (Kent / Boericke):
 *   3 = Bold red    (CAPITALS in Kent)        e.g. <b><font COLOR="#ff0000">Apis.</b></font>
 *   2 = Italic blue (italics in Kent)         e.g. <i><font COLOR="#0000ff">Acon.</i></font>
 *   1 = Plain                                  e.g. ang.
 *
 * Idempotent: existing rubrics/remedy mappings are updated, not duplicated.
 * Unknown remedy short-names are skipped and reported (no auto-create).
 *
 * USAGE
 *   CLI (dry-run):  php repertory/import_kent_html.php
 *   CLI (apply):    php repertory/import_kent_html.php --apply
 *   Web:            /repertory/import_kent_html.php?apply=1   (must be logged-in admin)
 */

// ---------------------------------------------------------------------------
// Bootstrap (works for both CLI and Web)
// ---------------------------------------------------------------------------
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    define('APP_ACCESS', true);
    require __DIR__ . '/../config/config.php';
    require __DIR__ . '/../includes/database.php';
    $apply = in_array('--apply', $argv ?? [], true);
} else {
    require_once __DIR__ . '/../includes/init.php';
    requireLogin();
    // Only allow doctors with admin role to run via web; fall back gracefully.
    $doctor = getLoggedInDoctor();
    if (empty($doctor['role']) || strtolower($doctor['role']) !== 'admin') {
        // Non-admins may still preview (dry-run) but not apply.
        $apply = false;
    } else {
        $apply = isset($_GET['apply']) && $_GET['apply'] == '1';
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$pdo = Database::getInstance()->getConnection();

$HTML_DIR = realpath(__DIR__ . '/../assets/Kent_rubics/_kent_html');
if (!$HTML_DIR || !is_dir($HTML_DIR)) {
    fwrite(STDERR, "HTML directory not found: $HTML_DIR\n");
    exit(1);
}

echo "Kent Repertory HTML Importer\n";
echo "================================\n";
echo "Source dir : $HTML_DIR\n";
echo "Mode       : " . ($apply ? "APPLY (writing to DB)" : "DRY-RUN (no changes)") . "\n\n";

// ---------------------------------------------------------------------------
// Load remedies map: normalized short_name -> id
// ---------------------------------------------------------------------------
$remedyMap = [];
$normalizeShort = static function (string $s): string {
    $s = strtolower(trim($s));
    $s = rtrim($s, '.');
    $s = preg_replace('/\s+/', '', (string)$s);
    return (string)$s;
};

$res = $pdo->query("SELECT id, remedy_short_name FROM remedies");
while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
    $key = $normalizeShort((string)$r['remedy_short_name']);
    if ($key !== '' && !isset($remedyMap[$key])) {
        $remedyMap[$key] = (int)$r['id'];
    }
}
echo "Loaded remedies: " . count($remedyMap) . "\n";

// ---------------------------------------------------------------------------
// Existing rubric map for `mind` category: lower(complete)|lower(sub) -> id
// ---------------------------------------------------------------------------
$rubricMap = [];
// Key on (rubric, sub_category) — case/whitespace-insensitive — so we match
// existing rubrics regardless of complete_rubric label format ("MIND - X"
// vs the project's canonical "Mind, X, Y").
$res = $pdo->query("SELECT id, rubric, sub_category FROM repertory WHERE LOWER(category)='mind'");
while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
    $key = strtolower(trim((string)$r['rubric'])) . '|' . strtolower(trim((string)$r['sub_category']));
    $rubricMap[$key] = (int)$r['id'];
}
echo "Existing mind rubrics: " . count($rubricMap) . "\n\n";

// ---------------------------------------------------------------------------
// Parser
// ---------------------------------------------------------------------------

/**
 * Strip every tag from a fragment while remembering nothing. Used for plain
 * text extraction of rubric labels and sub-rubric descriptors.
 */
function kent_plain(string $html): string {
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim((string)$t);
}

/**
 * Split a remedy-list HTML fragment into [shortName, grade] tuples by walking
 * the inline markup. Bold-red => 3, italic-blue => 2, plain => 1.
 * A remedy is any token ending with '.' (Kent uses dot-terminated abbrevs).
 */
function kent_parse_remedies(string $html): array {
    $remedies = [];
    // Normalise tags to lowercase for the state machine.
    $html = preg_replace_callback('#</?[a-zA-Z]+[^>]*>#', static function ($m) {
        return strtolower($m[0]);
    }, $html);

    $bold   = 0;
    $italic = 0;
    $red    = 0;
    $blue   = 0;

    $buf  = '';
    $i    = 0;
    $len  = strlen($html);

    $flush = static function () use (&$buf, &$bold, &$italic, &$red, &$blue, &$remedies) {
        $text = html_entity_decode($buf, ENT_QUOTES, 'UTF-8');
        $buf  = '';
        // Split by comma/semicolon; a remedy token ends with '.'.
        $tokens = preg_split('/[,;]/u', $text);
        foreach ($tokens as $tok) {
            $tok = trim($tok);
            if ($tok === '') continue;
            // Pick only tokens that look like a remedy abbreviation: letters
            // optionally with hyphen/digits, ending in '.'.
            if (preg_match('/^[A-Za-z][A-Za-z\-]*\d*\.?$/', $tok)) {
                $name = rtrim($tok, '.');
                if ($name === '' || strlen($name) < 2) continue;
                // Grade based on current styling at the time of flush. We
                // approximate by saying: if at least one bold+red span is
                // currently open, grade=3; else if italic+blue, grade=2; else 1.
                $grade = ($bold && $red) ? 3 : (($italic && $blue) ? 2 : 1);
                $remedies[] = [strtolower($name), $grade];
            }
        }
    };

    while ($i < $len) {
        if ($html[$i] === '<') {
            // flush text accumulated so far with the *current* style
            if ($buf !== '') {
                $flush();
            }
            $close = strpos($html, '>', $i);
            if ($close === false) break;
            $tag = substr($html, $i, $close - $i + 1);
            // Track style state
            if (preg_match('#^<b\b#', $tag))                $bold++;
            elseif (preg_match('#^</b>#', $tag))            $bold = max(0, $bold - 1);
            elseif (preg_match('#^<i\b#', $tag))            $italic++;
            elseif (preg_match('#^</i>#', $tag))            $italic = max(0, $italic - 1);
            elseif (preg_match('#^<font[^>]*color="?\#ff0000"?#i', $tag)) $red++;
            elseif (preg_match('#^<font[^>]*color="?\#0000ff"?#i', $tag)) $blue++;
            elseif (preg_match('#^</font>#', $tag)) {
                // Closing a font tag: best-effort decrement whichever was
                // currently active (LIFO not tracked, but the document is
                // well-balanced enough for the heuristic).
                if ($red > 0 && $bold) { $red--; }
                elseif ($blue > 0 && $italic) { $blue--; }
                elseif ($red > 0) { $red--; }
                elseif ($blue > 0) { $blue--; }
            }
            $i = $close + 1;
            continue;
        }
        $buf .= $html[$i];
        $i++;
    }
    if ($buf !== '') $flush();

    // Deduplicate keeping the HIGHEST grade per remedy.
    $best = [];
    foreach ($remedies as [$name, $grade]) {
        if (!isset($best[$name]) || $grade > $best[$name]) {
            $best[$name] = $grade;
        }
    }
    $out = [];
    foreach ($best as $name => $grade) {
        $out[] = [$name, $grade];
    }
    return $out;
}

/**
 * Parse a single Kent HTML file into rubric records:
 *   [
 *     'rubric'          => 'ABSENT-MINDED',
 *     'sub_category'    => '' | 'morning' | 'reading, while' | ...,
 *     'complete_rubric' => 'MIND - ABSENT-MINDED' or 'MIND - ABSENT-MINDED - morning',
 *     'page'            => 1,
 *     'remedies'        => [['acon',3], ['agn',2], ...],
 *   ]
 */
function kent_parse_file(string $path): array {
    $html = file_get_contents($path);
    if ($html === false) return [];

    // Drop the boilerplate header up to <body ...>
    $bodyStart = stripos($html, '<body');
    if ($bodyStart !== false) {
        $bodyOpen = strpos($html, '>', $bodyStart);
        if ($bodyOpen !== false) $html = substr($html, $bodyOpen + 1);
    }
    // Drop </body>...
    $bodyEnd = stripos($html, '</body>');
    if ($bodyEnd !== false) $html = substr($html, 0, $bodyEnd);

    // Split on <p ...> boundaries — every Kent entry is its own paragraph.
    // We retain the original markup inside each paragraph.
    $parts = preg_split('#<p\b[^>]*>#i', $html);
    array_shift($parts); // anything before first <p> is dropped

    $records = [];
    $currentPage   = 1;
    $currentRubric = '';
    $inSubBlock    = false;  // are we currently inside <dir> sub-rubric block?

    foreach ($parts as $raw) {
        // The paragraph body runs until the next </p> (markup before that
        // may include nested <dir> openers etc., we just trim).
        $endP = stripos($raw, '</p>');
        $body = $endP !== false ? substr($raw, 0, $endP) : $raw;
        $tail = $endP !== false ? substr($raw, $endP + 4) : '';

        // Detect page anchors: <a NAME="P12">p. 12</a>
        if (preg_match('/name="?p(\d+)"?/i', $body, $m)) {
            $currentPage = (int)$m[1];
        }

        // Detect <dir> openers/closers in the tail or body to track sub level.
        $opens  = preg_match_all('#<dir\b#i', $body . $tail, $tmp);
        $closes = preg_match_all('#</dir>#i', $body . $tail, $tmp);
        // We only care about the *innermost* dir: a sub-rubric paragraph
        // typically sits inside one extra <dir> right under its main rubric.
        // The file already opens 2 <dir> wrappers up top — so an additional
        // open level past 2 means we're inside a sub block.

        $plain = kent_plain($body);
        if ($plain === '' || $plain === '----------') {
            $inSubBlock = $inSubBlock || ($opens > $closes);
            if ($closes > $opens) $inSubBlock = false;
            continue;
        }

        // Skip navigation / chapter heading lines.
        if (preg_match('/^(MIND|KENT|p\.\s*\d+|\<+|\>+)$/i', $plain)) {
            $inSubBlock = $inSubBlock || ($opens > $closes);
            if ($closes > $opens) $inSubBlock = false;
            continue;
        }

        // A line that contains a ':' is a rubric / sub-rubric with remedies.
        $colonPos = strpos($body, ':');
        if ($colonPos !== false) {
            $labelHtml   = substr($body, 0, $colonPos);
            $remediesHtml = substr($body, $colonPos + 1);

            $label = kent_plain($labelHtml);
            $label = preg_replace('/\s*\([^)]*\)\s*$/u', '', $label); // trim trailing "(See ...)"
            $label = trim($label);

            $isSub = $inSubBlock;
            // Heuristic: a label that is fully UPPERCASE (with optional punctuation)
            // is a main rubric, even at the same dir level.
            $isUpper = (mb_strtoupper($label, 'UTF-8') === $label) && preg_match('/[A-Z]/u', $label);

            $remedies = kent_parse_remedies($remediesHtml);

            if ($isUpper || !$isSub) {
                $currentRubric = $label;
                $records[] = [
                    'rubric'          => $currentRubric,
                    'sub_category'    => '',
                    'complete_rubric' => 'Mind, ' . $currentRubric,
                    'page'            => $currentPage,
                    'remedies'        => $remedies,
                ];
            } else {
                if ($currentRubric === '') {
                    // sub-rubric with no parent — skip
                } else {
                    $records[] = [
                        'rubric'          => $currentRubric,
                        'sub_category'    => $label,
                        'complete_rubric' => 'Mind, ' . $currentRubric . ', ' . $label,
                        'page'            => $currentPage,
                        'remedies'        => $remedies,
                    ];
                }
            }
        } else {
            // No colon: this is either a rubric header with no remedies on its
            // own line (e.g. "ABANDONED (See Forsaken)") or pure heading text.
            $label = $plain;
            $label = preg_replace('/\s*\([^)]*\)\s*$/u', '', $label);
            $label = trim($label);
            if ($label !== '' && (mb_strtoupper($label, 'UTF-8') === $label) && preg_match('/[A-Z]/u', $label)) {
                $currentRubric = $label;
                // Record a header-only entry so the rubric exists in DB.
                $records[] = [
                    'rubric'          => $currentRubric,
                    'sub_category'    => '',
                    'complete_rubric' => 'Mind, ' . $currentRubric,
                    'page'            => $currentPage,
                    'remedies'        => [],
                ];
            }
        }

        // Update sub-block tracking AFTER processing this paragraph.
        if ($opens > $closes) $inSubBlock = true;
        if ($closes > $opens) $inSubBlock = false;
    }

    return $records;
}

// ---------------------------------------------------------------------------
// Drive
// ---------------------------------------------------------------------------
$files = glob($HTML_DIR . DIRECTORY_SEPARATOR . 'kent*.htm');
sort($files);
echo "Files to process: " . count($files) . "\n\n";

if ($apply) {
    $pdo->beginTransaction();
}

$insRubric = $pdo->prepare("
    INSERT INTO repertory
        (rubric, category, sub_category, complete_rubric, repertory_source,
         is_verified, verified_source, verified_page, verified_at)
    VALUES (?, 'mind', ?, ?, 'Kent''s Repertory', 1, 'Kent_Mind_HTML_1-95', ?, NOW())
");
$updRubric = $pdo->prepare("
    UPDATE repertory
       SET verified_page = ?, is_verified = 1,
           verified_source = COALESCE(NULLIF(verified_source,''), 'Kent_Mind_HTML_1-95'),
           verified_at = COALESCE(verified_at, NOW())
     WHERE id = ?
");
$insMap = $pdo->prepare("
    INSERT INTO repertory_remedies
        (repertory_id, remedy_id, grade, is_verified, verified_source, verified_page, verified_at)
    VALUES (?, ?, ?, 1, 'Kent_Mind_HTML_1-95', ?, NOW())
    ON DUPLICATE KEY UPDATE
        grade = GREATEST(CAST(grade AS UNSIGNED), VALUES(grade)),
        is_verified = 1,
        verified_source = COALESCE(NULLIF(verified_source,''), VALUES(verified_source)),
        verified_page = COALESCE(verified_page, VALUES(verified_page)),
        verified_at = COALESCE(verified_at, VALUES(verified_at))
");

$totalRubrics   = 0;
$totalMappings  = 0;
$insertedRubrics = 0;
$updatedRubrics  = 0;
$insertedMaps    = 0;
$skippedRemedies = [];

try {
    foreach ($files as $file) {
        $base = basename($file);
        $records = kent_parse_file($file);
        echo str_pad($base, 18) . " : " . count($records) . " rubric records\n";

        foreach ($records as $rec) {
            $totalRubrics++;
            $key = strtolower(trim($rec['rubric'])) . '|' . strtolower(trim($rec['sub_category']));
            if (isset($rubricMap[$key])) {
                $rubricId = $rubricMap[$key];
                if ($apply) {
                    $updRubric->execute([$rec['page'], $rubricId]);
                }
                $updatedRubrics++;
            } else {
                if ($apply) {
                    $insRubric->execute([
                        $rec['rubric'],
                        $rec['sub_category'],
                        $rec['complete_rubric'],
                        $rec['page'],
                    ]);
                    $rubricId = (int)$pdo->lastInsertId();
                } else {
                    $rubricId = 0;
                }
                $rubricMap[$key] = $rubricId;
                $insertedRubrics++;
            }

            foreach ($rec['remedies'] as [$short, $grade]) {
                $norm = $normalizeShort($short);
                if (!isset($remedyMap[$norm])) {
                    $skippedRemedies[$norm] = ($skippedRemedies[$norm] ?? 0) + 1;
                    continue;
                }
                $remedyId = $remedyMap[$norm];
                $totalMappings++;
                if ($apply && $rubricId > 0) {
                    $insMap->execute([$rubricId, $remedyId, $grade, $rec['page']]);
                    $insertedMaps++;
                }
            }
        }
    }

    if ($apply) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n--- Summary ---\n";
echo "Rubric records parsed      : $totalRubrics\n";
echo "Rubrics inserted (new)     : $insertedRubrics\n";
echo "Rubrics matched (updated)  : $updatedRubrics\n";
echo "Remedy mappings written    : " . ($apply ? $insertedMaps : $totalMappings) . ($apply ? '' : ' (would-be)') . "\n";
echo "Unknown remedy short-names : " . count($skippedRemedies) . "\n";
if (!empty($skippedRemedies)) {
    arsort($skippedRemedies);
    $top = array_slice($skippedRemedies, 0, 20, true);
    echo "  Top skipped:\n";
    foreach ($top as $n => $c) echo "    $n  ({$c}x)\n";
}
echo "\nDone. " . ($apply ? "[changes committed]" : "[dry-run, re-run with --apply to commit]") . "\n";
