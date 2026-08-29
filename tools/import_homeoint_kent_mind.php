<?php
/**
 * Import verified Kent chapter rubrics from Homeoint.
 *
 * Dry run:
 *   C:\xampp\php\php.exe tools\import_homeoint_kent_mind.php
 *   C:\xampp\php\php.exe tools\import_homeoint_kent_mind.php --chapter=kentvert.htm --category=vertigo --title=Vertigo
 *
 * Apply:
 *   C:\xampp\php\php.exe tools\import_homeoint_kent_mind.php --apply
 *   C:\xampp\php\php.exe tools\import_homeoint_kent_mind.php --chapter=kentvert.htm --category=vertigo --title=Vertigo --apply
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

const HOMEPOINT_BASE = 'http://homeoint.org/books/kentrep/';
const VERIFIED_SOURCE = 'Homeoint_Kent_Repertory';
const SOURCE_NAME = "Kent's Repertory";

$apply = in_array('--apply', $argv, true);
$chapterFile = 'kentmind.htm';
$categoryName = 'mind';
$chapterTitle = 'Mind';

foreach ($argv as $arg) {
    if (strpos($arg, '--chapter=') === 0) {
        $chapterFile = strtolower(trim(substr($arg, 10)));
    } elseif (strpos($arg, '--category=') === 0) {
        $categoryName = strtolower(trim(substr($arg, 11)));
    } elseif (strpos($arg, '--title=') === 0) {
        $chapterTitle = trim(substr($arg, 8));
    }
}

if (!preg_match('/^kent[a-z]+\.htm$/', $chapterFile)) {
    throw new InvalidArgumentException('Invalid --chapter value. Example: --chapter=kentvert.htm');
}

if (!preg_match('/^[a-z][a-z0-9_-]*$/', $categoryName)) {
    throw new InvalidArgumentException('Invalid --category value. Example: --category=vertigo');
}

$chapterTitle = $chapterTitle !== '' ? $chapterTitle : ucfirst($categoryName);
$chapterHeadingPattern = preg_quote(strtoupper($chapterTitle), '/');

function fetchHomeointKent(string $path): string
{
    $ch = curl_init(HOMEPOINT_BASE . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'CurenexAI Homeoint Kent Mind importer/1.0',
    ]);
    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($html === false || $status >= 400) {
        throw new RuntimeException("Could not fetch {$path}" . ($error ? ": {$error}" : " (HTTP {$status})"));
    }

    return $html;
}

function cleanKentText(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'Windows-1252');
    $text = str_replace(["\xc2\xa0", "\u{00a0}"], ' ', $text);
    return trim((string) preg_replace('/\s+/', ' ', $text));
}

function normalizeKentKey(string $text): string
{
    $text = cleanKentText($text);
    $text = preg_replace('/\([^)]*see[^)]*\)/i', ' ', $text);
    $text = preg_replace('/\([^)]*compare[^)]*\)/i', ' ', $text);
    $text = strtolower($text);
    $text = str_replace(['&amp;', '&'], ' and ', $text);
    $text = preg_replace('/\bagg\b\.?/i', ' aggravation ', $text);
    $text = preg_replace('/\bamel\b\.?/i', ' amelioration ', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    return trim((string) preg_replace('/\s+/', ' ', $text));
}

function rubricPartFromLine(string $text): string
{
    $text = cleanKentText($text);
    $text = preg_replace('/\([^)]*See[^)]*\)/i', ' ', $text);
    $text = preg_replace('/\([^)]*compare[^)]*\)/i', ' ', $text);

    $colon = strpos($text, ':');
    if ($colon !== false) {
        $text = substr($text, 0, $colon);
    }

    $text = preg_replace('/\s*\(p\.\s*\d+\)\s*$/i', '', (string) $text);
    return trim((string) preg_replace('/\s+/', ' ', $text), " \t\n\r\0\x0B,.;:-");
}

function domKentParagraphs(string $html): array
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
            'text' => cleanKentText($p->textContent ?? ''),
        ];
    }

    return $paragraphs;
}

function chapterPageFiles(string $chapterFile): array
{
    $html = fetchHomeointKent($chapterFile);
    preg_match_all('/href=["\']([^"\']*kent\d+\.htm)#[^"\']+["\'][^>]*>\s*p\.\s*(\d+)/i', $html, $matches, PREG_SET_ORDER);

    $files = [];
    foreach ($matches as $match) {
        $path = strtolower(str_replace('\\', '/', $match[1]));
        $path = preg_replace('#^\./#', '', $path);
        $files[$path] = (int) $match[2];
    }

    ksort($files);
    return $files;
}

function remedyMap(): array
{
    $rows = DB::query("SELECT id, remedy_name, remedy_short_name FROM remedies");
    $map = [];
    foreach ($rows ?: [] as $row) {
        foreach ([$row['remedy_short_name'] ?? '', $row['remedy_name'] ?? ''] as $name) {
            $key = normalizeKentKey((string) $name);
            if ($key !== '') {
                $map[$key] = (int) $row['id'];
            }
        }
    }
    return $map;
}

function extractRemedies(string $text, array $remedyMap): array
{
    $colon = strpos($text, ':');
    if ($colon === false) {
        return [];
    }

    $remedyText = substr($text, $colon + 1);
    $remedyText = preg_replace('/\([^)]*\)/', ' ', $remedyText);
    preg_match_all('/[A-Za-z][A-Za-z0-9-]*\.?/u', $remedyText, $matches);

    $remedies = [];
    foreach ($matches[0] as $token) {
        $raw = trim($token, " \t\n\r\0\x0B.");
        if (strlen($raw) < 2) {
            continue;
        }

        $key = normalizeKentKey($raw);
        if (isset($remedyMap[$key])) {
            $remedies[$remedyMap[$key]] = true;
        }
    }

    return array_keys($remedies);
}

function parsedChapterRubrics(array $remedyMap, string $chapterFile, string $categoryName, string $chapterTitle, string $chapterHeadingPattern): array
{
    $rubrics = [];

    foreach (chapterPageFiles($chapterFile) as $file => $firstPage) {
        $paragraphs = domKentParagraphs(fetchHomeointKent($file));
        $pathByDepth = [];
        $page = null;
        $expectPageHeading = false;

        foreach ($paragraphs as $paragraph) {
            $text = $paragraph['text'];
            if ($text === '' || $text === '----------' || strtoupper($text) === 'KENT') {
                continue;
            }

            if (preg_match('/^' . $chapterHeadingPattern . '\s+p\.\s*(\d+)/i', $text, $m)) {
                $page = (int) $m[1];
                $pathByDepth = [];
                $expectPageHeading = true;
                continue;
            }

            if ($page === null && strtoupper($text) === strtoupper($chapterTitle)) {
                $page = $firstPage;
                $pathByDepth = [];
                $expectPageHeading = false;
                continue;
            }

            if ($page === null || strtoupper($text) === strtoupper($chapterTitle)) {
                continue;
            }

            $part = rubricPartFromLine($text);
            if ($part === '' || strtoupper($part) === strtoupper($chapterTitle)) {
                continue;
            }

            $depth = (int) $paragraph['depth'];
            if ($expectPageHeading) {
                $pathByDepth = [$depth => $part];
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
            $parts = array_values($pathByDepth);
            $root = $parts[0] ?? $part;
            $sub = count($parts) > 1 ? implode(', ', array_slice($parts, 1)) : '';
            $complete = $chapterTitle . ', ' . implode(', ', $parts);
            $key = normalizeKentKey($complete);

            if ($key === '') {
                continue;
            }

            if (!isset($rubrics[$key])) {
                $rubrics[$key] = [
                    'rubric' => $root,
                    'sub_category' => $sub,
                    'complete_rubric' => $complete,
                    'page' => $page,
                    'remedies' => [],
                ];
            }

            foreach (extractRemedies($text, $remedyMap) as $remedyId) {
                $rubrics[$key]['remedies'][$remedyId] = true;
            }
        }
    }

    foreach ($rubrics as &$rubric) {
        $rubric['remedies'] = array_keys($rubric['remedies']);
    }

    return $rubrics;
}

$remedyMap = remedyMap();
$rubrics = parsedChapterRubrics($remedyMap, $chapterFile, $categoryName, $chapterTitle, $chapterHeadingPattern);

echo "Homeoint Kent {$chapterTitle} rubrics parsed: " . count($rubrics) . PHP_EOL;
echo 'Matched remedy short names in local remedies table: ' . count($remedyMap) . PHP_EOL;

$remedyLinks = 0;
foreach ($rubrics as $rubric) {
    $remedyLinks += count($rubric['remedies']);
}
echo 'Remedy links parsed and locally matched: ' . $remedyLinks . PHP_EOL;

if (!$apply) {
    echo "Dry run only. Re-run with --apply to import verified Kent {$chapterTitle}." . PHP_EOL;
    exit(0);
}

DB::beginTransaction();
try {
    $inserted = 0;
    $updated = 0;
    $mappings = 0;

    foreach ($rubrics as $rubric) {
        $existing = DB::queryOne(
            "SELECT id FROM repertory WHERE LOWER(TRIM(complete_rubric)) = LOWER(TRIM(?)) LIMIT 1",
            [$rubric['complete_rubric']]
        );

        if ($existing) {
            $repertoryId = (int) $existing['id'];
            DB::execute(
                "UPDATE repertory
                 SET category = ?,
                     rubric = ?,
                     sub_category = ?,
                     repertory_source = ?,
                     is_verified = 1,
                     verified_source = ?,
                     verified_page = ?,
                     verified_at = NOW()
                 WHERE id = ?",
                [$categoryName, $rubric['rubric'], $rubric['sub_category'], SOURCE_NAME, VERIFIED_SOURCE, $rubric['page'], $repertoryId]
            );
            $updated++;
        } else {
            $repertoryId = DB::insert('repertory', [
                'rubric' => $rubric['rubric'],
                'category' => $categoryName,
                'sub_category' => $rubric['sub_category'],
                'complete_rubric' => $rubric['complete_rubric'],
                'repertory_source' => SOURCE_NAME,
                'is_verified' => 1,
                'verified_source' => VERIFIED_SOURCE,
                'verified_page' => $rubric['page'],
                'verified_at' => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        foreach ($rubric['remedies'] as $remedyId) {
            $exists = DB::queryOne(
                "SELECT id FROM repertory_remedies WHERE repertory_id = ? AND remedy_id = ? LIMIT 1",
                [$repertoryId, $remedyId]
            );
            if ($exists) {
                continue;
            }

            DB::insert('repertory_remedies', [
                'repertory_id' => $repertoryId,
                'remedy_id' => $remedyId,
                'grade' => '1',
                'is_verified' => 1,
                'verified_source' => VERIFIED_SOURCE,
                'verified_page' => $rubric['page'],
                'verified_at' => date('Y-m-d H:i:s'),
            ]);
            $mappings++;
        }
    }

    DB::commit();
    echo "Imported Kent {$chapterTitle}. Inserted: {$inserted}, Updated: {$updated}, remedy mappings added: {$mappings}" . PHP_EOL;
} catch (Throwable $e) {
    DB::rollback();
    throw $e;
}
