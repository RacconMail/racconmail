<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days persistent session
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
if (empty($_SESSION['current_user_id'])) {
    header("Location: /auth?mode=register");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="notranslate" translate="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="Raccon Mail" />
  <meta name="theme-color" content="#FFFFFF" />
  <meta name="google" content="notranslate" />
  <title>Raccon Mail — Clever email for web & mobile</title>
  <meta name="description" content="Raccon Mail: a super modern, secure inbox on the web and your smartphone." />
    <!-- Comprehensive Favicon Suite for Google Search & All Browsers -->
  <link rel="icon" href="/favicon.ico" sizes="48x48" />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
  <link rel="manifest" href="/site.webmanifest" />
  
  <!-- FontAwesome 6 (Harmony SocialEngine icons) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Google AdSense -->
  <!-- AdSense script removed from private inbox for privacy and policy compliance -->

  <!-- Early Theme Initializer (Strictly Light Bubble White) -->
  <script>
    (function() {
      localStorage.setItem('raccon_theme', 'light');
      document.documentElement.className = 'theme-light';
      document.documentElement.classList.remove('dark', 'theme-dark');
    })();
  </script>

  <!-- Tailwind CSS Configured with Dynamic CSS Variables for Seamless Live Theming -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            background: 'var(--background)',
            foreground: 'var(--foreground)',
            card: 'var(--card)',
            'card-foreground': 'var(--card-foreground)',
            primary: 'var(--primary)',
            'primary-foreground': 'var(--primary-foreground)',
            secondary: 'var(--secondary)',
            'secondary-foreground': 'var(--secondary-foreground)',
            muted: 'var(--muted)',
            'muted-foreground': 'var(--muted-foreground)',
            accent: 'var(--accent)',
            'accent-foreground': 'var(--accent-foreground)',
            border: 'var(--border)',
            'border-light': 'var(--border-light)'
          },
          fontFamily: {
            sans: ['"DM Sans"', 'sans-serif'],
            display: ['"Space Grotesk"', 'sans-serif'],
            handwriting: ['"Caveat"', 'cursive']
          }
        }
      }
    }
  </script>

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Custom Postcard Styles with Dynamic Cache Busting -->
  <link rel="stylesheet" href="/css/raccoon-mail.css?v=<?php echo time(); ?>" />

  <style>
    /* Direct adjustments for the Bubble White layout */
    .bubble-compose-pill {
      background: #18181B !important;
      color: #FFFFFF !important;
      border-radius: 12px !important;
      padding: 8px 16px !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 7px !important;
      cursor: pointer !important;
      transition: all 0.15s ease !important;
    }
    .bubble-compose-pill:hover {
      background: #000000 !important;
      transform: translateY(-0.5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
  </style>
</head>
<body class="bg-white text-slate-900 flex flex-col h-screen w-screen relative select-none overflow-hidden m-0 p-0">

  <!-- TOP APP BAR (WHITE BACKGROUND, GRAY WRITE, BLACK BUTTONS, RACCON LOGO) -->
  <header class="h-14 border-b border-slate-200/80 bg-white flex items-center justify-between px-6 shrink-0 z-20" style="background-color: #ffffff !important; border-bottom: 1px solid #e2e8f0 !important; color: #64748b !important;">
    <!-- Left: Raccon Logo + Brand Name in Gray + Sidebar Toggle (Black Button) -->
    <div class="flex items-center gap-3">
      <img src="/raccoon-logo.png" alt="Raccon" class="h-6 w-6 object-contain" />
      <span class="font-bold text-base tracking-tight text-slate-500" style="color: #64748b !important;">Raccon</span>
      <button onclick="app.toggleMobileSidebar()" class="ml-2 px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer shadow-xs" style="background-color: #0f172a !important; color: #ffffff !important;" title="Toggle Sidebar">
        <i data-lucide="panel-left" class="h-4 w-4 text-white" style="color: #ffffff !important;"></i>
      </button>
    </div>

    <!-- Center: View Title in Gray -->
    <div class="hidden sm:flex items-center gap-2">
      <h1 id="current-folder-title" class="text-sm font-semibold text-slate-400" style="color: #94a3b8 !important;">All Mail</h1>
      <p id="current-folder-subtitle" class="hidden"></p>
      <button id="empty-folder-btn" class="hidden"></button>
    </div>

    <!-- Right: Black Button with White Write (Compose) -->
    <div class="flex items-center gap-2">
      <button
        onclick="app.openCompose()"
        id="btn-top-compose"
        class="flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-black text-white px-4 py-2 text-xs font-semibold shadow-xs transition active:scale-95 cursor-pointer"
        style="background-color: #0f172a !important; color: #ffffff !important;"
        title="Compose Message"
      >
        <i data-lucide="edit-3" class="h-3.5 w-3.5 text-white" style="color: #ffffff !important;"></i>
        <span class="text-white" style="color: #ffffff !important;">Compose</span>
      </button>
    </div>
  </header>

  <!-- MAIN 3-COLUMN WORKSPACE (EDGE-TO-EDGE) -->
  <div class="flex-1 flex overflow-hidden min-h-0 relative bg-white">

      <!-- 1. NAVIGATION SIDEBAR -->
      <aside id="sidebar-panel" class="sidebar fixed inset-y-0 left-0 z-50 w-64 bg-white transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-200 ease-in-out shrink-0 flex flex-col border-r border-slate-100 p-3 overflow-y-auto select-none shadow-xl md:shadow-none">
        
        <!-- Mobile Close Header -->
        <div class="p-2 border-b border-slate-100 flex items-center justify-between md:hidden mb-2">
          <div class="flex items-center gap-2 font-bold text-sm text-slate-900">
            <img src="/raccoon-logo.png" class="h-5 w-5 object-contain" />
            <span>Raccon Mail</span>
          </div>
          <button onclick="app.closeMobileSidebar()" class="p-1 rounded-full hover:bg-slate-100 text-slate-500">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>

        <!-- Section 1: MENU -->
        <div class="bubble-section-label">Menu</div>
        <div class="space-y-0.5">
          <button onclick="app.navTo('Inbox')" id="nav-item-all-mail" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="layout-grid" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">All Mail</span>
            </div>
          </button>
          <button onclick="app.navTo('Starred')" id="nav-item-favorites" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="bookmark" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Favorites</span>
            </div>
            <span id="badge-favorites" class="text-xs font-mono opacity-60"></span>
          </button>
          <button onclick="app.navTo('Spam')" id="nav-item-ignored" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="bell-off" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Spam & Ignored</span>
            </div>
            <span id="badge-spam" class="text-xs font-mono opacity-60"></span>
          </button>
        </div>

        <!-- Section 2: MAILBOX -->
        <div class="bubble-section-label">Mailbox</div>
        <div class="space-y-0.5">
          <!-- Active State: Black Capsule Pill -->
          <button onclick="app.navTo('Inbox')" id="nav-item-inbox" class="bubble-nav-item active">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="mail" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Inbox</span>
            </div>
            <span id="sidebar-inbox-badge" class="bubble-count-coral hidden"></span>
          </button>
          <button onclick="app.navTo('Sent')" id="nav-item-sent" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="send" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Sent</span>
            </div>
            <span id="badge-sent" class="text-xs font-mono opacity-60"></span>
          </button>
          <button onclick="app.navTo('Drafts')" id="nav-item-drafts" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="file-edit" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Drafts</span>
            </div>
            <span id="badge-drafts" class="text-xs font-mono opacity-60"></span>
          </button>
          <button onclick="app.navTo('Archive')" id="nav-item-archive" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="archive" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Archive</span>
            </div>
            <span id="badge-archive" class="text-xs font-mono opacity-60"></span>
          </button>
          <button onclick="app.navTo('Trash')" id="nav-item-trash" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="trash-2" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Trash</span>
            </div>
            <span id="badge-trash" class="text-xs font-mono opacity-60"></span>
          </button>
        </div>

        <!-- Section 3: CATEGORIES -->
        <div class="flex items-center justify-between pr-2">
          <div class="bubble-section-label">Categories</div>
          <button onclick="app.openNewFolderModal()" title="Add Category" class="text-slate-400 hover:text-slate-700 transition cursor-pointer">
            <i data-lucide="plus" class="h-3.5 w-3.5"></i>
          </button>
        </div>
        <div class="space-y-0.5">
          <button type="button" onclick="app.filterByCategory('personal')" data-category-filter="personal" class="bubble-nav-item category-nav-btn">
            <div class="flex items-center gap-2.5 truncate">
              <span class="category-dot-pill border-teal-500"></span>
              <span class="truncate">Personal</span>
            </div>
            <span id="badge-cat-personal" class="text-xs font-mono opacity-60"></span>
          </button>
          <button type="button" onclick="app.filterByCategory('work')" data-category-filter="work" class="bubble-nav-item category-nav-btn">
            <div class="flex items-center gap-2.5 truncate">
              <span class="category-dot-pill border-purple-500"></span>
              <span class="truncate">Work</span>
            </div>
            <span id="badge-cat-work" class="text-xs font-mono opacity-60"></span>
          </button>
          <button type="button" onclick="app.filterByCategory('community')" data-category-filter="community" class="bubble-nav-item category-nav-btn">
            <div class="flex items-center gap-2.5 truncate">
              <span class="category-dot-pill border-blue-500"></span>
              <span class="truncate">Community</span>
            </div>
            <span id="badge-cat-community" class="text-xs font-mono opacity-60"></span>
          </button>
          <div id="custom-folders-list" class="space-y-0.5"></div>
        </div>

        <!-- Section 4: ECOSYSTEM -->
        <div class="bubble-section-label">Ecosystem</div>
        <div class="space-y-0.5">
          <button onclick="app.openStoriesModal()" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="zap" class="h-4 w-4 shrink-0 opacity-70 text-amber-500"></i>
              <span class="truncate">World Stories</span>
            </div>
          </button>
          <button onclick="window.location.href='/news'" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="newspaper" class="h-4 w-4 shrink-0 opacity-70 text-blue-500"></i>
              <span class="truncate">News Wire</span>
            </div>
          </button>
          <button onclick="window.location.href='/forum'" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="message-square" class="h-4 w-4 shrink-0 opacity-70 text-cyan-500"></i>
              <span class="truncate">Forum</span>
            </div>
          </button>
          <button onclick="app.openContactsModal()" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="contact" class="h-4 w-4 shrink-0 opacity-70 text-teal-500"></i>
              <span class="truncate">Address Book</span>
            </div>
          </button>
        </div>

        <!-- Section 5: ACCOUNT & SETTINGS -->
        <div class="bubble-section-label">Account</div>
        <div class="space-y-0.5">
          <button onclick="app.openSettingsModal()" class="bubble-nav-item">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="settings" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Settings & IMAP</span>
            </div>
          </button>
          <button onclick="app.logout()" class="bubble-nav-item text-rose-600 hover:text-rose-700">
            <div class="flex items-center gap-2.5 truncate">
              <i data-lucide="log-out" class="h-4 w-4 shrink-0 opacity-70"></i>
              <span class="truncate">Sign Out</span>
            </div>
          </button>
        </div>

        <!-- Bottom User Profile Row (Interactive Account Dropdown) -->
        <div class="mt-auto pt-3 border-t border-slate-100 relative">
          <!-- Account Popover / Dropdown Menu -->
          <div id="account-dropdown" class="hidden absolute bottom-full left-0 right-0 mb-2 bg-white rounded-2xl border border-slate-200 shadow-2xl p-2 z-50 text-slate-900 space-y-1">
            <div class="px-3 py-2 border-b border-slate-100">
              <p id="dropdown-user-name" class="font-bold text-xs text-slate-900 truncate">Raccon User</p>
              <p id="dropdown-user-email" class="text-[11px] text-slate-400 truncate">user@racconmail.eu</p>
            </div>
            <button onclick="app.openSettingsModal(); app.toggleAccountDropdown()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-slate-700 hover:bg-slate-50 transition cursor-pointer text-left">
              <i data-lucide="settings" class="h-4 w-4 text-slate-500"></i>
              <span>Profile & Settings</span>
            </button>
            <button onclick="app.openContactsModal(); app.toggleAccountDropdown()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-slate-700 hover:bg-slate-50 transition cursor-pointer text-left">
              <i data-lucide="contact" class="h-4 w-4 text-teal-500"></i>
              <span>Address Book</span>
            </button>
            <button onclick="app.logout()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-rose-600 hover:bg-rose-50 transition cursor-pointer text-left">
              <i data-lucide="log-out" class="h-4 w-4 text-rose-500"></i>
              <span>Sign Out</span>
            </button>
          </div>

          <div id="user-profile-btn" class="flex items-center justify-between p-2 rounded-2xl hover:bg-slate-100 transition cursor-pointer select-none" onclick="app.toggleAccountDropdown(event)">
            <div class="flex items-center gap-2.5 min-w-0 pointer-events-none">
              <div id="active-user-avatar-badge" class="h-8 w-8 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shrink-0">
                <span id="active-user-badge-initial">R</span>
              </div>
              <div class="min-w-0 flex-1">
                <p id="active-user-name" class="text-xs font-bold text-slate-900 truncate">Raccon User</p>
                <p id="active-user-email" class="text-[11px] text-slate-400 truncate">user@racconmail.eu</p>
              </div>
            </div>
            <i data-lucide="chevron-up" class="h-4 w-4 text-slate-400 shrink-0 pointer-events-none"></i>
          </div>
        </div>
      </aside>

      <!-- 2. MESSAGES LIST PANEL -->
      <section id="messages-panel" class="w-full md:w-80 lg:w-[380px] border-r border-slate-100 flex flex-col shrink-0 overflow-hidden bg-white">
        
        <!-- Header: Title + Dynamic Badge + Subtitle -->
        <div class="pt-5 px-5 pb-2 shrink-0">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <h2 id="middle-folder-title" class="text-xl font-bold text-slate-900 tracking-tight">Inbox</h2>
              <span id="inbox-coral-badge" class="bubble-count-coral hidden"></span>
            </div>
            <div class="flex items-center gap-1">
              <button onclick="app.refreshCurrentFolder()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer" title="Refresh">
                <i data-lucide="rotate-cw" class="h-4 w-4"></i>
              </button>
              <button onclick="app.markAllRead()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer" title="Mark all read">
                <i data-lucide="check" class="h-4 w-4"></i>
              </button>
            </div>
          </div>
          <p id="middle-folder-subtitle" class="text-xs text-slate-400 leading-normal mt-1 font-normal"></p>

          <!-- Search Bar with Click-to-Open Suggestions -->
          <div id="global-search-container" class="mt-3.5 mb-1 relative">
            <div class="bubble-search-box">
              <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none"></i>
              <input
                id="global-search"
                type="text"
                placeholder="Search"
                onclick="app.onSearchClick(event)"
                onfocus="app.onSearchClick(event)"
                oninput="app.onSearchInput(this.value)"
                onkeydown="app.onSearchKeyDown(event)"
                autocomplete="off"
              />
              <button onclick="app.clearSearch()" id="search-clear-btn" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="h-3.5 w-3.5"></i>
              </button>
            </div>
            <!-- Search Suggestions Dropdown (Only appears when clicked) -->
            <div id="search-suggestions-dropdown" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 overflow-hidden divide-y divide-slate-100 max-h-80 overflow-y-auto flex flex-col"></div>
          </div>
        </div>

        <!-- Batch Action Toolbar (revealed when selected) -->
        <div id="batch-toolbar" class="hidden bg-slate-900 text-white px-3.5 py-2 flex items-center justify-between text-xs shrink-0 transition">
          <div class="flex items-center gap-2">
            <input type="checkbox" id="select-all-checkbox" onchange="app.toggleSelectAll(this.checked)" class="rounded border-slate-600 accent-white cursor-pointer" />
            <span id="selected-count-label" class="font-bold text-white">0 selected</span>
          </div>
          <div class="flex items-center gap-1">
            <button onclick="app.batchAction('mark_read')" title="Mark as read" class="p-1 rounded text-white hover:bg-slate-800"><i data-lucide="mail-open" class="h-4 w-4 text-white"></i></button>
            <button onclick="app.batchAction('trash')" title="Move to trash" class="p-1 rounded text-white hover:bg-slate-800"><i data-lucide="trash-2" class="h-4 w-4 text-white"></i></button>
          </div>
        </div>

        <!-- Messages List Container -->
        <div id="messages-list" class="flex-1 overflow-y-auto px-2 py-1 space-y-1 pb-20 md:pb-2">
          <!-- Dynamic bubble message cards populated by app.js -->
        </div>
      </section>

      <!-- 3. EMAIL READER & DETAILS PANEL -->
      <main class="hidden md:flex flex-1 flex-col bg-white overflow-hidden relative" id="reader-panel">
        <div id="reader-content" class="h-full flex flex-col overflow-y-auto">
          <!-- Default Empty State -->
          <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400 select-none">
            <div class="relative mb-6">
              <img src="/raccoon-logo.png" alt="Raccon" class="raccoon-anim relative h-28 w-28 object-contain opacity-75" />
            </div>
            <h2 class="text-xl font-bold text-slate-900">Select an email to read</h2>
            <p class="mt-2 max-w-sm text-xs text-slate-400">
              Clean, private and beautifully minimal mail client with bubble zen precision.
            </p>
          </div>
        </div>
      </main>
    </div>

  <!-- FLOATING CAPSULE BOTTOM BAR (MOBILE ONLY) -->
  <nav id="mobile-floating-capsule" class="md:hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-slate-900 text-white px-7 py-2.5 rounded-full shadow-2xl flex items-center gap-8 border border-slate-700 safe-area-bottom select-none">
    <button onclick="app.toggleMobileSidebar()" class="text-white hover:text-slate-200 transition cursor-pointer flex items-center justify-center p-1.5 active:scale-90" title="All Folders (List)">
      <i data-lucide="list" class="h-5 w-5 text-white"></i>
    </button>
    <button onclick="app.openCompose()" class="text-white hover:text-slate-200 transition cursor-pointer flex items-center justify-center p-1.5 active:scale-90" title="Compose New Email">
      <i data-lucide="edit-3" class="h-5 w-5 text-white"></i>
    </button>
  </nav>

  <!-- FLOATING ACTION BUTTON (FAB) FOR DESKTOP / TABLET QUICK COMPOSE -->
  <button
    id="desktop-compose-fab"
    onclick="app.openCompose()"
    title="Compose New Message"
    class="hidden fixed bottom-6 right-6 z-30 h-14 w-14 rounded-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white shadow-[0_0_20px_rgba(14,165,233,0.45)] items-center justify-center transform active:scale-90 transition border-2 border-sky-400/40 cursor-pointer"
  >
    <i data-lucide="pencil" class="h-6 w-6 text-white"></i>
  </button>

  <!-- FLOATING BUBBLE WHITE COMPOSE CARD MODAL -->
  <div id="compose-modal" class="hidden fixed inset-0 md:inset-auto md:bottom-6 md:right-8 z-50 w-full md:w-[520px] bg-white md:rounded-3xl border border-slate-200/90 shadow-2xl flex flex-col overflow-hidden transition-all duration-200">
    <!-- Header bar -->
    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between shrink-0 select-none cursor-pointer" onclick="if(app.isComposeMinimized) app.minimizeCompose()">
      <div class="flex items-center gap-2">
        <h3 id="compose-title" class="font-bold text-sm text-slate-900">New Email</h3>
      </div>
      <div class="flex items-center gap-1 text-slate-400">
        <span id="draft-status-pill" class="text-[11px] text-slate-400 font-mono mr-1"></span>
        <button type="button" onclick="event.stopPropagation(); app.minimizeCompose()" class="p-1 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition cursor-pointer" title="Minimize">
          <i data-lucide="minus" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="event.stopPropagation(); app.closeCompose()" class="p-1 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition cursor-pointer" title="Close">
          <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
      </div>
    </div>

    <!-- Compose Form -->
    <div id="compose-body-container" class="p-5 flex-1 flex flex-col gap-3 overflow-y-auto max-h-[70vh] bg-white text-slate-900">
      <!-- From Field -->
      <div class="flex items-center gap-2.5 border-b border-slate-100 pb-2.5">
        <span class="text-xs font-semibold text-slate-400 w-12 shrink-0">From:</span>
        <div class="bubble-pill-chip">
          <img src="/raccoon-logo.png" class="h-4 w-4 rounded-full object-cover" />
          <span id="compose-from-display" class="truncate max-w-[220px]">user@racconmail.eu</span>
        </div>
      </div>

      <!-- To Field -->
      <div class="relative flex items-center gap-2.5 border-b border-slate-100 pb-2.5">
        <span class="text-xs font-semibold text-slate-400 w-12 shrink-0">To:</span>
        <input
          id="compose-to"
          type="text"
          placeholder="recipient@example.com"
          class="flex-1 bg-transparent text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 outline-none"
          oninput="app.onRecipientInput(this.value)"
        />
        <button type="button" onclick="app.toggleCcBcc()" class="text-xs font-semibold text-slate-400 hover:text-slate-800 transition cursor-pointer">Cc/Bcc</button>
        <!-- Contact Auto-complete Suggestions Dropdown -->
        <div id="contact-suggestions" class="hidden absolute left-12 top-full mt-1 w-72 rounded-2xl border border-slate-200 bg-white p-1 shadow-2xl z-50 text-slate-900"></div>
      </div>

      <!-- CC / BCC Rows (Collapsible) -->
      <div id="cc-bcc-row" class="hidden space-y-2 border-b border-slate-100 pb-2">
        <div class="flex items-center gap-2.5">
          <span class="text-xs font-semibold text-slate-400 w-12 shrink-0">Cc:</span>
          <input id="compose-cc" type="text" placeholder="Carbon copy..." class="flex-1 bg-transparent text-xs text-slate-900 placeholder:text-slate-400 outline-none" />
        </div>
        <div class="flex items-center gap-2.5">
          <span class="text-xs font-semibold text-slate-400 w-12 shrink-0">Bcc:</span>
          <input id="compose-bcc" type="text" placeholder="Blind carbon copy..." class="flex-1 bg-transparent text-xs text-slate-900 placeholder:text-slate-400 outline-none" />
        </div>
      </div>

      <!-- Subject Field -->
      <div class="flex items-center gap-2.5 border-b border-slate-100 pb-2.5">
        <span class="text-xs font-semibold text-slate-400 w-12 shrink-0">Subject:</span>
        <input id="compose-subject" type="text" placeholder="Email subject..." class="flex-1 bg-transparent text-xs sm:text-sm font-semibold text-slate-900 placeholder:text-slate-400 outline-none" />
      </div>

      <!-- Editable Message Body -->
      <div
        id="compose-editor"
        contenteditable="true"
        placeholder="Write your email here..."
        class="min-h-[140px] max-h-[260px] overflow-y-auto text-xs sm:text-sm text-slate-800 leading-relaxed py-2 bg-transparent outline-none placeholder:text-slate-400"
      ></div>

      <!-- File Attachments List -->
      <div id="compose-attachments-list" class="flex flex-wrap gap-2 pt-2 border-t border-slate-100"></div>
    </div>

    <!-- Compose Bottom Action Bar -->
    <div id="compose-footer-container" class="px-5 py-3 border-t border-slate-100 bg-white flex items-center justify-between shrink-0">
      <button
        onclick="app.sendMessage()"
        id="send-mail-btn"
        class="flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-black text-white px-5 py-2 text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer"
        title="Send Email"
      >
        <span>Send</span>
      </button>

      <div class="flex items-center gap-1 text-slate-400">
        <label class="p-1.5 rounded-lg hover:text-slate-800 hover:bg-slate-100 transition cursor-pointer" title="Attach Files">
          <i data-lucide="paperclip" class="h-4 w-4"></i>
          <input type="file" id="compose-file-input" multiple class="hidden" onchange="app.handleFilesSelected(this.files)" />
        </label>
        <button type="button" onclick="app.insertLinkPrompt()" class="p-1.5 rounded-lg hover:text-slate-800 hover:bg-slate-100 transition cursor-pointer" title="Insert Link">
          <i data-lucide="link" class="h-4 w-4"></i>
        </button>
        <button type="button" onclick="app.insertSignature()" class="p-1.5 rounded-lg hover:text-slate-800 hover:bg-slate-100 transition cursor-pointer" title="Insert Signature">
          <i data-lucide="pen-tool" class="h-4 w-4"></i>
        </button>
        <button type="button" onclick="app.saveDraft()" class="p-1.5 rounded-lg hover:text-slate-800 hover:bg-slate-100 transition cursor-pointer" title="Save Draft">
          <i data-lucide="save" class="h-4 w-4"></i>
        </button>
        <button type="button" onclick="app.discardDraft()" class="p-1.5 rounded-lg hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer" title="Discard">
          <i data-lucide="trash-2" class="h-4 w-4"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- NEW CUSTOM FOLDER MODAL -->
  <div id="new-folder-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onclick="if (event.target === this) app.closeNewFolderModal()">
    <div class="w-full max-w-sm rounded-3xl bg-card p-6 shadow-soft border border-border">
      <h3 class="font-display text-lg font-bold">Create Custom Folder</h3>
      <p class="text-xs text-muted-foreground mt-1">Organize your clever postcards & letters.</p>
      <input
        id="new-folder-name"
        type="text"
        placeholder="e.g. Acorns & Stashes"
        class="mt-4 w-full rounded-xl border border-border bg-muted/50 p-2.5 text-sm outline-none focus:border-primary"
      />
      <div class="mt-5 flex items-center justify-end gap-2">
        <button onclick="app.closeNewFolderModal()" class="rounded-full px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-muted transition">Cancel</button>
        <button onclick="app.createFolder()" class="rounded-full bg-primary px-5 py-2 text-xs font-medium text-primary-foreground hover:bg-black transition">Create</button>
      </div>
    </div>
  </div>

  <!-- ADDRESS BOOK / CONTACTS MODAL -->
  <div id="contacts-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onclick="if (event.target === this) app.closeContactsModal()">
    <div class="w-full max-w-2xl rounded-3xl bg-card shadow-soft border border-border flex flex-col max-h-[85vh] overflow-hidden">
      <div class="p-5 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i data-lucide="contact" class="h-5 w-5 text-primary"></i>
          <h3 class="font-display text-lg font-bold">Address Book & Contacts</h3>
        </div>
        <button onclick="app.closeContactsModal()" class="p-1 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>
      <div class="p-4 border-b border-border bg-muted/30 flex items-center justify-between gap-3">
        <input
          type="text"
          placeholder="Filter contacts..."
          oninput="app.filterContacts(this.value)"
          class="flex-1 rounded-full border border-border bg-card px-4 py-1.5 text-xs outline-none"
        />
        <button onclick="app.openNewContactForm()" class="flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-xs font-medium text-primary-foreground hover:bg-black transition">
          <i data-lucide="user-plus" class="h-3.5 w-3.5"></i>
          <span>Add Contact</span>
        </button>
      </div>
      <div id="contacts-list" class="flex-1 overflow-y-auto p-4 grid grid-cols-1 md:grid-cols-2 gap-3"></div>
    </div>
  </div>

  <!-- NEW/EDIT CONTACT MODAL -->
  <div id="contact-form-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onclick="if (event.target === this) app.closeContactForm()">
    <div class="w-full max-w-md rounded-3xl bg-card p-6 shadow-soft border border-border">
      <h3 id="contact-form-title" class="font-display text-lg font-bold">New Contact</h3>
      <div class="mt-4 space-y-3 text-sm">
        <input id="contact-form-name" type="text" placeholder="Full Name (e.g. Luna)" class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none" />
        <input id="contact-form-email" type="email" placeholder="Email address" class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none" />
        <input id="contact-form-phone" type="text" placeholder="Phone number" class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none" />
        <textarea id="contact-form-notes" placeholder="Notes (e.g. night photographer)" rows="2" class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none"></textarea>
      </div>
      <div class="mt-5 flex justify-end gap-2">
        <button onclick="app.closeContactForm()" class="rounded-full px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-muted">Cancel</button>
        <button onclick="app.saveContact()" class="rounded-full bg-primary px-5 py-2 text-xs font-medium text-primary-foreground hover:bg-black">Save Contact</button>
      </div>
    </div>
  </div>

  <!-- SETTINGS & REAL IMAP / SMTP MODAL -->
  <div id="settings-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onclick="if (event.target === this) app.closeSettingsModal()">
    <div class="w-full max-w-lg rounded-3xl bg-card shadow-soft border border-border flex flex-col max-h-[90vh] overflow-hidden">
      <div class="p-5 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i data-lucide="sliders" class="h-5 w-5 text-primary"></i>
          <h3 class="font-display text-lg font-bold">Mail Client Settings</h3>
        </div>
        <button onclick="app.closeSettingsModal()" class="p-1 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <div class="p-5 flex-1 overflow-y-auto space-y-5 text-xs">
        <!-- Profile & Signature -->
        <div class="space-y-3">
          <h4 class="font-display text-sm font-bold text-foreground">User Profile & Signature</h4>
          <div>
            <label class="block text-muted-foreground mb-1">Display Name</label>
            <input id="settings-name" type="text" class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none" />
          </div>
          <div>
            <label class="block text-muted-foreground mb-1">Custom Postcard Signature</label>
            <textarea id="settings-signature" rows="2" class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none"></textarea>
          </div>
        </div>

        <!-- Real External IMAP / SMTP Server (Custom SMTP / IMAP) -->
        <div class="space-y-3 border-t border-border pt-4">
          <div class="flex items-center justify-between">
            <h4 class="font-display text-sm font-bold text-foreground">External Raccon / SMTP Server</h4>
            <span class="text-[10px] bg-accent px-2 py-0.5 rounded-full font-medium">Optional</span>
          </div>
          <p class="text-[11px] text-muted-foreground">
            Connect your custom SMTP or external mail server to send live external emails!
          </p>
          <div class="grid grid-cols-3 gap-2">
            <div class="col-span-2">
              <label class="block text-muted-foreground mb-1">SMTP Host (e.g. mail.racconmail.eu)</label>
              <input id="settings-smtp-host" type="text" placeholder="mail.racconmail.eu" class="w-full rounded-xl border border-border bg-muted/40 p-2 text-xs outline-none" />
            </div>
            <div>
              <label class="block text-muted-foreground mb-1">Port</label>
              <input id="settings-smtp-port" type="number" placeholder="587" class="w-full rounded-xl border border-border bg-muted/40 p-2 text-xs outline-none" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-muted-foreground mb-1">SMTP Username / Email</label>
              <input id="settings-smtp-user" type="text" placeholder="user@racconmail.eu" class="w-full rounded-xl border border-border bg-muted/40 p-2 text-xs outline-none" />
            </div>
            <div>
              <label class="block text-muted-foreground mb-1">SMTP App Password</label>
              <input id="settings-smtp-pass" type="password" placeholder="••••••••••••" class="w-full rounded-xl border border-border bg-muted/40 p-2 text-xs outline-none" />
            </div>
          </div>
          <div class="flex items-center justify-between pt-1">
            <button onclick="app.testSmtpConnection()" class="flex items-center gap-1.5 rounded-full border border-border bg-muted hover:bg-card px-3 py-1.5 text-xs font-medium transition">
              <i data-lucide="check-circle" class="h-3.5 w-3.5 text-muted-foreground"></i>
              <span>Test Connection</span>
            </button>
            <span id="smtp-test-status" class="text-[11px]"></span>
          </div>
        </div>

        <!-- Sound & Notifications -->
        <div class="border-t border-border pt-4 flex items-center justify-between">
          <div>
            <p class="font-bold text-foreground">Incoming Mail Chime</p>
            <p class="text-[11px] text-muted-foreground">Play a soft postal notification chime when new clever mail arrives.</p>
          </div>
          <input type="checkbox" id="settings-sound-enabled" class="cursor-pointer h-4 w-4" checked />
        </div>
      </div>

      <div class="p-4 border-t border-border bg-muted/30 flex justify-end gap-2">
        <button onclick="app.closeSettingsModal()" class="rounded-full px-4 py-2 text-xs font-medium text-muted-foreground hover:bg-muted">Close</button>
        <button onclick="app.saveSettings()" class="rounded-full bg-primary px-5 py-2 text-xs font-medium text-primary-foreground hover:bg-black">Save Settings</button>
      </div>
    </div>
  </div>

  <!-- PRINTABLE VINTAGE POSTCARD MODAL -->
  <div id="postcard-print-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onclick="if (event.target === this) app.closePostcardModal()">
    <div class="w-full max-w-3xl bg-white rounded-3xl p-8 border-2 border-slate-300 shadow-2xl relative overflow-hidden text-slate-900">
      <button onclick="app.closePostcardModal()" class="absolute top-4 right-4 p-1.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>

      <!-- Printable Postcard Body -->
      <div id="printable-postcard" class="grid grid-cols-1 md:grid-cols-2 gap-8 relative">
        <!-- Airmail border inner -->
        <div class="col-span-full stripes-subtle h-3 rounded-full mb-2"></div>

        <!-- Left Half: Postcard Letter Message -->
        <div class="flex flex-col justify-between pr-4 border-r border-slate-200">
          <div>
            <div class="flex items-center gap-2 mb-4">
              <img src="/raccoon-logo.png" class="h-8 w-8 object-contain" />
              <span class="font-display font-bold text-xs uppercase tracking-widest text-slate-500">Official Raccon Postcard</span>
            </div>
            <h2 id="postcard-subject" class="font-display text-xl font-bold mb-3 text-slate-900"></h2>
            <div id="postcard-body" class="font-handwriting text-2xl text-slate-800 leading-relaxed whitespace-pre-wrap min-h-[140px]"></div>
          </div>
          <div class="mt-6 pt-4 border-t border-slate-200 text-xs font-display text-slate-500">
            From: <span id="postcard-from" class="font-bold text-slate-900"></span>
          </div>
        </div>

        <!-- Right Half: Postage Stamp, Postmark & Address Lines -->
        <div class="flex flex-col justify-between pl-4">
          <!-- Top right: Vintage Postage Stamp & Postmark -->
          <div class="flex items-start justify-end gap-3">
            <div class="postmark-seal">
              <span>RACCON</span>
              <span>EXPRESS</span>
              <span id="postcard-postmark-date">OCT 2026</span>
            </div>
            <div class="postcard-stamp">
              <img src="/raccoon-logo.png" class="h-10 w-10 object-contain" />
              <div class="text-[9px] font-bold text-slate-600 leading-tight">
                RACCON<br/>POSTAGE<br/>25 ACORNS
              </div>
            </div>
          </div>

          <!-- Bottom: Postcard Address Lines -->
          <div class="space-y-4 my-8">
            <div class="border-b border-slate-300 pb-1 text-sm font-handwriting text-xl text-slate-900">
              To: <span id="postcard-to"></span>
            </div>
            <div class="border-b border-slate-300 pb-1 text-sm font-handwriting text-xl text-slate-600">
              Forest Burrow #42, Whispering Pines
            </div>
            <div class="border-b border-slate-300 pb-1 text-sm font-handwriting text-xl text-slate-600">
              The Great Northern Woodland, ZIP 00042
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-4">
            <button onclick="window.print()" class="rounded-full bg-slate-900 text-white px-5 py-2 text-xs font-bold hover:bg-black transition flex items-center gap-1.5 cursor-pointer">
              <i data-lucide="printer" class="h-3.5 w-3.5 text-white"></i>
              <span>Print Physical Postcard</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- KEYBOARD SHORTCUTS MODAL -->
  <div id="keyboard-shortcuts-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" onclick="if (event.target === this) app.closeKeyboardShortcuts()">
    <div class="w-full max-w-lg rounded-3xl bg-white p-6 sm:p-7 shadow-2xl border border-slate-200 relative overflow-hidden flex flex-col">
      <div class="flex items-center justify-between pb-4 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-800">
            <i data-lucide="keyboard" class="h-4 w-4 text-slate-700"></i>
          </div>
          <div>
            <h3 class="font-display font-bold text-base text-slate-900">Keyboard Shortcuts</h3>
            <p class="text-xs text-slate-500">Speed up your nocturnal workflow</p>
          </div>
        </div>
        <button onclick="app.closeKeyboardShortcuts()" class="p-1.5 rounded-full hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition cursor-pointer">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <div class="py-4 space-y-2.5 text-xs text-slate-700 max-h-[60vh] overflow-y-auto">
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Compose New Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">C</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Reply to Open Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">R</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Reply All</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">A</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Forward Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">F</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Archive Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">E</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Delete / Move to Trash</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">#</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Star / Unstar Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">S</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Mark as Unread / Read</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">U</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Print / Export PDF</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">P</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Next Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">J</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Previous Message</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">K</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Global Search</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">/</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <span>Command Palette</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">Ctrl + K</kbd>
        </div>
        <div class="flex items-center justify-between py-1.5">
          <span>Open Shortcuts Help</span>
          <kbd class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold">?</kbd>
        </div>
      </div>

      <div class="pt-4 border-t border-slate-200 flex justify-end">
        <button onclick="app.closeKeyboardShortcuts()" class="rounded-full bg-slate-900 hover:bg-black text-white px-5 py-2 text-xs font-bold transition cursor-pointer">
          Got It
        </button>
      </div>
    </div>
  </div>

  <!-- PRIVACY POLICY MODAL -->
  <div id="privacy-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" onclick="if (event.target === this) app.closePrivacyModal()">
    <div class="w-full max-w-2xl rounded-3xl bg-card p-6 sm:p-8 shadow-2xl border border-border relative overflow-hidden max-h-[90vh] flex flex-col">
      <div class="stripes-subtle h-2.5 -mx-8 -mt-8 mb-4 shrink-0"></div>

      <!-- Header -->
      <div class="flex items-start justify-between gap-4 pb-4 border-b border-border shrink-0">
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-2xl bg-slate-100 text-slate-800 border border-slate-200">
            <i data-lucide="shield-check" class="h-6 w-6"></i>
          </div>
          <div>
            <h3 class="font-display text-xl sm:text-2xl font-bold text-foreground">Raccon Mail Privacy &amp; Security Policy</h3>
            <p class="text-xs text-muted-foreground">European GDPR &amp; Nocturnal Privacy Architecture &bull; racconmail.eu</p>
          </div>
        </div>
        <button onclick="app.closePrivacyModal()" class="p-2 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <!-- Content -->
      <div class="overflow-y-auto py-4 space-y-4 text-xs sm:text-sm text-foreground/90 leading-relaxed pr-2">
        <div class="rounded-2xl bg-muted/40 p-4 border border-border">
          <p class="font-semibold text-foreground flex items-center gap-1.5">
            <i data-lucide="lock" class="h-4 w-4 text-slate-700"></i>
            <span>Our Nocturnal Guarantee:</span>
          </p>
          <p class="text-xs text-muted-foreground mt-1">
            Raccon Mail operates with zero third-party telemetry, zero ad-network tracking, and zero email content analysis. Your emails, attachments, and contacts belong exclusively to you.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-slate-500"></span>
            1. Zero Tracker Policy &amp; No Device Fingerprinting
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            We do not collect IP addresses, hardware identifiers, or tracking cookies for marketing. No surveillance agents or data brokers monitor your mailbox.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-slate-500"></span>
            2. Nocturnal Postcard Masking &amp; Mail Transmission
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            When you send or receive postcards via <strong>@racconmail.eu</strong>, communications are routed through secure MIME multipart envelopes. Outgoing mail sent through our system aligns strictly with SPF, DKIM, and DMARC authentication protocols.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-slate-500"></span>
            3. Isolated Encrypted Mailboxes &amp; Server Storage
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            User credentials are protected using industry-standard salted <code>bcrypt/argon2</code> password hashing. Mailbox databases and uploaded attachments are guarded in isolated SQLite storage with strict filesystem ACLs.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-slate-500"></span>
            4. Absolute Data Ownership &amp; Right to Erasure (GDPR)
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            You have complete sovereignty over your data. You may delete individual messages, purge attachment stashes, or request complete account erasure at any time.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-slate-500"></span>
            5. Security Contacts &amp; Inquiries
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            For security disclosures or compliance inquiries, contact our postmaster directly at <code class="text-foreground font-bold">postmaster@racconmail.eu</code>.
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="pt-4 border-t border-border flex items-center justify-between shrink-0">
        <span class="text-xs text-muted-foreground flex items-center gap-1.5">
          <i data-lucide="lock" class="h-3.5 w-3.5 text-muted-foreground"></i>
          <span>100% Masked Nocturnal Privacy Guaranteed</span>
        </span>
        <button onclick="app.closePrivacyModal()" class="rounded-full bg-primary hover:bg-black text-primary-foreground font-semibold px-5 py-2 text-xs transition cursor-pointer">
          Got It, Keep Me Safe
        </button>
      </div>
    </div>
  </div>

  <!-- RACCON NEWS MODAL (In-Mailbox Live Wire) -->
  <div id="news-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-2 sm:p-6 backdrop-blur-sm overflow-y-auto" onclick="if (event.target === this) app.closeNewsModal()">
    <div onclick="event.stopPropagation()" class="w-full max-w-5xl rounded-3xl bg-card p-4 sm:p-7 shadow-2xl border border-border relative flex flex-col h-[90vh] sm:h-[85vh] max-h-[90vh] sm:max-h-[85vh] min-h-0">
      <div class="stripes-subtle h-2.5 -mx-8 -mt-8 mb-4 shrink-0"></div>

      <!-- Header -->
      <div class="flex items-start justify-between gap-2 sm:gap-4 pb-4 border-b border-border shrink-0">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
          <div class="p-2 sm:p-2.5 rounded-2xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
            <i data-lucide="newspaper" class="h-4 w-4 sm:h-5 sm:w-5 text-slate-600"></i>
          </div>
          <div class="min-w-0">
            <h3 class="font-display text-base sm:text-2xl font-bold text-foreground flex flex-wrap items-center gap-1.5 sm:gap-2">
              <span class="whitespace-nowrap">Raccon News Wire</span>
              <span class="text-[10px] sm:text-xs font-mono bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-full font-bold whitespace-nowrap">Live Updates</span>
            </h3>
            <p class="text-xs text-muted-foreground line-clamp-1 sm:line-clamp-none">Tech, Sport, Games, Music, and Weather articles updated every 5 minutes</p>
          </div>
        </div>
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <a href="/news" class="rounded-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs p-2 sm:px-3.5 sm:py-1.5 transition flex items-center gap-1.5 shadow-2xs whitespace-nowrap" title="Open full-page news wire">
            <i data-lucide="maximize-2" class="h-3.5 w-3.5 shrink-0"></i>
            <span class="hidden sm:inline">Open Full Page News</span>
          </a>
          <button onclick="app.closeNewsModal()" class="p-1.5 sm:p-2 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer shrink-0">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>
      </div>

      <!-- 5 Columns Container (Scroll Fixed) -->
      <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain py-4 pr-1">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5" id="inbox-news-grid">
          <!-- Populated by app.js -->
        </div>
      </div>

      <!-- Footer Bar -->
      <div class="pt-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground shrink-0">
        <div class="flex items-center gap-2">
          <span>⏱️ Live Auto-Sync &bull; Raccon Wire</span>
          <span class="text-muted-foreground">&bull;</span>
          <a href="/news" class="font-bold text-primary hover:underline flex items-center gap-1">
            <span>Open Full Page News</span>
            <i data-lucide="arrow-right" class="h-3 w-3"></i>
          </a>
        </div>
        <button onclick="app.closeNewsModal()" class="rounded-full bg-muted hover:bg-muted/80 text-foreground font-semibold px-4 py-1.5 transition cursor-pointer">
          Close Wire
        </button>
      </div>
    </div>
  </div>

  <!-- WORLD STORIES MODAL (Modern Reader Module) -->
  <div id="stories-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-2 sm:p-6 backdrop-blur-sm overflow-y-auto" onclick="if (event.target === this) app.closeStoriesModal()">
    <div onclick="event.stopPropagation()" class="w-full max-w-5xl rounded-3xl bg-card p-4 sm:p-7 shadow-2xl border border-border relative flex flex-col h-[90vh] sm:h-[85vh] max-h-[90vh] sm:max-h-[85vh] min-h-0">
      <div class="stripes-subtle h-2.5 -mx-8 -mt-8 mb-4 shrink-0"></div>

      <!-- 1. LIBRARY CATALOG VIEW (Scroll Fixed) -->
      <div id="stories-catalog-view" class="flex flex-col flex-1 overflow-hidden min-h-0">
        <!-- Header -->
        <div class="flex items-start justify-between gap-2 sm:gap-4 pb-4 border-b border-border shrink-0">
          <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <div class="p-2 sm:p-2.5 rounded-2xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
              <i data-lucide="book-open" class="h-4 w-4 sm:h-5 sm:w-5 text-slate-600"></i>
            </div>
            <div class="min-w-0">
              <h3 class="font-display text-base sm:text-2xl font-bold text-foreground flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="whitespace-nowrap">World Stories</span>
                <span class="text-[10px] sm:text-xs font-mono bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-full font-bold whitespace-nowrap">English Library</span>
              </h3>
              <p class="text-xs text-muted-foreground line-clamp-1 sm:line-clamp-none">Folktales, myths, and fables from cultures around the globe</p>
            </div>
          </div>
          <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <a href="/stories" class="rounded-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs p-2 sm:px-3.5 sm:py-1.5 transition flex items-center gap-1.5 shadow-2xs whitespace-nowrap" title="Open full-page stories library">
              <i data-lucide="maximize-2" class="h-3.5 w-3.5 shrink-0"></i>
              <span class="hidden sm:inline">Open Full Page Stories</span>
            </a>
            <button onclick="app.closeStoriesModal()" class="p-1.5 sm:p-2 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer shrink-0">
              <i data-lucide="x" class="h-5 w-5"></i>
            </button>
          </div>
        </div>

        <!-- Filter Pills & Search Bar -->
        <div class="pt-3 pb-2 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0 text-xs" id="story-filter-tabs">
            <button onclick="app.filterStories('all')" class="story-tab active px-3 py-1.5 rounded-full font-bold bg-primary text-white transition shadow-2xs shrink-0 cursor-pointer">All Tales</button>
            <button onclick="app.filterStories('animals')" class="story-tab px-3 py-1.5 rounded-full font-medium text-muted-foreground hover:bg-muted transition shrink-0 cursor-pointer">Clever Animals</button>
            <button onclick="app.filterStories('folktales')" class="story-tab px-3 py-1.5 rounded-full font-medium text-muted-foreground hover:bg-muted transition shrink-0 cursor-pointer">Traditional Folktales</button>
            <button onclick="app.filterStories('fables')" class="story-tab px-3 py-1.5 rounded-full font-medium text-muted-foreground hover:bg-muted transition shrink-0 cursor-pointer">Fables &amp; Wisdom</button>
            <button onclick="app.filterStories('myths')" class="story-tab px-3 py-1.5 rounded-full font-medium text-muted-foreground hover:bg-muted transition shrink-0 cursor-pointer">Myths &amp; Magic</button>
          </div>
          <div class="relative w-full sm:w-64">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground"></i>
            <input
              id="stories-search-input"
              type="text"
              placeholder="Search stories, country..."
              class="w-full rounded-full border border-border bg-muted/60 pl-8 pr-3 py-1 text-xs outline-none focus:bg-card focus:border-primary transition text-foreground"
              oninput="app.searchStories(this.value)"
            />
          </div>
        </div>

        <!-- Stories Grid (Scroll Fixed) -->
        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain py-3 pr-1">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5" id="stories-grid-container">
            <!-- Rendered by app.renderStoriesGrid() -->
          </div>
        </div>

        <!-- Footer Bar -->
        <div class="pt-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted-foreground shrink-0">
          <div class="flex items-center gap-2">
            <span>📖 175+ traditional folktales &bull; Read directly inside Raccon Mail</span>
          </div>
          <button onclick="app.closeStoriesModal()" class="rounded-full bg-muted hover:bg-accent text-foreground font-semibold px-4 py-1.5 transition cursor-pointer">
            Close Stories
          </button>
        </div>
      </div>

      <!-- 2. STORY READER VIEW (Scroll Fixed) -->
      <div id="stories-reader-view" class="hidden flex-col flex-1 overflow-hidden min-h-0">
        <!-- Reader Header -->
        <div class="flex items-center justify-between pb-3 border-b border-border shrink-0">
          <button onclick="app.backToStoriesCatalog()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-muted hover:bg-accent text-foreground font-bold text-xs transition cursor-pointer">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            <span>Back to Stories</span>
          </button>
          <div class="flex items-center gap-2">
            <button onclick="app.sendStoryAsPostcard()" class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-primary hover:bg-primary-hover text-white font-bold text-xs transition cursor-pointer active:scale-95 shadow-2xs">
              <i data-lucide="send" class="h-3.5 w-3.5"></i>
              <span>Send as Postcard</span>
            </button>
            <button onclick="app.closeStoriesModal()" class="p-1.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer">
              <i data-lucide="x" class="h-5 w-5"></i>
            </button>
          </div>
        </div>

        <!-- Reader Scrollable Content (Scroll Fixed) -->
        <div id="stories-reader-content" class="flex-1 min-h-0 overflow-y-auto overscroll-contain py-5 px-2 sm:px-6 space-y-4">
          <!-- Populated by app.openStoryReader(storyId) -->
        </div>

        <!-- Reader Footer -->
        <div class="pt-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground shrink-0">
          <span id="story-reader-origin-badge">Origin</span>
          <span class="font-bold text-primary flex items-center gap-1">
            <span>Integrated Reader &bull; Raccon Mail Library</span>
          </span>
        </div>
      </div>

    </div>
  </div>

  <!-- ======================================================= -->
  <!-- COMMAND PALETTE MODAL (K-BAR / Ctrl+K / Cmd+K)         -->
  <!-- ======================================================= -->
  <div id="command-palette-backdrop" onclick="app.closeCommandPalette()" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-start justify-center pt-14 sm:pt-20 px-3 sm:px-4 transition-opacity">
    <div onclick="event.stopPropagation()" class="w-full max-w-xl bg-card rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col transform transition-all duration-200 divide-y divide-border/60">
      
      <!-- Command Palette Search Input Header -->
      <div class="p-3.5 flex items-center gap-3 bg-muted/30">
        <i data-lucide="terminal" class="h-5 w-5 text-primary shrink-0"></i>
        <input
          id="command-palette-input"
          type="text"
          placeholder="Type a command or search mail... (Esc to close)"
          class="w-full bg-transparent text-sm sm:text-base outline-none font-medium placeholder:text-muted-foreground text-foreground"
          oninput="app.onCommandPaletteInput(this.value)"
          onkeydown="app.onCommandPaletteKeyDown(event)"
          autocomplete="off"
        />
        <kbd class="hidden sm:inline-flex items-center gap-0.5 px-2 py-0.5 text-[10px] font-mono font-semibold bg-muted text-muted-foreground border border-border rounded">ESC</kbd>
      </div>

      <!-- Command Palette Actions List -->
      <div id="command-palette-results" class="max-h-[60vh] overflow-y-auto p-2 space-y-1 text-xs">
        <!-- Rendered by app.renderCommandPaletteItems() -->
      </div>

      <!-- Command Palette Keyboard Guide Footer -->
      <div class="px-4 py-2 bg-muted/20 text-[11px] text-muted-foreground flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 font-mono text-[10px] bg-card border border-border rounded">↑</kbd><kbd class="px-1.5 py-0.5 font-mono text-[10px] bg-card border border-border rounded">↓</kbd> Navigate</span>
          <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 font-mono text-[10px] bg-card border border-border rounded">↵</kbd> Select</span>
        </div>
        <span class="text-primary font-semibold flex items-center gap-1">🦝 Command Center</span>
      </div>

    </div>
  </div>



  <audio id="mail-chime" preload="auto">
    <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU..." type="audio/wav">
  </audio>

  <!-- Application Logic -->
  <script>
    // Immediate auth gate check: do not render mailbox if not logged in
    (async () => {
      try {
        const res = await fetch('/api/auth/me');
        const data = await res.json();
        if (!data.loggedIn || !data.user) {
          window.location.replace('/auth?mode=register');
        }
      } catch (_) {
        window.location.replace('/auth?mode=register');
      }
    })();
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="/js/app.js"></script>

  

</body>
</html>

