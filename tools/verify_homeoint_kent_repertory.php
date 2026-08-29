<?php
/**
 * Cross-check local Kent repertory rubrics against Homeoint's Kent repertory.
 *
 * Dry run:
 *   C:\xampp\php\php.exe tools\verify_homeoint_kent_repertory.php
 *
 * Apply:
 *   C:\xampp\php\php.exe tools\verify_homeoint_kent_repertory.php --apply
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

const HOMEPOINT_BASE = 'http://homeoint.org/books/kentrep/';
const VERIFIED_SOURCE = 'Homeoint_Kent_Repertory';

$apply = in_array('--apply', $argv, true);
$homeointLineParts = [];

function chapterKey(string $chapter): string
{
    $key = normalizeRubric($chapter);
    $aliases = [
        'genitalia male' => 'male',
        'genitalia female' => 'female',
        'larynx and trachea' => 'larynx',
        'urinary organs' => 'urinary',
        'fever and heat' => 'fever',
        'external throat' => 'throat',
        'generalities' => 'generalities',
        'generals' => 'generalities',
        'general' => 'generalities',
    ];
    return $aliases[$key] ?? $key;
}

function fetchHomeoint(string $path): string
{
    $url = preg_match('/^https?:/i', $path) ? $path : HOMEPOINT_BASE . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'CurenexAI Kent verifier/1.0',
    ]);

    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($html === false || $status >= 400) {
        throw new RuntimeException("Could not fetch {$url}" . ($error ? ": {$error}" : " (HTTP {$status})"));
    }

    return $html;
}

function cleanText(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'Windows-1252');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string) $text);
    return str_replace(["\xc2\xa0", "\u{00a0}"], ' ', $text);
}

function normalizeRubric(string $text): string
{
    $text = cleanText($text);
    $text = preg_replace('/\([^)]*see[^)]*\)/i', ' ', $text);
    $text = preg_replace('/\bp\.?\s*\d+\b/i', ' ', $text);
    $text = strtolower($text);
    $text = str_replace(['&amp;', '&'], ' and ', $text);
    $text = preg_replace('/\bagg\b\.?/i', ' aggravation ', $text);
    $text = preg_replace('/\bamel\b\.?/i', ' amelioration ', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));

    $parts = $text === '' ? [] : explode(' ', $text);
    $deduped = [];
    foreach ($parts as $part) {
        if ($part !== end($deduped)) {
            $deduped[] = $part;
        }
    }
    return implode(' ', $deduped);
}

function normalizeCommaPath(string $text): string
{
    $segments = array_values(array_filter(array_map(static function ($segment) {
        return normalizeRubric($segment);
    }, explode(',', $text)), static fn($segment) => $segment !== ''));

    $deduped = [];
    foreach ($segments as $segment) {
        if ($segment !== end($deduped)) {
            $deduped[] = $segment;
        }
    }

    return implode(' ', $deduped);
}

function lineRubricPart(string $text): string
{
    $text = cleanText($text);
    $text = preg_replace('/\([^)]*See[^)]*\)/i', ' ', $text);
    $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));

    $colon = strpos($text, ':');
    if ($colon !== false) {
        $text = substr($text, 0, $colon);
    }

    $text = preg_replace('/\s*\(p\.\s*\d+\)\s*$/i', '', $text);
    return trim((string) $text, " \t\n\r\0\x0B,.;:-");
}

function domParagraphs(string $html): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $paragraphs = [];
    foreach ($dom->getElementsByTagName('p') as $p) {
        $depth = 0;
        for ($node = $p->parentNode; $node; $node = $node->parentNode) {
            if ($node instanceof DOMElement && strtolower($node->tagName) === 'dir') {
                $depth++;
            }
        }
        $paragraphs[] = [
            'depth' => $depth,
            'text' => cleanText($p->textContent ?? ''),
        ];
    }

    return $paragraphs;
}

function chapterLinks(): array
{
    $index = fetchHomeoint('index.htm');
    preg_match_all('/href=["\'](kent[a-z]+\.htm)["\'][^>]*>(.*?)<\/a>/is', $index, $matches, PREG_SET_ORDER);

    $skip = ['kentpref.htm', 'kentcont.htm', 'kentreme.htm', 'kentuserep.htm', 'kentrepert.htm'];
    $links = [];
    foreach ($matches as $match) {
        $href = strtolower($match[1]);
        if (in_array($href, $skip, true)) {
            continue;
        }
        $label = cleanText(strip_tags($match[2]));
        if ($label !== '') {
            $links[$href] = $label;
        }
    }
    return $links;
}

function pageLinksForChapter(string $chapterPath): array
{
    $html = fetchHomeoint($chapterPath);
    preg_match_all('/href=["\'](kent\d+\.htm)#P(\d+)["\']/i', $html, $matches, PREG_SET_ORDER);

    $pages = [];
    foreach ($matches as $match) {
        $pages[strtolower($match[1])] = (int) $match[2];
    }
    return $pages;
}

function buildHomeointRubricIndex(): array
{
    global $homeointLineParts;

    $chapters = chapterLinks();
    $allPages = [];
    foreach ($chapters as $chapterPath => $chapterLabel) {
        foreach (pageLinksForChapter($chapterPath) as $pagePath => $pageNo) {
            $allPages[$pagePath] = ['page' => $pageNo, 'chapter' => $chapterLabel];
        }
    }

    ksort($allPages);
    $index = [];

    foreach ($allPages as $pagePath => $meta) {
        $paragraphs = domParagraphs(fetchHomeoint($pagePath));
        $chapter = null;
        $pathByDepth = [];
        $lastPageNo = $meta['page'];
        $expectPageHeading = false;

        foreach ($paragraphs as $paragraph) {
            $text = $paragraph['text'];
            if ($text === '' || $text === '----------' || strtoupper($text) === 'KENT') {
                continue;
            }

            if (preg_match('/^([A-Z][A-Za-z ]+)\s+p\.\s*(\d+)/', $text, $m)) {
                $chapter = trim($m[1]);
                $lastPageNo = (int) $m[2];
                $pathByDepth = [];
                $expectPageHeading = true;
                continue;
            }

            if ($chapter === null) {
                continue;
            }

            $part = lineRubricPart($text);
            if ($part === '' || strtoupper($part) === strtoupper($chapter)) {
                continue;
            }

            $chapterKey = chapterKey($chapter);
            $lineKey = normalizeRubric($part);
            if ($lineKey !== '') {
                $homeointLineParts[$chapterKey][$lineKey] = true;
            }

            $depth = (int) $paragraph['depth'];
            if ($expectPageHeading) {
                // Homeoint page headings often show where the printed page starts,
                // not the parent for every following rubric on that page.
                $rootPart = trim(explode(',', $part, 2)[0]);
                $pathByDepth = [$depth => ($rootPart !== '' ? $rootPart : $part)];
                $expectPageHeading = false;
            } else {
                foreach (array_keys($pathByDepth) as $existingDepth) {
                    if ($existingDepth >= $depth) {
                        unset($pathByDepth[$existingDepth]);
                    }
                }
                $pathByDepth[$depth] = $part;
            }

            ksort($pathByDepth);
            $complete = $chapter . ', ' . implode(', ', $pathByDepth);
            $normalized = normalizeCommaPath($complete);
            if ($normalized !== '') {
                $index[$normalized] = [
                    'page' => $lastPageNo,
                    'text' => $complete,
                    'file' => $pagePath,
                ];
            }
        }
    }

    return $index;
}

function hasHomeointLineMatch(array $row): bool
{
    global $homeointLineParts;

    $chapter = chapterKey((string) ($row['category'] ?? ''));
    if (empty($homeointLineParts[$chapter])) {
        return false;
    }

    $root = normalizeRubric(explode(',', (string) ($row['rubric'] ?? ''), 2)[0]);
    if ($root === '' || empty($homeointLineParts[$chapter][$root])) {
        return false;
    }

    $subSegments = array_values(array_filter(array_map('trim', explode(',', (string) ($row['sub_category'] ?? ''))), static fn($segment) => $segment !== ''));
    for ($i = 0; $i < count($subSegments); $i++) {
        $tail = implode(', ', array_slice($subSegments, $i));
        $tailKey = normalizeRubric($tail);
        if ($tailKey === '') {
            continue;
        }

        $tokenCount = count(explode(' ', $tailKey));
        if ($tokenCount < 2) {
            continue;
        }

        if (!empty($homeointLineParts[$chapter][$tailKey])) {
            return true;
        }
    }

    return false;
}

function localMatchKeys(array $row): array
{
    $complete = (string) ($row['complete_rubric'] ?? '');
    $category = (string) ($row['category'] ?? '');
    $rubric = (string) ($row['rubric'] ?? '');
    $sub = (string) ($row['sub_category'] ?? '');

    $candidates = [
        $complete,
        "{$category}, {$rubric}, {$sub}",
        "{$category}, {$sub}, {$rubric}",
    ];

    $subSegments = array_values(array_filter(array_map('trim', explode(',', $sub)), static fn($segment) => $segment !== ''));
    foreach ($subSegments as $index => $_segment) {
        $tail = implode(', ', array_slice($subSegments, $index));
        $candidates[] = "{$category}, {$rubric}, {$tail}";
    }

    if ($rubric !== '') {
        $completeSegments = array_values(array_filter(array_map('trim', explode(',', $complete)), static fn($segment) => $segment !== ''));
        for ($i = 0; $i < count($completeSegments); $i++) {
            if (normalizeRubric($completeSegments[$i]) === normalizeRubric($rubric)) {
                $tail = implode(', ', array_slice($completeSegments, $i + 1));
                if ($tail !== '') {
                    $candidates[] = "{$category}, {$rubric}, {$tail}";
                }
            }
        }
    }

    $keys = [];
    foreach ($candidates as $candidate) {
        $key = normalizeCommaPath($candidate);
        if ($key !== '') {
            $keys[$key] = true;
        }
    }
    return array_keys($keys);
}

$homeoint = buildHomeointRubricIndex();
echo 'Homeoint rubrics parsed: ' . count($homeoint) . PHP_EOL;

$rows = DB::query("SELECT id, category, rubric, sub_category, complete_rubric FROM repertory WHERE repertory_source LIKE '%Kent%'");
$matches = [];
$samples = [];

foreach ($rows as $row) {
    foreach (localMatchKeys($row) as $key) {
        if (isset($homeoint[$key])) {
            $matches[(int) $row['id']] = $homeoint[$key]['page'];
            if (count($samples) < 8) {
                $samples[] = [$row['complete_rubric'], $homeoint[$key]['text']];
            }
            break;
        }
    }

    if (!isset($matches[(int) $row['id']]) && hasHomeointLineMatch($row)) {
        $matches[(int) $row['id']] = null;
    }
}

echo 'Local Kent rows checked: ' . count($rows) . PHP_EOL;
echo 'Matched against Homeoint: ' . count($matches) . PHP_EOL;
echo 'Not matched: ' . (count($rows) - count($matches)) . PHP_EOL;

foreach ($samples as $sample) {
    echo 'MATCH: ' . $sample[0] . ' <= ' . $sample[1] . PHP_EOL;
}

if (!$apply) {
    echo 'Dry run only. Re-run with --apply to update is_verified/verified_source.' . PHP_EOL;
    exit(0);
}

DB::beginTransaction();
try {
    DB::query(
        "UPDATE repertory
         SET is_verified = 0, verified_source = NULL, verified_page = NULL, verified_at = NULL
         WHERE repertory_source LIKE '%Kent%'"
    );
    DB::query(
        "UPDATE repertory_remedies rr
         INNER JOIN repertory r ON r.id = rr.repertory_id
         SET rr.is_verified = 0, rr.verified_source = NULL, rr.verified_page = NULL, rr.verified_at = NULL
         WHERE r.repertory_source LIKE '%Kent%'"
    );

    foreach ($matches as $id => $page) {
        DB::execute(
            "UPDATE repertory
             SET is_verified = 1, verified_source = ?, verified_page = ?, verified_at = NOW()
             WHERE id = ?",
            [VERIFIED_SOURCE, $page, $id]
        );
        DB::execute(
            "UPDATE repertory_remedies
             SET is_verified = 1, verified_source = ?, verified_page = ?, verified_at = NOW()
             WHERE repertory_id = ?",
            [VERIFIED_SOURCE, $page, $id]
        );
    }

    DB::commit();
    echo 'Applied verification update.' . PHP_EOL;
} catch (Throwable $e) {
    DB::rollback();
    throw $e;
}
