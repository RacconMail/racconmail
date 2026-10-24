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
    $currentUser['avatar'] = '/raccoon-logo.png';
}

$uid = $currentUser['id'];
$uemail = $currentUser['email'];

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
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM messages WHERE user_id = ? AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = '' OR folder_id = 'inbox')");
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
    $stmtMsgs = $db->prepare("SELECT id, user_id, sender_name, sender_email, recipient_email, cc, bcc, reply_to, subject, body, body_html, is_read, is_starred, is_archived, is_spam, is_trashed, is_draft, folder_id, created_at FROM messages WHERE user_id = ? AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = '' OR folder_id = 'inbox') ORDER BY created_at DESC LIMIT 50");
    $stmtMsgs->execute([$uid]);
    $initialInboxMessages = $stmtMsgs->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100/70 notranslate" translate="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="RacconMail" />
  <meta name="theme-color" content="#FFFFFF" />
  <meta name="google" content="notranslate" />
  <title>RacconMail &mdash; All Emails</title>
  <meta name="description" content="Clever, minimal, encrypted email inbox with email zen precision." />

  <!-- Favicon Suite -->
  <link rel="icon" href="/favicon.ico" sizes="48x48" />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
  <link rel="manifest" href="/site.webmanifest" />

  <!-- Tailwind CSS & Google Fonts -->
  <script src="https://cdn.tailwindcss.com"></script>
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

  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    /* Modern 10-Second Turquoise Search Loop Glow Animation */
    @keyframes turquoiseSearchGlow10s {
      0% {
        box-shadow: 0 0 0 0 rgba(6, 182, 212, 0.85), 0 0 20px 4px rgba(6, 182, 212, 0.5);
        transform: scale(1);
      }
      10%, 30%, 50%, 70%, 90% {
        box-shadow: 0 0 45px 14px rgba(6, 182, 212, 0.9), 0 0 80px 24px rgba(6, 182, 212, 0.45);
        transform: scale(1.1);
        border-color: #22d3ee;
      }
      20%, 40%, 60%, 80% {
        box-shadow: 0 0 20px 4px rgba(6, 182, 212, 0.55), 0 0 40px 10px rgba(6, 182, 212, 0.3);
        transform: scale(1);
        border-color: #06b6d4;
      }
      100% {
        box-shadow: 0 10px 30px rgba(6, 182, 212, 0.38);
        transform: scale(1);
        border-color: #06b6d4;
      }
    }

    @keyframes turquoiseRadarHalo10s {
      0% {
        transform: scale(0.95);
        opacity: 0.9;
      }
      70% {
        transform: scale(1.75);
        opacity: 0;
      }
      100% {
        transform: scale(1.85);
        opacity: 0;
      }
    }

    .turquoise-glow-10s {
      animation: turquoiseSearchGlow10s 10s cubic-bezier(0.4, 0, 0.2, 1) forwards !important;
    }

    .turquoise-radar-10s {
      animation: turquoiseRadarHalo10s 2s cubic-bezier(0, 0, 0.2, 1) 5 !important; /* 5 pulses of 2s = exactly 10s */
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

    /* Active Black Capsule Pill matching user's design */
    .nav-pill-active {
      background: linear-gradient(180deg, #27272A 0%, #09090B 100%) !important;
      color: #FFFFFF !important;
      border-radius: 14px !important;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18) !important;
    }
    .nav-pill-active i, .nav-pill-active svg {
      color: #FFFFFF !important;
      stroke: #FFFFFF !important;
    }

    /* Inactive nav items */
    .nav-pill-inactive {
      color: #64748B;
      border-radius: 12px;
      transition: all 0.15s ease;
      cursor: pointer;
    }
    .nav-pill-inactive:hover {
      background-color: #F8FAFC;
      color: #0F172A;
    }
    .nav-pill-inactive:hover i {
      color: #64748B;
      stroke: #64748B;
    }

    /* Active Message Card Elevation */
    .msg-card-active {
      background: #FFFFFF !important;
      border-radius: 18px !important;
      box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.09), 0 4px 10px -2px rgba(0, 0, 0, 0.04) !important;
      border: 1px solid #F1F5F9 !important;
    }

    /* Inactive message row */
    .msg-card-inactive {
      background: transparent;
      border-radius: 14px;
      transition: background-color 0.15s ease;
      cursor: pointer;
    }
    .msg-card-inactive:hover {
      background-color: #F8FAFC;
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
  </style>
</head>
<body class="flex flex-col h-screen w-screen overflow-hidden bg-white text-slate-900 select-none">

  <!-- ========================================================================= -->
  <!-- PROTONMAIL-STYLE TOP TOGGLEBAR (WHITE BG, SUBTLE BORDER, GRAY WRITE, BLACK BUTTONS) -->
  <!-- ========================================================================= -->
  <header id="proton-togglebar" class="h-14 border-b border-slate-200/90 bg-white flex items-center justify-between px-3 sm:px-6 shrink-0 z-30 w-full select-none" style="background-color: #FFFFFF !important; border-bottom: 1px solid #E2E8F0 !important;">
    
    <!-- Left Section: App Switcher + Logo + Brand + Sidebar Toggle -->
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <!-- App Switcher (Proton Waffle 9-dots) -->
      <button
    type="button"
    onclick="openMobileSearchSheet()"
    id="mobile-floating-search-btn"
    class="turquoise-glow-10s md:hidden fixed bottom-6 left-6 z-[9990] w-14 h-14 rounded-full bg-white hover:bg-cyan-50/60 shadow-[0_10px_30px_rgba(6,182,212,0.38)] flex items-center justify-center transition active:scale-95 cursor-pointer border-2 border-cyan-400 ring-4 ring-cyan-400/25 group"
    title="Search Emails (Turquoise Loupe)"
    aria-label="Search Emails"
  >
    <!-- 10-Second Expanding Radar Halo -->
    <span class="turquoise-radar-10s pointer-events-none absolute -inset-2 rounded-full bg-cyan-400/35 blur-xs"></span>

    <div class="relative flex items-center justify-center z-10">
      <span class="absolute w-8 h-8 rounded-full bg-cyan-400/25 group-hover:bg-cyan-400/40 transition-all duration-300 blur-xs"></span>
      <i data-lucide="search" class="relative h-6 w-6 text-cyan-500 stroke-[2.75] group-hover:scale-110 transition filter drop-shadow-[0_1px_4px_rgba(6,182,212,0.6)]"></i>
    </div>
  </button>

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

</body>
</html>
