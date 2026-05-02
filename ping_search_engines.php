<?php
/**
 * PING SEARCH ENGINES - CURENEX / CURENEXAI
 * Run this script after updating sitemap to notify search engines
 * Usage: php ping_search_engines.php
 * 
 * Keywords: Curenex, CurenexAI, Curenex AI
 */

$sitemapUrl = 'https://curenexai.com/sitemap.xml';

// Search engine ping URLs
$pingUrls = [
    // Google
    'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl),
    
    // Bing (also covers Yahoo)
    'https://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl),
    
    // IndexNow (Bing, Yandex, Seznam, Naver)
    // 'https://api.indexnow.org/indexnow?url=' . urlencode('https://curenexai.com/') . '&key=YOUR_KEY',
];

echo "=================================================\n";
echo "CURENEX / CURENEXAI - Search Engine Ping Tool\n";
echo "=================================================\n\n";
echo "Sitemap URL: $sitemapUrl\n\n";

$results = [];

foreach ($pingUrls as $url) {
    echo "Pinging: " . parse_url($url, PHP_URL_HOST) . "...\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'CurenexAI Sitemap Pinger/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $host = parse_url($url, PHP_URL_HOST);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "  ✓ SUCCESS ($httpCode)\n";
        $results[$host] = 'Success';
    } else {
        echo "  ✗ FAILED ($httpCode) - $error\n";
        $results[$host] = "Failed: $httpCode";
    }
}

echo "\n=================================================\n";
echo "SUMMARY\n";
echo "=================================================\n";
foreach ($results as $host => $status) {
    echo "$host: $status\n";
}

echo "\n";
echo "IMPORTANT: For best results, also submit your sitemap directly:\n";
echo "1. Google Search Console: https://search.google.com/search-console\n";
echo "2. Bing Webmaster Tools: https://www.bing.com/webmasters\n";
echo "3. Yandex Webmaster: https://webmaster.yandex.com\n";
echo "\n";
echo "CurenexAI - Decode Health, Deliver Cure\n";
