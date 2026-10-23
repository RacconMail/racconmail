<?php
/**
 * 🦝 Raccon Mail - Search Engine Instant Submission (IndexNow)
 * Submits all active public pages and hubs to Bing, Yandex, Yahoo, Seznam & DuckDuckGo
 */
declare(strict_types=1);

$key = '36eb429cbb0749a997d4c063b4f5be7e';
$urls = [
    'https://racconmail.eu/',
    'https://racconmail.eu/forum',
    'https://racconmail.eu/news',
    'https://racconmail.eu/stories',
    'https://racconmail.eu/auth',
    'https://racconmail.eu/legal/terms',
    'https://racconmail.eu/legal/privacy'
];

$payload = json_encode([
    'host' => 'racconmail.eu',
    'key' => $key,
    'keyLocation' => "https://racconmail.eu/{$key}.txt",
    'urlList' => $urls
], JSON_UNESCAPED_SLASHES);

$endpoints = [
    'https://api.indexnow.org/indexnow',
    'https://www.bing.com/indexnow',
    'https://yandex.com/indexnow'
];

foreach ($endpoints as $ep) {
    if (function_exists('curl_init')) {
        $ch = curl_init($ep);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "Submitted to {$ep}: HTTP {$code}\n";
    }
}
