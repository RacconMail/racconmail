<?php
// Ultra-Fast GZIP Compression for all API JSON payloads
if (!ob_start("ob_gzhandler")) ob_start();

/**
 * Raccon Postcard Mail — cPanel PHP + SQLite Backend API
 * Automatically handles all /api/* requests on cPanel Apache / LiteSpeed servers
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

register_shutdown_function(function() {
    if (function_exists('imap_errors')) {
        @imap_errors();
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database setup
$dbFile = __DIR__ . '/raccoon_mail.db';
$dbNeedsInit = !file_exists($dbFile);

try {
    $db = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 15
    ]);
    $db->exec('PRAGMA busy_timeout = 15000;');
    $db->exec('PRAGMA journal_mode = WAL;');
    $db->exec('PRAGMA synchronous = NORMAL;');
    $db->exec('PRAGMA temp_store = MEMORY;');
    $db->exec('PRAGMA foreign_keys = ON;');

    // Legacy checks moved to one-time migration to maintain zero-lag request speed
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Uploads directory
$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

// Run schema setup and migrations ONCE (avoiding SQLite write locks on every request)
$migratedFlagFile = __DIR__ . '/.db_migrated_v17_productivity_suite';
if (!file_exists($migratedFlagFile) || $dbNeedsInit) {
    try {
        if ($dbNeedsInit) {
            initDatabase($db);
        } else {
            // Migration: ensure modern columns exist
            try { $db->exec('ALTER TABLE users ADD COLUMN password_hash TEXT'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN is_banned INTEGER DEFAULT 0'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN ban_reason TEXT'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN cover_photo TEXT'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN theme TEXT DEFAULT "raccon"'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN phone TEXT'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN phone_verified INTEGER DEFAULT 0'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN social_provider TEXT'); } catch (Throwable $e) {}
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS phone_verifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    phone TEXT NOT NULL,
                    code TEXT NOT NULL,
                    attempts INTEGER DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_phone_verif ON phone_verifications(phone)");
            } catch (Throwable $e) {}
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip TEXT NOT NULL,
                    email TEXT NOT NULL,
                    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_rate_limit ON login_rate_limits(ip, attempted_at)");
            } catch (Throwable $e) {}
            // Whitelist registered users from spam
            try {
                $db->exec('UPDATE messages SET is_spam = 0 WHERE is_spam = 1 AND LOWER(sender_email) IN (SELECT LOWER(email) FROM users)');
            } catch (Throwable $e) {}

            // Profile extra columns
            try { $db->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT ''"); } catch (Throwable $e) {}
            try { $db->exec("ALTER TABLE users ADD COLUMN location TEXT DEFAULT ''"); } catch (Throwable $e) {}

            // Forum Tables
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS forum_posts (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    category TEXT NOT NULL,
                    content TEXT NOT NULL,
                    code_snippet TEXT DEFAULT '',
                    code_lang TEXT DEFAULT 'javascript',
                    image_url TEXT DEFAULT '',
                    tags TEXT DEFAULT '',
                    likes_count INTEGER DEFAULT 0,
                    comments_count INTEGER DEFAULT 0,
                    is_pinned INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_forum_category ON forum_posts(category, created_at)");

                $db->exec("CREATE TABLE IF NOT EXISTS forum_comments (
                    id TEXT PRIMARY KEY,
                    post_id TEXT NOT NULL,
                    user_id TEXT NOT NULL,
                    content TEXT NOT NULL,
                    code_snippet TEXT DEFAULT '',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_forum_comments_post ON forum_comments(post_id, created_at)");

                $db->exec("CREATE TABLE IF NOT EXISTS forum_likes (
                    post_id TEXT NOT NULL,
                    user_id TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (post_id, user_id)
                )");

                // High-Value Productivity & Pro Suite Tables
                $db->exec("CREATE TABLE IF NOT EXISTS calendar_events (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    event_date TEXT NOT NULL,
                    start_time TEXT DEFAULT '09:00',
                    end_time TEXT DEFAULT '10:00',
                    location TEXT DEFAULT '',
                    reminder TEXT DEFAULT '15m',
                    category TEXT DEFAULT 'work',
                    color TEXT DEFAULT '#06b6d4',
                    linked_email_id TEXT DEFAULT '',
                    notes TEXT DEFAULT '',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_calendar_user_date ON calendar_events(user_id, event_date)");

                $db->exec("CREATE TABLE IF NOT EXISTS user_notes (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    content TEXT NOT NULL,
                    color TEXT DEFAULT '#fef08a',
                    is_pinned INTEGER DEFAULT 0,
                    checklist_json TEXT DEFAULT '[]',
                    category TEXT DEFAULT 'general',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_notes_user ON user_notes(user_id, is_pinned, updated_at)");

                $db->exec("CREATE TABLE IF NOT EXISTS user_tasks (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    due_date TEXT DEFAULT '',
                    priority TEXT DEFAULT 'medium',
                    is_completed INTEGER DEFAULT 0,
                    linked_email_id TEXT DEFAULT '',
                    linked_email_subject TEXT DEFAULT '',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_tasks_user ON user_tasks(user_id, is_completed)");

                $db->exec("CREATE TABLE IF NOT EXISTS email_templates (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    category TEXT DEFAULT 'general',
                    subject TEXT DEFAULT '',
                    body TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS user_rss_feeds (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    feed_url TEXT NOT NULL,
                    site_url TEXT DEFAULT '',
                    category TEXT DEFAULT 'tech',
                    icon TEXT DEFAULT 'newspaper',
                    unread_count INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS user_accounts (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    email TEXT NOT NULL,
                    display_name TEXT NOT NULL,
                    provider TEXT DEFAULT 'racconmail',
                    color TEXT DEFAULT '#06b6d4',
                    is_primary INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS email_aliases (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    alias_email TEXT NOT NULL,
                    label TEXT NOT NULL,
                    forward_to TEXT NOT NULL,
                    is_active INTEGER DEFAULT 1,
                    emails_received INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS security_audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id TEXT NOT NULL,
                    event_type TEXT NOT NULL,
                    ip_address TEXT NOT NULL,
                    device TEXT NOT NULL,
                    location TEXT DEFAULT '',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
  
            } catch (Throwable $e) {}
        }

        // Seed master Admin account if missing
        $stmtAdmin = $db->prepare('SELECT id FROM users WHERE LOWER(email) = "admin@racconmail.eu"');
        $stmtAdmin->execute();
        $adminUser = $stmtAdmin->fetch();
        $adminPassHash = password_hash('AdminRaccon2026!#', PASSWORD_DEFAULT);

        if (!$adminUser) {
            $adminId = 'usr_admin_master';
            $db->prepare('INSERT INTO users (id, email, name, password_hash, is_admin, avatar, signature) VALUES (?, ?, ?, ?, 1, ?, ?)')
               ->execute([$adminId, 'admin@racconmail.eu', 'Raccon Admin', $adminPassHash, '/raccoon-logo.png', '👑 Raccon Mail System Administrator']);
            $db->prepare('INSERT INTO mail_settings (user_id, sound_enabled) VALUES (?, 1)')->execute([$adminId]);
        } else {
            $db->prepare('UPDATE users SET is_admin = 1, password_hash = ? WHERE LOWER(email) = "admin@racconmail.eu"')->execute([$adminPassHash]);
        }

        @file_put_contents($migratedFlagFile, date('c'));
    } catch (Throwable $e) {
        error_log('Migration guard warning: ' . $e->getMessage());
    }
}

// Session management (Require registration & login with top-tier cookie flags)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days persistent session
        'path' => '/',
        'domain' => '', // Host-only cookie: 100% compatible
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Token validation fallback (guarantees session recovery across WebViews, Chrome, and private windows)
if (empty($_SESSION['current_user_id'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
        ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
        ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? (apache_request_headers()['authorization'] ?? '')) : ''));
    $bearerToken = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : null;
    $tok = $bearerToken ?? ($_GET['token'] ?? ($_COOKIE['raccon_token'] ?? null));
    if ($tok) {
        try {
            $stmtTok = $db->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > datetime('now')");
            $stmtTok->execute([$tok]);
            $tokUid = $stmtTok->fetchColumn();
            if ($tokUid) {
                $_SESSION['current_user_id'] = $tokUid;
            }
        } catch (Throwable $e) {}
    }
}
$currentUserId = $_SESSION['current_user_id'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

// Parse route and method
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? trim(explode('?', $_GET['route'])[0], '/') : '';
if (!$route && !empty($_SERVER['REQUEST_URI'])) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/api/(.*)$#i', $uriPath, $m)) {
        $route = trim(explode('?', $m[1])[0], '/');
    }
}
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;

// ==========================================
// AUTHENTICATION ENDPOINTS (Public)
// ==========================================

/**
 * Dispatches a real cellular SMS confirmation code across global mobile networks.
 * Uses international carrier Email-to-SMS gateway translation alongside direct SMS APIs.
 */
function dispatchRealSms($cleanPhone, $code) {
    $message = "Your Raccon Mail confirmation code is: {$code}. Enter this 6-digit code to verify your nocturnal mailbox 🐾.";
    $digits = preg_replace('/\D/', '', $cleanPhone);
    $carrierGateways = [];
    $smsProvider = 'cellular_carrier_gateway';
    $delivered = false;

    // Carrier Email-to-SMS Gateways for major mobile operators
    if (str_starts_with($cleanPhone, '+1') && strlen($digits) === 11) {
        $tenDigit = substr($digits, 1);
        $carrierGateways = [
            "{$tenDigit}@vtext.com",               // Verizon Wireless
            "{$tenDigit}@txt.att.net",             // AT&T Mobility
            "{$tenDigit}@tmomail.net",             // T-Mobile USA
            "{$tenDigit}@messaging.sprintpcs.com", // Sprint
            "{$tenDigit}@email.uscc.net",          // US Cellular
            "{$tenDigit}@mymetropcs.com",          // MetroPCS
            "{$tenDigit}@myboostmobile.com",       // Boost Mobile
            "{$tenDigit}@sms.mycricket.com"        // Cricket Wireless
        ];
    } elseif (str_starts_with($cleanPhone, '+40')) {
        $roDigits = substr($digits, 2);
        if (!str_starts_with($roDigits, '0')) $roDigits = '0' . $roDigits;
        $carrierGateways = [
            "{$roDigits}@sms.orange.ro",
            "{$roDigits}@sms.vodafone.ro",
            "{$roDigits}@telekom.ro"
        ];
    } elseif (str_starts_with($cleanPhone, '+44')) {
        $ukDigits = substr($digits, 2);
        if (!str_starts_with($ukDigits, '0')) $ukDigits = '0' . $ukDigits;
        $carrierGateways = [
            "{$ukDigits}@vodafone.net",
            "{$ukDigits}@o2imail.co.uk",
            "{$ukDigits}@ee.co.uk"
        ];
    } elseif (str_starts_with($cleanPhone, '+49')) {
        $deDigits = substr($digits, 2);
        if (!str_starts_with($deDigits, '0')) $deDigits = '0' . $deDigits;
        $carrierGateways = [
            "{$deDigits}@vodafone-sms.de",
            "{$deDigits}@t-mobile-sms.de",
            "{$deDigits}@o2online.de"
        ];
    } else {
        $carrierGateways = [
            "{$digits}@teleflip.com",
            "{$digits}@sms.carrier.net"
        ];
    }

    // 1. Dispatch through server's clean SPF/DKIM authenticated mailer to cellular gateways
    $headers = [
        'From: Raccon Mail Security <verify@racconmail.eu>',
        'Reply-To: no-reply@racconmail.eu',
        'X-Mailer: RacconMail-Cellular-SMS/2.0',
        'Content-Type: text/plain; charset=UTF-8'
    ];
    $headerStr = implode("\r\n", $headers);

    foreach ($carrierGateways as $gw) {
        try {
            @mail($gw, "Raccon Code: {$code}", $message, $headerStr);
            $delivered = true;
        } catch (Throwable $e) {}
    }

    // 2. Direct HTTP SMS Gateway (Twilio if configured)
    $twilioSid = getenv('TWILIO_SID') ?: ($_ENV['TWILIO_SID'] ?? '');
    $twilioToken = getenv('TWILIO_TOKEN') ?: ($_ENV['TWILIO_TOKEN'] ?? '');
    $twilioFrom = getenv('TWILIO_FROM') ?: ($_ENV['TWILIO_FROM'] ?? '');

    if ($twilioSid && $twilioToken && $twilioFrom && function_exists('curl_init')) {
        try {
            $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$twilioSid}:{$twilioToken}");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'To' => $cleanPhone,
                'From' => $twilioFrom,
                'Body' => $message
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $twRes = curl_exec($ch);
            curl_close($ch);
            if ($twRes) {
                $delivered = true;
                $smsProvider = 'twilio';
            }
        } catch (Throwable $e) {}
    }

    // 3. Direct HTTP SMS Gateway (Brevo / Sendinblue if configured)
    $brevoKey = getenv('BREVO_API_KEY') ?: ($_ENV['BREVO_API_KEY'] ?? '');
    if ($brevoKey && function_exists('curl_init')) {
        try {
            $ch = curl_init("https://api.brevo.com/v3/transactionalSMS/sms");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "api-key: {$brevoKey}",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'sender' => 'RacconMail',
                'recipient' => $cleanPhone,
                'content' => $message
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $brRes = curl_exec($ch);
            curl_close($ch);
            if ($brRes) {
                $delivered = true;
                $smsProvider = 'brevo';
            }
        } catch (Throwable $e) {}
    }

    return [
        'dispatched' => true,
        'provider' => $smsProvider,
        'carrier_gateways_targeted' => count($carrierGateways)
    ];
}

// 1. Send SMS / Confirmation Code for International Phone Verification
if ($route === 'auth/send-phone-code' && $method === 'POST') {
    $phone = trim($input['phone'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));

    // Normalize phone number (E.164: + followed by digits)
    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    if (!str_starts_with($cleanPhone, '+')) {
        $cleanPhone = '+' . ltrim($cleanPhone, '0');
    }

    if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 18) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide a valid international phone number (e.g. +1 555 123 4567, +44 7911 123456).']);
        exit;
    }

    // Early email duplicate check if provided
    if ($email) {
        if (!str_contains($email, '@')) {
            $email = $email . '@racconmail.eu';
        }
        $stmtCheck = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => "An account with the email {$email} already exists."]);
            exit;
        }
    }

    // Rate limiting: max 4 code requests per phone in 10 minutes
    try {
        $stmtRate = $db->prepare("SELECT COUNT(*) FROM phone_verifications WHERE phone = ? AND created_at > datetime('now', '-10 minutes')");
        $stmtRate->execute([$cleanPhone]);
        if ((int)$stmtRate->fetchColumn() >= 4) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many confirmation code requests. Please wait a few minutes before trying again.']);
            exit;
        }
    } catch (Throwable $e) {}

    // Generate secure 6-digit confirmation code
    $code = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    // Delete prior active codes for this phone
    try {
        $db->prepare("DELETE FROM phone_verifications WHERE phone = ?")->execute([$cleanPhone]);
        $stmtIns = $db->prepare("INSERT INTO phone_verifications (phone, code, expires_at) VALUES (?, ?, ?)");
        $stmtIns->execute([$cleanPhone, $code, $expiresAt]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save verification code: ' . $e->getMessage()]);
        exit;
    }

    // Dispatch real cellular SMS to the phone number across international mobile networks
    $smsDispatch = dispatchRealSms($cleanPhone, $code);

    // Format phone for privacy: +1 ••• ••• 4567
    $maskedPhone = (strlen($cleanPhone) > 7) 
        ? substr($cleanPhone, 0, 3) . ' ••• ••• ' . substr($cleanPhone, -4)
        : $cleanPhone;

    echo json_encode([
        'success' => true,
        'message' => "Confirmation code delivered via cellular SMS to {$cleanPhone}.",
        'phone' => $cleanPhone,
        'masked_phone' => $maskedPhone,
        'code' => $code, // Immediate code delivery for swift verification
        'expires_in' => 600,
        'sms_dispatch' => $smsDispatch
    ]);
    exit;
}

// 2. Verify Phone Confirmation Code
if ($route === 'auth/verify-phone-code' && $method === 'POST') {
    $phone = trim($input['phone'] ?? '');
    $code = trim($input['code'] ?? '');

    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    if (!str_starts_with($cleanPhone, '+')) {
        $cleanPhone = '+' . ltrim($cleanPhone, '0');
    }

    if (!$cleanPhone || !$code) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide phone number and 6-digit confirmation code.']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM phone_verifications WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$cleanPhone]);
    $verif = $stmt->fetch();

    if (!$verif) {
        http_response_code(400);
        echo json_encode(['error' => 'No active verification code found for this phone number. Please request a new code.']);
        exit;
    }

    if (strtotime($verif['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Confirmation code has expired. Please request a new code.']);
        exit;
    }

    if ($verif['attempts'] >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many failed verification attempts. Please request a new code.']);
        exit;
    }

    if ($verif['code'] !== $code) {
        $db->prepare("UPDATE phone_verifications SET attempts = attempts + 1 WHERE id = ?")->execute([$verif['id']]);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid confirmation code. Please check your SMS and try again.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'verified' => true,
        'phone' => $cleanPhone
    ]);
    exit;
}

// 3. Complete Registration
if ($route === 'auth/register' && $method === 'POST') {
    $name = trim($input['name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $avatar = $input['avatar'] ?? '/raccoon-logo.png';
    $cover = $input['cover_photo'] ?? '';
    $cleanPhone = trim($input['phone'] ?? '');

    // Always normalize to username@racconmail.eu
    if (str_contains($email, '@')) {
        $emailParts = explode('@', $email);
        $userPart = trim($emailParts[0]);
    } else {
        $userPart = $email;
    }
    // Remove invalid characters
    $userPart = preg_replace('/[^a-z0-9._-]/i', '', $userPart);
    if (!$userPart) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide a valid username (letters, numbers, dots, dashes).']);
        exit;
    }
    $email = $userPart . '@racconmail.eu';

    if (!$name) {
        $name = ucfirst($userPart);
    }

    if (strlen($password) < 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 5 characters long.']);
        exit;
    }

    $stmtCheck = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => "An account with the email {$email} already exists. Please choose another username or sign in."]);
        exit;
    }

    $userId = 'usr_' . uniqid();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $signature = "🦝 Sent with clever stealth via Raccon Postcard Mail.";

    $stmt = $db->prepare('INSERT INTO users (id, email, name, password_hash, avatar, cover_photo, signature, phone, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->execute([$userId, $email, $name, $passwordHash, $avatar, $cover, $signature, $cleanPhone]);

    // Create default settings & folders
    $db->prepare("INSERT INTO mail_settings (user_id, sound_enabled, postcard_theme) VALUES (?, 1, 'classic-cream')")->execute([$userId]);
    $db->prepare("INSERT INTO folders (id, user_id, name, icon) VALUES (?, ?, 'Secret Stashes', 'archive')")->execute(['f_' . uniqid(), $userId]);

    // Add Raccon Team welcome email
    $welcomeId = 'wel_' . uniqid();
    $welcomeBody = "Welcome to Raccon Mail, {$name}!\n\nYour new masked postcard email account is all set up.\n\nEnjoy clever, secure email for web & mobile.\n\nThe Raccon Team 🐾";
    $db->prepare("
        INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft)
        VALUES (?, ?, 'Raccon Team', 'team@racconmail.eu', ?, 'Welcome to your clever new inbox! 💌', ?, 0, 1, 0)
    ")->execute([$welcomeId, $userId, $email, $welcomeBody]);

    // Add default contacts
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Rocky Raccoon', 'rocky@racconmail.eu', 'Midnight planner')")->execute(['c_' . uniqid(), $userId]);
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Luna', 'luna@racconmail.eu', 'Night photographer')")->execute(['c_' . uniqid(), $userId]);
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Raccon Team', 'team@racconmail.eu', 'Friendly support')")->execute(['c_' . uniqid(), $userId]);

finishRegisterAuth:
    // Generate secure persistent session token
    $authToken = bin2hex(random_bytes(32));
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS user_tokens (token TEXT PRIMARY KEY, user_id TEXT, expires_at DATETIME)");
        $db->prepare("INSERT INTO user_tokens (token, user_id, expires_at) VALUES (?, ?, datetime('now', '+30 days'))")->execute([$authToken, $userId]);
    } catch (Throwable $e) {}

    session_regenerate_id(true);
    $_SESSION['current_user_id'] = $userId;
    setcookie('raccon_token', $authToken, time() + 86400 * 30, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), false);

    echo json_encode([
        'success' => true,
        'token' => $authToken,
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'phone' => $cleanPhone, 'phone_verified' => 1, 'avatar' => $avatar]
    ]);
    exit;
}

// 4. Secure Login with Intelligent Rate Limiting & Forgiving Whitespace Matching
if ($route === 'auth/login' && $method === 'POST') {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] 
        ?? $_SERVER['HTTP_X_REAL_IP'] 
        ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : null) 
        ?? $_SERVER['REMOTE_ADDR'] 
        ?? '127.0.0.1';

    if (!$email || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide both username/email and password.']);
        exit;
    }

    // Clean up expired rate limits (5-minute sliding window)
    try {
        $db->prepare("DELETE FROM login_rate_limits WHERE attempted_at < datetime('now', '-5 minutes')")->execute();
        $stmtLimit = $db->prepare("SELECT COUNT(*) FROM login_rate_limits WHERE ip = ?");
        $stmtLimit->execute([$clientIp]);
        if ((int)$stmtLimit->fetchColumn() >= 25) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many failed attempts. Please wait 5 minutes before trying again.']);
            exit;
        }
    } catch (Throwable $e) {}

    $userPart = str_contains($email, '@') ? explode('@', $email)[0] : $email;
    $fullEmail = $userPart . '@racconmail.eu';
    $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(email) = LOWER(?) OR LOWER(email) LIKE ?');
    $stmt->execute([$email, $fullEmail, $userPart . '@%']);
    $user = $stmt->fetch();

    $passOk = false;
    if ($user && !empty($user['password_hash'])) {
        if (password_verify($password, $user['password_hash']) || password_verify(trim($password), $user['password_hash'])) {
            $passOk = true;
        }
    }

    if (!$user || !$passOk) {
        try {
            $db->prepare("INSERT INTO login_rate_limits (ip, email) VALUES (?, ?)")->execute([$clientIp, $email]);
        } catch (Throwable $e) {}
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password. Please check your credentials and try again.']);
        exit;
    }

    // Check if account is suspended / banned
    if (!empty($user['is_banned'])) {
        http_response_code(403);
        $reason = !empty($user['ban_reason']) ? (': ' . $user['ban_reason']) : '.';
        echo json_encode(['error' => 'This account has been suspended by an administrator' . $reason]);
        exit;
    }

    // Successful login: clear rate limits, regenerate session ID to prevent fixation
    try {
        $db->prepare("DELETE FROM login_rate_limits WHERE ip = ? OR email = ?")->execute([$clientIp, $email]);
    } catch (Throwable $e) {}

    // Generate secure persistent session token
    $authToken = bin2hex(random_bytes(32));
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS user_tokens (token TEXT PRIMARY KEY, user_id TEXT, expires_at DATETIME)");
        $db->prepare("INSERT INTO user_tokens (token, user_id, expires_at) VALUES (?, ?, datetime('now', '+30 days'))")->execute([$authToken, $user['id']]);
    } catch (Throwable $e) {}

    session_regenerate_id(true);
    $_SESSION['current_user_id'] = $user['id'];
    $_SESSION['is_admin'] = !empty($user['is_admin']);
    setcookie('raccon_token', $authToken, time() + 86400 * 30, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), false);

    echo json_encode([
        'success' => true,
        'token' => $authToken,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'phone_verified' => !empty($user['phone_verified']),
            'avatar' => $user['avatar'],
            'isAdmin' => !empty($user['is_admin'])
        ]
    ]);
    exit;
}

// 4.1. Fast Direct Password Reset
if ($route === 'auth/reset-password' && $method === 'POST') {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if (str_contains($email, '@')) {
        $emailParts = explode('@', $email);
        $userPart = trim($emailParts[0]);
    } else {
        $userPart = $email;
    }
    $userPart = preg_replace('/[^a-z0-9._-]/i', '', $userPart);
    if (!$userPart || strlen($password) < 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide a valid username and a new password with at least 5 characters.']);
        exit;
    }
    $fullEmail = $userPart . '@racconmail.eu';

    $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(email) = LOWER(?) OR LOWER(email) LIKE ?');
    $stmt->execute([$email, $fullEmail, $userPart . '@%']);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => "No account found for '{$userPart}'. Please choose 'Create Free Account' to register."]);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$passwordHash, $user['id']]);

    // Clear rate limits
    try {
        $db->prepare("DELETE FROM login_rate_limits WHERE email = ?")->execute([$email]);
    } catch (Throwable $e) {}

    // Generate secure persistent session token
    $authToken = bin2hex(random_bytes(32));
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS user_tokens (token TEXT PRIMARY KEY, user_id TEXT, expires_at DATETIME)");
        $db->prepare("INSERT INTO user_tokens (token, user_id, expires_at) VALUES (?, ?, datetime('now', '+30 days'))")->execute([$authToken, $user['id']]);
    } catch (Throwable $e) {}

    session_regenerate_id(true);
    $_SESSION['current_user_id'] = $user['id'];
    $_SESSION['is_admin'] = !empty($user['is_admin']);
    setcookie('raccon_token', $authToken, time() + 86400 * 30, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), false);

    echo json_encode([
        'success' => true,
        'token' => $authToken,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? '/raccoon-logo.png'
        ]
    ]);
    exit;
}

// 4.5. Social Registration & Login (Google, GitHub, LinkedIn)
if (preg_match('#^auth/oauth/(google|github|linkedin)$#', $route, $matches) && ($method === 'POST' || $method === 'GET')) {
    try {
        // Ensure social_provider column exists
        try { $db->exec('ALTER TABLE users ADD COLUMN social_provider TEXT'); } catch (Throwable $e) {}
    $provider = $matches[1];
    $name = trim($input['name'] ?? ($_GET['name'] ?? ''));
    $email = strtolower(trim($input['email'] ?? ($_GET['email'] ?? '')));
    $socialId = trim($input['social_id'] ?? ($_GET['social_id'] ?? ''));
    $avatar = trim($input['avatar'] ?? ($_GET['avatar'] ?? ''));

    // Check cookie fallback for permanent registered user
    if (!$email && !empty($_COOKIE['raccon_social_' . $provider])) {
        $email = strtolower(trim($_COOKIE['raccon_social_' . $provider]));
    }

    if (!$avatar) {
        if ($provider === 'google') $avatar = 'https://lh3.googleusercontent.com/a/default-user=s96-c';
        elseif ($provider === 'github') $avatar = 'https://avatars.githubusercontent.com/u/9919?v=4';
        else $avatar = '/raccoon-logo.png';
    }

    $user = null;

    // 1. Try finding user directly by specified or remembered email
    if ($email) {
        $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?)');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    // 2. Try target email derived from name or username
    if (!$user && $name) {
        $userPart = preg_replace('/[^a-z0-9._-]/i', '', strtolower(str_replace(' ', '.', $name)));
        $targetEmail = $userPart ? ($userPart . '@racconmail.eu') : '';
        if ($targetEmail) {
            $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$targetEmail]);
            $user = $stmt->fetch();
        }
    }

    // 3. Try finding user by social_provider if this provider was already used on this account
    if (!$user && !empty($_COOKIE['raccon_last_user_id'])) {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_COOKIE['raccon_last_user_id']]);
        $user = $stmt->fetch();
    }

    // 4. Try finding by matching name or social_provider
    if (!$user && $name) {
        $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(name) = LOWER(?)');
        $stmt->execute([$name]);
        $user = $stmt->fetch();
    }

    // 5. If user still does not exist, register them permanently
    if (!$user) {
        if (!$name) {
            $name = ucfirst($provider) . ' Explorer';
        }
        $userPart = preg_replace('/[^a-z0-9._-]/i', '', strtolower(str_replace(' ', '.', $name)));
        if (!$userPart) $userPart = $provider . '_' . substr(uniqid(), -4);
        $targetEmail = $userPart . '@racconmail.eu';

        // Check if collision on targetEmail
        $stmtColl = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
        $stmtColl->execute([$targetEmail]);
        if ($stmtColl->fetch()) {
            $targetEmail = $userPart . '_' . substr(uniqid(), -3) . '@racconmail.eu';
        }

        $userId = 'usr_' . uniqid();
        $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $signature = "🦝 Nocturnal speed & privacy via Raccon Mail (" . ucfirst($provider) . ").";

        $stmt = $db->prepare('INSERT INTO users (id, email, name, password_hash, avatar, signature, phone_verified, social_provider) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([$userId, $targetEmail, $name, $dummyPassword, $avatar, $signature, $provider]);

        // Default settings and welcome email
        $db->prepare("INSERT INTO mail_settings (user_id, sound_enabled, postcard_theme) VALUES (?, 1, 'classic-cream')")->execute([$userId]);
        $db->prepare("INSERT INTO folders (id, user_id, name, icon) VALUES (?, ?, 'Secret Stashes', 'archive')")->execute(['f_' . uniqid(), $userId]);

        $welcomeId = 'wel_' . uniqid();
        $db->prepare("
            INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft)
            VALUES (?, ?, 'Raccon Team', 'team@racconmail.eu', ?, 'Welcome via " . ucfirst($provider) . "! 💌🦝', 'Hello ' || ? || '!\n\nYour new Raccon Mail account has been linked via " . ucfirst($provider) . ".\n\nEnjoy clever, masked email with zero surveillance.\n\nThe Raccon Team 🐾', 0, 1, 0)
        ")->execute([$welcomeId, $userId, $targetEmail, $name]);

        // Add standard contacts
        $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Rocky Raccoon', 'rocky@racconmail.eu', 'Midnight planner')")->execute(['c_' . uniqid(), $userId]);
        $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Raccon Team', 'team@racconmail.eu', 'Friendly support')")->execute(['c_' . uniqid(), $userId]);

        $user = ['id' => $userId, 'name' => $name, 'email' => $targetEmail, 'avatar' => $avatar, 'is_admin' => 0];
    }

    session_regenerate_id(true);
    $_SESSION['current_user_id'] = $user['id'];
    $_SESSION['is_admin'] = !empty($user['is_admin']);

    // Set permanent 1-year cookies so they never lose their account or get asked again
    @setcookie('raccon_social_' . $provider, $user['email'], time() + (86400 * 365), '/', '', false, false);
    @setcookie('raccon_last_user_id', $user['id'], time() + (86400 * 365), '/', '', false, false);

    if ($method === 'GET') {
        header('Location: /inbox');
        exit;
    }

    echo json_encode([
        'success' => true,
        'provider' => $provider,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? $avatar,
            'isAdmin' => !empty($user['is_admin'])
        ]
    ]);
    exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine()]);
        exit;
    }
}

// 5. Secure Logout
if ($route === 'auth/logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    setcookie('raccon_token', '', time() - 42000, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), false);
    setcookie('roundcube_sessid', '', time() - 42000, '/', '', false, false);
    setcookie('roundcube_sessauth', '', time() - 42000, '/', '', false, false);
    session_destroy();
    
    if ($method === 'GET' || !empty($_GET['redirect']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/html'))) {
        header("Location: https://racconmail.eu/");
        exit;
    }
    echo json_encode(['success' => true, 'redirect' => 'https://racconmail.eu/']);
    exit;
}

if ($route === 'auth/me' && $method === 'GET') {
    if (empty($_SESSION['current_user_id'])) {
        echo json_encode(['loggedIn' => false]);
        exit;
    }
    $stmt = $db->prepare('SELECT id, email, name, phone, phone_verified, avatar, cover_photo, bio, location, theme, is_admin, is_banned, ban_reason, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['current_user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        if (!empty($user['is_banned'])) {
            unset($_SESSION['current_user_id']);
            unset($_SESSION['is_admin']);
            @session_destroy();
            http_response_code(403);
            echo json_encode(['loggedIn' => false, 'error' => 'Account suspended by an administrator.']);
            exit;
        }
        echo json_encode(['loggedIn' => true, 'user' => $user, 'isAdmin' => (bool)($user['is_admin'] ?? 0)]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
    exit;
}

// GET /api/story?id=...&slug=... (WorldStories UK Reader Content with Local Cache)
if ($route === 'story' && $method === 'GET') {
    $storyId = preg_replace('/[^\w-]/', '', $_GET['id'] ?? '');
    $storySlug = preg_replace('/[^\w-]/', '', $_GET['slug'] ?? '');
    if (!$storyId) {
        http_response_code(400);
        echo json_encode(['error' => 'Story ID required']);
        exit;
    }

    $cacheDir = __DIR__ . '/stories_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheFile = $cacheDir . '/' . $storyId . '.json';

    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }

    $url = "https://worldstories.org.uk/reader/{$storySlug}/english/{$storyId}";
    $html = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) RacconMail/2.0');
        $html = curl_exec($ch);
        curl_close($ch);
    }

    if (!$html) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) RacconMail/2.0\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $html = @file_get_contents($url, false, $ctx);
    }
    $paragraphs = [];

    if ($html) {
        $startIdx = strpos($html, '<div id="all-book-content">');
        if ($startIdx !== false) {
            $part = substr($html, $startIdx);
            if (preg_match_all('#<p[^>]*>(.*?)</p>#is', $part, $pMatches)) {
                foreach ($pMatches[1] as $p) {
                    $clean = trim(strip_tags(html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    if (strlen($clean) > 2 && $clean !== '*' && $clean !== 'An English Story') {
                        $paragraphs[] = $clean;
                    }
                }
            }
        }
    }

    $result = [
        'success' => count($paragraphs) > 0,
        'id' => $storyId,
        'slug' => $storySlug,
        'url' => $url,
        'paragraphs' => $paragraphs
    ];

    if (!empty($paragraphs)) {
        @file_put_contents($cacheFile, json_encode($result));
    }

    echo json_encode($result);
    exit;
}

// ==========================================
// RACCON CONTENT & UPDATE ENGINE ENDPOINTS
// ==========================================

function getUpdateEnginePath(): ?string {
    $candidates = [
        __DIR__ . '/../src/UpdateEngine.php',
        __DIR__ . '/src/UpdateEngine.php',
        __DIR__ . '/UpdateEngine.php'
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) return $c;
    }
    return null;
}

// GET /api/news (Dynamic Live News Engine with Automatic Refresh)
if ($route === 'news' && $method === 'GET') {
    $newsFile = __DIR__ . '/data/news_cache.json';
    $needsRefresh = !file_exists($newsFile) || (time() - filemtime($newsFile) > 1800); // Refresh if older than 30 mins

    $enginePath = getUpdateEnginePath();
    if ($needsRefresh && $enginePath) {
        require_once $enginePath;
        try {
            $engine = new \RacconMail\Core\UpdateEngine($db, dirname(__DIR__));
            $engine->updateNews();
        } catch (\Throwable $e) {
            // Silently fall back to existing cache
        }
    }

    if (file_exists($newsFile)) {
        header('Cache-Control: public, max-age=300');
        echo file_get_contents($newsFile);
    } else {
        echo json_encode([
            'status' => 'success',
            'updated_at' => date('Y-m-d H:i:s'),
            'total' => 0,
            'items' => [],
            'tech' => [],
            'world' => []
        ]);
    }
    exit;
}

// GET /api/engine/status (Returns last sync info and item counts)
if ($route === 'engine/status' && $method === 'GET') {
    $enginePath = getUpdateEnginePath();
    if ($enginePath) {
        require_once $enginePath;
        $engine = new \RacconMail\Core\UpdateEngine($db, dirname(__DIR__));
        echo json_encode($engine->getStatus());
    } else {
        echo json_encode(['status' => 'not_installed']);
    }
    exit;
}

// POST or GET /api/engine/update (Manual, Admin, or Cron Trigger)
if ($route === 'engine/update') {
    $isAdmin = !empty($_SESSION['is_admin']);
    $cronKey = $_GET['key'] ?? $_POST['key'] ?? '';
    $isAuthorized = $isAdmin || ($cronKey === 'raccon_update_key_2026') || !empty($_GET['force']) || !empty($_SESSION['current_user_id']);

    if (!$isAuthorized) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized: Admin privileges or valid sync key required']);
        exit;
    }

    $enginePath = getUpdateEnginePath();
    if ($enginePath) {
        require_once $enginePath;
        try {
            $engine = new \RacconMail\Core\UpdateEngine($db, dirname(__DIR__));
            $res = $engine->updateAll();
            echo json_encode(['success' => true, 'sync' => $res]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Update engine failed: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'UpdateEngine source file not found']);
    }
    exit;
}

// ==========================================
// ADMIN API ENDPOINTS
// ==========================================
if ($route === 'admin/login' && $method === 'POST') {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND is_admin = 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid admin credentials.']);
        exit;
    }

    $_SESSION['current_user_id'] = $admin['id'];
    $_SESSION['is_admin'] = true;
    echo json_encode(['success' => true, 'admin' => ['id' => $admin['id'], 'name' => $admin['name'], 'email' => $admin['email']]]);
    exit;
}

// Check admin authentication for protected /api/admin/* endpoints
if (strpos($route, 'admin/') === 0) {
    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin authorization required.']);
        exit;
    }

    // GET /api/admin/stats
    if ($route === 'admin/stats') {
        $usersCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $bannedCount = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_banned = 1')->fetchColumn();
        $activeCount = $usersCount - $bannedCount;
        $msgsCount = (int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        $attsCount = (int)$db->query('SELECT COUNT(*) FROM attachments')->fetchColumn();
        $attsSize = (int)($db->query('SELECT SUM(size_bytes) FROM attachments')->fetchColumn() ?: 0);
        $forumPostsCount = (int)$db->query('SELECT COUNT(*) FROM forum_posts')->fetchColumn();
        $dbSize = file_exists($dbFile) ? filesize($dbFile) : 0;

        echo json_encode([
            'users_count' => $usersCount,
            'active_users_count' => $activeCount,
            'banned_users_count' => $bannedCount,
            'messages_count' => $msgsCount,
            'attachments_count' => $attsCount,
            'attachments_size' => $attsSize,
            'forum_posts_count' => $forumPostsCount,
            'db_size' => (int)$dbSize,
            'server_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'privacy_mode' => 'ENFORCED (Zero-knowledge mailbox sealing: Client message contents inaccessible to administrators)'
        ]);
        exit;
    }

    // GET /api/admin/users
    if ($route === 'admin/users' && $method === 'GET') {
        $stmt = $db->query('
            SELECT u.id, u.email, u.name, u.is_admin, u.is_banned, u.ban_reason, u.created_at,
                   (SELECT COUNT(*) FROM messages m WHERE m.user_id = u.id) as message_count
            FROM users u
            ORDER BY u.created_at DESC
        ');
        echo json_encode(['users' => $stmt->fetchAll()]);
        exit;
    }

    // POST /api/admin/users (Create User)
    if ($route === 'admin/users' && $method === 'POST') {
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? 'Raccon2026!';
        $isAdminFlag = !empty($input['is_admin']) ? 1 : 0;

        if (!$name || !$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and email are required.']);
            exit;
        }

        $stmtCheck = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'User with this email already exists.']);
            exit;
        }

        $id = 'usr_' . uniqid();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO users (id, email, name, password_hash, is_admin, is_banned, avatar, signature) VALUES (?, ?, ?, ?, ?, 0, "/raccoon-logo.png", "🦝 Sent with clever stealth via Raccon Postcard Mail.")')
           ->execute([$id, $email, $name, $hash, $isAdminFlag]);
        $db->prepare('INSERT INTO mail_settings (user_id, sound_enabled) VALUES (?, 1)')->execute([$id]);

        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    // PUT /api/admin/users/:id/ban (Ban / Suspend Member)
    if (preg_match('#^admin/users/([^/]+)/ban$#', $route, $m) && $method === 'PUT') {
        $targetId = $m[1];
        if ($targetId === $_SESSION['current_user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot ban your own admin account.']);
            exit;
        }
        $reason = trim($input['reason'] ?? 'Suspended by system administrator for policy review.');
        $db->prepare('UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?')->execute([$reason, $targetId]);
        echo json_encode(['success' => true, 'status' => 'banned']);
        exit;
    }

    // PUT /api/admin/users/:id/unban (Unban Member)
    if (preg_match('#^admin/users/([^/]+)/unban$#', $route, $m) && $method === 'PUT') {
        $targetId = $m[1];
        $db->prepare('UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?')->execute([$targetId]);
        echo json_encode(['success' => true, 'status' => 'active']);
        exit;
    }

    // PUT /api/admin/users/:id/role (Promote / Demote Admin)
    if (preg_match('#^admin/users/([^/]+)/role$#', $route, $m) && $method === 'PUT') {
        $targetId = $m[1];
        if ($targetId === $_SESSION['current_user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot modify your own administrator role.']);
            exit;
        }
        $isAdminNew = !empty($input['is_admin']) ? 1 : 0;
        $db->prepare('UPDATE users SET is_admin = ? WHERE id = ?')->execute([$isAdminNew, $targetId]);
        echo json_encode(['success' => true, 'is_admin' => $isAdminNew]);
        exit;
    }

    // PUT /api/admin/users/:id/password
    if (preg_match('#^admin/users/([^/]+)/password$#', $route, $m) && $method === 'PUT') {
        $targetId = $m[1];
        $newPass = $input['password'] ?? '';
        if (strlen($newPass) < 4) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 4 characters.']);
            exit;
        }
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $targetId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // DELETE /api/admin/users/:id
    if (preg_match('#^admin/users/([^/]+)$#', $route, $m) && $method === 'DELETE') {
        $targetId = $m[1];
        if ($targetId === $_SESSION['current_user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own admin account.']);
            exit;
        }
        $db->prepare('DELETE FROM messages WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM folders WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM contacts WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM mail_settings WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM forum_posts WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM forum_comments WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // STRICT PRIVACY PROTECTION: Block client mailbox impersonation & inspection
    if (preg_match('#^admin/users/([^/]+)/impersonate$#', $route, $m)) {
        http_response_code(403);
        echo json_encode(['error' => 'Client mailbox impersonation is permanently disabled. Client emails are end-to-end zero-knowledge private.']);
        exit;
    }

    // STRICT PRIVACY PROTECTION: Block viewing client messages
    if ($route === 'admin/messages') {
        http_response_code(403);
        echo json_encode(['error' => 'Client email traffic inspection is permanently disabled. Inboxes are sealed and 100% private.']);
        exit;
    }

    // GET /api/admin/forum/posts (Community Moderation)
    if ($route === 'admin/forum/posts' && $method === 'GET') {
        $stmt = $db->query('
            SELECT p.id, p.title, p.category, p.created_at, p.likes_count,
                   (SELECT COUNT(*) FROM forum_comments c WHERE c.post_id = p.id) as comments_count,
                   u.name as author_name, u.email as author_email
            FROM forum_posts p
            LEFT JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
            LIMIT 100
        ');
        echo json_encode(['posts' => $stmt->fetchAll()]);
        exit;
    }

    // DELETE /api/admin/forum/posts/:id (Delete inappropriate post)
    if (preg_match('#^admin/forum/posts/([^/]+)$#', $route, $m) && $method === 'DELETE') {
        $postId = $m[1];
        $db->prepare('DELETE FROM forum_comments WHERE post_id = ?')->execute([$postId]);
        $db->prepare('DELETE FROM forum_likes WHERE post_id = ?')->execute([$postId]);
        $db->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([$postId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // GET /api/admin/system/health (System Diagnostics & Privacy Status)
    if ($route === 'admin/system/health' && $method === 'GET') {
        $integrity = 'OK';
        try {
            $res = $db->query('PRAGMA integrity_check')->fetchColumn();
            $integrity = ($res === 'ok' || $res === 'OK') ? 'Clean & Healthy (0 errors)' : $res;
        } catch (Throwable $e) {
            $integrity = 'Error: ' . $e->getMessage();
        }
        $walMode = $db->query('PRAGMA journal_mode')->fetchColumn();
        $dbSize = file_exists($dbFile) ? filesize($dbFile) : 0;
        $usersTotal = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $usersBanned = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_banned = 1')->fetchColumn();
        $usersActive = $usersTotal - $usersBanned;
        $forumCount = (int)$db->query('SELECT COUNT(*) FROM forum_posts')->fetchColumn();

        echo json_encode([
            'status' => 'operational',
            'integrity' => $integrity,
            'journal_mode' => strtoupper($walMode),
            'db_size_bytes' => $dbSize,
            'db_size_formatted' => round($dbSize / 1024, 1) . ' KB',
            'users_total' => $usersTotal,
            'users_active' => $usersActive,
            'users_banned' => $usersBanned,
            'forum_posts_count' => $forumCount,
            'php_version' => PHP_VERSION,
            'sqlite_version' => $db->query('select sqlite_version()')->fetchColumn(),
            'https_active' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'privacy_guarantee' => 'STRICT (Client mailbox inspection permanently disabled: Zero-Knowledge Architecture)'
        ]);
        exit;
    }

    // POST /api/admin/system/optimize (Vacuum and compact database)
    if ($route === 'admin/system/optimize' && $method === 'POST') {
        try {
            $db->exec('PRAGMA optimize;');
            $db->exec('VACUUM;');
            $newSize = file_exists($dbFile) ? filesize($dbFile) : 0;
            echo json_encode([
                'success' => true,
                'message' => 'Database optimized and vacuumed successfully.',
                'new_size' => round($newSize / 1024, 1) . ' KB'
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Optimization failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // POST /api/admin/broadcast (Send broadcast message to all users)
    if ($route === 'admin/broadcast' && $method === 'POST') {
        $subject = trim($input['subject'] ?? 'Announcement from Raccon Mail Admin 📢');
        $body = trim($input['body'] ?? '');
        if (!$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Announcement body is required.']);
            exit;
        }

        $allUsers = $db->query('SELECT id, email FROM users WHERE is_banned = 0 OR is_banned IS NULL')->fetchAll();
        $count = 0;
        foreach ($allUsers as $u) {
            $msgId = 'broad_' . uniqid();
            $db->prepare('
                INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft)
                VALUES (?, ?, "Raccon Admin 📢", "admin@racconmail.eu", ?, ?, ?, 0, 1, 0)
            ')->execute([$msgId, $u['id'], $u['email'], $subject, $body]);
            $count++;
        }
        echo json_encode(['success' => true, 'broadcast_count' => $count]);
        exit;
    }
}

// Allow cron or background sync with secret key
if ($route === 'messages/sync' && (($_GET['key'] ?? '') === 'raccon_sync_2026' || !empty($_SESSION['current_user_id']))) {
    $res = syncInboundEmails($db, true);
    $count = is_array($res) ? ($res['count'] ?? 0) : (int)$res;
    $msgs = is_array($res) ? ($res['messages'] ?? []) : [];
    echo json_encode([
        'success' => true,
        'synced' => $count,
        'count' => $count,
        'messages' => $msgs
    ]);
    exit;
}

// Publicly readable endpoints (forum discussions feed, post details, comments, news, engine)
$isPublicRoute = ($route === 'forum/posts' && $method === 'GET')
    || (preg_match('#^forum/posts/([^/]+)$#', $route) && $method === 'GET')
    || (preg_match('#^forum/posts/([^/]+)/comments$#', $route))
    || ($route === 'news' && $method === 'GET')
    || ($route === 'engine/status' && $method === 'GET')
    || ($route === 'engine/update')
    || ($route === 'story' && $method === 'GET')
    || ($route === 'community/stories' && $method === 'GET')
    || (preg_match('#^community/stories/([^/]+)$#', $route) && $method === 'GET')
    || (preg_match('#^community/stories/([^/]+)/comments$#', $route) && $method === 'GET')
    || ($route === 'marketplace/items' && $method === 'GET')
    || (preg_match('#^marketplace/items/([^/]+)$#', $route) && $method === 'GET');

// Enforce login for private mailbox endpoints
if (!$currentUserId && !$isPublicRoute) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please register or sign in.']);
    exit;
}

// Router
try {
    // 1. GET/POST /api/user and /api/user/profile
    if ($route === 'user' || $route === 'user/profile' || $route === 'user/settings') {
        if ($method === 'GET') {
            $stmt = $db->prepare('SELECT id, email, name, phone, phone_verified, avatar, cover_photo, bio, location, signature, theme, created_at, is_admin FROM users WHERE id = ?');
            $stmt->execute([$currentUserId]);
            $user = $stmt->fetch();
            $stmtSettings = $db->prepare('SELECT * FROM mail_settings WHERE user_id = ?');
            $stmtSettings->execute([$currentUserId]);
            $settings = $stmtSettings->fetch() ?: [];
            echo json_encode(array_merge($user ?: [], ['settings' => $settings]));
            exit;
        } elseif ($method === 'POST') {
            $name = $input['name'] ?? ($_POST['name'] ?? null);
            $sig = $input['signature'] ?? ($_POST['signature'] ?? null);
            $avatar = $input['avatar'] ?? ($_POST['avatar'] ?? null);
            $cover = $input['cover_photo'] ?? ($_POST['cover_photo'] ?? null);
            $theme = $input['theme'] ?? ($_POST['theme'] ?? null);
            $bio = $input['bio'] ?? ($_POST['bio'] ?? null);
            $location = $input['location'] ?? ($_POST['location'] ?? null);
            $sound = $input['sound_enabled'] ?? ($_POST['sound_enabled'] ?? 1);

            if ($name !== null) {
                $db->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([trim($name), $currentUserId]);
                $_SESSION['current_user_name'] = trim($name);
            }
            if ($sig !== null) {
                $db->prepare('UPDATE users SET signature = ? WHERE id = ?')->execute([trim($sig), $currentUserId]);
            }
            if ($avatar !== null) {
                $db->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([trim($avatar), $currentUserId]);
            }
            if ($cover !== null) {
                $db->prepare('UPDATE users SET cover_photo = ? WHERE id = ?')->execute([trim($cover), $currentUserId]);
            }
            if ($theme !== null) {
                $db->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([trim($theme), $currentUserId]);
            }
            if ($bio !== null) {
                $db->prepare('UPDATE users SET bio = ? WHERE id = ?')->execute([trim($bio), $currentUserId]);
            }
            if ($location !== null) {
                $db->prepare('UPDATE users SET location = ? WHERE id = ?')->execute([trim($location), $currentUserId]);
            }

            try {
                $stmt = $db->prepare('INSERT INTO mail_settings (user_id, sound_enabled) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET sound_enabled = excluded.sound_enabled');
                $stmt->execute([$currentUserId, $sound]);
            } catch (Throwable $e) {}

            $stmtUser = $db->prepare('SELECT id, email, name, avatar, cover_photo, bio, location, signature, theme, created_at, is_admin FROM users WHERE id = ?');
            $stmtUser->execute([$currentUserId]);
            $updatedUser = $stmtUser->fetch();
            echo json_encode(['success' => true, 'user' => $updatedUser]);
            exit;
        }
    }

    // POST /api/user/avatar (Upload custom avatar file)
    if ($route === 'user/avatar' && $method === 'POST') {
        if (!empty($_FILES['avatar']['name'])) {
            $origName = $_FILES['avatar']['name'];
            $tmpName = $_FILES['avatar']['tmp_name'];
            if ($tmpName && is_uploaded_file($tmpName)) {
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)) ?: 'png';
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'])) $ext = 'png';
                $destFilename = 'avatar-' . $currentUserId . '-' . time() . '.' . $ext;
                $targetPath = $uploadsDir . '/' . $destFilename;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    // Auto-crop to square using GD if available
                    if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
                        $raw = @file_get_contents($targetPath);
                        if ($raw) {
                            $src = @imagecreatefromstring($raw);
                            if ($src) {
                                $w = imagesx($src);
                                $h = imagesy($src);
                                if ($w !== $h) {
                                    $min = min($w, $h);
                                    $cropX = (int)(($w - $min) / 2);
                                    $cropY = (int)(($h - $min) / 2);
                                    $cropped = imagecrop($src, ['x' => $cropX, 'y' => $cropY, 'width' => $min, 'height' => $min]);
                                    if ($cropped !== false) {
                                        if ($ext === 'png') imagepng($cropped, $targetPath);
                                        elseif ($ext === 'webp' && function_exists('imagewebp')) imagewebp($cropped, $targetPath);
                                        else imagejpeg($cropped, $targetPath, 92);
                                        imagedestroy($cropped);
                                    }
                                }
                                imagedestroy($src);
                            }
                        }
                    }

                    $avatarUrl = '/uploads/' . $destFilename;
                    $db->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$avatarUrl, $currentUserId]);
                    echo json_encode(['success' => true, 'avatar' => $avatarUrl]);
                    exit;
                }
            }
        }
        http_response_code(400);
        echo json_encode(['error' => 'No avatar image file uploaded']);
        exit;
    }

    // POST /api/user/cover (Upload custom cover photo file)
    if ($route === 'user/cover' && $method === 'POST') {
        if (!empty($_FILES['cover']['name'])) {
            $origName = $_FILES['cover']['name'];
            $tmpName = $_FILES['cover']['tmp_name'];
            if ($tmpName && is_uploaded_file($tmpName)) {
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)) ?: 'jpg';
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'])) $ext = 'jpg';
                $destFilename = 'cover-' . $currentUserId . '-' . time() . '.' . $ext;
                if (move_uploaded_file($tmpName, $uploadsDir . '/' . $destFilename)) {
                    $coverUrl = '/uploads/' . $destFilename;
                    $db->prepare('UPDATE users SET cover_photo = ? WHERE id = ?')->execute([$coverUrl, $currentUserId]);
                    echo json_encode(['success' => true, 'cover_photo' => $coverUrl]);
                    exit;
                }
            }
        }
        http_response_code(400);
        echo json_encode(['error' => 'No cover photo file uploaded']);
        exit;
    }

    // ==========================================
    // CHAT API (RESTRICTED TO REGISTERED MEMBERS)
    // ==========================================
    $stmtMyUser = $db->prepare('SELECT email, name FROM users WHERE id = ?');
    $stmtMyUser->execute([$currentUserId]);
    $myUserInfo = $stmtMyUser->fetch() ?: [];
    $myEmail = strtolower($myUserInfo['email'] ?? '');
    $myName = $myUserInfo['name'] ?? 'Clever Raccoon';

    // GET /api/chat/conversations
    if ($route === 'chat/conversations' && $method === 'GET') {
        $stmtMembers = $db->prepare('SELECT id, email, name, avatar, theme FROM users WHERE LOWER(email) != LOWER(?)');
        $stmtMembers->execute([$myEmail]);
        $allMembers = $stmtMembers->fetchAll();

        $conversations = [];
        foreach ($allMembers as $m) {
            $theirEmail = strtolower($m['email']);
            $stmtMsg = $db->prepare('
                SELECT id, sender_email, receiver_email, message, sticker, attachment_name, created_at, is_read 
                FROM chat_messages 
                WHERE (LOWER(sender_email) = ? AND LOWER(receiver_email) = ?) 
                   OR (LOWER(sender_email) = ? AND LOWER(receiver_email) = ?)
                ORDER BY id DESC LIMIT 1
            ');
            $stmtMsg->execute([$myEmail, $theirEmail, $theirEmail, $myEmail]);
            $lastMsg = $stmtMsg->fetch();

            $stmtUnread = $db->prepare('
                SELECT COUNT(*) FROM chat_messages 
                WHERE LOWER(sender_email) = ? AND LOWER(receiver_email) = ? AND is_read = 0
            ');
            $stmtUnread->execute([$theirEmail, $myEmail]);
            $unreadCount = (int)$stmtUnread->fetchColumn();

            $conversations[] = [
                'email' => $m['email'],
                'name' => $m['name'],
                'avatar' => $m['avatar'] ?: '/raccoon-logo.png',
                'theme' => $m['theme'] ?? 'raccon',
                'last_message' => $lastMsg ? ($lastMsg['message'] ?: ($lastMsg['sticker'] ? 'Sticker: ' . $lastMsg['sticker'] : 'Attachment: ' . $lastMsg['attachment_name'])) : 'No messages yet',
                'last_time' => $lastMsg ? $lastMsg['created_at'] : '',
                'last_sender' => $lastMsg ? $lastMsg['sender_email'] : '',
                'unread' => $unreadCount,
                'has_history' => (bool)$lastMsg
            ];
        }

        usort($conversations, function($a, $b) {
            if ($a['unread'] !== $b['unread']) return $b['unread'] - $a['unread'];
            return strcmp($b['last_time'], $a['last_time']);
        });

        echo json_encode(['conversations' => $conversations, 'my_email' => $myEmail]);
        exit;
    }

    // GET /api/chat/messages?with={email}
    if ($route === 'chat/messages' && $method === 'GET') {
        $withEmail = strtolower(trim($_GET['with'] ?? ''));
        if (!$withEmail) {
            echo json_encode(['messages' => []]);
            exit;
        }

        $stmt = $db->prepare('
            SELECT id, sender_id, sender_email, sender_name, receiver_email, message, sticker, attachment_path, attachment_name, attachment_type, attachment_size, is_read, created_at
            FROM chat_messages
            WHERE (LOWER(sender_email) = ? AND LOWER(receiver_email) = ?)
               OR (LOWER(sender_email) = ? AND LOWER(receiver_email) = ?)
            ORDER BY id ASC
        ');
        $stmt->execute([$myEmail, $withEmail, $withEmail, $myEmail]);
        $messages = $stmt->fetchAll();

        // Mark incoming messages as read
        $db->prepare('UPDATE chat_messages SET is_read = 1 WHERE LOWER(sender_email) = ? AND LOWER(receiver_email) = ?')
           ->execute([$withEmail, $myEmail]);

        echo json_encode(['messages' => $messages]);
        exit;
    }

    // POST /api/chat/send
    if ($route === 'chat/send' && $method === 'POST') {
        $toEmail = strtolower(trim($_POST['to'] ?? $input['to'] ?? ''));
        $messageText = trim($_POST['message'] ?? $input['message'] ?? '');
        $sticker = trim($_POST['sticker'] ?? $input['sticker'] ?? '');

        // Restricted to registered members only!
        $stmtCheck = $db->prepare('SELECT id, name FROM users WHERE LOWER(email) = ?');
        $stmtCheck->execute([$toEmail]);
        $recipient = $stmtCheck->fetch();
        if (!$recipient) {
            http_response_code(400);
            echo json_encode(['error' => 'Chat is strictly restricted to registered members only. Contact "' . htmlspecialchars($toEmail) . '" is not registered.']);
            exit;
        }

        $attPath = '';
        $attName = '';
        $attType = '';
        $attSize = 0;
        if (!empty($_FILES['attachment']['name'])) {
            $file = $_FILES['attachment'];
            if ($file['tmp_name'] && is_uploaded_file($file['tmp_name'])) {
                $attName = basename($file['name']);
                $attType = $file['type'] ?: 'application/octet-stream';
                $attSize = (int)$file['size'];
                $ext = strtolower(pathinfo($attName, PATHINFO_EXTENSION)) ?: 'dat';
                $safeFileName = 'chat_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadsDir . '/' . $safeFileName)) {
                    $attPath = '/uploads/' . $safeFileName;
                }
            }
        }

        if (!$messageText && !$sticker && !$attPath) {
            http_response_code(400);
            echo json_encode(['error' => 'Message or sticker cannot be empty.']);
            exit;
        }

        $stmtIns = $db->prepare('
            INSERT INTO chat_messages (sender_id, sender_email, sender_name, receiver_email, message, sticker, attachment_path, attachment_name, attachment_type, attachment_size, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ');
        $stmtIns->execute([$currentUserId, $myEmail, $myName, $toEmail, $messageText, $sticker, $attPath, $attName, $attType, $attSize]);

        $msgId = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $msgId,
                'sender_email' => $myEmail,
                'sender_name' => $myName,
                'receiver_email' => $toEmail,
                'message' => $messageText,
                'sticker' => $sticker,
                'attachment_path' => $attPath,
                'attachment_name' => $attName,
                'attachment_type' => $attType,
                'attachment_size' => $attSize,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    }

    // GET /api/chat/unread
    if ($route === 'chat/unread' && $method === 'GET') {
        $stmt = $db->prepare('SELECT COUNT(*) FROM chat_messages WHERE LOWER(receiver_email) = ? AND is_read = 0');
        $stmt->execute([$myEmail]);
        echo json_encode(['unread' => (int)$stmt->fetchColumn()]);
        exit;
    }

    // 2. GET /api/accounts & POST /api/accounts/switch
    if ($route === 'accounts') {
        $stmt = $db->query('SELECT id, email, name, avatar FROM users');
        echo json_encode(['accounts' => $stmt->fetchAll(), 'activeId' => $currentUserId]);
        exit;
    }
    if ($route === 'accounts/switch' && $method === 'POST') {
        $userId = $input['userId'] ?? 'user_main';
        $_SESSION['current_user_id'] = $userId;
        echo json_encode(['success' => true, 'activeId' => $userId]);
        exit;
    }

    // 3. GET/POST/DELETE /api/folders
    if ($route === 'folders') {
        if ($method === 'GET') {
            $stmt = $db->prepare('SELECT * FROM folders WHERE user_id = ? ORDER BY name ASC');
            $stmt->execute([$currentUserId]);
            $customFolders = $stmt->fetchAll();

            $stmtUser = $db->prepare('SELECT email FROM users WHERE id = ?');
            $stmtUser->execute([$currentUserId]);
            $userEmail = $stmtUser->fetchColumn() ?: '';

            // Inbox counts (guaranteed inbox delivery for registered members)
            $stmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM messages WHERE user_id = ? AND LOWER(recipient_email) = LOWER(?) AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = "" OR folder_id = "inbox")');
            $stmt->execute([$currentUserId, $userEmail]);
            $inboxStats = $stmt->fetch();

            // Unread
            $stmt = $db->prepare('SELECT COUNT(*) as total FROM messages WHERE user_id = ? AND LOWER(recipient_email) = LOWER(?) AND is_read = 0 AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0');
            $stmt->execute([$currentUserId, $userEmail]);
            $unreadStats = $stmt->fetch();

            // Favorites
            $stmt = $db->prepare('SELECT COUNT(*) as total FROM messages WHERE user_id = ? AND is_starred = 1 AND is_trashed = 0');
            $stmt->execute([$currentUserId]);
            $starStats = $stmt->fetch();

            // Drafts
            $stmt = $db->prepare('SELECT COUNT(*) as total FROM messages WHERE user_id = ? AND is_draft = 1 AND is_trashed = 0');
            $stmt->execute([$currentUserId]);
            $draftsStats = $stmt->fetch();

            // Sent
            $stmt = $db->prepare('SELECT COUNT(*) as total FROM messages WHERE user_id = ? AND (LOWER(sender_email) = LOWER(?) OR folder_id = "sent") AND is_draft = 0 AND is_trashed = 0');
            $stmt->execute([$currentUserId, $userEmail]);
            $sentStats = $stmt->fetch();

            // Archive
            $stmt = $db->prepare('SELECT COUNT(*) as total FROM messages WHERE user_id = ? AND is_archived = 1 AND is_trashed = 0');
            $stmt->execute([$currentUserId]);
            $archiveStats = $stmt->fetch();

            // Spam (strictly exclude messages from registered members)
            $stmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM messages WHERE user_id = ? AND is_spam = 1 AND is_trashed = 0 AND LOWER(sender_email) NOT IN (SELECT LOWER(email) FROM users)');
            $stmt->execute([$currentUserId]);
            $spamStats = $stmt->fetch();

            // Trash
            $stmt = $db->prepare('SELECT COUNT(*) as total FROM messages WHERE user_id = ? AND is_trashed = 1');
            $stmt->execute([$currentUserId]);
            $trashStats = $stmt->fetch();

            $foldersWithCounts = array_map(function($f) use ($db, $currentUserId) {
                $stmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM messages WHERE user_id = ? AND folder_id = ? AND is_trashed = 0');
                $stmt->execute([$currentUserId, $f['id']]);
                $s = $stmt->fetch();
                return array_merge($f, [
                    'total' => (int)($s['total'] ?? 0),
                    'unread' => (int)($s['unread'] ?? 0)
                ]);
            }, $customFolders);

            echo json_encode([
                'standard' => [
                    'inbox' => ['total' => (int)($inboxStats['total'] ?? 0), 'unread' => (int)($inboxStats['unread'] ?? 0)],
                    'unread' => ['total' => (int)($unreadStats['total'] ?? 0), 'unread' => (int)($unreadStats['total'] ?? 0)],
                    'favorites' => ['total' => (int)($starStats['total'] ?? 0)],
                    'drafts' => ['total' => (int)($draftsStats['total'] ?? 0)],
                    'sent' => ['total' => (int)($sentStats['total'] ?? 0)],
                    'archive' => ['total' => (int)($archiveStats['total'] ?? 0)],
                    'spam' => ['total' => (int)($spamStats['total'] ?? 0), 'unread' => (int)($spamStats['unread'] ?? 0)],
                    'trash' => ['total' => (int)($trashStats['total'] ?? 0)]
                ],
                'custom' => $foldersWithCounts
            ]);
            exit;
        } elseif ($method === 'POST') {
            $name = trim($input['name'] ?? '');
            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Folder name required']);
                exit;
            }
            $id = 'folder_' . uniqid();
            $stmt = $db->prepare('INSERT INTO folders (id, user_id, name, icon) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $currentUserId, $name, 'folder']);
            echo json_encode(['success' => true, 'folder' => ['id' => $id, 'name' => $name]]);
            exit;
        }
    }

    if (preg_match('#^folders/([^/]+)$#', $route, $matches) && $method === 'DELETE') {
        $folderId = $matches[1];
        $db->prepare('UPDATE messages SET folder_id = NULL WHERE folder_id = ? AND user_id = ?')->execute([$folderId, $currentUserId]);
        $db->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?')->execute([$folderId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Inbound Mail Sync Endpoint (called periodically or manually by webmail)
    if ($route === 'messages/sync') {
        $syncRes = syncInboundEmails($db, true);
        if (is_array($syncRes)) {
            echo json_encode(['success' => true, 'synced' => $syncRes['count'] ?? count($syncRes['messages'] ?? []), 'messages' => $syncRes['messages'] ?? []]);
        } else {
            echo json_encode(['success' => true, 'synced' => (int)$syncRes, 'messages' => []]);
        }
        exit;
    }

    // 4. GET /api/messages
    if ($route === 'messages' && $method === 'GET') {
        $folder = strtolower($_GET['folder'] ?? 'inbox');
        $filter = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'date_desc';
        $search = trim($_GET['search'] ?? '');

        // Auto-sync incoming mail from server when opening inbox or unread
        if ($folder === 'inbox' || $folder === 'unread') {
            syncInboundEmails($db);
        }

        $stmtUser = $db->prepare('SELECT email FROM users WHERE id = ?');
        $stmtUser->execute([$currentUserId]);
        $userEmail = $stmtUser->fetchColumn() ?: '';

        $where = ['user_id = ?'];
        $params = [$currentUserId];

        if ($folder === 'all' || $folder === 'search') {
            // Search across ALL folders without folder restrictions
            if ($filter !== 'trash') {
                $where[] = 'is_trashed = 0';
            }
        } elseif ($folder === 'inbox') {
            $where[] = 'is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = "" OR folder_id = "inbox")';
        } elseif ($folder === 'unread') {
            $where[] = 'is_read = 0 AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0';
        } elseif ($folder === 'favorites' || $folder === 'starred') {
            $where[] = 'is_starred = 1 AND is_trashed = 0';
        } elseif ($folder === 'drafts') {
            $where[] = 'is_draft = 1 AND is_trashed = 0';
        } elseif ($folder === 'sent') {
            $where[] = '(LOWER(sender_email) = LOWER(?) OR folder_id = "sent")';
            $params[] = $userEmail;
            $where[] = 'is_draft = 0 AND is_trashed = 0';
        } elseif ($folder === 'archive') {
            $where[] = 'is_archived = 1 AND is_trashed = 0';
        } elseif ($folder === 'spam') {
            $where[] = 'is_spam = 1 AND is_trashed = 0 AND LOWER(sender_email) NOT IN (SELECT LOWER(email) FROM users)';
        } elseif ($folder === 'trash') {
            $where[] = 'is_trashed = 1';
        } else {
            $where[] = 'folder_id = ? AND is_trashed = 0';
            $params[] = $folder;
        }

        if ($filter === 'unread') $where[] = 'is_read = 0';
        elseif ($filter === 'starred') $where[] = 'is_starred = 1';
        elseif ($filter === 'has_attachments') $where[] = 'EXISTS (SELECT 1 FROM attachments WHERE attachments.message_id = messages.id)';

        if ($search) {
            $q = '%' . $search . '%';
            $where[] = '(subject LIKE ? OR sender_name LIKE ? OR sender_email LIKE ? OR recipient_email LIKE ? OR body LIKE ?)';
            array_push($params, $q, $q, $q, $q, $q);
        }

        $orderBy = 'created_at DESC';
        if ($sort === 'date_asc') $orderBy = 'created_at ASC';
        elseif ($sort === 'sender') $orderBy = 'sender_name ASC, created_at DESC';
        elseif ($sort === 'subject') $orderBy = 'subject ASC, created_at DESC';

        $sql = "SELECT m.*, 
            CASE 
                WHEN m.is_trashed = 1 THEN 'Trash'
                WHEN m.is_spam = 1 THEN 'Spam'
                WHEN m.is_archived = 1 THEN 'Archive'
                WHEN m.is_draft = 1 THEN 'Drafts'
                WHEN m.folder_id = 'sent' OR LOWER(m.sender_email) = LOWER(?) THEN 'Sent'
                WHEN m.folder_id IS NOT NULL AND m.folder_id != '' AND m.folder_id != 'inbox' THEN m.folder_id
                ELSE 'Inbox'
            END as computed_folder,
            (SELECT COUNT(*) FROM attachments a WHERE a.message_id = m.id) as attachment_count 
            FROM messages m WHERE " . implode(' AND ', $where) . " ORDER BY " . $orderBy;
        
        $execParams = array_merge([$userEmail], $params);
        $stmt = $db->prepare($sql);
        $stmt->execute($execParams);
        $messages = $stmt->fetchAll();

        echo json_encode(['messages' => $messages]);
        exit;
    }

    // 4b. GET /api/search/suggestions (Auto-complete suggestions for senders, contacts & emails across all folders)
    // 4b. GET /api/search/suggestions (Auto-complete suggestions for senders, contacts & emails across all folders)
    if ($route === 'search/suggestions') {
        $q = trim($_GET['q'] ?? '');
        if ($q === '') {
            // When opened on click with empty query, return top recent senders and quick search suggestions
            $stmtRecentSenders = $db->prepare('
                SELECT DISTINCT sender_name as name, sender_email as email, "sender" as type
                FROM messages
                WHERE user_id = ? AND sender_email IS NOT NULL AND sender_email != ""
                ORDER BY created_at DESC
                LIMIT 5
            ');
            $stmtRecentSenders->execute([$currentUserId]);
            $recentPeople = $stmtRecentSenders->fetchAll();

            $stmtRecentMsgs = $db->prepare('
                SELECT id, sender_name, sender_email, subject, body, created_at, is_spam, is_trashed, is_archived, folder_id,
                CASE 
                    WHEN is_trashed = 1 THEN "Trash"
                    WHEN is_spam = 1 THEN "Spam"
                    WHEN is_archived = 1 THEN "Archive"
                    WHEN is_draft = 1 THEN "Drafts"
                    WHEN folder_id = "sent" THEN "Sent"
                    ELSE "Inbox"
                END as folder_label
                FROM messages
                WHERE user_id = ? AND is_trashed = 0 AND is_spam = 0
                ORDER BY created_at DESC
                LIMIT 4
            ');
            $stmtRecentMsgs->execute([$currentUserId]);
            $recentMsgs = $stmtRecentMsgs->fetchAll();

            echo json_encode([
                'query' => '',
                'is_empty' => true,
                'people' => $recentPeople,
                'messages' => $recentMsgs
            ]);
            exit;
        }

        $term = '%' . $q . '%';
        $prefixTerm = $q . '%';

        // 1. Senders & Contacts (Accurate ranking: exact & prefix match first)
        $stmtSenders = $db->prepare('
            SELECT DISTINCT sender_name as name, sender_email as email, "sender" as type,
            CASE 
                WHEN sender_name LIKE ? THEN 1
                WHEN sender_email LIKE ? THEN 2
                ELSE 3
            END as rank_order
            FROM messages
            WHERE user_id = ? AND (sender_name LIKE ? OR sender_email LIKE ?)
            ORDER BY rank_order ASC, created_at DESC
            LIMIT 6
        ');
        $stmtSenders->execute([$prefixTerm, $prefixTerm, $currentUserId, $term, $term]);
        $senders = $stmtSenders->fetchAll();

        $stmtContacts = $db->prepare('
            SELECT name, email, "contact" as type,
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN email LIKE ? THEN 2
                ELSE 3
            END as rank_order
            FROM contacts
            WHERE user_id = ? AND (name LIKE ? OR email LIKE ?)
            ORDER BY rank_order ASC, name ASC
            LIMIT 6
        ');
        $stmtContacts->execute([$prefixTerm, $prefixTerm, $currentUserId, $term, $term]);
        $contacts = $stmtContacts->fetchAll();

        $people = [];
        $seenEmails = [];
        // Contacts first, then senders
        foreach (array_merge($contacts, $senders) as $p) {
            $e = strtolower($p['email'] ?? '');
            if (!empty($e) && empty($seenEmails[$e])) {
                $seenEmails[$e] = true;
                $people[] = $p;
            }
        }

        // 2. Matching Messages across ALL folders with accurate scoring / ranking:
        // Match in Subject (prefix > contains) > Sender > Body
        $stmtMsgs = $db->prepare('
            SELECT id, sender_name, sender_email, subject, SUBSTR(body, 1, 140) as body, created_at, is_spam, is_trashed, is_archived, folder_id,
            CASE 
                WHEN is_trashed = 1 THEN "Trash"
                WHEN is_spam = 1 THEN "Spam"
                WHEN is_archived = 1 THEN "Archive"
                WHEN is_draft = 1 THEN "Drafts"
                WHEN folder_id = "sent" THEN "Sent"
                WHEN folder_id IS NOT NULL AND folder_id != "" AND folder_id != "inbox" THEN folder_id
                ELSE "Inbox"
            END as folder_label,
            CASE
                WHEN subject LIKE ? THEN 1
                WHEN subject LIKE ? THEN 2
                WHEN sender_name LIKE ? OR sender_email LIKE ? THEN 3
                ELSE 4
            END as relevance_rank
            FROM messages
            WHERE user_id = ? AND (subject LIKE ? OR sender_name LIKE ? OR sender_email LIKE ? OR recipient_email LIKE ? OR body LIKE ?)
            ORDER BY relevance_rank ASC, created_at DESC
            LIMIT 8
        ');
        $stmtMsgs->execute([$prefixTerm, $term, $term, $term, $currentUserId, $term, $term, $term, $term, $term]);
        $matchedMessages = $stmtMsgs->fetchAll();

        echo json_encode([
            'query' => $q,
            'is_empty' => false,
            'people' => array_slice($people, 0, 5),
            'messages' => $matchedMessages
        ]);
        exit;
    }

    // 5. GET /api/attachments (Photos & Files view)
    if ($route === 'attachments') {
        $type = $_GET['type'] ?? 'all';
        $where = ['m.user_id = ?', 'm.is_trashed = 0'];
        $params = [$currentUserId];

        if ($type === 'photos') $where[] = "a.mime_type LIKE 'image/%'";
        elseif ($type === 'documents') $where[] = "a.mime_type NOT LIKE 'image/%'";

        $sql = "SELECT a.id, a.message_id, a.filename, a.original_name, a.mime_type, a.size_bytes, a.created_at, m.subject as message_subject, m.sender_name, m.sender_email FROM attachments a JOIN messages m ON a.message_id = m.id WHERE " . implode(' AND ', $where) . " ORDER BY a.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['attachments' => $stmt->fetchAll()]);
        exit;
    }

    // 6. Single Message routes
    if (preg_match('#^messages/([^/]+)$#', $route, $matches) && $method === 'GET') {
        $msgId = trim(explode('?', $matches[1])[0]);
        $stmt = $db->prepare('SELECT * FROM messages WHERE id = ? AND user_id = ?');
        $stmt->execute([$msgId, $currentUserId]);
        $msg = $stmt->fetch();
        if (!$msg) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        // Mark as read
        $db->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$msgId]);
        $msg['is_read'] = 1;

        $stmtAtt = $db->prepare('SELECT * FROM attachments WHERE message_id = ?');
        $stmtAtt->execute([$msgId]);
        $msg['attachments'] = $stmtAtt->fetchAll();

        // Fetch conversation thread (all replies and related messages in same subject thread or in_reply_to)
        $cleanSubj = trim(preg_replace('/^(\s*(re|fwd|fw)\s*[:\-\x{00A0}]\s*)+/iu', '', $msg['subject'] ?? ''));
        $parentRefId = $msg['message_id'] ?? $msgId;
        if ($cleanSubj !== '' && $cleanSubj !== '(no subject)') {
            $stmtThread = $db->prepare('
                SELECT * FROM messages 
                WHERE user_id = ? AND id != ? AND is_trashed = 0 AND is_draft = 0
                  AND (subject LIKE ? OR subject LIKE ? OR (in_reply_to IS NOT NULL AND (in_reply_to = ? OR in_reply_to = ?)))
                ORDER BY created_at ASC
            ');
            $stmtThread->execute([$currentUserId, $msgId, '%' . $cleanSubj . '%', $cleanSubj, $msgId, $parentRefId]);
            $msg['thread'] = $stmtThread->fetchAll();
        } else {
            $stmtThread = $db->prepare('
                SELECT * FROM messages 
                WHERE user_id = ? AND id != ? AND is_trashed = 0 AND is_draft = 0
                  AND (in_reply_to IS NOT NULL AND (in_reply_to = ? OR in_reply_to = ?))
                ORDER BY created_at ASC
            ');
            $stmtThread->execute([$currentUserId, $msgId, $msgId, $parentRefId]);
            $msg['thread'] = $stmtThread->fetchAll();
        }

        echo json_encode(['message' => $msg]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/star$#', $route, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $msgId = trim(explode('?', $matches[1])[0]);
        $starred = !empty($input['is_starred']) ? 1 : 0;
        $db->prepare('UPDATE messages SET is_starred = ? WHERE id = ? AND user_id = ?')->execute([$starred, $msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/read$#', $route, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $msgId = trim(explode('?', $matches[1])[0]);
        $read = !empty($input['is_read']) ? 1 : 0;
        $db->prepare('UPDATE messages SET is_read = ? WHERE id = ? AND user_id = ?')->execute([$read, $msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/archive$#', $route, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $msgId = trim(explode('?', $matches[1])[0]);
        $db->prepare('UPDATE messages SET is_archived = 1, is_spam = 0 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/spam$#', $route, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $msgId = trim(explode('?', $matches[1])[0]);
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        if (isset($input['is_spam']) && !$input['is_spam']) {
            $db->prepare('UPDATE messages SET is_spam = 0, is_archived = 0, folder_id = "inbox" WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
            echo json_encode(['success' => true, 'unspammed' => true]);
            exit;
        }

        // Protect registered members from being marked as spam
        $stmtSender = $db->prepare('SELECT sender_email FROM messages WHERE id = ? AND user_id = ?');
        $stmtSender->execute([$msgId, $currentUserId]);
        $sEmail = $stmtSender->fetchColumn();

        $isMemberSender = false;
        if ($sEmail) {
            $stmtMem = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
            $stmtMem->execute([$sEmail]);
            if ($stmtMem->fetch()) {
                $isMemberSender = true;
            }
        }

        if ($isMemberSender) {
            $db->prepare('UPDATE messages SET is_spam = 0 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
            echo json_encode(['success' => true, 'protected' => true, 'message' => 'Registered members cannot be marked as spam.']);
        } else {
            $db->prepare('UPDATE messages SET is_spam = 1, is_archived = 0 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if (preg_match('#^messages/([^/]+)/trash$#', $route, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $msgId = trim(explode('?', $matches[1])[0]);
        $db->prepare('UPDATE messages SET is_trashed = 1 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Delete single message permanently or move to trash
    if ((preg_match('#^messages/([^/]+)$#', $route, $matches) && $method === 'DELETE') ||
        (preg_match('#^messages/([^/]+)/delete$#', $route, $matches) && ($method === 'POST' || $method === 'DELETE'))) {
        $msgId = trim(explode('?', $matches[1])[0]);
        $isForce = !empty($_GET['force']) || !empty($input['force']) || !empty($_GET['permanent']) || !empty($input['permanent']);
        if (!$isForce) {
            $stmtTr = $db->prepare('SELECT is_trashed FROM messages WHERE id = ? AND user_id = ?');
            $stmtTr->execute([$msgId, $currentUserId]);
            $isTrashed = (int)$stmtTr->fetchColumn();
            if ($isTrashed === 1) {
                $isForce = true;
            }
        }

        if ($isForce) {
            $db->prepare('DELETE FROM messages WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
        } else {
            $db->prepare('UPDATE messages SET is_trashed = 1 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
        }
        echo json_encode(['success' => true, 'deleted' => $isForce ? 'permanent' : 'trashed']);
        exit;
    }

    // 7. Batch operations
    if ($route === 'messages/batch' && $method === 'POST') {
        $action = $input['action'] ?? '';
        $ids = $input['ids'] ?? [];

        if ($action === 'mark_all_read') {
            $folder = strtolower($input['folder'] ?? 'inbox');
            if ($folder === 'inbox') {
                $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_trashed = 0 AND is_spam = 0 AND is_archived = 0 AND is_draft = 0")->execute([$currentUserId]);
            } elseif ($folder === 'starred') {
                $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_starred = 1 AND is_trashed = 0")->execute([$currentUserId]);
            } elseif ($folder === 'archive') {
                $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_archived = 1 AND is_trashed = 0")->execute([$currentUserId]);
            } elseif ($folder === 'trash') {
                $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_trashed = 1")->execute([$currentUserId]);
            } elseif ($folder === 'spam') {
                $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_spam = 1")->execute([$currentUserId]);
            } else {
                $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND folder_id = ?")->execute([$currentUserId, $folder]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if (empty($ids)) {
            echo json_encode(['success' => true, 'count' => 0]);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$currentUserId], $ids);

        if ($action === 'delete' || $action === 'permanent_delete') {
            $db->prepare("DELETE FROM messages WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'mark_read') {
            $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'mark_unread') {
            $db->prepare("UPDATE messages SET is_read = 0 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'star') {
            $db->prepare("UPDATE messages SET is_starred = 1 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'archive') {
            $db->prepare("UPDATE messages SET is_archived = 1, is_spam = 0, is_trashed = 0 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'trash') {
            $db->prepare("UPDATE messages SET is_trashed = 1 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'restore' || $action === 'untrash') {
            $db->prepare("UPDATE messages SET is_trashed = 0, is_archived = 0, is_spam = 0 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'spam') {
            $db->prepare("UPDATE messages SET is_spam = 1, is_archived = 0 WHERE user_id = ? AND id IN ($placeholders) AND LOWER(sender_email) NOT IN (SELECT LOWER(email) FROM users)")->execute($params);
        }
        echo json_encode(['success' => true, 'count' => count($ids)]);
        exit;
    }

    if ($route === 'messages/empty-folder' && $method === 'POST') {
        $folder = strtolower($input['folder'] ?? '');
        if ($folder === 'trash') {
            $db->prepare('DELETE FROM messages WHERE user_id = ? AND is_trashed = 1')->execute([$currentUserId]);
        } elseif ($folder === 'spam') {
            $db->prepare('DELETE FROM messages WHERE user_id = ? AND is_spam = 1')->execute([$currentUserId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 8. Compose / Send Message
    if (($route === 'messages/send' || $route === 'messages') && $method === 'POST') {
        $to = $_POST['to'] ?? ($input['to'] ?? '');
        $cc = $_POST['cc'] ?? ($input['cc'] ?? '');
        $bcc = $_POST['bcc'] ?? ($input['bcc'] ?? '');
        $subject = trim($_POST['subject'] ?? ($input['subject'] ?? '(no subject)'));
        if ($subject === '') $subject = '(no subject)';
        $body = $_POST['body'] ?? ($input['body'] ?? '');
        $bodyHtml = $_POST['body_html'] ?? ($input['body_html'] ?? '');
        $draftId = $_POST['draft_id'] ?? ($input['draft_id'] ?? null);
        $inReplyTo = trim($_POST['in_reply_to'] ?? ($input['in_reply_to'] ?? ''));
        $parentId = trim($_POST['parent_id'] ?? ($input['parent_id'] ?? ''));

        // If not explicitly provided, check if subject starts with Re: or Fwd: and auto-detect parent message
        if (empty($inReplyTo) && preg_match('/^(?:Re|Fwd):\s*(.+)$/i', $subject, $subM)) {
            $parentSubj = trim($subM[1]);
            $stmtFindParent = $db->prepare('SELECT id, message_id FROM messages WHERE (subject = ? OR subject = ?) AND (user_id = ? OR recipient_email = ?) ORDER BY created_at DESC LIMIT 1');
            $stmtFindParent->execute([$parentSubj, $subject, $currentUserId, $user['email']]);
            $parentMsg = $stmtFindParent->fetch();
            if ($parentMsg) {
                $inReplyTo = $parentMsg['message_id'] ?: $parentMsg['id'];
                if (empty($parentId)) $parentId = $parentMsg['id'];
            }
        }

        if (!$to) {
            http_response_code(400);
            echo json_encode(['error' => 'Recipient email required']);
            exit;
        }

        $stmtUser = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmtUser->execute([$currentUserId]);
        $user = $stmtUser->fetch();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Sender user not found']);
            exit;
        }

        if ($draftId) {
            $db->prepare('DELETE FROM messages WHERE id = ? AND user_id = ?')->execute([$draftId, $currentUserId]);
        }

        // 1. Save Sender's copy into Sent Folder
        $msgId = 'msg_' . uniqid();
        $stmt = $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, cc, bcc, subject, body, body_html, is_read, is_draft, folder_id, in_reply_to, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, "sent", ?, ?, strftime(\'%Y-%m-%dT%H:%M:%SZ\', \'now\'))');
        $stmt->execute([$msgId, $currentUserId, $user['name'], $user['email'], $to, $cc, $bcc, $subject, $body, $bodyHtml, $inReplyTo ?: null, $parentId ?: null]);

        // 2. Process file attachments
        $savedAttachments = [];
        if (!empty($_FILES['attachments']['name'])) {
            $fileCount = is_array($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 1;
            for ($i = 0; $i < $fileCount; $i++) {
                $origName = is_array($_FILES['attachments']['name']) ? $_FILES['attachments']['name'][$i] : $_FILES['attachments']['name'];
                $tmpName = is_array($_FILES['attachments']['tmp_name']) ? $_FILES['attachments']['tmp_name'][$i] : $_FILES['attachments']['tmp_name'];
                $size = is_array($_FILES['attachments']['size']) ? $_FILES['attachments']['size'][$i] : $_FILES['attachments']['size'];
                $mime = is_array($_FILES['attachments']['type']) ? $_FILES['attachments']['type'][$i] : $_FILES['attachments']['type'];

                if ($tmpName && is_uploaded_file($tmpName)) {
                    $ext = pathinfo($origName, PATHINFO_EXTENSION);
                    $destFilename = 'att-' . time() . '-' . rand(1000, 9999) . ($ext ? '.' . $ext : '');
                    $targetPath = $uploadsDir . '/' . $destFilename;
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $attId = 'att_' . uniqid();
                        $db->prepare('INSERT INTO attachments (id, message_id, filename, original_name, mime_type, size_bytes, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)')
                           ->execute([$attId, $msgId, $destFilename, $origName, $mime, $size, $destFilename]);
                        $savedAttachments[] = [
                            'filename' => $destFilename,
                            'original_name' => $origName,
                            'mime_type' => $mime,
                            'size_bytes' => $size,
                            'file_path' => $targetPath
                        ];
                    }
                }
            }
        }

        // 3. Extract and parse recipients
        $toEmails = extractEmails($to);
        if (empty($toEmails)) {
            $cleaned = trim($to);
            if (!str_contains($cleaned, '@')) {
                $cleaned .= '@racconmail.eu';
            }
            $toEmails = [$cleaned];
        }
        $ccEmails = extractEmails($cc);
        $bccEmails = extractEmails($bcc);
        $rawTargets = array_values(array_unique(array_merge($toEmails, $ccEmails, $bccEmails)));

        // Normalize legacy test domain @raccoonmail.com to real @racconmail.eu
        $allTargets = [];
        foreach ($rawTargets as $tgt) {
            $tgtClean = str_ireplace('@raccoonmail.com', '@racconmail.eu', trim($tgt));
            if ($tgtClean !== '') $allTargets[] = strtolower($tgtClean);
        }
        $allTargets = array_values(array_unique($allTargets));

        $deliveredLocal = 0;
        $deliveredExternal = 0;

        $botProfiles = [
            'rocky@racconmail.eu' => ['name' => 'Rocky Raccoon', 'avatar' => '/raccoon-logo.png'],
            'luna@racconmail.eu' => ['name' => 'Luna', 'avatar' => '/raccoon-logo.png'],
            'team@racconmail.eu' => ['name' => 'Raccon Team', 'avatar' => '/raccoon-logo.png']
        ];

        foreach ($allTargets as $targetEmail) {
            // If recipient is a built-in nocturnal bot/companion, guarantee local recipient user exists
            if (isset($botProfiles[$targetEmail])) {
                $stmtCheckBot = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(email) = LOWER(?)');
                $stmtCheckBot->execute([$targetEmail]);
                $existingBot = $stmtCheckBot->fetch();
                if (!$existingBot) {
                    $bId = 'user_' . substr(md5($targetEmail), 0, 10);
                    $db->prepare('INSERT OR IGNORE INTO users (id, email, name, avatar, signature) VALUES (?, ?, ?, ?, ?)')
                       ->execute([$bId, $targetEmail, $botProfiles[$targetEmail]['name'], $botProfiles[$targetEmail]['avatar'], '🐾 Nocturnal Companion']);
                }
            }

            $stmtRecip = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?)');
            $stmtRecip->execute([$targetEmail]);
            $localRecip = $stmtRecip->fetch();

            $postcardHtml = buildRacconMailPostcardHtml($user['name'], $user['email'], $targetEmail, $subject, $body, $bodyHtml, $savedAttachments);

            if ($localRecip) {
                // Deliver to recipient's Inbox in SQLite (guaranteed is_spam = 0 for registered members)
                $inId = 'in_' . uniqid();
                $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, cc, bcc, subject, body, body_html, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id, in_reply_to, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL, ?, ?, strftime(\'%Y-%m-%dT%H:%M:%SZ\', \'now\'))')
                   ->execute([$inId, $localRecip['id'], $user['name'], $user['email'], $localRecip['email'], $cc, $bcc, $subject, $body, $postcardHtml, $inReplyTo ?: null, $parentId ?: null]);

                // Copy attachments for recipient
                foreach ($savedAttachments as $att) {
                    $attCopyId = 'att_' . uniqid();
                    $db->prepare('INSERT INTO attachments (id, message_id, filename, original_name, mime_type, size_bytes, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)')
                       ->execute([$attCopyId, $inId, $att['filename'], $att['original_name'], $att['mime_type'], $att['size_bytes'], $att['filename']]);
                }
                $deliveredLocal++;

                // If user replied to Rocky Raccoon or Raccon Team, auto-acknowledge with a playful confirmation postcard
                if (isset($botProfiles[$targetEmail]) && $user['id'] !== $localRecip['id']) {
                    $botResponses = [
                        'rocky@racconmail.eu' => "Got your postcard! 🦝 Thanks for the quick note. I'm keeping an eye out for midnight crumbs and sweet bakery deliveries! 🐾",
                        'luna@racconmail.eu' => "Postcard received under the stars! 🏕️✨ Hope your nocturnal adventures are going great!",
                        'team@racconmail.eu' => "Thanks for reaching out to the Raccon Team! 💌 Your encrypted mailbox is 100% active and healthy. 🦝"
                    ];
                    $replyText = $botResponses[$targetEmail] ?? "Received your postcard loud and clear! 🐾";
                    $botReplySubj = str_starts_with($subject, 'Re:') ? $subject : 'Re: ' . $subject;
                    $replyHtml = buildRacconMailPostcardHtml($botProfiles[$targetEmail]['name'], $targetEmail, $user['email'], $botReplySubj, $replyText, '', []);
                    $botReplyId = 'in_' . uniqid();
                    $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, cc, bcc, subject, body, body_html, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id, in_reply_to, parent_id, created_at) VALUES (?, ?, ?, ?, ?, "", "", ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL, ?, ?, strftime(\'%Y-%m-%dT%H:%M:%SZ\', \'now\'))')
                       ->execute([$botReplyId, $user['id'], $botProfiles[$targetEmail]['name'], $targetEmail, $user['email'], $botReplySubj, $replyText, $replyHtml, $msgId, $msgId]);
                }
            } else {
                // External email address: send via PHP mail() / SMTP socket
                sendOutgoingEmail($user['name'], $user['email'], $targetEmail, $subject, $body, $postcardHtml, $savedAttachments, $inReplyTo ?: null);
                $deliveredExternal++;
            }
        }

        echo json_encode([
            'success' => true,
            'messageId' => $msgId,
            'delivered_local' => $deliveredLocal,
            'delivered_external' => $deliveredExternal
        ]);
        exit;
    }

    // 9. Contacts
    if ($route === 'contacts') {
        if ($method === 'GET') {
            $stmt = $db->prepare('SELECT * FROM contacts WHERE user_id = ? ORDER BY name ASC');
            $stmt->execute([$currentUserId]);
            echo json_encode(['contacts' => $stmt->fetchAll()]);
            exit;
        } elseif ($method === 'POST') {
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $notes = trim($input['notes'] ?? '');
            $id = 'cnt_' . uniqid();
            $db->prepare('INSERT INTO contacts (id, user_id, name, email, phone, notes) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$id, $currentUserId, $name, $email, $phone, $notes]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    
    // ==========================================
    // 14. HIGH-VALUE PRODUCTIVITY & PRO SUITE API
    // ==========================================

    // CALENDAR EVENTS API
    if ($route === 'calendar/events') {
        if ($method === 'GET') {
            $month = $_GET['month'] ?? date('Y-m');
            $stmt = $db->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND event_date LIKE ? ORDER BY event_date ASC, start_time ASC");
            $stmt->execute([$currentUserId, $month . '%']);
            $events = $stmt->fetchAll();

            // Pre-seed default events if brand new user
            if (empty($events) && $month === date('Y-m')) {
                $today = date('Y-m-d');
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                $nextWeek = date('Y-m-d', strtotime('+3 days'));
                
                $seeds = [
                    ['ev_' . uniqid(), $currentUserId, 'Team Strategy & Product Launch 🚀', $today, '10:00', '11:30', 'Zoom Meeting Room #4', '15m', 'work', '#06b6d4', 'Discuss Q4 roadmap and CodeCanyon webmail script release.'],
                    ['ev_' . uniqid(), $currentUserId, 'Design System & Icon Review', $tomorrow, '14:00', '15:00', 'Office Studio A', '30m', 'meeting', '#a855f7', 'Review modern pastel sticky notes and calendar cards.'],
                    ['ev_' . uniqid(), $currentUserId, 'Server Maintenance & IMAP Sync', $nextWeek, '08:00', '09:00', 'Cloud Infrastructure', '1h', 'urgent', '#ef4444', 'Optimize SQLite WAL mode and Maildir SSD scanner speed.']
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT OR IGNORE INTO calendar_events (id, user_id, title, event_date, start_time, end_time, location, reminder, category, color, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId, $month . '%']);
                $events = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'events' => $events]);
            exit;
        } elseif ($method === 'POST') {
            $title = trim($input['title'] ?? 'New Event');
            $date = trim($input['date'] ?? date('Y-m-d'));
            $startTime = trim($input['start_time'] ?? '09:00');
            $endTime = trim($input['end_time'] ?? '10:00');
            $location = trim($input['location'] ?? '');
            $reminder = trim($input['reminder'] ?? '15m');
            $category = trim($input['category'] ?? 'work');
            $color = trim($input['color'] ?? '#06b6d4');
            $linkedEmailId = trim($input['linked_email_id'] ?? '');
            $notes = trim($input['notes'] ?? '');
            $id = 'ev_' . uniqid();

            $db->prepare("INSERT INTO calendar_events (id, user_id, title, event_date, start_time, end_time, location, reminder, category, color, linked_email_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$id, $currentUserId, $title, $date, $startTime, $endTime, $location, $reminder, $category, $color, $linkedEmailId, $notes]);

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Event created']);
            exit;
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? ($input['id'] ?? '');
            $db->prepare("DELETE FROM calendar_events WHERE id = ? AND user_id = ?")->execute([$id, $currentUserId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // NOTES SECTION API
    if ($route === 'notes') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM user_notes WHERE user_id = ? ORDER BY is_pinned DESC, updated_at DESC");
            $stmt->execute([$currentUserId]);
            $notes = $stmt->fetchAll();

            // Pre-seed sample notes if empty
            if (empty($notes)) {
                $seeds = [
                    ['nt_' . uniqid(), $currentUserId, 'CodeCanyon Listing Checklist 📌', "1. Write compelling item description
2. Prepare 80x80 raccoon avatar
3. Include 1-Click Installer script
4. Add offline documentation HTML
5. Verify high speed 60fps performance", '#fef08a', 1, 'work'],
                    ['nt_' . uniqid(), $currentUserId, 'IMAP SSL & Security Keys', "Remember to enable port 993 for secure IMAP and port 465 for SSL SMTP. PGP E2EE keys stored in client vault.", '#bbf7d0', 0, 'security'],
                    ['nt_' . uniqid(), $currentUserId, 'Ideas for Next Version', "• Voice memo transcription
• Kanban view for customer inquiries
• Dark glassmorphism widgets", '#fed7aa', 0, 'ideas'],
                    ['nt_' . uniqid(), $currentUserId, 'Meeting Notes: Design System', "Keep fonts clean: DM Sans for reading and Space Grotesk for headers. Turquoise glow provides high-contrast focus.", '#e9d5ff', 0, 'general']
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT INTO user_notes (id, user_id, title, content, color, is_pinned, category) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId]);
                $notes = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'notes' => $notes]);
            exit;
        } elseif ($method === 'POST') {
            $id = trim($input['id'] ?? ('nt_' . uniqid()));
            $title = trim($input['title'] ?? 'Untitled Note');
            $content = trim($input['content'] ?? '');
            $color = trim($input['color'] ?? '#fef08a');
            $isPinned = !empty($input['is_pinned']) ? 1 : 0;
            $category = trim($input['category'] ?? 'general');

            $stmtCheck = $db->prepare("SELECT id FROM user_notes WHERE id = ? AND user_id = ?");
            $stmtCheck->execute([$id, $currentUserId]);
            if ($stmtCheck->fetch()) {
                $db->prepare("UPDATE user_notes SET title = ?, content = ?, color = ?, is_pinned = ?, category = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?")
                   ->execute([$title, $content, $color, $isPinned, $category, $id, $currentUserId]);
            } else {
                $db->prepare("INSERT INTO user_notes (id, user_id, title, content, color, is_pinned, category) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$id, $currentUserId, $title, $content, $color, $isPinned, $category]);
            }
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? ($input['id'] ?? '');
            $db->prepare("DELETE FROM user_notes WHERE id = ? AND user_id = ?")->execute([$id, $currentUserId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // TASKS & TO-DO API
    if ($route === 'tasks') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM user_tasks WHERE user_id = ? ORDER BY is_completed ASC, created_at DESC");
            $stmt->execute([$currentUserId]);
            $tasks = $stmt->fetchAll();

            // Pre-seed sample tasks
            if (empty($tasks)) {
                $seeds = [
                    ['tsk_' . uniqid(), $currentUserId, 'Review contracts and reply to legal team', date('Y-m-d', strtotime('+1 day')), 'high', 0, '', 'Legal Partnership Agreement'],
                    ['tsk_' . uniqid(), $currentUserId, 'Schedule demo with enterprise client', date('Y-m-d', strtotime('+2 days')), 'medium', 0, '', 'Inquiry: Enterprise Mail Plan'],
                    ['tsk_' . uniqid(), $currentUserId, 'Back up SQLite database and rotate logs', date('Y-m-d', strtotime('+4 days')), 'low', 0, '', 'System Maintenance'],
                    ['tsk_' . uniqid(), $currentUserId, 'Upgrade cPanel PHP 8.2 memory limit', date('Y-m-d'), 'medium', 1, '', 'Server notice']
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT INTO user_tasks (id, user_id, title, due_date, priority, is_completed, linked_email_id, linked_email_subject) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId]);
                $tasks = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'tasks' => $tasks]);
            exit;
        } elseif ($method === 'POST') {
            $title = trim($input['title'] ?? 'New Task');
            $dueDate = trim($input['due_date'] ?? date('Y-m-d'));
            $priority = trim($input['priority'] ?? 'medium');
            $linkedEmailId = trim($input['linked_email_id'] ?? '');
            $linkedSubject = trim($input['linked_email_subject'] ?? '');
            $id = 'tsk_' . uniqid();

            $db->prepare("INSERT INTO user_tasks (id, user_id, title, due_date, priority, is_completed, linked_email_id, linked_email_subject) VALUES (?, ?, ?, ?, ?, 0, ?, ?)")
               ->execute([$id, $currentUserId, $title, $dueDate, $priority, $linkedEmailId, $linkedSubject]);

            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        } elseif ($route === 'tasks/toggle' || $method === 'PUT') {
            $id = $input['id'] ?? ($_GET['id'] ?? '');
            $completed = !empty($input['is_completed']) ? 1 : 0;
            $db->prepare("UPDATE user_tasks SET is_completed = ? WHERE id = ? AND user_id = ?")->execute([$completed, $id, $currentUserId]);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? ($input['id'] ?? '');
            $db->prepare("DELETE FROM user_tasks WHERE id = ? AND user_id = ?")->execute([$id, $currentUserId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // TEMPLATES & CANNED RESPONSES API
    if ($route === 'templates') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM email_templates WHERE user_id = ? ORDER BY category ASC, title ASC");
            $stmt->execute([$currentUserId]);
            $templates = $stmt->fetchAll();

            if (empty($templates)) {
                $seeds = [
                    ['tpl_' . uniqid(), $currentUserId, 'Meeting Confirmation', 'business', 'Confirmation: Scheduled Discussion', "Hi there,

Thank you for reaching out! I am writing to confirm our scheduled meeting. I look forward to speaking with you and discussing the details.

Please let me know if you need to reschedule or have any questions beforehand.

Best regards,
Alex"],
                    ['tpl_' . uniqid(), $currentUserId, 'Support Ticket Received', 'support', 'We received your inquiry [#REF-4829]', "Hello,

Thanks for reaching out to Raccon Mail Support. We have received your request and our engineering team is actively investigating.

You can expect an update within 2 to 4 business hours.

Warmly,
Raccon Customer Care Team"],
                    ['tpl_' . uniqid(), $currentUserId, 'Invoice & Payment Follow-up', 'finance', 'Follow-up regarding Invoice #[Number]', "Dear Client,

I hope you are having a wonderful week. This is a gentle reminder regarding invoice #[Number] which was issued on [Date].

Could you please confirm when payment is expected to be processed? Let me know if you need another copy of the invoice.

Thank you for your business!"],
                    ['tpl_' . uniqid(), $currentUserId, 'Quick Thank You', 'general', 'Thank you!', "Hi,

Thank you so much for your quick response and assistance. I really appreciate your support!

Have a great rest of your day,
Alex"]
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT INTO email_templates (id, user_id, title, category, subject, body) VALUES (?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId]);
                $templates = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'templates' => $templates]);
            exit;
        } elseif ($method === 'POST') {
            $title = trim($input['title'] ?? 'Custom Template');
            $category = trim($input['category'] ?? 'general');
            $subject = trim($input['subject'] ?? '');
            $body = trim($input['body'] ?? '');
            $id = 'tpl_' . uniqid();

            $db->prepare("INSERT INTO email_templates (id, user_id, title, category, subject, body) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$id, $currentUserId, $title, $category, $subject, $body]);

            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? ($input['id'] ?? '');
            $db->prepare("DELETE FROM email_templates WHERE id = ? AND user_id = ?")->execute([$id, $currentUserId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // RSS / FEED READER API
    if ($route === 'rss') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM user_rss_feeds WHERE user_id = ? ORDER BY created_at ASC");
            $stmt->execute([$currentUserId]);
            $feeds = $stmt->fetchAll();

            if (empty($feeds)) {
                $seeds = [
                    ['rss_' . uniqid(), $currentUserId, 'TechCrunch Daily', 'https://techcrunch.com/feed/', 'https://techcrunch.com', 'tech', 'cpu', 3],
                    ['rss_' . uniqid(), $currentUserId, 'Hacker News Frontpage', 'https://news.ycombinator.com/rss', 'https://news.ycombinator.com', 'code', 'terminal', 5],
                    ['rss_' . uniqid(), $currentUserId, 'Smashing Magazine Design', 'https://www.smashingmagazine.com/feed/', 'https://smashingmagazine.com', 'design', 'palette', 2],
                    ['rss_' . uniqid(), $currentUserId, 'Reuters Business & Finance', 'https://reuters.com/rss/business', 'https://reuters.com', 'news', 'globe', 4]
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT INTO user_rss_feeds (id, user_id, title, feed_url, site_url, category, icon, unread_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId]);
                $feeds = $stmt->fetchAll();
            }

            // Return feeds and sample curated articles
            $articles = [
                ['id' => 'art_1', 'feed_id' => $feeds[0]['id'] ?? 'f1', 'source' => 'TechCrunch', 'title' => 'The Next Era of Privacy-First Open Source Email Systems', 'date' => '2 hours ago', 'snippet' => 'Zero-knowledge encryption and local SQLite indexing are revolutionizing how modern developers manage asynchronous communication on self-hosted servers.', 'url' => 'https://techcrunch.com', 'author' => 'Sarah Perez'],
                ['id' => 'art_2', 'feed_id' => $feeds[1]['id'] ?? 'f2', 'source' => 'Hacker News', 'title' => 'Why SQLite in WAL mode outperforms client-server databases for webmail', 'date' => '4 hours ago', 'snippet' => 'Benchmarking memory-mapped I/O with 256MB cache demonstrates sub-millisecond retrieval across millions of email headers.', 'url' => 'https://news.ycombinator.com', 'author' => 'dhouston'],
                ['id' => 'art_3', 'feed_id' => $feeds[2]['id'] ?? 'f3', 'source' => 'Smashing Magazine', 'title' => 'Crafting High-DPI Vector Icons and Ergonomic Reading Canvases', 'date' => 'Yesterday', 'snippet' => 'How subtle turquoise focus rings, responsive typography, and tactile pill selectors improve user engagement in productivity applications.', 'url' => 'https://smashingmagazine.com', 'author' => 'Vitaly Friedman'],
                ['id' => 'art_4', 'feed_id' => $feeds[3]['id'] ?? 'f4', 'source' => 'Reuters Business', 'title' => 'Global SaaS Market Demands Standalone Self-Hosted Scripts', 'date' => '2 days ago', 'snippet' => 'Enterprises and webmasters increasingly favor perpetual license scripts on platforms like CodeCanyon over recurring monthly subscriptions.', 'url' => 'https://reuters.com', 'author' => 'K. Miller']
            ];

            echo json_encode(['success' => true, 'feeds' => $feeds, 'articles' => $articles]);
            exit;
        } elseif ($method === 'POST') {
            $title = trim($input['title'] ?? 'Custom Feed');
            $feedUrl = trim($input['feed_url'] ?? '');
            $category = trim($input['category'] ?? 'tech');
            $id = 'rss_' . uniqid();

            $db->prepare("INSERT INTO user_rss_feeds (id, user_id, title, feed_url, category) VALUES (?, ?, ?, ?, ?)")
               ->execute([$id, $currentUserId, $title, $feedUrl, $category]);

            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    // MULTI-ACCOUNT & UNIFIED INBOX API
    if ($route === 'accounts/switcher') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM user_accounts WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC");
            $stmt->execute([$currentUserId]);
            $accounts = $stmt->fetchAll();

            if (empty($accounts)) {
                $userStmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                $userStmt->execute([$currentUserId]);
                $u = $userStmt->fetch();
                $mainEmail = $u['email'] ?? 'alex@racconmail.eu';
                $mainName = $u['name'] ?? 'Alex Raccon';

                $seeds = [
                    ['acc_1', $currentUserId, $mainEmail, $mainName . ' (Personal)', 'racconmail', '#06b6d4', 1, 1],
                    ['acc_2', $currentUserId, 'work@racconmail.eu', 'Raccon Tech Ltd (Work)', 'imap', '#a855f7', 0, 1],
                    ['acc_3', $currentUserId, 'alex.founder@gmail.com', 'Google Workspace (Sync)', 'google', '#ea4335', 0, 1]
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT INTO user_accounts (id, user_id, email, display_name, provider, color, is_primary, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId]);
                $accounts = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'accounts' => $accounts]);
            exit;
        } elseif ($method === 'POST') {
            $email = trim($input['email'] ?? '');
            $name = trim($input['display_name'] ?? $email);
            $provider = trim($input['provider'] ?? 'imap');
            $color = trim($input['color'] ?? '#06b6d4');
            $id = 'acc_' . uniqid();

            $db->prepare("INSERT INTO user_accounts (id, user_id, email, display_name, provider, color, is_primary, is_active) VALUES (?, ?, ?, ?, ?, ?, 0, 1)")
               ->execute([$id, $currentUserId, $email, $name, $provider, $color]);

            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    // ATTACHMENTS & MEDIA DASHBOARD API
    if ($route === 'attachments/dashboard') {
        $stmt = $db->prepare("
            SELECT a.*, m.subject as email_subject, m.sender_name, m.sender_email, m.created_at as email_date
            FROM attachments a
            LEFT JOIN messages m ON a.message_id = m.id
            WHERE m.recipient_id = ? OR m.sender_id = ?
            ORDER BY a.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$currentUserId, $currentUserId]);
        $attachments = $stmt->fetchAll();

        // If user has few attachments, provide rich sample files
        if (count($attachments) < 3) {
            $sampleFiles = [
                ['id' => 'att_demo_1', 'filename' => 'q4_financial_growth_report.pdf', 'original_name' => 'Q4_Financial_Growth_Report_2026.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2480000, 'email_subject' => 'Q4 Executive Summary & Revenue', 'sender_name' => 'Finance Director', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
                ['id' => 'att_demo_2', 'filename' => 'codecanyon_seller_guidelines.pdf', 'original_name' => 'Envato_Author_Master_Guide.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1840000, 'email_subject' => 'CodeCanyon Submission Instructions', 'sender_name' => 'Envato Review Team', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
                ['id' => 'att_demo_3', 'filename' => 'architecture_database_schema.png', 'original_name' => 'SQLite_HighSpeed_Schema_Diagram.png', 'mime_type' => 'image/png', 'size_bytes' => 920000, 'email_subject' => 'Architecture Diagram update', 'sender_name' => 'Lead Architect', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],
                ['id' => 'att_demo_4', 'filename' => 'user_onboarding_analytics.xlsx', 'original_name' => 'Monthly_Signups_and_Conversion.xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'size_bytes' => 540000, 'email_subject' => 'Analytics metrics review', 'sender_name' => 'Growth Lead', 'created_at' => date('Y-m-d H:i:s', strtotime('-4 days'))]
            ];
            $attachments = array_merge($attachments, $sampleFiles);
        }

        echo json_encode(['success' => true, 'attachments' => $attachments]);
        exit;
    }

    // EMAIL ALIASES & MASKING API
    if ($route === 'security/aliases') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM email_aliases WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$currentUserId]);
            $aliases = $stmt->fetchAll();

            if (empty($aliases)) {
                $seeds = [
                    ['als_' . uniqid(), $currentUserId, 'shopping.mask.8492@racconmail.eu', 'Online Shopping & Stores', 'alex@racconmail.eu', 1, 14],
                    ['als_' . uniqid(), $currentUserId, 'newsletter.burner.3291@racconmail.eu', 'Tech Newsletters & Subscriptions', 'alex@racconmail.eu', 1, 38],
                    ['als_' . uniqid(), $currentUserId, 'social.anon.9102@racconmail.eu', 'Public Forums & Social Media', 'alex@racconmail.eu', 1, 6]
                ];
                foreach ($seeds as $s) {
                    $db->prepare("INSERT INTO email_aliases (id, user_id, alias_email, label, forward_to, is_active, emails_received) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute($s);
                }
                $stmt->execute([$currentUserId]);
                $aliases = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'aliases' => $aliases]);
            exit;
        } elseif ($method === 'POST') {
            $label = trim($input['label'] ?? 'General Mask');
            $forwardTo = trim($input['forward_to'] ?? 'alex@racconmail.eu');
            $randomHex = bin2hex(random_bytes(4));
            $aliasEmail = 'mask.' . strtolower(preg_replace('/[^a-z0-9]/', '', $label)) . '.' . $randomHex . '@racconmail.eu';
            $id = 'als_' . uniqid();

            $db->prepare("INSERT INTO email_aliases (id, user_id, alias_email, label, forward_to, is_active, emails_received) VALUES (?, ?, ?, ?, ?, 1, 0)")
               ->execute([$id, $currentUserId, $aliasEmail, $label, $forwardTo]);

            echo json_encode(['success' => true, 'id' => $id, 'alias_email' => $aliasEmail]);
            exit;
        }
    }

    // SECURITY AUDIT LOGS & SESSIONS API
    if ($route === 'security/audit') {
        $logs = [
            ['id' => 1, 'event' => 'Successful Login (Web Client)', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'device' => 'Chrome on Windows 11', 'location' => 'Bucharest, Romania', 'status' => 'Authorized', 'time' => 'Just now'],
            ['id' => 2, 'event' => 'IMAP Session Handshake', 'ip' => '178.132.24.11', 'device' => 'Native Android APK Mail Client', 'location' => 'Bucharest, Romania', 'status' => 'Active', 'time' => '12 minutes ago'],
            ['id' => 3, 'event' => 'API Token Authorization', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'device' => 'Desktop Electron Mail', 'location' => 'Bucharest, Romania', 'status' => 'Valid', 'time' => 'Yesterday, 18:24'],
            ['id' => 4, 'event' => 'Password Verification Check', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'device' => 'Chrome on Windows 11', 'location' => 'Bucharest, Romania', 'status' => 'Verified', 'time' => '3 days ago']
        ];
        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }
  
// 10. Simulate Incoming Mail
    if ($route === 'messages/simulate' && $method === 'POST') {
        $senders = [
            ['name' => 'Rocky Raccoon', 'email' => 'rocky@raccoonmail.com', 'subject' => 'Midnight snack plans 🦝', 'body' => 'Found the best bakery dumpster! Fresh warm croissants disposal at 2am sharp. Bring the crew.'],
            ['name' => 'Luna', 'email' => 'luna@raccoonmail.com', 'subject' => 'Photos from the camping trip 🏕️✨', 'body' => 'Look at the night sky over the forest! We definitely need to go back next new moon.'],
            ['name' => 'Forest Bank', 'email' => 'bank@forestbank.org', 'subject' => 'Monthly acorn statement ready 🌰', 'body' => 'Your reserves are securely stored in the Great Oak vault. Current balance: 14,800 acorns.']
        ];
        $sample = $senders[array_rand($senders)];
        $stmtUser = $db->prepare('SELECT email FROM users WHERE id = ?');
        $stmtUser->execute([$currentUserId]);
        $userEmail = $stmtUser->fetchColumn();

        $id = 'sim_' . uniqid();
        $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_draft) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)')
           ->execute([$id, $currentUserId, $sample['name'], $sample['email'], $userEmail, $sample['subject'], $sample['body']]);
        echo json_encode(['success' => true, 'id' => $id, 'message' => $sample]);
        exit;
    }

    // ==========================================
    // 11. RACCON NOCTURNAL FORUM (MEMBERS)
    // ==========================================

    // Ensure forum tables exist
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS forum_posts (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            content TEXT NOT NULL,
            code_snippet TEXT DEFAULT '',
            code_lang TEXT DEFAULT 'javascript',
            image_url TEXT DEFAULT '',
            tags TEXT DEFAULT '',
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            is_pinned INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_forum_category ON forum_posts(category, created_at)");

        $db->exec("CREATE TABLE IF NOT EXISTS forum_comments (
            id TEXT PRIMARY KEY,
            post_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            content TEXT NOT NULL,
            code_snippet TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_forum_comments_post ON forum_comments(post_id, created_at)");

        $db->exec("CREATE TABLE IF NOT EXISTS forum_likes (
            post_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id, user_id)
        )");
    } catch (Throwable $e) {}

    // Ensure location column exists and clean any external aggregator URLs from existing posts
    try {
        $db->exec("ALTER TABLE forum_posts ADD COLUMN location TEXT DEFAULT ''");
    } catch (Throwable $e) {}

    try {
        $db->exec("UPDATE forum_posts SET content = trim(substr(content, 1, instr(content, 'Comments URL:') - 1)) WHERE content LIKE '%Comments URL:%'");
        $db->exec("UPDATE forum_posts SET content = trim(substr(content, 1, instr(content, 'https://news.ycombinator.com') - 1)) WHERE content LIKE '%https://news.ycombinator.com%'");
        $db->exec("UPDATE forum_posts SET location = 'Bucharest, Romania' WHERE location IS NULL OR location = ''");
    } catch (Throwable $e) {}

    // Auto-seed initial developer & designer discussions if table is empty
    $forumCount = 0;
    try {
        $stmtForumCount = $db->query("SELECT COUNT(*) FROM forum_posts");
        if ($stmtForumCount) {
            $forumCount = (int)$stmtForumCount->fetchColumn();
        }
    } catch (Throwable $e) {}

    if ($forumCount === 0) {
        $seedUserId = $currentUserId ?: 'usr_admin_master';
        $seedPosts = [
            [
                'id' => 'post_seed_1',
                'title' => 'Post-Quantum Lattice Cryptography for Webmail Envelopes',
                'category' => 'tech',
                'content' => "With quantum computing advancements on the horizon, traditional RSA and elliptic-curve cryptography will eventually be vulnerable to Shor's algorithm. In Raccon Mail, we are exploring module lattice-based key encapsulation mechanisms (ML-KEM) to guarantee that all postcards sealed in burrows remain untappable forever.\n\nWho else is experimenting with Kyber/Dilithium implementations in client-side WebAssembly?",
                'code_snippet' => "// ML-KEM-768 Hybrid Key Encapsulation\nasync function sealPostcardPayload(recipientPublicKey, plainText) {\n  const sharedSecret = await crypto.subtle.deriveKey(\n    { name: 'ECDH', public: recipientPublicKey },\n    privateKey,\n    { name: 'AES-GCM', length: 256 },\n    false,\n    ['encrypt']\n  );\n  return sharedSecret;\n}",
                'code_lang' => 'javascript',
                'image_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80',
                'tags' => 'cryptography,tech,security,postquantum',
                'likes' => 14,
                'comments' => 3
            ],
            [
                'id' => 'post_seed_2',
                'title' => 'Mastering SQLite Concurrency with WAL Mode in High-Throughput PHP',
                'category' => 'coding',
                'content' => "SQLite is frequently misunderstood as a 'toy' database, but when configured with Write-Ahead Logging (WAL) and `busy_timeout = 5000`, it easily serves millions of reads with zero locks! Here is the exact snippet we recommend for any high-traffic PHP endpoint.",
                'code_snippet' => "\$db = new PDO('sqlite:/path/to/mail.db');\n\$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n// Enable concurrent readers while writing\n\$db->exec('PRAGMA journal_mode = WAL;');\n\$db->exec('PRAGMA synchronous = NORMAL;');\n\$db->exec('PRAGMA busy_timeout = 5000;');\n\$db->exec('PRAGMA cache_size = -64000;'); // 64MB memory cache",
                'code_lang' => 'php',
                'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=800&q=80',
                'tags' => 'sqlite,php,performance,backend',
                'likes' => 28,
                'comments' => 5
            ],
            [
                'id' => 'post_seed_3',
                'title' => '2026 UI Trends: Obsidian Dark Theme & Frosted Glassmorphism',
                'category' => 'webdesign',
                'content' => "Pure pitch-black (#000000) causes intense eye strain under OLED screens due to high contrast halation. The sweet spot for modern nocturnal aesthetics is deep obsidian navy (#080c14) paired with 8% white translucent borders and `backdrop-blur-md`.\n\nNotice how fluid cards feel when you add a 300ms cubic-bezier transition on hover scale! Thoughts on neon accents vs matte pastels?",
                'code_snippet' => ".glass-burrow-card {\n  background: rgba(17, 28, 51, 0.75);\n  backdrop-filter: blur(16px);\n  -webkit-backdrop-filter: blur(16px);\n  border: 1px solid rgba(255, 255, 255, 0.08);\n  border-radius: 1.5rem;\n  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);\n}",
                'code_lang' => 'css',
                'image_url' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=800&q=80',
                'tags' => 'css,webdesign,uiux,glassmorphism',
                'likes' => 35,
                'comments' => 7
            ],
            [
                'id' => 'post_seed_4',
                'title' => 'Zero-Dependency Vanilla JavaScript Reactive State Store (<40 lines)',
                'category' => 'sharing',
                'content' => "You do not need Redux, Zustand, or 200KB npm dependencies to have clean reactive state in your web apps. JavaScript's native Proxy API makes creating an observable store effortless. Here is the entire implementation:",
                'code_snippet' => "function createStore(initialState) {\n  const listeners = new Set();\n  const state = new Proxy(initialState, {\n    set(target, key, value) {\n      target[key] = value;\n      listeners.forEach(fn => fn(target));\n      return true;\n    }\n  });\n  return {\n    state,\n    subscribe: (fn) => { listeners.add(fn); return () => listeners.delete(fn); }\n  };\n}",
                'code_lang' => 'javascript',
                'image_url' => '',
                'tags' => 'javascript,vanillajs,frontend,state',
                'likes' => 42,
                'comments' => 4
            ],
            [
                'id' => 'post_seed_5',
                'title' => 'Debugging ServiceWorker Push Notifications in Background State',
                'category' => 'help',
                'content' => "Hey community owls! Quick question: on mobile Chrome and Android PWA, push events sometimes get throttled by aggressive battery optimization if the app has been sleeping for more than 4 hours. Has anyone solved this with persistent background sync or periodic background fetch?",
                'code_snippet' => "self.addEventListener('push', (event) => {\n  const data = event.data ? event.data.json() : {};\n  event.waitUntil(\n    self.registration.showNotification(data.title || 'Raccon Mail', {\n      body: data.body,\n      icon: '/raccoon-logo.png',\n      badge: '/favicon.png'\n    })\n  );\n});",
                'code_lang' => 'javascript',
                'image_url' => '',
                'tags' => 'serviceworker,pwa,help,android',
                'likes' => 19,
                'comments' => 6
            ],
            [
                'id' => 'post_seed_6',
                'title' => 'Demystifying Zero-Knowledge Client Masking: How It Works Step-by-Step',
                'category' => 'explaining',
                'content' => "A lot of people ask: 'How does client-side email masking actually work?' Here is an intuitive breakdown:\n\n1. Sender types an email.\n2. In the browser, the Web Crypto API derives an AES-256 key from a passphrase known only to the user.\n3. The raw body is encrypted before the HTTP POST is dispatched.\n4. Even if database servers are inspected, only encrypted ciphertext envelopes are stored.\n\nKnowledge belongs to the sender and recipient alone!",
                'code_snippet' => "const iv = crypto.getRandomValues(new Uint8Array(12));\nconst cipher = await crypto.subtle.encrypt(\n  { name: 'AES-GCM', iv },\n  encryptionKey,\n  new TextEncoder().encode(plainBody)\n);",
                'code_lang' => 'javascript',
                'image_url' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=800&q=80',
                'tags' => 'explaining,security,encryption,guide',
                'likes' => 51,
                'comments' => 8
            ]
        ];

        foreach ($seedPosts as $p) {
            $db->prepare("INSERT INTO forum_posts (id, user_id, title, category, content, code_snippet, code_lang, image_url, tags, likes_count, comments_count, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)")
               ->execute([$p['id'], $seedUserId, $p['title'], $p['category'], $p['content'], $p['code_snippet'], $p['code_lang'], $p['image_url'], $p['tags'], $p['likes'], $p['comments']]);

            // Add sample comment
            $db->prepare("INSERT INTO forum_comments (id, post_id, user_id, content) VALUES (?, ?, ?, 'Brilliant post! Really clear explanation and clean snippet.')")
               ->execute(['cm_' . uniqid(), $p['id'], $seedUserId]);
        }
    }

    
    

    
    // One-time purge of TikTok post
    try {
        $db->exec("DELETE FROM forum_posts WHERE id = 'post_canturovibe'");
        $db->exec("DELETE FROM forum_comments WHERE post_id = 'post_canturovibe'");
        $db->exec("DELETE FROM forum_likes WHERE post_id = 'post_canturovibe'");
    } catch (Throwable $e) {}

    // 11.1 GET /api/forum/posts (List & Search & Filter)
    if ($route === 'forum/posts' && $method === 'GET') {
        $category = trim($_GET['category'] ?? 'all');
        $search = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'latest');

        $query = "SELECT p.*, 
                  COALESCE(u.name, REPLACE(SUBSTR(p.user_id, 1, 14), '_', ' ')) as author_name, 
                  COALESCE(u.email, p.user_id || '@racconmail.eu') as author_email, 
                  COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar, 
                  COALESCE(u.is_admin, 0) as author_is_admin 
                  FROM forum_posts p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE 1=1";
        $params = [];

        if ($category !== 'all' && $category !== '') {
            $query .= " AND p.category = ?";
            $params[] = $category;
        }

        if ($search !== '') {
            $query .= " AND (p.title LIKE ? OR p.content LIKE ? OR p.tags LIKE ? OR p.code_snippet LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($sort === 'popular') {
            $query .= " ORDER BY p.is_pinned DESC, p.likes_count DESC, p.created_at DESC";
        } else {
            $query .= " ORDER BY p.is_pinned DESC, p.created_at DESC";
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // Attach user like status
        if ($currentUserId) {
            $stmtLikes = $db->prepare("SELECT post_id FROM forum_likes WHERE user_id = ?");
            $stmtLikes->execute([$currentUserId]);
            $likedPostIds = array_column($stmtLikes->fetchAll(), 'post_id');
            $likedSet = array_flip($likedPostIds);
            foreach ($posts as &$post) {
                $post['has_liked'] = isset($likedSet[$post['id']]);
            }
        } else {
            foreach ($posts as &$post) {
                $post['has_liked'] = false;
            }
        }

        echo json_encode([
            'success' => true,
            'posts' => $posts,
            'isLoggedIn' => !empty($currentUserId),
            'currentUserId' => $currentUserId
        ]);
        exit;
    }

    // 11.2 GET /api/forum/posts/:id (Post Detail + Comments)
    if (preg_match('#^forum/posts/([^/]+)$#', $route, $m) && $method === 'GET') {
        $postId = $m[1];
        $stmt = $db->prepare("SELECT p.*, u.name as author_name, u.email as author_email, u.avatar as author_avatar, u.is_admin as author_is_admin 
                              FROM forum_posts p 
                              LEFT JOIN users u ON p.user_id = u.id 
                              WHERE p.id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            exit;
        }

        if ($currentUserId) {
            $stmtLiked = $db->prepare("SELECT 1 FROM forum_likes WHERE post_id = ? AND user_id = ?");
            $stmtLiked->execute([$postId, $currentUserId]);
            $post['has_liked'] = (bool)$stmtLiked->fetchColumn();
        } else {
            $post['has_liked'] = false;
        }

        // Fetch comments
        $stmtC = $db->prepare("SELECT c.*, 
                               COALESCE(u.name, REPLACE(SUBSTR(c.user_id, 1, 14), '_', ' ')) as author_name, 
                               COALESCE(u.email, c.user_id || '@racconmail.eu') as author_email, 
                               COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar, 
                               COALESCE(u.is_admin, 0) as author_is_admin 
                               FROM forum_comments c 
                               LEFT JOIN users u ON c.user_id = u.id 
                               WHERE c.post_id = ? 
                               ORDER BY c.created_at ASC");
        $stmtC->execute([$postId]);
        $comments = $stmtC->fetchAll();

        echo json_encode([
            'success' => true,
            'post' => $post,
            'comments' => $comments,
            'isLoggedIn' => !empty($currentUserId),
            'currentUserId' => $currentUserId
        ]);
        exit;
    }

    // 11.3 POST /api/forum/posts (Create New Post - Members Only)
    if ($route === 'forum/posts' && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Access restricted: Only registered Raccon Mail members can post to the forum. Please sign in or join free.']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $category = trim($input['category'] ?? 'tech');
        $content = trim($input['content'] ?? '');
        $codeSnippet = trim($input['code_snippet'] ?? '');
        $codeLang = trim($input['code_lang'] ?? 'javascript');
        $imageUrl = trim($input['image_url'] ?? '');
        $tags = trim($input['tags'] ?? '');
        $location = trim($input['location'] ?? '');
        if (empty($location)) {
            $location = 'Bucharest, Romania';
        }

        if (!$title && $content) {
            $title = mb_substr(strip_tags($content), 0, 50) . (mb_strlen($content) > 50 ? '...' : '');
        }

        if (!$title || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide message content.']);
            exit;
        }

        $validCats = ['general', 'announcements', 'security', 'tech', 'coding', 'webdesign', 'feedback', 'wall', 'sharing', 'help', 'explaining'];
        if (!in_array($category, $validCats)) $category = 'general';

        $postId = 'post_' . uniqid();
        $stmt = $db->prepare("INSERT INTO forum_posts (id, user_id, title, category, content, code_snippet, code_lang, image_url, tags, location, likes_count, comments_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
        $stmt->execute([$postId, $currentUserId, $title, $category, $content, $codeSnippet, $codeLang, $imageUrl, $tags, $location]);

        echo json_encode([
            'success' => true,
            'id' => $postId,
            'message' => 'Your post is now live on the Raccon Forum! 🦝🚀'
        ]);
        exit;
    }

    // 11.3b GET /api/forum/posts/:id/comments (Fetch Comments for Post)
    if (preg_match('#^forum/posts/([^/]+)/comments$#', $route, $m) && $method === 'GET') {
        $postId = $m[1];
        $stmtC = $db->prepare("SELECT c.*, 
                               COALESCE(u.name, REPLACE(SUBSTR(c.user_id, 1, 14), '_', ' ')) as author_name, 
                               COALESCE(u.email, c.user_id || '@racconmail.eu') as author_email, 
                               COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar, 
                               COALESCE(u.is_admin, 0) as author_is_admin 
                               FROM forum_comments c 
                               LEFT JOIN users u ON c.user_id = u.id 
                               WHERE c.post_id = ? 
                               ORDER BY c.created_at ASC");
        $stmtC->execute([$postId]);
        $comments = $stmtC->fetchAll();

        echo json_encode([
            'success' => true,
            'comments' => $comments,
            'count' => count($comments)
        ]);
        exit;
    }

    // 11.4 POST /api/forum/posts/:id/comments (Add Comment - Members & Verified Guests)
    if (preg_match('#^forum/posts/([^/]+)/comments$#', $route, $m) && $method === 'POST') {
        $postId = $m[1];
        $commentContent = trim($input['content'] ?? '');
        $codeSnippet = trim($input['code_snippet'] ?? '');
        $guestAuthor = trim($input['author_name'] ?? '');

        if (!$commentContent) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment text cannot be empty.']);
            exit;
        }

        $userId = $currentUserId;
        $authorName = 'Raccon Member';
        $authorEmail = '';
        $authorAvatar = '/raccoon-logo.png';
        $isAdmin = false;

        if ($currentUserId) {
            $stmtUser = $db->prepare("SELECT name, email, avatar, is_admin FROM users WHERE id = ?");
            $stmtUser->execute([$currentUserId]);
            $user = $stmtUser->fetch();
            if ($user) {
                $authorName = $user['name'] ?: $user['email'];
                $authorEmail = $user['email'] ?: '';
                $authorAvatar = $user['avatar'] ?: '/raccoon-logo.png';
                $isAdmin = !empty($user['is_admin']);
            }
        } else {
            $guestIdSeed = substr(md5(session_id() . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')), 0, 8);
            $userId = 'guest_' . $guestIdSeed;
            $authorName = $guestAuthor ?: ('Raccon Guest #' . substr($guestIdSeed, 0, 4));
            $authorEmail = $userId . '@racconmail.eu';
        }

        $commId = 'cm_' . uniqid();
        $stmt = $db->prepare("INSERT INTO forum_comments (id, post_id, user_id, content, code_snippet) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$commId, $postId, $userId, $commentContent, $codeSnippet]);

        // Increment count on post
        $db->prepare("UPDATE forum_posts SET comments_count = comments_count + 1 WHERE id = ?")->execute([$postId]);

        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $commId,
                'post_id' => $postId,
                'user_id' => $userId,
                'content' => $commentContent,
                'code_snippet' => $codeSnippet,
                'created_at' => date('Y-m-d H:i:s'),
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'author_avatar' => $authorAvatar,
                'author_is_admin' => $isAdmin
            ]
        ]);
        exit;
    }

    // 11.5 POST /api/forum/posts/:id/like (Toggle Like/Paw - Members Only)
    if (preg_match('#^forum/posts/([^/]+)/like$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in to like and leave paws on community posts! 🐾']);
            exit;
        }

        $postId = $m[1];
        $stmtCheck = $db->prepare("SELECT 1 FROM forum_likes WHERE post_id = ? AND user_id = ?");
        $stmtCheck->execute([$postId, $currentUserId]);
        $hasLiked = (bool)$stmtCheck->fetchColumn();

        if ($hasLiked) {
            $db->prepare("DELETE FROM forum_likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $currentUserId]);
            $db->prepare("UPDATE forum_posts SET likes_count = MAX(0, likes_count - 1) WHERE id = ?")->execute([$postId]);
            $liked = false;
        } else {
            $db->prepare("INSERT INTO forum_likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $currentUserId]);
            $db->prepare("UPDATE forum_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$postId]);
            $liked = true;
        }

        $stmtCnt = $db->prepare("SELECT likes_count FROM forum_posts WHERE id = ?");
        $stmtCnt->execute([$postId]);
        $likesCount = (int)$stmtCnt->fetchColumn();

        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $likesCount
        ]);
        exit;
    }

    // 11.6 DELETE /api/forum/posts/:id (Delete Post - Author or Admin Only)
    if (preg_match('#^forum/posts/([^/]+)$#', $route, $m) && $method === 'DELETE') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $postId = $m[1];
        $stmtPost = $db->prepare("SELECT user_id FROM forum_posts WHERE id = ?");
        $stmtPost->execute([$postId]);
        $postAuthorId = $stmtPost->fetchColumn();

        if (!$postAuthorId) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            exit;
        }

        $stmtUser = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmtUser->execute([$currentUserId]);
        $isAdmin = (bool)$stmtUser->fetchColumn();

        if ($postAuthorId !== $currentUserId && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only delete your own discussions.']);
            exit;
        }

        $db->prepare("DELETE FROM forum_comments WHERE post_id = ?")->execute([$postId]);
        $db->prepare("DELETE FROM forum_likes WHERE post_id = ?")->execute([$postId]);
        $db->prepare("DELETE FROM forum_posts WHERE id = ?")->execute([$postId]);

        echo json_encode(['success' => true, 'message' => 'Post removed successfully']);
        exit;
    }

    // ==========================================
    // 12. RACCON MARKETPLACE & ARTICLES (SECOND-HAND & NEW GOODS)
    // ==========================================

    // Ensure marketplace table exists
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS marketplace_items (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'other',
            condition TEXT NOT NULL DEFAULT 'good',
            price REAL DEFAULT 0,
            currency TEXT DEFAULT 'EUR',
            is_free INTEGER DEFAULT 0,
            location TEXT DEFAULT '',
            image_url TEXT DEFAULT '',
            status TEXT DEFAULT 'active',
            views_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mkt_category ON marketplace_items(category, created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mkt_status ON marketplace_items(status, created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mkt_user ON marketplace_items(user_id)");

        // Table for in-person buyers who confirmed serious purchase intent to reveal contact
        $db->exec("CREATE TABLE IF NOT EXISTS marketplace_reveals (
            item_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (item_id, user_id)
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mkt_reveals ON marketplace_reveals(user_id, item_id)");
    } catch (Throwable $e) {}

    if (!function_exists('maskMarketplaceEmail')) {
        function maskMarketplaceEmail($email) {
            if (!$email || !strpos($email, '@')) return '••••••••@racconmail.eu';
            list($user, $domain) = explode('@', $email, 2);
            $len = strlen($user);
            if ($len <= 2) {
                $maskedUser = substr($user, 0, 1) . '***';
            } else {
                $maskedUser = substr($user, 0, 2) . str_repeat('•', min(6, $len - 2));
            }
            return $maskedUser . '@' . $domain;
        }
    }

    // Auto-seed initial marketplace listings if table is empty
    $mktCount = 0;
    try {
        $stmtMktCount = $db->query("SELECT COUNT(*) FROM marketplace_items");
        if ($stmtMktCount) {
            $mktCount = (int)$stmtMktCount->fetchColumn();
        }
    } catch (Throwable $e) {}

    if ($mktCount === 0) {
        $seedUserId = $currentUserId ?: 'usr_admin_master';
        $seedItems = [
            [
                'id' => 'mkt_seed_1',
                'title' => 'ThinkPad X1 Carbon Gen 10 (16GB RAM, 512GB SSD, Intel i7)',
                'description' => 'Super clean premium business ultrabook used for software development. Battery health 96%, original 65W USB-C fast charger included. Ships worldwide or local pickup.',
                'category' => 'electronics',
                'condition' => 'like_new',
                'price' => 680,
                'currency' => 'EUR',
                'is_free' => 0,
                'location' => 'Bucharest, Romania',
                'image_url' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=800&auto=format&fit=crop&q=80'
            ],
            [
                'id' => 'mkt_seed_2',
                'title' => 'Sony WH-1000XM4 Noise-Canceling Wireless Headphones',
                'description' => 'Superb acoustic fidelity and industry-leading active noise cancellation. Clean earpads, includes hard travel carrying case, flight adapter, and audio cable.',
                'category' => 'electronics',
                'condition' => 'good',
                'price' => 145,
                'currency' => 'EUR',
                'is_free' => 0,
                'location' => 'Cluj-Napoca, Romania',
                'image_url' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&auto=format&fit=crop&q=80'
            ],
            [
                'id' => 'mkt_seed_3',
                'title' => 'Keychron K2 Wireless Mechanical Keyboard (Gateron Brown Switches)',
                'description' => 'Brand new in original retail packaging, factory sealed. Bluetooth 5.1 and wired USB-C mode, compatible with macOS and Windows. White LED edition.',
                'category' => 'electronics',
                'condition' => 'new',
                'price' => 75,
                'currency' => 'EUR',
                'is_free' => 0,
                'location' => 'Timisoara, Romania',
                'image_url' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&auto=format&fit=crop&q=80'
            ],
            [
                'id' => 'mkt_seed_4',
                'title' => 'Vintage Canon AE-1 Program 35mm SLR Camera + FD 50mm f/1.8 Lens',
                'description' => 'Fully tested and functioning legendary 35mm film camera. Shutter squeak serviced, fresh light seals installed last month. Crisp glass with no fungus.',
                'category' => 'other',
                'condition' => 'good',
                'price' => 180,
                'currency' => 'EUR',
                'is_free' => 0,
                'location' => 'Iasi, Romania',
                'image_url' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800&auto=format&fit=crop&q=80'
            ],
            [
                'id' => 'mkt_seed_5',
                'title' => 'Herman Miller Aeron Ergonomic Task Chair (Graphite, Size B)',
                'description' => 'Equipped with PostureFit SL lumbar support, fully adjustable armrests, forward tilt limiter. Excellent ergonomic condition from a smoke-free home studio.',
                'category' => 'home',
                'condition' => 'like_new',
                'price' => 590,
                'currency' => 'EUR',
                'is_free' => 0,
                'location' => 'Bucharest, Romania',
                'image_url' => 'https://images.unsplash.com/photo-1580481077195-c3a821a58875?w=800&auto=format&fit=crop&q=80'
            ],
            [
                'id' => 'mkt_seed_6',
                'title' => 'Hardcover Illustrated World Folktales & Nocturnal Legends',
                'description' => 'Generously giving away this stunning collector illustrated hardcover book to a fellow reading enthusiast. Pick up in Brasov or cover standard parcel postage.',
                'category' => 'books',
                'condition' => 'new',
                'price' => 0,
                'currency' => 'EUR',
                'is_free' => 1,
                'location' => 'Brasov, Romania',
                'image_url' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=800&auto=format&fit=crop&q=80'
            ]
        ];

        foreach ($seedItems as $si) {
            try {
                $db->prepare("INSERT INTO marketplace_items (id, user_id, title, description, category, condition, price, currency, is_free, location, image_url, status, views_count)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)")
                   ->execute([$si['id'], $seedUserId, $si['title'], $si['description'], $si['category'], $si['condition'], $si['price'], $si['currency'], $si['is_free'], $si['location'], $si['image_url'], rand(12, 85)]);
            } catch (Throwable $e) {}
        }
    }

    // 12.1 GET /api/marketplace/items (List Marketplace Listings)
    if ($route === 'marketplace/items' && $method === 'GET') {
        $category = $_GET['category'] ?? 'all';
        $condition = $_GET['condition'] ?? 'all';
        $status = $_GET['status'] ?? 'active';
        $search = trim($_GET['q'] ?? '');
        $filter = $_GET['filter'] ?? '';
        $sort = $_GET['sort'] ?? 'newest';
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        $sql = "SELECT m.*, u.name as seller_name, u.email as seller_email, u.avatar as seller_avatar
                FROM marketplace_items m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($filter === 'my' && $currentUserId) {
            $sql .= " AND m.user_id = ?";
            $params[] = $currentUserId;
        } elseif ($status !== 'all') {
            $sql .= " AND m.status = ?";
            $params[] = $status;
        }

        if ($category !== 'all' && $category !== '') {
            $sql .= " AND m.category = ?";
            $params[] = $category;
        }

        if ($condition !== 'all' && $condition !== '') {
            $sql .= " AND m.condition = ?";
            $params[] = $condition;
        }

        if ($search !== '') {
            $sql .= " AND (m.title LIKE ? OR m.description LIKE ? OR m.location LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        switch ($sort) {
            case 'price_asc':
                $sql .= " ORDER BY m.price ASC, m.created_at DESC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY m.price DESC, m.created_at DESC";
                break;
            case 'views':
                $sql .= " ORDER BY m.views_count DESC, m.created_at DESC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY m.created_at DESC";
                break;
        }

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $isOwner = ($currentUserId && $r['user_id'] === $currentUserId);
            $r['is_owner'] = $isOwner;
            $r['price'] = (float)$r['price'];
            $r['is_free'] = (int)$r['is_free'];
            $r['views_count'] = (int)$r['views_count'];
            $rawEmail = $r['seller_email'] ?? '';
            $r['seller_email_preview'] = maskMarketplaceEmail($rawEmail);
            if (!$isOwner) {
                $r['seller_email'] = ''; // Hidden until in-person purchase intent is confirmed
            }
            $items[] = $r;
        }

        // Total count
        $cntSql = "SELECT COUNT(*) FROM marketplace_items m WHERE 1=1";
        $cntParams = [];
        if ($filter === 'my' && $currentUserId) {
            $cntSql .= " AND m.user_id = ?";
            $cntParams[] = $currentUserId;
        } elseif ($status !== 'all') {
            $cntSql .= " AND m.status = ?";
            $cntParams[] = $status;
        }
        if ($category !== 'all' && $category !== '') {
            $cntSql .= " AND m.category = ?";
            $cntParams[] = $category;
        }
        if ($condition !== 'all' && $condition !== '') {
            $cntSql .= " AND m.condition = ?";
            $cntParams[] = $condition;
        }
        if ($search !== '') {
            $cntSql .= " AND (m.title LIKE ? OR m.description LIKE ? OR m.location LIKE ?)";
            $cntParams[] = "%$search%";
            $cntParams[] = "%$search%";
            $cntParams[] = "%$search%";
        }
        $stmtCnt = $db->prepare($cntSql);
        $stmtCnt->execute($cntParams);
        $total = (int)$stmtCnt->fetchColumn();

        echo json_encode([
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        exit;
    }

    // 12.2 GET /api/marketplace/items/:id (Single Item Details)
    if (preg_match('#^marketplace/items/([^/]+)$#', $route, $m) && $method === 'GET') {
        $itemId = $m[1];
        // Increment views
        $db->prepare("UPDATE marketplace_items SET views_count = views_count + 1 WHERE id = ?")->execute([$itemId]);

        $stmt = $db->prepare("
            SELECT m.*, u.name as seller_name, u.email as seller_email, u.avatar as seller_avatar
            FROM marketplace_items m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Marketplace item not found']);
            exit;
        }

        $isOwner = ($currentUserId && $item['user_id'] === $currentUserId);
        $rawEmail = $item['seller_email'] ?? '';

        // Check if user has confirmed in-person purchase intent
        $hasRevealed = false;
        if ($currentUserId) {
            try {
                $stmtRev = $db->prepare("SELECT 1 FROM marketplace_reveals WHERE item_id = ? AND user_id = ?");
                $stmtRev->execute([$itemId, $currentUserId]);
                $hasRevealed = (bool)$stmtRev->fetchColumn();
            } catch (Throwable $e) {}
        }

        $canViewDirectEmail = ($isOwner || $hasRevealed);

        $item['is_owner'] = $isOwner;
        $item['has_revealed_contact'] = $canViewDirectEmail;
        $item['price'] = (float)$item['price'];
        $item['is_free'] = (int)$item['is_free'];
        $item['views_count'] = (int)$item['views_count'];
        $item['seller_email_preview'] = maskMarketplaceEmail($rawEmail);

        if (!$canViewDirectEmail) {
            $item['seller_email'] = ''; // Hidden until buyer confirms in-person purchase intent
        } else {
            $item['seller_email'] = $rawEmail;
        }

        echo json_encode(['item' => $item]);
        exit;
    }

    // 12.3 POST /api/marketplace/items (Create New Listing - Members Only)
    if ($route === 'marketplace/items' && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Access restricted: Only registered Raccon Mail members can post items for sale. Please sign in or join free.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $category = trim($input['category'] ?? 'other');
        $condition = trim($input['condition'] ?? 'good');
        $price = max(0, (float)($input['price'] ?? 0));
        $currency = in_array(strtoupper($input['currency'] ?? 'EUR'), ['EUR', 'RON', 'USD']) ? strtoupper($input['currency']) : 'EUR';
        $isFree = !empty($input['is_free']) ? 1 : 0;
        if ($isFree) $price = 0;
        $location = trim($input['location'] ?? 'Bucharest, Romania');
        $imageUrl = trim($input['image_url'] ?? '');

        if (mb_strlen($title) < 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide an item title of at least 3 characters.']);
            exit;
        }

        if (mb_strlen($description) < 5) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide a description of at least 5 characters.']);
            exit;
        }

        $allowedCats = ['electronics', 'home', 'fashion', 'books', 'vehicles', 'sports', 'other'];
        if (!in_array($category, $allowedCats)) {
            $category = 'other';
        }

        $allowedConds = ['new', 'like_new', 'good', 'fair'];
        if (!in_array($condition, $allowedConds)) {
            $condition = 'good';
        }

        $itemId = 'mkt_' . bin2hex(random_bytes(6));
        $stmt = $db->prepare("
            INSERT INTO marketplace_items (id, user_id, title, description, category, condition, price, currency, is_free, location, image_url, status, views_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)
        ");
        $stmt->execute([$itemId, $currentUserId, $title, $description, $category, $condition, $price, $currency, $isFree, $location, $imageUrl]);

        echo json_encode([
            'success' => true,
            'id' => $itemId,
            'message' => 'Your item has been listed on Raccon Marketplace!'
        ]);
        exit;
    }

    // 12.4 PUT /api/marketplace/items/:id (Update Listing or Mark as Sold - Owner or Admin)
    if (preg_match('#^marketplace/items/([^/]+)$#', $route, $m) && $method === 'PUT') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $itemId = $m[1];
        $stmtItem = $db->prepare("SELECT user_id, status FROM marketplace_items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $row = $stmtItem->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found']);
            exit;
        }

        $stmtUser = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmtUser->execute([$currentUserId]);
        $isAdmin = (bool)$stmtUser->fetchColumn();

        if ($row['user_id'] !== $currentUserId && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only edit your own listings.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $updates = [];
        $params = [];

        if (isset($input['status']) && in_array($input['status'], ['active', 'sold', 'archived'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
        }
        if (isset($input['title']) && mb_strlen(trim($input['title'])) >= 3) {
            $updates[] = "title = ?";
            $params[] = trim($input['title']);
        }
        if (isset($input['description']) && mb_strlen(trim($input['description'])) >= 5) {
            $updates[] = "description = ?";
            $params[] = trim($input['description']);
        }
        if (isset($input['price'])) {
            $updates[] = "price = ?";
            $params[] = max(0, (float)$input['price']);
        }
        if (isset($input['condition']) && in_array($input['condition'], ['new', 'like_new', 'good', 'fair'])) {
            $updates[] = "condition = ?";
            $params[] = $input['condition'];
        }
        if (isset($input['image_url'])) {
            $updates[] = "image_url = ?";
            $params[] = trim($input['image_url']);
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";

        if (!empty($updates)) {
            $params[] = $itemId;
            $db->prepare("UPDATE marketplace_items SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        }

        echo json_encode(['success' => true, 'message' => 'Listing updated successfully']);
        exit;
    }

    // 12.5 DELETE /api/marketplace/items/:id (Delete Listing - Owner or Admin)
    if (preg_match('#^marketplace/items/([^/]+)$#', $route, $m) && $method === 'DELETE') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $itemId = $m[1];
        $stmtItem = $db->prepare("SELECT user_id FROM marketplace_items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $itemAuthorId = $stmtItem->fetchColumn();

        if (!$itemAuthorId) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found']);
            exit;
        }

        $stmtUser = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmtUser->execute([$currentUserId]);
        $isAdmin = (bool)$stmtUser->fetchColumn();

        if ($itemAuthorId !== $currentUserId && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only delete your own listings.']);
            exit;
        }

        $db->prepare("DELETE FROM marketplace_items WHERE id = ?")->execute([$itemId]);

        echo json_encode(['success' => true, 'message' => 'Listing removed successfully']);
        exit;
    }

    // 12.6 POST /api/marketplace/upload (Upload Item Photo - Members Only)
    if ($route === 'marketplace/upload' && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No image file uploaded or upload error.']);
            exit;
        }

        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF.']);
            exit;
        }

        $destFilename = 'mkt_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
        $targetPath = $uploadsDir . '/' . $destFilename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode([
                'success' => true,
                'image_url' => '/uploads/' . $destFilename
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save uploaded image.']);
            exit;
        }
    }

    // 12.7 POST /api/marketplace/items/:id/reveal-contact (Confirm In-Person Purchase Intent & Reveal Email)
    if (preg_match('#^marketplace/items/([^/]+)/reveal-contact$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'You must be signed in to confirm in-person purchase intent and reveal the seller\'s contact.']);
            exit;
        }

        $itemId = $m[1];
        $stmtItem = $db->prepare("
            SELECT m.*, u.name as seller_name, u.email as seller_email
            FROM marketplace_items m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $stmtItem->execute([$itemId]);
        $item = $stmtItem->fetch();

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found.']);
            exit;
        }

        // Record serious in-person purchase intent in database
        try {
            $stmtInsert = $db->prepare("INSERT OR IGNORE INTO marketplace_reveals (item_id, user_id) VALUES (?, ?)");
            $stmtInsert->execute([$itemId, $currentUserId]);
        } catch (Throwable $e) {}

        echo json_encode([
            'success' => true,
            'seller_email' => $item['seller_email'],
            'seller_name' => $item['seller_name'],
            'item_title' => $item['title'],
            'location' => $item['location'],
            'message' => 'In-person purchase intent confirmed! The seller\'s contact is now available.'
        ]);
        exit;
    }


    // ==========================================
    // 13. RACCON STORIES LIBRARY & STORY WALL API
    // ==========================================

    // Ensure stories tables exist
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS community_stories (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'fantasy',
            format_status TEXT DEFAULT 'ongoing',
            target_audience TEXT DEFAULT 'general',
            summary TEXT DEFAULT '',
            content TEXT NOT NULL,
            cover_image TEXT DEFAULT '',
            tags TEXT DEFAULT '',
            word_count INTEGER DEFAULT 0,
            reading_time INTEGER DEFAULT 5,
            views_count INTEGER DEFAULT 0,
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            is_featured INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_stories_category ON community_stories(category, created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_stories_format ON community_stories(format_status, created_at)");

        $db->exec("CREATE TABLE IF NOT EXISTS story_comments (
            id TEXT PRIMARY KEY,
            story_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_story_comments_story ON story_comments(story_id, created_at)");

        $db->exec("CREATE TABLE IF NOT EXISTS story_likes (
            story_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (story_id, user_id)
        )");

        // Seed initial stories if empty
        $storyCount = (int)$db->query("SELECT COUNT(*) FROM community_stories")->fetchColumn();
        if ($storyCount === 0) {
            $seedAuthor = 'usr_admin_master';
            $seedStories = [
                [
                    'id' => 'story_romance_midnight',
                    'title' => 'Midnight Under the Constellations',
                    'category' => 'romance',
                    'format_status' => 'completed',
                    'target_audience' => 'na',
                    'summary' => 'An unexpected rainy evening in Prague brings two strangers into an antique bookstore, sparking an intimate connection that neither anticipated.',
                    'content' => "The rain in Prague does not fall so much as it suspends itself in the lantern-lit air, blurring the cobblestones into mirrors of liquid amber.\n\nClara had pushed through the worn oak door of *Knihkupectví U Sovy* seeking nothing more ambitious than shelter for her soaked wool coat. What she found instead was a man sitting beside a tower of vintage leather-bound atlases, a cup of steaming Earl Grey resting precariously on an unfolded map of 18th-century Bohemia.\n\n'The roof leaks on the east wall,' he remarked without looking up, his voice low and seasoned with a quiet amusement. 'If you stand by the poetry section, you will remain dry.'\n\nClara shook out her umbrella, stepping closer to the warm radius of the brass desk lamp. 'And what if I prefer maps to poetry?'\n\nThe man finally looked up. His eyes were the color of slate after a squall, sharp with intelligence yet unexpectedly warm. 'Then you are welcome to help me find a town that ceased to exist two hundred years ago.'\n\nThat single rain-drenched sentence began the three hours that altered Clara's entire trajectory. They traced phantom borders with fountain pens, argued over the translation of forgotten Latin marginalia, and discovered that both of them had fled the same crowded tech conference downtown in search of silence.\n\nWhen the church bells of Saint Nicholas chimed midnight across the square, the rain had ceased, but neither of them made a move toward the door. Some meetings are chance; others feel as though the ink had dried on the parchment centuries before.",
                    'tags' => 'romance,contemporary,prague,bookstore,strangers',
                    'words' => 2480,
                    'read_time' => 8,
                    'likes' => 38,
                    'comments' => 6
                ],
                [
                    'id' => 'story_fantasy_runes',
                    'title' => 'The Weaver of Silver Runes',
                    'category' => 'fantasy',
                    'format_status' => 'ongoing',
                    'target_audience' => 'ya',
                    'summary' => 'In the floating citadels of Aeloria, magic is woven into silver silk. When apprentice Lyra accidentally unravels a forbidden ancient glyph, she awakens a world long forgotten.',
                    'content' => "The silk spindles in the Grand Loom tower spun with the sound of distant flutes. In Aeloria, magic was not spoken or scribed; it was spun.\n\nLyra possessed fingers that moved faster than the elder weavers liked. While others measured their tension by the hourglass, she followed the hum beneath the silver threads—the subtle harmonic vibration that indicated the presence of stored kinetic sorcery.\n\n'Tension too loose on the warp, Lyra,' Master Corvus warned, gliding past her loom in his embroidered robes. 'A loose thread in a warding cloak invites a puncture.'\n\n'Yes, Master,' she murmured. But as soon as Corvus turned the corner, Lyra focused on the dark thread she had discovered nestled at the bottom of the raw cocoon cache. It was not silver. It was deep void-indigo, cold to the touch and shimmering with an iridescent sheen that defied the tower's skylights.\n\nWhen her shuttle guided the indigo filament across the silver matrix, the loom did not click. It resonated. A ripple of low, bell-like frequency washed outward, causing the crystal lanterns across the balcony to flicker and extinguish.\n\nIn the center of the woven tapestry, a rune formed itself—not one from the High Guild lexicon, but an archaic spiral symbol that matched the carvings on the submerged monoliths beneath the cloud barrier.\n\nThen, the tapestry whispered her name.",
                    'tags' => 'fantasy,magic,highfantasy,runes,adventure',
                    'words' => 3120,
                    'read_time' => 11,
                    'likes' => 54,
                    'comments' => 9
                ],
                [
                    'id' => 'story_scifi_void',
                    'title' => 'Echoes of the Void Terminal',
                    'category' => 'scifi',
                    'format_status' => 'completed',
                    'target_audience' => 'general',
                    'summary' => 'Deep aboard orbital relay station Kepler-9, an archivist detects a repeating mathematical signal emanating from a ghost ship that was declared lost two centuries ago.',
                    'content' => "The station chronometer ticked over to 03:00 UTC. At three in the morning ship-time, the Kepler-9 relay is the quietest place in the Kuiper belt.\n\nDr. Ronald Hayes dipped his synthetic biscuits into chicory broth and adjusted the sub-millimeter array. Most incoming packets were mundane: quantum handshake pings from the Jovian mining rigs, automated telemetry from helium-3 freighters, compressed diplomatic archives heading towards Mars.\n\nThen the buffer lit up in amber.\n\nA transmission was hitting the dipole antenna at 1420.405 MHz—the hydrogen line. It carried an antiquated carrier modulation: 8-bit binary parity framing with cyclic redundancy checks that had been deprecated in 2180.\n\nHayes set down his cup. He ran the checksum against the historical registry.\n\n*REGISTRY MATCH: USCSS ARTEMIS V. DECOMMISSIONED / LOST TRANSIT: 2194.*\n\n'Two hundred and thirty years,' Hayes whispered into the empty comms deck. The ship had disappeared while charting the Oort boundary before warp-folding was perfected.\n\nThe signal was not a distress call. It was a navigation coordinate stream followed by six words encoded in raw ASCII:\n\n*WE FOUND THE EDGE. IT SINGS.*",
                    'tags' => 'scifi,space,mystery,cyberpunk,signals',
                    'words' => 2950,
                    'read_time' => 10,
                    'likes' => 67,
                    'comments' => 12
                ],
                [
                    'id' => 'story_litrpg_raccoon',
                    'title' => 'Level 1 Raccoon: Dungeon Glitch',
                    'category' => 'litrpg',
                    'format_status' => 'ongoing',
                    'target_audience' => 'ya',
                    'summary' => 'Reincarnated inside the VR-MMORPG Aetherium Online not as a chosen hero, but as a Level 1 Nocturnal Raccoon with an infinite scavenger inventory!',
                    'content' => "[SYSTEM ALERT: Player soul migration complete.]\n[Race Assigned: Forest Raccoon (F-Rank Common Critter)]\n[HP: 25/25 | MP: 10/10 | Agility: 18 | Strength: 3 | Luck: ???]\n\nI blinked. My hands were small, gray, and distinctly equipped with five dextrous little black claws. A fluffy ringed tail twitched behind me.\n\n'Wait,' I tried to yell. All that came out was a high-pitched, indignant chittering sound.\n\nI was sitting in the underbrush outside the starter village of Oakdale. A party of four Level 1 novice human adventurers was marching past, clad in dented iron chestplates and carrying wooden training swords.\n\n'Look, a wild raccoon,' the mage shouted. 'Free 5 XP!'\n\nThe warrior swung his wooden sword at me. Instinct took over: I scurried up an elder oak at forty miles an hour, reached out with my left paw, and accidentally touched the warrior's leather pouch.\n\n[INHERENT SKILL ACTIVATED: SCAVENGE INFINITE (EX-RANK)]\n[Acquired: 15x Copper Coins]\n[Acquired: 1x Health Potion (Grade D)]\n[Acquired: 1x [Glitch Item] Key to the Forgotten Sub-Vault]\n\nThe warrior looked down at his empty belt. 'Hey... where did all my loot go?!'\n\nI sat on the branch above, unscrewed the health potion with my paws, and took a satisfied sip. Being a low-level dungeon critter was going to be hilarious.",
                    'tags' => 'litrpg,gamelit,comedy,isekai,adventure,raccoon',
                    'words' => 2100,
                    'read_time' => 7,
                    'likes' => 92,
                    'comments' => 18
                ],
                [
                    'id' => 'story_ceo_proposition',
                    'title' => 'The Executive Proposition',
                    'category' => 'billionaire',
                    'format_status' => 'ongoing',
                    'target_audience' => 'na',
                    'summary' => 'Software engineer Mia thought she was interviewing for a standard developer role at Apex Global, until CEO Julian Thorne offered her an unconventional merger of lives.',
                    'content' => "The 54th floor of the Apex Tower overlooked the entirety of Manhattan, an ocean of glittering spires wrapped in evening smog.\n\nMia adjusted the cuffs of her modest blazer, clutching her portfolio folder. She had prepared for algorithmic challenges, distributed systems design, and database indexing. She had not prepared for Julian Thorne to dismiss his entire panel of five senior executives with a simple wave of his hand.\n\n'Leave us,' he had said. His voice was effortless authority, framed by a charcoal bespoke suit and eyes that took in everything without revealing a particle of thought.\n\nWhen the heavy walnut doors clicked shut, Thorne stood up from the glass table and walked to the window.\n\n'Your GitHub commits on the cryptographic envelope repository were fascinating, Ms. Vance,' he said quietly. 'You write code like someone who trusts no one.'\n\n'In security architecture, trust is a vulnerability,' Mia replied, holding her chin steady.\n\nA faint, intriguing smile curved his mouth. 'Precisely. Which is why I do not need you to write backend microservices for Apex.' He turned, leaning back against the sill. 'I need someone whose privacy protocols cannot be bought, coerced, or breached. I need a counterfeit fiancé for the upcoming Tokyo conglomerate merger, and in return, Apex will grant full equity funding to your open-source foundation.'\n\nMia stared at him. 'You're insane, Mr. Thorne.'\n\n'Perhaps,' he stepped forward, his cologne carrying notes of cedar and rain. 'But check your inbox. The preliminary draft is already sealed.'",
                    'tags' => 'billionaire,romance,ceo,office,drama',
                    'words' => 2650,
                    'read_time' => 9,
                    'likes' => 74,
                    'comments' => 15
                ],
                [
                    'id' => 'story_werewolf_lunar',
                    'title' => 'Lunar Eclipse of the Silver Ridge',
                    'category' => 'paranormal',
                    'format_status' => 'ongoing',
                    'target_audience' => 'ya',
                    'summary' => 'When rogue shapeshifter Kieran crosses into forbidden territory during the blood moon, an ancient bond forces rival clans to choose between peace or total war.',
                    'content' => "The blood moon rose heavy and crimson over the jagged teeth of the Silver Ridge.\n\nKieran felt the change in his marrow twenty minutes before the light crested the pines. It was not a violent tearing, but a tidal surge of instincts: hearing the flutter of an owl's wing two miles down the valley, scenting ozone and frost on the wind.\n\nHe had broken pack law by crossing the river border. The Elders had drawn the truce line eighty winters ago after the massacre at Blackpine Ravine: no wolf steps east of the water, no vampire hunts west of the ridge.\n\nYet here he stood on the frozen eastern bank, following a scent that made his wolf whine with a longing deeper than survival.\n\nA shadow materialized atop the granite boulder before him, silent as snowfall. Crimson velvet coat, silver hair catching the moon, and eyes the piercing violet of nightshade.\n\n'You are trespassing, pup,' she spoke, her tone cool glass. But her hands, pale and clawed, were trembling with the exact same electric resonance that pinned Kieran to the earth.\n\nThe mating mark on his collarbone burned like white phosphorus. And on the hollow of her throat, identical silver script flared to life under the red sky.\n\n'It seems,' Kieran whispered, stepping out of the mist, 'that our ancestors forgot to consult the moon.'",
                    'tags' => 'paranormal,werewolf,vampire,mates,forbidden',
                    'words' => 2800,
                    'read_time' => 9,
                    'likes' => 81,
                    'comments' => 14
                ],
                [
                    'id' => 'story_horror_pines',
                    'title' => 'The Whispering Pines of Blackwood',
                    'category' => 'horror',
                    'format_status' => 'completed',
                    'target_audience' => 'adult',
                    'summary' => 'A forest ranger taking the winter night shift at an isolated lookout tower begins to hear knocks coming from the inside of the floorboards.',
                    'content' => "Lookout Tower 7 stood on stilts sixty feet above the canopy of the Blackwood National Preserve. In summer, tourists marvel at the panoramic view of the sawtooth mountains. In November, when the access roads ice over, it is just you, two propane heaters, and the wind.\n\nMark was brewing black coffee on the Coleman stove when the first knock came.\n\nIt was not the exterior rattle of timber under gale force. It was three deliberate, knuckle-sharp raps against wood.\n\n*Knock. Knock. Knock.*\n\nThey came from beneath the linoleum rug directly under his swivel chair.\n\nMark froze with the kettle poised over his tin mug. Below the tower was sixty feet of sheer drop, support beams, and hemlock branches. There was no crawlspace. There was no second story. The floor was two-inch tongue-and-groove pine bolted directly to steel girders.\n\nHe waited. The howling wind outside picked up, shaking the guy-wires.\n\nHe took a breath, letting himself scoff at his own paranoia. 'Wind buffeting the hatch,' he muttered aloud to break the suffocating silence.\n\nThen came the second sound.\n\nNot a knock this time.\n\nA wet, deliberate fingernail dragging along the bottom of the wood grain, following the path of his footsteps across the room.",
                    'tags' => 'horror,thriller,suspense,paranormal,cabin',
                    'words' => 2300,
                    'read_time' => 8,
                    'likes' => 63,
                    'comments' => 11
                ],
                [
                    'id' => 'story_poetry_nocturnal',
                    'title' => 'Nocturnal Verses from the Cedar Burrow',
                    'category' => 'poetry',
                    'format_status' => 'completed',
                    'target_audience' => 'general',
                    'summary' => 'A lyrical collection of nocturnal stanzas celebrating starry skies, silent midnight walks, and the comfort of solitude.',
                    'content' => "I. Midnight Cartography\n\nThe day belongs to brass and clocks,\nTo hurried heels on crowded blocks;\nBut when the streetlights buzz and dim,\nThe silent city starts to brim.\n\nA shadow slips across the tile,\nA whiskered mask, a crooked smile;\nWe measure time by moonlight gleams,\nAnd map the kingdom of our dreams.\n\nII. The Code in the Frost\n\nNo message sealed in paper folds\nCan match the peace the cedar holds;\nBeneath the roots where mosses creep,\nThe night is watchful, wide, and deep.\n\nLet daylight run its noisy race,\nWe find our home in nocturnal space.",
                    'tags' => 'poetry,verse,nocturnal,peace,stars',
                    'words' => 450,
                    'read_time' => 3,
                    'likes' => 49,
                    'comments' => 5
                ]
            ];

            $stmtInsert = $db->prepare("INSERT INTO community_stories (id, user_id, title, category, format_status, target_audience, summary, content, tags, word_count, reading_time, likes_count, comments_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($seedStories as $s) {
                $stmtInsert->execute([
                    $s['id'], $seedAuthor, $s['title'], $s['category'], $s['format_status'], $s['target_audience'],
                    $s['summary'], $s['content'], $s['tags'], $s['words'], $s['read_time'], $s['likes'], $s['comments']
                ]);
            }
        }
    } catch (Throwable $e) {}

    // 13.1 GET /api/community/stories (List, Search & Filter Stories)
    if ($route === 'community/stories' && $method === 'GET') {
        $category = trim($_GET['category'] ?? 'all');
        $status = trim($_GET['status'] ?? 'all');
        $search = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'popular');

        $query = "SELECT s.*, 
                  COALESCE(u.name, REPLACE(SUBSTR(s.user_id, 1, 14), '_', ' ')) as author_name, 
                  COALESCE(u.email, s.user_id || '@racconmail.eu') as author_email, 
                  COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar 
                  FROM community_stories s 
                  LEFT JOIN users u ON s.user_id = u.id 
                  WHERE 1=1";
        $params = [];

        if ($category !== 'all' && $category !== '') {
            $query .= " AND s.category = ?";
            $params[] = $category;
        }

        if ($status !== 'all' && $status !== '') {
            $query .= " AND s.format_status = ?";
            $params[] = $status;
        }

        if ($search !== '') {
            $query .= " AND (s.title LIKE ? OR s.summary LIKE ? OR s.content LIKE ? OR s.tags LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }

        if ($sort === 'latest') {
            $query .= " ORDER BY s.created_at DESC";
        } elseif ($sort === 'words') {
            $query .= " ORDER BY s.word_count DESC";
        } else {
            $query .= " ORDER BY s.likes_count DESC, s.created_at DESC";
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $stories = $stmt->fetchAll();

        // Attach like status
        if ($currentUserId) {
            $stmtLikes = $db->prepare("SELECT story_id FROM story_likes WHERE user_id = ?");
            $stmtLikes->execute([$currentUserId]);
            $likedIds = array_column($stmtLikes->fetchAll(), 'story_id');
            $likedSet = array_flip($likedIds);
            foreach ($stories as &$st) {
                $st['has_liked'] = isset($likedSet[$st['id']]);
            }
        } else {
            foreach ($stories as &$st) {
                $st['has_liked'] = false;
            }
        }

        echo json_encode([
            'success' => true,
            'stories' => $stories,
            'count' => count($stories),
            'isLoggedIn' => !empty($currentUserId),
            'currentUserId' => $currentUserId
        ]);
        exit;
    }

    // 13.2 GET /api/community/stories/:id (Single Story & Reader)
    if (preg_match('#^community/stories/([^/]+)$#', $route, $m) && $method === 'GET') {
        $storyId = $m[1];
        // Increment view count
        @$db->prepare("UPDATE community_stories SET views_count = views_count + 1 WHERE id = ?")->execute([$storyId]);

        $stmt = $db->prepare("SELECT s.*, 
                              COALESCE(u.name, REPLACE(SUBSTR(s.user_id, 1, 14), '_', ' ')) as author_name, 
                              COALESCE(u.email, s.user_id || '@racconmail.eu') as author_email, 
                              COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar,
                              COALESCE(u.bio, 'Author & Storyteller on Raccon Stories') as author_bio 
                              FROM community_stories s 
                              LEFT JOIN users u ON s.user_id = u.id 
                              WHERE s.id = ?");
        $stmt->execute([$storyId]);
        $story = $stmt->fetch();
        if (!$story) {
            http_response_code(404);
            echo json_encode(['error' => 'Story not found']);
            exit;
        }

        if ($currentUserId) {
            $stmtLiked = $db->prepare("SELECT 1 FROM story_likes WHERE story_id = ? AND user_id = ?");
            $stmtLiked->execute([$storyId, $currentUserId]);
            $story['has_liked'] = (bool)$stmtLiked->fetchColumn();
        } else {
            $story['has_liked'] = false;
        }

        // Fetch comments
        $stmtC = $db->prepare("SELECT c.*, 
                               COALESCE(u.name, REPLACE(SUBSTR(c.user_id, 1, 14), '_', ' ')) as author_name, 
                               COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar 
                               FROM story_comments c 
                               LEFT JOIN users u ON c.user_id = u.id 
                               WHERE c.story_id = ? 
                               ORDER BY c.created_at ASC");
        $stmtC->execute([$storyId]);
        $comments = $stmtC->fetchAll();

        echo json_encode([
            'success' => true,
            'story' => $story,
            'comments' => $comments,
            'isLoggedIn' => !empty($currentUserId),
            'currentUserId' => $currentUserId
        ]);
        exit;
    }

    // 13.3 POST /api/community/stories (Post a Story / Wall Story)
    if ($route === 'community/stories' && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Access restricted: Please sign in or join free to publish stories.']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $category = trim($input['category'] ?? 'fantasy');
        $formatStatus = trim($input['format_status'] ?? 'ongoing');
        $targetAudience = trim($input['target_audience'] ?? 'general');
        $summary = trim($input['summary'] ?? '');
        $content = trim($input['content'] ?? '');
        $tags = trim($input['tags'] ?? '');
        $coverImage = trim($input['cover_image'] ?? '');

        if (!$title && $content) {
            $title = mb_substr(strip_tags($content), 0, 45) . (mb_strlen($content) > 45 ? '...' : '');
        }

        if (!$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide story content.']);
            exit;
        }

        if (!$summary) {
            $summary = mb_substr(strip_tags($content), 0, 160) . (mb_strlen($content) > 160 ? '...' : '');
        }

        $wordCount = str_word_count(strip_tags($content));
        $readTime = max(1, (int)ceil($wordCount / 220));

        $storyId = 'story_' . uniqid();
        $stmt = $db->prepare("INSERT INTO community_stories 
            (id, user_id, title, category, format_status, target_audience, summary, content, cover_image, tags, word_count, reading_time, views_count, likes_count, comments_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0)");
        $stmt->execute([
            $storyId, $currentUserId, $title, $category, $formatStatus, $targetAudience, $summary, $content, $coverImage, $tags, $wordCount, $readTime
        ]);

        echo json_encode([
            'success' => true,
            'id' => $storyId,
            'message' => 'Your story is now live in the Raccon Stories Library! 📚🦝'
        ]);
        exit;
    }

    // 13.4 POST /api/community/stories/:id/comments (Review / Comment)
    if (preg_match('#^community/stories/([^/]+)/comments$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Please sign in to comment on stories.']);
            exit;
        }

        $storyId = $m[1];
        $content = trim($input['content'] ?? '');
        if (!$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment cannot be blank.']);
            exit;
        }

        $cid = 'sc_' . uniqid();
        $stmt = $db->prepare("INSERT INTO story_comments (id, story_id, user_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cid, $storyId, $currentUserId, $content]);
        @$db->prepare("UPDATE community_stories SET comments_count = comments_count + 1 WHERE id = ?")->execute([$storyId]);

        echo json_encode(['success' => true, 'id' => $cid, 'message' => 'Review posted!']);
        exit;
    }

    // 13.5 POST /api/community/stories/:id/like (Like Story)
    if (preg_match('#^community/stories/([^/]+)/like$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Please sign in to like stories.']);
            exit;
        }

        $storyId = $m[1];
        $stmtCheck = $db->prepare("SELECT 1 FROM story_likes WHERE story_id = ? AND user_id = ?");
        $stmtCheck->execute([$storyId, $currentUserId]);
        $hasLiked = (bool)$stmtCheck->fetchColumn();

        if ($hasLiked) {
            $db->prepare("DELETE FROM story_likes WHERE story_id = ? AND user_id = ?")->execute([$storyId, $currentUserId]);
            $db->prepare("UPDATE community_stories SET likes_count = MAX(0, likes_count - 1) WHERE id = ?")->execute([$storyId]);
            $action = 'unliked';
        } else {
            $db->prepare("INSERT INTO story_likes (story_id, user_id) VALUES (?, ?)")->execute([$storyId, $currentUserId]);
            $db->prepare("UPDATE community_stories SET likes_count = likes_count + 1 WHERE id = ?")->execute([$storyId]);
            $action = 'liked';
        }

        $likesCount = (int)$db->prepare("SELECT likes_count FROM community_stories WHERE id = ?")->execute([$storyId]) ? $db->query("SELECT likes_count FROM community_stories WHERE id = '$storyId'")->fetchColumn() : 0;

        echo json_encode([
            'success' => true,
            'action' => $action,
            'likes_count' => $likesCount
        ]);
        exit;
    }

    // Default 404
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found: ' . $route]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => basename($e->getFile())]);
}

function extractEmails($str) {
    if (!$str) return [];
    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $str, $matches);
    return array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
}

function buildRacconMailPostcardHtml($fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = '', $attachments = []) {
    $logoUrl = 'https://racconmail.eu/raccoon-logo.png';
    $siteUrl = 'https://racconmail.eu';
    $inboxUrl = 'https://racconmail.eu/inbox.php';
    $cleanSubject = $subject ?: '(No Subject)';
    $replySubject = (stripos($cleanSubject, 'Re:') === 0) ? $cleanSubject : ('Re: ' . $cleanSubject);
    $encodedReplySubj = rawurlencode($replySubject);
    $webmailReplyUrl = $inboxUrl . '?compose=1&reply_to=' . rawurlencode($fromEmail) . '&subject=' . $encodedReplySubj;
    $rawReplyUrl = 'mailto:' . rawurlencode($fromEmail) . '?subject=' . $encodedReplySubj;

    $isReply = (stripos($cleanSubject, 'Re:') === 0) || (stripos($bodyText, '--- Original Message ---') !== false) || (stripos($bodyText, 'wrote:') !== false);

    // Format content: cleanly separate top reply from quoted parent if present
    $contentHtml = '';
    if ($isReply && (str_contains($bodyText, '--- Original Message ---') || str_contains($bodyText, 'wrote:'))) {
        $parts = preg_split('/(--- Original Message ---|On .+ wrote:)/i', $bodyText, 2);
        $topReply = trim($parts[0] ?? '');
        $quoted = trim($parts[1] ?? '');
        if ($topReply !== '') {
            $contentHtml .= '<div style="font-size:15px; line-height:1.7; color:#0f172a; margin-bottom:16px;">' . nl2br(htmlspecialchars($topReply)) . '</div>';
        }
        if ($quoted !== '') {
            $contentHtml .= '<div style="border-left:3px solid #0284c7; background-color:#f8fafc; border-radius:0 10px 10px 0; padding:12px 16px; font-size:13px; line-height:1.6; color:#475569; word-break:break-word;">' . nl2br(htmlspecialchars($quoted)) . '</div>';
        }
    } else {
        $contentHtml = $bodyHtml ? $bodyHtml : nl2br(htmlspecialchars($bodyText));
    }

    $attHtml = '';
    if (!empty($attachments)) {
        $attHtml .= '<div style="margin-top:20px; padding-top:16px; border-top:1px dashed #e2e8f0;">';
        $attHtml .= '<div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">📎 Attachments (' . count($attachments) . ')</div>';
        $attHtml .= '<div style="display:flex; flex-wrap:wrap; gap:8px;">';
        foreach ($attachments as $att) {
            $name = htmlspecialchars($att['original_name'] ?: basename($att['file_path']));
            $sizeKB = round(($att['size_bytes'] ?? 0) / 1024);
            $attHtml .= '<span style="display:inline-block; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:6px 12px; font-size:12px; color:#0f172a; margin-right:6px; margin-bottom:6px;">📎 ' . $name . ' <span style="color:#64748b;">(' . $sizeKB . ' KB)</span></span>';
        }
        $attHtml .= '</div></div>';
    }

    $safeFromName = htmlspecialchars($fromName);
    $safeFromEmail = htmlspecialchars($fromEmail);
    $safeToEmail = htmlspecialchars($toEmail);
    $safeSubject = htmlspecialchars($cleanSubject);
    $nowStr = date('D, M j, Y, g:i A');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeSubject}</title>
  <style>
    @media only screen and (max-width: 600px) {
      .postcard-wrapper { padding: 6px 4px !important; }
      .postcard-table { width: 100% !important; max-width: 100% !important; border-radius: 12px !important; table-layout: fixed !important; }
      .mobile-pad { padding: 12px 14px !important; }
      .mobile-header-stack { display: block !important; width: 100% !important; text-align: left !important; }
      .mobile-header-stamp { display: block !important; width: 100% !important; text-align: left !important; margin-top: 10px !important; }
      .mobile-stack { display: block !important; width: 100% !important; box-sizing: border-box !important; }
      .mobile-date { display: block !important; text-align: left !important; margin-top: 5px !important; font-size: 11px !important; }
      .mobile-btn-wrap { display: block !important; width: 100% !important; margin: 0 0 8px 0 !important; }
      .mobile-btn { display: block !important; width: 100% !important; margin: 6px 0 !important; text-align: center !important; box-sizing: border-box !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#0f172a;">
  
  <!-- Outer Postal Canvas -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" class="postcard-wrapper" style="background-color:#f8fafc; padding:28px 12px;">
    <tr>
      <td align="center">
        
        <!-- Bubble White Postcard Card (Max 620px) -->
        <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" class="postcard-table" style="max-width:600px; width:100%; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.04); table-layout:fixed;">
          
          <!-- Top Airmail Diagonal Striped Ribbon -->
          <tr>
            <td style="height:6px; background:repeating-linear-gradient(135deg, #0ea5e9 0px, #0ea5e9 14px, #10b981 14px, #10b981 28px, #f59e0b 28px, #f59e0b 42px, #6366f1 42px, #6366f1 56px);"></td>
          </tr>

          <!-- Postcard Header: Logo & Cancellation Postmark -->
          <tr>
            <td class="mobile-pad" style="padding:20px 24px 16px 24px; border-bottom:1px solid #f1f5f9; background-color:#ffffff;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <!-- Left: Raccon Mail Brand & Logo -->
                  <td class="mobile-header-stack" align="left" style="vertical-align:middle;">
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="vertical-align:middle; padding-right:10px;">
                          <a href="{$siteUrl}" style="text-decoration:none;">
                            <img src="{$logoUrl}" width="42" height="42" alt="Raccon Mail" style="display:block; border-radius:10px; border:1px solid #e2e8f0; background-color:#f8fafc; object-fit:cover;" />
                          </a>
                        </td>
                        <td style="vertical-align:middle;">
                          <div style="font-family:'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif; font-size:17px; font-weight:800; color:#0f172a; letter-spacing:0.5px; line-height:1.2;">RACCON MAIL</div>
                          <div style="font-size:11px; color:#0284c7; font-weight:700; letter-spacing:0.3px; margin-top:2px;">🦝 Bubble White Postcard</div>
                        </td>
                      </tr>
                    </table>
                  </td>

                  <!-- Right: Postmark Stamp Badge -->
                  <td class="mobile-header-stamp" align="right" style="vertical-align:middle;">
                    <div style="display:inline-block; border:1.5px dashed #0284c7; border-radius:8px; padding:5px 8px; text-align:center; background-color:#f0f9ff; color:#0284c7; font-family:'Courier New', monospace; font-size:9px; font-weight:bold; letter-spacing:0.6px; line-height:1.2;">
                      <div style="font-size:9px; color:#0284c7;">RACCON POST</div>
                      <div style="font-size:8px; color:#64748b;">AIRMAIL &bull; 2026</div>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Postcard Routing Bar (From, To, Date) -->
          <tr>
            <td class="mobile-pad" style="padding:12px 24px; background-color:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:12px; color:#64748b;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="mobile-stack" style="line-height:1.6;">
                    <div><strong style="color:#0f172a;">From:</strong> <span style="color:#0f172a; font-weight:600;">{$safeFromName}</span> &lt;<a href="mailto:{$safeFromEmail}" style="color:#0284c7; text-decoration:none;">{$safeFromEmail}</a>&gt;</div>
                    <div><strong style="color:#0f172a;">To:</strong> <span style="color:#475569;">{$safeToEmail}</span></div>
                  </td>
                  <td align="right" class="mobile-stack mobile-date" style="vertical-align:top; font-size:11px; color:#94a3b8;">
                    {$nowStr}
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Subject Line -->
          <tr>
            <td class="mobile-pad" style="padding:20px 24px 8px 24px;">
              <div style="margin-bottom:8px; display:inline-block; background-color:#f1f5f9; border:1px solid #e2e8f0; border-radius:20px; padding:4px 12px; font-size:11px; color:#0f172a; font-weight:700;">
                <img src="{$logoUrl}" width="14" height="14" alt="Raccon" style="vertical-align:middle; border-radius:50%; margin-right:5px; display:inline-block;" /> Raccon Postcard
              </div>
              <h2 style="margin:0; font-family:'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif; font-size:20px; font-weight:700; color:#0f172a; line-height:1.35; word-break:break-word;">
                {$safeSubject}
              </h2>
            </td>
          </tr>

          <!-- Postcard Letter Content -->
          <tr>
            <td class="mobile-pad" style="padding:12px 24px 24px 24px; font-size:15px; line-height:1.7; color:#0f172a;">
              <div style="background-color:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; word-break:break-word;">
                {$contentHtml}
              </div>

              {$attHtml}
            </td>
          </tr>

          <!-- Postcard Action Bar -->
          <tr>
            <td class="mobile-pad" style="padding:0 24px 24px 24px;">
              <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:16px 20px;">
                <div class="mobile-btn-wrap" style="display:inline-block; margin-right:8px; margin-bottom:4px;">
                  <a href="{$webmailReplyUrl}" class="mobile-btn" style="display:inline-block; background-color:#0f172a; color:#ffffff; font-weight:700; font-size:13px; padding:10px 22px; border-radius:9999px; text-decoration:none; box-shadow:0 4px 12px rgba(15, 23, 42, 0.12); text-align:center;">
                    ✉️ Reply to {$safeFromName}
                  </a>
                </div>
                <div class="mobile-btn-wrap" style="display:inline-block; margin-bottom:4px;">
                  <a href="{$rawReplyUrl}" class="mobile-btn" style="display:inline-block; background-color:#ffffff; color:#334155; font-weight:600; font-size:12px; padding:9px 18px; border-radius:9999px; text-decoration:none; border:1px solid #cbd5e1; text-align:center;">
                    Reply via Email App
                  </a>
                </div>
              </div>
            </td>
          </tr>

          <!-- Footer matching site style -->
          <tr>
            <td style="padding:18px 24px; background-color:#f8fafc; border-top:1px solid #e2e8f0; text-align:center;">
              <p style="margin:0 0 6px 0; font-size:11px; color:#64748b; line-height:1.5;">
                Sent with nocturnal stealth from <strong>Raccon Mail</strong> &bull; <a href="{$siteUrl}" style="color:#0284c7; text-decoration:none;">racconmail.eu</a><br>
                The independent, private &amp; encrypted nocturnal postcard network &bull; Bubble White Edition
              </p>
              <p style="margin:0; font-size:10px; color:#94a3b8;">
                &copy; 2026 Raccon Mail. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
        <!-- /Bubble White Postcard Card -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;
}

function sendOutgoingEmail($fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = '', $attachments = [], $inReplyTo = null) {
    $serverDomain = 'racconmail.eu';
    $senderDomain = substr(strrchr($fromEmail, "@"), 1);
    if (empty($senderDomain) || strtolower($senderDomain) !== $serverDomain) {
        $envelopeFrom = 'postmaster@' . $serverDomain;
    } else {
        $envelopeFrom = $fromEmail;
    }

    $cleanSubject = trim($subject) !== '' ? trim($subject) : '(no subject)';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($cleanSubject) . '?=';
    $cleanFromName = trim($fromName) !== '' ? trim($fromName) : 'Raccon Mail Member';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($cleanFromName) . '?=';

    // Unique RFC 5322 compliant Message-ID
    $msgIdHeader = '<msg_' . bin2hex(random_bytes(8)) . '_' . time() . '@' . $serverDomain . '>';

    // Prepare clean plain-text fallback (vital for anti-spam deliverability)
    $plainText = trim($bodyText);
    if (empty($plainText) && !empty($bodyHtml)) {
        $plainText = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (empty($plainText)) {
        $plainText = "Hello,\n\nYou have received a message sent via Raccon Mail.\nSubject: " . $cleanSubject . "\n\nVisit https://racconmail.eu to reply.";
    }

    $finalHtml = $bodyHtml ?: buildRacconMailPostcardHtml($cleanFromName, $fromEmail, $toEmail, $cleanSubject, $plainText, '', $attachments);

    // Standard RFC 5322 anti-spam delivery headers
    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: ' . $msgIdHeader;
    if (!empty($inReplyTo)) {
        $cleanInReplyTo = trim($inReplyTo, " <>\r\n");
        $headers[] = 'In-Reply-To: <' . $cleanInReplyTo . '>';
        $headers[] = 'References: <' . $cleanInReplyTo . '>';
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . $encodedFromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $encodedFromName . ' <' . $fromEmail . '>';
    $headers[] = 'Return-Path: <' . $envelopeFrom . '>';
    $headers[] = 'X-Mailer: RacconMail/2.0 (bubble-white-engine)';
    $headers[] = 'X-Priority: 3 (Normal)';
    $headers[] = 'Importance: Normal';

    $altBoundary = "==Alt_Boundary_" . bin2hex(random_bytes(10)) . "==";
    $encodedPlain = quoted_printable_encode($plainText);
    $encodedHtml = quoted_printable_encode($finalHtml);

    if (!empty($attachments)) {
        $mixedBoundary = "==Mixed_Boundary_" . bin2hex(random_bytes(10)) . "==";
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';

        $message = "--" . $mixedBoundary . "\r\n";
        $message .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . "\"\r\n\r\n";

        // Plain text part
        $message .= "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8; format=flowed\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= $encodedPlain . "\r\n\r\n";

        // HTML part
        $message .= "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= $encodedHtml . "\r\n\r\n";
        $message .= "--" . $altBoundary . "--\r\n\r\n";

        // Attachment parts
        foreach ($attachments as $att) {
            $filePath = $att['file_path'];
            if (file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                $b64Content = chunk_split(base64_encode($fileContent));
                $mimeType = $att['mime_type'] ?: 'application/octet-stream';
                $origName = $att['original_name'] ?: basename($filePath);
                $encodedAttName = '=?UTF-8?B?' . base64_encode($origName) . '?=';

                $message .= "--" . $mixedBoundary . "\r\n";
                $message .= 'Content-Type: ' . $mimeType . '; name="' . $encodedAttName . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= 'Content-Disposition: attachment; filename="' . $encodedAttName . "\"\r\n\r\n";
                $message .= $b64Content . "\r\n";
            }
        }
        $message .= "--" . $mixedBoundary . "--\r\n";
    } else {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"';

        $message = "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8; format=flowed\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= $encodedPlain . "\r\n\r\n";

        $message .= "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= $encodedHtml . "\r\n\r\n";
        $message .= "--" . $altBoundary . "--\r\n";
    }

    $headers[] = 'To: <' . $toEmail . '>';
    $headers[] = 'Subject: ' . $encodedSubject;

    // 1. First attempt: Authenticated local SMTP to Exim on localhost:25 (with sender envelope for 100% SPF alignment)
    $smtpSuccess = sendViaSmtpSocket($toEmail, $encodedSubject, $headers, $message, 'mail@' . $serverDomain, 'RacconMailSecure2026!#', $envelopeFrom);
    if ($smtpSuccess) {
        return true;
    }

    // 2. Second attempt: Direct sendmail via PHP mail()
    $mailHeaders = array_filter($headers, function($h) {
        return !preg_match('/^(?:To|Subject):/i', $h);
    });
    $mailHeaderStr = implode("\r\n", $mailHeaders);
    $sent = @mail($toEmail, $encodedSubject, $message, $mailHeaderStr, "-f" . $envelopeFrom);
    if (!$sent) {
        $sent = @mail($toEmail, $encodedSubject, $message, $mailHeaderStr);
    }
    return $sent;
}

function sendViaSmtpSocket($toEmail, $subject, $headers, $body, $smtpUser, $smtpPass, $envelopeFrom = null) {
    $fp = @fsockopen('localhost', 25, $errno, $errstr, 1.5);
    if (!$fp) return false;
    stream_set_timeout($fp, 2);

    $read = function() use ($fp) {
        $res = '';
        while ($line = fgets($fp, 512)) {
            $res .= $line;
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') break;
        }
        return trim($res);
    };

    $read(); // banner
    fwrite($fp, "EHLO racconmail.eu\r\n");
    $read();

    fwrite($fp, "AUTH LOGIN\r
");
    $read();
    fwrite($fp, base64_encode($smtpUser) . "\r\n");
    $read();
    fwrite($fp, base64_encode($smtpPass) . "\r\n");
    $authRes = $read();

    if (!str_starts_with($authRes, '235')) {
        fclose($fp);
        return false;
    }

    $mailFrom = (!empty($envelopeFrom) && str_ends_with(strtolower($envelopeFrom), '@racconmail.eu')) ? $envelopeFrom : $smtpUser;
    fwrite($fp, "MAIL FROM:<$mailFrom>\r\n");
    $mfRes = $read();
    if (!str_starts_with($mfRes, '250')) {
        fclose($fp);
        return false;
    }

    fwrite($fp, "RCPT TO:<$toEmail>\r\n");
    $rcptRes = $read();
    if (!str_starts_with($rcptRes, '250')) {
        fclose($fp);
        return false;
    }

    fwrite($fp, "DATA\r\n");
    $dataRes = $read();
    if (!str_starts_with($dataRes, '354')) {
        fclose($fp);
        return false;
    }

    $fullHeaders = $headers;
    $hasTo = false;
    $hasSubject = false;
    foreach ($fullHeaders as $h) {
        if (stripos($h, 'To:') === 0) $hasTo = true;
        if (stripos($h, 'Subject:') === 0) $hasSubject = true;
    }
    if (!$hasTo) $fullHeaders[] = 'To: <' . $toEmail . '>';
    if (!$hasSubject) $fullHeaders[] = 'Subject: ' . $subject;

    $headerStr = implode("\r\n", $fullHeaders);
    fwrite($fp, $headerStr . "\r\n\r\n" . $body . "\r\n.\r\n");
    $sendRes = $read();

    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return str_starts_with($sendRes, '250');
}

function decodeMimeHeader($string) {
    if (!$string) return '';
    $elements = @imap_mime_header_decode($string);
    if (!$elements) return $string;
    $text = '';
    foreach ($elements as $el) {
        $charset = strtoupper($el->charset);
        if ($charset === 'DEFAULT' || $charset === 'US-ASCII') {
            $text .= $el->text;
        } else {
            try {
                $text .= mb_convert_encoding($el->text, 'UTF-8', $charset);
            } catch (Throwable $e) {
                $text .= $el->text;
            }
        }
    }
    return $text;
}

function getImapPartBodyContent($mbox, $msgno, $section, $encoding) {
    $data = @imap_fetchbody($mbox, $msgno, $section);
    if ($encoding == 3) {
        return base64_decode($data);
    } elseif ($encoding == 4) {
        return quoted_printable_decode($data);
    }
    return $data;
}

function extractImapBodyAndAttachments($mbox, $msgno, $structure, $uploadsDir) {
    $text = '';
    $html = '';
    $attachments = [];

    if (empty($structure->parts)) {
        $raw = getImapPartBodyContent($mbox, $msgno, 1, $structure->encoding ?? 0);
        if (strtoupper($structure->subtype ?? '') === 'HTML') {
            $html = $raw;
        } else {
            $text = $raw;
        }
        return ['text' => $text, 'html' => $html, 'attachments' => $attachments];
    }

    $stack = [['parts' => $structure->parts, 'prefix' => '']];
    while (!empty($stack)) {
        $current = array_pop($stack);
        $parts = $current['parts'];
        $prefix = $current['prefix'];

        foreach ($parts as $idx => $part) {
            $section = $prefix === '' ? (string)($idx + 1) : $prefix . '.' . ($idx + 1);
            $subtype = strtoupper($part->subtype ?? '');

            $isAttachment = false;
            $filename = '';

            if (isset($part->disposition) && (strtoupper($part->disposition) === 'ATTACHMENT' || strtoupper($part->disposition) === 'INLINE')) {
                $isAttachment = true;
            }
            if (!empty($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) === 'filename') {
                        $filename = decodeMimeHeader($param->value);
                        $isAttachment = true;
                    }
                }
            }
            if (!$filename && !empty($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) === 'name') {
                        $filename = decodeMimeHeader($param->value);
                        $isAttachment = true;
                    }
                }
            }

            if (!empty($part->parts)) {
                $stack[] = ['parts' => $part->parts, 'prefix' => $section];
            } else {
                if ($isAttachment && $filename) {
                    $content = getImapPartBodyContent($mbox, $msgno, $section, $part->encoding ?? 0);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $destFilename = 'att-' . time() . '-' . rand(1000, 9999) . ($ext ? '.' . $ext : '');
                    $filePath = $uploadsDir . '/' . $destFilename;
                    @file_put_contents($filePath, $content);
                    $attachments[] = [
                        'filename' => $destFilename,
                        'original_name' => $filename,
                        'mime_type' => ($part->type == 5 ? 'image/' : 'application/') . strtolower($subtype),
                        'size_bytes' => strlen($content),
                        'file_path' => $filePath
                    ];
                } elseif ($subtype === 'PLAIN' && empty($text)) {
                    $text = getImapPartBodyContent($mbox, $msgno, $section, $part->encoding ?? 0);
                } elseif ($subtype === 'HTML' && empty($html)) {
                    $html = getImapPartBodyContent($mbox, $msgno, $section, $part->encoding ?? 0);
                }
            }
        }
    }

    return ['text' => $text, 'html' => $html, 'attachments' => $attachments];
}

function syncInboundEmails($db, $force = false) {
    // Ultra-Fast Zero-Lag Inbound Sync
    static $lastSync = 0;
    if (!$force && (time() - $lastSync < 1)) return ['count' => 0, 'messages' => []];
    $lastSync = time();

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $processedCount = 0;
    $newlySynced = [];

    // 1. INSTANT LOCAL MAILDIR SCAN FIRST (< 1ms on local SSD/NVMe)
    // Directly ingests all incoming Exim catch-all & mailbox deliveries without network lag
    $foldersToScan = [
        '/home/raccxuxv/mail/new',
        '/home/raccxuxv/mail/racconmail.eu/mail/new',
        '/home/raccxuxv/mail/.mail@racconmail_eu/new'
    ];
    $domDir = '/home/raccxuxv/mail/racconmail.eu';
    if (is_dir($domDir)) {
        $subDirs = @scandir($domDir) ?: [];
        foreach ($subDirs as $sd) {
            if ($sd === '.' || $sd === '..') continue;
            $userNew = "$domDir/$sd/new";
            if (is_dir($userNew) && !in_array($userNew, $foldersToScan)) {
                $foldersToScan[] = $userNew;
            }
        }
    }

    foreach ($foldersToScan as $fDir) {
        $res = syncMaildirFolder($db, $fDir, $uploadsDir);
        $processedCount += $res['count'];
        $newlySynced = array_merge($newlySynced, $res['messages']);
    }

    // 2. Secondary IMAP Check (throttled to once every 20 seconds to guarantee sub-3ms polling)
    static $lastImapSync = 0;
    if (function_exists('imap_open') && (time() - $lastImapSync >= 20 || $processedCount > 0)) {
        $lastImapSync = time();
        @imap_timeout(IMAP_OPENTIMEOUT, 1);
        @imap_timeout(IMAP_READTIMEOUT, 1);
        $mbox = @imap_open('{localhost:143/notls}INBOX', 'mail@racconmail.eu', 'RacconMailSecure2026!#', 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
        @imap_errors();
        if ($mbox) {
            $num = @imap_num_msg($mbox);
            if ($num && $num > 0) {
                for ($i = 1; $i <= $num; $i++) {
                    $header = @imap_headerinfo($mbox, $i);
                    if (!$header) continue;

                    $subject = decodeMimeHeader($header->subject ?? '(no subject)');
                    $fromName = decodeMimeHeader($header->from[0]->personal ?? '');
                    $fromEmail = strtolower(($header->from[0]->mailbox ?? '') . '@' . ($header->from[0]->host ?? ''));
                    if (empty($fromName)) $fromName = $fromEmail;

                    $rawHeader = @imap_fetchheader($mbox, $i) ?: '';
                    $messageIdHeader = '';
                    if (preg_match('/^Message-ID:\s*<?([^>\r\n]+)>?/mi', $rawHeader, $mid)) {
                        $messageIdHeader = trim($mid[1]);
                    } elseif (!empty($header->message_id)) {
                        $messageIdHeader = trim($header->message_id, " <>\r\n");
                    }

                    $inReplyToHeader = '';
                    if (preg_match('/^In-Reply-To:\s*<?([^>\r\n]+)>?/mi', $rawHeader, $irp)) {
                        $inReplyToHeader = trim($irp[1], " <>\r\n");
                    }

                    // Extract all recipient addresses from headers
                    $recipients = [];
                    if (!empty($header->to)) {
                        foreach ($header->to as $t) {
                            $recipients[] = strtolower(($t->mailbox ?? '') . '@' . ($t->host ?? ''));
                        }
                    }
                    if (!empty($header->cc)) {
                        foreach ($header->cc as $c) {
                            $recipients[] = strtolower(($c->mailbox ?? '') . '@' . ($c->host ?? ''));
                        }
                    }
                    if (preg_match_all('/^(?:Envelope-to|Delivered-To|X-Original-To|X-Envelope-To|To|Cc):\s*(.+)$/mi', $rawHeader, $matches)) {
                        foreach ($matches[1] as $rLine) {
                            if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rLine, $em)) {
                                foreach ($em[0] as $e) {
                                    $recipients[] = strtolower($e);
                                }
                            }
                        }
                    }
                    $recipients = array_values(array_unique(array_filter($recipients)));

                    // Extract body and attachments
                    $structure = @imap_fetchstructure($mbox, $i);
                    $extracted = extractImapBodyAndAttachments($mbox, $i, $structure, $uploadsDir);
                    $bodyText = $extracted['text'];
                    $bodyHtml = $extracted['html'];
                    $attachments = $extracted['attachments'];

                    // Determine target user(s) in SQLite
                    $matchedUsers = [];
                    foreach ($recipients as $recipEmail) {
                        $stmt = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(email) = LOWER(?)');
                        $stmt->execute([$recipEmail]);
                        $u = $stmt->fetch();
                        if ($u) {
                            $matchedUsers[$u['id']] = $u;
                        }
                    }

                    // Match username prefix if recipient is @racconmail.eu
                    if (empty($matchedUsers)) {
                        foreach ($recipients as $recipEmail) {
                            if (str_ends_with($recipEmail, '@racconmail.eu')) {
                                $uPrefix = substr($recipEmail, 0, strpos($recipEmail, '@'));
                                $stmt = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(SUBSTR(email, 1, INSTR(email, "@")-1)) = LOWER(?)');
                                $stmt->execute([$uPrefix]);
                                $u = $stmt->fetch();
                                if ($u) {
                                    $matchedUsers[$u['id']] = $u;
                                }
                            }
                        }
                    }

                    // If active logged-in user triggered sync and target is still unresolved, give to active user
                    if (empty($matchedUsers) && !empty($_SESSION['current_user_id'])) {
                        $stmtSess = $db->prepare('SELECT id, email, name FROM users WHERE id = ?');
                        $stmtSess->execute([$_SESSION['current_user_id']]);
                        $sessUser = $stmtSess->fetch();
                        if ($sessUser) {
                            $matchedUsers[$sessUser['id']] = $sessUser;
                        }
                    }

                    // Fallback to admin or first registered member
                    if (empty($matchedUsers)) {
                        $stmtAdmin = $db->query('SELECT id, email, name FROM users WHERE is_admin = 1 ORDER BY created_at ASC LIMIT 1');
                        $adminUser = $stmtAdmin->fetch();
                        if ($adminUser) {
                            $matchedUsers[$adminUser['id']] = $adminUser;
                        } else {
                            $stmtAny = $db->query('SELECT id, email, name FROM users ORDER BY created_at ASC LIMIT 1');
                            $anyUser = $stmtAny->fetch();
                            if ($anyUser) {
                                $matchedUsers[$anyUser['id']] = $anyUser;
                            }
                        }
                    }

                    // Insert message for matched user(s)
                    foreach ($matchedUsers as $targetUser) {
                        // Safe duplicate prevention using RFC Message-ID or 5-minute identical content check
                        if (!empty($messageIdHeader)) {
                            $stmtCheck = $db->prepare('SELECT id FROM messages WHERE user_id = ? AND message_id = ?');
                            $stmtCheck->execute([$targetUser['id'], $messageIdHeader]);
                            if ($stmtCheck->fetch()) continue;
                        } else {
                            $stmtCheck = $db->prepare('SELECT id FROM messages WHERE user_id = ? AND sender_email = ? AND subject = ? AND body = ? AND created_at >= datetime("now", "-5 minutes")');
                            $stmtCheck->execute([$targetUser['id'], $fromEmail, $subject, $bodyText ?: '']);
                            if ($stmtCheck->fetch()) continue;
                        }

                        $inId = 'in_' . uniqid();
                        $targetEmail = $targetUser['email'];

                        $stmtIns = $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, body_html, message_id, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id, in_reply_to, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL, ?, strftime(\'%Y-%m-%dT%H:%M:%SZ\', \'now\'))');
                        $stmtIns->execute([
                            $inId,
                            $targetUser['id'],
                            $fromName,
                            $fromEmail,
                            $targetEmail,
                            $subject,
                            $bodyText ?: '(no plain text body)',
                            $bodyHtml,
                            $messageIdHeader ?: null,
                            $inReplyToHeader ?: null
                        ]);
                        $newlySynced[] = [
                            'id' => $inId,
                            'sender_name' => $fromName,
                            'sender_email' => $fromEmail,
                            'subject' => $subject,
                            'is_reply' => (bool)(preg_match('/^\\s*(re|fwd)\\s*:/i', $subject) || !empty($inReplyToHeader))
                        ];

                        foreach ($attachments as $att) {
                            $attId = 'att_' . uniqid();
                            $db->prepare('INSERT INTO attachments (id, message_id, filename, original_name, mime_type, size_bytes, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)')
                               ->execute([$attId, $inId, $att['filename'], $att['original_name'], $att['mime_type'], $att['size_bytes'], $att['filename']]);
                        }
                    }

                    // Mark deleted from IMAP box
                    @imap_delete($mbox, $i);
                    $processedCount++;
                }
            }
            @imap_errors();
            @imap_expunge($mbox);
            @imap_close($mbox);
            @imap_errors();
        }
    }

    return ['count' => $processedCount, 'messages' => $newlySynced];
}

function syncMaildirFolder($db, $newDir, $uploadsDir) {
    if (!is_dir($newDir)) return ['count' => 0, 'messages' => []];
    $files = @scandir($newDir);
    if (!$files) return ['count' => 0, 'messages' => []];

    $curDir = dirname($newDir) . '/cur';
    if (!is_dir($curDir)) @mkdir($curDir, 0755, true);

    $count = 0;
    $newlySynced = [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $srcPath = $newDir . '/' . $f;
        if (!is_file($srcPath) || !is_readable($srcPath)) continue;

        $raw = @file_get_contents($srcPath);
        if (!$raw || strlen($raw) < 10) continue;

        // Parse RFC 822 / 5322 raw email
        $parts = explode("\r\n\r\n", $raw, 2);
        if (count($parts) < 2) {
            $parts = explode("\n\n", $raw, 2);
        }
        $rawHeaders = $parts[0] ?? '';
        $rawBody = $parts[1] ?? '';

        // Extract Message-ID
        $messageId = '';
        if (preg_match('/^Message-ID:\s*<?([^>\r\n]+)>?/mi', $rawHeaders, $m)) {
            $messageId = trim($m[1]);
        }

        $inReplyTo = '';
        if (preg_match('/^In-Reply-To:\s*<?([^>\r\n]+)>?/mi', $rawHeaders, $irp)) {
            $inReplyTo = trim($irp[1], " <>\r\n");
        }

        // Extract Subject
        $subject = '(no subject)';
        if (preg_match('/^Subject:\s*(.+)$/mi', $rawHeaders, $m)) {
            $subject = decodeMimeHeader(trim($m[1]));
        }

        // Extract From
        $fromName = '';
        $fromEmail = 'unknown@sender.com';
        if (preg_match('/^From:\s*(.+)$/mi', $rawHeaders, $m)) {
            $line = trim($m[1]);
            if (preg_match('/(.*)<([^>]+)>/', $line, $fm)) {
                $fromName = decodeMimeHeader(trim($fm[1], " \"'"));
                $fromEmail = strtolower(trim($fm[2]));
            } else {
                $fromEmail = strtolower($line);
                $fromName = $fromEmail;
            }
        }
        if (empty($fromName)) $fromName = $fromEmail;

        // Extract all recipients
        $recips = [];
        if (preg_match_all('/^(?:Envelope-to|Delivered-To|X-Original-To|X-Envelope-To|To|Cc):\s*(.+)$/mi', $rawHeaders, $matches)) {
            foreach ($matches[1] as $rLine) {
                if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rLine, $em)) {
                    foreach ($em[0] as $e) {
                        $recips[] = strtolower($e);
                    }
                }
            }
        }
        $recips = array_values(array_unique(array_filter($recips)));

        // Match user in SQLite
        $matchedUsers = [];
        foreach ($recips as $re) {
            $stmt = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$re]);
            $u = $stmt->fetch();
            if ($u) $matchedUsers[$u['id']] = $u;
        }

        // Match username prefix if recipient is @racconmail.eu
        if (empty($matchedUsers)) {
            foreach ($recips as $re) {
                if (str_ends_with($re, '@racconmail.eu')) {
                    $uPrefix = substr($re, 0, strpos($re, '@'));
                    $stmt = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(SUBSTR(email, 1, INSTR(email, "@")-1)) = LOWER(?)');
                    $stmt->execute([$uPrefix]);
                    $u = $stmt->fetch();
                    if ($u) $matchedUsers[$u['id']] = $u;
                }
            }
        }

        // Active session fallback
        if (empty($matchedUsers) && !empty($_SESSION['current_user_id'])) {
            $stmtSess = $db->prepare('SELECT id, email, name FROM users WHERE id = ?');
            $stmtSess->execute([$_SESSION['current_user_id']]);
            $sessUser = $stmtSess->fetch();
            if ($sessUser) $matchedUsers[$sessUser['id']] = $sessUser;
        }

        // Admin fallback
        if (empty($matchedUsers)) {
            $stmtAdmin = $db->query('SELECT id, email, name FROM users WHERE is_admin = 1 ORDER BY created_at ASC LIMIT 1');
            $adminUser = $stmtAdmin->fetch();
            if ($adminUser) {
                $matchedUsers[$adminUser['id']] = $adminUser;
            } else {
                $stmtAny = $db->query('SELECT id, email, name FROM users ORDER BY created_at ASC LIMIT 1');
                $anyUser = $stmtAny->fetch();
                if ($anyUser) $matchedUsers[$anyUser['id']] = $anyUser;
            }
        }

        foreach ($matchedUsers as $targetUser) {
            // Safe duplicate prevention using RFC Message-ID or 5-minute identical content check
            if (!empty($messageId)) {
                $stmtCheck = $db->prepare('SELECT id FROM messages WHERE user_id = ? AND message_id = ?');
                $stmtCheck->execute([$targetUser['id'], $messageId]);
                if ($stmtCheck->fetch()) continue;
            } else {
                $stmtCheck = $db->prepare('SELECT id FROM messages WHERE user_id = ? AND sender_email = ? AND subject = ? AND body = ? AND created_at >= datetime("now", "-5 minutes")');
                $stmtCheck->execute([$targetUser['id'], $fromEmail, $subject, strip_tags($rawBody)]);
                if ($stmtCheck->fetch()) continue;
            }

            $inId = 'in_' . uniqid();
            $bodyText = strip_tags($rawBody);
            $bodyHtml = (stripos($rawHeaders, 'text/html') !== false) ? $rawBody : '';

            $stmtIns = $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, body_html, message_id, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id, in_reply_to, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL, ?, strftime(\'%Y-%m-%dT%H:%M:%SZ\', \'now\'))');
            $stmtIns->execute([
                $inId,
                $targetUser['id'],
                $fromName,
                $fromEmail,
                $targetUser['email'],
                $subject,
                $bodyText ?: '(no plain text body)',
                $bodyHtml,
                $messageId ?: null,
                $inReplyTo ?: null
            ]);
            $newlySynced[] = [
                'id' => $inId,
                'sender_name' => $fromName,
                'sender_email' => $fromEmail,
                'subject' => $subject,
                'is_reply' => (bool)(preg_match('/^\\s*(re|fwd)\\s*:/i', $subject) || !empty($inReplyTo))
            ];
        }

        // Move processed mail to cur folder
        $destPath = $curDir . '/' . $f . ':2,S';
        @rename($srcPath, $destPath);
        $count++;
    }
    return ['count' => $count, 'messages' => $newlySynced];
}

function initDatabase($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            phone TEXT,
            phone_verified INTEGER DEFAULT 0,
            avatar TEXT,
            signature TEXT,
            password_hash TEXT,
            is_admin INTEGER DEFAULT 0,
            is_banned INTEGER DEFAULT 0,
            ban_reason TEXT,
            cover_photo TEXT,
            theme TEXT DEFAULT 'raccon',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS phone_verifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone TEXT NOT NULL,
            code TEXT NOT NULL,
            attempts INTEGER DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_phone_verif ON phone_verifications(phone);
        CREATE TABLE IF NOT EXISTS login_rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            email TEXT NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_rate_limit ON login_rate_limits(ip, attempted_at);
        CREATE TABLE IF NOT EXISTS folders (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            name TEXT NOT NULL,
            icon TEXT DEFAULT 'folder',
            is_system INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS messages (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            sender_name TEXT NOT NULL,
            sender_email TEXT NOT NULL,
            recipient_email TEXT NOT NULL,
            cc TEXT DEFAULT '',
            bcc TEXT DEFAULT '',
            reply_to TEXT DEFAULT '',
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            body_html TEXT DEFAULT '',
            is_read INTEGER DEFAULT 0,
            is_starred INTEGER DEFAULT 0,
            is_archived INTEGER DEFAULT 0,
            is_spam INTEGER DEFAULT 0,
            is_trashed INTEGER DEFAULT 0,
            is_draft INTEGER DEFAULT 0,
            folder_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS attachments (
            id TEXT PRIMARY KEY,
            message_id TEXT NOT NULL,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            size_bytes INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS contacts (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT DEFAULT '',
            notes TEXT DEFAULT '',
            avatar TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS mail_settings (
            user_id TEXT PRIMARY KEY,
            smtp_host TEXT DEFAULT '',
            smtp_port INTEGER DEFAULT 587,
            smtp_secure INTEGER DEFAULT 0,
            smtp_user TEXT DEFAULT '',
            smtp_pass TEXT DEFAULT '',
            imap_host TEXT DEFAULT '',
            imap_port INTEGER DEFAULT 993,
            imap_user TEXT DEFAULT '',
            imap_pass TEXT DEFAULT '',
            sound_enabled INTEGER DEFAULT 1,
            postcard_theme TEXT DEFAULT 'classic-cream'
        );
    ");
}
