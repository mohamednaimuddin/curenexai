<?php
/**
 * SEARCH ENGINE NOTIFIER - CURENEX / CURENEXAI
 *
 * Notes (2026):
 *  - Google deprecated /ping?sitemap= in June 2023 (returns 404). Use Search Console.
 *  - Bing  deprecated /ping?sitemap= in May  2022 (returns 410). Use IndexNow.
 *
 * This script:
 *  1. Verifies sitemap.xml is publicly reachable.
 *  2. Submits URLs via IndexNow (Bing, Yandex, Seznam, Naver, Yep).
 *  3. Reminds you to submit/refresh in Google Search Console manually.
 *
 * Setup for IndexNow:
 *  - Generate a key:  php -r "echo bin2hex(random_bytes(16));"
 *  - Save it as <key>.txt at the site root, file containing only the key string
 *  - Set INDEXNOW_KEY below to that key
 *
 * Usage: php ping_search_engines.php
 */

// ============ CONFIG ============
$host        = 'curenexai.com';
$sitemapUrl  = "https://{$host}/sitemap.xml";
$keyLocation = "https://{$host}/"; // host root that serves <KEY>.txt

// Set this to your IndexNow key (and create <KEY>.txt at site root).
// Leave empty to skip IndexNow submission.
$INDEXNOW_KEY = 'ee3e2682437da959ec58d19f13133838';

// URLs to notify search engines about (extensionless, canonical).
$urlsToSubmit = [
    "https://{$host}/",
    "https://{$host}/about",
    "https://{$host}/digital-repertory-software-homeopathy",
    "https://{$host}/register",
    "https://{$host}/login",
    "https://{$host}/privacy",
    "https://{$host}/terms",
    "https://{$host}/faq",
    "https://{$host}/support",
    "https://{$host}/documentation",
];
// =================================

echo "=================================================\n";
echo "CURENEX / CURENEXAI - Search Engine Notifier\n";
echo "=================================================\n\n";

// ---------- 1. Verify sitemap is reachable ----------
echo "[1/3] Checking sitemap: $sitemapUrl\n";
$ch = curl_init($sitemapUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'CurenexAI Sitemap Checker/2.0',
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200 && stripos($body, '<urlset') !== false) {
    $count = substr_count($body, '<url>');
    echo "  ✓ Sitemap OK ($code) - $count URLs listed\n\n";
} else {
    echo "  ✗ Sitemap not reachable or invalid (HTTP $code)\n\n";
}

// ---------- 2. IndexNow submission ----------
echo "[2/3] IndexNow submission (Bing, Yandex, Seznam, Naver, Yep)\n";

if ($INDEXNOW_KEY === '') {
    echo "  ⚠  Skipped: \$INDEXNOW_KEY is empty.\n";
    echo "     Generate one with: php -r \"echo bin2hex(random_bytes(16));\"\n";
    echo "     Then create <KEY>.txt at site root containing the key.\n\n";
} else {
    $payload = json_encode([
        'host'        => $host,
        'key'         => $INDEXNOW_KEY,
        'keyLocation' => $keyLocation . $INDEXNOW_KEY . '.txt',
        'urlList'     => $urlsToSubmit,
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.indexnow.org/IndexNow');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'CurenexAI IndexNow/2.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    // IndexNow status codes:
    //  200 = OK, 202 = Accepted (key not yet validated),
    //  400 = Bad request, 403 = Key not valid, 422 = URLs don't belong to host,
    //  429 = Too many requests
    if ($code === 200 || $code === 202) {
        echo "  ✓ Submitted " . count($urlsToSubmit) . " URLs (HTTP $code)\n\n";
    } else {
        echo "  ✗ Failed (HTTP $code) $err\n";
        if ($resp) echo "    Response: $resp\n";
        echo "\n";
    }
}

// ---------- 3. Manual reminders ----------
echo "[3/3] Manual steps still required\n";
echo "  • Google Search Console -> Sitemaps -> Resubmit:\n";
echo "    https://search.google.com/search-console\n";
echo "  • Bing Webmaster Tools (also accepts the sitemap directly):\n";
echo "    https://www.bing.com/webmasters\n";
echo "  • Yandex Webmaster:\n";
echo "    https://webmaster.yandex.com\n\n";

echo "=================================================\n";
echo "Why no Google/Bing 'ping' anymore?\n";
echo "  Google retired /ping?sitemap= in June 2023 (returns 404).\n";
echo "  Bing   retired /ping?sitemap= in May  2022 (returns 410).\n";
echo "  IndexNow is the modern replacement for Bing/Yandex/etc.\n";
echo "  Google only honours Search Console + sitemap link in robots.txt.\n";
echo "=================================================\n";
echo "CurenexAI - Decode Health, Deliver Cure\n";
