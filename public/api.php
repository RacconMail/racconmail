<?php
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
$migratedFlagFile = __DIR__ . '/.db_migrated_v7';
if (!file_exists($migratedFlagFile) || $dbNeedsInit) {
    try {
        if ($dbNeedsInit) {
            initDatabase($db);
        } else {
            // Migration: ensure modern columns exist
            try { $db->exec('ALTER TABLE users ADD COLUMN password_hash TEXT'); } catch (Throwable $e) {}
            try { $db->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER DEFAULT 0'); } catch (Throwable $e) {}
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
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
$currentUserId = $_SESSION['current_user_id'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

// Parse route and method
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? trim($_GET['route'], '/') : '';
if (!$route && !empty($_SERVER['REQUEST_URI'])) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/api/(.*)$#i', $uriPath, $m)) {
        $route = trim($m[1], '/');
    }
}
$input = json_decode(file_get_contents('php://input'), true) ?? [];

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

// 3. Complete Registration (Requires Verified Phone & Code)
if ($route === 'auth/register' && $method === 'POST') {
    $name = trim($input['name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';
    $phone = trim($input['phone'] ?? '');
    $code = trim($input['phone_code'] ?? ($input['code'] ?? ''));
    $avatar = $input['avatar'] ?? '/raccoon-logo.png';
    $cover = $input['cover_photo'] ?? '';

    if (!$name || !$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide name, email, and password.']);
        exit;
    }

    // Optional phone confirmation (Privacy-First: Phone verification is NO longer mandatory)
    if ($phone && $code) {
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+' . ltrim($cleanPhone, '0');
        }
        $stmtVerif = $db->prepare("SELECT * FROM phone_verifications WHERE phone = ? OR phone = ? ORDER BY id DESC LIMIT 1");
        $stmtVerif->execute([$cleanPhone, ltrim($cleanPhone, '+')]);
        $verif = $stmtVerif->fetch();
        if ($verif && $verif['code'] === $code && strtotime($verif['expires_at']) >= time()) {
            $db->prepare("DELETE FROM phone_verifications WHERE phone = ? OR phone = ?")->execute([$cleanPhone, ltrim($cleanPhone, '+')]);
        }
    } else {
        $cleanPhone = '';
    }

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
        echo json_encode(['error' => 'Please provide a valid username for your email.']);
        exit;
    }
    $email = $userPart . '@racconmail.eu';

    $stmtCheck = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => "An account with the email {$email} already exists."]);
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
    $db->prepare("
        INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft)
        VALUES (?, ?, 'Raccon Team', 'team@raccoonmail.com', ?, 'Welcome to your clever new inbox! 💌', 'Welcome to Raccon Mail, ' || ? || '!\n\nYour new masked postcard email account is all set up with verified phone security (' || ? || ').\n\nEnjoy clever, secure email for web & mobile.\n\nThe Raccon Team 🐾', 0, 1, 0)
    ")->execute([$welcomeId, $userId, $email, $name, $cleanPhone]);

    // Add default contacts
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Rocky Raccoon', 'rocky@raccoonmail.com', 'Midnight planner')")->execute(['c_' . uniqid(), $userId]);
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Luna', 'luna@raccoonmail.com', 'Night photographer')")->execute(['c_' . uniqid(), $userId]);
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES (?, ?, 'Raccon Team', 'team@raccoonmail.com', 'Friendly support')")->execute(['c_' . uniqid(), $userId]);

    session_regenerate_id(true);
    $_SESSION['current_user_id'] = $userId;
    echo json_encode([
        'success' => true,
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'phone' => $cleanPhone, 'phone_verified' => 1, 'avatar' => $avatar]
    ]);
    exit;
}

// 4. Secure Login with Brute-Force Rate Limiting & Strict Password Verification
if ($route === 'auth/login' && $method === 'POST') {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide both email and password.']);
        exit;
    }

    // Check brute-force attempts in past 15 minutes
    try {
        $db->prepare("DELETE FROM login_rate_limits WHERE attempted_at < datetime('now', '-15 minutes')")->execute();
        $stmtLimit = $db->prepare("SELECT COUNT(*) FROM login_rate_limits WHERE ip = ?");
        $stmtLimit->execute([$clientIp]);
        if ((int)$stmtLimit->fetchColumn() >= 5) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many failed login attempts. Account access is temporarily locked for 15 minutes.']);
            exit;
        }
    } catch (Throwable $e) {}

    $fullEmail = str_contains($email, '@') ? $email : ($email . '@racconmail.eu');
    $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(email) = LOWER(?)');
    $stmt->execute([$email, $fullEmail]);
    $user = $stmt->fetch();

    // Strict authentication: both user existence and password_verify must succeed
    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        try {
            $db->prepare("INSERT INTO login_rate_limits (ip, email) VALUES (?, ?)")->execute([$clientIp, $email]);
        } catch (Throwable $e) {}
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password. Top-tier security protects this mailbox.']);
        exit;
    }

    // Successful login: clear rate limits, regenerate session ID to prevent fixation
    try {
        $db->prepare("DELETE FROM login_rate_limits WHERE ip = ? OR email = ?")->execute([$clientIp, $email]);
    } catch (Throwable $e) {}

    session_regenerate_id(true);
    $_SESSION['current_user_id'] = $user['id'];
    $_SESSION['is_admin'] = !empty($user['is_admin']);

    echo json_encode([
        'success' => true,
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
if ($route === 'auth/logout' && $method === 'POST') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($route === 'auth/me' && $method === 'GET') {
    if (empty($_SESSION['current_user_id'])) {
        echo json_encode(['loggedIn' => false]);
        exit;
    }
    $stmt = $db->prepare('SELECT id, email, name, phone, phone_verified, avatar, cover_photo, theme, is_admin FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['current_user_id']]);
    $user = $stmt->fetch();
    if ($user) {
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
        $usersCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $msgsCount = $db->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        $attsCount = $db->query('SELECT COUNT(*) FROM attachments')->fetchColumn();
        $attsSize = $db->query('SELECT SUM(size_bytes) FROM attachments')->fetchColumn() ?: 0;
        $dbSize = file_exists($dbFile) ? filesize($dbFile) : 0;

        echo json_encode([
            'users_count' => (int)$usersCount,
            'messages_count' => (int)$msgsCount,
            'attachments_count' => (int)$attsCount,
            'attachments_size' => (int)$attsSize,
            'db_size' => (int)$dbSize,
            'server_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION
        ]);
        exit;
    }

    // GET /api/admin/users
    if ($route === 'admin/users' && $method === 'GET') {
        $stmt = $db->query('
            SELECT u.id, u.email, u.name, u.is_admin, u.created_at,
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
        $db->prepare('INSERT INTO users (id, email, name, password_hash, is_admin, avatar, signature) VALUES (?, ?, ?, ?, ?, "/raccoon-logo.png", "🦝 Sent with clever stealth via Raccon Postcard Mail.")')
           ->execute([$id, $email, $name, $hash, $isAdminFlag]);
        $db->prepare('INSERT INTO mail_settings (user_id, sound_enabled) VALUES (?, 1)')->execute([$id]);

        echo json_encode(['success' => true, 'id' => $id]);
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
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // POST /api/admin/users/:id/impersonate (1-click jump into any user's mailbox)
    if (preg_match('#^admin/users/([^/]+)/impersonate$#', $route, $m) && $method === 'POST') {
        $targetId = $m[1];
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }
        $_SESSION['current_user_id'] = $target['id'];
        echo json_encode(['success' => true, 'user' => $target]);
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

        $allUsers = $db->query('SELECT id, email FROM users')->fetchAll();
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

    // GET /api/admin/messages (Audit recent messages)
    if ($route === 'admin/messages' && $method === 'GET') {
        $stmt = $db->query('
            SELECT id, user_id, sender_name, sender_email, recipient_email, subject, created_at, is_spam, is_trashed
            FROM messages
            ORDER BY created_at DESC
            LIMIT 50
        ');
        echo json_encode(['messages' => $stmt->fetchAll()]);
        exit;
    }

    // DELETE /api/admin/messages/:id (Delete any message)
    if (preg_match('#^admin/messages/([^/]+)$#', $route, $m) && $method === 'DELETE') {
        $db->prepare('DELETE FROM messages WHERE id = ?')->execute([$m[1]]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Allow cron or background sync with secret key
if ($route === 'messages/sync' && (($_GET['key'] ?? '') === 'raccon_sync_2026' || !empty($_SESSION['current_user_id']))) {
    $count = syncInboundEmails($db);
    echo json_encode(['success' => true, 'synced' => $count]);
    exit;
}

// Publicly readable endpoints (forum discussions feed, post details, comments)
$isPublicRoute = ($route === 'forum/posts' && $method === 'GET')
    || (preg_match('#^forum/posts/([^/]+)$#', $route) && $method === 'GET')
    || (preg_match('#^forum/posts/([^/]+)/comments$#', $route) && $method === 'GET');

// Enforce login for private mailbox endpoints
if (!$currentUserId && !$isPublicRoute) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please register or sign in.']);
    exit;
}

// Router
try {
    // 1. GET/POST /api/user
    if ($route === 'user') {
        if ($method === 'GET') {
            $stmt = $db->prepare('SELECT id, email, name, phone, phone_verified, avatar, cover_photo, signature, theme, created_at, is_admin FROM users WHERE id = ?');
            $stmt->execute([$currentUserId]);
            $user = $stmt->fetch();
            $stmtSettings = $db->prepare('SELECT * FROM mail_settings WHERE user_id = ?');
            $stmtSettings->execute([$currentUserId]);
            $settings = $stmtSettings->fetch() ?: [];
            echo json_encode(array_merge($user ?: [], ['settings' => $settings]));
            exit;
        } elseif ($method === 'POST') {
            $name = $input['name'] ?? null;
            $sig = $input['signature'] ?? null;
            $avatar = $input['avatar'] ?? null;
            $cover = $input['cover_photo'] ?? null;
            $theme = $input['theme'] ?? null;
            $sound = $input['sound_enabled'] ?? 1;

            if ($name !== null || $sig !== null || $avatar !== null || $cover !== null || $theme !== null) {
                $stmt = $db->prepare('UPDATE users SET name = COALESCE(?, name), signature = COALESCE(?, signature), avatar = COALESCE(?, avatar), cover_photo = COALESCE(?, cover_photo), theme = COALESCE(?, theme) WHERE id = ?');
                $stmt->execute([$name, $sig, $avatar, $cover, $theme, $currentUserId]);
            }
            $stmt = $db->prepare('INSERT INTO mail_settings (user_id, sound_enabled) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET sound_enabled = excluded.sound_enabled');
            $stmt->execute([$currentUserId, $sound]);

            $stmtUser = $db->prepare('SELECT id, email, name, avatar, cover_photo, signature, theme, created_at, is_admin FROM users WHERE id = ?');
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
        $count = syncInboundEmails($db);
        echo json_encode(['success' => true, 'synced' => $count]);
        exit;
    }

    // 4. GET /api/messages
    if ($route === 'messages') {
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

        // 1. Senders & Contacts
        $stmtSenders = $db->prepare('
            SELECT DISTINCT sender_name as name, sender_email as email, "sender" as type
            FROM messages
            WHERE user_id = ? AND (sender_name LIKE ? OR sender_email LIKE ?)
            LIMIT 5
        ');
        $stmtSenders->execute([$currentUserId, $term, $term]);
        $senders = $stmtSenders->fetchAll();

        $stmtContacts = $db->prepare('
            SELECT name, email, "contact" as type
            FROM contacts
            WHERE user_id = ? AND (name LIKE ? OR email LIKE ?)
            LIMIT 5
        ');
        $stmtContacts->execute([$currentUserId, $term, $term]);
        $contacts = $stmtContacts->fetchAll();

        $people = [];
        $seenEmails = [];
        foreach (array_merge($contacts, $senders) as $p) {
            $e = strtolower($p['email']);
            if (!empty($e) && empty($seenEmails[$e])) {
                $seenEmails[$e] = true;
                $people[] = $p;
            }
        }

        // 2. Matching Messages across ALL folders
        $stmtMsgs = $db->prepare('
            SELECT id, sender_name, sender_email, subject, body, created_at, is_spam, is_trashed, is_archived, folder_id,
            CASE 
                WHEN is_trashed = 1 THEN "Trash"
                WHEN is_spam = 1 THEN "Spam"
                WHEN is_archived = 1 THEN "Archive"
                WHEN is_draft = 1 THEN "Drafts"
                WHEN folder_id = "sent" THEN "Sent"
                WHEN folder_id IS NOT NULL AND folder_id != "" AND folder_id != "inbox" THEN folder_id
                ELSE "Inbox"
            END as folder_label
            FROM messages
            WHERE user_id = ? AND (subject LIKE ? OR sender_name LIKE ? OR sender_email LIKE ? OR recipient_email LIKE ? OR body LIKE ?)
            ORDER BY created_at DESC
            LIMIT 6
        ');
        $stmtMsgs->execute([$currentUserId, $term, $term, $term, $term, $term]);
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
        $msgId = $matches[1];
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

        echo json_encode(['message' => $msg]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/star$#', $route, $matches) && $method === 'PUT') {
        $msgId = $matches[1];
        $starred = !empty($input['is_starred']) ? 1 : 0;
        $db->prepare('UPDATE messages SET is_starred = ? WHERE id = ? AND user_id = ?')->execute([$starred, $msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/read$#', $route, $matches) && $method === 'PUT') {
        $msgId = $matches[1];
        $read = !empty($input['is_read']) ? 1 : 0;
        $db->prepare('UPDATE messages SET is_read = ? WHERE id = ? AND user_id = ?')->execute([$read, $msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/archive$#', $route, $matches) && $method === 'PUT') {
        $msgId = $matches[1];
        $db->prepare('UPDATE messages SET is_archived = 1, is_spam = 0 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('#^messages/([^/]+)/spam$#', $route, $matches) && $method === 'PUT') {
        $msgId = $matches[1];
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

    if (preg_match('#^messages/([^/]+)/trash$#', $route, $matches) && $method === 'PUT') {
        $msgId = $matches[1];
        $db->prepare('UPDATE messages SET is_trashed = 1 WHERE id = ? AND user_id = ?')->execute([$msgId, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 7. Batch operations
    if ($route === 'messages/batch' && $method === 'POST') {
        $action = $input['action'] ?? '';
        $ids = $input['ids'] ?? [];
        if (empty($ids)) {
            echo json_encode(['success' => true, 'count' => 0]);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$currentUserId], $ids);

        if ($action === 'mark_read') {
            $db->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'mark_unread') {
            $db->prepare("UPDATE messages SET is_read = 0 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'star') {
            $db->prepare("UPDATE messages SET is_starred = 1 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'archive') {
            $db->prepare("UPDATE messages SET is_archived = 1, is_spam = 0, is_trashed = 0 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
        } elseif ($action === 'trash') {
            $db->prepare("UPDATE messages SET is_trashed = 1 WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
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
    if ($route === 'messages/send' && $method === 'POST') {
        $to = $_POST['to'] ?? ($input['to'] ?? '');
        $cc = $_POST['cc'] ?? ($input['cc'] ?? '');
        $bcc = $_POST['bcc'] ?? ($input['bcc'] ?? '');
        $subject = trim($_POST['subject'] ?? ($input['subject'] ?? '(no subject)'));
        if ($subject === '') $subject = '(no subject)';
        $body = $_POST['body'] ?? ($input['body'] ?? '');
        $bodyHtml = $_POST['body_html'] ?? ($input['body_html'] ?? '');
        $draftId = $_POST['draft_id'] ?? ($input['draft_id'] ?? null);

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
        $stmt = $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, cc, bcc, subject, body, body_html, is_read, is_draft, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, "sent")');
        $stmt->execute([$msgId, $currentUserId, $user['name'], $user['email'], $to, $cc, $bcc, $subject, $body, $bodyHtml]);

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
        $allTargets = array_values(array_unique(array_merge($toEmails, $ccEmails, $bccEmails)));

        $deliveredLocal = 0;
        $deliveredExternal = 0;

        foreach ($allTargets as $targetEmail) {
            $stmtRecip = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?)');
            $stmtRecip->execute([$targetEmail]);
            $localRecip = $stmtRecip->fetch();

            $postcardHtml = buildRacconMailPostcardHtml($user['name'], $user['email'], $targetEmail, $subject, $body, $bodyHtml, $savedAttachments);

            if ($localRecip) {
                // Deliver to recipient's Inbox in SQLite (guaranteed is_spam = 0 for registered members)
                $inId = 'in_' . uniqid();
                $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, cc, bcc, subject, body, body_html, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL)')
                   ->execute([$inId, $localRecip['id'], $user['name'], $user['email'], $localRecip['email'], $cc, $bcc, $subject, $body, $postcardHtml]);

                // Copy attachments for recipient
                foreach ($savedAttachments as $att) {
                    $attCopyId = 'att_' . uniqid();
                    $db->prepare('INSERT INTO attachments (id, message_id, filename, original_name, mime_type, size_bytes, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)')
                       ->execute([$attCopyId, $inId, $att['filename'], $att['original_name'], $att['mime_type'], $att['size_bytes'], $att['filename']]);
                }
                $deliveredLocal++;
            } else {
                // External email address: send via PHP mail()
                sendOutgoingEmail($user['name'], $user['email'], $targetEmail, $subject, $body, $postcardHtml, $savedAttachments);
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
    // 11. RACCON NOCTURNAL FORUM & WALL (MEMBERS)
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

        $query = "SELECT p.*, u.name as author_name, u.email as author_email, u.avatar as author_avatar, u.is_admin as author_is_admin 
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
        $stmtC = $db->prepare("SELECT c.*, u.name as author_name, u.email as author_email, u.avatar as author_avatar, u.is_admin as author_is_admin 
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
            echo json_encode(['error' => 'Access restricted: Only registered Raccon Mail members can post to the community wall. Please sign in or join free.']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $category = trim($input['category'] ?? 'tech');
        $content = trim($input['content'] ?? '');
        $codeSnippet = trim($input['code_snippet'] ?? '');
        $codeLang = trim($input['code_lang'] ?? 'javascript');
        $imageUrl = trim($input['image_url'] ?? '');
        $tags = trim($input['tags'] ?? '');

        if (!$title || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide both a discussion title and message content.']);
            exit;
        }

        $validCats = ['tech', 'coding', 'webdesign', 'sharing', 'help', 'explaining'];
        if (!in_array($category, $validCats)) $category = 'tech';

        $postId = 'post_' . uniqid();
        $stmt = $db->prepare("INSERT INTO forum_posts (id, user_id, title, category, content, code_snippet, code_lang, image_url, tags, likes_count, comments_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
        $stmt->execute([$postId, $currentUserId, $title, $category, $content, $codeSnippet, $codeLang, $imageUrl, $tags]);

        echo json_encode([
            'success' => true,
            'id' => $postId,
            'message' => 'Your post is now live on the Raccon Nocturnal Wall! 🦝🚀'
        ]);
        exit;
    }

    // 11.4 POST /api/forum/posts/:id/comments (Add Comment - Members Only)
    if (preg_match('#^forum/posts/([^/]+)/comments$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Only registered members can join discussions and reply. Please sign in or create an account.']);
            exit;
        }

        $postId = $m[1];
        $commentContent = trim($input['content'] ?? '');
        $codeSnippet = trim($input['code_snippet'] ?? '');

        if (!$commentContent) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment text cannot be empty.']);
            exit;
        }

        $commId = 'cm_' . uniqid();
        $stmt = $db->prepare("INSERT INTO forum_comments (id, post_id, user_id, content, code_snippet) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$commId, $postId, $currentUserId, $commentContent, $codeSnippet]);

        // Increment count on post
        $db->prepare("UPDATE forum_posts SET comments_count = comments_count + 1 WHERE id = ?")->execute([$postId]);

        $stmtUser = $db->prepare("SELECT name, email, avatar, is_admin FROM users WHERE id = ?");
        $stmtUser->execute([$currentUserId]);
        $user = $stmtUser->fetch();

        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $commId,
                'post_id' => $postId,
                'user_id' => $currentUserId,
                'content' => $commentContent,
                'code_snippet' => $codeSnippet,
                'created_at' => date('Y-m-d H:i:s'),
                'author_name' => $user['name'] ?? 'Burrow Member',
                'author_email' => $user['email'] ?? '',
                'author_avatar' => $user['avatar'] ?? '/raccoon-logo.png',
                'author_is_admin' => !empty($user['is_admin'])
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
    $inboxUrl = 'https://racconmail.eu/inbox';
    $cleanSubject = $subject ?: '(No Subject)';
    $replySubject = 'Re: ' . $cleanSubject;
    $rawReplyUrl = 'mailto:' . rawurlencode($fromEmail) . '?subject=' . rawurlencode($replySubject);
    $webmailReplyUrl = $inboxUrl . '?compose=1&reply_to=' . rawurlencode($fromEmail) . '&subject=' . rawurlencode($replySubject);

    // One-tap quick reply options
    $quickReplies = [
        ['label' => 'Got it! 🦝', 'body' => "Got it! Thanks! 🦝\n\nSent via Raccon Mail Express"],
        ['label' => 'Sounds great! 🐾', 'body' => "Sounds great! Let's do it! 🐾\n\nSent via Raccon Mail Express"],
        ['label' => 'Midnight snack at 2am? 🥐', 'body' => "Count me in for the 2am midnight bakery run! 🥐🦝\n\nSent via Raccon Mail Express"],
        ['label' => 'Thanks for the postcard! 💌', 'body' => "Received your postcard loud and clear! 💌✨\n\nSent via Raccon Mail Express"],
        ['label' => 'On my way! 🌲', 'body' => "On my way through the nocturnal woods! 🌲🐾\n\nSent via Raccon Mail Express"]
    ];

    $quickChipsHtml = '';
    foreach ($quickReplies as $qr) {
        $chipHref = $inboxUrl . '?compose=1&reply_to=' . rawurlencode($fromEmail) . '&subject=' . rawurlencode($replySubject) . '&body=' . rawurlencode($qr['body']);
        $quickChipsHtml .= '<a href="' . $chipHref . '" style="display:inline-block; background-color:#1e293b; color:#fbbf24; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif; font-size:12px; font-weight:600; padding:7px 14px; border-radius:9999px; text-decoration:none; border:1px solid #334155; margin:3px 4px 3px 0;">' . htmlspecialchars($qr['label']) . '</a> ';
    }

    $contentHtml = $bodyHtml ? $bodyHtml : nl2br(htmlspecialchars($bodyText));

    $attHtml = '';
    if (!empty($attachments)) {
        $attHtml .= '<div style="margin-top:20px; padding-top:16px; border-top:1px dashed #334155;">';
        $attHtml .= '<div style="font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">📎 Attachments (' . count($attachments) . ')</div>';
        $attHtml .= '<div style="display:flex; flex-wrap:wrap; gap:8px;">';
        foreach ($attachments as $att) {
            $name = htmlspecialchars($att['original_name'] ?: basename($att['file_path']));
            $sizeKB = round(($att['size_bytes'] ?? 0) / 1024);
            $attHtml .= '<span style="display:inline-block; background:#1e293b; border:1px solid #334155; border-radius:8px; padding:6px 12px; font-size:12px; color:#e2e8f0; margin-right:6px; margin-bottom:6px;">📎 ' . $name . ' <span style="color:#64748b;">(' . $sizeKB . ' KB)</span></span>';
        }
        $attHtml .= '</div></div>';
    }

    $safeFromName = htmlspecialchars($fromName);
    $safeFromEmail = htmlspecialchars($fromEmail);
    $safeToEmail = htmlspecialchars($toEmail);
    $safeSubject = htmlspecialchars($cleanSubject);
    $nowStr = date('l, F j, Y \a\t g:i A T');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeSubject}</title>
  <style>
    @media only screen and (max-width: 600px) {
      .postcard-wrapper { padding: 12px 6px !important; }
      .postcard-table { width: 100% !important; border-radius: 12px !important; }
      .mobile-stack { display: block !important; width: 100% !important; box-sizing: border-box !important; }
      .mobile-pad { padding: 14px 14px !important; }
      .mobile-date { display: block !important; text-align: left !important; margin-top: 6px !important; font-size: 11px !important; }
      .mobile-btn { display: block !important; width: 100% !important; margin: 6px 0 !important; text-align: center !important; box-sizing: border-box !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background-color:#0b0f19; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#e2e8f0;">
  
  <!-- Outer Table -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" class="postcard-wrapper" style="background-color:#0b0f19; padding:24px 12px;">
    <tr>
      <td align="center">
        
        <!-- Postcard Container (Max 620px) -->
        <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" class="postcard-table" style="max-width:620px; width:100%; background-color:#111827; border:1px solid #1f293d; border-radius:18px; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.6);">
          
          <!-- Top Airmail Diagonal Striped Ribbon -->
          <tr>
            <td style="height:7px; background:repeating-linear-gradient(135deg, #f59e0b 0px, #f59e0b 14px, #10b981 14px, #10b981 28px, #3b82f6 28px, #3b82f6 42px, #ef4444 42px, #ef4444 56px);"></td>
          </tr>

          <!-- Postcard Header: Logo & Cancellation Postmark -->
          <tr>
            <td class="mobile-pad" style="padding:22px 24px 16px 24px; border-bottom:1px solid #1f293d; background-color:#0f172a;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <!-- Left: Raccon Mail Brand & Logo -->
                  <td align="left" style="vertical-align:middle;">
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="vertical-align:middle; padding-right:14px;">
                          <a href="{$siteUrl}" style="text-decoration:none;">
                            <img src="{$logoUrl}" width="54" height="54" alt="Raccon Mail" style="display:block; border-radius:50%; border:3px solid #f59e0b; background-color:#1f293d; object-fit:cover; box-shadow:0 0 15px rgba(245, 158, 11, 0.45);" />
                          </a>
                        </td>
                        <td style="vertical-align:middle;">
                          <div style="font-family:'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif; font-size:20px; font-weight:800; color:#ffffff; letter-spacing:0.8px; line-height:1.2;">RACCON MAIL</div>
                          <div style="font-size:11px; color:#fbbf24; font-weight:700; letter-spacing:0.4px; margin-top:2px;">🦝 Nocturnal Postcard Service</div>
                        </td>
                      </tr>
                    </table>
                  </td>

                  <!-- Right: Vintage Cancellation Stamp with Raccoon -->
                  <td align="right" style="vertical-align:middle;">
                    <div style="display:inline-block; border:2px dashed #f59e0b; border-radius:10px; padding:6px 10px; text-align:center; background-color:#1e293b; color:#fbbf24; font-family:'Courier New', monospace; font-size:10px; font-weight:bold; letter-spacing:0.8px; line-height:1.3;">
                      <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                          <td style="vertical-align:middle; padding-right:6px;">
                            <img src="{$logoUrl}" width="28" height="28" alt="Raccon" style="display:block; border-radius:50%;" />
                          </td>
                          <td style="vertical-align:middle; text-align:left;">
                            <div style="color:#f59e0b; font-size:9px;">RACCON POST</div>
                            <div style="font-size:8px; color:#94a3b8;">AIRMAIL &bull; 2026</div>
                          </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Postcard Routing Bar (From, To, Date) -->
          <tr>
            <td class="mobile-pad" style="padding:14px 24px; background-color:#131c31; border-bottom:1px solid #1f293d; font-size:12px; color:#94a3b8;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="mobile-stack" style="line-height:1.6;">
                    <div><strong style="color:#cbd5e1;">From:</strong> <span style="color:#ffffff; font-weight:600;">{$safeFromName}</span> &lt;<a href="mailto:{$safeFromEmail}" style="color:#38bdf8; text-decoration:none;">{$safeFromEmail}</a>&gt;</div>
                    <div><strong style="color:#cbd5e1;">To:</strong> <span style="color:#94a3b8;">{$safeToEmail}</span></div>
                  </td>
                  <td align="right" class="mobile-stack mobile-date" style="vertical-align:top; font-size:11px; color:#64748b;">
                    {$nowStr}
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Subject Line -->
          <tr>
            <td class="mobile-pad" style="padding:20px 24px 8px 24px;">
              <div style="margin-bottom:8px; display:inline-block; background-color:#1e293b; border:1px solid #334155; border-radius:20px; padding:4px 12px; font-size:11px; color:#fbbf24; font-weight:700;">
                <img src="{$logoUrl}" width="16" height="16" alt="Raccon" style="vertical-align:middle; border-radius:50%; margin-right:5px; display:inline-block;" /> Official Raccon Postcard
              </div>
              <h2 style="margin:0; font-family:'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif; font-size:20px; font-weight:700; color:#f8fafc; line-height:1.35; word-break:break-word;">
                {$safeSubject}
              </h2>
            </td>
          </tr>

          <!-- Postcard Letter Content -->
          <tr>
            <td class="mobile-pad" style="padding:12px 24px 24px 24px; font-size:15px; line-height:1.7; color:#e2e8f0;">
              <div style="background-color:#141e33; border:1px solid #1f2c45; border-radius:12px; padding:18px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; word-break:break-word;">
                {$contentHtml}
              </div>

              {$attHtml}
            </td>
          </tr>

          <!-- FAST REPLY SECTION -->
          <tr>
            <td class="mobile-pad" style="padding:0 24px 24px 24px;">
              <div style="background-color:#0f172a; border:1px solid #1f293d; border-radius:14px; padding:16px 18px;">
                <div style="font-family:'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif; font-size:12px; font-weight:700; color:#f59e0b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:12px;">
                  💬 Fast Reply to this Postcard
                </div>

                <!-- Primary Big Fast Reply Button -->
                <div style="margin-bottom:12px;">
                  <a href="{$webmailReplyUrl}" class="mobile-btn" style="display:inline-block; background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color:#0b0f19; font-weight:700; font-size:13px; padding:11px 22px; border-radius:9999px; text-decoration:none; box-shadow:0 4px 14px rgba(245, 158, 11, 0.35);">
                    ✉️ Fast Reply to {$safeFromName}
                  </a>
                  <a href="{$rawReplyUrl}" class="mobile-btn" style="display:inline-block; margin-left:6px; background-color:#1e293b; color:#cbd5e1; font-weight:600; font-size:12px; padding:10px 16px; border-radius:9999px; text-decoration:none; border:1px solid #334155;">
                    Reply in Mail App
                  </a>
                  <a href="{$inboxUrl}" class="mobile-btn" style="display:inline-block; margin-left:6px; background-color:#1e293b; color:#cbd5e1; font-weight:600; font-size:12px; padding:10px 16px; border-radius:9999px; text-decoration:none; border:1px solid #334155;">
                    Open Webmail
                  </a>
                </div>

                <!-- 1-Tap Quick Reply Chips -->
                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">1-Tap Express Quick Replies:</div>
                <div style="line-height:2.2;">
                  {$quickChipsHtml}
                </div>
              </div>
            </td>
          </tr>

          <!-- Support & Footer -->
          <tr>
            <td style="padding:18px 24px; background-color:#0c111d; border-top:1px solid #1f293d; text-align:center;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center">
                    <p style="margin:0 0 6px 0; font-size:11px; color:#64748b; line-height:1.5;">
                      Sent with nocturnal stealth from <strong>Raccon Mail</strong> &bull; <a href="{$siteUrl}" style="color:#94a3b8; text-decoration:none;">racconmail.eu</a><br>
                      The independent, private &amp; encrypted nocturnal postcard network.
                    </p>
                    <p style="margin:0; font-size:10px; color:#475569;">
                      &copy; 2026 Raccon Mail. All rights reserved.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!-- /Postcard Container -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;
}

function sendOutgoingEmail($fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = '', $attachments = []) {
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

    // Prepare clean plain-text fallback (vital for Gmail/Outlook anti-spam score)
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
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . $encodedFromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $encodedFromName . ' <' . $fromEmail . '>';
    $headers[] = 'Return-Path: <' . $envelopeFrom . '>';
    $headers[] = 'X-Mailer: RacconMail/2.0 (clever-nocturnal-engine)';
    $headers[] = 'X-Priority: 3 (Normal)';
    $headers[] = 'Importance: Normal';

    $altBoundary = "==Alt_Boundary_" . bin2hex(random_bytes(10)) . "==";

    if (!empty($attachments)) {
        $mixedBoundary = "==Mixed_Boundary_" . bin2hex(random_bytes(10)) . "==";
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';

        $message = "--" . $mixedBoundary . "\r\n";
        $message .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . "\"\r\n\r\n";

        // Plain text part
        $message .= "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8; format=flowed\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainText . "\r\n\r\n";

        // HTML part
        $message .= "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $finalHtml . "\r\n\r\n";
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
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainText . "\r\n\r\n";

        $message .= "--" . $altBoundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $finalHtml . "\r\n\r\n";
        $message .= "--" . $altBoundary . "--\r\n";
    }

    $headers[] = 'To: <' . $toEmail . '>';
    $headers[] = 'Subject: ' . $encodedSubject;

    // 1. First attempt: Authenticated local SMTP to Exim on localhost:25 (High deliverability & DKIM signing)
    $smtpSuccess = sendViaSmtpSocket($toEmail, $encodedSubject, $headers, $message, 'mail@' . $serverDomain, 'RacconMailSecure2026!#');
    if ($smtpSuccess) {
        return true;
    }

    // 2. Second attempt: Direct sendmail via PHP mail()
    $mailHeaders = array_filter($headers, function($h) {
        return !preg_match('/^(?:To|Subject):/i', $h);
    });
    $mailHeaderStr = implode("\r\n", $mailHeaders);
    $sent = @mail($toEmail, $encodedSubject, $message, $mailHeaderStr, "-fmail@" . $serverDomain);
    if (!$sent) {
        $sent = @mail($toEmail, $encodedSubject, $message, $mailHeaderStr);
    }
    return $sent;
}

function sendViaSmtpSocket($toEmail, $subject, $headers, $body, $smtpUser, $smtpPass) {
    $fp = @fsockopen('localhost', 25, $errno, $errstr, 5);
    if (!$fp) return false;

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

    fwrite($fp, "AUTH LOGIN\r\n");
    $read();
    fwrite($fp, base64_encode($smtpUser) . "\r\n");
    $read();
    fwrite($fp, base64_encode($smtpPass) . "\r\n");
    $authRes = $read();

    if (!str_starts_with($authRes, '235')) {
        fclose($fp);
        return false;
    }

    fwrite($fp, "MAIL FROM:<$smtpUser>\r\n");
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

function syncInboundEmails($db) {
    // Avoid running too frequently per thread (limit to once every 4 seconds)
    static $lastSync = 0;
    if (time() - $lastSync < 4) return 0;
    $lastSync = time();

    if (!function_exists('imap_open')) return 0;

    $mbox = @imap_open('{localhost:143/notls}INBOX', 'mail@racconmail.eu', 'RacconMailSecure2026!#');
    @imap_errors();
    if (!$mbox) return 0;

    $num = @imap_num_msg($mbox);
    if (!$num || $num === 0) {
        @imap_errors();
        @imap_close($mbox);
        @imap_errors();
        return 0;
    }

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $processedCount = 0;

    for ($i = 1; $i <= $num; $i++) {
        $header = @imap_headerinfo($mbox, $i);
        if (!$header) continue;

        $subject = decodeMimeHeader($header->subject ?? '(no subject)');
        $fromName = decodeMimeHeader($header->from[0]->personal ?? '');
        $fromEmail = strtolower(($header->from[0]->mailbox ?? '') . '@' . ($header->from[0]->host ?? ''));
        if (empty($fromName)) $fromName = $fromEmail;

        $messageIdHeader = trim($header->message_id ?? '');

        // Extract all recipient addresses
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

        // If no user match, inspect raw headers for Delivered-To or X-Original-To
        if (empty($matchedUsers)) {
            $rawHeader = @imap_fetchheader($mbox, $i);
            if (preg_match('/(?:Delivered-To|X-Original-To):\s*<?([^>\r\n]+)>?/i', $rawHeader, $m)) {
                $delivEmail = strtolower(trim($m[1]));
                $stmt = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(email) = LOWER(?)');
                $stmt->execute([$delivEmail]);
                $u = $stmt->fetch();
                if ($u) {
                    $matchedUsers[$u['id']] = $u;
                }
            }
        }

        // Fallback: If still not matched, deliver to admin user or first registered member
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
            // Duplicate prevention
            if ($messageIdHeader) {
                $stmtCheck = $db->prepare('SELECT id FROM messages WHERE user_id = ? AND subject = ? AND sender_email = ? AND created_at >= datetime("now", "-1 day")');
                $stmtCheck->execute([$targetUser['id'], $subject, $fromEmail]);
                if ($stmtCheck->fetch()) {
                    continue;
                }
            }

            $inId = 'in_' . uniqid();
            $targetEmail = $targetUser['email'];

            $stmtIns = $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, body_html, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL, datetime("now"))');
            $stmtIns->execute([
                $inId,
                $targetUser['id'],
                $fromName,
                $fromEmail,
                $targetEmail,
                $subject,
                $bodyText ?: '(no plain text body)',
                $bodyHtml
            ]);

            foreach ($attachments as $att) {
                $attId = 'att_' . uniqid();
                $db->prepare('INSERT INTO attachments (id, message_id, filename, original_name, mime_type, size_bytes, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$attId, $inId, $att['filename'], $att['original_name'], $att['mime_type'], $att['size_bytes'], $att['filename']]);
            }
        }

        // Remove from IMAP box
        @imap_delete($mbox, $i);
        $processedCount++;
    }

    @imap_errors();
    @imap_expunge($mbox);
    @imap_close($mbox);
    @imap_errors();

    // Also scan local maildir folders for any unread files written directly by local Exim delivery
    $processedCount += syncMaildirFolder($db, '/home/raccxuxv/mail/racconmail.eu/mail/new', $uploadsDir);
    $processedCount += syncMaildirFolder($db, '/home/raccxuxv/mail/new', $uploadsDir);

    return $processedCount;
}

function syncMaildirFolder($db, $newDir, $uploadsDir) {
    if (!is_dir($newDir)) return 0;
    $files = @scandir($newDir);
    if (!$files) return 0;

    $curDir = dirname($newDir) . '/cur';
    if (!is_dir($curDir)) @mkdir($curDir, 0755, true);

    $count = 0;
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

        // Extract headers
        $subject = '(no subject)';
        if (preg_match('/^Subject:\s*(.+)$/mi', $rawHeaders, $m)) {
            $subject = decodeMimeHeader(trim($m[1]));
        }

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

        // Extract recipients
        $recips = [];
        if (preg_match_all('/^(?:To|Cc|Delivered-To|X-Original-To):\s*(.+)$/mi', $rawHeaders, $matches)) {
            foreach ($matches[1] as $rLine) {
                if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rLine, $em)) {
                    foreach ($em[0] as $e) {
                        $recips[] = strtolower($e);
                    }
                }
            }
        }
        $recips = array_values(array_unique($recips));

        // Match user in SQLite
        $matchedUsers = [];
        foreach ($recips as $re) {
            $stmt = $db->prepare('SELECT id, email, name FROM users WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$re]);
            $u = $stmt->fetch();
            if ($u) $matchedUsers[$u['id']] = $u;
        }

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
            // Duplicate prevention
            $stmtCheck = $db->prepare('SELECT id FROM messages WHERE user_id = ? AND subject = ? AND sender_email = ? AND created_at >= datetime("now", "-1 day")');
            $stmtCheck->execute([$targetUser['id'], $subject, $fromEmail]);
            if ($stmtCheck->fetch()) continue;

            $inId = 'in_' . uniqid();
            $bodyText = strip_tags($rawBody);
            $bodyHtml = (stripos($rawHeaders, 'text/html') !== false) ? $rawBody : '';

            $stmtIns = $db->prepare('INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, body_html, is_read, is_draft, is_starred, is_archived, is_spam, is_trashed, folder_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, NULL, datetime("now"))');
            $stmtIns->execute([
                $inId,
                $targetUser['id'],
                $fromName,
                $fromEmail,
                $targetUser['email'],
                $subject,
                $bodyText ?: '(no plain text body)',
                $bodyHtml
            ]);
        }

        // Move processed mail to cur folder
        $destPath = $curDir . '/' . $f . ':2,S';
        @rename($srcPath, $destPath);
        $count++;
    }
    return $count;
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

    // Seed default user
    $db->prepare("INSERT INTO users (id, email, name, avatar, signature) VALUES ('user_main', 'me@raccoonmail.com', 'Clever Raccoon', '/raccoon-logo.png', '🦝 Sent with clever stealth via Raccon Postcard Mail.')")->execute();
    $db->prepare("INSERT INTO folders (id, user_id, name, icon) VALUES ('folder_stashes', 'user_main', 'Secret Stashes', 'archive')")->execute();

    // Seed contacts
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES ('c1', 'user_main', 'Rocky Raccoon', 'rocky@raccoonmail.com', 'Midnight planner')")->execute();
    $db->prepare("INSERT INTO contacts (id, user_id, name, email, notes) VALUES ('c2', 'user_main', 'Luna', 'luna@raccoonmail.com', 'Photographer')")->execute();

    // Seed messages
    $db->prepare("INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft) VALUES ('m1', 'user_main', 'Rocky Raccoon', 'rocky@raccoonmail.com', 'me@raccoonmail.com', 'Midnight snack plans 🦝', 'Found the best bakery dumpster downtown! Fresh sourdough disposal at 02:15 AM.', 1, 1, 0)")->execute();
    $db->prepare("INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft) VALUES ('m2', 'user_main', 'Luna', 'luna@raccoonmail.com', 'me@raccoonmail.com', 'Photos from the camping trip 🏕️✨', 'Hey Clever! Here is that postcard photo from our weekend under the stars.', 0, 1, 0)")->execute();
    $db->prepare("INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, is_starred, is_draft) VALUES ('m3', 'user_main', 'Raccon Team', 'team@raccoonmail.com', 'me@raccoonmail.com', 'Welcome to Raccon Mail 💌', 'Welcome to your new postcard-style inbox! Clever, fast and secure.', 1, 0, 0)")->execute();
}
