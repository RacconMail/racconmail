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
    /* Active Message Card Elevation & Modern Turquoise Reading Glow */
    .msg-card-active {
      background: #FFFFFF !important;
      border-radius: 18px !important;
      border: 2px solid #06b6d4 !important;
      box-shadow: 0 0 25px 6px rgba(6, 182, 212, 0.35), 0 10px 25px -4px rgba(15, 23, 42, 0.08) !important;
      animation: readEmailGlow 3s ease-in-out infinite alternate !important;
    }

    @keyframes readEmailGlow {
      0% {
        box-shadow: 0 0 15px 3px rgba(6, 182, 212, 0.25), 0 8px 20px -4px rgba(15, 23, 42, 0.06);
        border-color: #06b6d4;
      }
      100% {
        box-shadow: 0 0 32px 10px rgba(6, 182, 212, 0.5), 0 12px 28px -4px rgba(6, 182, 212, 0.2);
        border-color: #22d3ee;
      }
    }

    /* Reading Pane Turquoise Ambient Accent & Reading Glow */
    #col-reader {
      box-shadow: inset 0 2px 0 0 #06b6d4, 0 0 30px 4px rgba(6, 182, 212, 0.12) !important;
    }

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
      0% { transform: scale(0.95); opacity: 0.9; }
      70% { transform: scale(1.75); opacity: 0; }
      100% { transform: scale(1.85); opacity: 0; }
    }

    .turquoise-glow-10s {
      animation: turquoiseSearchGlow10s 10s cubic-bezier(0.4, 0, 0.2, 1) forwards !important;
    }

    .turquoise-radar-10s {
      animation: turquoiseRadarHalo10s 2s cubic-bezier(0, 0, 0.2, 1) 5 !important;
    }

    /* Ultra-Modern Luminous Turquoise Primary Counter Pill */
    .turq-counter-pill {
      background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
      color: #FFFFFF !important;
      font-weight: 800 !important;
      font-size: 11px !important;
      font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif !important;
      letter-spacing: -0.02em !important;
      padding: 2px 9px !important;
      border-radius: 9999px !important;
      border: 1px solid rgba(255, 255, 255, 0.45) !important;
      box-shadow: 0 2px 10px -1px rgba(6, 182, 212, 0.55), 0 0 0 1px rgba(6, 182, 212, 0.25), inset 0 1px 1px rgba(255, 255, 255, 0.6) !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      line-height: 1.15 !important;
      text-shadow: 0 1px 2px rgba(8, 145, 178, 0.4) !important;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .turq-counter-pill:hover {
      box-shadow: 0 4px 16px -1px rgba(6, 182, 212, 0.8), 0 0 0 1.5px rgba(34, 211, 238, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.8) !important;
      transform: translateY(-1px) scale(1.05);
    }

    /* Ultra-Modern Frosted Glass Turquoise Counter Pill */
    .turq-counter-soft {
      background: rgba(6, 182, 212, 0.09) !important;
      color: #0e7490 !important;
      font-weight: 700 !important;
      font-size: 11px !important;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
      letter-spacing: -0.01em !important;
      padding: 1.5px 8px !important;
      border-radius: 9999px !important;
      border: 1px solid rgba(6, 182, 212, 0.26) !important;
      box-shadow: 0 1px 2px rgba(6, 182, 212, 0.08), inset 0 1px 1px rgba(255, 255, 255, 0.8) !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      line-height: 1.2 !important;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .turq-counter-soft:hover {
      background: rgba(6, 182, 212, 0.18) !important;
      border-color: rgba(6, 182, 212, 0.45) !important;
      color: #0891b2 !important;
      box-shadow: 0 2px 6px rgba(6, 182, 212, 0.18) !important;
      transform: translateY(-0.5px);
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
      <button onclick="toggleAppSwitcher()" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="RacconMail Apps">
        <i data-lucide="layout-grid" class="h-4 w-4"></i>
      </button>

      <!-- Brand Mascot Logo & Name: Clicking refreshes the mail without logging out -->
      <a href="/inbox" onclick="window.location.reload(); return false;" class="flex items-center gap-2 group cursor-pointer mr-1" title="RacconMail (Click to refresh)">
        <img src="/raccoon-logo.png" alt="RacconMail" class="h-7 w-7 object-contain group-hover:scale-105 transition" />
        <span class="font-bold text-base tracking-tight hidden sm:inline" style="color: #64748B !important;">RacconMail</span>
      </a>

      <!-- ProtonMail Sidebar Toggle Button -->
      <button
        type="button"
        id="btn-toggle-sidebar"
        onclick="toggleSidebarAction()"
        class="p-2 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-50 text-slate-400 hover:text-slate-700 transition cursor-pointer flex items-center justify-center shadow-2xs"
        title="Toggle Sidebar"
      >
        <i data-lucide="panel-left" class="h-4 w-4"></i>
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
        <button onclick="toggleUserDropdown(event)" id="user-profile-btn" class="flex items-center gap-2 p-1 sm:px-2.5 sm:py-1 rounded-full border border-slate-200/90 hover:bg-slate-50 transition cursor-pointer select-none shadow-2xs">
          <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="h-6 w-6 rounded-full object-cover shrink-0 border border-slate-200" />
          <span id="header-user-email" class="text-xs font-medium text-slate-700 hidden lg:inline max-w-[130px] truncate"><?php echo htmlspecialchars($currentUser['email']); ?></span>
          <i data-lucide="chevron-down" class="h-3 w-3 text-slate-400"></i>
        </button>

        <!-- Account Popover Dropdown -->
        <div id="account-dropdown" class="hidden absolute right-0 top-full mt-2 w-64 bg-white rounded-2xl border border-slate-200 shadow-2xl p-2 z-50 text-slate-900 space-y-1">
          <div class="px-3 py-2.5 border-b border-slate-100">
            <p id="dropdown-user-name" class="font-bold text-xs text-slate-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
            <p id="dropdown-user-email" class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
            <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400">
              <span>Storage: <strong>12.5 MB / 1 GB</strong></span>
              <span class="text-emerald-500 font-bold">1%</span>
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1 overflow-hidden">
              <div class="bg-slate-900 h-full w-[2%] rounded-full"></div>
            </div>
          </div>
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
            <span>RacconMail Community</span>
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
    <aside id="sidebar-panel" class="w-60 border-r border-slate-100 flex flex-col shrink-0 bg-white h-full p-3 overflow-y-auto select-none fixed inset-y-0 left-0 z-50 md:relative md:translate-x-0 transition-all duration-200 -translate-x-full md:flex">
      
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
          <span id="badge-inbox" class="sidebar-badge turq-counter-pill"><?php echo $counts['inbox_unread']; ?></span>
        </button>

        <!-- 2. UNREAD -->
        <button onclick="switchFolder('Unread')" id="folder-btn-unread" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="mail" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Unread</span>
          </div>
          <span id="badge-unread" class="sidebar-badge turq-counter-soft"><?php echo $counts['unread_total']; ?></span>
        </button>

        <!-- 3. STARRED / FAVORITES -->
        <button onclick="switchFolder('Starred')" id="folder-btn-starred" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="star" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Starred</span>
          </div>
          <span id="badge-starred" class="sidebar-badge turq-counter-soft"><?php echo $counts['starred_total']; ?></span>
        </button>

        <!-- 4. DRAFTS -->
        <button onclick="switchFolder('Drafts')" id="folder-btn-drafts" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="file-edit" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Drafts</span>
          </div>
          <span id="badge-drafts" class="sidebar-badge turq-counter-soft"><?php echo $counts['drafts_total']; ?></span>
        </button>

        <!-- 5. SENT -->
        <button onclick="switchFolder('Sent')" id="folder-btn-sent" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="send" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Sent</span>
          </div>
          <span id="badge-sent" class="sidebar-badge turq-counter-soft"><?php echo $counts['sent_total']; ?></span>
        </button>

        <!-- 6. ARCHIVE -->
        <button onclick="switchFolder('Archive')" id="folder-btn-archive" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="archive" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Archive</span>
          </div>
          <span id="badge-archive" class="sidebar-badge turq-counter-soft"><?php echo $counts['archive_total']; ?></span>
        </button>

        <!-- 7. SPAM -->
        <button onclick="switchFolder('Spam')" id="folder-btn-spam" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="shield-alert" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Spam</span>
          </div>
          <span id="badge-spam" class="sidebar-badge turq-counter-soft"><?php echo $counts['spam_total']; ?></span>
        </button>

        <!-- 8. TRASH -->
        <div class="flex items-center justify-between w-full">
          <button onclick="switchFolder('Trash')" id="folder-btn-trash" class="flex-1 flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="trash-2" class="h-4 w-4 shrink-0"></i>
              <span class="sidebar-text truncate">Trash</span>
            </div>
            <span id="badge-trash" class="sidebar-badge turq-counter-soft"><?php echo $counts['trash_total']; ?></span>
          </button>
          <button onclick="emptyTrashAction()" title="Empty Trash" class="p-1.5 text-slate-400 hover:text-rose-600 transition cursor-pointer">
            <i data-lucide="eraser" class="h-3.5 w-3.5"></i>
          </button>
        </div>

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

      <!-- Section: ECOSYSTEM (Modern Gray Badges) -->
      <div class="sidebar-section-title text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-1 mt-5">Ecosystem</div>
      <div class="space-y-1">
        <button onclick="openStoriesModal()" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-500 group-hover:text-slate-800 group-hover:bg-slate-200/80 transition shrink-0">
            <i data-lucide="book-open" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">World Stories</span>
        </button>
        <button onclick="window.location.href='/news'" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-500 group-hover:text-slate-800 group-hover:bg-slate-200/80 transition shrink-0">
            <i data-lucide="newspaper" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">News Wire</span>
        </button>
        <button onclick="window.location.href='/forum'" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-500 group-hover:text-slate-800 group-hover:bg-slate-200/80 transition shrink-0">
            <i data-lucide="message-square" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Forum</span>
        </button>
        <button onclick="openContactsModal()" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold nav-pill-inactive group">
          <div class="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-500 group-hover:text-slate-800 group-hover:bg-slate-200/80 transition shrink-0">
            <i data-lucide="users" class="h-3.5 w-3.5"></i>
          </div>
          <span class="sidebar-text truncate">Address Book</span>
        </button>
      </div>

      <!-- Bottom User Profile Card -->
      <div class="mt-auto pt-4 border-t border-slate-100">
        <div onclick="toggleUserDropdown(event)" class="flex items-center justify-between p-2 rounded-2xl hover:bg-slate-50 transition cursor-pointer select-none">
          <div class="flex items-center gap-2.5 min-w-0">
            <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="h-8 w-8 rounded-full object-cover shrink-0 border border-slate-200" />
            <div class="sidebar-text min-w-0 flex-1">
              <p class="text-xs font-bold text-slate-900 truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
              <p class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
            </div>
          </div>
          <i data-lucide="chevron-up" class="sidebar-text h-4 w-4 text-slate-400 shrink-0"></i>
        </div>
      </div>

    </aside>

    <!-- ========================================================================= -->
    <!-- COLUMN 2: MIDDLE INBOX LIST PANEL (~360px)                                -->
    <!-- ========================================================================= -->
    <section id="col-inbox" class="w-full md:w-[350px] lg:w-[380px] border-r border-slate-100 flex flex-col shrink-0 bg-white h-full overflow-hidden select-none">
      
      <!-- Middle Header: Folder Title + Badge + Subtitle + Action Buttons -->
      <div class="p-5 pb-3 shrink-0">
        <div class="flex items-center justify-between mb-1">
          <div class="flex items-center gap-2.5">
            <h1 id="current-folder-display-title" class="text-2xl font-bold text-slate-900 tracking-tight">Inbox</h1>
            <span id="coral-inbox-badge" class="turq-counter-pill"><?php echo $counts['inbox_unread']; ?></span>
          </div>

          <!-- Mark all read and refresh buttons -->
          <div class="flex items-center gap-1 text-slate-400">
            <button onclick="refreshMailbox()" class="p-1.5 rounded-xl hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer" title="Refresh">
              <i data-lucide="rotate-cw" class="h-4 w-4"></i>
            </button>
            <button onclick="markAllAsReadAction()" class="p-1.5 rounded-xl hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer" title="Mark all as read">
              <i data-lucide="check-check" class="h-4 w-4"></i>
            </button>
          </div>
        </div>

        <p class="text-xs text-slate-400 leading-normal mb-3 font-normal">
          Maximizing Efficiency, and Achieving Email Zen.
        </p>

        <!-- Quick Filter Tabs: All, Unread, Starred -->
        <div class="flex items-center gap-1.5 mb-2">
          <button onclick="setListFilter('all')" id="filter-btn-all" class="px-3 py-1 rounded-full text-xs font-bold bg-slate-900 text-white cursor-pointer">All</button>
          <button onclick="setListFilter('unread')" id="filter-btn-unread" class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer">Unread</button>
          <button onclick="setListFilter('starred')" id="filter-btn-starred" class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer">Starred</button>
        </div>
      </div>

      <!-- Messages Thread List Container -->
      <div id="threads-container" class="flex-1 overflow-y-auto px-3 py-1 space-y-1.5 pb-6">
        <!-- Rendered dynamically by renderMessageList() -->
      </div>

    </section>

    <!-- ========================================================================= -->
    <!-- COLUMN 3: RIGHT ALL EMAILS & CONVERSATION READING PANE                    -->
    <!-- ========================================================================= -->
    <main id="col-reader" class="hidden md:flex flex-1 flex-col bg-white h-full overflow-hidden relative">
      
      <!-- Right Top Bar: All Emails + Top Actions + [ Compose ] + [ + New Identity ] -->
      <header class="h-16 px-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white select-none z-20">
        <div class="flex items-center gap-3">
          <button onclick="backToMobileInbox()" class="md:hidden p-1.5 rounded-lg border border-slate-200 text-slate-600">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
          </button>
          <h2 id="reader-top-title" class="text-lg font-bold text-slate-900 tracking-tight">All Emails</h2>
        </div>

        <!-- Action Buttons: Reply, Star, Trash, Archive, Compose, New Identity -->
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-1 text-slate-400">
            <button onclick="replyCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Reply">
              <i data-lucide="reply" class="h-4 w-4"></i>
            </button>
            <button onclick="starCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-amber-500 transition cursor-pointer" title="Star message">
              <i data-lucide="star" id="reader-star-icon" class="h-4 w-4"></i>
            </button>
            <button onclick="archiveCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Archive">
              <i data-lucide="archive" class="h-4 w-4"></i>
            </button>
            <button onclick="trashCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-rose-600 transition cursor-pointer" title="Delete / Move to Trash">
              <i data-lucide="trash-2" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </header>

      <!-- Thread Conversation View Area -->
      <div id="thread-conversation-container" class="flex-1 overflow-y-auto p-6 md:p-8 space-y-6 pb-36">
        <!-- Rendered dynamically by renderConversationThread() -->
      </div>

    </main>

  </div>

  <!-- ALL MODALS (SETTINGS, CONTACTS, STORIES, APP SWITCHER) -->
  
  <!-- 1. Settings & Profile Modal -->
  <div id="settings-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
        <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
          <i data-lucide="settings" class="h-5 w-5 text-slate-500"></i>
          <span>RacconMail Settings</span>
        </h3>
        <button onclick="closeSettingsModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-4 w-4"></i></button>
      </div>
      <div class="space-y-4 text-xs">
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

  <!-- 3. App Switcher Popover (RacconMail Ecosystem - Modern Gray Badges) -->
  <div id="app-switcher-modal" class="hidden fixed top-16 left-4 bg-white rounded-2xl shadow-2xl border border-slate-200/90 p-4 z-50 w-80">
    <div class="flex items-center justify-between mb-3 px-1">
      <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">RacconMail Ecosystem</p>
      <span class="text-[10px] font-mono font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full border border-slate-200/80">v2.0</span>
    </div>
    <div class="grid grid-cols-3 gap-2.5 text-center text-xs">
      <a href="/inbox" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition shadow-2xs">
          <i data-lucide="mail" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Mail</span>
      </a>
      <a href="/forum" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition shadow-2xs">
          <i data-lucide="message-square" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Forum</span>
      </a>
      <a href="/news" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition shadow-2xs">
          <i data-lucide="newspaper" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">News</span>
      </a>
      <a href="/stories" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition shadow-2xs">
          <i data-lucide="book-open" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Stories</span>
      </a>
      <button onclick="openContactsModal(); toggleAppSwitcher();" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group cursor-pointer w-full">
        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition shadow-2xs">
          <i data-lucide="users" class="h-5 w-5"></i>
        </div>
        <span class="font-semibold text-[11px]">Contacts</span>
      </button>
      <a href="/downloads/RacconMail-v2.0.apk" class="p-2.5 rounded-xl hover:bg-slate-50 flex flex-col items-center gap-2 text-slate-700 transition group">
        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition shadow-2xs">
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
    lucide.createIcons();

    // Central Data Model with real messages & realistic seed
    let currentFolder = 'Inbox';
    let currentFilter = 'all';
    let currentSelectedThreadIndex = 0;
    let isSidebarCollapsed = false;

    // Database of messages across all folders initialized from real SQLite database
    const initialDbMessages = <?php echo json_encode($initialInboxMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];

    const mailDatabase = {
      'Inbox': initialDbMessages.map(m => ({
        id: m.id,
        sender: m.sender_email,
        senderName: m.sender_name || m.sender_email,
        time: (m.created_at || '').slice(11, 16) || 'Today',
        subject: m.subject || '(no subject)',
        isStarred: !!m.is_starred,
        isRead: !!m.is_read,
        avatar: '/raccoon-logo.png',
        body_html: m.body_html || '',
        thread: [
          {
            sender: m.sender_email,
            to: m.recipient_email || '<?php echo htmlspecialchars($currentUser['email']); ?>',
            date: m.created_at || 'Recently',
            avatar: '/raccoon-logo.png',
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

    // Switch Folder (Inbox, Unread, Starred, Sent, Drafts, Archive, Spam, Trash, All)
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

      // Update title in middle column
      const titleEl = document.getElementById('current-folder-display-title');
      if (titleEl) titleEl.innerText = folderName;

      renderMessageList();
      await fetchFolderMessages(folderName);
    }

    // Filter message list (All, Unread, Starred)
    function setListFilter(filter) {
      currentFilter = filter;
      ['all', 'unread', 'starred'].forEach(f => {
        const btn = document.getElementById('filter-btn-' + f);
        if (btn) {
          if (f === filter) {
            btn.className = 'px-3 py-1 rounded-full text-xs font-bold bg-slate-900 text-white cursor-pointer';
          } else {
            btn.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer';
          }
        }
      });
      renderMessageList();
    }

    // Render messages in Middle Column
    function renderMessageList(searchQuery = '') {
      const container = document.getElementById('threads-container');
      if (!container) return;

      let list = [];
      if (currentFolder === 'All') {
        list = [].concat(...Object.values(mailDatabase));
      } else if (currentFolder === 'Unread') {
        list = (mailDatabase['Inbox'] || []).filter(m => !m.isRead);
      } else if (currentFolder === 'Starred') {
        list = [].concat(...Object.values(mailDatabase)).filter(m => m.isStarred);
      } else {
        list = mailDatabase[currentFolder] || [];
      }

      // Apply sub-filters
      if (currentFilter === 'unread') list = list.filter(m => !m.isRead);
      if (currentFilter === 'starred') list = list.filter(m => m.isStarred);

      // Apply search query
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        list = list.filter(m => 
          m.senderName.toLowerCase().includes(q) ||
          m.subject.toLowerCase().includes(q) ||
          m.sender.toLowerCase().includes(q)
        );
      }

      // Update Coral Badge count
      const badge = document.getElementById('coral-inbox-badge');
      if (badge) badge.innerText = list.length;

      if (list.length === 0) {
        container.innerHTML = `
          <div class="p-8 text-center text-slate-400 select-none">
            <i data-lucide="inbox" class="h-10 w-10 mx-auto mb-2 opacity-50"></i>
            <p class="font-bold text-sm text-slate-600">No messages in ${currentFolder}</p>
            <p class="text-xs text-slate-400 mt-1">Achieving Email Zen.</p>
          </div>
        `;
        lucide.createIcons();
        renderEmptyConversation();
        return;
      }

      container.innerHTML = list.map((msg, idx) => {
        const isActive = idx === currentSelectedThreadIndex;
        const isRead = !!msg.isRead;
        const rawSnippet = (msg.thread && msg.thread.length > 0 ? (msg.thread[msg.thread.length - 1]?.body || '') : (msg.preview || ''));
        const cleanSnippet = rawSnippet.replace(/<[^>]+>/g, '').slice(0, 75).trim() + (rawSnippet.length > 75 ? '...' : '');

        return `
          <div onclick="selectThreadItem(${idx})" id="thread-item-${idx}" class="${isActive ? 'msg-card-active' : 'msg-card-inactive'} p-3.5 transition cursor-pointer">
            <div class="flex items-start gap-3">
              <img src="${msg.avatar}" class="h-10 w-10 rounded-full object-cover shrink-0 border border-slate-100" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-0.5">
                  <div class="flex items-center gap-1.5 truncate min-w-0">
                    ${isRead ? '<span class="w-2.5 h-2.5 rounded-full bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.95)] ring-2 ring-cyan-400/20 shrink-0 inline-block mr-0.5" title="Read message"></span>' : ''}
                    <span class="${isRead ? 'font-normal text-xs text-slate-600' : 'font-black text-sm text-black tracking-tight'} truncate">${msg.senderName}</span>
                  </div>
                  <span class="text-[11px] ${isRead ? 'font-normal text-slate-400' : 'font-bold text-slate-900'} shrink-0 font-mono">${msg.time}</span>
                </div>
                <p class="${isRead ? 'text-xs font-normal text-slate-500' : 'text-xs sm:text-sm font-black text-black leading-snug'} truncate mb-0.5">${msg.subject}</p>
                <p class="${isRead ? 'text-[11px] font-normal text-slate-400' : 'text-xs font-bold text-slate-800'} line-clamp-1 truncate">${cleanSnippet}</p>
              </div>
              <div class="flex items-center gap-0.5 shrink-0">
                <button onclick="event.stopPropagation(); deleteMessageById('${msg.id}')" title="Delete" class="p-1 text-slate-300 hover:text-rose-500 transition cursor-pointer">
                  <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                </button>
                <button onclick="event.stopPropagation(); toggleStarItem('${msg.id}')" title="Star" class="p-1 text-slate-300 hover:text-amber-500 transition cursor-pointer">
                  <i data-lucide="star" class="h-3.5 w-3.5 ${msg.isStarred ? 'text-amber-500 fill-amber-500' : ''}"></i>
                </button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      lucide.createIcons();
      renderConversationThread(list[currentSelectedThreadIndex] || list[0]);
    }

    // Select Thread Item
    function selectThreadItem(index) {
      currentSelectedThreadIndex = index;
      renderMessageList(document.getElementById('global-search')?.value || '');

      // On mobile view: show reading pane and hide middle column
      if (window.innerWidth < 768) {
        document.getElementById('col-inbox')?.classList.add('hidden');
        document.getElementById('col-reader')?.classList.remove('hidden');
        document.getElementById('col-reader')?.classList.add('flex');
      }
    }

    // Render Conversation in Right Column
    function renderConversationThread(msg) {
      const container = document.getElementById('thread-conversation-container');
      const titleEl = document.getElementById('reader-top-title');
      if (!container || !msg) return;

      if (titleEl) titleEl.innerText = msg.subject || 'All Emails';

      // Update reader star icon
      const starIcon = document.getElementById('reader-star-icon');
      if (starIcon) {
        starIcon.className = `h-4 w-4 ${msg.isStarred ? 'text-amber-500 fill-amber-500' : 'text-slate-400'}`;
      }

      container.innerHTML = msg.thread.map((card, i) => `
        <div class="border-b border-slate-100 pb-6">
          <div class="flex items-start gap-3 mb-3">
            <img src="${card.avatar}" class="h-9 w-9 rounded-full object-cover shrink-0 border border-slate-200" />
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <span class="font-bold text-xs text-slate-900">${card.sender}</span>
                <div class="flex items-center gap-2 text-slate-400">
                  <span class="text-[11px]">${card.date}</span>
                  <button class="hover:text-slate-700 p-0.5"><i data-lucide="more-vertical" class="h-3.5 w-3.5"></i></button>
                </div>
              </div>
              <div class="text-[11px] text-slate-400 flex items-center gap-1">
                <span>to ${card.to}</span>
                <i data-lucide="chevron-down" class="h-3 w-3"></i>
              </div>
            </div>
          </div>
          <div class="pl-12 text-xs text-slate-600 leading-relaxed space-y-2 whitespace-pre-line">
            ${card.body}
          </div>
        </div>
      `).join('') + `
        <!-- Quick Reply Box at bottom -->
        <div class="pt-4 flex items-center gap-3">
          <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" class="h-8 w-8 rounded-full border border-slate-200" />
          <div class="flex-1 relative flex items-center">
            <input id="quick-reply-input" type="text" placeholder="Write a quick reply..." class="w-full bg-slate-50 rounded-full border border-slate-200 pl-4 pr-24 py-2 text-xs text-slate-800 outline-none focus:border-slate-400" />
            <button onclick="sendQuickReply()" class="absolute right-1.5 px-3 py-1 bg-slate-900 hover:bg-black text-white rounded-full text-xs font-semibold cursor-pointer">Reply</button>
          </div>
        </div>
      `;

      lucide.createIcons();
    }

    function renderEmptyConversation() {
      const container = document.getElementById('thread-conversation-container');
      if (container) {
        container.innerHTML = `
          <div class="flex flex-col items-center justify-center h-full p-8 text-center text-slate-400">
            <img src="/raccoon-logo.png" class="h-20 w-20 object-contain opacity-60 mb-3" />
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
            avatar: '/raccoon-logo.png',
            body_html: m.body_html || '',
            thread: [
              {
                sender: m.sender_email,
                to: m.recipient_email || '<?php echo htmlspecialchars($currentUser['email']); ?>',
                date: m.created_at || 'Recently',
                avatar: '/raccoon-logo.png',
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

    // Refresh real counts from SQLite API
    async function refreshFolderCounts() {
      try {
        const res = await fetch('/api/folders');
        if (res.ok) {
          const data = await res.json();
          if (data && data.standard) {
            const std = data.standard;
            const bInbox = document.getElementById('badge-inbox');
            if (bInbox) bInbox.innerText = std.inbox?.total || 0;
            const bCoral = document.getElementById('coral-inbox-badge');
            if (bCoral && currentFolder === 'Inbox') bCoral.innerText = std.inbox?.total || 0;
            const bUnread = document.getElementById('badge-unread');
            if (bUnread) bUnread.innerText = std.unread?.total || 0;
            const bStarred = document.getElementById('badge-starred');
            if (bStarred) bStarred.innerText = std.favorites?.total || 0;
            const bDrafts = document.getElementById('badge-drafts');
            if (bDrafts) bDrafts.innerText = std.drafts?.total || 0;
            const bSent = document.getElementById('badge-sent');
            if (bSent) bSent.innerText = std.sent?.total || 0;
            const bArchive = document.getElementById('badge-archive');
            if (bArchive) bArchive.innerText = std.archive?.total || 0;
            const bSpam = document.getElementById('badge-spam');
            if (bSpam) bSpam.innerText = std.spam?.total || 0;
            const bTrash = document.getElementById('badge-trash');
            if (bTrash) bTrash.innerText = std.trash?.total || 0;
          }
        }
      } catch (e) {}
    }

    function updateBadgeCounts() {
      const bInbox = document.getElementById('badge-inbox');
      if (bInbox && mailDatabase['Inbox']) bInbox.innerText = mailDatabase['Inbox'].length;
      const bCoral = document.getElementById('coral-inbox-badge');
      if (bCoral && mailDatabase[currentFolder]) bCoral.innerText = mailDatabase[currentFolder].length;
      const bTrash = document.getElementById('badge-trash');
      if (bTrash && mailDatabase['Trash']) bTrash.innerText = mailDatabase['Trash'].length;
    }

    // Delete single message permanently or move to trash
    async function deleteMessageById(msgId) {
      let removedMsg = null;
      for (const f in mailDatabase) {
        if (Array.isArray(mailDatabase[f])) {
          const idx = mailDatabase[f].findIndex(m => m.id === msgId);
          if (idx !== -1) {
            removedMsg = mailDatabase[f].splice(idx, 1)[0];
          }
        }
      }

      const isCurrentlyTrash = (currentFolder.toLowerCase() === 'trash');
      if (!isCurrentlyTrash && removedMsg) {
        if (!mailDatabase['Trash']) mailDatabase['Trash'] = [];
        mailDatabase['Trash'].unshift(removedMsg);
      }

      currentSelectedThreadIndex = 0;
      renderMessageList(document.getElementById('global-search')?.value || '');
      updateBadgeCounts();

      try {
        if (isCurrentlyTrash) {
          await fetch('/api/messages/' + encodeURIComponent(msgId) + '?permanent=1', { method: 'DELETE' });
        } else {
          await fetch('/api/messages/' + encodeURIComponent(msgId) + '/trash', { method: 'POST' });
        }
      } catch (err) {
        console.error('Delete error:', err);
      }
      refreshFolderCounts();
    }

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

    // Send Email
    async function sendEmailNow() {
      const btn = document.getElementById('btn-send-email');
      const to = document.getElementById('compose-to')?.value.trim();
      const subject = document.getElementById('compose-subject')?.value.trim();
      const body = document.getElementById('compose-body')?.value.trim();

      if (!to || !body) {
        alert('Please fill in both the recipient (To) and body text.');
        return;
      }

      if (btn) {
        btn.disabled = true;
        btn.innerText = 'Sending...';
      }

      try {
        const res = await fetch('/api/messages/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ to, subject, body })
        });
      } catch (_) {}

      // Add to Sent folder
      mailDatabase['Sent'].unshift({
        id: 'sent_' + Date.now(),
        sender: '<?php echo htmlspecialchars($currentUser['email']); ?>',
        senderName: 'To: ' + to,
        time: 'Just now',
        subject: subject,
        isStarred: false,
        isRead: true,
        avatar: '<?php echo htmlspecialchars($currentUser['avatar']); ?>',
        thread: [
          {
            sender: '<?php echo htmlspecialchars($currentUser['email']); ?>',
            to: to,
            date: 'Just now',
            avatar: '<?php echo htmlspecialchars($currentUser['avatar']); ?>',
            body: body
          }
        ]
      });

      showRaccoonSuccessModal();
      refreshFolderCounts();
      closeComposeCard();
      if (btn) {
        btn.disabled = false;
        btn.innerText = 'Send';
      }
    }

    function sendQuickReply() {
      const inp = document.getElementById('quick-reply-input');
      const val = inp?.value.trim();
      if (!val) return;

      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'];
      const msg = list[currentSelectedThreadIndex];
      if (msg) {
        msg.thread.push({
          sender: '<?php echo htmlspecialchars($currentUser['email']); ?>',
          to: msg.sender,
          date: 'Just now',
          avatar: '<?php echo htmlspecialchars($currentUser['avatar']); ?>',
          body: val
        });
        inp.value = '';
        showRaccoonSuccessModal();
        renderConversationThread(msg);
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
      document.getElementById('col-inbox')?.classList.remove('hidden');
      document.getElementById('col-reader')?.classList.add('hidden');
    }

    // Dropdowns & Modals
    function toggleUserDropdown(e) {
      if (e) e.stopPropagation();
      document.getElementById('account-dropdown')?.classList.toggle('hidden');
    }

    function toggleAppSwitcher() {
      document.getElementById('app-switcher-modal')?.classList.toggle('hidden');
    }

    function openSettingsModal() {
      document.getElementById('settings-modal')?.classList.remove('hidden');
    }

    function closeSettingsModal() {
      document.getElementById('settings-modal')?.classList.add('hidden');
    }

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
        <span class="text-[10px] text-slate-400 font-mono">RacconMail</span>
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
  <button
    type="button"
    onclick="openComposeCard()"
    id="mobile-floating-compose-btn"
    class="md:hidden fixed bottom-6 right-6 z-[9990] w-14 h-14 rounded-full bg-slate-900 hover:bg-black text-white shadow-[0_10px_25px_rgba(15,23,42,0.45)] flex items-center justify-center transition active:scale-95 cursor-pointer border border-slate-700/40"
    title="Write New Message"
    aria-label="Write New Message"
  >
    <i class="fas fa-pen text-lg text-white"></i>
  </button>

  <!-- ========================================================================= -->
  <!-- RACCOON SUCCESS POPUP MODAL ("Email sent successfully! 🐾")                -->
  <!-- ========================================================================= -->
  <div id="raccoon-success-modal" class="hidden fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-all duration-200" onclick="if(event.target === this) closeRaccoonSuccessModal()">
    <div class="relative w-full max-w-sm rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-200/90 text-center animate-in zoom-in-95 duration-200">
      <button onclick="closeRaccoonSuccessModal()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition cursor-pointer">
        <i class="fas fa-times text-xs"></i>
      </button>
      <div class="relative inline-block mb-3">
        <img src="/raccoon-logo.png" alt="Raccon Mascot" class="h-24 w-24 mx-auto object-contain filter drop-shadow-md animate-bounce" />
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
  <!-- FLOATING MOBILE SEARCH BUTTON (BOTTOM-LEFT CORNER)                         -->
  <!-- ========================================================================= -->
  <button
    type="button"
    onclick="openMobileSearchSheet()"
    id="mobile-floating-search-btn"
    class="md:hidden fixed bottom-6 left-6 z-[9990] w-14 h-14 rounded-full bg-white hover:bg-cyan-50/60 shadow-[0_10px_30px_rgba(6,182,212,0.38)] flex items-center justify-center transition active:scale-95 cursor-pointer border-2 border-cyan-400 ring-4 ring-cyan-400/25 group"
    title="Search Emails (Turquoise Loupe)"
    aria-label="Search Emails"
  >
    <div class="relative flex items-center justify-center">
      <span class="absolute w-8 h-8 rounded-full bg-cyan-400/20 group-hover:bg-cyan-400/35 transition-all duration-300 blur-xs"></span>
      <i data-lucide="search" class="relative h-6 w-6 text-cyan-500 stroke-[2.75] group-hover:scale-110 transition filter drop-shadow-[0_1px_3px_rgba(6,182,212,0.45)]"></i>
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
