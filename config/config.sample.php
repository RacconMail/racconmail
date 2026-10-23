<?php
declare(strict_types=1);

/**
 * Raccon Mail Server Configuration Template
 * Rename this file to config.php in production.
 */
return [
    'db' => [
        'path' => __DIR__ . '/../raccoon_mail.db',
        'timeout_ms' => 15000,
    ],
    'imap' => [
        'host' => 'mail.racconmail.eu',
        'port' => 993,
        'flags' => '/imap/ssl/novalidate-cert',
        'user' => 'admin@racconmail.eu',
        'pass' => 'YOUR_SECURE_PASSWORD_HERE',
    ],
    'app' => [
        'name' => 'Raccon Mail',
        'url' => 'https://racconmail.eu',
        'env' => 'production',
        'debug' => false,
    ]
];
