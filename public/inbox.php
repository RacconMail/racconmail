<?php
if (session_status() === PHP_SESSION_NONE) {
    $cookieDomain = (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'racconmail.eu')) ? '.racconmail.eu' : '';
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days persistent session
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$token = $_GET['token'] ?? ($_COOKIE['raccon_token'] ?? null);
$db = null;
try {
    $db = new PDO('sqlite:' . __DIR__ . '/raccoon_mail.db', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {}

if (empty($_SESSION['current_user_id']) && $token && $db) {
    try {
        $stmtTok = $db->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > datetime('now')");
        $stmtTok->execute([$token]);
        $tokUid = $stmtTok->fetchColumn();
        if ($tokUid) {
            $_SESSION['current_user_id'] = $tokUid;
        }
    } catch (Throwable $e) {}
}

// If user is not authenticated, redirect immediately to landing page
if (empty($_SESSION['current_user_id']) || empty($db)) {
    header("Location: https://racconmail.eu/");
    exit;
}

$currentUser = null;
try {
    $stmtUser = $db->prepare("SELECT id, name, email, avatar, signature, theme FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['current_user_id']]);
    $currentUser = $stmtUser->fetch();
} catch (Throwable $e) {}

if (!$currentUser) {
    $_SESSION = [];
    header("Location: https://racconmail.eu/");
    exit;
}
if (empty($currentUser['avatar'])) {
    $currentUser['avatar'] = '/raccoon-logo.svg';
}

$uid = $currentUser['id'];
$uemail = $currentUser['email'];


function cleanMimeEmailContent($raw) {
    if (empty($raw) || !is_string($raw)) return '';
    $str = trim($raw);

    // Detect ZIP or GZIP binary data stored directly in message body (e.g. DMARC automated reports)
    if (str_starts_with($str, "PK\x03\x04")) {
        return "[Binary Archive: ZIP file / DMARC Report attachment]";
    }
    if (str_starts_with($str, "\x1f\x8b")) {
        return "[Binary Archive: GZIP compressed data attachment]";
    }

    // Strip null bytes
    $str = str_replace("\0", '', $str);

    // Ensure initial UTF-8 validity
    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    if (str_contains($str, '--') || preg_match('/content-type:\s*(text\/plain|text\/html|multipart)/i', $str)) {
        if (preg_match('/content-type:\s*text\/plain[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n([\s\S]*?)(?=--[a-zA-Z0-9_\-\.]+|\z)/i', $str, $pm)) {
            $candidate = trim($pm[1]);
            if (!empty($candidate)) {
                $str = $candidate;
            }
        } elseif (preg_match('/content-type:\s*text\/html[^\r\n]*\r?\n(?:[^\r\n]+\r?\n)*\r?\n([\s\S]*?)(?=--[a-zA-Z0-9_\-\.]+|\z)/i', $str, $hm)) {
            $candidate = trim($hm[1]);
            if (!empty($candidate)) {
                $str = trim(strip_tags($candidate));
            }
        } else {
            $str = preg_replace('/--[a-zA-Z0-9_\-\.]+(?:--)?/', '', $str);
            $str = preg_replace('/content-type:[^\r\n]+/i', '', $str);
            $str = preg_replace('/content-transfer-encoding:[^\r\n]+/i', '', $str);
            $str = preg_replace('/charset=["\']?[^"\'\r\n]+["\']?/i', '', $str);
        }
    }

    $str = preg_replace('/^--[a-zA-Z0-9_\-\.]+/m', '', $str);
    $str = preg_replace('/--[a-zA-Z0-9_\-\.]+--\s*$/m', '', $str);
    $str = preg_replace('/^[ \t]*Content-Type:[^\r\n]*/mi', '', $str);
    $str = preg_replace('/^[ \t]*Content-Transfer-Encoding:[^\r\n]*/mi', '', $str);

    if (str_contains($str, '=20') || str_contains($str, '=0A') || str_contains($str, "=\r\n")) {
        $str = quoted_printable_decode($str);
    }

    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = @iconv('UTF-8', 'UTF-8//IGNORE', $str) ?: '';
    }

    return trim($str);
}

// Calculate REAL counts for each folder
$counts = [
    'inbox_total' => 0,
    'inbox_unread' => 0,
    'unread_total' => 0,
    'starred_total' => 0,
    'drafts_total' => 0,
    'sent_total' => 0,
    'archive_total' => 0,
    'spam_total' => 0,
    'trash_total' => 0
];

$initialInboxMessages = [];

try {
    // 1. Inbox counts
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM messages WHERE user_id = ? AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = '' OR folder_id = 'inbox' OR folder_id = 'updates')");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    $counts['inbox_total'] = (int)($row['total'] ?? 0);
    $counts['inbox_unread'] = (int)($row['unread'] ?? 0);

    // 2. Unread total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_read = 0 AND is_draft = 0 AND is_spam = 0 AND is_trashed = 0");
    $stmt->execute([$uid]);
    $counts['unread_total'] = (int)$stmt->fetchColumn();

    // 3. Starred total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_starred = 1 AND is_trashed = 0");
    $stmt->execute([$uid]);
    $counts['starred_total'] = (int)$stmt->fetchColumn();

    // 4. Drafts total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_draft = 1 AND is_trashed = 0");
    $stmt->execute([$uid]);
    $counts['drafts_total'] = (int)$stmt->fetchColumn();

    // 5. Sent total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND folder_id = 'sent' AND is_trashed = 0");
    $stmt->execute([$uid]);
    $counts['sent_total'] = (int)$stmt->fetchColumn();

    // 6. Archive total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_archived = 1 AND is_trashed = 0");
    $stmt->execute([$uid]);
    $counts['archive_total'] = (int)$stmt->fetchColumn();

    // 7. Spam total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_spam = 1 AND is_trashed = 0");
    $stmt->execute([$uid]);
    $counts['spam_total'] = (int)$stmt->fetchColumn();

    // 8. Trash total
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_trashed = 1");
    $stmt->execute([$uid]);
    $counts['trash_total'] = (int)$stmt->fetchColumn();

    // Fetch initial messages for Inbox
    $stmtMsgs = $db->prepare("SELECT id, user_id, sender_name, sender_email, recipient_email, cc, bcc, reply_to, subject, body, body_html, is_read, is_starred, is_archived, is_spam, is_trashed, is_draft, folder_id, created_at FROM messages WHERE user_id = ? AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = '' OR folder_id = 'inbox' OR folder_id = 'updates') AND NOT (LOWER(sender_email) LIKE '%mailer-daemon%' OR LOWER(sender_name) LIKE '%mail delivery%' OR LOWER(subject) LIKE '%delayed%' OR LOWER(body) LIKE '%created automatically by mail delivery software%' OR LOWER(body) LIKE '%myboostmobile.com%') ORDER BY created_at DESC LIMIT 50");
    $stmtMsgs->execute([$uid]);
    $initialInboxMessages = $stmtMsgs->fetchAll();
    foreach ($initialInboxMessages as &$im) {
        $im['body'] = cleanMimeEmailContent($im['body'] ?? '');
        $im['subject'] = cleanMimeEmailContent($im['subject'] ?? '');
        $im['sender_name'] = cleanMimeEmailContent($im['sender_name'] ?? '');
        $im['sender_email'] = cleanMimeEmailContent($im['sender_email'] ?? '');
    }
    unset($im);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100/70 notranslate" translate="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="Raccon Mail" />
  <meta name="theme-color" content="#FFFFFF" />
  <meta name="google" content="notranslate" />
  <title>Raccon Mail &mdash; All Emails</title>
  <meta name="description" content="Clever, minimal, encrypted email inbox with email zen precision." />

  <!-- Google AdSense (Yahoo Mail Style In-Feed & Top Placements) -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4083745907535838" crossorigin="anonymous"></script>

  <!-- Favicon Suite -->
  <link rel="icon" href="/favicon.ico" sizes="48x48" />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
  <link rel="manifest" href="/site.webmanifest" />

  <!-- Tailwind CSS & Google Fonts -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function toggleInboxNotificationPanel() {
      const modal = document.getElementById('inbox-notifications-modal');
      if (modal) modal.classList.toggle('hidden');
    }
    function handleInboxNotificationClick(fromButton = false) {
      toggleInboxNotificationPanel();
      if (window.RacconNotifications && typeof RacconNotifications.requestPermission === 'function') {
        RacconNotifications.requestPermission().then(function(perm) {
          if (perm === 'granted') {
            RacconNotifications.show({
              title: 'Raccoon Mail Notifications Active! 🔔',
              body: 'Real-time push alerts for incoming emails and productivity.',
              url: '/inbox'
            });
          }
        }).catch(function(err) {
          console.warn('Notifications permission notice:', err);
        });
      }
      if (typeof showToast === 'function') {
        showToast('Notifications Active! 🔔');
      }
    }
    window.toggleInboxNotificationPanel = toggleInboxNotificationPanel;
    window.handleInboxNotificationClick = handleInboxNotificationClick;
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['"DM Sans"', 'system-ui', '-apple-system', 'sans-serif'],
            display: ['"Space Grotesk"', 'system-ui', 'sans-serif']
          },
          colors: {
            coral: '#FF4D6D'
          }
        }
      }
    }
  </script>

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="/js/notifications.js"></script>

  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    
    /* New Warm Modern Card Design matching reference */
    .msg-card-active {
      background: #FFFFFF !important;
      border-radius: 24px !important;
      border: 2px solid #F68D72 !important;
      box-shadow: 0 10px 25px -4px rgba(246, 141, 114, 0.25), 0 4px 10px -2px rgba(0, 0, 0, 0.04) !important;
    }
    .msg-card-inactive {
      background: #F8F9FA !important;
      border-radius: 24px !important;
      border: 1px solid rgba(241, 245, 249, 0.95) !important;
      transition: all 0.18s ease;
      cursor: pointer;
    }
    .msg-card-inactive:hover {
      background: #FFFFFF !important;
      border-color: #E2E8F0 !important;
      box-shadow: 0 6px 18px -4px rgba(0, 0, 0, 0.05) !important;
    }

    .category-filter-pill {
      padding: 7px 15px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 700;
      transition: all 0.18s ease;
      cursor: pointer;
      white-space: nowrap;
      user-select: none;
    }
    .active-category-pill {
      background: #F68D72 !important;
      color: #FFFFFF !important;
      box-shadow: 0 4px 14px rgba(246, 141, 114, 0.35) !important;
    }
    .inactive-category-pill {
      background: #F3F4F6 !important;
      color: #374151 !important;
    }
    .inactive-category-pill:hover {
      background: #E5E7EB !important;
      color: #111827 !important;
    }

    /* Active Message Card Elevation & Modern Turquoise Reading Glow */
    .msg-card-active {
      background: #FFFFFF !important;
      border-radius: 18px !important;
      border: 2px solid #06b6d4 !important;
      box-shadow: 0 0 25px 6px rgba(6, 182, 212, 0.35), 0 10px 25px -4px rgba(15, 23, 42, 0.08) !important;
      animation: readEmailGlow 3s ease-in-out infinite alternate !important;
    }

    




    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Space+Grotesk:wght@500;600;700;800&display=swap');

    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
      background-color: #FFFFFF;
      font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    /* Form inputs and textareas MUST have selectable, editable text */
    input, textarea, [contenteditable="true"] {
      user-select: text !important;
      -webkit-user-select: text !important;
      -moz-user-select: text !important;
      cursor: text !important;
    }

    /* All icons gray by default ("iconite gri") */
    i[data-lucide], .lucide {
      color: #94A3B8;
      stroke: #94A3B8;
    }

    /* =========================================================================
       LINKUP SOFT-ELEVATION DESIGN SYSTEM (PURE WHITE CARDS & PASTEL SOFT PILLS)
       ========================================================================= */
    body {
      background-color: #F8F9FB !important;
      color: #0F172A !important;
    }

    /* Active Nav Item - Soft Turquoise/Cyan Pill like reference "Dashboard" */
    .nav-pill-active {
      background: #E8F8F9 !important;
      color: #0284C7 !important;
      border-radius: 16px !important;
      box-shadow: 0 4px 14px rgba(14, 165, 233, 0.08) !important;
      font-weight: 800 !important;
    }
    .nav-pill-active i, .nav-pill-active svg {
      color: #0284C7 !important;
      stroke: #0284C7 !important;
    }

    /* Inactive nav items */
    .nav-pill-inactive {
      color: #64748B;
      border-radius: 16px;
      transition: all 0.16s ease;
      cursor: pointer;
    }
    .nav-pill-inactive:hover {
      background-color: #F1F4F9;
      color: #0F172A;
    }
    .nav-pill-inactive:hover i, .nav-pill-inactive:hover svg {
      color: #475569;
      stroke: #475569;
    }

    /* Active Message Card Elevation */
    .msg-card-active {
      background: #FFFFFF !important;
      border-radius: 20px !important;
      box-shadow: 0 12px 32px -4px rgba(14, 165, 233, 0.12), 0 4px 12px -2px rgba(0, 0, 0, 0.03) !important;
      border: 1.5px solid #0EA5E9 !important;
    }

    /* Inactive message card - Clean White Card with soft elevation */
    .msg-card-inactive {
      background: #FFFFFF !important;
      border-radius: 18px !important;
      border: 1px solid rgba(226, 232, 240, 0.7) !important;
      box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.03) !important;
      margin-bottom: 6px;
      transition: all 0.18s ease;
      cursor: pointer;
    }
    .msg-card-inactive:hover {
      background: #FFFFFF !important;
      border-color: #CBD5E1 !important;
      box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.06) !important;
      transform: translateY(-1px);
    }

    /* Linkup Elevated Surface */
    .linkup-card {
      background: #FFFFFF !important;
      border-radius: 24px !important;
      border: 1px solid rgba(226, 232, 240, 0.7) !important;
      box-shadow: 0 10px 30px -4px rgba(15, 23, 42, 0.04), 0 6px 12px -2px rgba(15, 23, 42, 0.02) !important;
    }

    /* Pastel Status Pills */
    .pill-pastel-mint {
      background: #E8F8F0 !important;
      color: #10B981 !important;
      border: 1px solid #D1FAE5 !important;
      border-radius: 9999px !important;
      font-weight: 700 !important;
    }
    .pill-pastel-cyan {
      background: #E0F7FA !important;
      color: #0097A7 !important;
      border: 1px solid #B2EBF2 !important;
      border-radius: 9999px !important;
      font-weight: 700 !important;
    }
    .pill-pastel-rose {
      background: #FFF1F2 !important;
      color: #F43F5E !important;
      border: 1px solid #FFE4E6 !important;
      border-radius: 9999px !important;
      font-weight: 700 !important;
    }

    
    /* Fluid Hardware Acceleration & 60-120fps Smooth Momentum */
    * {
      -webkit-tap-highlight-color: transparent;
    }
    button, [role="button"], a {
      touch-action: manipulation;
    }
    .msg-card-active, .msg-card-inactive, .nav-pill-active, .nav-pill-inactive {
      transform: translateZ(0);
      will-change: transform, background-color;
      transition: transform 0.16s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.15s ease, box-shadow 0.16s ease !important;
    }
    .msg-card-active:active, .msg-card-inactive:active {
      transform: scale(0.99) translateZ(0) !important;
    }
    #col-messages, #col-reader, #sidebar, #col-inbox {
      -webkit-overflow-scrolling: touch !important;
      scroll-behavior: smooth !important;
      overscroll-behavior: contain;
    }

    /* Collapsed Sidebar Rail Style */
    .sidebar-collapsed {
      width: 68px !important;
      min-width: 68px !important;
      padding-left: 8px !important;
      padding-right: 8px !important;
    }
    .sidebar-collapsed .sidebar-text,
    .sidebar-collapsed .sidebar-section-title,
    .sidebar-collapsed .sidebar-badge {
      display: none !important;
    }
    .sidebar-collapsed .nav-pill-active,
    .sidebar-collapsed .nav-pill-inactive {
      justify-content: center !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

    /* Custom Scrollbars */
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }
    ::-webkit-scrollbar-track {
      background: transparent;
    }
    ::-webkit-scrollbar-thumb {
      background: #E2E8F0;
      border-radius: 9999px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: #CBD5E1;
    }
  
    /* ========================================================================= */
    /* MOBILE BOUNCE SEARCH ANIMATION                                            */
    /* ========================================================================= */
    @keyframes mobileSearchBounceIn {
      0% {
        opacity: 0;
        transform: translateY(-24px) scale(0.92);
      }
      55% {
        opacity: 1;
        transform: translateY(6px) scale(1.02);
      }
      75% {
        transform: translateY(-2px) scale(0.99);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    @keyframes mobileSearchBounceOut {
      0% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
      30% {
        transform: translateY(4px) scale(1.01);
      }
      100% {
        opacity: 0;
        transform: translateY(-24px) scale(0.92);
      }
    }
    .search-bounce-enter {
      display: flex !important;
      animation: mobileSearchBounceIn 0.36s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
    .search-bounce-exit {
      display: flex !important;
      animation: mobileSearchBounceOut 0.22s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
    .pb-safe {
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }
  </style>
</head>
<body class="flex flex-col h-screen w-screen overflow-hidden bg-white text-slate-900 select-none">

  <!-- ========================================================================= -->
  <!-- PROTONMAIL-STYLE TOP TOGGLEBAR (WHITE BG, SUBTLE BORDER, GRAY WRITE, BLACK BUTTONS) -->
  <!-- ========================================================================= -->
  <header id="proton-togglebar" class="hidden md:flex h-14 border-b border-slate-200/90 bg-white items-center justify-between px-3 sm:px-6 shrink-0 z-30 w-full select-none" style="background-color: #FFFFFF !important; border-bottom: 1px solid #E2E8F0 !important;">
    
    <!-- Left Section: App Switcher + Logo + Brand + Sidebar Toggle -->
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <!-- App Switcher (Proton Waffle 9-dots) -->
      <button onclick="toggleAppSwitcher()" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="Raccon Mail Apps">
        <i data-lucide="layout-grid" class="h-4 w-4"></i>
      </button>

      <!-- Brand Mascot Logo & Name: Clicking refreshes the mail without logging out -->
      <a href="/inbox" onclick="window.location.reload(); return false;" class="flex items-center gap-2 group cursor-pointer mr-1" title="Raccon Mail (Click to refresh)">
        <img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-7 w-7 object-contain group-hover:scale-105 transition" />
        <span class="font-bold text-base tracking-tight hidden sm:inline" style="color: #64748B !important;">Raccon Mail</span>
      </a>

      <!-- ProtonMail Sidebar Toggle Button -->
      <button
        type="button"
        id="btn-toggle-sidebar"
        onclick="toggleSidebarAction()"
        class="p-2 sm:p-2 rounded-xl border border-slate-200/90 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 transition cursor-pointer flex items-center justify-center shadow-2xs"
        title="Toggle Folders & Menu"
      >
        <i data-lucide="menu" class="h-4 w-4 md:hidden text-slate-700"></i>
        <i data-lucide="panel-left" class="h-4 w-4 hidden md:inline text-slate-700"></i>
      </button>
    </div>

    <!-- Center Section: Proton-style Global Search Bar -->
    <!-- Center Section: Proton-style Global Search Bar (Hidden on mobile & APK, accessed via bottom-left search button) -->
    <div class="hidden md:block flex-1 max-w-xl mx-6">
      <div class="relative w-full flex items-center">
        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-cyan-500 stroke-[2.5] pointer-events-none filter drop-shadow-[0_1px_2px_rgba(6,182,212,0.3)]"></i>
        <input
          id="global-search"
          type="text"
          placeholder="Search all emails..."
          oninput="handleSearch(this.value)"
          class="w-full bg-slate-50 hover:bg-white focus:bg-white rounded-full border border-slate-200/90 pl-10 pr-10 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition shadow-2xs"
        />
        <button onclick="clearSearch()" id="search-clear-btn" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
          <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
      </div>
    </div>

    <!-- Right Section: Action Icons + Proton User Account Dropdown -->
    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
      <!-- Refresh Button -->
      <button onclick="refreshMailbox()" id="btn-top-refresh" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="Refresh messages">
        <i data-lucide="rotate-cw" class="h-4 w-4"></i>
      </button>

      <!-- Help Button -->
      <button onclick="window.location.href='/forum'" class="hidden sm:flex p-2 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="Help & Forum">
        <i data-lucide="help-circle" class="h-4 w-4"></i>
      </button>

      <!-- Settings Button -->
      <button onclick="openSettingsModal()" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="Settings">
        <i data-lucide="settings" class="h-4 w-4"></i>
      </button>

      <!-- Proton User Account Pill -->
      <div class="relative ml-1">
        <button onclick="toggleUserDropdown(event)" id="user-profile-btn" class="flex items-center gap-2 p-1 sm:px-2.5 sm:py-1 rounded-full border border-slate-200/90 hover:bg-slate-50 transition cursor-pointer select-none shadow-2xs group" title="Account & Profile">
          <img id="header-user-avatar" src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="user-avatar-img h-6 w-6 rounded-full object-cover shrink-0 border border-slate-200 group-hover:ring-1 group-hover:ring-[#F68D72] transition" />
          <span id="header-user-email" class="text-xs font-medium text-slate-700 hidden lg:inline max-w-[130px] truncate"><?php echo htmlspecialchars($currentUser['email']); ?></span>
          <i data-lucide="chevron-down" class="h-3 w-3 text-slate-400"></i>
        </button>

        <!-- Account Popover Dropdown -->
        <div id="account-dropdown" class="hidden absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-1.5rem)] bg-white rounded-2xl border border-slate-200 shadow-2xl p-2.5 z-50 text-slate-900 space-y-1">
          <!-- User Profile Hero inside Dropdown -->
          <div class="p-3 border-b border-slate-100 bg-linear-to-b from-slate-50/70 to-white rounded-xl mb-1">
            <div class="flex items-center gap-3">
              <div class="relative group cursor-pointer shrink-0" onclick="openAvatarModal(); toggleUserDropdown();" title="Click to change profile photo">
                <img id="dropdown-user-avatar" src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="user-avatar-img h-12 w-12 rounded-full object-cover ring-2 ring-slate-200 group-hover:ring-[#F68D72] shadow-xs transition" />
                <div class="absolute inset-0 bg-black/45 rounded-full opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white backdrop-blur-2xs">
                  <i data-lucide="camera" class="h-4 w-4"></i>
                </div>
                <div class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-[#FFEFEA] border-2 border-white text-[#E06748] flex items-center justify-center shadow-2xs group-hover:bg-[#E06748] group-hover:text-white transition" title="Change Photo">
                  <i data-lucide="camera" class="h-2.5 w-2.5"></i>
                </div>
              </div>
              <div class="min-w-0 flex-1">
                <p id="dropdown-user-name" class="font-bold text-xs text-slate-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                <p id="dropdown-user-email" class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                <div class="mt-1 flex items-center gap-1.5 text-[10px] font-semibold text-emerald-600">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  <span class="truncate">Active Account</span>
                </div>
              </div>
            </div>
            <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400">
              <span>Storage: <strong>12.5 MB / 1 GB</strong></span>
              <span class="text-emerald-500 font-bold">1%</span>
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1 overflow-hidden">
              <div class="bg-slate-900 h-full w-[2%] rounded-full"></div>
            </div>
          </div>

          <!-- Dropdown Navigation Items -->
          <button onclick="openAvatarModal(); toggleUserDropdown()" class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 hover:bg-orange-50/70 hover:text-[#E06748] transition cursor-pointer text-left group">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-7 h-7 rounded-lg bg-[#FFEFEA] text-[#E06748] flex items-center justify-center shrink-0 group-hover:scale-105 transition">
                <i data-lucide="camera" class="h-3.5 w-3.5"></i>
              </div>
              <span class="truncate">Change Profile Photo</span>
            </div>
            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-[#FFEFEA] text-[#E06748] shrink-0 ml-2">New</span>
          </button>
          <button onclick="openSettingsModal(); toggleUserDropdown()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-slate-700 hover:bg-slate-50 transition cursor-pointer text-left">
            <i data-lucide="user" class="h-4 w-4 text-slate-400"></i>
            <span>Profile & Settings</span>
          </button>
          <button onclick="openContactsModal(); toggleUserDropdown()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-slate-700 hover:bg-slate-50 transition cursor-pointer text-left">
            <i data-lucide="contact" class="h-4 w-4 text-slate-400"></i>
            <span>Address Book</span>
          </button>
          <button onclick="window.location.href='/forum'" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-slate-700 hover:bg-slate-50 transition cursor-pointer text-left">
            <i data-lucide="message-square" class="h-4 w-4 text-slate-400"></i>
            <span>Raccon Mail Community</span>
          </button>
          <button onclick="logoutNow()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-rose-600 hover:bg-rose-50 transition cursor-pointer text-left font-medium">
            <i data-lucide="log-out" class="h-4 w-4 text-rose-500"></i>
            <span>Sign Out</span>
          </button>
        </div>
      </div>

    </div>

  </header>

  <!-- Mobile Sidebar Backdrop Overlay -->
  <div id="mobile-sidebar-backdrop" onclick="toggleSidebarAction()" class="hidden md:hidden fixed inset-0 bg-slate-900/40 backdrop-blur-2xs z-40 transition-opacity"></div>

  <!-- ========================================================================= -->
  <!-- MAIN 3-COLUMN WORKSPACE (EDGE-TO-EDGE)                                    -->
  <!-- ========================================================================= -->
  <div class="flex-1 flex overflow-hidden min-h-0 relative bg-white w-full">

    <!-- ========================================================================= -->
    <!-- COLUMN 1: SIDEBAR NAVIGATION WITH ALL MAIL FOLDERS                        -->
    <!-- ========================================================================= -->
    
    <!-- ========================================================================= -->
    <!-- DESKTOP APP RAIL (COLUMN 1: RISE SAAS STYLE APP SWITCHER)                 -->
    <!-- ========================================================================= -->
    <aside id="desktop-app-rail" class="hidden md:flex flex-col w-16 bg-white border-r border-slate-200/80 shrink-0 items-center py-3 select-none text-slate-400 z-30 justify-between h-full overflow-y-auto overflow-x-hidden scrollbar-none">
      <!-- Top Apps -->
      <div class="flex flex-col items-center gap-2.5 w-full">
        <!-- 1. Dashboard -->
        <a href="/inbox" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Dashboard">
          <i data-lucide="layout-dashboard" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </a>

        <!-- 2. Mailbox (Active) -->
        <a href="/inbox" class="w-10 h-10 flex items-center justify-center text-sky-600 hover:text-sky-700 transition cursor-pointer" title="Mailbox">
          <i data-lucide="mail" class="w-5 h-5"></i>
        </a>

        <!-- 3. Docs -->
        <button type="button" onclick="openProductivityDrawer('notes')" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Docs & Notes">
          <i data-lucide="file-text" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </button>

        <!-- 4. Contacts -->
        <button type="button" onclick="openContactsModal()" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Contacts">
          <i data-lucide="users" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </button>

        <!-- 5. Forum -->
        <a href="/forum" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Raccon Forum">
          <i data-lucide="messages-square" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </a>

        <!-- 6. Stories -->
        <a href="/stories" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Raccon Stories">
          <i data-lucide="book-open" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </a>

        <!-- 7. Tasks -->
        <button type="button" onclick="openProductivityDrawer('tasks')" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Tasks">
          <i data-lucide="check-square" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </button>

        <!-- 8. News -->
        <a href="/news" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="News Portal">
          <i data-lucide="newspaper" class="w-5 h-5 group-hover:scale-110 transition"></i>
        </a>
      </div>

      <!-- Bottom: Settings -->
      <div class="flex flex-col items-center gap-2 w-full pt-2 border-t border-slate-200/80">
        <button type="button" onclick="openSettingsModal()" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-700 transition cursor-pointer group" title="Settings">
          <i data-lucide="settings" class="w-5 h-5 group-hover:rotate-45 transition"></i>
        </button>
      </div>
    </aside>

<aside id="sidebar-panel" class="w-72 max-w-[85vw] border-r border-slate-200 flex flex-col shrink-0 bg-white h-full p-3 overflow-y-auto select-none fixed inset-y-0 left-0 z-50 md:relative md:w-60 md:translate-x-0 transition-transform duration-200 -translate-x-full md:flex shadow-2xl md:shadow-none">
      
      <!-- Mobile Sidebar Close Header -->
      <div class="flex items-center justify-between pb-3 pt-1 border-b border-slate-100 md:hidden mb-2">
        <div class="flex items-center gap-2">
          <img src="/raccoon-logo.svg" alt="Raccon" class="h-6 w-6 object-contain" />
          <span class="font-extrabold text-xs text-slate-900 tracking-tight">Mailbox Folders</span>
        </div>
        <button onclick="toggleSidebarAction()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer" title="Close">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      
      <!-- Secondary Rail Header: All Emails Dropdown Selector -->
      <div class="relative pb-2 select-none">
        <button type="button" onclick="toggleAllEmailsDropdown(event)" class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-bold text-slate-800 hover:bg-slate-100 transition cursor-pointer border border-slate-200/60 shadow-2xs">
          <div class="flex items-center gap-2">
            <i data-lucide="inbox" class="w-3.5 h-3.5 text-sky-600"></i>
            <span id="all-emails-dropdown-label">All emails</span>
          </div>
          <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
        </button>
        <!-- Dropdown menu -->
        <div id="all-emails-dropdown-menu" class="hidden absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-slate-200 shadow-xl py-1 z-50 text-xs">
          <button type="button" onclick="switchFolder('Inbox'); closeAllEmailsDropdown();" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700 flex items-center justify-between">
            <span>Inbox</span>
            <span class="text-[10px] text-slate-400"><?php echo $counts['inbox_total']; ?></span>
          </button>
          <button type="button" onclick="switchFolder('All'); closeAllEmailsDropdown();" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700">
            All Mail
          </button>
          <button type="button" onclick="switchFolder('Sent'); closeAllEmailsDropdown();" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700">
            Sent
          </button>
          <button type="button" onclick="switchFolder('Starred'); closeAllEmailsDropdown();" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700">
            Starred
          </button>
          <button type="button" onclick="switchFolder('Trash'); closeAllEmailsDropdown();" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-semibold text-slate-700">
            Trash
          </button>
        </div>
      </div>

<!-- Primary New Message Black Capsule Button at Top of Sidebar -->
      <div class="pb-3 pt-1">
        <button onclick="openComposeCard()" class="w-full flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-black text-white py-2.5 px-4 text-xs font-bold shadow-xs transition active:scale-95 cursor-pointer">
          <i data-lucide="plus" class="h-4 w-4 text-white"></i>
          <span class="sidebar-text">New message</span>
        </button>
      </div>

      <!-- Section: MAILBOX (ALL ESSENTIAL FOLDERS) -->
      <div class="sidebar-section-title text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-1 mt-2">Mailbox</div>
      <div class="space-y-0.5">
        
        <!-- 1. INBOX (ACTIVE BY DEFAULT) -->
        <button onclick="switchFolder('Inbox')" id="folder-btn-inbox" class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold nav-pill-active cursor-pointer">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="inbox" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Inbox</span>
          </div>
          <span id="badge-inbox" class="sidebar-badge bg-slate-100 text-slate-700 border border-slate-200/90 text-[10px] font-bold px-2.5 py-0.5 rounded-full <?php echo ($counts['inbox_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_total']; ?></span>
        </button>

        <!-- 2. UNREAD -->
        <button onclick="switchFolder('Unread')" id="folder-btn-unread" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="mail" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Unread</span>
          </div>
          <span id="badge-unread" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['unread_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['unread_total']; ?></span>
        </button>

        <!-- 3. STARRED / FAVORITES -->
        <button onclick="switchFolder('Starred')" id="folder-btn-starred" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="star" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Starred</span>
          </div>
          <span id="badge-starred" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['starred_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['starred_total']; ?></span>
        </button>

        <!-- 4. DRAFTS -->
        <button onclick="switchFolder('Drafts')" id="folder-btn-drafts" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="file-edit" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Drafts</span>
          </div>
          <span id="badge-drafts" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['drafts_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['drafts_total']; ?></span>
        </button>

        <!-- 5. SENT -->
        <button onclick="switchFolder('Sent')" id="folder-btn-sent" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="send" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Sent</span>
          </div>
          <span id="badge-sent" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['sent_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['sent_total']; ?></span>
        </button>

        <!-- 6. ARCHIVE -->
        <button onclick="switchFolder('Archive')" id="folder-btn-archive" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="archive" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Archive</span>
          </div>
          <span id="badge-archive" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['archive_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['archive_total']; ?></span>
        </button>

        <!-- 7. SPAM -->
        <button onclick="switchFolder('Spam')" id="folder-btn-spam" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="shield-alert" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Spam</span>
          </div>
          <span id="badge-spam" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['spam_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['spam_total']; ?></span>
        </button>

        <!-- 8. TRASH (100% Identical Structure and Column Alignment) -->
        <button onclick="switchFolder('Trash')" id="folder-btn-trash" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="trash-2" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Trash</span>
          </div>
          <span id="badge-trash" class="sidebar-badge text-xs font-mono text-slate-400 <?php echo ($counts['trash_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['trash_total']; ?></span>
        </button>

        <!-- 9. ALL MAIL -->
        <button onclick="switchFolder('All')" id="folder-btn-all" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="folder" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">All Mail</span>
          </div>
        </button>
      </div>

      <!-- Section: CATEGORIES -->
      <div class="flex items-center justify-between px-3 mb-1 mt-5">
        <div class="sidebar-section-title text-[11px] font-bold text-slate-400 uppercase tracking-wider">Categories</div>
        <button onclick="openNewCategoryModal()" title="Add Category" class="text-slate-400 hover:text-slate-700 transition cursor-pointer">
          <i data-lucide="plus" class="h-3.5 w-3.5"></i>
        </button>
      </div>
      <div class="space-y-0.5">
        <button onclick="filterCategory('personal')" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <span class="w-3.5 h-3.5 rounded-md border-2 border-teal-400 shrink-0"></span>
          <span class="sidebar-text truncate">Personal</span>
        </button>
        <button onclick="filterCategory('work')" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <span class="w-3.5 h-3.5 rounded-md border-2 border-purple-400 shrink-0"></span>
          <span class="sidebar-text truncate">Work</span>
        </button>
        <button onclick="filterCategory('entertainment')" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <span class="w-3.5 h-3.5 rounded-md border-2 border-sky-400 shrink-0"></span>
          <span class="sidebar-text truncate">Entertainment</span>
        </button>
      </div>

      <!-- Section: PRODUCTIVITY & SUITE (High-Value Modern Sections) -->
      <div class="sidebar-section-title text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-1 mt-5">Productivity</div>
      <div class="space-y-1">
        
        <!-- 1. Calendar -->
        <button onclick="openProductivityDrawer('calendar')" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="flex items-center gap-2.5 truncate">
            <div class="w-6 h-6 rounded-lg bg-cyan-50 border border-cyan-200/80 flex items-center justify-center text-cyan-600 group-hover:bg-cyan-100 transition shrink-0">
              <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
            </div>
            <span class="sidebar-text truncate">Calendar</span>
          </div>
          <span class="sidebar-badge text-[10px] font-bold bg-cyan-100/80 text-cyan-800 px-2 py-0.5 rounded-full">3 Today</span>
        </button>

        <!-- 2. Notes Section -->
        <button onclick="openProductivityDrawer('notes')" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="flex items-center gap-2.5 truncate">
            <div class="w-6 h-6 rounded-lg bg-amber-50 border border-amber-200/80 flex items-center justify-center text-amber-600 group-hover:bg-amber-100 transition shrink-0">
              <i data-lucide="sticky-note" class="h-3.5 w-3.5"></i>
            </div>
            <span class="sidebar-text truncate">Keep Notes</span>
          </div>
          <span class="sidebar-badge text-[10px] font-mono text-slate-400">4</span>
        </button>

        <!-- 3. Tasks / To-Do -->
        <button onclick="openProductivityDrawer('tasks')" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="flex items-center gap-2.5 truncate">
            <div class="w-6 h-6 rounded-lg bg-emerald-50 border border-emerald-200/80 flex items-center justify-center text-emerald-600 group-hover:bg-emerald-100 transition shrink-0">
              <i data-lucide="check-square" class="h-3.5 w-3.5"></i>
            </div>
            <span class="sidebar-text truncate">Tasks / To-Do</span>
          </div>
          <span id="sidebar-tasks-count" class="sidebar-badge text-[10px] font-bold bg-emerald-100/80 text-emerald-800 px-2 py-0.5 rounded-full">2</span>
        </button>

        <!-- 4. Attachments & Media Dashboard -->
        <button onclick="openProductivityDrawer('attachments')" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-purple-50 border border-purple-200/80 flex items-center justify-center text-purple-600 group-hover:bg-purple-100 transition shrink-0">
            <i data-lucide="paperclip" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Files & Gallery</span>
        </button>

        <!-- 5. RSS / Feed Reader -->
        <button onclick="openProductivityDrawer('rss')" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-rose-50 border border-rose-200/80 flex items-center justify-center text-rose-600 group-hover:bg-rose-100 transition shrink-0">
            <i data-lucide="rss" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Feed Reader</span>
        </button>

        <!-- 6. Templates & Canned Responses -->
        <button onclick="openProductivityDrawer('templates')" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-indigo-50 border border-indigo-200/80 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-100 transition shrink-0">
            <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Templates</span>
        </button>

      </div>

      <!-- Section: ECOSYSTEM (Distinct Colors & Zero Shadow) -->
      <div class="sidebar-section-title text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-1 mt-5">Ecosystem</div>
      <div class="space-y-1">
        <button onclick="openStoriesModal()" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] flex items-center justify-center transition shrink-0 shadow-none">
            <i data-lucide="book-open" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">World Stories</span>
        </button>
        <button onclick="window.location.href='/news'" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0] flex items-center justify-center transition shrink-0 shadow-none">
            <i data-lucide="newspaper" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">News Wire</span>
        </button>
        <button onclick="window.location.href='/forum'" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-[#EFF4FF] text-[#3B66E0] border border-[#D5E2FF] flex items-center justify-center transition shrink-0 shadow-none">
            <i data-lucide="message-square" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Forum</span>
        </button>
        <button onclick="openContactsModal()" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-[#F5F3FF] text-[#7C3AED] border border-[#DDD6FE] flex items-center justify-center transition shrink-0 shadow-none">
            <i data-lucide="users" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Address Book</span>
        </button>
      </div>

    </aside>

    <!-- ========================================================================= -->
    <!-- COLUMN 2: MIDDLE INBOX LIST PANEL (~360px)                                -->
    <!-- ========================================================================= -->
    <section id="col-inbox" class="w-full md:w-[370px] lg:w-[410px] xl:w-[450px] border-r border-slate-200/80 flex flex-col shrink-0 bg-white h-full overflow-hidden select-none">
      
      <!-- ========================================================================= -->
      <!-- MOBILE TOP HEADER (MATCHING NATIVE ANDROID REFERENCE APP SCREENSHOT)      -->
      <!-- ========================================================================= -->
      <div id="mobile-top-bar" class="md:hidden bg-white border-b border-slate-100 px-4 py-3 shrink-0 z-30 relative select-none">
        <div class="flex items-center justify-between">
          
          <!-- Left: Hamburger Menu (3 lines stacked) + Folder Title + Synced Subtitle -->
          <div class="flex items-center gap-3 min-w-0">
            <button type="button" onclick="toggleSidebarAction()" class="p-1.5 -ml-1.5 text-slate-800 hover:text-slate-900 rounded-xl hover:bg-slate-100 transition cursor-pointer" title="Open Folders">
              <i data-lucide="menu" class="w-6 h-6 stroke-[2.2] text-slate-800"></i>
            </button>
            <div class="min-w-0">
              <h1 id="mobile-top-folder-title" class="text-xl font-bold text-slate-900 leading-tight tracking-tight truncate">Inbox</h1>
              <p id="mobile-top-sync-subtitle" class="text-[11px] text-slate-500 font-medium leading-tight mt-0.5">Synced: 10/25/26 16:44</p>
            </div>
          </div>

          <!-- Right: Sliders (Tune/Filter) + Search (Bounce) + User Avatar Initial Circle -->
          <div class="flex items-center gap-1 shrink-0">
            
            <!-- Sliders / Filter Tune Icon -->
            <div class="relative">
              <button type="button" onclick="toggleMobileFilterMenu(event)" class="p-2 text-slate-700 hover:text-slate-900 rounded-full hover:bg-slate-100 transition cursor-pointer" title="Filter emails">
                <i data-lucide="sliders-horizontal" class="w-5 h-5 text-slate-700"></i>
              </button>
              
              <!-- Filter Dropdown Popover -->
              <div id="mobile-filter-popover" class="hidden absolute right-0 top-full mt-2 w-48 bg-white rounded-2xl border border-slate-200 shadow-xl p-2 z-50 text-slate-800 space-y-1">
                <button type="button" onclick="applyEmailFilter('all')" class="w-full text-left px-3 py-2 text-xs font-semibold rounded-xl hover:bg-slate-50 transition flex items-center justify-between cursor-pointer">
                  <span>All Messages</span>
                  <i id="filter-check-all" data-lucide="check" class="w-3.5 h-3.5 text-sky-600"></i>
                </button>
                <button type="button" onclick="applyEmailFilter('unread')" class="w-full text-left px-3 py-2 text-xs font-semibold rounded-xl hover:bg-slate-50 transition flex items-center justify-between cursor-pointer">
                  <span>Unread Only</span>
                  <i id="filter-check-unread" data-lucide="check" class="hidden w-3.5 h-3.5 text-sky-600"></i>
                </button>
                <button type="button" onclick="applyEmailFilter('starred')" class="w-full text-left px-3 py-2 text-xs font-semibold rounded-xl hover:bg-slate-50 transition flex items-center justify-between cursor-pointer">
                  <span>Starred Only</span>
                  <i id="filter-check-starred" data-lucide="check" class="hidden w-3.5 h-3.5 text-sky-600"></i>
                </button>
                <button type="button" onclick="applyEmailFilter('attachments')" class="w-full text-left px-3 py-2 text-xs font-semibold rounded-xl hover:bg-slate-50 transition flex items-center justify-between cursor-pointer">
                  <span>With Attachments</span>
                  <i id="filter-check-attachments" data-lucide="check" class="hidden w-3.5 h-3.5 text-sky-600"></i>
                </button>
                <div class="border-t border-slate-100 my-1"></div>
                <button onclick="markAllAsReadAction(); closeMobileFilterPopover();" class="w-full text-left px-3 py-2 text-xs font-semibold rounded-xl text-sky-600 hover:bg-sky-50 transition cursor-pointer">
                  Mark all as read
                </button>
              </div>
            </div>

            <!-- Search Icon (Triggers Bouncing Search Bar) -->
            <button type="button" onclick="toggleMobileBounceSearch(true)" id="mobile-search-toggle-btn" class="p-2 text-slate-700 hover:text-slate-900 rounded-full hover:bg-slate-100 transition cursor-pointer" title="Search emails">
              <i data-lucide="search" class="w-5 h-5 text-slate-700"></i>
            </button>

            <!-- User Avatar Circle (Opens Full Profile Page on tap) -->
            <button type="button" onclick="openMobileProfilePage()" class="relative ml-1 cursor-pointer flex items-center justify-center group" title="Account & Profile">
              <div class="w-9 h-9 rounded-full ring-2 ring-slate-200 group-hover:ring-slate-400 overflow-hidden shadow-xs relative flex items-center justify-center bg-slate-100">
                <?php if (!empty($currentUser['avatar'])): ?>
                  <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="user-avatar-img w-full h-full object-cover" alt="Profile" onerror="this.style.display='none'; const fb = document.getElementById('mobile-header-initial-fallback'); if(fb) fb.style.display='flex';" />
                  <span id="mobile-header-initial-fallback" style="display:none;" class="w-full h-full bg-slate-700 text-white font-bold text-xs items-center justify-center uppercase">
                    <?php echo strtoupper(substr($currentUser['name'] ?? $currentUser['email'] ?? 'U', 0, 1)); ?>
                  </span>
                <?php else: ?>
                  <span class="w-full h-full bg-slate-700 text-white font-bold text-xs flex items-center justify-center uppercase">
                    <?php echo strtoupper(substr($currentUser['name'] ?? $currentUser['email'] ?? 'U', 0, 1)); ?>
                  </span>
                <?php endif; ?>
              </div>
            </button>

          </div>
        </div>

        <!-- BOUNCING SEARCH BAR OVERLAY (Opens with bounce animation when search is clicked) -->
        <div id="mobile-bounce-search-bar" class="hidden absolute inset-0 bg-white px-3 flex items-center gap-2 z-40">
          <button type="button" onclick="toggleMobileBounceSearch(false)" class="p-2 text-slate-600 hover:text-slate-900 rounded-full hover:bg-slate-100 transition cursor-pointer" title="Back">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
          </button>
          <div class="flex-1 relative flex items-center">
            <input
              id="mobile-bounce-search-input"
              type="text"
              placeholder="Search in emails..."
              oninput="handleMobileBounceSearch(this.value)"
              class="w-full bg-slate-100 focus:bg-white rounded-full pl-4 pr-9 py-2 text-sm text-slate-800 placeholder-slate-400 border border-transparent focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition outline-none"
            />
            <button type="button" onclick="clearMobileBounceSearch()" id="mobile-bounce-search-clear" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
              <i data-lucide="x" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

      </div>
      
      <!-- Middle Column Desktop Header (No Search Bar, Zero Overlap, Clean 2-Column Layout) -->
      <div class="hidden md:flex items-center justify-between px-4 h-14 border-b border-slate-200/80 bg-white shrink-0 select-none">
        <!-- Left: Checkbox dropdown + Filter tabs: All, Read, Unread -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Multi-select Checkbox with Dropdown Caret -->
          <div class="relative inline-flex items-center">
            <input type="checkbox" id="master-select-checkbox" onchange="toggleMasterSelect(this)" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-4 h-4" title="Select All" />
            <button type="button" onclick="toggleSelectDropdown(event)" class="p-1 -ml-0.5 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="Selection options">
              <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
            </button>
            <!-- Dropdown Menu -->
            <div id="select-dropdown-menu" class="hidden absolute left-0 top-full mt-1.5 w-36 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50 text-xs font-semibold">
              <button type="button" onclick="selectFilteredBatch('all')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">All</button>
              <button type="button" onclick="selectFilteredBatch('none')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">None</button>
              <button type="button" onclick="selectFilteredBatch('read')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">Read</button>
              <button type="button" onclick="selectFilteredBatch('unread')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">Unread</button>
              <button type="button" onclick="selectFilteredBatch('starred')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">Starred</button>
            </div>
          </div>

          <!-- Filter Tabs: All, Read, Unread -->
          <div class="flex items-center gap-1 bg-slate-100/90 p-0.5 rounded-lg border border-slate-200/80 ml-1 shrink-0 whitespace-nowrap">
            <button type="button" id="d-filter-all" onclick="applyEmailFilter('all')" class="px-2.5 py-1 rounded-md text-xs font-bold bg-white text-slate-900 shadow-2xs transition cursor-pointer">
              All
            </button>
            <button type="button" id="d-filter-read" onclick="applyEmailFilter('read')" class="px-2.5 py-1 rounded-md text-xs font-semibold text-slate-600 hover:text-slate-900 transition cursor-pointer">
              Read
            </button>
            <button type="button" id="d-filter-unread" onclick="applyEmailFilter('unread')" class="px-2.5 py-1 rounded-md text-xs font-semibold text-slate-600 hover:text-slate-900 transition cursor-pointer">
              Unread
            </button>
          </div>
        </div>

        <!-- Right: Refresh & Filter/Sliders (No search bar, plenty of spacing) -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Refresh Button -->
          <button type="button" onclick="refreshEmailListAction()" id="list-refresh-btn" class="p-1.5 rounded-lg border border-slate-200/80 hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="Refresh messages">
            <i data-lucide="rotate-cw" id="list-refresh-icon" class="w-3.5 h-3.5"></i>
          </button>

          <!-- Filter / Sorting Button -->
          <div class="relative">
            <button type="button" onclick="toggleDesktopFilterMenu(event)" class="p-1.5 rounded-lg border border-slate-200/80 hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="Filters & Sorting">
              <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
            </button>
            <div id="desktop-filter-menu" class="hidden absolute right-0 top-full mt-1.5 w-44 bg-white rounded-xl shadow-lg border border-slate-200 py-1.5 z-50 text-xs font-semibold">
              <button type="button" onclick="applyEmailFilter('starred')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700 flex items-center justify-between">
                <span>Starred only</span>
                <i data-lucide="star" class="w-3.5 h-3.5 text-amber-500"></i>
              </button>
              <button type="button" onclick="applyEmailFilter('attachments')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700 flex items-center justify-between">
                <span>With attachments</span>
                <i data-lucide="paperclip" class="w-3.5 h-3.5 text-slate-400"></i>
              </button>
              <div class="border-t border-slate-100 my-1"></div>
              <button type="button" onclick="exportEmailsToCSV()" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700 flex items-center justify-between">
                <span>Export to Excel</span>
                <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5 text-emerald-600"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Messages Thread List Container -->
      <div id="threads-container" class="flex-1 overflow-y-auto px-4 py-1 space-y-1.5 pb-24 md:pb-6">
        <!-- Rendered dynamically by renderMessageList() -->
      </div>

    </section>

    <!-- ========================================================================= -->
    <!-- COLUMN 3: RIGHT ALL EMAILS & CONVERSATION READING PANE                    -->
    <!-- ========================================================================= -->
    <main id="col-reader" class="hidden md:flex flex-1 flex-col bg-white h-full overflow-hidden relative">
      
      <!-- Right Top Bar: Pagination Navigation + Right Action Tools (Matching Blueprint) -->
      <header class="h-14 sm:h-16 px-4 sm:px-6 border-b border-slate-200/80 flex items-center justify-between shrink-0 bg-white select-none z-20 gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
          <button onclick="backToMobileInbox()" class="md:hidden p-1.5 rounded-lg border border-slate-200 text-slate-600 shrink-0 hover:bg-slate-50 transition" title="Back to Inbox">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
          </button>
          
          <!-- Pagination Navigation: < 1 of 5 > -->
          <div class="flex items-center gap-1.5 text-slate-600 text-xs font-semibold">
            <button onclick="navigateThread(-1)" class="p-1 rounded-lg border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="Previous email">
              <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <span id="reader-counter" class="text-xs font-semibold text-slate-600 px-1.5 min-w-[55px] text-center">1 of 1</span>
            <button onclick="navigateThread(1)" class="p-1 rounded-lg border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="Next email">
              <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Action Tools: Star, Print, Delete, Fullscreen / Popout -->
        <div class="flex items-center gap-1 shrink-0 text-slate-400">
          <button onclick="starCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-amber-500 transition cursor-pointer" title="Star message">
            <i data-lucide="star" id="reader-star-icon" class="h-4 w-4"></i>
          </button>
          <button onclick="printActiveMail()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Print email">
            <i data-lucide="printer" class="h-4 w-4"></i>
          </button>
          <button onclick="trashCurrentThread()" class="p-2 rounded-xl hover:bg-rose-50 hover:text-rose-600 text-rose-500 transition cursor-pointer" title="Delete Email">
            <i data-lucide="trash-2" class="h-4 w-4"></i>
          </button>
          <button onclick="toggleReaderFullscreen()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Fullscreen / Popout">
            <i data-lucide="maximize-2" id="reader-fullscreen-icon" class="h-4 w-4"></i>
          </button>
        </div>
      </header>

      <!-- Thread Conversation View Area -->
      <div id="thread-conversation-container" class="flex-1 overflow-y-auto p-6 md:p-8 space-y-6 pb-36">
        <!-- Rendered dynamically by renderConversationThread() -->
      </div>

    </main>
    <!-- ========================================================================= -->
    <!-- RIGHT QUICK-ACCESS 1-CLICK PRODUCTIVITY RAIL (Gmail / Outlook Style)      -->
    <!-- ========================================================================= -->
    <div id="quick-rail" class="hidden xl:flex flex-col items-center justify-between w-14 border-l border-slate-100 bg-white py-4 shrink-0 select-none z-20">
      <div class="flex flex-col items-center gap-4">
        <button onclick="openProductivityDrawer('calendar')" title="Calendar & Events" class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 transition cursor-pointer active:scale-95">
          <i data-lucide="calendar" class="h-5 w-5"></i>
        </button>
        <button onclick="openProductivityDrawer('notes')" title="Keep Notes" class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition cursor-pointer active:scale-95">
          <i data-lucide="sticky-note" class="h-5 w-5"></i>
        </button>
        <button onclick="openProductivityDrawer('tasks')" title="Tasks & To-Do" class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition cursor-pointer active:scale-95">
          <i data-lucide="check-square" class="h-5 w-5"></i>
        </button>
        <button onclick="openProductivityDrawer('attachments')" title="Attachments & Files Gallery" class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-400 hover:text-purple-600 hover:bg-purple-50 transition cursor-pointer active:scale-95">
          <i data-lucide="paperclip" class="h-5 w-5"></i>
        </button>
        <button onclick="openProductivityDrawer('rss')" title="Newsletter & Feed Reader" class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer active:scale-95">
          <i data-lucide="rss" class="h-5 w-5"></i>
        </button>
        <button onclick="openProductivityDrawer('templates')" title="Canned Response Templates" class="w-10 h-10 rounded-2xl flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition cursor-pointer active:scale-95">
          <i data-lucide="file-text" class="h-5 w-5"></i>
        </button>
      </div>

      
    </div>


  </div>

  <!-- ALL MODALS (SETTINGS, CONTACTS, STORIES, APP SWITCHER) -->
  
  <!-- 1. Settings & Profile Modal -->
  <div id="settings-modal" style="z-index: 99999 !important;" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-[99999] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
        <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
          <i data-lucide="settings" class="h-5 w-5 text-slate-500"></i>
          <span>Raccon Mail Settings</span>
        </h3>
        <button onclick="closeSettingsModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-4 w-4"></i></button>
      </div>
      <div class="space-y-4 text-xs">
        <!-- Profile Avatar Card inside Settings -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div class="relative group cursor-pointer shrink-0" onclick="openAvatarModal(true)" title="Change avatar">
              <img id="settings-user-avatar" src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="user-avatar-img h-12 w-12 rounded-full object-cover ring-2 ring-slate-200 group-hover:ring-[#F68D72] transition" />
              <div class="absolute inset-0 bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white">
                <i data-lucide="camera" class="h-4 w-4"></i>
              </div>
              <div class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-[#FFEFEA] border-2 border-white text-[#E06748] flex items-center justify-center shadow-2xs">
                <i data-lucide="camera" class="h-2.5 w-2.5"></i>
              </div>
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-xs text-slate-900">Profile Photo</p>
              <p class="text-[11px] text-slate-400 truncate">Custom photo, exclusive mascot badges, or dice studio</p>
            </div>
          </div>
          <button type="button" onclick="openAvatarModal(true)" class="shrink-0 w-full sm:w-auto justify-center px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-xs font-bold text-slate-700 transition cursor-pointer flex items-center gap-1.5 shadow-2xs">
            <i data-lucide="camera" class="h-3.5 w-3.5 text-[#F68D72]"></i>
            <span>Change Photo</span>
          </button>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Your Display Name</label>
          <input id="setting-name" type="text" value="<?php echo htmlspecialchars($currentUser['name']); ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2 outline-none focus:border-slate-800" />
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Primary Email Address</label>
          <input type="text" disabled value="<?php echo htmlspecialchars($currentUser['email']); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-500" />
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <span class="text-slate-500">Need to change password or manage storage?</span>
          <a href="/admin" class="font-bold text-slate-900 hover:underline">Admin Console &rarr;</a>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button onclick="closeSettingsModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 font-semibold text-slate-700 cursor-pointer">Close</button>
          <button onclick="saveSettingsAction()" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-black text-white font-semibold cursor-pointer">Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- MODERN PROFILE PHOTO MODAL (#modal-change-avatar)                         -->
  <!-- ========================================================================= -->
  <div id="modal-change-avatar" style="z-index: 99999 !important;" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[99999] flex items-center justify-center p-3 sm:p-4 overflow-y-auto" onclick="if (event.target === this) closeAvatarModal();">
    <div class="bg-white rounded-3xl max-w-lg w-full max-h-[92vh] sm:max-h-[88vh] flex flex-col shadow-2xl border border-slate-200 relative my-auto overflow-hidden">
      
      <!-- Modal Header (Fixed at top) -->
      <div class="px-5 pt-5 pb-3.5 border-b border-slate-100 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-2xl bg-[#FFEFEA] text-[#E06748] flex items-center justify-center shrink-0 shadow-2xs">
            <i data-lucide="camera" class="h-5 w-5"></i>
          </div>
          <div class="min-w-0">
            <h3 class="font-bold text-base text-slate-900 leading-tight truncate">Change Profile Photo</h3>
            <p class="text-[11px] text-slate-400 truncate">Personalize your identity across Raccon Mail & Community</p>
          </div>
        </div>
        <button type="button" onclick="closeAvatarModal()" class="w-8 h-8 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition flex items-center justify-center cursor-pointer shrink-0 ml-2" title="Close">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <!-- Modal Body (Independently Scrollable with smooth scrolling) -->
      <div class="px-5 py-4 overflow-y-auto overscroll-contain flex-1 space-y-4">
        
        <!-- Live Interactive Avatar Preview -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-linear-to-b from-slate-50/90 to-white border border-slate-100 flex items-center gap-3.5 sm:gap-4">
          <div class="relative group shrink-0">
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full ring-4 ring-slate-100 shadow-md overflow-hidden bg-slate-100 relative">
              <img id="avatar-preview-img" src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="w-full h-full object-cover" alt="Avatar Preview" />
              <div id="avatar-preview-loading" class="hidden absolute inset-0 bg-black/50 text-white flex flex-col items-center justify-center text-[10px] font-bold">
                <i data-lucide="loader-2" class="h-5 w-5 animate-spin mb-1"></i>
                <span>Processing...</span>
              </div>
            </div>
            <span class="absolute bottom-0 right-0 h-5 w-5 rounded-full bg-emerald-500 ring-2 ring-white flex items-center justify-center text-white text-[9px] shadow-xs" title="Online Status">
              <i data-lucide="check" class="h-3 w-3 stroke-[3]"></i>
            </span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-orange-50 text-[#E06748] border border-orange-100 text-[10px] font-bold uppercase tracking-wider mb-1">
              <i data-lucide="sparkles" class="h-3 w-3 text-[#F68D72]"></i>
              <span>Live Preview</span>
            </div>
            <p class="font-bold text-sm text-slate-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
            <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
            <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">Synced in real-time across Webmail, Forum discussions, and Stories.</p>
          </div>
        </div>

        <!-- Segmented Tab Navigation (Responsive Grid, Zero Overlap) -->
        <div class="grid grid-cols-4 p-1 bg-slate-100/90 rounded-2xl gap-1 text-[11px] sm:text-xs font-bold text-slate-600 select-none">
          <button type="button" onclick="switchAvatarTab('upload')" id="tab-btn-upload" class="py-2 px-1 sm:px-2 rounded-xl transition flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer bg-white text-slate-900 shadow-2xs truncate">
            <i data-lucide="upload-cloud" class="h-3.5 w-3.5 text-[#F68D72] shrink-0"></i>
            <span class="truncate">Upload</span>
          </button>
          <button type="button" onclick="switchAvatarTab('presets')" id="tab-btn-presets" class="py-2 px-1 sm:px-2 rounded-xl transition flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer text-slate-500 hover:text-slate-900 truncate">
            <i data-lucide="shield" class="h-3.5 w-3.5 shrink-0"></i>
            <span class="truncate">Badges</span>
          </button>
          <button type="button" onclick="switchAvatarTab('dice')" id="tab-btn-dice" class="py-2 px-1 sm:px-2 rounded-xl transition flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer text-slate-500 hover:text-slate-900 truncate">
            <i data-lucide="dices" class="h-3.5 w-3.5 shrink-0"></i>
            <span class="truncate">Dice<span class="hidden sm:inline"> Roll</span></span>
          </button>
          <button type="button" onclick="switchAvatarTab('url')" id="tab-btn-url" class="py-2 px-1 sm:px-2 rounded-xl transition flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer text-slate-500 hover:text-slate-900 truncate">
            <i data-lucide="link-2" class="h-3.5 w-3.5 shrink-0"></i>
            <span class="truncate">Link</span>
          </button>
        </div>

        <!-- TAB 1: UPLOAD PHOTO -->
        <div id="tab-panel-upload" class="space-y-3">
          <input type="file" id="avatar-file-input" accept="image/*" class="hidden" onchange="handleAvatarFileSelect(event)" />
          <label for="avatar-file-input" id="avatar-dropzone" class="block border-2 border-dashed border-slate-200 hover:border-[#F68D72] hover:bg-[#FFEFEA]/20 transition-all rounded-2xl p-5 sm:p-6 text-center cursor-pointer group">
            <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-orange-50 group-hover:bg-[#FFEFEA] text-[#E06748] flex items-center justify-center transition">
              <i data-lucide="image-plus" class="h-6 w-6"></i>
            </div>
            <p class="font-bold text-xs text-slate-800 mb-0.5">Click to choose image or drag &amp; drop</p>
            <p class="text-[11px] text-slate-400">PNG, JPG, WEBP, GIF, SVG &bull; Auto square cropped</p>
            <span class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold shadow-2xs transition active:scale-95 cursor-pointer">
              <i data-lucide="upload" class="w-3.5 h-3.5"></i>
              <span>Choose Photo</span>
            </span>
          </label>
          <div id="upload-status-msg" class="hidden text-xs px-3 py-2 rounded-xl"></div>
        </div>

        <!-- TAB 2: EXCLUSIVE RACCON BADGES & MASCOTS (Responsive 2-col mobile, 4-col desktop) -->
        <div id="tab-panel-presets" class="hidden">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 max-h-60 overflow-y-auto pr-1">
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-default.svg', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-default.svg" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Classic</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-gold-gear.png', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-gold-gear.png" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Gold Gear</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-cyber-mechanic.png', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-cyber-mechanic.png" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Mechanic</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-mascot.png', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-mascot.png" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Mascot</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-portrait.png', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-portrait.png" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Portrait</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-stealth-agent.svg', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-stealth-agent.svg" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Stealth</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-sunset-coral.svg', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-sunset-coral.svg" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Coral Badge</span>
            </button>
            <button type="button" onclick="selectPresetAvatar('/avatars/raccoon-neon-matrix.svg', this)" class="preset-avatar-btn p-2 rounded-2xl border-2 border-slate-200 hover:border-[#F68D72] flex flex-col items-center gap-1.5 transition text-center cursor-pointer group hover:bg-slate-50">
              <img src="/avatars/raccoon-neon-matrix.svg" class="w-12 h-12 rounded-full object-cover group-hover:scale-105 transition" />
              <span class="text-[10px] font-bold text-slate-700 truncate w-full">Neon Operative</span>
            </button>
          </div>
        </div>

        <!-- TAB 3: DICE GENERATOR (Responsive 3-col mobile, 6-col desktop) -->
        <div id="tab-panel-dice" class="hidden space-y-3">
          <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 border border-slate-100 gap-2">
            <div class="min-w-0">
              <p class="font-bold text-xs text-slate-800 truncate">Generative Studio</p>
              <p class="text-[11px] text-slate-400 truncate">Roll to generate unique avatar variations</p>
            </div>
            <button type="button" onclick="rollDiceAvatars()" id="dice-roll-btn" class="shrink-0 px-3 py-2 rounded-xl bg-slate-900 hover:bg-black text-white font-bold text-xs flex items-center gap-1.5 shadow-2xs transition active:scale-95 cursor-pointer">
              <i data-lucide="dices" class="h-3.5 w-3.5 text-[#F68D72] transition-transform duration-300" id="dice-icon-spin"></i>
              <span>Roll Dice</span>
            </button>
          </div>
          <div id="dice-grid" class="grid grid-cols-3 sm:grid-cols-6 gap-2 pt-1">
            <!-- Populated dynamically via JS -->
          </div>
        </div>

        <!-- TAB 4: WEB LINK -->
        <div id="tab-panel-url" class="hidden space-y-3">
          <div>
            <label class="block font-bold text-xs text-slate-700 mb-1">Image URL</label>
            <div class="flex gap-2">
              <input type="url" id="avatar-url-input" placeholder="https://example.com/photo.jpg" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:border-slate-800" />
              <button type="button" onclick="previewCustomUrl()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-bold text-xs text-slate-700 transition cursor-pointer shrink-0">Preview</button>
            </div>
          </div>
          <p class="text-[11px] text-slate-400">Enter a direct link to any public image (HTTPS recommended).</p>
        </div>

      </div>

      <!-- Modal Footer (Fixed at bottom, Fully Responsive) -->
      <div class="px-5 py-3.5 border-t border-slate-100 bg-slate-50/60 shrink-0 flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-2.5">
        <button type="button" onclick="resetAvatarToDefault()" class="w-full sm:w-auto justify-center text-xs font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 px-3 py-2 rounded-xl transition cursor-pointer flex items-center gap-1.5" title="Revert to original mascot">
          <i data-lucide="rotate-ccw" class="h-3.5 w-3.5 shrink-0"></i>
          <span>Reset to Default</span>
        </button>
        <div class="flex items-center gap-2 w-full sm:w-auto">
          <button type="button" onclick="closeAvatarModal()" class="flex-1 sm:flex-none justify-center px-4 py-2.5 sm:py-2 rounded-xl border border-slate-200 hover:bg-slate-100 font-semibold text-xs text-slate-700 cursor-pointer text-center">Cancel</button>
          <button type="button" id="save-avatar-btn" onclick="saveSelectedAvatar()" class="flex-1 sm:flex-none justify-center px-5 py-2.5 sm:py-2 rounded-xl bg-slate-900 hover:bg-black text-white font-bold text-xs shadow-xs transition active:scale-95 cursor-pointer flex items-center gap-2 text-center">
            <i data-lucide="check" class="h-4 w-4 shrink-0"></i>
            <span>Save Photo</span>
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- 2. Contacts / Address Book Modal -->
  <div id="contacts-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
        <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
          <i data-lucide="contact" class="h-5 w-5 text-slate-500"></i>
          <span>Address Book</span>
        </h3>
        <button onclick="closeContactsModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-4 w-4"></i></button>
      </div>
      <div class="space-y-2 max-h-60 overflow-y-auto text-xs divide-y divide-slate-100">
        <div class="py-2 flex items-center justify-between">
          <div><p class="font-bold text-slate-800">List Imin</p><p class="text-slate-400 text-[11px]">listimin@gmail.com</p></div>
          <button onclick="composeTo('listimin@gmail.com'); closeContactsModal();" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600"><i data-lucide="mail" class="h-3.5 w-3.5"></i></button>
        </div>
        <div class="py-2 flex items-center justify-between">
          <div><p class="font-bold text-slate-800">Happy Baswedan</p><p class="text-slate-400 text-[11px]">happy@gmail.com</p></div>
          <button onclick="composeTo('happy@gmail.com'); closeContactsModal();" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600"><i data-lucide="mail" class="h-3.5 w-3.5"></i></button>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. App Switcher Popover (Raccon Mail Ecosystem - Distinct Colors & Zero Shadow) -->
  <div id="app-switcher-modal" class="hidden fixed top-16 left-4 bg-white rounded-2xl shadow-xl border border-slate-200/90 p-4 z-50 w-80">
    <div class="flex items-center justify-between mb-3 px-1">
      <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Raccon Mail Ecosystem</p>
    </div>
    <div class="grid grid-cols-3 gap-2.5 text-center text-xs">
      <a href="/inbox" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-[#FFEFEA] text-[#E06748] border border-[#FCD9CE] flex items-center justify-center transition shadow-none">
          <i data-lucide="mail" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Mail</span>
      </a>
      <a href="/forum" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-[#EFF4FF] text-[#3B66E0] border border-[#D5E2FF] flex items-center justify-center transition shadow-none">
          <i data-lucide="message-square" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Forum</span>
      </a>
      <a href="/news" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0] flex items-center justify-center transition shadow-none">
          <i data-lucide="newspaper" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">News</span>
      </a>
      <a href="/stories" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] flex items-center justify-center transition shadow-none">
          <i data-lucide="book-open" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Stories</span>
      </a>
      <button onclick="openContactsModal(); toggleAppSwitcher();" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group cursor-pointer w-full">
        <div class="w-10 h-10 rounded-xl bg-[#F5F3FF] text-[#7C3AED] border border-[#DDD6FE] flex items-center justify-center transition shadow-none">
          <i data-lucide="users" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Contacts</span>
      </button>
      <a href="/downloads/RacconMail-v2.0.apk" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-[#F0FDFA] text-[#0D9488] border border-[#99F6E4] flex items-center justify-center transition shadow-none">
          <i data-lucide="smartphone" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Android</span>
      </a>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- HIGH-PERFORMANCE CLIENT-SIDE INTERACTIVITY SCRIPT                         -->
  <!-- ========================================================================= -->
  <script>
    // Initialize Lucide Icons immediately
    try { if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons(); } catch (_) {}

    // Central Data Model with real messages & realistic seed
    let currentFolder = 'Inbox';
    let currentFilter = 'all';
    let currentSelectedThreadIndex = 0;
    let isSidebarCollapsed = false;

    // =========================================================================
    // RACCON SOUND SYSTEM
    // Happy Raccoon (1.00s joyful chitter) on Send
    // Afraid Raccoon (0.85s startled alert) on Receive
    // =========================================================================
    const happyRaccoonAudio = new Audio('/sounds/happy-raccoon.wav');
    const afraidRaccoonAudio = new Audio('/sounds/afraid-raccoon.wav');
    happyRaccoonAudio.preload = 'auto';
    afraidRaccoonAudio.preload = 'auto';

    let audioContextInstance = null;
    function getAudioContext() {
      if (!audioContextInstance) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
          audioContextInstance = new AudioContextClass();
        }
      }
      if (audioContextInstance && audioContextInstance.state === 'suspended') {
        audioContextInstance.resume().catch(() => {});
      }
      return audioContextInstance;
    }

    // Web Audio Synthesizer: 1-Second Happy Raccoon Joyful Chittering
    function synthHappyRaccoon() {
      try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;

        // 4 rapid happy chitter pulses (duration: exactly 1.0s)
        const pulses = [
          { start: 0.02, end: 0.22, f1: 1150, f2: 1550, vib: 28, vibD: 70 },
          { start: 0.26, end: 0.46, f1: 1350, f2: 1850, vib: 32, vibD: 90 },
          { start: 0.50, end: 0.72, f1: 1450, f2: 1980, vib: 35, vibD: 110 },
          { start: 0.76, end: 0.98, f1: 1300, f2: 1700, vib: 26, vibD: 60 }
        ];

        pulses.forEach(p => {
          const osc1 = ctx.createOscillator();
          const osc2 = ctx.createOscillator();
          const gain = ctx.createGain();
          const lfo = ctx.createOscillator();
          const lfoGain = ctx.createGain();

          osc1.type = 'triangle';
          osc2.type = 'sine';
          lfo.type = 'sine';

          const pStart = now + p.start;
          const pEnd = now + p.end;
          const pDur = p.end - p.start;

          osc1.frequency.setValueAtTime(p.f1, pStart);
          osc1.frequency.exponentialRampToValueAtTime(p.f2, pEnd);

          osc2.frequency.setValueAtTime(p.f1 * 2, pStart);
          osc2.frequency.exponentialRampToValueAtTime(p.f2 * 2, pEnd);

          lfo.frequency.setValueAtTime(p.vib, pStart);
          lfoGain.gain.setValueAtTime(p.vibD, pStart);
          lfo.connect(osc1.frequency);
          lfo.connect(osc2.frequency);

          gain.gain.setValueAtTime(0.0001, pStart);
          gain.gain.exponentialRampToValueAtTime(0.28, pStart + pDur * 0.35);
          gain.gain.exponentialRampToValueAtTime(0.0001, pEnd);

          osc1.connect(gain);
          osc2.connect(gain);
          gain.connect(ctx.destination);

          osc1.start(pStart);
          osc2.start(pStart);
          lfo.start(pStart);
          osc1.stop(pEnd + 0.05);
          osc2.stop(pEnd + 0.05);
          lfo.stop(pEnd + 0.05);
        });
      } catch (_) {}
    }

    // Web Audio Synthesizer: Afraid / Startled Raccoon Sound Notification
    function synthAfraidRaccoon() {
      try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;

        // Alarmed stages:
        // 1. Sudden startled screech (sharp high attack 3100Hz -> 2100Hz with panicky flutter)
        // 2. Anxious tremor squeak (2400Hz -> 1750Hz)
        // 3. Alarmed whimper chitter (1950Hz -> 1450Hz)
        const stages = [
          { start: 0.00, end: 0.32, f1: 3100, f2: 2100, vib: 42, vibD: 180, vol: 0.35, sharp: true },
          { start: 0.35, end: 0.56, f1: 2400, f2: 1750, vib: 48, vibD: 140, vol: 0.28, sharp: false },
          { start: 0.59, end: 0.80, f1: 1950, f2: 1450, vib: 52, vibD: 110, vol: 0.22, sharp: false }
        ];

        stages.forEach(s => {
          const osc = ctx.createOscillator();
          const oscHarm = ctx.createOscillator();
          const gain = ctx.createGain();
          const lfo = ctx.createOscillator();
          const lfoGain = ctx.createGain();

          osc.type = 'sawtooth';
          oscHarm.type = 'triangle';
          lfo.type = 'sine';

          const sStart = now + s.start;
          const sEnd = now + s.end;
          const sDur = s.end - s.start;

          osc.frequency.setValueAtTime(s.f1, sStart);
          osc.frequency.exponentialRampToValueAtTime(s.f2, sEnd);

          oscHarm.frequency.setValueAtTime(s.f1 * 1.5, sStart);
          oscHarm.frequency.exponentialRampToValueAtTime(s.f2 * 1.5, sEnd);

          lfo.frequency.setValueAtTime(s.vib, sStart);
          lfoGain.gain.setValueAtTime(s.vibD, sStart);
          lfo.connect(osc.frequency);
          lfo.connect(oscHarm.frequency);

          if (s.sharp) {
            gain.gain.setValueAtTime(s.vol, sStart);
            gain.gain.exponentialRampToValueAtTime(0.0001, sEnd);
          } else {
            gain.gain.setValueAtTime(0.0001, sStart);
            gain.gain.exponentialRampToValueAtTime(s.vol, sStart + sDur * 0.25);
            gain.gain.exponentialRampToValueAtTime(0.0001, sEnd);
          }

          osc.connect(gain);
          oscHarm.connect(gain);
          gain.connect(ctx.destination);

          osc.start(sStart);
          oscHarm.start(sStart);
          lfo.start(sStart);
          osc.stop(sEnd + 0.05);
          oscHarm.stop(sEnd + 0.05);
          lfo.stop(sEnd + 0.05);
        });
      } catch (_) {}
    }

    // Unified Sound Player
    function playRaccoonSound(type = 'happy') {
      const isHappy = (type === 'happy' || type === 'send');
      const targetAudio = isHappy ? happyRaccoonAudio : afraidRaccoonAudio;

      try {
        targetAudio.currentTime = 0;
        const playPromise = targetAudio.play();
        if (playPromise !== undefined) {
          playPromise.catch(() => {
            // Audio element failed or blocked: fallback to Web Audio synthesizer
            if (isHappy) {
              synthHappyRaccoon();
            } else {
              synthAfraidRaccoon();
            }
          });
        }
      } catch (_) {
        if (isHappy) {
          synthHappyRaccoon();
        } else {
          synthAfraidRaccoon();
        }
      }
    }

    // Auto-unlock audio on first user interaction
    (function initAudioUnlock() {
      function unlock() {
        getAudioContext();
        happyRaccoonAudio.load();
        afraidRaccoonAudio.load();
        window.removeEventListener('click', unlock);
        window.removeEventListener('keydown', unlock);
        window.removeEventListener('touchstart', unlock);
      }
      window.addEventListener('click', unlock, { once: true });
      window.addEventListener('keydown', unlock, { once: true });
      window.addEventListener('touchstart', unlock, { once: true });
    })();

    let latestIncomingMsgId = null;
    function showIncomingToast(sender, subject, msgId = null) {
      latestIncomingMsgId = msgId;
      const toast = document.getElementById('raccoon-incoming-toast');
      const senderEl = document.getElementById('incoming-toast-sender');
      const subjEl = document.getElementById('incoming-toast-subject');
      if (senderEl) senderEl.innerText = sender || 'New Sender';
      if (subjEl) subjEl.innerText = subject || '(no subject)';
      if (toast) {
        toast.classList.remove('hidden');
        setTimeout(() => {
          toast.classList.remove('-translate-y-4', 'opacity-0');
        }, 10);
        clearTimeout(window.__incomingToastTimer);
        window.__incomingToastTimer = setTimeout(closeIncomingToast, 6500);
      }
    }

    function closeIncomingToast() {
      const toast = document.getElementById('raccoon-incoming-toast');
      if (toast) {
        toast.classList.add('-translate-y-4', 'opacity-0');
        setTimeout(() => {
          toast.classList.add('hidden');
        }, 300);
      }
    }

    function clickIncomingToast() {
      closeIncomingToast();
      if (currentFolder !== 'Inbox') {
        switchFolder('Inbox');
      } else {
        renderMessageList();
      updateMobileSyncTime(); // initial run
      }
      if (latestIncomingMsgId) {
        const list = mailDatabase['Inbox'] || [];
        const idx = list.findIndex(m => m.id === latestIncomingMsgId);
        if (idx !== -1) {
          selectMessageThread(idx);
        }
      }
    }


    // Database of messages across all folders initialized from real SQLite database
    const initialDbMessages = <?php 
        $jsonMsgs = json_encode($initialInboxMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
        echo ($jsonMsgs !== false && strlen($jsonMsgs) > 0) ? $jsonMsgs : '[]';
    ?> || [];

    // Real-Time High-Speed Inbound Mail Engine (Fast 3-Second Cycle + Instant Focus Triggers)
    const knownMessageIds = new Set(initialDbMessages.map(m => m.id));
    const permanentlyDeletedIds = new Set(JSON.parse(localStorage.getItem('raccon_deleted_ids') || '[]'));
    let isPollingActive = false;

    async function pollForIncomingEmails() {
      if (isPollingActive) return;
      isPollingActive = true;

      try {
        let hasNew = false;
        let newSender = '';
        let newSubject = '';
        let newMsgId = null;

        // 1. Direct Inbound Sync API
        try {
          const syncRes = await fetch('/api/messages/sync');
          if (syncRes.ok) {
            const syncData = await syncRes.json();
            const newCount = (typeof syncData.synced === 'number' ? syncData.synced : (syncData.synced?.count || syncData.count || 0));
            const msgsList = Array.isArray(syncData.messages) ? syncData.messages : (syncData.synced?.messages || []);

            if (newCount > 0 || msgsList.length > 0) {
              const first = msgsList[0];
              newSender = first ? (first.sender_name || first.sender_email || 'New Sender') : 'A friend';
              newSubject = first ? (first.subject || '(no subject)') : 'Incoming email';
              newMsgId = first ? first.id : null;
              hasNew = true;
            }
          }
        } catch (_) {}

        // 2. Catch-all SQLite verification for folder=inbox
        const res = await fetch('/api/messages?folder=inbox');
        if (res.ok) {
          const data = await res.json();
          const msgs = data.messages || [];
          for (const m of msgs) {
            if (permanentlyDeletedIds.has(m.id)) continue;
            if (!knownMessageIds.has(m.id)) {
              knownMessageIds.add(m.id);
              if (!hasNew) {
                hasNew = true;
                newSender = m.sender_name || m.sender_email || 'New Sender';
                newSubject = m.subject || '(no subject)';
                newMsgId = m.id;
              }
            }
          }
        }

        if (hasNew) {
          // Play Afraid Raccoon notification sound immediately!
          playRaccoonSound('afraid');
          showIncomingToast(newSender, newSubject, newMsgId);
          
          // Trigger system Web Push Notification across device
          if (window.RacconNotifications) {
            window.RacconNotifications.show({
              title: 'New Email from ' + (newSender || 'Raccon Mail'),
              body: newSubject || '(no subject)',
              tag: 'mail-' + (newMsgId || Date.now()),
              url: '/inbox'
            });
          }

          await fetchFolderMessages(currentFolder);
          await refreshFolderCounts();
        }
      } catch (err) {
        console.warn('Inbound polling notice:', err);
      } finally {
        isPollingActive = false;
      }
    }

    // Instant initial check (0ms delay) + 3-second rapid cycle
    pollForIncomingEmails();
    setInterval(pollForIncomingEmails, 3000);

    // Instant triggers on tab focus & window visibility
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) pollForIncomingEmails();
    });
    window.addEventListener('focus', () => {
      pollForIncomingEmails();
    });


    
        function parseEmailReplyAndQuote(raw) {
      if (!raw) return { isReply: false, replyText: '', quotedText: '' };
      var text = String(raw).trim();
      var norm = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
      var lines = norm.split('\n');
      var count = lines.length;

      var quoteStartLine = -1;

      for (var i = 0; i < count; i++) {
        var line = lines[i].trim();

        // Check for quote header endings like "a scris:", "wrote:", "schrieb:", etc.
        if (/(?:a\s*scris|wrote|a\s*écrit|schrieb)\s*:$/i.test(line)) {
          quoteStartLine = i;
          // Walk backwards up to 6 lines to capture author, date, and email tokens
          for (var j = i - 1; j >= Math.max(0, i - 6); j--) {
            var prev = lines[j].trim();
            if (prev === '') break;
            quoteStartLine = j;
          }
          break;
        }

        // Standard headers
        if (/^-{3,}\s*(?:Original Message|Forwarded message)\s*-{3,}$/i.test(line) ||
            /^_{20,}$/.test(line) ||
            /^From:\s+/i.test(line)) {
          quoteStartLine = i;
          break;
        }
      }

      // Fallback: lines starting with > or &gt;
      if (quoteStartLine === -1) {
        for (var i = 0; i < count; i++) {
          var line = lines[i].trim();
          if (line.startsWith('>') || line.startsWith('&gt;')) {
            quoteStartLine = i;
            if (i > 0 && lines[i - 1].trim() !== '') {
              if (/:\s*$/.test(lines[i - 1].trim())) {
                quoteStartLine = i - 1;
              }
            }
            break;
          }
        }
      }

      if (quoteStartLine > 0) {
        var replyPart = lines.slice(0, quoteStartLine).join('\n').trim();
        var quotedPart = lines.slice(quoteStartLine).join('\n').trim();
        if (replyPart.length > 0) {
          return {
            isReply: true,
            replyText: replyPart,
            quotedText: quotedPart
          };
        }
      }

      if (text.startsWith('>') || text.startsWith('&gt;')) {
        return {
          isReply: true,
          replyText: '',
          quotedText: text
        };
      }

      return {
        isReply: false,
        replyText: text,
        quotedText: ''
      };
    }
    window.parseEmailReplyAndQuote = parseEmailReplyAndQuote;

    window.toggleQuotedHistory = function(btn) {
      var panel = btn.nextElementSibling;
      var textSpan = btn.querySelector('span:nth-child(2)');
      var chevron = btn.querySelector('[data-lucide="chevron-down"], svg');
      if (!panel) return;
      var isHidden = panel.classList.contains('hidden');
      if (isHidden) {
        panel.classList.remove('hidden');
        if (textSpan) textSpan.textContent = 'Hide quoted email history';
        if (chevron) chevron.style.transform = 'rotate(180deg)';
      } else {
        panel.classList.add('hidden');
        if (textSpan) textSpan.textContent = 'Show quoted email history';
        if (chevron) chevron.style.transform = 'rotate(0deg)';
      }
    };

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
    window.escapeHtml = escapeHtml;

    function cleanMimeEmailContent(raw) {
      if (!raw || typeof raw !== 'string') return '';
      let str = raw.trim();
      if (str.startsWith('PK\x03\x04')) return '[Binary Archive: ZIP file / DMARC Report attachment]';
      if (str.startsWith('\x1f\x8b')) return '[Binary Archive: GZIP compressed data attachment]';
      if (str.includes('--') || /content-type:\s*(text\/plain|text\/html|multipart)/i.test(str)) {
        const plainMatch = str.match(/content-type:\s*text\/plain[^]*?\r?\n\r?\n([\s\S]*?)(?=--[a-zA-Z0-9_\-\.]+|\z)/i);
        if (plainMatch && plainMatch[1] && plainMatch[1].trim()) {
          str = plainMatch[1].trim();
        } else {
          const htmlMatch = str.match(/content-type:\s*text\/html[^]*?\r?\n\r?\n([\s\S]*?)(?=--[a-zA-Z0-9_\-\.]+|\z)/i);
          if (htmlMatch && htmlMatch[1] && htmlMatch[1].trim()) {
            str = htmlMatch[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
          } else {
            str = str.replace(/--[a-zA-Z0-9_\-\.]+(?:--)?/g, '');
            str = str.replace(/content-type:[^\r\n]+/gi, '');
            str = str.replace(/content-transfer-encoding:[^\r\n]+/gi, '');
            str = str.replace(/charset=["']?[^"'\r\n]+["']?/gi, '');
          }
        }
      }
      str = str.replace(/^--[a-zA-Z0-9_\-\.]+/gm, '');
      str = str.replace(/--[a-zA-Z0-9_\-\.]+--\s*$/gm, '');
      str = str.replace(/^[ \t]*Content-Type:[^\r\n]*/gmi, '');
      str = str.replace(/^[ \t]*Content-Transfer-Encoding:[^\r\n]*/gmi, '');
      return str.trim();
    }
  
    const mailDatabase = {
      'Inbox': initialDbMessages.map(m => ({
        id: m.id,
        sender: m.sender_email,
        senderName: m.sender_name || m.sender_email,
        time: (m.created_at || '').slice(11, 16) || 'Today',
        subject: m.subject || '(no subject)',
        isStarred: !!m.is_starred,
        isRead: !!m.is_read,
        avatar: '/raccoon-logo.svg',
        body_html: m.body_html || '',
        thread: [
          {
            sender: m.sender_email,
            to: m.recipient_email || '<?php echo htmlspecialchars($currentUser['email']); ?>',
            date: m.created_at || 'Recently',
            avatar: '/raccoon-logo.svg',
            body: m.body || m.body_html || 'No content.'
          }
        ]
      })),
      'Unread': [],
      'Starred': [],
      'Drafts': [],
      'Sent': [],
      'Archive': [],
      'Spam': [],
      'Trash': []
    };

    // Togglebar Action (ProtonMail Style)
    function toggleSidebarAction() {
      const sidebar = document.getElementById('sidebar-panel');
      const backdrop = document.getElementById('mobile-sidebar-backdrop');
      if (!sidebar) return;

      if (window.innerWidth < 768) {
        // Mobile drawer slide
        const isClosed = sidebar.classList.contains('-translate-x-full');
        if (isClosed) {
          sidebar.classList.remove('-translate-x-full');
          backdrop?.classList.remove('hidden');
        } else {
          sidebar.classList.add('-translate-x-full');
          backdrop?.classList.add('hidden');
        }
      } else {
        // Desktop collapse toggle
        isSidebarCollapsed = !isSidebarCollapsed;
        sidebar.classList.toggle('sidebar-collapsed', isSidebarCollapsed);
      }
    }
    window.toggleSidebarAction = toggleSidebarAction;

    // Switch Folder (Inbox, Unread, Starred, Sent, Drafts, Archive, Spam, Trash, All)
    window.selectFolder = function(f) { return switchFolder(f); };

    // =========================================================================
    // MOBILE REFERENCE APP HELPER FUNCTIONS
    // =========================================================================
    function getSenderAvatarColor(name) {
      const colors = [
        'bg-blue-100 text-blue-700',
        'bg-emerald-100 text-emerald-700',
        'bg-indigo-100 text-indigo-700',
        'bg-violet-100 text-violet-700',
        'bg-amber-100 text-amber-800',
        'bg-rose-100 text-rose-700',
        'bg-cyan-100 text-cyan-700',
        'bg-teal-100 text-teal-700'
      ];
      let hash = 0;
      for (let i = 0; i < (name || '').length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
      }
      return colors[Math.abs(hash) % colors.length];
    }

    function formatMobileTime(timeStr) {
      if (!timeStr) return 'Today';
      const m = String(timeStr).match(/\d{1,2}:\d{2}(\s*(?:AM|PM))?/i);
      if (m) return m[0];
      return String(timeStr).replace(/Today,?\s*/i, '');
    }

    function updateMobileSyncTime() {
      const el = document.getElementById('mobile-top-sync-subtitle');
      if (!el) return;
      const now = new Date();
      const m = String(now.getMonth() + 1).padStart(2, '0');
      const d = String(now.getDate()).padStart(2, '0');
      const y = String(now.getFullYear()).slice(-2);
      const hh = String(now.getHours()).padStart(2, '0');
      const mm = String(now.getMinutes()).padStart(2, '0');
      el.innerText = `Synced: ${m}/${d}/${y} ${hh}:${mm}`;
    }

    // Mobile Bounce Search Controls
    function toggleMobileBounceSearch(open) {
      const bar = document.getElementById('mobile-bounce-search-bar');
      const input = document.getElementById('mobile-bounce-search-input');
      if (!bar) return;

      if (open) {
        bar.classList.remove('hidden', 'search-bounce-exit');
        bar.classList.add('search-bounce-enter');
        if (input) {
          setTimeout(() => input.focus(), 120);
        }
      } else {
        bar.classList.remove('search-bounce-enter');
        bar.classList.add('search-bounce-exit');
        setTimeout(() => {
          bar.classList.add('hidden');
          bar.classList.remove('search-bounce-exit');
        }, 220);
        clearMobileBounceSearch();
      }
    }

    function handleMobileBounceSearch(query) {
      const clearBtn = document.getElementById('mobile-bounce-search-clear');
      if (clearBtn) {
        if (query && query.length > 0) clearBtn.classList.remove('hidden');
        else clearBtn.classList.add('hidden');
      }
      const desktopSearch = document.getElementById('global-search');
      if (desktopSearch) desktopSearch.value = query;
      handleSearch(query);
    }

    function clearMobileBounceSearch() {
      const input = document.getElementById('mobile-bounce-search-input');
      const clearBtn = document.getElementById('mobile-bounce-search-clear');
      if (input) input.value = '';
      if (clearBtn) clearBtn.classList.add('hidden');
      const desktopSearch = document.getElementById('global-search');
      if (desktopSearch) desktopSearch.value = '';
      handleSearch('');
    }

    // Mobile Filter Popover Controls
    function toggleMobileFilterMenu(e) {
      if (e) e.stopPropagation();
      const popover = document.getElementById('mobile-filter-popover');
      if (popover) {
        popover.classList.toggle('hidden');
      }
    }

    function closeMobileFilterPopover() {
      const popover = document.getElementById('mobile-filter-popover');
      if (popover) popover.classList.add('hidden');
    }

    // Mobile Bottom Tab Switcher
    function switchMobileTab(tab) {
      const emailPill = document.getElementById('mobile-tab-email-pill');
      const emailLabel = document.getElementById('mobile-tab-email-label');
      const filesPill = document.getElementById('mobile-tab-files-pill');
      const filesLabel = document.getElementById('mobile-tab-files-label');

      if (tab === 'email') {
        closeProductivityDrawer();
        backToMobileInbox();
        if (emailPill) {
          emailPill.className = 'px-5 py-1 rounded-full bg-blue-100 text-blue-700 transition';
          const icon = emailPill.querySelector('i');
          if (icon) icon.className = 'w-5 h-5 text-blue-700';
        }
        if (emailLabel) emailLabel.className = 'text-[11px] font-bold text-blue-700 mt-0.5';

        if (filesPill) {
          filesPill.className = 'px-5 py-1 rounded-full text-slate-500 hover:text-slate-900 transition';
          const icon = filesPill.querySelector('i');
          if (icon) icon.className = 'w-5 h-5 text-slate-500';
        }
        if (filesLabel) filesLabel.className = 'text-[11px] font-medium text-slate-500 mt-0.5';
      } else if (tab === 'files') {
        openProductivityDrawer('attachments');
        if (filesPill) {
          filesPill.className = 'px-5 py-1 rounded-full bg-blue-100 text-blue-700 transition';
          const icon = filesPill.querySelector('i');
          if (icon) icon.className = 'w-5 h-5 text-blue-700';
        }
        if (filesLabel) filesLabel.className = 'text-[11px] font-bold text-blue-700 mt-0.5';

        if (emailPill) {
          emailPill.className = 'px-5 py-1 rounded-full text-slate-500 hover:text-slate-900 transition';
          const icon = emailPill.querySelector('i');
          if (icon) icon.className = 'w-5 h-5 text-slate-500';
        }
        if (emailLabel) emailLabel.className = 'text-[11px] font-medium text-slate-500 mt-0.5';
      }
    }

    // Close popover when clicking anywhere else
    document.addEventListener('click', function(e) {
      if (!e.target.closest('#mobile-filter-popover') && !e.target.closest('button[onclick*="toggleMobileFilterMenu"]')) {
        closeMobileFilterPopover();
      }
    });

    // Mobile Profile Page Handlers
    
    // Direct Photo Upload for Mobile Profile
    async function handleDirectPhotoUpload(event) {
      const file = event.target.files?.[0];
      if (!file) return;

      const largeImg = document.getElementById('mobile-profile-large-img');
      const originalSrc = largeImg?.src;
      
      try {
        if (typeof showToast === 'function') showToast('Uploading profile photo... ⏳', 'info');
        
        const formData = new FormData();
        formData.append('avatar', file, file.name || 'avatar.jpg');

        const tok = typeof getAuthToken === 'function' ? getAuthToken() : '';
        const authHeader = tok ? { 'Authorization': 'Bearer ' + tok } : {};

        const res = await fetch('/api/user/avatar', {
          method: 'POST',
          credentials: 'include',
          headers: authHeader,
          body: formData
        });
        const data = await res.json();

        if (data.success && data.avatar) {
          const cacheBuster = (data.avatar.includes('?') ? '&' : '?') + 't=' + Date.now();
          document.querySelectorAll('.user-avatar-img').forEach(img => {
            img.src = data.avatar + cacheBuster;
            img.style.display = 'block';
          });
          if (largeImg) largeImg.src = data.avatar + cacheBuster;
          const fallback = document.getElementById('mobile-header-initial-fallback');
          if (fallback) fallback.style.display = 'none';
          
          if (typeof showToast === 'function') {
            showToast('Profile photo updated successfully! 🦝', 'success');
          } else {
            alert('Profile photo updated successfully!');
          }
        } else {
          throw new Error(data.error || 'Failed to upload photo.');
        }
      } catch (err) {
        alert('Could not update profile photo: ' + err.message);
        if (largeImg && originalSrc) largeImg.src = originalSrc;
      } finally {
        event.target.value = '';
      }
    }

    function openMobileProfilePage() {
      const page = document.getElementById('mobile-profile-page');
      if (page) {
        page.classList.remove('hidden');
        if (window.lucide) {
          try { lucide.createIcons(); } catch (_) {}
        }
        try {
          history.pushState({ profilePageOpen: true }, '');
        } catch (_) {}
      }
    }

    function closeMobileProfilePage() {
      const page = document.getElementById('mobile-profile-page');
      if (page) {
        page.classList.add('hidden');
      }
    }

    // Handle device back button
    window.addEventListener('popstate', function(e) {
      const page = document.getElementById('mobile-profile-page');
      if (page && !page.classList.contains('hidden')) {
        closeMobileProfilePage();
      }
    });

    async function switchFolder(folderName) {
      currentFolder = folderName;
      currentSelectedThreadIndex = 0;

      // Close mobile drawer if open
      if (window.innerWidth < 768) {
        document.getElementById('sidebar-panel')?.classList.add('-translate-x-full');
        document.getElementById('mobile-sidebar-backdrop')?.classList.add('hidden');
        document.getElementById('col-inbox')?.classList.remove('hidden');
        document.getElementById('col-reader')?.classList.add('hidden');
      }

      // Update sidebar active highlights
      const folderIds = ['inbox', 'unread', 'starred', 'drafts', 'sent', 'archive', 'spam', 'trash', 'all'];
      folderIds.forEach(id => {
        const btn = document.getElementById('folder-btn-' + id);
        if (btn) {
          if (id.toLowerCase() === folderName.toLowerCase()) {
            btn.className = 'w-full flex items-center justify-between px-3 py-2 text-xs font-bold nav-pill-active cursor-pointer';
          } else {
            btn.className = 'w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive';
          }
        }
      });

      // Update title in middle column & desktop workspace & mobile title
      const titleEl = document.getElementById('current-folder-display-title');
      if (titleEl) titleEl.innerText = folderName;
      const desktopTitle = document.getElementById('desktop-workspace-title');
      if (desktopTitle) desktopTitle.innerText = folderName;
      const mobileFolderTitle = document.getElementById('mobile-top-folder-title');
      if (mobileFolderTitle) mobileFolderTitle.innerText = folderName;
      const dropdownLabel = document.getElementById('all-emails-dropdown-label');
      if (dropdownLabel) dropdownLabel.innerText = folderName;

      const trashEmptyBtn = document.getElementById('trash-empty-btn');
      if (trashEmptyBtn) {
        if (folderName.toLowerCase() === 'trash') {
          trashEmptyBtn.classList.remove('hidden');
        } else {
          trashEmptyBtn.classList.add('hidden');
        }
      }

      renderMessageList();
      await fetchFolderMessages(folderName);
    }

    // Filter message list (All, Unread, Starred)
    function setListFilter(filter) {
      applyEmailFilter(filter);
    }

    // Render messages in Middle Column
    
    // Unified Filter Handler for All, Unread, Starred, Attachments
    function applyEmailFilter(mode) {
      currentFilter = mode; // 'all', 'unread', 'starred', 'attachments'
      
      // Update checkmarks in mobile filter popover
      ['all', 'unread', 'starred', 'attachments'].forEach(m => {
        const checkEl = document.getElementById('filter-check-' + m);
        if (checkEl) {
          if (m === mode) checkEl.classList.remove('hidden');
          else checkEl.classList.add('hidden');
        }
      });

      // Update desktop filter buttons if present
      ['all', 'unread', 'starred'].forEach(m => {
        const dBtn = document.getElementById('d-filter-' + m);
        if (dBtn) {
          if (m === mode) {
            dBtn.className = 'px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white shadow-2xs';
          } else {
            dBtn.className = 'px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100';
          }
        }
      });

      const q = document.getElementById('global-search')?.value || document.getElementById('desktop-search-input')?.value || '';
      renderMessageList(q);
      closeMobileFilterPopover();

      if (typeof showToast === 'function') {
        const label = mode === 'unread' ? 'Filter: Unread only' : (mode === 'starred' ? 'Filter: Starred only' : 'Showing all messages');
        showToast(label, 'info');
      }
    }

    // Export current folder emails to Excel/CSV
    function exportEmailsToCSV() {
      const list = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) : (mailDatabase[currentFolder] || []);
      if (!list.length) {
        alert('No emails in ' + currentFolder + ' to export.');
        return;
      }
      let csv = 'ID,Sender Name,Sender Email,Subject,Date,Starred,Read\r\n';
      list.forEach(m => {
        const sName = (m.senderName || '').replace(/"/g, '""');
        const sEmail = (m.sender || '').replace(/"/g, '""');
        const subj = (m.subject || '').replace(/"/g, '""');
        csv += `"${m.id}","${sName}","${sEmail}","${subj}","${m.time || ''}","${m.isStarred ? 'Yes':'No'}","${m.isRead ? 'Yes':'No'}"\r\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `RacconMail_${currentFolder}_${Date.now()}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      if (typeof showToast === 'function') showToast('Exported ' + list.length + ' emails to Excel/CSV! 📊', 'success');
    }

    // Print active email
    function printActiveMail() {
      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'] || [];
      const msg = list[currentSelectedThreadIndex];
      if (msg) {
        const printWindow = window.open('', '_blank');
        const bodyContent = msg.thread && msg.thread[0] ? (msg.thread[0].body_html || msg.thread[0].body || '') : (msg.body_html || msg.preview || '');
        printWindow.document.write(`
          <!DOCTYPE html>
          <html>
            <head>
              <title>${msg.subject || 'Print Email'}</title>
              <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; color: #1e293b; line-height: 1.6; }
                h1 { font-size: 20px; font-weight: bold; margin-bottom: 8px; color: #0f172a; }
                .meta { font-size: 13px; color: #64748b; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; }
                .content { font-size: 14px; }
              </style>
            </head>
            <body>
              <h1>${msg.subject || '(No Subject)'}</h1>
              <div class="meta">
                <div><strong>From:</strong> ${msg.senderName || ''} &lt;${msg.sender || ''}&gt;</div>
                <div><strong>Date:</strong> ${msg.time || ''}</div>
              </div>
              <div class="content">${bodyContent}</div>
            
  <!-- ========================================================================= -->
  <!-- INTERACTIVE WHITEBOARD STUDIO MODAL                                      -->
  <!-- ========================================================================= -->
  <div id="whiteboard-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[99999] flex flex-col p-2 sm:p-5 select-none overflow-hidden">
    <div class="bg-white rounded-3xl w-full h-full flex flex-col shadow-2xl border border-slate-200 overflow-hidden">
      
      <!-- Whiteboard Studio Header Bar -->
      <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3 shrink-0">
        <!-- Title & Mascot -->
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-9 h-9 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center border border-violet-200/80 shrink-0">
            <i data-lucide="presentation" class="w-5 h-5"></i>
          </div>
          <div>
            <h3 class="font-black text-sm text-slate-900 tracking-tight leading-tight">Raccon Whiteboard Studio</h3>
            <p class="text-[11px] text-slate-400">Sketch ideas, draw diagrams, and brainstorm freely</p>
          </div>
        </div>

        <!-- Center Drawing Controls -->
        <div class="flex items-center gap-2 flex-wrap bg-white p-1 rounded-2xl border border-slate-200/80 shadow-2xs">
          <!-- Pen Tool -->
          <button type="button" id="wb-tool-pen" onclick="setWhiteboardTool('pen')" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 text-white shadow-xs transition flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="pen-tool" class="w-3.5 h-3.5"></i>
            <span>Pen</span>
          </button>

          <!-- Eraser Tool -->
          <button type="button" id="wb-tool-eraser" onclick="setWhiteboardTool('eraser')" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="eraser" class="w-3.5 h-3.5"></i>
            <span>Eraser</span>
          </button>

          <div class="w-px h-5 bg-slate-200 my-auto mx-1"></div>

          <!-- Color Palette -->
          <div class="flex items-center gap-1">
            <button type="button" onclick="setWhiteboardColor('#1e293b', this)" class="wb-color-btn w-6 h-6 rounded-full bg-slate-900 ring-2 ring-slate-900 ring-offset-2 transition cursor-pointer" title="Black"></button>
            <button type="button" onclick="setWhiteboardColor('#2563eb', this)" class="wb-color-btn w-6 h-6 rounded-full bg-blue-600 transition cursor-pointer" title="Blue"></button>
            <button type="button" onclick="setWhiteboardColor('#7c3aed', this)" class="wb-color-btn w-6 h-6 rounded-full bg-purple-600 transition cursor-pointer" title="Purple"></button>
            <button type="button" onclick="setWhiteboardColor('#dc2626', this)" class="wb-color-btn w-6 h-6 rounded-full bg-rose-600 transition cursor-pointer" title="Red"></button>
            <button type="button" onclick="setWhiteboardColor('#16a34a', this)" class="wb-color-btn w-6 h-6 rounded-full bg-emerald-600 transition cursor-pointer" title="Green"></button>
            <button type="button" onclick="setWhiteboardColor('#d97706', this)" class="wb-color-btn w-6 h-6 rounded-full bg-amber-600 transition cursor-pointer" title="Amber"></button>
          </div>

          <div class="w-px h-5 bg-slate-200 my-auto mx-1"></div>

          <!-- Brush Size Selector -->
          <div class="flex items-center gap-1">
            <button type="button" onclick="setWhiteboardSize(2, this)" class="wb-size-btn px-2 py-1 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100" title="Fine (2px)">2px</button>
            <button type="button" onclick="setWhiteboardSize(5, this)" class="wb-size-btn px-2 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-900" title="Regular (5px)">5px</button>
            <button type="button" onclick="setWhiteboardSize(12, this)" class="wb-size-btn px-2 py-1 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100" title="Bold (12px)">12px</button>
          </div>
        </div>

        <!-- Right Action Buttons -->
        <div class="flex items-center gap-2 shrink-0">
          <button type="button" onclick="clearWhiteboardCanvas()" class="px-3 py-1.5 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer" title="Clear Canvas">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            <span>Clear</span>
          </button>
          <button type="button" onclick="downloadWhiteboardPNG()" class="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer" title="Download Image">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
            <span>Export PNG</span>
          </button>
          <button type="button" onclick="closeWhiteboard()" class="w-8 h-8 rounded-xl hover:bg-slate-200/70 text-slate-400 hover:text-slate-800 transition flex items-center justify-center cursor-pointer ml-1" title="Close Whiteboard">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

      <!-- Canvas Area -->
      <div id="whiteboard-canvas-container" class="flex-1 bg-white relative overflow-hidden cursor-crosshair touch-none">
        <canvas id="whiteboard-canvas" class="w-full h-full block bg-white"></canvas>
      </div>

    </div>
  </div>

</body>
          </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
      } else {
        window.print();
      }
    }

    // Desktop Split-View Toggle
    let isDesktopSplitActive = true;
    function toggleDesktopSplitView() {
      const colReader = document.getElementById('col-reader');
      const colInbox = document.getElementById('col-inbox');
      if (!colReader || !colInbox) return;
      isDesktopSplitActive = !isDesktopSplitActive;

      if (isDesktopSplitActive) {
        colReader.classList.remove('hidden');
        colReader.classList.add('md:flex');
        colInbox.classList.remove('md:w-full');
        colInbox.classList.add('md:w-[370px]', 'lg:w-[410px]');
      } else {
        colReader.classList.add('hidden');
        colReader.classList.remove('md:flex');
        colInbox.classList.remove('md:w-[370px]', 'lg:w-[410px]');
        colInbox.classList.add('md:w-full');
      }
    }

    // Toggle Importance
    function toggleImportantItem(msgId) {
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          const item = mailDatabase[f].find(m => m.id === msgId);
          if (item) {
            item.isImportant = !item.isImportant;
            break;
          }
        }
      }
      renderMessageList(document.getElementById('global-search')?.value || document.getElementById('desktop-search-input')?.value || '');
    }

    // Master Checkbox Toggle
    function toggleSelectAllEmails(masterCb) {
      const isChecked = masterCb.checked;
      document.querySelectorAll('.email-checkbox').forEach(cb => {
        cb.checked = isChecked;
      });
    }

    // Toggle Desktop App Rail
    function toggleDesktopAppRail() {
      const rail = document.getElementById('sidebar-panel');
      if (rail) {
        rail.classList.toggle('hidden');
        rail.classList.toggle('md:flex');
      }
    }

    function toggleAllEmailsDropdown(e) {
      if (e) e.stopPropagation();
      const menu = document.getElementById('all-emails-dropdown-menu');
      if (menu) menu.classList.toggle('hidden');
    }

    function closeAllEmailsDropdown() {
      const menu = document.getElementById('all-emails-dropdown-menu');
      if (menu) menu.classList.add('hidden');
    }

    
    // Delete single email item with confirmation
    async function deleteEmailItem(msgId) {
      if (!confirm('Are you sure you want to permanently delete this email?')) return;
      await deleteMessageById(msgId);
      if (typeof showToast === 'function') showToast('Email permanently deleted 🗑️', 'info');
    }

    
    // =========================================================================
    // RACCON WHITEBOARD STUDIO ENGINE
    // =========================================================================
    let wbCanvas = null;
    let wbCtx = null;
    let wbIsDrawing = false;
    let wbCurrentTool = 'pen';
    let wbCurrentColor = '#1e293b';
    let wbCurrentSize = 5;

    function openWhiteboard() {
      const modal = document.getElementById('whiteboard-modal');
      if (!modal) return;
      modal.classList.remove('hidden');
      setTimeout(initWhiteboardCanvas, 50);
      if (window.lucide) {
        try { lucide.createIcons(); } catch (_) {}
      }
    }
    window.openWhiteboard = openWhiteboard;

    function closeWhiteboard() {
      const modal = document.getElementById('whiteboard-modal');
      if (modal) modal.classList.add('hidden');
    }
    window.closeWhiteboard = closeWhiteboard;

    function initWhiteboardCanvas() {
      wbCanvas = document.getElementById('whiteboard-canvas');
      if (!wbCanvas) return;
      const container = document.getElementById('whiteboard-canvas-container');
      if (!container) return;
      const rect = container.getBoundingClientRect();
      
      const dpr = window.devicePixelRatio || 1;
      wbCanvas.width = rect.width * dpr;
      wbCanvas.height = rect.height * dpr;
      wbCtx = wbCanvas.getContext('2d');
      wbCtx.scale(dpr, dpr);
      wbCtx.lineCap = 'round';
      wbCtx.lineJoin = 'round';

      // Fill white background so exported PNG is clean
      wbCtx.fillStyle = '#ffffff';
      wbCtx.fillRect(0, 0, rect.width, rect.height);

      // Mouse events
      wbCanvas.onmousedown = function(e) {
        wbIsDrawing = true;
        const b = wbCanvas.getBoundingClientRect();
        wbCtx.beginPath();
        wbCtx.moveTo(e.clientX - b.left, e.clientY - b.top);
      };

      wbCanvas.onmousemove = function(e) {
        if (!wbIsDrawing) return;
        const b = wbCanvas.getBoundingClientRect();
        wbCtx.strokeStyle = (wbCurrentTool === 'eraser') ? '#ffffff' : wbCurrentColor;
        wbCtx.lineWidth = (wbCurrentTool === 'eraser') ? wbCurrentSize * 3 : wbCurrentSize;
        wbCtx.lineTo(e.clientX - b.left, e.clientY - b.top);
        wbCtx.stroke();
      };

      wbCanvas.onmouseup = function() { wbIsDrawing = false; };
      wbCanvas.onmouseleave = function() { wbIsDrawing = false; };

      // Touch events (mobile & tablets)
      wbCanvas.ontouchstart = function(e) {
        if (e.touches.length > 1) return;
        e.preventDefault();
        wbIsDrawing = true;
        const b = wbCanvas.getBoundingClientRect();
        const t = e.touches[0];
        wbCtx.beginPath();
        wbCtx.moveTo(t.clientX - b.left, t.clientY - b.top);
      };

      wbCanvas.ontouchmove = function(e) {
        if (!wbIsDrawing || e.touches.length > 1) return;
        e.preventDefault();
        const b = wbCanvas.getBoundingClientRect();
        const t = e.touches[0];
        wbCtx.strokeStyle = (wbCurrentTool === 'eraser') ? '#ffffff' : wbCurrentColor;
        wbCtx.lineWidth = (wbCurrentTool === 'eraser') ? wbCurrentSize * 3 : wbCurrentSize;
        wbCtx.lineTo(t.clientX - b.left, t.clientY - b.top);
        wbCtx.stroke();
      };

      wbCanvas.ontouchend = function(e) { wbIsDrawing = false; };
    }

    function setWhiteboardTool(tool) {
      wbCurrentTool = tool;
      const penBtn = document.getElementById('wb-tool-pen');
      const eraserBtn = document.getElementById('wb-tool-eraser');
      if (tool === 'pen') {
        penBtn?.classList.add('bg-slate-900', 'text-white', 'shadow-xs');
        penBtn?.classList.remove('text-slate-600', 'hover:bg-slate-100');
        eraserBtn?.classList.remove('bg-slate-900', 'text-white', 'shadow-xs');
        eraserBtn?.classList.add('text-slate-600', 'hover:bg-slate-100');
      } else {
        eraserBtn?.classList.add('bg-slate-900', 'text-white', 'shadow-xs');
        eraserBtn?.classList.remove('text-slate-600', 'hover:bg-slate-100');
        penBtn?.classList.remove('bg-slate-900', 'text-white', 'shadow-xs');
        penBtn?.classList.add('text-slate-600', 'hover:bg-slate-100');
      }
    }
    window.setWhiteboardTool = setWhiteboardTool;

    function setWhiteboardColor(color, el) {
      wbCurrentColor = color;
      setWhiteboardTool('pen');
      document.querySelectorAll('.wb-color-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-slate-900', 'ring-offset-2');
      });
      el?.classList.add('ring-2', 'ring-slate-900', 'ring-offset-2');
    }
    window.setWhiteboardColor = setWhiteboardColor;

    function setWhiteboardSize(size, el) {
      wbCurrentSize = size;
      document.querySelectorAll('.wb-size-btn').forEach(btn => {
        btn.classList.remove('bg-slate-100', 'text-slate-900', 'font-bold');
        btn.classList.add('text-slate-500');
      });
      el?.classList.add('bg-slate-100', 'text-slate-900', 'font-bold');
      el?.classList.remove('text-slate-500');
    }
    window.setWhiteboardSize = setWhiteboardSize;

    function clearWhiteboardCanvas() {
      if (!confirm('Are you sure you want to clear the whiteboard?')) return;
      if (!wbCanvas || !wbCtx) return;
      const b = wbCanvas.getBoundingClientRect();
      wbCtx.fillStyle = '#ffffff';
      wbCtx.fillRect(0, 0, b.width, b.height);
    }
    window.clearWhiteboardCanvas = clearWhiteboardCanvas;

    function downloadWhiteboardPNG() {
      if (!wbCanvas) return;
      const link = document.createElement('a');
      link.download = 'raccon-whiteboard-' + Date.now() + '.png';
      link.href = wbCanvas.toDataURL('image/png');
      link.click();
    }
    window.downloadWhiteboardPNG = downloadWhiteboardPNG;


    
    // =========================================================================
    // 2-COLUMN BLUEPRINT: STATE, DATE GROUPING & FILTERING
    // =========================================================================
    function getEmailDateInfo(dateStr) {
      if (!dateStr) return { group: 'OLDER', display: 'Recent', full: 'Recently', relative: 'Recently' };
      const d = new Date(String(dateStr).replace(' ', 'T'));
      if (isNaN(d.getTime())) {
        return { group: 'THIS MONTH', display: String(dateStr).slice(0, 10), full: String(dateStr), relative: 'Recently' };
      }
      const now = new Date();
      const diffMs = now - d;
      const diffSec = Math.floor(diffMs / 1000);
      const diffMin = Math.floor(diffSec / 60);
      const diffHours = Math.floor(diffMin / 60);
      const diffDays = Math.floor(diffHours / 24);

      let relative = 'Just now';
      if (diffMin >= 1 && diffMin < 60) relative = `${diffMin}m ago`;
      else if (diffHours >= 1 && diffHours < 24) relative = `${diffHours}h ago`;
      else if (diffDays === 1) relative = 'Yesterday';
      else if (diffDays > 1 && diffDays < 30) relative = `${diffDays} days ago`;
      else if (diffDays >= 30) relative = `${Math.floor(diffDays / 30)} month${Math.floor(diffDays / 30) > 1 ? 's' : ''} ago`;

      const isToday = d.toDateString() === now.toDateString();
      const yesterday = new Date(now);
      yesterday.setDate(now.getDate() - 1);
      const isYesterday = d.toDateString() === yesterday.toDateString();

      let group = 'THIS MONTH';
      if (isToday) group = 'TODAY';
      else if (isYesterday) group = 'YESTERDAY';
      else if (d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()) group = 'THIS MONTH';
      else group = 'OLDER';

      const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const fullMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
      
      let display = `${monthNames[d.getMonth()]} ${d.getDate()}`;
      const hours = d.getHours();
      const minutes = d.getMinutes().toString().padStart(2, '0');
      const ampm = hours >= 12 ? 'PM' : 'AM';
      const formattedHours = hours % 12 || 12;

      if (isToday) {
        display = `${formattedHours}:${minutes} ${ampm}`;
      }

      const full = `${fullMonthNames[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}, ${formattedHours}:${minutes} ${ampm}`;

      return { group, display, full, relative };
    }
    window.getEmailDateInfo = getEmailDateInfo;

    function getCurrentFilteredList(searchQuery = '') {
      let list = [];
      if (currentFolder === 'All') {
        list = [].concat(...Object.values(mailDatabase));
      } else if (currentFolder === 'Unread') {
        list = (mailDatabase['Inbox'] || []).filter(m => !m.isRead);
      } else if (currentFolder === 'Starred') {
        list = [].concat(...Object.values(mailDatabase)).filter(m => m.isStarred);
      } else if (currentFolder === 'Updates') {
        list = (mailDatabase['Updates'] && mailDatabase['Updates'].length > 0) ? mailDatabase['Updates'] : [].concat(...Object.values(mailDatabase)).filter(m => m.is_reply || m.category === 'updates' || (m.subject && m.subject.toLowerCase().startsWith('re:')));
      } else {
        list = mailDatabase[currentFolder] || [];
      }

      // Filter out automated mail delivery software delay warnings and bounces
      list = list.filter(m => {
        const text = ((m.sender || m.sender_email || '') + ' ' + (m.senderName || m.sender_name || '') + ' ' + (m.subject || '') + ' ' + (m.body || '') + ' ' + (m.preview || '')).toLowerCase();
        return !text.includes('mailer-daemon') &&
               !text.includes('mail delivery system') &&
               !text.includes('mail delivery software') &&
               !text.includes('created automatically by mail delivery software') &&
               !text.includes('host lookup did not complete') &&
               !text.includes('delayed 72 hours') &&
               !text.includes('warning: message') &&
               !text.includes('myboostmobile.com');
      });

      if (currentFilter === 'unread') list = list.filter(m => !m.isRead);
      if (currentFilter === 'read') list = list.filter(m => m.isRead);
      if (currentFilter === 'starred') list = list.filter(m => m.isStarred);
      if (currentFilter === 'attachments') list = list.filter(m => m.hasAttachments || (m.attachments && m.attachments.length > 0));

      const q = (searchQuery || document.getElementById('global-search')?.value || '').toLowerCase().trim();
      if (q) {
        list = list.filter(m => 
          (m.senderName || '').toLowerCase().includes(q) ||
          (m.subject || '').toLowerCase().includes(q) ||
          (m.sender || '').toLowerCase().includes(q) ||
          (m.preview || '').toLowerCase().includes(q)
        );
      }
      return list;
    }
    window.getCurrentFilteredList = getCurrentFilteredList;

    function applyEmailFilter(mode) {
      currentFilter = mode; // 'all', 'read', 'unread', 'starred', 'attachments'
      
      // Update top tab pills in middle column
      ['all', 'read', 'unread'].forEach(m => {
        const dBtn = document.getElementById('d-filter-' + m);
        if (dBtn) {
          if (m === mode) {
            dBtn.className = 'px-2.5 py-1 rounded-md text-xs font-bold bg-white text-slate-900 shadow-2xs transition cursor-pointer';
          } else {
            dBtn.className = 'px-2.5 py-1 rounded-md text-xs font-semibold text-slate-600 hover:text-slate-900 transition cursor-pointer';
          }
        }
      });

      const q = document.getElementById('global-search')?.value || '';
      renderMessageList(q);
      closeMobileFilterPopover();

      if (typeof showToast === 'function') {
        const label = mode === 'unread' ? 'Filter: Unread' : (mode === 'read' ? 'Filter: Read' : (mode === 'starred' ? 'Filter: Starred' : 'All messages'));
        showToast(label, 'info');
      }
    }
    window.applyEmailFilter = applyEmailFilter;

    // =========================================================================
    // RENDER MESSAGE LIST (MIDDLE COLUMN)
    // =========================================================================
    function renderMessageList(searchQuery = '') {
      const container = document.getElementById('threads-container');
      if (!container) return;

      const list = getCurrentFilteredList(searchQuery);

      // Update counter badges
      const curFolderList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) : (mailDatabase[currentFolder] || list);
      const folderTotal = curFolderList.length;
      const folderUnread = curFolderList.filter(m => !m.isRead).length;

      const badge = document.getElementById('coral-inbox-badge');
      if (badge) {
        badge.innerText = folderTotal;
        badge.classList.remove('hidden');
      }

      const unreadPill = document.getElementById('inbox-unread-pill');
      if (unreadPill) {
        if (folderUnread > 0) {
          unreadPill.innerText = folderUnread + ' unread';
          unreadPill.classList.remove('hidden');
        } else {
          unreadPill.classList.add('hidden');
        }
      }

      if (list.length === 0) {
        container.innerHTML = `
          <div class="p-10 text-center text-slate-400 select-none">
            <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center mx-auto mb-3 text-slate-300 border border-slate-100">
              <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5v-3h3.56c.69 1.19 1.97 2 3.44 2s2.75-.81 3.44-2H19v3zm0-5h-4.99c0 1.1-.9 2-2.01 2s-2.01-.9-2.01-2H5V5h14v9z"/></svg>
            </div>
            <p class="font-bold text-sm text-slate-700">No messages in ${escapeHtml(currentFolder)}</p>
            <p class="text-xs text-slate-400 mt-1">Your inbox is clear and up to date.</p>
          </div>
        `;
        renderEmptyConversation();
        return;
      }

      if (currentSelectedThreadIndex >= list.length) {
        currentSelectedThreadIndex = 0;
      }

      let currentGroup = null;
      let itemsHtml = '';

      list.forEach((msg, idx) => {
        const isActive = idx === currentSelectedThreadIndex;
        const isRead = !!msg.isRead;
        const rawSnippet = cleanMimeEmailContent(msg.thread && msg.thread.length > 0 ? (msg.thread[msg.thread.length - 1]?.body || '') : (msg.preview || ''));
        const parsedReply = parseEmailReplyAndQuote(rawSnippet);
        const snippetText = (parsedReply.isReply && parsedReply.replyText) ? parsedReply.replyText : (msg.reply_text || rawSnippet);
        const cleanSnippet = snippetText.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').slice(0, 95).trim();
        const senderDisplayName = msg.senderName || (msg.sender ? msg.sender.split('@')[0] : 'Raccon Mail');
        const initial = (senderDisplayName || 'R').trim().charAt(0).toUpperCase();
        const avatarColor = getSenderAvatarColor(senderDisplayName);
        const dateInfo = getEmailDateInfo(msg.date || msg.created_at || msg.time);
        const threadCount = Array.isArray(msg.thread) ? msg.thread.length : 1;

        // Date Group Heading Divider (e.g. THIS MONTH, TODAY, YESTERDAY, OLDER)
        if (dateInfo.group !== currentGroup) {
          currentGroup = dateInfo.group;
          itemsHtml += `
            <div class="px-4 py-1.5 text-[10px] sm:text-[11px] font-extrabold text-slate-400 tracking-wider uppercase bg-slate-50/70 border-y border-slate-100 select-none my-1">
              ${escapeHtml(currentGroup)}
            </div>
          `;
        }

        itemsHtml += `
          <div onclick="selectThreadItem(${idx})"
               ontouchstart="handleCardTouchStart(event, '${msg.id}', ${idx})"
               ontouchmove="handleCardTouchMove(event)"
               ontouchend="handleCardTouchEnd(event, ${idx})"
               oncontextmenu="handleCardContextMenu(event, '${msg.id}', ${idx})"
               id="thread-item-${idx}"
               class="group cursor-pointer select-none transition border-b border-slate-100/90 relative ${isActive ? 'bg-slate-50/90 border-r-2 border-r-slate-900 shadow-2xs font-semibold' : 'bg-white hover:bg-slate-50/60 border-r-2 border-r-transparent'}">
            
            <div class="flex items-start gap-3 p-3.5">
              <!-- Selection Checkbox -->
              <input type="checkbox"
                     data-msg-id="${msg.id}"
                     onclick="event.stopPropagation(); handleEmailItemCheck(event, '${msg.id}')"
                     class="email-select-checkbox rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-4 h-4 mt-0.5 shrink-0" />

              <!-- Circular Avatar -->
              <div class="w-9 h-9 rounded-full ${avatarColor} flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden mt-0.5 shadow-2xs">
                ${msg.senderAvatar ? `<img src="${escapeHtml(msg.senderAvatar)}" class="w-full h-full object-cover" onerror="this.remove()">` : ''}
                <span>${initial}</span>
              </div>

              <!-- Main Text Block -->
              <div class="flex-1 min-w-0 pr-0.5">
                <!-- Line 1: Sender Name (+ thread count if > 1) + Date on right -->
                <div class="flex items-center justify-between gap-2 mb-0.5">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <span class="text-xs sm:text-[13px] ${isRead ? 'font-semibold text-slate-700' : 'font-extrabold text-slate-900'} truncate">
                      ${escapeHtml(senderDisplayName)}
                    </span>
                    ${threadCount > 1 ? `<span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold shrink-0">${threadCount}</span>` : ''}
                  </div>
                  <span class="text-[11px] text-slate-400 font-medium shrink-0 ml-auto whitespace-nowrap font-mono">
                    ${escapeHtml(dateInfo.display)}
                  </span>
                </div>

                <!-- Line 2: Red Unread Dot (if unread) + Bold Subject -->
                <div class="flex items-center gap-1.5 mb-0.5">
                  ${!isRead ? `<span class="w-2 h-2 rounded-full bg-rose-500 shrink-0 inline-block unread-dot mr-0.5" title="Unread"></span>` : ''}
                  <h4 class="text-xs sm:text-[13px] ${isRead ? 'font-medium text-slate-700' : 'font-bold text-slate-900'} truncate leading-tight">
                    ${escapeHtml(msg.subject || '(no subject)')}
                  </h4>
                </div>

                <!-- Line 3: Snippet Preview (1 line truncated) -->
                <p class="text-xs text-slate-400 truncate leading-snug line-clamp-1">
                  ${escapeHtml(cleanSnippet || 'No additional text')}
                </p>
              </div>
            </div>
          </div>
        `;
      });

      container.innerHTML = itemsHtml;
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
      renderConversationThread(list[currentSelectedThreadIndex] || list[0]);
    }
    window.renderMessageList = renderMessageList;

    function selectThreadItem(index) {
      if (cardLongPressFired) return;
      const list = getCurrentFilteredList();
      if (!list.length) return;
      if (index < 0) index = 0;
      if (index >= list.length) index = list.length - 1;

      const prevActive = document.getElementById('thread-item-' + currentSelectedThreadIndex);
      if (prevActive) {
        prevActive.classList.remove('bg-slate-50/90', 'border-r-slate-900', 'shadow-2xs', 'font-semibold');
        prevActive.classList.add('bg-white', 'border-r-transparent');
      }

      currentSelectedThreadIndex = index;
      const nextActive = document.getElementById('thread-item-' + index);
      if (nextActive) {
        nextActive.classList.remove('bg-white', 'border-r-transparent');
        nextActive.classList.add('bg-slate-50/90', 'border-r-slate-900', 'shadow-2xs', 'font-semibold');
      }

      const selected = list[index];
      if (selected) {
        if (!selected.isRead) {
          selected.isRead = true;
          Object.values(mailDatabase).forEach(arr => {
            if (Array.isArray(arr)) {
              const found = arr.find(m => m.id === selected.id);
              if (found) found.isRead = true;
            }
          });
          if (nextActive) {
            const dot = nextActive.querySelector('.unread-dot');
            if (dot) dot.remove();
          }
          updateBadgeCounts();
          fetch('/api/messages/' + encodeURIComponent(selected.id) + '/read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_read: 1 })
          }).then(() => refreshFolderCounts()).catch(() => {});
        }
        renderConversationThread(selected);
      }

      if (window.innerWidth < 768) {
        const colInbox = document.getElementById('col-inbox');
        const colReader = document.getElementById('col-reader');
        if (colInbox) colInbox.classList.add('hidden');
        if (colReader) {
          colReader.classList.remove('hidden');
          colReader.classList.add('flex');
        }
      }
    }
    window.selectThreadItem = selectThreadItem;

    // =========================================================================
    // RENDER READING PANE & INLINE QUICK REPLY COMPOSER (RIGHT COLUMN)
    // =========================================================================
    function renderConversationThread(msg) {
      const container = document.getElementById('thread-conversation-container');
      if (!container || !msg) return;

      const list = getCurrentFilteredList();
      const counterEl = document.getElementById('reader-counter');
      if (counterEl) {
        counterEl.innerText = `${currentSelectedThreadIndex + 1} of ${list.length}`;
      }

      if (!msg.isRead) {
        msg.isRead = true;
        Object.values(mailDatabase).forEach(arr => {
          if (Array.isArray(arr)) {
            const found = arr.find(m => m.id === msg.id);
            if (found) found.isRead = true;
          }
        });
        const activeCard = document.getElementById('thread-item-' + currentSelectedThreadIndex);
        if (activeCard) {
          const dot = activeCard.querySelector('.unread-dot');
          if (dot) dot.remove();
        }
        updateBadgeCounts();
        fetch('/api/messages/' + encodeURIComponent(msg.id) + '/read', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ is_read: 1 })
        }).then(() => refreshFolderCounts()).catch(() => {});
      }

      const starIcon = document.getElementById('reader-star-icon');
      if (starIcon) {
        starIcon.className = `w-4 h-4 ${msg.isStarred ? 'text-amber-500 fill-amber-500' : 'text-slate-400'}`;
      }

      const senderDisplayName = msg.senderName || (msg.sender ? msg.sender.split('@')[0] : 'Raccon Member');
      const senderEmail = msg.sender || (msg.senderEmail || 'user@racconmail.eu');
      const recipientText = msg.to || (msg.recipient || 'support@racconmail.eu');
      const dateInfo = getEmailDateInfo(msg.date || msg.created_at || msg.time);
      const initial = (senderDisplayName || 'R').trim().charAt(0).toUpperCase();
      const avatarColor = getSenderAvatarColor(senderDisplayName);

      const threadList = (Array.isArray(msg.thread) && msg.thread.length > 0) ? msg.thread : [
        {
          sender: msg.sender || senderDisplayName,
          to: recipientText,
          date: dateInfo.full,
          body: msg.body || msg.body_html || msg.preview || '(No content)',
          avatar: msg.senderAvatar || '/raccoon-logo.svg'
        }
      ];

      const cardsHtml = threadList.map((card, i) => {
        const rawBody = card.body || '';
        const cleanedBody = cleanMimeEmailContent(rawBody);

        return `
          <div class="mb-6 rounded-2xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-7 relative select-text w-full">
            <!-- Sender & Recipient Information Row -->
            <div class="flex items-start justify-between gap-3 pb-4 mb-5 border-b border-slate-100 flex-wrap">
              <div class="flex items-center gap-3.5 min-w-0">
                <!-- Circular Avatar -->
                <div class="w-10 h-10 rounded-full ${avatarColor} flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden shadow-2xs border border-slate-200/80">
                  ${card.avatar && card.avatar !== '/raccoon-logo.svg' ? `<img src="${escapeHtml(card.avatar)}" class="w-full h-full object-cover">` : `<span>${initial}</span>`}
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-bold text-sm sm:text-base text-slate-900 leading-tight">${escapeHtml(card.sender || senderDisplayName)}</h3>
                    <span class="inline-flex items-center text-sky-500" title="Verified"><i data-lucide="badge-check" class="w-4 h-4 text-sky-500"></i></span>
                    <span class="text-xs text-slate-400 font-mono">&lt;${escapeHtml(senderEmail)}&gt;</span>
                  </div>
                  <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                    <span>To: <strong class="text-slate-700 font-semibold">${escapeHtml(card.to || recipientText)}</strong></span>
                  </div>
                </div>
              </div>

              <!-- Date & Relative Time -->
              <div class="text-right shrink-0">
                <div class="text-xs font-semibold text-slate-600">${escapeHtml(dateInfo.full)}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">(${escapeHtml(dateInfo.relative)})</div>
              </div>
            </div>

            <!-- Full Message Body -->
            <div class="text-[15px] text-slate-800 leading-relaxed space-y-4 whitespace-pre-wrap break-words [word-break:break-word] select-text font-normal pt-1">
              ${cleanedBody}
            </div>
          </div>
        `;
      }).join('');

      container.innerHTML = `
        <div class="w-full max-w-4xl mx-auto px-1 sm:px-4 py-2">
          <!-- Prominent Subject Header (Matching Blueprint) -->
          <div class="mb-5 pb-2">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-snug break-words">
              ${escapeHtml(msg.subject || '(no subject)')}
            </h1>
          </div>

          <!-- Thread Message Cards -->
          ${cardsHtml}

          <!-- INLINE DOCKED QUICK REPLY CARD (Docked below message canvas matching Blueprint) -->
          <div class="rounded-2xl border border-slate-200/90 bg-white shadow-xs p-4 sm:p-5 mt-6 mb-8 transition focus-within:border-slate-300 focus-within:shadow-sm">
            <!-- Top Row of Reply Card: Reply Dropdown, To: Recipient Pill, Cc, Bcc, Popout -->
            <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100 text-xs">
              <div class="flex items-center gap-2 min-w-0 flex-wrap">
                <div class="flex items-center gap-1 text-slate-500 font-semibold">
                  <i data-lucide="corner-up-left" class="w-3.5 h-3.5 text-slate-600"></i>
                  <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                </div>
                <span class="text-slate-400 font-medium">To:</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 border border-slate-200/80 text-xs font-semibold text-slate-800 shadow-2xs">
                  <div class="w-4 h-4 rounded-full ${avatarColor} flex items-center justify-center text-[9px] font-bold overflow-hidden">
                    ${msg.senderAvatar ? `<img src="${escapeHtml(msg.senderAvatar)}" class="w-full h-full object-cover">` : `<span>${initial}</span>`}
                  </div>
                  <span id="inline-reply-recipient-name" class="truncate max-w-[140px] sm:max-w-xs">${escapeHtml(senderDisplayName)}</span>
                  <button type="button" onclick="clearInlineReplyRecipient()" class="text-slate-400 hover:text-rose-500 transition cursor-pointer ml-0.5" title="Remove recipient">
                    <i data-lucide="x" class="w-3 h-3"></i>
                  </button>
                </span>
              </div>

              <div class="flex items-center gap-3 shrink-0 text-slate-500 font-semibold text-xs">
                <button type="button" onclick="toggleInlineCc()" class="hover:text-slate-900 transition cursor-pointer">Cc</button>
                <button type="button" onclick="toggleInlineBcc()" class="hover:text-slate-900 transition cursor-pointer">Bcc</button>
                <button type="button" onclick="replyCurrentThread()" class="text-slate-400 hover:text-slate-800 transition cursor-pointer" title="Expand to popup modal">
                  <i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </div>

            <!-- Collapsible CC row -->
            <div id="inline-cc-row" class="hidden flex items-center gap-2 py-2 border-b border-slate-100 text-xs">
              <span class="text-slate-400 font-medium w-8">Cc:</span>
              <input id="inline-cc-input" type="text" placeholder="Add Cc email addresses..." class="flex-1 bg-transparent text-xs text-slate-800 outline-none" />
            </div>

            <!-- Collapsible BCC row -->
            <div id="inline-bcc-row" class="hidden flex items-center gap-2 py-2 border-b border-slate-100 text-xs">
              <span class="text-slate-400 font-medium w-8">Bcc:</span>
              <input id="inline-bcc-input" type="text" placeholder="Add Bcc email addresses..." class="flex-1 bg-transparent text-xs text-slate-800 outline-none" />
            </div>

            <!-- Inline Textarea Editor -->
            <div class="py-3">
              <textarea id="quick-reply-input"
                        rows="4"
                        placeholder="Write your reply..."
                        onkeydown="handleQuickReplyKey(event)"
                        class="w-full bg-transparent text-sm text-slate-800 placeholder-slate-400 outline-none resize-none leading-relaxed min-h-[90px]"></textarea>
            </div>

            <!-- Attached Files container -->
            <div id="inline-attached-files-container" class="flex flex-wrap gap-2 pb-2 empty:hidden"></div>

            <!-- Bottom Actions Toolbar (Matching Blueprint) -->
            <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 flex-wrap">
              <!-- Left Formatting & Attachment Tools -->
              <div class="flex items-center gap-0.5 sm:gap-1 text-slate-500">
                <!-- AI Assist Pill -->
                <button type="button" onclick="triggerInlineAiAssist()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer shadow-2xs mr-1" title="AI Reply Assistant">
                  <i data-lucide="sparkles" class="w-3.5 h-3.5 text-purple-600"></i>
                  <span>AI</span>
                </button>
                <!-- Formatting TT -->
                <button type="button" onclick="toggleInlineFormattingBar()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Formatting options">
                  <i data-lucide="type" class="w-4 h-4"></i>
                </button>
                <!-- Attach File -->
                <button type="button" onclick="document.getElementById('inline-file-picker').click()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Attach files">
                  <i data-lucide="paperclip" class="w-4 h-4"></i>
                </button>
                <input type="file" id="inline-file-picker" class="hidden" multiple onchange="handleInlineFileAttach(this)" />
                <!-- Insert Link -->
                <button type="button" onclick="insertInlineLink()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert link">
                  <i data-lucide="link" class="w-4 h-4"></i>
                </button>
                <!-- Emoji -->
                <button type="button" onclick="insertInlineEmoji()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert emoji">
                  <i data-lucide="smile" class="w-4 h-4"></i>
                </button>
                <!-- Image -->
                <button type="button" onclick="document.getElementById('inline-image-picker').click()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert image">
                  <i data-lucide="image" class="w-4 h-4"></i>
                </button>
                <input type="file" id="inline-image-picker" class="hidden" accept="image/*" onchange="handleInlineImageAttach(this)" />
                <!-- Pen / Signature -->
                <button type="button" onclick="insertInlineSignature()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert signature">
                  <i data-lucide="pen-tool" class="w-4 h-4"></i>
                </button>
                <!-- Lock / Confidential -->
                <button type="button" onclick="toggleInlineConfidential()" id="inline-confidential-btn" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Confidential mode">
                  <i data-lucide="lock" class="w-4 h-4"></i>
                </button>
              </div>

              <!-- Right Action Tools: Discard, More, Send Split Button -->
              <div class="flex items-center gap-1.5 shrink-0">
                <!-- Discard -->
                <button type="button" onclick="clearInlineReply()" class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition cursor-pointer" title="Discard reply">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
                <!-- More -->
                <button type="button" onclick="toggleInlineMoreOptions()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="More options">
                  <i data-lucide="more-vertical" class="w-4 h-4"></i>
                </button>
                <!-- Send Split Button -->
                <div class="inline-flex rounded-xl shadow-xs overflow-hidden">
                  <button type="button" onclick="sendQuickReply()" id="inline-send-btn" class="px-4 py-2 bg-slate-900 hover:bg-black text-white text-xs font-bold transition cursor-pointer flex items-center gap-1.5 active:scale-95">
                    <span>Send</span>
                  </button>
                  <button type="button" onclick="toggleScheduleSendDropdown()" class="px-2 py-2 bg-slate-900 hover:bg-black text-white text-xs font-bold border-l border-slate-700 transition cursor-pointer" title="Schedule send">
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.renderConversationThread = renderConversationThread;

    // =========================================================================
    // TOP NAVIGATION & BULK ACTIONS
    // =========================================================================
    function navigateThread(delta) {
      const q = document.getElementById('global-search')?.value || '';
      const list = getCurrentFilteredList(q);
      if (!list.length) return;
      let nextIdx = currentSelectedThreadIndex + delta;
      if (nextIdx < 0) nextIdx = 0;
      if (nextIdx >= list.length) nextIdx = list.length - 1;
      selectThreadItem(nextIdx);
    }
    window.navigateThread = navigateThread;

    let isReaderFullscreen = false;
    function toggleReaderFullscreen() {
      const colInbox = document.getElementById('col-inbox');
      const sidebar = document.getElementById('sidebar-panel');
      const quickRail = document.getElementById('quick-rail');
      const icon = document.getElementById('reader-fullscreen-icon');
      isReaderFullscreen = !isReaderFullscreen;

      if (isReaderFullscreen) {
        colInbox?.classList.add('hidden');
        sidebar?.classList.add('hidden');
        quickRail?.classList.add('hidden');
        if (icon) icon.setAttribute('data-lucide', 'minimize-2');
      } else {
        colInbox?.classList.remove('hidden');
        sidebar?.classList.remove('hidden');
        quickRail?.classList.remove('hidden');
        if (icon) icon.setAttribute('data-lucide', 'maximize-2');
      }
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.toggleReaderFullscreen = toggleReaderFullscreen;

    function toggleMasterSelect(checkbox) {
      const isChecked = checkbox.checked;
      const boxes = document.querySelectorAll('.email-select-checkbox');
      boxes.forEach(b => b.checked = isChecked);
    }
    window.toggleMasterSelect = toggleMasterSelect;

    function selectFilteredBatch(type) {
      const dropdown = document.getElementById('select-dropdown-menu');
      if (dropdown) dropdown.classList.add('hidden');
      const master = document.getElementById('master-select-checkbox');

      const list = getCurrentFilteredList();
      const checkboxes = document.querySelectorAll('.email-select-checkbox');

      checkboxes.forEach((cb, idx) => {
        const msg = list[idx];
        if (!msg) return;
        if (type === 'all') cb.checked = true;
        else if (type === 'none') cb.checked = false;
        else if (type === 'read') cb.checked = !!msg.isRead;
        else if (type === 'unread') cb.checked = !msg.isRead;
        else if (type === 'starred') cb.checked = !!msg.isStarred;
      });

      if (master) {
        master.checked = type === 'all';
      }
    }
    window.selectFilteredBatch = selectFilteredBatch;

    function toggleSelectDropdown(event) {
      event?.stopPropagation();
      const dropdown = document.getElementById('select-dropdown-menu');
      if (dropdown) dropdown.classList.toggle('hidden');
    }
    window.toggleSelectDropdown = toggleSelectDropdown;

    function toggleDesktopFilterMenu(event) {
      event?.stopPropagation();
      const menu = document.getElementById('desktop-filter-menu');
      if (menu) menu.classList.toggle('hidden');
    }
    window.toggleDesktopFilterMenu = toggleDesktopFilterMenu;

    function handleEmailItemCheck(event, id) {
      // Checked state maintained
    }
    window.handleEmailItemCheck = handleEmailItemCheck;

    async function refreshEmailListAction() {
      const icon = document.getElementById('list-refresh-icon');
      if (icon) icon.classList.add('animate-spin');
      try {
        await fetchFolderMessages(currentFolder);
        await refreshFolderCounts();
        if (typeof showToast === 'function') showToast('Inbox updated', 'success');
      } catch (_) {
      } finally {
        setTimeout(() => {
          if (icon) icon.classList.remove('animate-spin');
        }, 600);
      }
    }
    window.refreshEmailListAction = refreshEmailListAction;

    // =========================================================================
    // INLINE DOCKED REPLY CARD ACTIONS
    // =========================================================================
    function triggerInlineAiAssist() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const list = getCurrentFilteredList();
      const msg = list[currentSelectedThreadIndex];
      const senderName = msg?.senderName || 'there';
      const promptReplies = [
        `Hi ${senderName},\n\nThank you for reaching out. I've received your message and will review this shortly.\n\nBest regards,`,
        `Hi ${senderName},\n\nThanks for the update! Everything looks great on my end.\n\nBest regards,`,
        `Hi ${senderName},\n\nCould you please provide a few more details regarding this? Thanks in advance!\n\nBest regards,`
      ];
      const chosen = promptReplies[Math.floor(Math.random() * promptReplies.length)];
      inp.value = chosen;
      inp.focus();
      if (typeof showToast === 'function') showToast('AI response suggestion inserted', 'info');
    }
    window.triggerInlineAiAssist = triggerInlineAiAssist;

    function toggleInlineCc() {
      const row = document.getElementById('inline-cc-row');
      if (row) {
        row.classList.toggle('hidden');
        if (!row.classList.contains('hidden')) document.getElementById('inline-cc-input')?.focus();
      }
    }
    window.toggleInlineCc = toggleInlineCc;

    function toggleInlineBcc() {
      const row = document.getElementById('inline-bcc-row');
      if (row) {
        row.classList.toggle('hidden');
        if (!row.classList.contains('hidden')) document.getElementById('inline-bcc-input')?.focus();
      }
    }
    window.toggleInlineBcc = toggleInlineBcc;

    function clearInlineReply() {
      const inp = document.getElementById('quick-reply-input');
      if (inp) inp.value = '';
      const attachCont = document.getElementById('inline-attached-files-container');
      if (attachCont) attachCont.innerHTML = '';
      document.getElementById('inline-cc-row')?.classList.add('hidden');
      document.getElementById('inline-bcc-row')?.classList.add('hidden');
      if (typeof showToast === 'function') showToast('Reply discarded', 'info');
    }
    window.clearInlineReply = clearInlineReply;

    function clearInlineReplyRecipient() {
      const nameEl = document.getElementById('inline-reply-recipient-name');
      const newRecip = prompt('Enter reply recipient email:', '');
      if (newRecip && newRecip.includes('@')) {
        if (nameEl) nameEl.innerText = newRecip;
      }
    }
    window.clearInlineReplyRecipient = clearInlineReplyRecipient;

    function insertInlineSignature() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const userSig = `\n\n--\n<?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?>\n<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>`;
      inp.value = (inp.value.trim() + userSig).trim();
      inp.focus();
    }
    window.insertInlineSignature = insertInlineSignature;

    function insertInlineEmoji() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const emojis = ['👍', '😊', '🙏', '🚀', '✅', '👋', '🎉'];
      const em = emojis[Math.floor(Math.random() * emojis.length)];
      inp.value += ' ' + em;
      inp.focus();
    }
    window.insertInlineEmoji = insertInlineEmoji;

    function insertInlineLink() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const url = prompt('Enter URL (e.g. https://example.com):', 'https://');
      if (url) {
        inp.value += ' ' + url;
        inp.focus();
      }
    }
    window.insertInlineLink = insertInlineLink;

    let isInlineConfidential = false;
    function toggleInlineConfidential() {
      isInlineConfidential = !isInlineConfidential;
      const btn = document.getElementById('inline-confidential-btn');
      if (btn) {
        if (isInlineConfidential) {
          btn.classList.add('bg-amber-100', 'text-amber-700');
          if (typeof showToast === 'function') showToast('Confidential mode enabled', 'info');
        } else {
          btn.classList.remove('bg-amber-100', 'text-amber-700');
          if (typeof showToast === 'function') showToast('Confidential mode disabled', 'info');
        }
      }
    }
    window.toggleInlineConfidential = toggleInlineConfidential;

    function handleInlineFileAttach(input) {
      const container = document.getElementById('inline-attached-files-container');
      if (!container || !input.files.length) return;
      Array.from(input.files).forEach(file => {
        const pill = document.createElement('div');
        pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs text-slate-700 font-medium';
        pill.innerHTML = `
          <i data-lucide="paperclip" class="w-3 h-3 text-slate-500"></i>
          <span class="truncate max-w-[150px]">${escapeHtml(file.name)}</span>
          <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500 cursor-pointer ml-1">✕</button>
        `;
        container.appendChild(pill);
      });
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.handleInlineFileAttach = handleInlineFileAttach;

    function handleInlineImageAttach(input) {
      const container = document.getElementById('inline-attached-files-container');
      if (!container || !input.files.length) return;
      Array.from(input.files).forEach(file => {
        const pill = document.createElement('div');
        pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs text-slate-700 font-medium';
        pill.innerHTML = `
          <i data-lucide="image" class="w-3 h-3 text-slate-500"></i>
          <span class="truncate max-w-[150px]">${escapeHtml(file.name)}</span>
          <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500 cursor-pointer ml-1">✕</button>
        `;
        container.appendChild(pill);
      });
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.handleInlineImageAttach = handleInlineImageAttach;

    function toggleScheduleSendDropdown() {
      const scheduleTime = prompt('Schedule Send: enter time (e.g. "Tomorrow 8:00 AM" or "Monday 9:00 AM"):', 'Tomorrow 8:00 AM');
      if (scheduleTime) {
        if (typeof showToast === 'function') showToast(`Email scheduled for ${scheduleTime}`, 'success');
        sendQuickReply();
      }
    }
    window.toggleScheduleSendDropdown = toggleScheduleSendDropdown;

    function handleQuickReplyKey(event) {
      if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        sendQuickReply();
      }
    }
    window.handleQuickReplyKey = handleQuickReplyKey;

    function toggleInlineFormattingBar() {
      if (typeof showToast === 'function') showToast('Markdown & rich formatting enabled', 'info');
    }
    window.toggleInlineFormattingBar = toggleInlineFormattingBar;

    function toggleInlineMoreOptions() {
      if (typeof showToast === 'function') showToast('Plain text, request read receipt, set priority', 'info');
    }
    window.toggleInlineMoreOptions = toggleInlineMoreOptions;

    // Global listener for closing dropdowns on outside click
    document.addEventListener('click', () => {
      document.getElementById('select-dropdown-menu')?.classList.add('hidden');
      document.getElementById('desktop-filter-menu')?.classList.add('hidden');
    });

function renderEmptyConversation() {
      const container = document.getElementById('thread-conversation-container');
      if (container) {
        container.innerHTML = `
          <div class="flex flex-col items-center justify-center h-full p-8 text-center text-slate-400">
            <img src="/raccoon-logo.svg" class="h-20 w-20 object-contain opacity-60 mb-3" />
            <p class="font-bold text-base text-slate-700">No Conversation Selected</p>
            <p class="text-xs text-slate-400 mt-1">Select an email to read or click Compose to start a new thread.</p>
          </div>
        `;
      }
    }

    // Fetch real messages for a folder from SQLite API
    async function fetchFolderMessages(folderName) {
      const folderKey = folderName.toLowerCase();
      try {
        const res = await fetch('/api/messages?folder=' + encodeURIComponent(folderKey));
        if (res.ok) {
          const data = await res.json();
          const msgs = data.messages || [];
          mailDatabase[folderName] = msgs.map(m => ({
            id: m.id,
            sender: m.sender_email,
            senderName: m.sender_name || m.sender_email,
            time: (m.created_at || '').slice(11, 16) || 'Today',
            subject: m.subject || '(no subject)',
            isStarred: !!m.is_starred,
            isRead: !!m.is_read,
            avatar: '/raccoon-logo.svg',
            body_html: m.body_html || '',
            thread: [
              {
                sender: m.sender_email,
                to: m.recipient_email || '<?php echo htmlspecialchars($currentUser['email']); ?>',
                date: m.created_at || 'Recently',
                avatar: '/raccoon-logo.svg',
                body: m.body || m.body_html || 'No content.'
              }
            ]
          }));
          if (currentFolder === folderName) {
            renderMessageList();
            if (mailDatabase[folderName].length > 0) {
              renderConversationThread(mailDatabase[folderName][currentSelectedThreadIndex] || mailDatabase[folderName][0]);
            } else {
              renderEmptyConversation();
            }
          }
        }
      } catch (err) {
        console.error('fetchFolderMessages error:', err);
      }
    }

    // Safe DOM badge updater (hides when count is 0)
    function setBadgeValue(elementId, count) {
      const el = document.getElementById(elementId);
      if (!el) return;
      el.innerText = count;
      if (count > 0) {
        el.classList.remove('hidden');
      } else {
        el.classList.add('hidden');
      }
    }

    // Update reader read/unread button in toolbar
    function updateReaderReadButton(isRead) {
      const btn = document.getElementById('reader-toggle-read-btn');
      const icon = document.getElementById('reader-mail-icon');
      if (!btn) return;
      if (isRead) {
        btn.title = "Mark as unread";
        btn.setAttribute('aria-label', 'Mark as unread');
        if (icon) {
          icon.setAttribute('data-lucide', 'mail');
          icon.className = 'h-4 w-4 text-slate-500 hover:text-slate-800';
        }
      } else {
        btn.title = "Mark as read";
        btn.setAttribute('aria-label', 'Mark as read');
        if (icon) {
          icon.setAttribute('data-lucide', 'mail-open');
          icon.className = 'h-4 w-4 text-[#E06748]';
        }
      }
      try { lucide.createIcons(); } catch (_) {}
    }

    // Toggle Read/Unread state for current selected thread
    function toggleCurrentThreadReadState() {
      const list = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                   (currentFolder === 'Updates') ? (mailDatabase['Updates'] || []) :
                   (mailDatabase[currentFolder] || mailDatabase['Inbox']);
      const msg = list[currentSelectedThreadIndex];
      if (!msg) return;

      const newReadState = !msg.isRead;
      msg.isRead = newReadState;

      Object.values(mailDatabase).forEach(arr => {
        if (Array.isArray(arr)) {
          const found = arr.find(m => m.id === msg.id);
          if (found) found.isRead = newReadState;
        }
      });

      renderMessageList();
      updateBadgeCounts();
      updateReaderReadButton(newReadState);

      fetch('/api/messages/' + encodeURIComponent(msg.id) + '/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_read: newReadState ? 1 : 0 })
      }).then(() => refreshFolderCounts()).catch(() => {});

      showToast(newReadState ? 'Marked as read' : 'Marked as unread');
    }

    // Refresh real counts from SQLite API
    async function refreshFolderCounts() {
      try {
        const res = await fetch('/api/folders');
        if (res.ok) {
          const data = await res.json();
          if (data && data.standard) {
            const std = data.standard;
            const inboxTotal = std.inbox?.total || 0;
            const inboxUnread = std.inbox?.unread || 0;

            // 1. Sidebar Inbox badge: shows how many emails you got in Inbox (ALWAYS VISIBLE!)
            const bInbox = document.getElementById('badge-inbox');
            if (bInbox) {
              bInbox.innerText = inboxTotal;
              if (inboxTotal > 0) bInbox.classList.remove('hidden');
              else bInbox.classList.add('hidden');
            }

            // 2. Middle header Coral badge: shows how many emails you got in current folder (ALWAYS VISIBLE!)
            const bCoral = document.getElementById('coral-inbox-badge');
            if (bCoral) {
              let fCount = inboxTotal;
              if (currentFolder === 'Sent') fCount = std.sent?.total || 0;
              else if (currentFolder === 'Trash') fCount = std.trash?.total || 0;
              else if (currentFolder === 'Archive') fCount = std.archive?.total || 0;
              else if (currentFolder === 'Spam') fCount = std.spam?.total || 0;
              else if (currentFolder === 'Starred') fCount = std.favorites?.total || 0;
              else if (currentFolder === 'Drafts') fCount = std.drafts?.total || 0;
              else if (currentFolder === 'Unread') fCount = std.unread?.total || inboxUnread;
              bCoral.innerText = fCount;
              bCoral.classList.remove('hidden'); // ALWAYS VISIBLE! Shows how many emails you got!
            }

            // 3. Middle header Unread pill: shows unread count if any (hides when 0)
            const unreadPill = document.getElementById('inbox-unread-pill');
            if (unreadPill) {
              const curUnread = (currentFolder === 'Inbox' || currentFolder === 'Unread') ? inboxUnread : 0;
              if (curUnread > 0) {
                unreadPill.innerText = curUnread + ' unread';
                unreadPill.classList.remove('hidden');
              } else {
                unreadPill.classList.add('hidden');
              }
            }

            // 4. Mark all read button
            const markAllBtn = document.getElementById('mark-all-read-btn');
            if (markAllBtn) {
              const curUnread = (currentFolder === 'Inbox' || currentFolder === 'Unread') ? inboxUnread : 0;
              if (curUnread > 0) markAllBtn.classList.remove('hidden');
              else markAllBtn.classList.add('hidden');
            }

            // 5. Sidebar Unread: shows unread count (hides when 0)
            setBadgeValue('badge-unread', std.unread?.total || inboxUnread || 0);

            // 6. Mobile bottom bar badge: shows how many emails you got
            const bMobile = document.getElementById('mobile-bottom-inbox-badge');
            if (bMobile) {
              bMobile.innerText = inboxTotal;
              if (inboxTotal > 0) bMobile.classList.remove('hidden');
              else bMobile.classList.add('hidden');
            }

            // 7. Other folders
            setBadgeValue('badge-starred', std.favorites?.total || 0);
            setBadgeValue('badge-drafts', std.drafts?.total || 0);
            setBadgeValue('badge-sent', std.sent?.total || 0);
            setBadgeValue('badge-archive', std.archive?.total || 0);
            setBadgeValue('badge-spam', std.spam?.total || 0);
            setBadgeValue('badge-trash', std.trash?.total || 0);
          }
        }
      } catch (e) {}
    }

    function updateBadgeCounts() {
      const inboxList = mailDatabase['Inbox'] || [];
      const totalInbox = inboxList.length;
      const unreadInbox = inboxList.filter(m => !m.isRead).length;

      // 1. Sidebar Inbox badge: shows how many emails you got in Inbox (ALWAYS VISIBLE!)
      const bInbox = document.getElementById('badge-inbox');
      if (bInbox) {
        bInbox.innerText = totalInbox;
        if (totalInbox > 0) bInbox.classList.remove('hidden');
        else bInbox.classList.add('hidden');
      }

      // 2. Middle header Coral badge: shows how many emails you got in current folder (ALWAYS VISIBLE!)
      const bCoral = document.getElementById('coral-inbox-badge');
      if (bCoral) {
        const curList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                        (mailDatabase[currentFolder] || []);
        bCoral.innerText = curList.length;
        bCoral.classList.remove('hidden'); // ALWAYS VISIBLE! Shows how many emails you got!
      }

      // 3. Middle header Unread pill: shows unread count if any (hides when 0)
      const curList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                      (mailDatabase[currentFolder] || []);
      const curUnread = curList.filter(m => !m.isRead).length;
      const unreadPill = document.getElementById('inbox-unread-pill');
      if (unreadPill) {
        if (curUnread > 0) {
          unreadPill.innerText = curUnread + ' unread';
          unreadPill.classList.remove('hidden');
        } else {
          unreadPill.classList.add('hidden');
        }
      }

      // 4. Mark all read button
      const markAllBtn = document.getElementById('mark-all-read-btn');
      if (markAllBtn) {
        if (curUnread > 0) markAllBtn.classList.remove('hidden');
        else markAllBtn.classList.add('hidden');
      }

      // 5. Sidebar Unread: shows unread count (hides when 0)
      setBadgeValue('badge-unread', unreadInbox);

      // 6. Mobile bottom bar badge: shows how many emails you got
      const bMobile = document.getElementById('mobile-bottom-inbox-badge');
      if (bMobile) {
        bMobile.innerText = totalInbox;
        if (totalInbox > 0) bMobile.classList.remove('hidden');
        else bMobile.classList.add('hidden');
      }

      // 7. Other folders show their counts
      const trashCount = (mailDatabase['Trash'] || []).length;
      setBadgeValue('badge-trash', trashCount);

      const totalStarred = [].concat(...Object.values(mailDatabase)).filter(m => m.isStarred).length;
      setBadgeValue('badge-starred', totalStarred);

      const draftsCount = (mailDatabase['Drafts'] || []).length;
      setBadgeValue('badge-drafts', draftsCount);

      const sentCount = (mailDatabase['Sent'] || []).length;
      setBadgeValue('badge-sent', sentCount);

      const archiveCount = (mailDatabase['Archive'] || []).length;
      setBadgeValue('badge-archive', archiveCount);

      const spamCount = (mailDatabase['Spam'] || []).length;
      setBadgeValue('badge-spam', spamCount);
    }

    // Delete single message permanently or move to trash
    // Delete single message permanently from trash or move to trash
    async function deleteMessageById(msgId) {
      if (!msgId) return;

      // 1. Remove permanently from all local in-memory folder lists
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          const idx = mailDatabase[f].findIndex(m => m.id === msgId);
          if (idx !== -1) {
            mailDatabase[f].splice(idx, 1);
          }
        }
      }

      // 2. Add to permanently deleted set & persist in localStorage so sync NEVER restores it
      permanentlyDeletedIds.add(msgId);
      try {
        localStorage.setItem('raccon_deleted_ids', JSON.stringify(Array.from(permanentlyDeletedIds)));
      } catch (_) {}

      // 3. Immediately re-render list and update badges
      currentSelectedThreadIndex = 0;
      renderMessageList(document.getElementById('global-search')?.value || '');
      updateBadgeCounts();

      // 4. Send permanent delete request to backend API
      try {
        const tok = typeof getAuthToken === 'function' ? getAuthToken() : '';
        const authHeader = tok ? { 'Authorization': 'Bearer ' + tok } : {};
        await fetch('/api/messages/' + encodeURIComponent(msgId) + '?permanent=1', {
          method: 'DELETE',
          credentials: 'include',
          headers: authHeader
        });
      } catch (err) {
        console.error('Delete error:', err);
      }

      refreshFolderCounts();
    }

    async function trashCurrentThread() {
      const list = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                   (currentFolder === 'Updates') ? (mailDatabase['Updates'] || []) :
                   (mailDatabase[currentFolder] || mailDatabase['Inbox']);
      const msg = list[currentSelectedThreadIndex];
      if (msg) {
        if (!confirm('Are you sure you want to permanently delete this email?')) return;
        await deleteMessageById(msg.id);
        if (typeof showToast === 'function') showToast('Email permanently deleted 🗑️', 'info');
        if (window.innerWidth < 768) {
          backToMobileInbox();
        }
      }
    }


    // Mobile Hold-to-Delete Touch Engine & Toast Notification
    let cardLongPressTimer = null;
    let cardTouchStartX = 0;
    let cardTouchStartY = 0;
    let cardTouchMoved = false;
    let cardLongPressFired = false;
    let activeActionMsgId = null;
    let activeActionMsgIndex = -1;

    function handleCardTouchStart(e, msgId, idx) {
      if (e.touches && e.touches.length > 1) return;
      const touch = e.touches ? e.touches[0] : e;
      cardTouchStartX = touch.clientX;
      cardTouchStartY = touch.clientY;
      cardTouchMoved = false;
      cardLongPressFired = false;
      activeActionMsgId = msgId;
      activeActionMsgIndex = idx;

      clearTimeout(cardLongPressTimer);
      cardLongPressTimer = setTimeout(() => {
        if (!cardTouchMoved) {
          cardLongPressFired = true;
          if (navigator.vibrate) {
            try { navigator.vibrate(60); } catch (_) {}
          }
          openMobileActionSheet(msgId, idx);
        }
      }, 450); // 450ms smooth hold
    }

    function handleCardTouchMove(e) {
      if (!cardTouchMoved && e.touches && e.touches[0]) {
        const dx = Math.abs(e.touches[0].clientX - cardTouchStartX);
        const dy = Math.abs(e.touches[0].clientY - cardTouchStartY);
        if (dx > 10 || dy > 10) {
          cardTouchMoved = true;
          clearTimeout(cardLongPressTimer);
        }
      }
    }

    function handleCardTouchEnd(e, idx) {
      clearTimeout(cardLongPressTimer);
      if (cardLongPressFired) {
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
        setTimeout(() => { cardLongPressFired = false; }, 200);
        return;
      }
      if (!cardTouchMoved && typeof idx === 'number') {
        // Immediate 1-tap open on smartphone (zero double-click needed!)
        selectThreadItem(idx);
      }
    }

    function handleCardContextMenu(e, msgId, idx) {
      e.preventDefault();
      openMobileActionSheet(msgId, idx);
    }

    function openMobileActionSheet(msgId, idx) {
      activeActionMsgId = msgId;
      activeActionMsgIndex = idx;

      let msg = null;
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          msg = mailDatabase[f].find(m => m.id === msgId);
          if (msg) break;
        }
      }

      const isTrash = (currentFolder.toLowerCase() === 'trash' || (msg && (msg.is_trashed === 1 || msg.computed_folder === 'Trash')));

      const sheetSub = document.getElementById('sheet-msg-subject');
      const sheetSender = document.getElementById('sheet-msg-sender');
      const sheetFolder = document.getElementById('sheet-folder-name');
      const sheetDelLabel = document.getElementById('sheet-delete-label');
      const sheetDelSub = document.getElementById('sheet-delete-sub');
      const sheetStarLabel = document.getElementById('sheet-star-label');
      const sheetReadLabel = document.getElementById('sheet-read-label');

      if (sheetSub) sheetSub.innerText = msg ? (msg.subject || '(no subject)') : 'Selected Email';
      if (sheetSender) sheetSender.innerText = msg ? (msg.senderName || msg.sender || '') : '';
      if (sheetFolder) sheetFolder.innerText = isTrash ? 'Trash Folder Options' : 'Email Options';

      if (sheetDelLabel) {
        sheetDelLabel.innerText = isTrash ? 'Delete Permanently' : 'Delete / Move to Trash';
      }
      if (sheetDelSub) {
        sheetDelSub.innerText = isTrash ? 'Purge permanently from Trash' : 'Move to Trash folder';
      }
      if (sheetStarLabel) {
        sheetStarLabel.innerText = (msg && msg.isStarred) ? 'Unstar Message' : 'Star Message';
      }
      if (sheetReadLabel) {
        sheetReadLabel.innerText = (msg && msg.isRead) ? 'Mark as Unread' : 'Mark as Read';
      }

      const backdrop = document.getElementById('mobile-action-sheet-backdrop');
      const panel = document.getElementById('mobile-action-sheet-panel');
      if (backdrop && panel) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => {
          backdrop.classList.remove('opacity-0');
          panel.classList.remove('translate-y-full');
          panel.classList.remove('sm:translate-y-4');
        });
        try { lucide.createIcons(); } catch (_) {}
      }
    }

    function closeMobileActionSheet() {
      const backdrop = document.getElementById('mobile-action-sheet-backdrop');
      const panel = document.getElementById('mobile-action-sheet-panel');
      if (backdrop && panel) {
        backdrop.classList.add('opacity-0');
        panel.classList.add('translate-y-full');
        setTimeout(() => {
          backdrop.classList.add('hidden');
          activeActionMsgId = null;
          activeActionMsgIndex = -1;
        }, 250);
      }
    }

    async function handleSheetDelete() {
      const msgId = activeActionMsgId;
      closeMobileActionSheet();
      if (!msgId) return;
      const isTrash = (currentFolder.toLowerCase() === 'trash');
      if (isTrash) {
        if (!confirm('Permanently delete this email from Trash?')) return;
      }
      await deleteMessageById(msgId);
      showToast(isTrash ? 'Permanently deleted from Trash 🗑️' : 'Moved to Trash 🗑️');
    }

    async function handleSheetStar() {
      const msgId = activeActionMsgId;
      closeMobileActionSheet();
      if (!msgId) return;
      await toggleStarItem(msgId);
    }

    async function handleSheetToggleRead() {
      const msgId = activeActionMsgId;
      closeMobileActionSheet();
      if (!msgId) return;
      let target = null;
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          target = mailDatabase[f].find(m => m.id === msgId);
          if (target) break;
        }
      }
      if (target) {
        target.isRead = !target.isRead;
        renderMessageList(document.getElementById('global-search')?.value || '');
        try {
          await fetch('/api/messages/' + encodeURIComponent(msgId) + '/read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_read: target.isRead ? 1 : 0 })
          });
        } catch (_) {}
        refreshFolderCounts();
      }
    }

    async function triggerCardDelete(msgId, idx) {
      const isTrash = (currentFolder.toLowerCase() === 'trash');
      if (isTrash) {
        if (!confirm('Permanently delete this email from Trash?')) return;
      }
      await deleteMessageById(msgId);
      showToast(isTrash ? 'Permanently deleted from Trash 🗑️' : 'Moved to Trash 🗑️');
    }

    function showToast(message) {
      let toast = document.getElementById('inbox-live-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'inbox-live-toast';
        toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-full bg-slate-900/90 text-white text-xs font-semibold shadow-lg backdrop-blur-md transition-all duration-300 pointer-events-none opacity-0 translate-y-3 flex items-center gap-2';
        document.body.appendChild(toast);
      }
      toast.innerHTML = '<span>' + escapeHtml(message) + '</span>';
      toast.classList.remove('hidden', 'opacity-0', 'translate-y-3');
      toast.classList.add('opacity-100', 'translate-y-0');

      clearTimeout(window._toastTimeout);
      window._toastTimeout = setTimeout(() => {
        toast.classList.remove('opacity-100', 'translate-y-0');
        toast.classList.add('opacity-0', 'translate-y-3');
      }, 2500);
    }
    window.showToast = showToast;

    // Toggle Star
    async function toggleStarItem(msgId) {
      let targetMsg = null;
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          const item = mailDatabase[f].find(m => m.id === msgId);
          if (item) {
            item.isStarred = !item.isStarred;
            targetMsg = item;
            break;
          }
        }
      }
      renderMessageList(document.getElementById('global-search')?.value || '');
      if (targetMsg) {
        try {
          await fetch('/api/messages/' + encodeURIComponent(msgId) + '/star', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_starred: targetMsg.isStarred ? 1 : 0 })
          });
        } catch (e) {}
        refreshFolderCounts();
      }
    }

    function starCurrentThread() {
      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'];
      const msg = list[currentSelectedThreadIndex];
      if (msg) toggleStarItem(msg.id);
    }

    // Reply Current Thread
    function replyCurrentThread() {
      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'];
      const msg = list[currentSelectedThreadIndex];
      if (msg) {
        openComposeCard();
        document.getElementById('compose-to').value = msg.sender;
        document.getElementById('compose-subject').value = 'Re: ' + msg.subject;
        document.getElementById('compose-body').value = `\n\n--- Original Message ---\nFrom: ${msg.sender}\n${msg.thread[0]?.body || ''}`;
        document.getElementById('compose-body').focus();
        document.getElementById('compose-body').setSelectionRange(0, 0);
      }
    }

    // Move to Trash
    async function trashCurrentThread() {
      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'];
      const msg = list[currentSelectedThreadIndex];
      if (msg) {
        await deleteMessageById(msg.id);
      }
    }

    // Archive Current Thread
    async function archiveCurrentThread() {
      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'];
      const msg = list[currentSelectedThreadIndex];
      if (!msg) return;

      const msgId = msg.id;
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          const idx = mailDatabase[f].findIndex(m => m.id === msgId);
          if (idx !== -1) mailDatabase[f].splice(idx, 1);
        }
      }
      if (!mailDatabase['Archive']) mailDatabase['Archive'] = [];
      mailDatabase['Archive'].unshift(msg);

      currentSelectedThreadIndex = 0;
      renderMessageList(document.getElementById('global-search')?.value || '');
      updateBadgeCounts();

      try {
        await fetch('/api/messages/' + encodeURIComponent(msgId) + '/archive', { method: 'POST' });
      } catch (e) {
        console.error('Archive error:', e);
      }
      refreshFolderCounts();
    }

    // Empty Trash
    async function emptyTrashAction() {
      if (!confirm('Permanently delete all messages in Trash?')) return;
      (mailDatabase['Trash'] || []).forEach(m => permanentlyDeletedIds.add(m.id));
      try { localStorage.setItem('raccon_deleted_ids', JSON.stringify(Array.from(permanentlyDeletedIds))); } catch (_) {}
      mailDatabase['Trash'] = [];
      if (currentFolder === 'Trash') {
        renderMessageList();
        renderEmptyConversation();
      }
      updateBadgeCounts();

      try {
        await fetch('/api/messages/empty-folder', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ folder: 'trash' })
        });
      } catch (e) {
        console.error('Empty trash error:', e);
      }
      refreshFolderCounts();
    }

    // Mark All Read
    async function markAllAsReadAction() {
      const folderKey = currentFolder.toLowerCase();
      (mailDatabase[currentFolder] || []).forEach(m => m.isRead = true);
      renderMessageList();
      updateBadgeCounts();

      try {
        await fetch('/api/messages/batch', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'mark_all_read', folder: folderKey })
        });
      } catch (e) {}
      refreshFolderCounts();
    }

    // Compose Card Handlers
    function openComposeCard(isReply = false) {
      if (window.innerWidth < 768) {
        document.getElementById('sidebar-panel')?.classList.add('-translate-x-full');
        document.getElementById('mobile-sidebar-backdrop')?.classList.add('hidden');
      }
      const card = document.getElementById('docked-compose-card');
      if (card) {
        card.classList.remove('hidden');
        document.getElementById('compose-body-container')?.classList.remove('hidden');
        try { lucide.createIcons(); } catch (_) {}
        if (!isReply) {
          const toEl = document.getElementById('compose-to');
          const subEl = document.getElementById('compose-subject');
          const bodyEl = document.getElementById('compose-body');
          if (toEl) toEl.value = '';
          if (subEl) subEl.value = '';
          if (bodyEl) bodyEl.value = '';
          setTimeout(() => {
            toEl?.focus();
          }, 80);
        }
      }
    }

    function closeComposeCard() {
      document.getElementById('docked-compose-card')?.classList.add('hidden');
    }

    function minimizeComposeCard() {
      const body = document.getElementById('compose-body-container');
      body?.classList.toggle('hidden');
    }

    function composeTo(email) {
      openComposeCard();
      document.getElementById('compose-to').value = email;
    }

    // Send Email (Rock-Solid API Delivery with Real-Time Database Sync)
    async function sendEmailNow() {
      const btn = document.getElementById('btn-send-email');
      const to = document.getElementById('compose-to')?.value.trim();
      const subject = document.getElementById('compose-subject')?.value.trim() || '(no subject)';
      const body = document.getElementById('compose-body')?.value.trim();

      if (!to || !body) {
        alert('Please fill in both the recipient (To) and body text.');
        return;
      }

      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
      }

      try {
        const res = await fetch('/api/messages/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ to: to, subject: subject, body: body })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || 'Failed to dispatch email');
        }

        playRaccoonSound('happy');
        showRaccoonSuccessModal();
        closeComposeCard();
        await refreshFolderCounts();
        await fetchFolderMessages(currentFolder);
      } catch (err) {
        alert('Could not send email: ' + (err.message || err));
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i data-lucide="send" class="h-3.5 w-3.5 text-white"></i><span>Send</span>';
          if (window.lucide) lucide.createIcons();
        }
      }
    }

    async function sendQuickReply() {
      const inp = document.getElementById('quick-reply-input');
      const val = inp?.value.trim();
      if (!val) return;

      const list = getCurrentFilteredList();
      const msg = list[currentSelectedThreadIndex];
      if (!msg) return;

      const toEmail = msg.sender || msg.sender_email;
      const origSubj = msg.subject || '(no subject)';
      const replySubj = origSubj.toLowerCase().startsWith('re:') ? origSubj : 'Re: ' + origSubj;
      const ccVal = document.getElementById('inline-cc-input')?.value.trim() || undefined;
      const bccVal = document.getElementById('inline-bcc-input')?.value.trim() || undefined;

      inp.disabled = true;
      const sendBtn = document.getElementById('inline-send-btn');
      if (sendBtn) sendBtn.disabled = true;

      try {
        const res = await fetch('/api/messages/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            to: toEmail,
            cc: ccVal,
            bcc: bccVal,
            subject: replySubj,
            body: val,
            in_reply_to: msg.id,
            parent_id: msg.id
          })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || 'Failed to dispatch reply');
        }

        if (!Array.isArray(msg.thread)) msg.thread = [];
        msg.thread.push({
          sender: '<?php echo htmlspecialchars($currentUser['email']); ?>',
          to: toEmail,
          date: 'Just now',
          avatar: '<?php echo htmlspecialchars($currentUser['avatar']); ?>',
          body: val
        });
        inp.value = '';
        const ccRow = document.getElementById('inline-cc-row');
        const bccRow = document.getElementById('inline-bcc-row');
        if (ccRow) ccRow.classList.add('hidden');
        if (bccRow) bccRow.classList.add('hidden');

        if (typeof playRaccoonSound === 'function') playRaccoonSound('happy');
        if (typeof showRaccoonSuccessModal === 'function') showRaccoonSuccessModal();
        if (typeof showToast === 'function') showToast('Reply sent successfully', 'success');
        renderConversationThread(msg);
        refreshFolderCounts();
        fetchFolderMessages(currentFolder);
      } catch (err) {
        alert('Could not send reply: ' + (err.message || err));
      } finally {
        inp.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
        inp.focus();
      }
    }

    
    // Mobile Search Sheet Functions
    function openMobileSearchSheet() {
      const sheet = document.getElementById('mobile-search-sheet');
      const backdrop = document.getElementById('mobile-search-backdrop');
      if (!sheet || !backdrop) return;
      backdrop.classList.remove('hidden');
      sheet.classList.remove('hidden');
      setTimeout(() => {
        sheet.classList.remove('translate-y-full');
        sheet.classList.add('translate-y-0');
        const inp = document.getElementById('mobile-search-input');
        if (inp) {
          inp.focus();
          handleMobileSearchInput(inp.value || '');
        }
      }, 20);
      if (window.lucide) lucide.createIcons();
    }

    function closeMobileSearchSheet() {
      const sheet = document.getElementById('mobile-search-sheet');
      const backdrop = document.getElementById('mobile-search-backdrop');
      if (!sheet || !backdrop) return;
      sheet.classList.remove('translate-y-0');
      sheet.classList.add('translate-y-full');
      setTimeout(() => {
        sheet.classList.add('hidden');
        backdrop.classList.add('hidden');
      }, 300);
    }

    function handleMobileSearchInput(val) {
      const clearBtn = document.getElementById('mobile-search-clear-btn');
      if (val && val.trim().length > 0) {
        clearBtn?.classList.remove('hidden');
      } else {
        clearBtn?.classList.add('hidden');
      }

      // Synchronize with desktop search input
      const deskInp = document.getElementById('global-search');
      if (deskInp) deskInp.value = val;

      renderMobileSearchSuggestions(val);
      renderMessageList(val);
    }

    function clearMobileSearch() {
      const inp = document.getElementById('mobile-search-input');
      if (inp) {
        inp.value = '';
        inp.focus();
      }
      handleMobileSearchInput('');
    }

    function applySearchFilter(filterQuery) {
      const inp = document.getElementById('mobile-search-input');
      if (inp) inp.value = filterQuery;
      handleMobileSearchInput(filterQuery);
    }

    function renderMobileSearchSuggestions(query = '') {
      const listEl = document.getElementById('mobile-search-suggestions-list');
      const countEl = document.getElementById('mobile-search-results-count');
      const titleEl = document.getElementById('mobile-search-results-title');
      if (!listEl) return;

      const q = query.trim().toLowerCase();
      const allMsgs = (mailDatabase[currentFolder] || mailDatabase['Inbox'] || []);

      let matches = [];
      if (q) {
        titleEl.textContent = 'Matching Conversations';
        matches = allMsgs.filter(m => 
          (m.senderName && m.senderName.toLowerCase().includes(q)) ||
          (m.sender && m.sender.toLowerCase().includes(q)) ||
          (m.subject && m.subject.toLowerCase().includes(q)) ||
          (m.preview && m.preview.toLowerCase().includes(q))
        );
      } else {
        titleEl.textContent = 'Recent Conversations';
        matches = allMsgs.slice(0, 6);
      }

      if (countEl) countEl.textContent = matches.length + ' found';

      if (matches.length === 0) {
        listEl.innerHTML = `
          <div class="py-6 text-center text-slate-400">
            <i data-lucide="search-x" class="h-8 w-8 mx-auto mb-2 text-slate-300"></i>
            <p class="text-xs font-semibold">No emails matching "${escapeHtml(query)}"</p>
            <p class="text-[11px] text-slate-400 mt-1">Try another keyword or tap a smart filter above.</p>
          </div>
        `;
        if (window.lucide) lucide.createIcons();
        return;
      }

      listEl.innerHTML = matches.map((m, idx) => `
        <div onclick="selectMobileSearchResult('${escapeJsString(m.id)}')" class="p-2.5 rounded-2xl border border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50 transition cursor-pointer flex items-center justify-between gap-2.5">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600 font-bold text-xs shrink-0">
              ${escapeHtml(m.senderName ? m.senderName.charAt(0).toUpperCase() : 'M')}
            </div>
            <div class="min-w-0">
              <p class="text-xs font-bold text-slate-800 truncate">${escapeHtml(m.senderName || m.sender)}</p>
              <p class="text-[11px] text-slate-500 truncate">${escapeHtml(m.subject)}</p>
            </div>
          </div>
          <span class="text-[10px] text-slate-400 font-mono shrink-0">${escapeHtml(m.time || 'Today')}</span>
        </div>
      `).join('');

      if (window.lucide) lucide.createIcons();
    }

    function selectMobileSearchResult(msgId) {
      closeMobileSearchSheet();
      const allMsgs = (mailDatabase[currentFolder] || mailDatabase['Inbox'] || []);
      const idx = allMsgs.findIndex(m => String(m.id) === String(msgId));
      if (idx !== -1) {
        selectThreadItem(idx);
      }
    }

    // Search
    function handleSearch(val) {
      const clearBtn = document.getElementById('search-clear-btn');
      if (val && val.trim().length > 0) {
        clearBtn?.classList.remove('hidden');
      } else {
        clearBtn?.classList.add('hidden');
      }
      renderMessageList(val);
    }

    function clearSearch() {
      const inp = document.getElementById('global-search');
      if (inp) inp.value = '';
      document.getElementById('search-clear-btn')?.classList.add('hidden');
      renderMessageList();
    }

    // Refresh Mailbox
    async function refreshMailbox() {
      updateMobileSyncTime();
      const refreshBtn = document.getElementById('btn-top-refresh');
      if (refreshBtn) refreshBtn.classList.add('animate-spin');
      try {
        await Promise.all([
          fetchFolderMessages(currentFolder),
          refreshFolderCounts()
        ]);
      } catch (e) {}
      if (refreshBtn) refreshBtn.classList.remove('animate-spin');
    }

    // Mobile Back Navigation
    function backToMobileInbox() {
      const colReader = document.getElementById('col-reader');
      const colInbox = document.getElementById('col-inbox');
      if (colReader) {
        colReader.classList.add('hidden');
        colReader.classList.remove('flex');
      }
      if (colInbox) {
        colInbox.classList.remove('hidden');
      }
      window.scrollTo(0, 0);
    }

    // Dropdowns & Modals
    function toggleUserDropdown(e) {
      if (e) e.stopPropagation();
      document.getElementById('account-dropdown')?.classList.toggle('hidden');
    }
    window.toggleUserDropdown = toggleUserDropdown;

    function toggleAppSwitcher() {
      document.getElementById('app-switcher-modal')?.classList.toggle('hidden');
    }
    window.toggleAppSwitcher = toggleAppSwitcher;

    function openSettingsModal() {
      document.getElementById('settings-modal')?.classList.remove('hidden');
    }
    window.openSettingsModal = openSettingsModal;

    function closeSettingsModal() {
      document.getElementById('settings-modal')?.classList.add('hidden');
    }

    // =========================================================================
    // MODERN PROFILE PHOTO MANAGER (Raccon Mail Studio)
    // =========================================================================
    let currentSelectedAvatar = '<?php echo htmlspecialchars($currentUser['avatar']); ?>';
    let pendingAvatarBlob = null;
    let pendingAvatarUploadFile = null;
    let currentActiveAvatarTab = 'upload';

    let openedFromSettings = false;
    
    function getAuthToken() {
      const urlTok = new URLSearchParams(window.location.search).get('token');
      if (urlTok) return urlTok;
      try {
        const loc = localStorage.getItem('raccon_token');
        if (loc) return loc;
      } catch (_) {}
      const match = document.cookie.match(/(?:^|;\s*)raccon_token=([^;]+)/);
      return match ? decodeURIComponent(match[1]) : '';
    }
  
    function openAvatarModal(fromSettings = false) {
      if (fromSettings) {
        openedFromSettings = true;
        closeSettingsModal();
      }
      const modal = document.getElementById('modal-change-avatar');
      if (!modal) return;
      modal.classList.remove('hidden');
      pendingAvatarBlob = null;
      pendingAvatarUploadFile = null;

      const currentAvatar = document.getElementById('header-user-avatar')?.src || '<?php echo htmlspecialchars($currentUser['avatar']); ?>';
      currentSelectedAvatar = currentAvatar;
      const previewImg = document.getElementById('avatar-preview-img');
      if (previewImg) previewImg.src = currentAvatar;

      const statusMsg = document.getElementById('upload-status-msg');
      if (statusMsg) {
        statusMsg.className = 'hidden text-xs px-3 py-2 rounded-xl';
        statusMsg.innerText = '';
      }

      switchAvatarTab(currentActiveAvatarTab || 'upload');
      initAvatarDragAndDrop();
      if (window.lucide) lucide.createIcons();
    }

    function closeAvatarModal() {
      const modal = document.getElementById('modal-change-avatar');
      if (modal) modal.classList.add('hidden');
      pendingAvatarBlob = null;
      pendingAvatarUploadFile = null;
      if (openedFromSettings) {
        openedFromSettings = false;
        openSettingsModal();
      }
    }

    function switchAvatarTab(tab) {
      currentActiveAvatarTab = tab;
      const tabs = ['upload', 'presets', 'dice', 'url'];
      tabs.forEach(t => {
        const btn = document.getElementById(`tab-btn-${t}`);
        const panel = document.getElementById(`tab-panel-${t}`);
        if (t === tab) {
          btn?.classList.add('bg-white', 'text-slate-900', 'shadow-2xs');
          btn?.classList.remove('text-slate-500');
          panel?.classList.remove('hidden');
        } else {
          btn?.classList.remove('bg-white', 'text-slate-900', 'shadow-2xs');
          btn?.classList.add('text-slate-500');
          panel?.classList.add('hidden');
        }
      });
      if (tab === 'dice') {
        const diceGrid = document.getElementById('dice-grid');
        if (diceGrid && diceGrid.children.length === 0) {
          rollDiceAvatars();
        }
      }
      if (window.lucide) lucide.createIcons();
    }

    function selectPresetAvatar(url, btnElement) {
      currentSelectedAvatar = url;
      pendingAvatarBlob = null;
      pendingAvatarUploadFile = null;

      const preview = document.getElementById('avatar-preview-img');
      if (preview) preview.src = url;

      document.querySelectorAll('.preset-avatar-btn').forEach(btn => {
        btn.classList.remove('border-[#F68D72]', 'bg-orange-50/40', 'ring-2', 'ring-[#F68D72]/30');
        btn.classList.add('border-slate-200');
      });
      if (btnElement) {
        btnElement.classList.remove('border-slate-200');
        btnElement.classList.add('border-[#F68D72]', 'bg-orange-50/40', 'ring-2', 'ring-[#F68D72]/30');
      }
    }

    function initAvatarDragAndDrop() {
      const dropzone = document.getElementById('avatar-dropzone');
      if (!dropzone || dropzone.dataset.dndBound) return;
      dropzone.dataset.dndBound = 'true';

      ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, e => {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.add('border-[#F68D72]', 'bg-[#FFEFEA]/40', 'scale-[1.01]');
        });
      });

      ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, e => {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.remove('border-[#F68D72]', 'bg-[#FFEFEA]/40', 'scale-[1.01]');
        });
      });

      dropzone.addEventListener('drop', e => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
          processAvatarFile(e.dataTransfer.files[0]);
        }
      });
    }

    function handleAvatarFileSelect(e) {
      if (e.target && e.target.files && e.target.files.length > 0) {
        processAvatarFile(e.target.files[0]);
      }
    }

    function processAvatarFile(file) {
      const statusMsg = document.getElementById('upload-status-msg');
      if (!file) return;

      if (!file.type.startsWith('image/')) {
        if (statusMsg) {
          statusMsg.className = 'text-xs px-3 py-2 rounded-xl bg-rose-50 text-rose-600 font-semibold block';
          statusMsg.innerText = 'Please select a valid image file (PNG, JPG, WEBP, GIF, SVG).';
        }
        return;
      }

      if (file.size > 12 * 1024 * 1024) {
        if (statusMsg) {
          statusMsg.className = 'text-xs px-3 py-2 rounded-xl bg-rose-50 text-rose-600 font-semibold block';
          statusMsg.innerText = 'Image size exceeds 12MB. Please select a smaller photo.';
        }
        return;
      }

      const reader = new FileReader();
      const loading = document.getElementById('avatar-preview-loading');
      if (loading) loading.classList.remove('hidden');

      reader.onload = function(evt) {
        const img = new Image();
        img.onload = function() {
          const canvas = document.createElement('canvas');
          const maxDim = 400;
          canvas.width = maxDim;
          canvas.height = maxDim;
          const ctx = canvas.getContext('2d');

          const minSide = Math.min(img.width, img.height);
          const startX = (img.width - minSide) / 2;
          const startY = (img.height - minSide) / 2;

          ctx.drawImage(img, startX, startY, minSide, minSide, 0, 0, maxDim, maxDim);

          canvas.toBlob(blob => {
            if (loading) loading.classList.add('hidden');
            if (blob) {
              pendingAvatarBlob = blob;
              pendingAvatarUploadFile = file;
              const croppedUrl = URL.createObjectURL(blob);
              currentSelectedAvatar = croppedUrl;
              const preview = document.getElementById('avatar-preview-img');
              if (preview) preview.src = croppedUrl;

              if (statusMsg) {
                statusMsg.className = 'text-xs px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 font-semibold flex items-center gap-1.5';
                statusMsg.innerHTML = '<i data-lucide="check-circle-2" class="h-4 w-4"></i> Photo auto-cropped and ready! Click "Save Photo" below to apply.';
                if (window.lucide) lucide.createIcons();
              }
            }
          }, 'image/jpeg', 0.92);
        };
        img.onerror = function() {
          if (loading) loading.classList.add('hidden');
          if (statusMsg) {
            statusMsg.className = 'text-xs px-3 py-2 rounded-xl bg-rose-50 text-rose-600 font-semibold block';
            statusMsg.innerText = 'Unable to decode image. Please try another file.';
          }
        };
        img.src = evt.target.result;
      };
      reader.readAsDataURL(file);
    }

    function rollDiceAvatars() {
      const diceIcon = document.getElementById('dice-icon-spin');
      if (diceIcon) {
        diceIcon.classList.add('rotate-180');
        setTimeout(() => diceIcon.classList.remove('rotate-180'), 300);
      }

      const styles = ['bottts', 'adventurer', 'lorelei', 'fun-emoji', 'croodles', 'thumbs'];
      const diceGrid = document.getElementById('dice-grid');
      if (!diceGrid) return;
      diceGrid.innerHTML = '';

      styles.forEach(style => {
        const seed = 'raccon_' + Math.random().toString(36).substring(2, 8);
        const url = `https://api.dicebear.com/7.x/${style}/svg?seed=${seed}&backgroundColor=f1f5f9`;
        
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'p-1.5 rounded-xl border border-slate-200 hover:border-[#F68D72] bg-slate-50 hover:bg-white flex flex-col items-center gap-1 transition cursor-pointer hover:scale-105 active:scale-95 group';
        card.innerHTML = `<img src="${url}" class="w-10 h-10 rounded-full object-cover group-hover:scale-110 transition" loading="lazy" /><span class="text-[9px] font-bold text-slate-500 truncate w-full text-center capitalize">${style}</span>`;
        card.onclick = () => selectPresetAvatar(url, card);
        diceGrid.appendChild(card);
      });
    }

    function previewCustomUrl() {
      const urlInput = document.getElementById('avatar-url-input');
      const url = urlInput?.value.trim();
      if (!url) return;

      const testImg = new Image();
      const loading = document.getElementById('avatar-preview-loading');
      if (loading) loading.classList.remove('hidden');

      testImg.onload = function() {
        if (loading) loading.classList.add('hidden');
        selectPresetAvatar(url, null);
      };
      testImg.onerror = function() {
        if (loading) loading.classList.add('hidden');
        alert('Could not load image from that URL. Please verify the URL is public and CORS-accessible.');
      };
      testImg.src = url;
    }

    function resetAvatarToDefault() {
      const defaultAvatar = '/avatars/raccoon-default.svg';
      selectPresetAvatar(defaultAvatar, null);
    }

    async function saveSelectedAvatar() {
      const saveBtn = document.getElementById('save-avatar-btn');
      const originalHtml = saveBtn?.innerHTML;
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Saving...</span>';
        if (window.lucide) lucide.createIcons();
      }

      try {
        let finalAvatarUrl = null;
        const tok = typeof getAuthToken === 'function' ? getAuthToken() : '';
        const authHeader = tok ? { 'Authorization': 'Bearer ' + tok } : {};

        if (pendingAvatarBlob || pendingAvatarUploadFile) {
          const fileToSend = pendingAvatarBlob || pendingAvatarUploadFile;
          const formData = new FormData();
          formData.append('avatar', fileToSend, fileToSend.name || 'avatar.jpg');
          const res = await fetch('/api/user/avatar', {
            method: 'POST',
            credentials: 'include',
            headers: authHeader,
            body: formData
          });
          const d = await res.json();
          if (d.success && d.avatar) {
            finalAvatarUrl = d.avatar;
          } else {
            throw new Error(d.error || 'Avatar upload failed.');
          }
        } else if (currentSelectedAvatar && !currentSelectedAvatar.startsWith('blob:')) {
          const res = await fetch('/api/user/avatar', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              ...authHeader
            },
            body: JSON.stringify({ avatar: currentSelectedAvatar })
          });
          const d = await res.json();
          if (d.success && d.avatar) {
            finalAvatarUrl = d.avatar;
          } else {
            throw new Error(d.error || 'Failed to save avatar.');
          }
        } else {
          throw new Error('Please select an image or avatar first.');
        }

        if (finalAvatarUrl) {
          const cacheBuster = (finalAvatarUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
          const cleanUrl = finalAvatarUrl + cacheBuster;

          // Instantly update ALL user avatars in DOM
          document.querySelectorAll('.user-avatar-img').forEach(img => {
            img.src = cleanUrl;
            img.style.display = 'block';
          });
          const mobLarge = document.getElementById('mobile-profile-large-img');
          if (mobLarge) mobLarge.src = cleanUrl;
          const prevImg = document.getElementById('avatar-preview-img');
          if (prevImg) prevImg.src = cleanUrl;
          const fallback = document.getElementById('mobile-header-initial-fallback');
          if (fallback) fallback.style.display = 'none';

          if (typeof currentUser === 'object' && currentUser) {
            currentUser.avatar = finalAvatarUrl;
          }
          try { localStorage.setItem('raccon_user_avatar', finalAvatarUrl); } catch (_) {}
          
          closeAvatarModal();
          if (typeof showToast === 'function') {
            showToast('Profile photo updated successfully! 🦝', 'success');
          } else if (typeof showAvatarToast === 'function') {
            showAvatarToast('Profile photo updated successfully! 🦝');
          } else {
            alert('Profile photo updated successfully!');
          }
        }
      } catch (err) {
        alert('Could not save profile photo: ' + err.message);
      } finally {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = originalHtml;
          if (window.lucide) lucide.createIcons();
        }
      }
    }

    function showAvatarToast(message) {
      let toast = document.getElementById('avatar-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'avatar-toast';
        toast.className = 'fixed bottom-6 right-6 z-70 bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-2xl border border-slate-700/80 flex items-center gap-3 transition-all duration-300 transform translate-y-10 opacity-0 pointer-events-none';
        document.body.appendChild(toast);
      }
      toast.innerHTML = `
        <div class="w-8 h-8 rounded-xl bg-[#FFEFEA] text-[#E06748] flex items-center justify-center shrink-0">
          <i data-lucide="check" class="h-4 w-4 stroke-[3]"></i>
        </div>
        <span class="text-xs font-bold text-slate-100">${message}</span>
      `;
      if (window.lucide) lucide.createIcons();
      toast.classList.remove('translate-y-10', 'opacity-0');
      toast.classList.add('translate-y-0', 'opacity-100');

      setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-10', 'opacity-0');
      }, 4000);
    }

    // Close avatar modal on Escape key
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        const modal = document.getElementById('modal-change-avatar');
        if (modal && !modal.classList.contains('hidden')) {
          closeAvatarModal();
        }
      }
    });

    async function saveSettingsAction() {
      const name = document.getElementById('setting-name')?.value.trim();
      const signature = document.getElementById('setting-signature')?.value.trim();
      try {
        const res = await fetch('/api/user', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, signature })
        });
        const d = await res.json();
        if (d.success && d.user) {
          const newName = d.user.name || d.user.email;
          const topName = document.getElementById('top-user-name');
          if (topName) topName.textContent = newName;
          const dropName = document.getElementById('dropdown-user-name');
          if (dropName) dropName.textContent = newName;
          const sideName = document.getElementById('sidebar-user-name');
          if (sideName) sideName.textContent = newName;
          alert('Profile name updated successfully!');
          closeSettingsModal();
        } else {
          alert(d.error || 'Could not update profile.');
        }
      } catch (err) {
        alert('Failed to save settings: ' + err.message);
      }
    }

    function openContactsModal() {
      document.getElementById('contacts-modal')?.classList.remove('hidden');
    }

    function closeContactsModal() {
      document.getElementById('contacts-modal')?.classList.add('hidden');
    }

    function openNewIdentityModal() {
      alert('New Masked Identity created! Mask: secure.' + Math.random().toString(36).substring(2, 7) + '@racconmail.eu');
    }

    function openNewCategoryModal() {
      const name = prompt('Enter new category or label name:');
      if (name) alert('Category "' + name + '" created successfully!');
    }

    function openStoriesModal() {
      window.location.href = '/stories';
    }

    function attachFileAction() {
      const inp = document.createElement('input');
      inp.type = 'file';
      inp.onchange = () => alert('File attached: ' + inp.files[0]?.name);
      inp.click();
    }

    function insertLinkAction() {
      const url = prompt('Enter link URL (e.g. https://...):');
      if (url) {
        document.getElementById('compose-body').value += ' ' + url;
      }
    }

    function insertImageAction() {
      attachFileAction();
    }

    function filterCategory(cat) {
      alert('Filtered by category: ' + cat);
    }

    // Logout - Always returns to root landing page
    async function logoutNow() {
      try {
        await fetch('/api/auth/logout', { method: 'POST' });
      } catch (_) {}
      try {
        localStorage.clear();
        sessionStorage.clear();
        document.cookie = 'raccon_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
        document.cookie = 'roundcube_sessid=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
        document.cookie = 'roundcube_sessauth=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
      } catch (_) {}
      window.location.replace('https://racconmail.eu/');
    }

    // Dismiss popovers on outside click
    document.addEventListener('click', (e) => {
      const dd = document.getElementById('account-dropdown');
      const btn = document.getElementById('user-profile-btn');
      if (dd && !dd.contains(e.target) && !btn?.contains(e.target)) {
        dd.classList.add('hidden');
      }
      const appSw = document.getElementById('app-switcher-modal');
      if (appSw && !appSw.contains(e.target) && !e.target.closest('button[onclick="toggleAppSwitcher()"]')) {
        appSw.classList.add('hidden');
      }
    });

    // Raccoon Success Popup Controllers
    function showRaccoonSuccessModal() {
      const modal = document.getElementById('raccoon-success-modal');
      if (modal) {
        modal.classList.remove('hidden');
        setTimeout(() => {
          closeRaccoonSuccessModal();
        }, 3500);
      }
    }

    function closeRaccoonSuccessModal() {
      const modal = document.getElementById('raccoon-success-modal');
      if (modal) modal.classList.add('hidden');
    }

    // Initial Render
    renderMessageList();
  </script>

  <!-- ========================================================================= -->
  <!-- DOCKED / FLOATING COMPOSE CARD: "New Email" (Universal Root Level)        -->
  <!-- ========================================================================= -->
  <div id="docked-compose-card" class="hidden fixed inset-x-3 bottom-3 sm:inset-x-auto sm:bottom-5 sm:right-6 sm:w-[500px] max-w-[calc(100vw-24px)] max-h-[90vh] bg-white rounded-2xl shadow-[0_25px_60px_rgba(0,0,0,0.35)] border border-slate-200/90 flex flex-col overflow-hidden z-[99999] transition-all duration-200">
    
    <!-- Header: "New Email" + Minimize (-) + Close (X) -->
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white select-none">
      <div class="flex items-center gap-2">
        <span class="font-bold text-xs text-slate-900 tracking-tight">New Email</span>
        <span class="text-[10px] text-slate-400 font-mono">Raccon Mail</span>
      </div>
      <div class="flex items-center gap-1">
        <button onclick="minimizeComposeCard()" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer" title="Minimize">
          <i data-lucide="minus" class="h-3.5 w-3.5"></i>
        </button>
        <button onclick="closeComposeCard()" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer" title="Close">
          <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
      </div>
    </div>

    <!-- Form Rows matching Figma -->
    <div id="compose-body-container" class="p-4 space-y-2.5 text-xs overflow-y-auto">
      <!-- From Field with Capsule Pill -->
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
        <span class="text-slate-400 font-medium w-12 shrink-0">From:</span>
        <div class="inline-flex items-center gap-1.5 bg-slate-100 rounded-full px-2.5 py-1 text-slate-700 font-medium">
          <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="h-3.5 w-3.5 rounded-full object-cover" />
          <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
        </div>
      </div>

      <!-- To Field with Capsule Pill -->
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
        <span class="text-slate-400 font-medium w-12 shrink-0">To:</span>
        <input id="compose-to" type="email" placeholder="recipient@domain.com" value="" autocomplete="off" class="flex-1 bg-transparent text-slate-800 font-medium outline-none text-xs select-text" />
      </div>

      <!-- Subject Field -->
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
        <span class="text-slate-400 font-medium w-12 shrink-0">Subject:</span>
        <input id="compose-subject" type="text" placeholder="Subject" value="" autocomplete="off" class="flex-1 bg-transparent text-slate-800 font-medium outline-none text-xs select-text" />
      </div>

      <!-- Message Body Textarea -->
      <div>
        <textarea
          id="compose-body"
          rows="6"
          class="w-full resize-none bg-transparent outline-none text-xs text-slate-700 leading-relaxed placeholder-slate-400 select-text"
          placeholder="Write your email here..."
        ></textarea>
      </div>
    </div>

    <!-- Footer Action Toolbar -->
    <div class="px-4 py-3 bg-white border-t border-slate-100 flex items-center justify-between shrink-0">
      <!-- Black Send Button on the Left -->
      <button
        onclick="sendEmailNow()"
        id="btn-send-email"
        class="bg-slate-900 hover:bg-black text-white text-xs font-semibold px-5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5 cursor-pointer"
      >
        <i data-lucide="send" class="h-3.5 w-3.5 text-white"></i>
        <span>Send</span>
      </button>

      <!-- Toolbar Icons on the Right (Gray Lucide Icons) -->
      <div class="flex items-center gap-2 text-slate-400">
        <button type="button" onclick="attachFileAction()" class="hover:text-slate-700 p-1 transition" title="Attach file"><i data-lucide="paperclip" class="h-4 w-4"></i></button>
        <button type="button" onclick="insertLinkAction()" class="hover:text-slate-700 p-1 transition" title="Insert link"><i data-lucide="link" class="h-4 w-4"></i></button>
        <button type="button" onclick="insertImageAction()" class="hover:text-slate-700 p-1 transition" title="Insert image"><i data-lucide="image" class="h-4 w-4"></i></button>
        <button type="button" onclick="closeComposeCard()" class="hover:text-rose-500 p-1 transition" title="Discard"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
      </div>
    </div>

  </div>

  <!-- ========================================================================= -->
  <!-- FLOATING MOBILE COMPOSE BUTTON (PENCIL IN BOTTOM RIGHT CORNER)            -->
  <!-- ========================================================================= -->
  <!-- ========================================================================= -->
  
  <!-- Fixed Modern Bottom Navigation Bar (Matches Reference Image) -->
  <nav id="mobile-app-bottom-bar" class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-t border-slate-100 py-2.5 px-3 flex items-center justify-around select-none">
    <!-- Inbox Tray (Active Coral) -->
    <button onclick="switchFolder('Inbox'); backToMobileInbox();" class="flex flex-col items-center gap-1 cursor-pointer group relative" title="Inbox">
      <div class="w-6 h-6 flex items-center justify-center text-[#F68D72] relative">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5v-3h3.56c.69 1.19 1.97 2 3.44 2s2.75-.81 3.44-2H19v3zm0-5h-4.99c0 1.1-.9 2-2.01 2s-2.01-.9-2.01-2H5V5h14v9z"/></svg>
        <span id="mobile-bottom-inbox-badge" class="absolute -top-1 -right-1.5 min-w-[14px] h-3.5 px-1 rounded-full bg-[#E06748] text-white text-[8px] font-black flex items-center justify-center shadow-xs <?php echo ($counts['inbox_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_total']; ?></span>
      </div>
      <span class="text-[10px] font-bold text-slate-800">Inbox</span>
    </button>

    <!-- Menu / Sidebar Drawer Toggle -->
    <button onclick="toggleSidebarAction()" class="flex flex-col items-center gap-1 cursor-pointer text-slate-500 hover:text-slate-900 transition group" title="Menu">
      <div class="w-6 h-6 flex items-center justify-center">
        <svg class="w-5 h-5 stroke-current fill-none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <line x1="4" y1="6" x2="20" y2="6"></line>
          <line x1="4" y1="12" x2="20" y2="12"></line>
          <line x1="4" y1="18" x2="20" y2="18"></line>
        </svg>
      </div>
      <span class="text-[10px] font-bold text-slate-600">Menu</span>
    </button>

    <!-- Pencil / Compose -->
    <button onclick="openComposeCard()" class="flex flex-col items-center gap-1 cursor-pointer text-slate-500 hover:text-slate-900 transition group" title="Compose Email">
      <div class="w-6 h-6 flex items-center justify-center">
        <svg class="w-5 h-5 stroke-current fill-none" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
      </div>
      <span class="text-[10px] font-bold text-slate-600">Compose</span>
    </button>

    <!-- Community / Forum & Stories -->
    <a href="/forum" class="flex flex-col items-center gap-1 cursor-pointer text-slate-500 hover:text-slate-900 transition group" title="Community Forum">
      <div class="w-6 h-6 flex items-center justify-center">
        <svg class="w-5 h-5 stroke-current fill-none" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      </div>
      <span class="text-[10px] font-bold text-slate-600">Forum</span>
    </a>

    <!-- Notifications Bell -->
    <button onclick="handleInboxNotificationClick()" class="flex flex-col items-center gap-1 cursor-pointer text-slate-500 hover:text-slate-900 transition group" title="Notifications">
      <div class="w-6 h-6 flex items-center justify-center">
        <svg class="w-5 h-5 stroke-current fill-none" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      </div>
      <span class="text-[10px] font-bold text-slate-600">Alerts</span>
    </button>
  </nav>

  <!-- ========================================================================= -->
  <!-- RACCOON SUCCESS POPUP MODAL ("Email sent successfully! 🐾")                -->
  <!-- ========================================================================= -->
  <div id="raccoon-success-modal" class="hidden fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-all duration-200" onclick="if(event.target === this) closeRaccoonSuccessModal()">
    <div class="relative w-full max-w-sm rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-200/90 text-center animate-in zoom-in-95 duration-200">
      <button onclick="closeRaccoonSuccessModal()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition cursor-pointer">
        <i class="fas fa-times text-xs"></i>
      </button>
      <div class="relative inline-block mb-3">
        <img src="/raccoon-logo.svg" alt="Raccon Mascot" class="h-24 w-24 mx-auto object-contain filter drop-shadow-md animate-bounce" />
        <span class="absolute -top-1 -right-1 text-2xl">✨</span>
      </div>
      <h3 class="font-display text-xl font-bold text-slate-900">Email sent successfully! 🐾</h3>
      <p class="text-xs text-slate-500 mt-2 leading-relaxed">
        Your sealed envelope is on its nocturnal flight and has been safely dispatched worldwide!
      </p>
      <button onclick="closeRaccoonSuccessModal()" class="mt-5 w-full rounded-full bg-slate-900 hover:bg-black text-white font-bold text-xs py-3 transition active:scale-95 shadow-sm cursor-pointer">
        Awesome, thanks Rocky!
      </button>
    </div>
  </div>

  <!-- ========================================================================= -->


  <!-- ========================================================================= -->
  <!-- MODERN HALF-SCREEN SEARCH BOTTOM SHEET (MOBILE VIEW & ANDROID APK)        -->
  <!-- ========================================================================= -->
  <div id="mobile-search-backdrop" onclick="closeMobileSearchSheet()" class="hidden fixed inset-0 bg-black/40 backdrop-blur-xs z-[99995] md:hidden transition-opacity duration-300"></div>

  <div id="mobile-search-sheet" class="hidden fixed bottom-0 left-0 right-0 z-[99998] md:hidden bg-white rounded-t-3xl border-t border-slate-200/90 shadow-2xl flex flex-col max-h-[75vh] h-[65vh] transition-transform duration-300 ease-out transform translate-y-full">
    <!-- Drag handle indicator -->
    <div class="pt-3 pb-1 flex justify-center cursor-pointer" onclick="closeMobileSearchSheet()">
      <div class="w-12 h-1.5 rounded-full bg-slate-300"></div>
    </div>

    <!-- Sheet Header with Search Bar -->
    <div class="p-3.5 border-b border-slate-100 flex items-center gap-2">
      <div class="relative flex-1 flex items-center">
        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-cyan-500 stroke-[2.5] pointer-events-none filter drop-shadow-[0_1px_2px_rgba(6,182,212,0.35)]"></i>
        <input
          id="mobile-search-input"
          type="text"
          placeholder="Search sender, subject, keywords..."
          oninput="handleMobileSearchInput(this.value)"
          class="w-full bg-slate-50 hover:bg-white focus:bg-white rounded-2xl border border-slate-200 pl-10 pr-9 py-2.5 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100 transition shadow-2xs"
          autocomplete="off"
        />
        <button id="mobile-search-clear-btn" onclick="clearMobileSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1 cursor-pointer">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>
      <button onclick="closeMobileSearchSheet()" class="px-2.5 py-1.5 text-slate-500 hover:text-slate-800 text-xs font-semibold rounded-xl hover:bg-slate-100 transition cursor-pointer">
        Cancel
      </button>
    </div>

    <!-- Scrollable Suggestions & Results Body -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
      <!-- Quick Filter Pills -->
      <div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Smart Filters</p>
        <div class="flex flex-wrap gap-1.5">
          <button onclick="applySearchFilter('is:unread')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80 text-[11px] font-medium flex items-center gap-1 transition cursor-pointer">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span> Unread
          </button>
          <button onclick="applySearchFilter('is:starred')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80 text-[11px] font-medium flex items-center gap-1 transition cursor-pointer">
            <i data-lucide="star" class="h-3 w-3 text-amber-500 fill-amber-500"></i> Starred
          </button>
          <button onclick="applySearchFilter('has:attachment')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80 text-[11px] font-medium flex items-center gap-1 transition cursor-pointer">
            <i data-lucide="paperclip" class="h-3 w-3 text-slate-500"></i> Attachments
          </button>
          <button onclick="applySearchFilter('support')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80 text-[11px] font-medium flex items-center gap-1 transition cursor-pointer">
            <i data-lucide="shield-check" class="h-3 w-3 text-slate-500"></i> Support
          </button>
        </div>
      </div>

      <!-- Live Search Suggestions Section -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <p id="mobile-search-results-title" class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Suggested Results</p>
          <span id="mobile-search-results-count" class="text-[10px] text-slate-400 font-mono"></span>
        </div>
        <div id="mobile-search-suggestions-list" class="space-y-1.5">
          <!-- Dynamically populated -->
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- HIGH-VALUE PRODUCTIVITY SLIDE-OVER DRAWER (Calendar, Notes, Tasks, etc.)  -->
  <!-- ========================================================================= -->
  <div id="productivity-drawer-backdrop" onclick="closeProductivityDrawer()" class="hidden fixed inset-0 bg-slate-900/30 backdrop-blur-2xs z-40 transition-opacity"></div>
  
  <aside id="productivity-drawer" class="fixed inset-y-0 right-0 w-full sm:w-[420px] bg-white border-l border-slate-200 shadow-2xl z-50 flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out select-none">
    
    <!-- Drawer Top Header & Tool Tabs -->
    <div class="p-4 border-b border-slate-100 flex flex-col gap-3 shrink-0 bg-slate-50/70">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div id="drawer-header-icon" class="w-8 h-8 rounded-xl bg-cyan-100/80 text-cyan-700 flex items-center justify-center font-bold">
            <i data-lucide="calendar" class="h-4 w-4"></i>
          </div>
          <div>
            <h2 id="drawer-header-title" class="text-sm font-bold text-slate-900 leading-tight">Calendar & Schedule</h2>
            <p id="drawer-header-subtitle" class="text-[11px] text-slate-400">Integrated events and smart reminders</p>
          </div>
        </div>

        <button onclick="closeProductivityDrawer()" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition cursor-pointer">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <!-- Quick Tool Switcher Ribbon -->
      <div class="grid grid-cols-6 gap-1 p-1 bg-white rounded-xl border border-slate-200/80 shadow-2xs text-[10px] font-bold text-slate-500">
        <button onclick="switchDrawerTab('calendar')" id="drawer-tab-calendar" class="flex flex-col items-center py-1.5 rounded-lg bg-cyan-50 text-cyan-700 transition cursor-pointer">
          <i data-lucide="calendar" class="h-3.5 w-3.5 mb-0.5"></i>
          <span>Cal</span>
        </button>
        <button onclick="switchDrawerTab('notes')" id="drawer-tab-notes" class="flex flex-col items-center py-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          <i data-lucide="sticky-note" class="h-3.5 w-3.5 mb-0.5"></i>
          <span>Notes</span>
        </button>
        <button onclick="switchDrawerTab('tasks')" id="drawer-tab-tasks" class="flex flex-col items-center py-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          <i data-lucide="check-square" class="h-3.5 w-3.5 mb-0.5"></i>
          <span>Tasks</span>
        </button>
        <button onclick="switchDrawerTab('attachments')" id="drawer-tab-attachments" class="flex flex-col items-center py-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          <i data-lucide="paperclip" class="h-3.5 w-3.5 mb-0.5"></i>
          <span>Files</span>
        </button>
        <button onclick="switchDrawerTab('rss')" id="drawer-tab-rss" class="flex flex-col items-center py-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          <i data-lucide="rss" class="h-3.5 w-3.5 mb-0.5"></i>
          <span>Feeds</span>
        </button>
        <button onclick="switchDrawerTab('templates')" id="drawer-tab-templates" class="flex flex-col items-center py-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          <i data-lucide="file-text" class="h-3.5 w-3.5 mb-0.5"></i>
          <span>Templates</span>
        </button>
      </div>
    </div>

    <!-- Drawer Dynamic Scrollable Content Pane -->
    <div id="drawer-content-container" class="flex-1 overflow-y-auto p-4 space-y-4">
      <!-- Injected by renderDrawerContent() -->
    </div>

  </aside>

  <!-- ========================================================================= -->
  <!-- ⚙️ PRO FEATURES & ENTERPRISE VALUE SUITE MODAL (Full Multi-Tab Showcase) -->
  <!-- ========================================================================= -->
  

  <!-- ========================================================================= -->
  <!-- ADD / CONNECT EXTERNAL ACCOUNT MODAL                                      -->
  <!-- ========================================================================= -->
  <div id="add-account-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-2xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-100">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
        <div class="flex items-center gap-2">
          <i data-lucide="plus-circle" class="h-5 w-5 text-cyan-600"></i>
          <h3 class="text-sm font-bold text-slate-900">Connect External Mail Account</h3>
        </div>
        <button onclick="closeAddAccountModal()" class="text-slate-400 hover:text-slate-700">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Email Address</label>
          <input type="email" id="new-acc-email" placeholder="e.g. name@company.com or @gmail.com" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-cyan-500" />
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Display Label</label>
          <input type="text" id="new-acc-name" placeholder="e.g. Work Mailbox" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-cyan-500" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">IMAP Server</label>
            <input type="text" id="new-acc-imap" placeholder="imap.mail.com" value="mail.racconmail.eu" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl font-mono text-[11px]" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Port & Security</label>
            <input type="text" id="new-acc-port" value="993 (SSL)" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl font-mono text-[11px]" />
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Password / App Key</label>
          <input type="password" id="new-acc-pass" placeholder="••••••••••••" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-cyan-500" />
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 mt-5 pt-3 border-t border-slate-100">
        <button onclick="closeAddAccountModal()" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
        <button onclick="saveConnectedAccountAction()" class="px-4 py-2 rounded-xl text-xs font-bold bg-cyan-600 hover:bg-cyan-700 text-white shadow-2xs">Save & Connect</button>
      </div>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- HIGH-VALUE PRODUCTIVITY & PRO SUITE JAVASCRIPT LOGIC                      -->
  <!-- ========================================================================= -->
  <script>
    // Productivity State
    let currentDrawerTab = 'calendar';
    let isUnifiedInboxActive = false;
    let selectedCalendarDate = new Date().toISOString().split('T')[0];
    let cachedCalendarEvents = [];
    let cachedUserNotes = [];
    let cachedUserTasks = [];
    let cachedAttachments = [];
    let cachedTemplates = [];
    let cachedRssFeeds = [];
    let cachedRssArticles = [];
    let activeEventColor = '#06b6d4';
    let activeNoteColor = '#fef08a';

    function escapeHtml(str) {
      if (str === null || str === undefined) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
    window.escapeHtml = escapeHtml;

    // 1. Drawer Opening & Tab Switching
    function openProductivityDrawer(tabName) {
      tabName = tabName || 'calendar';
      const drawer = document.getElementById('productivity-drawer');
      const backdrop = document.getElementById('productivity-drawer-backdrop');
      if (!drawer) return;

      drawer.classList.remove('translate-x-full');
      if (backdrop) backdrop.classList.remove('hidden');
      switchDrawerTab(tabName);
    }

    function closeProductivityDrawer() {
      const drawer = document.getElementById('productivity-drawer');
      const backdrop = document.getElementById('productivity-drawer-backdrop');
      if (!drawer) return;

      drawer.classList.add('translate-x-full');
      if (backdrop) backdrop.classList.add('hidden');
    }

    function switchDrawerTab(tabName) {
      currentDrawerTab = tabName;
      
      // Update top tab buttons
      ['calendar', 'notes', 'tasks', 'attachments', 'rss', 'templates'].forEach(function(t) {
        const btn = document.getElementById('drawer-tab-' + t);
        if (btn) {
          if (t === tabName) {
            btn.className = 'flex flex-col items-center py-1.5 rounded-lg bg-cyan-50 text-cyan-700 font-bold transition cursor-pointer shadow-2xs';
          } else {
            btn.className = 'flex flex-col items-center py-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer text-slate-500';
          }
        }
      });

      // Update Header Text & Icon
      const titleEl = document.getElementById('drawer-header-title');
      const subEl = document.getElementById('drawer-header-subtitle');
      const iconEl = document.getElementById('drawer-header-icon');

      const meta = {
        calendar: { title: 'Calendar & Schedule', sub: 'Integrated events, meetings, and smart reminders', icon: 'calendar', color: 'bg-cyan-100 text-cyan-700' },
        notes: { title: 'Keep Notes & Ideas', sub: 'Pastel sticky notes with checklists, tags & color codes', icon: 'sticky-note', color: 'bg-amber-100 text-amber-700' },
        tasks: { title: 'Actionable Tasks / To-Do', sub: 'Turn correspondence into prioritized milestones', icon: 'check-square', color: 'bg-emerald-100 text-emerald-700' },
        attachments: { title: 'Attachments & Media Gallery', sub: 'Visual cloud dashboard for documents, images & files', icon: 'paperclip', color: 'bg-purple-100 text-purple-700' },
        rss: { title: 'Newsletter & Feed Reader', sub: 'Distraction-free aggregator for blogs, tech news & feeds', icon: 'rss', color: 'bg-rose-100 text-rose-700' },
        templates: { title: 'Canned Responses & Templates', sub: 'Insert pre-saved professional replies with 1 click', icon: 'file-text', color: 'bg-indigo-100 text-indigo-700' }
      }[tabName];

      if (titleEl && meta) titleEl.innerText = meta.title;
      if (subEl && meta) subEl.innerText = meta.sub;
      if (iconEl && meta) {
        iconEl.className = 'w-8 h-8 rounded-xl flex items-center justify-center font-bold ' + meta.color;
        iconEl.innerHTML = '<i data-lucide="' + meta.icon + '" class="h-4 w-4"></i>';
      }

      renderDrawerContent(tabName);
      if (window.lucide) lucide.createIcons();
    }

    // 2. Render Drawer Content Dynamically
    async function renderDrawerContent(tabName) {
      const container = document.getElementById('drawer-content-container');
      if (!container) return;

      container.innerHTML = '<div class="py-12 flex flex-col items-center justify-center text-slate-400 gap-2"><div class="w-6 h-6 border-2 border-cyan-500 border-t-transparent rounded-full animate-spin"></div><span class="text-xs">Loading ' + tabName + '...</span></div>';

      try {
        if (tabName === 'calendar') {
          await loadCalendarView(container);
        } else if (tabName === 'notes') {
          await loadNotesView(container);
        } else if (tabName === 'tasks') {
          await loadTasksView(container);
        } else if (tabName === 'attachments') {
          await loadAttachmentsView(container);
        } else if (tabName === 'rss') {
          await loadRssView(container);
        } else if (tabName === 'templates') {
          await loadTemplatesView(container);
        }
      } catch (err) {
        container.innerHTML = '<div class="p-4 rounded-xl bg-rose-50 text-rose-700 text-xs">Error loading ' + tabName + ': ' + (err.message || err) + '</div>';
      }

      if (window.lucide) lucide.createIcons();
    }

    // =========================================================================
    // CALENDAR VIEW & EVENT PLANNER
    // =========================================================================
    async function loadCalendarView(container) {
      const res = await fetch('/api/calendar/events');
      const data = await res.json();
      cachedCalendarEvents = data.events || [];

      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.toLocaleString('default', { month: 'long' });
      const daysInMonth = new Date(currentYear, now.getMonth() + 1, 0).getDate();
      const todayDate = now.getDate();

      let daysHtml = '';
      for (let d = 1; d <= daysInMonth; d++) {
        const dStr = String(d).padStart(2, '0');
        const mStr = String(now.getMonth() + 1).padStart(2, '0');
        const fullDate = currentYear + '-' + mStr + '-' + dStr;
        const isToday = (d === todayDate);
        const isSelected = (fullDate === selectedCalendarDate);
        const hasEvents = cachedCalendarEvents.some(function(ev) { return ev.event_date === fullDate; });

        daysHtml += '<button onclick="selectCalendarDate(\'' + fullDate + '\')" class="h-8 rounded-lg flex flex-col items-center justify-center text-xs font-semibold transition cursor-pointer relative ' +
          (isSelected ? 'bg-cyan-600 text-white font-bold shadow-2xs' : (isToday ? 'bg-cyan-50 text-cyan-800 font-bold border border-cyan-300' : 'hover:bg-slate-100 text-slate-700')) + '">' +
          '<span>' + d + '</span>' +
          (hasEvents ? '<span class="w-1.5 h-1.5 rounded-full ' + (isSelected ? 'bg-white' : 'bg-cyan-500') + ' absolute bottom-1"></span>' : '') +
          '</button>';
      }

      const dayEvents = cachedCalendarEvents.filter(function(ev) { return ev.event_date === selectedCalendarDate; });

      let eventsListHtml = '';
      if (dayEvents.length === 0) {
        eventsListHtml = '<div class="py-6 text-center text-slate-400 bg-slate-50/60 rounded-2xl border border-dashed border-slate-200">' +
          '<i data-lucide="calendar-x" class="h-6 w-6 mx-auto mb-1.5 opacity-60"></i>' +
          '<p class="text-xs font-medium">No events scheduled for ' + escapeHtml(selectedCalendarDate) + '</p>' +
          '<button onclick="showQuickAddEvent()" class="mt-2 text-xs font-bold text-cyan-600 hover:underline cursor-pointer">+ Schedule Event</button>' +
          '</div>';
      } else {
        eventsListHtml = dayEvents.map(function(ev) {
          return '<div class="p-3 rounded-2xl bg-white border border-slate-100 shadow-2xs hover:shadow-xs transition flex items-start justify-between gap-3 group">' +
            '<div class="flex items-start gap-2.5 min-w-0">' +
              '<span class="w-2.5 h-2.5 rounded-full mt-1 shrink-0" style="background-color: ' + (ev.color || '#06b6d4') + '"></span>' +
              '<div class="min-w-0">' +
                '<p class="text-xs font-bold text-slate-900 truncate leading-snug">' + escapeHtml(ev.title) + '</p>' +
                '<div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">' +
                  '<span class="font-mono font-medium text-slate-600">' + escapeHtml(ev.start_time) + ' - ' + escapeHtml(ev.end_time) + '</span>' +
                  (ev.location ? '<span>• ' + escapeHtml(ev.location) + '</span>' : '') +
                '</div>' +
                (ev.notes ? '<p class="text-[11px] text-slate-500 mt-1 line-clamp-2">' + escapeHtml(ev.notes) + '</p>' : '') +
              '</div>' +
            '</div>' +
            '<button onclick="deleteCalendarEvent(\'' + ev.id + '\')" class="opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-rose-500 transition cursor-pointer">' +
              '<i data-lucide="trash" class="h-3.5 w-3.5"></i>' +
            '</button>' +
          '</div>';
        }).join('');
      }

      container.innerHTML = '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">' +
        '<div class="flex items-center justify-between mb-3">' +
          '<h3 class="text-xs font-bold text-slate-800 flex items-center gap-1.5">' +
            '<i data-lucide="calendar" class="h-4 w-4 text-cyan-500"></i>' +
            '<span>' + currentMonth + ' ' + currentYear + '</span>' +
          '</h3>' +
          '<span class="text-[10px] font-bold text-cyan-700 bg-cyan-50 border border-cyan-200 px-2 py-0.5 rounded-full">Today: ' + todayDate + ' ' + currentMonth + '</span>' +
        '</div>' +
        '<div class="grid grid-cols-7 gap-1 text-[10px] font-bold text-slate-400 text-center mb-1">' +
          '<span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>' +
        '</div>' +
        '<div class="grid grid-cols-7 gap-1">' + daysHtml + '</div>' +
      '</div>' +
      '<div class="flex items-center justify-between pt-1">' +
        '<h4 class="text-xs font-bold text-slate-900 flex items-center gap-1.5">' +
          '<i data-lucide="clock" class="h-3.5 w-3.5 text-cyan-600"></i>' +
          '<span>Schedule for ' + escapeHtml(selectedCalendarDate) + '</span>' +
        '</h4>' +
        '<button onclick="showQuickAddEvent()" class="text-xs font-bold text-cyan-600 hover:text-cyan-800 transition flex items-center gap-1 cursor-pointer">' +
          '<i data-lucide="plus" class="h-3.5 w-3.5"></i>' +
          '<span>Add Event</span>' +
        '</button>' +
      '</div>' +
      '<div id="quick-add-event-form" class="hidden p-3.5 rounded-2xl bg-cyan-50/60 border border-cyan-200/80 space-y-2.5 animate-in fade-in duration-100">' +
        '<input type="text" id="ev-input-title" placeholder="Event title or meeting..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-cyan-500 font-semibold" />' +
        '<div class="grid grid-cols-2 gap-2">' +
          '<input type="time" id="ev-input-start" value="10:00" class="px-2.5 py-1 text-xs bg-white border border-slate-200 rounded-lg text-slate-700 font-mono" />' +
          '<input type="time" id="ev-input-end" value="11:00" class="px-2.5 py-1 text-xs bg-white border border-slate-200 rounded-lg text-slate-700 font-mono" />' +
        '</div>' +
        '<input type="text" id="ev-input-loc" placeholder="Location or Zoom / Meet URL" class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-cyan-500" />' +
        '<div class="flex items-center justify-between pt-1">' +
          '<div class="flex items-center gap-1.5">' +
            '<label class="text-[10px] text-slate-400 font-bold uppercase">Color:</label>' +
            '<button onclick="setEventColor(\'#06b6d4\')" class="w-4 h-4 rounded-full bg-cyan-500 border border-white shadow-2xs"></button>' +
            '<button onclick="setEventColor(\'#a855f7\')" class="w-4 h-4 rounded-full bg-purple-500 border border-white shadow-2xs"></button>' +
            '<button onclick="setEventColor(\'#10b981\')" class="w-4 h-4 rounded-full bg-emerald-500 border border-white shadow-2xs"></button>' +
            '<button onclick="setEventColor(\'#ef4444\')" class="w-4 h-4 rounded-full bg-rose-500 border border-white shadow-2xs"></button>' +
            '<button onclick="setEventColor(\'#f59e0b\')" class="w-4 h-4 rounded-full bg-amber-500 border border-white shadow-2xs"></button>' +
          '</div>' +
          '<div class="flex items-center gap-1.5">' +
            '<button onclick="hideQuickAddEvent()" class="px-2.5 py-1 rounded-lg text-xs text-slate-500 hover:bg-slate-200/60 font-semibold cursor-pointer">Cancel</button>' +
            '<button onclick="saveCalendarEventAction()" class="px-3 py-1 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-700 text-white shadow-2xs cursor-pointer">Save</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="space-y-2">' + eventsListHtml + '</div>';
    }

    function selectCalendarDate(dateStr) {
      selectedCalendarDate = dateStr;
      renderDrawerContent('calendar');
    }

    function showQuickAddEvent() {
      const f = document.getElementById('quick-add-event-form');
      if (f) {
        f.classList.remove('hidden');
        document.getElementById('ev-input-title').focus();
      }
    }

    function hideQuickAddEvent() {
      const f = document.getElementById('quick-add-event-form');
      if (f) f.classList.add('hidden');
    }

    function setEventColor(colorHex) {
      activeEventColor = colorHex;
    }

    async function saveCalendarEventAction() {
      try {
        const titleEl = document.getElementById('ev-input-title');
        const startEl = document.getElementById('ev-input-start');
        const endEl = document.getElementById('ev-input-end');
        const locEl = document.getElementById('ev-input-loc');
        if (!titleEl) return;

        const title = titleEl.value.trim();
        const start = startEl ? startEl.value : '10:00';
        const end = endEl ? endEl.value : '11:00';
        const loc = locEl ? locEl.value.trim() : '';

        if (!title) return alert('Please enter an event title');

        const res = await fetch('/api/calendar/events', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            title: title,
            date: selectedCalendarDate,
            start_time: start,
            end_time: end,
            location: loc,
            color: activeEventColor
          })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to save event');

        hideQuickAddEvent();
        titleEl.value = '';
        renderDrawerContent('calendar');
      } catch (err) {
        alert('Error saving calendar event: ' + (err.message || err));
      }
    }

    async function deleteCalendarEvent(id) {
      if (!confirm('Delete this event?')) return;
      try {
        await fetch('/api/calendar/events?id=' + encodeURIComponent(id), { method: 'DELETE' });
        renderDrawerContent('calendar');
      } catch (err) {
        alert('Error deleting event: ' + err.message);
      }
    }

    // =========================================================================
    // NOTES SECTION (Pastel Sticky Cards & Categories)
    // =========================================================================
    async function loadNotesView(container) {
      const res = await fetch('/api/notes');
      const data = await res.json();
      cachedUserNotes = data.notes || [];

      const notesHtml = cachedUserNotes.map(function(n) {
        return '<div class="p-3.5 rounded-2xl shadow-2xs border border-slate-200/70 space-y-2 group transition hover:shadow-xs relative" style="background-color: ' + (n.color || '#fef08a') + '">' +
          '<div class="flex items-start justify-between gap-2">' +
            '<h4 class="text-xs font-bold text-slate-900 leading-snug truncate">' + escapeHtml(n.title) + '</h4>' +
            '<div class="flex items-center gap-1 opacity-80 group-hover:opacity-100">' +
              (n.is_pinned ? '<span title="Pinned Note">📌</span>' : '') +
              '<button onclick="deleteNoteAction(\'' + n.id + '\')" class="p-1 text-slate-400 hover:text-rose-600 transition cursor-pointer">' +
                '<i data-lucide="trash" class="h-3 w-3"></i>' +
              '</button>' +
            '</div>' +
          '</div>' +
          '<p class="text-xs text-slate-800 font-normal leading-relaxed whitespace-pre-wrap">' + escapeHtml(n.content) + '</p>' +
          '<div class="flex items-center justify-between text-[10px] text-slate-500/80 pt-1 border-t border-black/5">' +
            '<span class="font-mono uppercase font-bold">' + escapeHtml(n.category || 'general') + '</span>' +
            '<span>' + new Date(n.created_at || Date.now()).toLocaleDateString() + '</span>' +
          '</div>' +
        '</div>';
      }).join('');

      container.innerHTML = '<div class="flex items-center justify-between">' +
        '<div class="relative flex-1 mr-2">' +
          '<i data-lucide="search" class="h-3.5 w-3.5 text-slate-400 absolute left-3 top-2.5"></i>' +
          '<input type="text" oninput="filterNotesLive(this.value)" placeholder="Search sticky notes..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white" />' +
        '</div>' +
        '<button onclick="showQuickAddNote()" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold transition flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">' +
          '<i data-lucide="plus" class="h-3.5 w-3.5 text-white"></i>' +
          '<span>New Note</span>' +
        '</button>' +
      '</div>' +
      '<div id="quick-add-note-card" class="hidden p-3.5 rounded-2xl bg-amber-50/80 border border-amber-200/90 space-y-2.5 animate-in fade-in duration-100">' +
        '<input type="text" id="note-input-title" placeholder="Note Title..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 font-bold" />' +
        '<textarea id="note-input-body" rows="3" placeholder="Write thoughts, checklists, or key details..." class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 leading-normal"></textarea>' +
        '<div class="flex items-center justify-between pt-1">' +
          '<div class="flex items-center gap-1.5">' +
            '<button onclick="setNoteColor(\'#fef08a\')" title="Sunny Yellow" class="w-4 h-4 rounded-full bg-amber-200 border border-amber-300"></button>' +
            '<button onclick="setNoteColor(\'#bbf7d0\')" title="Mint Green" class="w-4 h-4 rounded-full bg-emerald-200 border border-emerald-300"></button>' +
            '<button onclick="setNoteColor(\'#fed7aa\')" title="Peach Coral" class="w-4 h-4 rounded-full bg-orange-200 border border-orange-300"></button>' +
            '<button onclick="setNoteColor(\'#e9d5ff\')" title="Lavender Purple" class="w-4 h-4 rounded-full bg-purple-200 border border-purple-300"></button>' +
            '<button onclick="setNoteColor(\'#bae6fd\')" title="Sky Blue" class="w-4 h-4 rounded-full bg-sky-200 border border-sky-300"></button>' +
          '</div>' +
          '<div class="flex items-center gap-1.5">' +
            '<button onclick="hideQuickAddNote()" class="px-2.5 py-1 text-xs text-slate-500 hover:bg-slate-200/60 rounded-lg cursor-pointer">Cancel</button>' +
            '<button onclick="saveNoteAction()" class="px-3 py-1 text-xs font-bold bg-amber-500 hover:bg-amber-600 text-slate-900 rounded-lg shadow-2xs cursor-pointer">Save Note</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div id="notes-grid-list" class="space-y-3">' + (notesHtml || '<div class="py-8 text-center text-slate-400 text-xs">No notes saved yet. Click "+ New Note" to write one.</div>') + '</div>';
    }

    function showQuickAddNote() {
      const el = document.getElementById('quick-add-note-card');
      if (el) {
        el.classList.remove('hidden');
        document.getElementById('note-input-title').focus();
      }
    }

    function hideQuickAddNote() {
      const el = document.getElementById('quick-add-note-card');
      if (el) el.classList.add('hidden');
    }

    function setNoteColor(c) {
      activeNoteColor = c;
    }

    async function saveNoteAction() {
      try {
        const titleEl = document.getElementById('note-input-title');
        const contentEl = document.getElementById('note-input-body');
        if (!titleEl || !contentEl) return;

        const title = titleEl.value.trim();
        const content = contentEl.value.trim();
        if (!title && !content) return alert('Please enter note title or body');

        const res = await fetch('/api/notes', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            title: title || 'Untitled Note',
            content: content,
            color: activeNoteColor
          })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to save note');

        hideQuickAddNote();
        titleEl.value = '';
        contentEl.value = '';
        renderDrawerContent('notes');
      } catch (err) {
        alert('Error saving note: ' + (err.message || err));
      }
    }

    async function deleteNoteAction(id) {
      if (!confirm('Delete this note?')) return;
      try {
        await fetch('/api/notes?id=' + encodeURIComponent(id), { method: 'DELETE' });
        renderDrawerContent('notes');
      } catch (err) {
        alert('Error deleting note: ' + err.message);
      }
    }

    function filterNotesLive(query) {
      query = (query || '').toLowerCase();
      const list = document.getElementById('notes-grid-list');
      if (!list) return;

      const filtered = cachedUserNotes.filter(function(n) {
        return (n.title || '').toLowerCase().includes(query) || (n.content || '').toLowerCase().includes(query);
      });

      list.innerHTML = filtered.map(function(n) {
        return '<div class="p-3.5 rounded-2xl shadow-2xs border border-slate-200/70 space-y-2 group transition hover:shadow-xs relative" style="background-color: ' + (n.color || '#fef08a') + '">' +
          '<div class="flex items-start justify-between gap-2">' +
            '<h4 class="text-xs font-bold text-slate-900 leading-snug truncate">' + escapeHtml(n.title) + '</h4>' +
            '<button onclick="deleteNoteAction(\'' + n.id + '\')" class="p-1 text-slate-400 hover:text-rose-600 transition cursor-pointer">' +
              '<i data-lucide="trash" class="h-3 w-3"></i>' +
            '</button>' +
          '</div>' +
          '<p class="text-xs text-slate-800 font-normal leading-relaxed whitespace-pre-wrap">' + escapeHtml(n.content) + '</p>' +
        '</div>';
      }).join('');
      if (window.lucide) lucide.createIcons();
    }

    // =========================================================================
    // TASKS & ACTIONABLE TO-DO LIST
    // =========================================================================
    async function loadTasksView(container) {
      const res = await fetch('/api/tasks');
      const data = await res.json();
      cachedUserTasks = data.tasks || [];

      const tasksHtml = cachedUserTasks.map(function(t) {
        const isDone = Boolean(Number(t.is_completed));
        const priColor = { high: 'bg-rose-50 text-rose-700 border-rose-200', medium: 'bg-amber-50 text-amber-700 border-amber-200', low: 'bg-slate-100 text-slate-600 border-slate-200' }[t.priority || 'medium'];

        return '<div class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex items-center justify-between gap-3 group transition hover:shadow-xs">' +
          '<div class="flex items-center gap-2.5 min-w-0">' +
            '<input type="checkbox" ' + (isDone ? 'checked' : '') + ' onchange="toggleTaskAction(\'' + t.id + '\', this.checked)" class="w-4 h-4 rounded text-cyan-600 focus:ring-cyan-500 cursor-pointer shrink-0" />' +
            '<div class="min-w-0">' +
              '<p class="text-xs font-bold text-slate-900 truncate ' + (isDone ? 'line-through text-slate-400' : '') + '">' + escapeHtml(t.title) + '</p>' +
              '<div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">' +
                '<span class="px-1.5 py-0.5 rounded border uppercase font-mono font-bold ' + priColor + '">' + escapeHtml(t.priority) + '</span>' +
                (t.due_date ? '<span class="font-mono text-slate-500">Due: ' + escapeHtml(t.due_date) + '</span>' : '') +
                (t.linked_email_subject ? '<span class="truncate text-cyan-600 font-medium">✉️ ' + escapeHtml(t.linked_email_subject) + '</span>' : '') +
              '</div>' +
            '</div>' +
          '</div>' +
          '<button onclick="deleteTaskAction(\'' + t.id + '\')" class="opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-rose-500 transition cursor-pointer">' +
            '<i data-lucide="trash" class="h-3.5 w-3.5"></i>' +
          '</button>' +
        '</div>';
      }).join('');

      container.innerHTML = '<div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">' +
        '<input type="text" id="task-input-title" placeholder="Add actionable task or milestone..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-cyan-500" />' +
        '<div class="flex items-center justify-between">' +
          '<div class="flex items-center gap-2">' +
            '<select id="task-input-priority" class="text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 text-slate-600 font-semibold">' +
              '<option value="medium">Medium Priority</option>' +
              '<option value="high">High Priority</option>' +
              '<option value="low">Low Priority</option>' +
            '</select>' +
            '<input type="date" id="task-input-due" value="' + new Date().toISOString().split('T')[0] + '" class="text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 text-slate-600 font-mono" />' +
          '</div>' +
          '<button onclick="saveTaskAction()" class="px-3.5 py-1 text-xs font-bold bg-slate-900 hover:bg-black text-white rounded-lg shadow-2xs cursor-pointer">Add Task</button>' +
        '</div>' +
      '</div>' +
      '<div class="flex items-center justify-between text-xs font-bold text-slate-700 pt-1">' +
        '<span>Active Tasks (' + cachedUserTasks.filter(function(t) { return !Number(t.is_completed); }).length + ')</span>' +
        '<span class="text-[10px] text-slate-400 font-mono">' + cachedUserTasks.filter(function(t) { return Boolean(Number(t.is_completed)); }).length + ' Completed</span>' +
      '</div>' +
      '<div class="space-y-2">' + (tasksHtml || '<div class="py-6 text-center text-slate-400 text-xs">No tasks found. Add a new task above!</div>') + '</div>';
    }

    async function saveTaskAction() {
      try {
        const titleEl = document.getElementById('task-input-title');
        const priEl = document.getElementById('task-input-priority');
        const dueEl = document.getElementById('task-input-due');
        if (!titleEl) return;

        const title = titleEl.value.trim();
        const priority = priEl ? priEl.value : 'medium';
        const due = dueEl ? dueEl.value : new Date().toISOString().split('T')[0];

        if (!title) return alert('Please enter a task title');

        const res = await fetch('/api/tasks', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title: title, priority: priority, due_date: due })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to save task');

        titleEl.value = '';
        renderDrawerContent('tasks');
      } catch (err) {
        alert('Error saving task: ' + (err.message || err));
      }
    }

    async function toggleTaskAction(id, isCompleted) {
      try {
        await fetch('/api/tasks/toggle', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, is_completed: isCompleted ? 1 : 0 })
        });
        renderDrawerContent('tasks');
      } catch (err) {
        alert('Error updating task: ' + err.message);
      }
    }

    async function deleteTaskAction(id) {
      try {
        await fetch('/api/tasks?id=' + encodeURIComponent(id), { method: 'DELETE' });
        renderDrawerContent('tasks');
      } catch (err) {
        alert('Error deleting task: ' + err.message);
      }
    }

    // =========================================================================
    // ATTACHMENTS & MEDIA GALLERY DASHBOARD (With Direct Upload & Download)
    // =========================================================================
    async function loadAttachmentsView(container) {
      const res = await fetch('/api/attachments/dashboard');
      const data = await res.json();
      cachedAttachments = data.attachments || [];

      const itemsHtml = cachedAttachments.map(function(att) {
        const isImg = (att.mime_type || '').startsWith('image/');
        const isPdf = (att.mime_type || '').includes('pdf');
        const isZip = (att.mime_type || '').includes('zip') || (att.filename || '').endsWith('.zip');
        const sizeMb = (att.size_bytes ? (att.size_bytes / 1024 / 1024).toFixed(1) + ' MB' : '1.2 MB');

        return '<div class="p-3 rounded-2xl bg-white border border-slate-200/80 shadow-2xs hover:shadow-xs transition flex items-center justify-between gap-3 group">' +
          '<div class="flex items-center gap-3 min-w-0">' +
            '<div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 ' +
              (isImg ? 'bg-amber-50 text-amber-600' : (isPdf ? 'bg-rose-50 text-rose-600' : (isZip ? 'bg-emerald-50 text-emerald-600' : 'bg-cyan-50 text-cyan-600'))) + '">' +
              '<i data-lucide="' + (isImg ? 'image' : (isPdf ? 'file-text' : (isZip ? 'archive' : 'file'))) + '" class="h-5 w-5"></i>' +
            '</div>' +
            '<div class="min-w-0">' +
              '<p class="text-xs font-bold text-slate-800 truncate">' + escapeHtml(att.original_name || att.filename) + '</p>' +
              '<div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">' +
                '<span class="font-mono font-medium text-slate-600">' + sizeMb + '</span>' +
                (att.email_subject ? '<span class="truncate">✉️ ' + escapeHtml(att.email_subject) + '</span>' : '<span>☁️ Vault File</span>') +
              '</div>' +
            '</div>' +
          '</div>' +
          '<div class="flex items-center gap-1.5 shrink-0">' +
            '<a href="/api/attachments/' + att.id + '/download" download class="p-2 rounded-xl text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 transition" title="Download File">' +
              '<i data-lucide="download" class="h-4 w-4"></i>' +
            '</a>' +
            '<button onclick="deleteAttachmentAction(\'' + att.id + '\')" class="opacity-0 group-hover:opacity-100 p-2 rounded-xl text-slate-300 hover:text-rose-600 transition cursor-pointer" title="Delete File">' +
              '<i data-lucide="trash" class="h-3.5 w-3.5"></i>' +
            '</button>' +
          '</div>' +
        '</div>';
      }).join('');

      container.innerHTML = '<div class="flex items-center justify-between">' +
        '<div>' +
          '<p class="text-xs font-bold text-slate-800">Files & Attachments (' + cachedAttachments.length + ')</p>' +
          '<span class="text-[10px] text-slate-400 font-mono">SSD Encrypted Storage</span>' +
        '</div>' +
        '<button onclick="toggleQuickAddFile()" class="px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer shadow-2xs">' +
          '<i data-lucide="upload-cloud" class="h-3.5 w-3.5"></i>' +
          '<span>Upload File</span>' +
        '</button>' +
      '</div>' +
      '<div id="quick-add-file-card" class="hidden p-3.5 rounded-2xl bg-purple-50/70 border border-purple-200/90 space-y-2 animate-in fade-in duration-100">' +
        '<input type="file" id="attachment-file-input" class="hidden" onchange="handleFileUploadAction(event)" />' +
        '<div onclick="document.getElementById(\'attachment-file-input\').click()" class="border-2 border-dashed border-purple-300 rounded-xl p-4 text-center cursor-pointer hover:bg-purple-100/50 transition bg-white/70">' +
          '<i data-lucide="paperclip" class="h-6 w-6 text-purple-600 mx-auto mb-1"></i>' +
          '<p class="text-xs font-bold text-slate-800">Click to choose a file or drag here</p>' +
          '<p class="text-[10px] text-slate-400 mt-0.5">Supports PDF, Documents, Spreadsheets, Images, Archives up to 50MB</p>' +
        '</div>' +
        '<div id="upload-file-status" class="hidden text-xs text-center text-purple-700 font-bold py-1"></div>' +
      '</div>' +
      '<div class="space-y-2">' + (itemsHtml || '<div class="py-8 text-center text-slate-400 text-xs">No files uploaded yet. Click "+ Upload File" above!</div>') + '</div>';
    }

    function toggleQuickAddFile() {
      const f = document.getElementById('quick-add-file-card');
      if (f) f.classList.toggle('hidden');
    }

    async function handleFileUploadAction(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      const statusEl = document.getElementById('upload-file-status');
      if (statusEl) {
        statusEl.classList.remove('hidden');
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Uploading ' + escapeHtml(file.name) + '...';
      }

      try {
        const formData = new FormData();
        formData.append('file', file);

        const res = await fetch('/api/attachments/upload', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to upload file');

        toggleQuickAddFile();
        renderDrawerContent('attachments');
      } catch (err) {
        alert('Upload Error: ' + (err.message || err));
        if (statusEl) statusEl.classList.add('hidden');
      }
    }

    async function deleteAttachmentAction(id) {
      if (!confirm('Remove this file from your dashboard?')) return;
      try {
        await fetch('/api/attachments?id=' + encodeURIComponent(id), { method: 'DELETE' });
        renderDrawerContent('attachments');
      } catch (err) {
        alert('Error removing file: ' + err.message);
      }
    }

    // =========================================================================
    // RSS & FEED READER (With Custom Feed Subscriptions)
    // =========================================================================
    async function loadRssView(container) {
      const res = await fetch('/api/rss');
      const data = await res.json();
      cachedRssFeeds = data.feeds || [];
      cachedRssArticles = data.articles || [];

      const feedsPillsHtml = cachedRssFeeds.map(function(f) {
        return '<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-[11px] font-bold border border-slate-200 shrink-0">' +
          '<span>' + escapeHtml(f.title) + '</span>' +
          '<button onclick="deleteFeedAction(\'' + f.id + '\')" class="text-slate-400 hover:text-rose-600 transition cursor-pointer" title="Unsubscribe">×</button>' +
        '</div>';
      }).join('');

      const articlesHtml = cachedRssArticles.map(function(art) {
        return '<div class="p-3.5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2 hover:border-rose-200 transition">' +
          '<div class="flex items-center justify-between text-[10px] text-slate-400">' +
            '<span class="font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200/60">' + escapeHtml(art.source) + '</span>' +
            '<span>' + escapeHtml(art.date) + '</span>' +
          '</div>' +
          '<h4 class="text-xs font-bold text-slate-900 leading-snug">' + escapeHtml(art.title) + '</h4>' +
          '<p class="text-[11px] text-slate-500 leading-relaxed">' + escapeHtml(art.snippet) + '</p>' +
          '<div class="flex items-center justify-between pt-1 text-[11px]">' +
            '<span class="text-slate-400 font-medium">By ' + escapeHtml(art.author || 'Editorial') + '</span>' +
            '<a href="' + art.url + '" target="_blank" class="text-cyan-600 hover:underline font-bold flex items-center gap-1">' +
              '<span>Read Source</span>' +
              '<i data-lucide="external-link" class="h-3 w-3"></i>' +
            '</a>' +
          '</div>' +
        '</div>';
      }).join('');

      container.innerHTML = '<div class="flex items-center justify-between">' +
        '<div>' +
          '<h3 class="text-xs font-bold text-slate-800">Subscribed Feeds</h3>' +
          '<span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Live Sync</span>' +
        '</div>' +
        '<button onclick="toggleQuickAddFeed()" class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer shadow-2xs">' +
          '<i data-lucide="plus" class="h-3.5 w-3.5"></i>' +
          '<span>Add Feed</span>' +
        '</button>' +
      '</div>' +
      '<div id="quick-add-feed-form" class="hidden p-3.5 rounded-2xl bg-rose-50/70 border border-rose-200/90 space-y-2 animate-in fade-in duration-100">' +
        '<input type="text" id="feed-input-title" placeholder="Feed Name (e.g. Ars Technica)..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500 font-bold" />' +
        '<input type="url" id="feed-input-url" placeholder="RSS or Atom Feed URL (https://...)..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500 font-mono text-[11px]" />' +
        '<div class="flex items-center justify-between pt-1">' +
          '<select id="feed-input-cat" class="text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 text-slate-600 font-semibold">' +
            '<option value="tech">Technology</option>' +
            '<option value="news">World News</option>' +
            '<option value="design">Design & UI</option>' +
            '<option value="business">Business & Finance</option>' +
            '<option value="science">Science & Space</option>' +
          '</select>' +
          '<div class="flex items-center gap-1.5">' +
            '<button onclick="toggleQuickAddFeed()" class="px-2.5 py-1 text-xs text-slate-500 hover:bg-slate-200/60 rounded-lg cursor-pointer">Cancel</button>' +
            '<button onclick="saveFeedAction()" class="px-3 py-1 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-lg shadow-2xs cursor-pointer">Subscribe</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="flex items-center gap-1.5 overflow-x-auto pb-1">' + feedsPillsHtml + '</div>' +
      '<div class="space-y-3">' + (articlesHtml || '<div class="py-6 text-center text-slate-400 text-xs">No articles available.</div>') + '</div>';
    }

    function toggleQuickAddFeed() {
      const f = document.getElementById('quick-add-feed-form');
      if (f) f.classList.toggle('hidden');
    }

    async function saveFeedAction() {
      try {
        const title = document.getElementById('feed-input-title').value.trim();
        const url = document.getElementById('feed-input-url').value.trim();
        const cat = document.getElementById('feed-input-cat').value;

        if (!title || !url) return alert('Please enter both Feed Name and RSS URL');

        const res = await fetch('/api/rss', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title: title, feed_url: url, category: cat })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to add feed');

        toggleQuickAddFeed();
        renderDrawerContent('rss');
      } catch (err) {
        alert('Error adding feed: ' + (err.message || err));
      }
    }

    async function deleteFeedAction(id) {
      if (!confirm('Unsubscribe from this feed?')) return;
      try {
        await fetch('/api/rss?id=' + encodeURIComponent(id), { method: 'DELETE' });
        renderDrawerContent('rss');
      } catch (err) {
        alert('Error deleting feed: ' + err.message);
      }
    }

    // =========================================================================
    // TEMPLATES & CANNED RESPONSES (With New Template Builder)
    // =========================================================================
    async function loadTemplatesView(container) {
      const res = await fetch('/api/templates');
      const data = await res.json();
      cachedTemplates = data.templates || [];

      const templatesHtml = cachedTemplates.map(function(tpl) {
        return '<div class="p-3.5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2 hover:border-indigo-200 transition group">' +
          '<div class="flex items-center justify-between">' +
            '<div class="flex items-center gap-2 min-w-0">' +
              '<span class="w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span>' +
              '<h4 class="text-xs font-bold text-slate-900 truncate">' + escapeHtml(tpl.title) + '</h4>' +
            '</div>' +
            '<div class="flex items-center gap-1.5 shrink-0">' +
              '<span class="text-[10px] font-mono text-slate-400 uppercase">' + escapeHtml(tpl.category) + '</span>' +
              '<button onclick="deleteTemplateAction(\'' + tpl.id + '\')" class="opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-rose-600 transition cursor-pointer" title="Delete Template">' +
                '<i data-lucide="trash" class="h-3 w-3"></i>' +
              '</button>' +
            '</div>' +
          '</div>' +
          (tpl.subject ? '<p class="text-[11px] text-slate-500 font-medium">Subject: ' + escapeHtml(tpl.subject) + '</p>' : '') +
          '<p class="text-[11px] text-slate-600 line-clamp-3 bg-slate-50 p-2 rounded-xl whitespace-pre-wrap font-sans leading-relaxed">' + escapeHtml(tpl.body) + '</p>' +
          '<div class="flex items-center justify-end gap-2 pt-1">' +
            '<button onclick="copyTemplateById(\'' + tpl.id + '\')" class="px-2.5 py-1 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 transition cursor-pointer">Copy</button>' +
            '<button onclick="insertTemplateById(\'' + tpl.id + '\')" class="px-3 py-1 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-2xs transition cursor-pointer">Insert into Email</button>' +
          '</div>' +
        '</div>';
      }).join('');

      container.innerHTML = '<div class="flex items-center justify-between">' +
        '<div>' +
          '<h3 class="text-xs font-bold text-slate-800">Canned Responses</h3>' +
          '<span class="text-[10px] text-slate-400 font-mono">' + cachedTemplates.length + ' Saved</span>' +
        '</div>' +
        '<button onclick="toggleQuickAddTemplate()" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer shadow-2xs">' +
          '<i data-lucide="plus" class="h-3.5 w-3.5"></i>' +
          '<span>New Template</span>' +
        '</button>' +
      '</div>' +
      '<div id="quick-add-template-form" class="hidden p-3.5 rounded-2xl bg-indigo-50/70 border border-indigo-200/90 space-y-2 animate-in fade-in duration-100">' +
        '<input type="text" id="tpl-input-title" placeholder="Template Name (e.g. Support Receipt)..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500 font-bold" />' +
        '<input type="text" id="tpl-input-subj" placeholder="Email Subject Line (Optional)..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500" />' +
        '<textarea id="tpl-input-body" rows="3" placeholder="Write pre-composed reply body..." class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500 leading-normal"></textarea>' +
        '<div class="flex items-center justify-between pt-1">' +
          '<select id="tpl-input-cat" class="text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 text-slate-600 font-semibold">' +
            '<option value="business">Business</option>' +
            '<option value="support">Support</option>' +
            '<option value="finance">Finance</option>' +
            '<option value="general">General</option>' +
          '</select>' +
          '<div class="flex items-center gap-1.5">' +
            '<button onclick="toggleQuickAddTemplate()" class="px-2.5 py-1 text-xs text-slate-500 hover:bg-slate-200/60 rounded-lg cursor-pointer">Cancel</button>' +
            '<button onclick="saveTemplateAction()" class="px-3 py-1 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-2xs cursor-pointer">Save Template</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="space-y-3">' + (templatesHtml || '<div class="py-8 text-center text-slate-400 text-xs">No templates saved yet. Click "+ New Template" above!</div>') + '</div>';
    }

    function toggleQuickAddTemplate() {
      const f = document.getElementById('quick-add-template-form');
      if (f) f.classList.toggle('hidden');
    }

    async function saveTemplateAction() {
      try {
        const title = document.getElementById('tpl-input-title').value.trim();
        const subj = document.getElementById('tpl-input-subj').value.trim();
        const cat = document.getElementById('tpl-input-cat').value;
        const body = document.getElementById('tpl-input-body').value.trim();

        if (!title || !body) return alert('Please enter both Template Title and Body text');

        const res = await fetch('/api/templates', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title: title, subject: subj, category: cat, body: body })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to save template');

        toggleQuickAddTemplate();
        renderDrawerContent('templates');
      } catch (err) {
        alert('Error saving template: ' + (err.message || err));
      }
    }

    async function deleteTemplateAction(id) {
      if (!confirm('Delete this template?')) return;
      try {
        await fetch('/api/templates?id=' + encodeURIComponent(id), { method: 'DELETE' });
        renderDrawerContent('templates');
      } catch (err) {
        alert('Error deleting template: ' + err.message);
      }
    }

    function insertTemplateById(tplId) {
      const tpl = cachedTemplates.find(function(t) { return t.id === tplId; });
      if (!tpl) return;
      if (typeof openComposeCard === 'function') openComposeCard();
      const bodyEl = document.getElementById('compose-body');
      const subjEl = document.getElementById('compose-subject');
      if (bodyEl) bodyEl.value = tpl.body;
      if (subjEl && tpl.subject && !subjEl.value) subjEl.value = tpl.subject;
      closeProductivityDrawer();
    }

    function copyTemplateById(tplId) {
      const tpl = cachedTemplates.find(function(t) { return t.id === tplId; });
      if (!tpl) return;
      if (navigator && navigator.clipboard) {
        navigator.clipboard.writeText(tpl.body);
      }
      alert('Template copied to clipboard!');
    }

    // =========================================================================
    // MULTI-ACCOUNT & UNIFIED INBOX LOGIC
    // =========================================================================
    function toggleAccountDropdown(e) {
      if (e) e.stopPropagation();
      const m = document.getElementById('account-dropdown-menu');
      if (m) m.classList.toggle('hidden');
    }

    function switchActiveAccount(email, modeName, color) {
      document.getElementById('active-acc-name').innerText = email;
      document.getElementById('active-acc-mode').innerText = modeName;
      document.getElementById('active-acc-dot').style.backgroundColor = color;
      toggleAccountDropdown();
      refreshMailbox();
    }

    function toggleUnifiedInbox() {
      isUnifiedInboxActive = !isUnifiedInboxActive;
      const stateEl = document.getElementById('unified-toggle-state');
      const badgeEl = document.getElementById('unified-status-badge');
      const titleEl = document.getElementById('current-folder-display-title');

      if (isUnifiedInboxActive) {
        if (stateEl) stateEl.innerText = 'ON (3 ACCOUNTS)';
        if (badgeEl) badgeEl.classList.remove('hidden');
        if (titleEl) titleEl.innerText = 'Unified Inbox';
      } else {
        if (stateEl) stateEl.innerText = 'OFF';
        if (badgeEl) badgeEl.classList.add('hidden');
        if (titleEl) titleEl.innerText = 'Inbox';
      }
      toggleAccountDropdown();
      refreshMailbox();
    }

    function openAddAccountModal() {
      const m = document.getElementById('add-account-modal');
      if (m) m.classList.remove('hidden');
    }

    function closeAddAccountModal() {
      const m = document.getElementById('add-account-modal');
      if (m) m.classList.add('hidden');
    }

    async function saveConnectedAccountAction() {
      const email = document.getElementById('new-acc-email').value.trim();
      const name = document.getElementById('new-acc-name').value.trim();
      if (!email) return alert('Please enter an email address');

      await fetch('/api/accounts/switcher', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, display_name: name })
      });

      closeAddAccountModal();
      alert('External account ' + email + ' connected successfully!');
    }

    // =========================================================================
    // SMART CATEGORIES / TABS
    // =========================================================================
    let activeSmartTab = 'primary';
    function setSmartCategory(cat) {
      activeSmartTab = cat;
      ['primary', 'promotions', 'social', 'updates'].forEach(function(c) {
        const btn = document.getElementById('smart-tab-' + c);
        if (btn) {
          if (c === cat) {
            btn.className = 'flex items-center justify-center gap-1 py-1 rounded-lg bg-white text-slate-900 font-bold shadow-2xs transition cursor-pointer';
          } else {
            btn.className = 'flex items-center justify-center gap-1 py-1 rounded-lg hover:bg-white/80 transition cursor-pointer text-slate-500';
          }
        }
      });
      refreshMailbox();
    }

    // =========================================================================
    // ⚙️ PRO FEATURES & ENTERPRISE VALUE SUITE MODAL
    // =========================================================================
    function openProFeaturesModal() {
      const m = document.getElementById('pro-features-modal');
      if (m) {
        m.classList.remove('hidden');
        switchProTab('infra');
      }
    }

    function closeProFeaturesModal() {
      const m = document.getElementById('pro-features-modal');
      if (m) m.classList.add('hidden');
    }

    function switchProTab(tabKey) {
      ['infra', 'security', 'productivity', 'business'].forEach(function(k) {
        const btn = document.getElementById('pro-tab-' + k);
        if (btn) {
          if (k === tabKey) {
            btn.className = 'py-3 text-center border-b-2 border-amber-500 text-amber-800 bg-amber-50/40 transition cursor-pointer flex items-center justify-center gap-1.5 font-bold';
          } else {
            btn.className = 'py-3 text-center border-b-2 border-transparent hover:text-slate-800 transition cursor-pointer flex items-center justify-center gap-1.5 text-slate-500';
          }
        }
      });
      renderProTabContent(tabKey);
      if (window.lucide) lucide.createIcons();
    }

    function renderProTabContent(tabKey) {
      const container = document.getElementById('pro-tab-content-container');
      if (!container) return;

      if (tabKey === 'infra') {
        container.innerHTML = '<div class="space-y-4">' +
          '<div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">' +
            '<div>' +
              '<h4 class="text-xs font-bold text-slate-900">Expanded Cloud Storage (2TB Tier)</h4>' +
              '<p class="text-[11px] text-slate-400">High-speed SSD storage for messages, logs, and database files</p>' +
            '</div>' +
            '<span class="text-xs font-mono font-bold text-cyan-600 bg-cyan-50 border border-cyan-200 px-2.5 py-1 rounded-full">14.2 GB / 2,000 GB</span>' +
          '</div>' +
          '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-3">' +
            '<div class="flex items-center justify-between">' +
              '<div>' +
                '<h4 class="text-xs font-bold text-slate-900">Custom Domain Support (name@yourdomain.com)</h4>' +
                '<p class="text-[11px] text-slate-400">Automated DNS MX, SPF, DKIM & DMARC configuration</p>' +
              '</div>' +
              '<span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">Active</span>' +
            '</div>' +
            '<div class="bg-slate-900 text-emerald-400 p-3 rounded-xl font-mono text-[11px] space-y-1">' +
              '<p>MX 10 mail.yourdomain.com.</p>' +
              '<p>TXT "v=spf1 include:racconmail.eu ~all"</p>' +
              '<p>TXT default._domainkey "v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBA..."</p>' +
            '</div>' +
          '</div>' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
            '<div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1.5">' +
              '<h4 class="text-xs font-bold text-slate-900">Large File Sending (&gt;25MB)</h4>' +
              '<p class="text-[11px] text-slate-500">Auto-generates encrypted cloud download links with expiry dates and password protection.</p>' +
            '</div>' +
            '<div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1.5">' +
              '<h4 class="text-xs font-bold text-slate-900">Multi-Account IMAP/POP3 Sync</h4>' +
              '<p class="text-[11px] text-slate-500">Aggregate unlimited mailboxes into a single synchronized dashboard with 0ms local caching.</p>' +
            '</div>' +
          '</div>' +
        '</div>';
      } else if (tabKey === 'security') {
        container.innerHTML = '<div class="space-y-4">' +
          '<div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200/80 flex items-center justify-between">' +
            '<div>' +
              '<h4 class="text-xs font-bold text-emerald-950">End-to-End Encryption (E2EE) & PGP Keys</h4>' +
              '<p class="text-[11px] text-emerald-700">Client-side zero-knowledge encryption key vault active</p>' +
            '</div>' +
            '<span class="text-[10px] font-bold text-white bg-emerald-600 px-3 py-1 rounded-full shadow-2xs">PGP 4096-bit</span>' +
          '</div>' +
          '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-3">' +
            '<div class="flex items-center justify-between">' +
              '<div>' +
                '<h4 class="text-xs font-bold text-slate-900">Email Aliases & Burner Masking</h4>' +
                '<p class="text-[11px] text-slate-400">Generate disposable addresses to stop tracking and spam</p>' +
              '</div>' +
              '<button onclick="generateBurnerAlias()" class="text-xs font-bold text-cyan-600 hover:underline cursor-pointer">+ Generate Mask</button>' +
            '</div>' +
            '<div class="space-y-1.5">' +
              '<div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 text-xs">' +
                '<span class="font-mono text-slate-700">mask.shopping.4829@racconmail.eu</span>' +
                '<span class="text-[10px] text-slate-400">Forwards to alex@racconmail.eu</span>' +
              '</div>' +
              '<div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 text-xs">' +
                '<span class="font-mono text-slate-700">mask.newsletters.9102@racconmail.eu</span>' +
                '<span class="text-[10px] text-slate-400">Forwards to alex@racconmail.eu</span>' +
              '</div>' +
            '</div>' +
          '</div>' +
          '<div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">' +
            '<h4 class="text-xs font-bold text-slate-900">Security Audit Logs & Active Sessions</h4>' +
            '<p class="text-[11px] text-slate-400">Full IP address logging, suspicious login alerts, and remote session termination.</p>' +
            '<div class="text-[10px] text-slate-600 font-mono space-y-1 pt-1">' +
              '<p>• ' + new Date().toLocaleDateString() + ' 20:54 &mdash; Webmail Authorized &mdash; 178.132.24.11 (Chrome Win11)</p>' +
              '<p>• Yesterday 14:12 &mdash; Android APK Token Handshake &mdash; Verified</p>' +
            '</div>' +
          '</div>' +
        '</div>';
      } else if (tabKey === 'productivity') {
        container.innerHTML = '<div class="space-y-4">' +
          '<div class="p-4 rounded-2xl bg-cyan-50/60 border border-cyan-200/80 flex items-center justify-between">' +
            '<div>' +
              '<h4 class="text-xs font-bold text-cyan-950">AI Writing Assistant & Summarizer</h4>' +
              '<p class="text-[11px] text-cyan-700">Draft replies, adjust professional tone, and summarize long multi-nested threads</p>' +
            '</div>' +
            '<span class="text-[10px] font-bold text-white bg-cyan-600 px-3 py-1 rounded-full shadow-2xs">Integrated</span>' +
          '</div>' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
            '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1.5">' +
              '<h4 class="text-xs font-bold text-slate-900">Email Tracking & Read Receipts</h4>' +
              '<p class="text-[11px] text-slate-500">Real-time open tracking and link analytics so you know precisely when recipients open your proposals.</p>' +
            '</div>' +
            '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1.5">' +
              '<h4 class="text-xs font-bold text-slate-900">Send Later & 30s Undo Send</h4>' +
              '<p class="text-[11px] text-slate-500">Scheduled delivery matching recipient time zones, plus an extended 30-second cancellation safety window.</p>' +
            '</div>' +
          '</div>' +
          '<div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1.5">' +
            '<h4 class="text-xs font-bold text-slate-900">Advanced Automation Rules & Sequences</h4>' +
            '<p class="text-[11px] text-slate-500">Build conditional triggers: \'If sender is client, apply Priority tag, create Calendar reminder, and auto-reply confirmation.\'</p>' +
          '</div>' +
        '</div>';
      } else if (tabKey === 'business') {
        container.innerHTML = '<div class="space-y-4">' +
          '<div class="p-4 rounded-2xl bg-purple-50/60 border border-purple-200/80 flex items-center justify-between">' +
            '<div>' +
              '<h4 class="text-xs font-bold text-purple-950">Shared Inboxes (support@ & team@)</h4>' +
              '<p class="text-[11px] text-purple-700">Team collaboration with internal comments, assignment queues, and ticket statuses</p>' +
            '</div>' +
            '<span class="text-[10px] font-bold text-white bg-purple-600 px-3 py-1 rounded-full shadow-2xs">Multi-Agent</span>' +
          '</div>' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' +
            '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1.5">' +
              '<h4 class="text-xs font-bold text-slate-900">Full CRM Two-Way Sync</h4>' +
              '<p class="text-[11px] text-slate-500">Direct integration with Salesforce, HubSpot, and Pipedrive to sync contacts, leads, and deal history.</p>' +
            '</div>' +
            '<div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1.5">' +
              '<h4 class="text-xs font-bold text-slate-900">Premium Webhooks</h4>' +
              '<p class="text-[11px] text-slate-500">Live webhooks for Slack notifications, Jira ticket creation, Asana milestones, and Zoom video meetings.</p>' +
            '</div>' +
          '</div>' +
        '</div>';
      }
    }

    async function generateBurnerAlias() {
      const res = await fetch('/api/security/aliases', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ label: 'Quick Burner' })
      });
      const data = await res.json();
      alert('Created Alias: ' + data.alias_email);
      renderProTabContent('security');
    }

    // Helper actions to link Email -> Calendar, Tasks, Notes
    function addCurrentEmailToCalendar() {
      const activeMsg = (window.activeThreadMessage) || (window.messages && window.messages[0]);
      openProductivityDrawer('calendar');
      setTimeout(function() {
        showQuickAddEvent();
        const titleEl = document.getElementById('ev-input-title');
        if (titleEl && activeMsg) {
          titleEl.value = 'Discussion: ' + (activeMsg.subject || 'Email Review');
        }
      }, 150);
    }

    function addCurrentEmailToTask() {
      const activeMsg = (window.activeThreadMessage) || (window.messages && window.messages[0]);
      openProductivityDrawer('tasks');
      setTimeout(function() {
        const titleEl = document.getElementById('task-input-title');
        if (titleEl && activeMsg) {
          titleEl.value = 'Follow up: ' + (activeMsg.subject || 'Correspondence');
          titleEl.focus();
        }
      }, 150);
    }

    function saveCurrentEmailToNote() {
      const activeMsg = (window.activeThreadMessage) || (window.messages && window.messages[0]);
      openProductivityDrawer('notes');
      setTimeout(function() {
        showQuickAddNote();
        const titleEl = document.getElementById('note-input-title');
        const bodyEl = document.getElementById('note-input-body');
        if (activeMsg) {
          if (titleEl) titleEl.value = 'Note: ' + (activeMsg.subject || 'Email Thought');
          if (bodyEl) bodyEl.value = 'From: ' + (activeMsg.sender || activeMsg.sender_email || '') + '\n\n' + (activeMsg.snippet || activeMsg.body || '');
        }
      }, 150);
    }
  </script>

  <!-- Notification Center Modal (Mobile & Desktop) -->
  <div 
    id="inbox-notifications-modal" 
    class="hidden fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs select-none" 
    onclick="if(event.target === this) toggleInboxNotificationPanel()"
  >
    <div class="relative w-full max-w-sm rounded-3xl bg-white p-5 sm:p-6 shadow-2xl border border-slate-100 animate-in zoom-in-95 duration-200">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-xl bg-[#FFEFEA] text-[#E06748] flex items-center justify-center">
            <i class="fas fa-bell text-xs"></i>
          </div>
          <div>
            <h3 class="font-bold text-sm text-slate-900">Notifications & Alerts</h3>
            <p class="text-[11px] text-slate-400">Mailbox & Productivity</p>
          </div>
        </div>
        <button onclick="toggleInboxNotificationPanel()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center text-xs transition cursor-pointer">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="space-y-2.5 text-xs">
        <div class="p-3.5 rounded-2xl bg-[#F8F9FA] border border-slate-100 flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <i class="fas fa-check-circle text-xs"></i>
          </div>
          <div>
            <p class="font-bold text-slate-800">Push Notifications Enabled</p>
            <p class="text-slate-500 text-[11px] mt-0.5 leading-relaxed">Instant alerts for new incoming emails, mentions, and urgent messages.</p>
          </div>
        </div>

        <div class="p-3.5 rounded-2xl bg-[#F8F9FA] border border-slate-100 flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl bg-[#EFF4FF] text-[#3B66E0] flex items-center justify-center shrink-0">
            <i class="fas fa-envelope-open-text text-xs"></i>
          </div>
          <div>
            <p class="font-bold text-slate-800">Real-Time Maildir Sync</p>
            <p class="text-slate-500 text-[11px] mt-0.5 leading-relaxed">Nocturnal sync actively monitors your inbox and catch-all routes.</p>
          </div>
        </div>
      </div>

      <div class="mt-4 flex items-center gap-2">
        <button 
          onclick="handleInboxNotificationClick(true)" 
          class="flex-1 py-2.5 rounded-xl bg-[#F68D72] hover:bg-[#F57C60] text-white font-bold text-xs transition shadow-none cursor-pointer text-center"
        >
          Send Test Alert 🔔
        </button>
        <button 
          onclick="toggleInboxNotificationPanel()" 
          class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer"
        >
          Close
        </button>
      </div>
    </div>
  </div>


  <!-- Mobile Action Bottom Sheet for Hold-to-Delete -->
  <div id="mobile-action-sheet-backdrop" onclick="closeMobileActionSheet()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden transition-opacity duration-300 opacity-0 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div id="mobile-action-sheet-panel" onclick="event.stopPropagation()" class="w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl p-5 sm:p-6 transform translate-y-full sm:translate-y-4 transition-transform duration-300 ease-out border border-slate-200/80">
      
      <!-- Drag indicator bar for mobile -->
      <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-4 sm:hidden"></div>
      
      <!-- Sheet Header: Subject & Sender -->
      <div class="flex items-start gap-3 pb-4 border-b border-slate-100">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
          <img src="/raccoon-logo.svg" class="w-6 h-6 object-contain" alt="Raccoon Logo" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-[10px] font-extrabold uppercase tracking-wider text-[#6c4ff5]" id="sheet-folder-name">Email Options</div>
          <h3 id="sheet-msg-subject" class="text-sm font-bold text-slate-900 truncate">Subject</h3>
          <p id="sheet-msg-sender" class="text-xs text-slate-400 truncate">Sender</p>
        </div>
        <button onclick="closeMobileActionSheet()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition cursor-pointer">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- Action Options -->
      <div class="py-3 space-y-2">
        <!-- Delete Option (Permanently or Trash) -->
        <button id="sheet-btn-delete" onclick="handleSheetDelete()" class="w-full flex items-center justify-between p-3.5 rounded-2xl bg-rose-50/80 hover:bg-rose-100 text-rose-600 transition font-bold text-sm cursor-pointer border border-rose-200/60 active:scale-98">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center shadow-xs">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </div>
            <div class="text-left">
              <div id="sheet-delete-label" class="font-extrabold text-rose-700">Delete Permanently</div>
              <div id="sheet-delete-sub" class="text-[11px] text-rose-500/80 font-normal">Purge permanently from Trash</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-rose-400"></i>
        </button>

        <!-- Toggle Star -->
        <button id="sheet-btn-star" onclick="handleSheetStar()" class="w-full flex items-center justify-between p-3.5 rounded-2xl hover:bg-slate-50 text-slate-700 transition font-bold text-sm cursor-pointer border border-slate-100 active:scale-98">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 border border-amber-200/60 flex items-center justify-center">
              <i data-lucide="star" class="w-4 h-4"></i>
            </div>
            <div class="text-left">
              <div id="sheet-star-label" class="font-bold text-slate-800">Star Message</div>
              <div class="text-[11px] text-slate-400 font-normal">Mark as important</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>
        </button>

        <!-- Mark Unread/Read -->
        <button id="sheet-btn-read" onclick="handleSheetToggleRead()" class="w-full flex items-center justify-between p-3.5 rounded-2xl hover:bg-slate-50 text-slate-700 transition font-bold text-sm cursor-pointer border border-slate-100 active:scale-98">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/60 flex items-center justify-center">
              <i data-lucide="mail" class="w-4 h-4"></i>
            </div>
            <div class="text-left">
              <div id="sheet-read-label" class="font-bold text-slate-800">Mark as Unread</div>
              <div class="text-[11px] text-slate-400 font-normal">Keep for later review</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>
        </button>
      </div>

      <!-- Cancel Button -->
      <button type="button" onclick="closeMobileActionSheet()" class="w-full py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-sm transition mt-1 active:scale-98 cursor-pointer">
        Cancel
      </button>
    </div>
  </div>


  <!-- ========================================================================= -->
  <!-- MOBILE FLOATING ACTION BUTTON (COMPOSE EMAIL - PENCIL ICON)               -->
  <!-- ========================================================================= -->
  <!-- MOBILE FLOATING ACTION BUTTON (COMPOSE EMAIL - SOLID SLATE GRAY SQUIRCLE) -->
  <!-- ========================================================================= -->
  <button
    type="button"
    id="mobile-compose-fab"
    onclick="openComposeCard()"
    style="position: fixed !important; bottom: 84px !important; right: 18px !important; width: 56px !important; height: 56px !important; border-radius: 16px !important; background-color: #334155 !important; background: #334155 !important; color: #ffffff !important; display: flex !important; align-items: center !important; justify-content: center !important; box-shadow: 0 10px 25px -3px rgba(15, 23, 42, 0.45), 0 4px 6px -4px rgba(15, 23, 42, 0.25) !important; border: 1.5px solid rgba(255, 255, 255, 0.25) !important; z-index: 9999 !important; cursor: pointer !important; -webkit-tap-highlight-color: transparent !important; outline: none !important;"
    class="md:hidden active:scale-95 transition-transform"
    title="Compose Email"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 24px !important; height: 24px !important; stroke: #ffffff !important; display: block !important;"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
  </button>

  <!-- ========================================================================= -->
  <!-- MOBILE BOTTOM NAVIGATION (E-MAIL & PHOTOS/FILES TABS - MATCHING SCREENSHOT)-->
  <!-- ========================================================================= -->
  <nav id="mobile-bottom-nav" class="md:hidden fixed bottom-0 inset-x-0 h-16 bg-white border-t border-slate-200/90 flex items-center justify-around z-40 select-none pb-safe shadow-xs">
    
    <!-- Tab 1: E-Mail -->
    <button
      type="button"
      id="mobile-tab-email"
      onclick="switchMobileTab('email')"
      class="flex flex-col items-center justify-center py-1 flex-1 cursor-pointer group"
    >
      <div id="mobile-tab-email-pill" class="px-5 py-1 rounded-full bg-blue-100 text-blue-700 transition">
        <i data-lucide="mail" class="w-5 h-5 text-blue-700"></i>
      </div>
      <span id="mobile-tab-email-label" class="text-[11px] font-bold text-blue-700 mt-0.5">E-Mail</span>
    </button>

    <!-- Tab 2: Photos/Files -->
    <button
      type="button"
      id="mobile-tab-files"
      onclick="switchMobileTab('files')"
      class="flex flex-col items-center justify-center py-1 flex-1 cursor-pointer group"
    >
      <div id="mobile-tab-files-pill" class="px-5 py-1 rounded-full text-slate-500 hover:text-slate-900 transition">
        <i data-lucide="images" class="w-5 h-5 text-slate-500"></i>
      </div>
      <span id="mobile-tab-files-label" class="text-[11px] font-medium text-slate-500 mt-0.5">Photos/Files</span>
    </button>

  </nav>


  <!-- ========================================================================= -->
  <!-- DEDICATED FULL-SCREEN MOBILE PROFILE PAGE (PERFECT FOR ANDROID & MOBILE)   -->
  <!-- ========================================================================= -->
  <div id="mobile-profile-page" class="hidden fixed inset-0 z-50 bg-slate-100 flex flex-col overflow-y-auto select-none">
    
    <!-- Profile Page Header Bar -->
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 py-3 flex items-center justify-between shadow-2xs">
      <div class="flex items-center gap-3">
        <button type="button" onclick="closeMobileProfilePage()" class="p-2 -ml-1 text-slate-700 hover:text-slate-900 rounded-full hover:bg-slate-100 transition cursor-pointer" title="Back to Mailbox">
          <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </button>
        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Account & Profile</h2>
      </div>
      <button type="button" onclick="closeMobileProfilePage()" class="p-2 text-slate-400 hover:text-slate-700 rounded-full hover:bg-slate-100 transition cursor-pointer" title="Close">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </header>

    <!-- Profile Content Container -->
    <main class="flex-1 max-w-lg w-full mx-auto p-4 space-y-4 pb-12">
      
      <!-- User Profile Hero Card -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm text-center relative overflow-hidden">
        <!-- Background subtle gradient accent -->
        <div class="absolute inset-x-0 top-0 h-20 bg-linear-to-b from-slate-100 to-transparent"></div>
        
        <!-- Big Profile Avatar with Camera Edit Badge -->
        <!-- Direct Photo Upload Input -->
        <input type="file" id="mobile-direct-photo-input" accept="image/*" class="hidden" onchange="handleDirectPhotoUpload(event)" />
        <div class="relative w-24 h-24 mx-auto mb-3 cursor-pointer group" onclick="document.getElementById('mobile-direct-photo-input').click()" title="Tap to change profile photo">
          <div class="w-24 h-24 rounded-full ring-4 ring-white shadow-md overflow-hidden bg-slate-200 relative">
            <img id="mobile-profile-large-img" src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="user-avatar-img w-full h-full object-cover group-hover:scale-105 transition duration-200" alt="Profile Photo" onerror="this.src='/raccoon-logo.svg'" />
          </div>
          <!-- Camera badge docked on bottom-right of avatar -->
          <button type="button" onclick="event.stopPropagation(); document.getElementById('mobile-direct-photo-input').click()" class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-slate-900 hover:bg-black text-white flex items-center justify-center ring-2 ring-white shadow-md transition group-hover:scale-110 cursor-pointer" title="Take or Choose Photo">
            <i data-lucide="camera" class="w-4 h-4 text-white"></i>
          </button>
        </div>

        <!-- User Identity -->
        <h3 class="text-xl font-black text-slate-900 tracking-tight leading-tight">
          <?php echo htmlspecialchars($currentUser['name'] ?? 'Raccon Member'); ?>
        </h3>
        <p class="text-xs text-slate-500 font-mono mt-1">
          <?php echo htmlspecialchars($currentUser['email'] ?? 'user@racconmail.eu'); ?>
        </p>

        <!-- Active Status Badge -->
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-[11px] font-bold mt-3 shadow-2xs">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Active Account</span>
        </div>

        <!-- Action Buttons Row -->
        <div class="grid grid-cols-2 gap-2.5 mt-5 pt-4 border-t border-slate-100">
          <button type="button" onclick="openAvatarModal()" class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition active:scale-95 cursor-pointer">
            <i data-lucide="camera" class="w-4 h-4 text-slate-600"></i>
            <span>Change Photo</span>
          </button>
          <button type="button" onclick="openSettingsModal()" class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition active:scale-95 cursor-pointer">
            <i data-lucide="user-cog" class="w-4 h-4 text-slate-600"></i>
            <span>Edit Profile</span>
          </button>
        </div>
      </div>

      <!-- Storage & Mailbox Status Card -->
      <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
              <i data-lucide="hard-drive" class="w-4 h-4"></i>
            </div>
            <div>
              <h4 class="text-xs font-bold text-slate-900">Mailbox Storage</h4>
              <p class="text-[11px] text-slate-400">12.5 MB of 1 GB used</p>
            </div>
          </div>
          <span class="text-xs font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">1%</span>
        </div>
        <!-- Storage Progress Bar -->
        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
          <div class="bg-slate-800 h-full w-[2%] rounded-full"></div>
        </div>
        <p class="text-[11px] text-slate-400 flex items-center justify-between pt-1">
          <span>Lifetime Free Cloud Storage</span>
          <span class="text-slate-600 font-semibold">987.5 MB Free</span>
        </p>
      </div>

      <!-- Quick Navigation & App Links -->
      <div class="bg-white rounded-3xl p-3 border border-slate-200 shadow-sm space-y-1">
        <button type="button" onclick="openContactsModal()" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl hover:bg-slate-50 transition cursor-pointer text-left group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
              <i data-lucide="users" class="w-4 h-4"></i>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-800">Address Book & Contacts</p>
              <p class="text-[11px] text-slate-400">Manage saved recipients</p>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </button>

        <button type="button" onclick="openProductivityDrawer('attachments')" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl hover:bg-slate-50 transition cursor-pointer text-left group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:scale-105 transition">
              <i data-lucide="file-text" class="w-4 h-4"></i>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-800">Attachments & Photos</p>
              <p class="text-[11px] text-slate-400">Browse all received files</p>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </button>

        <button type="button" onclick="window.location.href='/forum'" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl hover:bg-slate-50 transition cursor-pointer text-left group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition">
              <i data-lucide="message-square" class="w-4 h-4"></i>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-800">Raccon Forum Community</p>
              <p class="text-[11px] text-slate-400">Discuss with other members</p>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </button>

        <button type="button" onclick="window.location.href='/stories'" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl hover:bg-slate-50 transition cursor-pointer text-left group">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition">
              <i data-lucide="book-open" class="w-4 h-4"></i>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-800">Raccon Stories</p>
              <p class="text-[11px] text-slate-400">Discover and read world stories</p>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </button>
      </div>

      <!-- Sign Out Button -->
      <div class="pt-2">
        <button type="button" onclick="logoutNow()" class="w-full py-3.5 px-4 rounded-2xl bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-sm border border-rose-200/80 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-2xs">
          <i data-lucide="log-out" class="w-4 h-4 text-rose-600"></i>
          <span>Sign Out of Account</span>
        </button>
      </div>

    </main>
  </div>

</body>
</html>
