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

  <!-- Early Theme Initializer (Neon Flower Minimalist Palette) -->
  <script>
    (function() {
      localStorage.setItem('raccon_theme', 'neon_flower');
      document.documentElement.className = 'dark theme-neon-flower';
      const themeVars = {
        '--background': '#070A14',
        '--foreground': '#F8FAFC',
        '--card': '#0B1120',
        '--card-alt': '#0F172A',
        '--card-foreground': '#F8FAFC',
        '--primary': '#0EA5E9',
        '--primary-hover': '#38BDF8',
        '--primary-foreground': '#FFFFFF',
        '--primary-glow': 'rgba(14, 165, 233, 0.35)',
        '--secondary': '#0F172A',
        '--secondary-foreground': '#F8FAFC',
        '--secondary-hover': '#1E293B',
        '--muted': '#0E162B',
        '--muted-foreground': '#94A3B8',
        '--accent': '#131E35',
        '--accent-foreground': '#38BDF8',
        '--accent-pink': '#EC4899',
        '--border': 'rgba(56, 189, 248, 0.16)',
        '--border-light': 'rgba(56, 189, 248, 0.08)',
        '--mask': '#070A14',
        '--fur': '#0EA5E9',
        '--cream': '#0B1120',
        '--stamp-red': '#EC4899',
        '--stamp-blue': '#0EA5E9'
      };
      for (const [k, v] of Object.entries(themeVars)) {
        document.documentElement.style.setProperty(k, v);
      }
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
            'border-light': 'var(--border-light)',
            mask: 'var(--mask)',
            fur: 'var(--fur)',
            cream: 'var(--cream)'
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

  <!-- Direct Theme-Adaptive Compose Button CSS (Immune to Cache) -->
  <style>
    #btn-compose {
      border-radius: 50px !important;
      padding: 12px 20px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 10px !important;
      font-family: inherit !important;
      font-size: 0.95rem !important;
      font-weight: 700 !important;
      cursor: pointer !important;
      transition: all 0.22s ease-in-out !important;
      text-decoration: none !important;
    }

    /* Clean Neon Flower Minimalist Compose Button */
    #btn-compose,
    html #btn-compose,
    body #btn-compose {
      background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 60%, #1d4ed8 100%) !important;
      border: 1.5px solid rgba(56, 189, 248, 0.4) !important;
      color: #FFFFFF !important;
      box-shadow: 0 4px 18px rgba(14, 165, 233, 0.35), 0 0 12px rgba(14, 165, 233, 0.2) !important;
    }
    #btn-compose:hover,
    html #btn-compose:hover,
    body #btn-compose:hover {
      background: linear-gradient(135deg, #38bdf8 0%, #1d4ed8 100%) !important;
      border-color: rgba(56, 189, 248, 0.7) !important;
      color: #FFFFFF !important;
      transform: translateY(-1.5px) !important;
      box-shadow: 0 8px 24px rgba(14, 165, 233, 0.5) !important;
    }
    #btn-compose .compose-pencil-badge {
      background-color: rgba(255, 255, 255, 0.22) !important;
      border: 1px solid rgba(255, 255, 255, 0.35) !important;
    }
    #btn-compose svg,
    #btn-compose i {
      stroke: #FFFFFF !important;
      color: #FFFFFF !important;
    }
    #btn-compose .compose-btn-text {
      color: #FFFFFF !important;
    }
  </style>
</head>
<body class="bg-background text-foreground flex flex-col h-screen relative selection:bg-sky-500 selection:text-white overflow-hidden">

  <!-- AMBIENT LUMINESCENT CORNER AURA (Static & Minimalist) -->
  <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden" style="background: radial-gradient(ellipse 55% 45% at 95% 95%, rgba(236, 72, 153, 0.08) 0%, transparent 70%), radial-gradient(ellipse 45% 35% at 5% 5%, rgba(14, 165, 233, 0.08) 0%, transparent 70%);"></div>

  <!-- TOP POSTAL STRIPE ACCENT -->
  <div class="stripes h-[2px] w-full shrink-0"></div>

  <!-- TOP APP BAR -->
  <header class="h-14 border-b border-border bg-card px-3 md:px-4 flex items-center justify-between shrink-0 shadow-xs z-20">
    <!-- Brand / Mascot / Hamburger (Stationary logo restored to original clean size on both mobile & desktop) -->
    <div class="flex items-center gap-2 md:gap-3 shrink-0">
      <!-- Mobile Sidebar Toggle -->
      <button
        onclick="app.toggleMobileSidebar()"
        class="md:hidden p-2 rounded-xl bg-slate-900 hover:bg-black text-white transition cursor-pointer shadow-xs"
        title="Open Folders"
      >
        <i data-lucide="menu" class="h-5 w-5 text-white"></i>
      </button>

      <!-- Desktop & Mobile Brand Logo (Visible on all screens, original clean size) -->
      <a href="/inbox" class="flex items-center gap-2 md:gap-2.5 font-display text-base md:text-xl font-bold tracking-tight cursor-pointer shrink-0 group text-foreground" onclick="event.preventDefault(); app.returnToInbox();" title="Return to Inbox">
        <img src="/raccoon-logo.png" alt="Raccon Mail" class="h-7 w-7 md:h-8 md:w-8 object-contain raccoon-wiggle shrink-0" />
        <span class="text-foreground shrink-0 font-display font-bold">Raccon Mail</span>
      </a>
      <span class="hidden lg:inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 shrink-0">
        <span class="h-1.5 w-1.5 rounded-full bg-sky-400 animate-pulse"></span>
        Masked & Secure
      </span>
    </div>

    <!-- Minimalist Compact Search Bar (Desktop Only, Small & Well-Fitted) -->
    <div class="hidden md:block flex-1 min-w-0 max-w-sm lg:max-w-md mx-3 lg:mx-5 relative" id="global-search-container">
      <div class="relative flex items-center rounded-full border border-sky-500/20 bg-[#090F1F]/90 shadow-2xs hover:border-sky-500/40 focus-within:border-sky-400 focus-within:ring-1 focus-within:ring-sky-500/30 transition h-9 px-3">
        <!-- Integrated Left Search Loop Icon -->
        <button
          type="button"
          onclick="app.triggerSearchSubmit()"
          class="text-sky-400 hover:text-sky-300 transition cursor-pointer flex items-center shrink-0 pr-2"
          title="Search"
        >
          <i data-lucide="search" class="h-3.5 w-3.5 stroke-[2.2]"></i>
        </button>

        <!-- Search Input Field -->
        <input
          id="global-search"
          type="text"
          autocomplete="off"
          placeholder="Search mail..."
          class="flex-1 min-w-0 bg-transparent py-1 text-xs outline-none text-white placeholder:text-slate-400 font-normal"
          onclick="app.onSearchClick()"
          oninput="app.onSearchInput(this.value)"
          onfocus="app.onSearchClick()"
          onkeydown="app.onSearchKeyDown(event)"
        />

        <!-- Clear 'X' Button (visible when typing) -->
        <button
          id="search-clear-btn"
          type="button"
          class="hidden px-1.5 text-slate-400 hover:text-white text-xs cursor-pointer shrink-0"
          onclick="app.clearSearch()"
          title="Clear search"
        >
          <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>

        <!-- Advanced Search Filter Dropdown Toggle Button -->
        <button
          type="button"
          onclick="app.toggleAdvancedSearchDropdown()"
          class="p-1 text-slate-400 hover:text-sky-300 transition cursor-pointer flex items-center shrink-0"
          title="Search filters"
        >
          <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i>
        </button>

        <!-- Keyboard Shortcut Badge (Ctrl+K) -->
        <span class="hidden lg:inline-flex items-center text-[9px] font-mono text-sky-300/80 bg-sky-950/60 border border-sky-500/20 px-1.5 py-0.5 rounded ml-1 pointer-events-none shrink-0">
          ⌘K
        </span>
      </div>

      <!-- Advanced Search Filters Dropdown -->
      <div id="advanced-search-dropdown" class="hidden absolute left-0 right-0 top-full mt-2 z-50 bg-[#0B1120] rounded-2xl border border-sky-500/25 shadow-2xl p-4 text-xs text-slate-200 space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-sky-500/15">
          <span class="font-bold text-sm text-white">Advanced Search</span>
          <button type="button" onclick="app.closeAdvancedSearchDropdown()" class="text-slate-400 hover:text-white p-1 rounded-full cursor-pointer"><i data-lucide="x" class="h-3.5 w-3.5"></i></button>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-bold text-sky-300 mb-1">From</label>
            <input type="text" id="adv-search-from" placeholder="Sender name or email..." class="w-full rounded-lg border border-sky-500/20 bg-[#080D1A] px-2.5 py-1.5 text-xs text-white outline-none focus:border-sky-400" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-sky-300 mb-1">To</label>
            <input type="text" id="adv-search-to" placeholder="Recipient..." class="w-full rounded-lg border border-sky-500/20 bg-[#080D1A] px-2.5 py-1.5 text-xs text-white outline-none focus:border-sky-400" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-sky-300 mb-1">Subject</label>
            <input type="text" id="adv-search-subject" placeholder="Keywords in subject..." class="w-full rounded-lg border border-sky-500/20 bg-[#080D1A] px-2.5 py-1.5 text-xs text-white outline-none focus:border-sky-400" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-sky-300 mb-1">In Folder</label>
            <select id="adv-search-folder" class="w-full rounded-lg border border-sky-500/20 px-2.5 py-1.5 text-xs text-white outline-none focus:border-sky-400 bg-[#080D1A]">
              <option value="all">All Folders</option>
              <option value="Inbox">Inbox</option>
              <option value="Sent">Sent</option>
              <option value="Drafts">Drafts</option>
              <option value="Archive">Archive</option>
              <option value="Spam">Spam</option>
              <option value="Trash">Trash</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-sky-500/15">
          <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-300">
            <input type="checkbox" id="adv-search-has-attachment" class="rounded border-sky-500/30 accent-sky-500 cursor-pointer" />
            <span>Has attachment</span>
          </label>
          <div class="flex items-center gap-2">
            <button type="button" onclick="app.resetAdvancedSearch()" class="px-3.5 py-1 rounded-full bg-[#0D152A] hover:bg-[#14203D] text-slate-300 border border-sky-500/20 font-semibold cursor-pointer transition">Reset</button>
            <button type="button" onclick="app.submitAdvancedSearch()" class="px-4 py-1 rounded-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold cursor-pointer shadow-[0_0_10px_rgba(14,165,233,0.3)] transition">Search</button>
          </div>
        </div>
      </div>

      <!-- Live Search Suggestions Dropdown -->
      <div id="search-suggestions-dropdown" class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-[#0B1120] rounded-2xl border border-sky-500/25 shadow-2xl overflow-hidden max-h-96 flex-col text-xs divide-y divide-sky-500/15">
        <!-- Populated dynamically by app.renderSearchSuggestions -->
      </div>
    </div>

    <!-- Header Actions & Account Switcher -->
    <div class="flex items-center gap-1 sm:gap-2 shrink-0">
      <!-- World Stories Button (Desktop/Tablet) -->
      <button
        onclick="app.openStoriesModal()"
        title="World Stories — Read global folktales & fables"
        class="hidden sm:flex items-center gap-1.5 rounded-full bg-[#0D152A] hover:bg-[#132040] text-sky-200 border border-sky-500/25 px-3 py-1.5 text-xs font-bold transition cursor-pointer active:scale-95 shadow-2xs shrink-0"
      >
        <i data-lucide="book-open" class="h-3.5 w-3.5 text-sky-400 shrink-0"></i>
        <span class="hidden sm:inline">Stories</span>
        <span class="text-[10px] bg-gradient-to-r from-pink-500 to-rose-600 text-white px-1.5 py-0.2 rounded-full font-extrabold hidden md:inline shadow-[0_0_8px_rgba(236,72,153,0.4)]">175+</span>
      </button>

      <!-- Account Switcher Dropdown -->
      <div class="relative">
        <button
          onclick="app.toggleAccountDropdown()"
          class="flex items-center gap-1.5 rounded-full bg-[#0D152A] hover:bg-[#132040] text-white border border-sky-500/25 p-1 md:pl-2.5 md:pr-2 text-xs transition cursor-pointer shadow-xs"
        >
          <span id="active-user-name" class="font-medium max-w-[100px] truncate hidden md:inline text-white">Clever Raccoon</span>
          <div id="active-user-avatar-badge" class="h-7 w-7 rounded-full bg-gradient-to-tr from-sky-600 to-blue-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs border border-sky-400/40">
            <span id="active-user-badge-initial">R</span>
          </div>
          <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-sky-400 mr-1 hidden md:inline"></i>
        </button>

        <!-- Modern Profile Dropdown Menu (Auto-dismisses on click-outside) -->
        <div id="account-dropdown" class="hidden absolute right-0 mt-2 rounded-3xl border border-sky-500/25 bg-[#0B1120] overflow-hidden shadow-2xl z-50 text-white w-72 max-w-[calc(100vw-20px)] animate-in fade-in-50 duration-150">
          <!-- Cover Photo Header -->
          <div id="dropdown-user-cover" class="h-20 w-full bg-gradient-to-r from-sky-900 via-blue-900 to-pink-900 relative bg-cover bg-center"></div>

          <!-- User Info with overlapping Avatar & Registration Date -->
          <div class="px-4 pb-3 pt-0 relative border-b border-sky-500/15">
            <div class="flex items-end justify-between -mt-8 mb-2">
              <img id="dropdown-user-avatar" src="/raccoon-logo.png" class="h-14 w-14 rounded-full bg-[#0B1120] object-cover aspect-square border-4 border-[#0B1120] shadow-md shrink-0" />
            </div>
            
            <p id="dropdown-user-name" class="text-sm font-bold truncate text-white">Clever Raccoon</p>
            <p id="dropdown-user-email" class="text-xs text-sky-300 truncate font-mono">me@racconmail.eu</p>
            
            <!-- Registration Date Badges -->
            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10.5px]">
              <span id="dropdown-user-joined" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-sky-950/60 border border-sky-500/25 text-sky-300 font-semibold">
                <i data-lucide="calendar" class="h-3 w-3 text-sky-400"></i>
                <span>Member since Oct 2026</span>
              </span>
            </div>
          </div>

          <!-- Quick Navigation Links -->
          <div class="p-2 space-y-0.5 text-xs">
            <button onclick="app.openSettingsModal()" class="w-full text-left flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-[#132040] font-semibold transition text-slate-200 hover:text-white whitespace-nowrap cursor-pointer">
              <i data-lucide="settings" class="h-4 w-4 text-sky-400 shrink-0"></i>
              <span>Profile &amp; Mail Settings</span>
            </button>
            <button onclick="app.logout()" class="w-full text-left flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-[#132040] text-slate-300 hover:text-white font-medium transition whitespace-nowrap cursor-pointer">
              <i data-lucide="log-out" class="h-4 w-4 text-rose-400 shrink-0"></i>
              <span>Sign Out</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Header Sign Out Button (Desktop only) -->
      <button
        onclick="app.logout()"
        title="Sign Out of Raccon Mail"
        class="hidden md:flex items-center gap-1.5 rounded-full border border-sky-500/20 bg-[#0D152A] hover:bg-[#132040] text-slate-300 hover:text-white px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
      >
        <i data-lucide="log-out" class="h-3.5 w-3.5 text-sky-400"></i>
        <span>Sign Out</span>
      </button>
    </div>
  </header>

  <!-- Mobile Sidebar Backdrop Overlay -->
  <div id="mobile-sidebar-backdrop" onclick="app.closeMobileSidebar()" class="hidden fixed inset-0 z-40 bg-black/50 md:hidden backdrop-blur-xs"></div>

  <!-- MAIN THREE-COLUMN MAIL INTERFACE -->
  <div class="flex-1 flex overflow-hidden relative">

    <!-- 1. NAVIGATION SIDEBAR (Off-canvas Drawer on Mobile, Left Panel on Desktop) -->
    <aside id="sidebar-panel" class="fixed inset-y-0 left-0 z-50 w-72 bg-card transform -translate-x-full md:relative md:translate-x-0 md:w-60 transition-transform duration-300 ease-in-out shrink-0 flex flex-col border-r border-border shadow-2xl md:shadow-none">
      <!-- Mobile Close Button in Drawer -->
      <div class="p-3 border-b border-border flex items-center justify-between md:hidden">
        <div class="flex items-center gap-2 font-display font-bold text-sm">
          <img src="/raccoon-logo.png" class="h-6 w-6 object-contain" />
          <span>Raccon Mail</span>
        </div>
        <button onclick="app.closeMobileSidebar()" class="p-1 rounded-full hover:bg-muted text-muted-foreground">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <!-- Compose Button: Theme-Adaptive Pill with High-Contrast Pencil Badge -->
      <div class="p-4">
        <button
          id="btn-compose"
          onclick="app.closeMobileSidebar(); app.openCompose()"
          class="w-full flex items-center justify-center gap-2.5 rounded-full font-display font-bold text-sm py-3 px-4 shadow-md transition transform active:scale-95 cursor-pointer bg-slate-900 hover:bg-slate-800 text-white border border-slate-900"
        >
          <span class="compose-pencil-badge w-6 h-6 rounded-full flex items-center justify-center shrink-0 transition bg-white/20 border border-white/30">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0 text-white">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
              <path d="m15 5 4 4"/>
            </svg>
          </span>
          <span class="compose-btn-text font-bold text-white">Compose Postcard</span>
        </button>
      </div>

      <!-- Navigation Folders List -->
      <nav class="flex-1 overflow-y-auto px-2 space-y-1 text-sm">
        <!-- Standard Folders -->
        <div id="standard-folders-list" class="space-y-0.5">
          <button onclick="app.navTo('Inbox')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer active font-bold shadow-sm">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="inbox" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Inbox</span>
            </div>
            <div class="pointer-events-none"><span id="inbox-badge-placeholder" class="badge-unread hidden">0</span></div>
          </button>
          <button onclick="app.navTo('Unread')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="mail-open" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Unread</span>
            </div>
            <div class="pointer-events-none"><span id="unread-badge-placeholder" class="badge-unread hidden">0</span></div>
          </button>
          <button onclick="app.navTo('Favorites')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="heart" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Favorites</span>
            </div>
          </button>
          <button onclick="app.navTo('Drafts')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="file-text" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Drafts</span>
            </div>
          </button>
          <button onclick="app.navTo('Sent')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="send" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Sent</span>
            </div>
          </button>
          <button onclick="app.navTo('Archive')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="archive" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Archive</span>
            </div>
          </button>
          <button onclick="app.navTo('Photos & Files')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="image" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Photos &amp; Files</span>
            </div>
          </button>
          <button onclick="app.navTo('Spam')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="shield-alert" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Spam</span>
            </div>
          </button>
          <button onclick="app.navTo('Trash')" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground">
            <div class="flex items-center gap-2.5 truncate pointer-events-none">
              <i data-lucide="trash-2" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">Trash</span>
            </div>
          </button>
        </div>

        <!-- Custom Folders Section (Raccon style) -->
        <div class="pt-4 pb-1">
          <div class="flex items-center justify-between px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            <span>Custom Folders</span>
            <button onclick="app.openNewFolderModal()" title="Add new folder" class="hover:text-foreground p-0.5 rounded">
              <i data-lucide="plus" class="h-3.5 w-3.5"></i>
            </button>
          </div>
          <div id="custom-folders-list" class="mt-1 space-y-0.5"></div>
        </div>
      </nav>

      <!-- Bottom Sidebar Utilities (Styled identical to standard folders) -->
      <div class="border-t border-border p-2 space-y-0.5 text-xs font-medium">
        <a href="/forum" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="messages-square" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">Raccon Forum</span>
          </div>
        </a>
        <a href="/news" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="newspaper" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">Raccon News Wire</span>
          </div>
        </a>
        <a href="/stories" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="book-open" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">World Stories</span>
          </div>
        </a>
        <button onclick="app.openContactsView()" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="users" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">Address Book</span>
          </div>
          <span id="contacts-count-badge" class="badge-count text-[10px]">5</span>
        </button>
        <button onclick="app.openSettingsModal()" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="sliders-horizontal" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">Raccon / SMTP Config</span>
          </div>
        </button>
        <button onclick="app.openPrivacyModal()" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="shield" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">Privacy &amp; Policy</span>
          </div>
        </button>
        <button onclick="app.logout()" class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer text-foreground hover:bg-muted">
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="log-out" class="h-4 w-4 shrink-0 text-muted-foreground"></i>
            <span class="truncate font-semibold">Sign Out</span>
          </div>
        </button>
      </div>

      <!-- Storage Quota Meter -->
      <div class="px-3.5 py-3 border-t border-sky-500/15 bg-[#080D1A] shrink-0">
        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-300 mb-1.5">
          <span class="flex items-center gap-1.5"><i data-lucide="hard-drive" class="h-3.5 w-3.5 text-sky-400"></i>Storage</span>
          <span id="sidebar-storage-text" class="font-mono text-[10.5px] text-sky-300">24.8 MB / 15 GB (0.2%)</span>
        </div>
        <div class="w-full bg-[#0E162B] rounded-full h-1.5 overflow-hidden border border-sky-500/20">
          <div id="sidebar-storage-bar" class="bg-gradient-to-r from-sky-400 to-pink-500 h-1.5 rounded-full transition-all duration-300" style="width: 1.5%"></div>
        </div>
      </div>
    </aside>

    <!-- 2. MESSAGES LIST PANEL (Full width on mobile until email opened, Middle Panel on Desktop) -->
    <section id="messages-panel" class="w-full md:w-96 border-r border-sky-500/15 bg-[#0B1122] flex flex-col shrink-0 overflow-hidden">
      <!-- Folder Header & Filter Toolbar -->
      <div class="p-3 border-b border-sky-500/15 bg-[#080D1B]/90 backdrop-blur-md shrink-0 space-y-2">
        <div class="flex items-center justify-between gap-2">
          <div class="min-w-0 flex-1 truncate">
            <h1 id="current-folder-title" class="font-display text-lg font-extrabold truncate text-white">Inbox</h1>
            <p id="current-folder-subtitle" class="text-xs text-sky-200/60 font-medium truncate">Loading emails...</p>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <a href="/forum" title="Raccon Forum" class="px-3 py-1 rounded-full text-white bg-[#0F1A33] hover:bg-[#16274D] border border-sky-500/25 transition flex items-center gap-1.5 text-xs font-bold shadow-xs cursor-pointer shrink-0">
              <i data-lucide="messages-square" class="h-3.5 w-3.5 text-sky-400 shrink-0"></i>
              <span class="hidden sm:inline">Forum</span>
            </a>
            <button onclick="app.openNewsModal()" title="Raccon News Wire" class="px-3 py-1 rounded-full text-white bg-[#0F1A33] hover:bg-[#16274D] border border-sky-500/25 transition flex items-center gap-1.5 text-xs font-bold shadow-xs cursor-pointer shrink-0">
              <i data-lucide="newspaper" class="h-3.5 w-3.5 text-pink-400 shrink-0"></i>
              <span class="hidden sm:inline">News</span>
            </button>
            <button onclick="app.refreshCurrentFolder()" title="Refresh" class="p-1.5 rounded-full text-sky-300 hover:text-white hover:bg-sky-950/60 border border-sky-500/20 transition cursor-pointer shrink-0 shadow-xs">
              <i data-lucide="refresh-cw" class="h-4 w-4"></i>
            </button>
            <button onclick="app.markAllRead()" title="Mark all as read" class="p-1.5 rounded-full text-sky-300 hover:text-white hover:bg-sky-950/60 border border-sky-500/20 transition cursor-pointer shrink-0 shadow-xs">
              <i data-lucide="check-check" class="h-4 w-4"></i>
            </button>
            <button id="empty-folder-btn" onclick="app.emptyCurrentFolder()" title="Empty Folder" class="hidden text-xs text-white bg-rose-900/80 hover:bg-rose-800 border border-rose-500/40 px-3 py-1 rounded-full transition font-bold shrink-0 shadow-xs">
              Empty
            </button>
          </div>
        </div>

        <!-- Filter Pills & Sorting -->
        <div class="flex items-center justify-between gap-1 pt-1 overflow-x-auto no-scrollbar">
          <div class="flex items-center gap-1.5 text-xs shrink-0">
            <button onclick="app.setFilter('all')" id="filter-all" class="px-3 py-1 rounded-full font-bold bg-gradient-to-r from-sky-500 to-blue-600 text-white transition shadow-[0_0_12px_rgba(14,165,233,0.35)] whitespace-nowrap shrink-0 border border-sky-400/40">All</button>
            <button onclick="app.setFilter('unread')" id="filter-unread" class="px-3 py-1 rounded-full font-medium bg-[#0E162B] hover:bg-[#14203D] text-slate-300 transition border border-sky-500/20 whitespace-nowrap shrink-0">Unread</button>
            <button onclick="app.setFilter('starred')" id="filter-starred" class="px-3 py-1 rounded-full font-medium bg-[#0E162B] hover:bg-[#14203D] text-slate-300 transition border border-sky-500/20 whitespace-nowrap shrink-0">Starred</button>
            <button onclick="app.setFilter('has_attachments')" id="filter-has_attachments" class="px-3 py-1 rounded-full font-medium bg-[#0E162B] hover:bg-[#14203D] text-slate-300 transition border border-sky-500/20 flex items-center gap-1 whitespace-nowrap shrink-0">
              <i data-lucide="paperclip" class="h-3 w-3 text-sky-400"></i> Files
            </button>
          </div>

          <!-- Sort menu -->
          <select id="sort-select" onchange="app.onSortChange(this.value)" class="text-xs bg-[#090F1F] border border-sky-500/25 rounded-full px-2.5 py-1 outline-none text-slate-200 font-medium cursor-pointer shrink-0 ml-2 shadow-2xs">
            <option value="date_desc">Newest</option>
            <option value="date_asc">Oldest</option>
            <option value="sender">Sender</option>
            <option value="subject">Subject</option>
          </select>
        </div>
      </div>

      <!-- Batch Action Toolbar (Reveals when items are checked) -->
      <div id="batch-toolbar" class="hidden bg-slate-900 text-white border-b border-slate-800 px-3.5 py-2 flex items-center justify-between text-xs shrink-0 transition">
        <div class="flex items-center gap-2">
          <input type="checkbox" id="select-all-checkbox" onchange="app.toggleSelectAll(this.checked)" class="rounded border-slate-600 accent-white cursor-pointer" />
          <span id="selected-count-label" class="font-bold text-white">0 selected</span>
        </div>
        <div class="flex items-center gap-1">
          <button onclick="app.batchAction('mark_read')" title="Mark as read" class="p-1 rounded text-white hover:bg-slate-800">
            <i data-lucide="mail-open" class="h-4 w-4 text-white"></i>
          </button>
          <button onclick="app.batchAction('mark_unread')" title="Mark as unread" class="p-1 rounded text-white hover:bg-slate-800">
            <i data-lucide="mail" class="h-4 w-4 text-white"></i>
          </button>
          <button onclick="app.batchAction('star')" title="Star" class="p-1 rounded text-white hover:bg-slate-800">
            <i data-lucide="star" class="h-4 w-4 text-white"></i>
          </button>
          <button onclick="app.batchAction('archive')" title="Archive" class="p-1 rounded text-white hover:bg-slate-800">
            <i data-lucide="archive" class="h-4 w-4 text-white"></i>
          </button>
          <button onclick="app.batchAction('trash')" title="Move to trash" class="p-1 rounded text-white hover:bg-slate-800">
            <i data-lucide="trash-2" class="h-4 w-4 text-white"></i>
          </button>
        </div>
      </div>

      <!-- Messages Scrollable List -->
      <div id="messages-list" class="flex-1 overflow-y-auto py-2 space-y-1.5 pb-20 md:pb-2">
        <!-- Rendered by JS -->
      </div>
    </section>

    <!-- 3. EMAIL READER & DETAILS PANEL (Right Panel, Full width on mobile when open) -->
    <main class="hidden md:flex flex-1 flex-col bg-background overflow-hidden relative" id="reader-panel">
      <!-- Rendered dynamically: Empty State OR Message Reader OR Photos & Files Gallery OR Contacts View -->
      <div id="reader-content" class="h-full flex flex-col overflow-y-auto pb-20 md:pb-0">
        <!-- Default Empty State -->
        <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
          <div class="relative mb-6">
            <div class="absolute inset-0 rounded-full bg-accent/60 blur-2xl"></div>
            <img src="/raccoon-logo.png" alt="Clever Raccoon" class="raccoon-anim relative h-36 w-36 object-contain opacity-85" />
          </div>
          <h2 class="font-display text-2xl font-bold text-foreground">Pick an email to read</h2>
          <p class="mt-2 max-w-sm text-sm text-muted-foreground">
            Fast, private and beautifully simple — cleverly masked from spam, on web and smartphone.
          </p>
          <div class="mt-6 flex items-center gap-3">
            <button onclick="app.openCompose()" class="rounded-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 px-5 py-2 text-xs font-semibold text-white shadow-[0_0_12px_rgba(14,165,233,0.35)] transition cursor-pointer">
              Write something clever
            </button>
            <button onclick="app.openStoriesModal()" class="rounded-full border border-sky-500/25 bg-[#0D152A] hover:bg-[#132040] text-sky-200 px-4 py-2 text-xs font-medium transition flex items-center gap-1.5 cursor-pointer">
              <i data-lucide="book-open" class="h-3.5 w-3.5 text-sky-400"></i>
              <span>Explore World Stories</span>
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- FLOATING CAPSULE BOTTOM BAR (List Menu & Compose Pencil Only - No Search on Mobile) -->
  <nav id="mobile-floating-capsule" class="md:hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-[#080D1C]/95 backdrop-blur-md text-white px-8 py-3 rounded-full shadow-2xl flex items-center gap-10 border border-sky-500/30 safe-area-bottom select-none">
    <button onclick="app.toggleMobileSidebar()" class="text-sky-300 hover:text-white transition cursor-pointer flex items-center justify-center p-1.5 active:scale-90" title="All Folders (List)">
      <i data-lucide="list" class="h-6 w-6 text-sky-400"></i>
    </button>
    <button onclick="app.openCompose()" class="text-sky-300 hover:text-white transition cursor-pointer flex items-center justify-center p-1.5 active:scale-90" title="Compose New Message (Pencil)">
      <i data-lucide="pencil" class="h-5.5 w-5.5 text-sky-400"></i>
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

  <!-- FLOATING / FULLSCREEN RICH COMPOSE MODAL (Neon Flower Minimalist) -->
  <div id="compose-modal" class="hidden fixed inset-0 md:inset-auto md:bottom-4 md:right-6 z-50 w-full md:max-w-2xl bg-[#0B1120] md:rounded-3xl border border-sky-500/25 shadow-2xl flex flex-col overflow-hidden transition-all duration-200">
    <!-- Top subtle accent line -->
    <div class="stripes-subtle h-[2px] w-full shrink-0"></div>

    <!-- Header bar -->
    <div class="px-4 py-3 border-b border-sky-500/20 bg-[#080D1B] flex items-center justify-between shrink-0 select-none cursor-pointer" onclick="if(app.isComposeMinimized) app.minimizeCompose()">
      <div class="flex items-center gap-2">
        <img src="/raccoon-logo.png" class="h-4.5 w-4.5 object-contain" />
        <h3 id="compose-title" class="font-display font-bold text-xs sm:text-sm text-white">New Message</h3>
      </div>
      <div class="flex items-center gap-1 text-slate-400">
        <span id="draft-status-pill" class="text-[11px] text-sky-300 font-mono mr-1.5"></span>
        <button type="button" onclick="event.stopPropagation(); app.minimizeCompose()" class="p-1 hover:text-white rounded-md hover:bg-sky-950/60 transition cursor-pointer" title="Minimize">
          <i data-lucide="minus" class="h-3.5 w-3.5 text-sky-400"></i>
        </button>
        <button type="button" onclick="event.stopPropagation(); app.maximizeCompose()" class="p-1 hover:text-white rounded-md hover:bg-sky-950/60 transition cursor-pointer hidden md:inline-flex" title="Maximize">
          <i data-lucide="maximize-2" id="compose-maximize-icon" class="h-3.5 w-3.5 text-sky-400"></i>
        </button>
        <button type="button" onclick="event.stopPropagation(); app.closeCompose()" class="p-1 hover:text-white rounded-md hover:bg-sky-950/60 transition cursor-pointer" title="Close">
          <i data-lucide="x" class="h-3.5 w-3.5 text-sky-400"></i>
        </button>
      </div>
    </div>

    <!-- Compose Form -->
    <div id="compose-body-container" class="p-5 flex-1 flex flex-col gap-3 overflow-y-auto max-h-[75vh] bg-[#080D1A] text-white">
      <!-- To Field -->
      <div class="relative flex items-center gap-2.5 border-b border-sky-500/15 pb-2">
        <span class="text-xs font-bold text-sky-300 w-16 shrink-0">To:</span>
        <input
          id="compose-to"
          type="text"
          placeholder="Recipient email address..."
          class="flex-1 bg-transparent text-sm text-white placeholder:text-slate-500 outline-none"
          oninput="app.onRecipientInput(this.value)"
        />
        <div class="flex items-center gap-1.5 text-xs text-sky-400">
          <button type="button" onclick="app.toggleCcBcc()" class="hover:text-white font-semibold px-2 py-0.5 rounded hover:bg-sky-950/60 transition cursor-pointer">Cc/Bcc</button>
        </div>
        <!-- Contact Auto-complete Suggestions Dropdown -->
        <div id="contact-suggestions" class="hidden absolute left-16 top-full mt-1 w-72 rounded-2xl border border-sky-500/25 bg-[#0B1120] p-1 shadow-2xl z-50 text-white"></div>
      </div>

      <!-- CC / BCC Rows (Collapsible) -->
      <div id="cc-bcc-row" class="hidden space-y-2 border-b border-sky-500/15 pb-2">
        <div class="flex items-center gap-2.5">
          <span class="text-xs font-bold text-sky-300 w-16 shrink-0">Cc:</span>
          <input id="compose-cc" type="text" placeholder="Carbon copy..." class="flex-1 bg-transparent text-sm text-white placeholder:text-slate-500 outline-none" />
        </div>
        <div class="flex items-center gap-2.5">
          <span class="text-xs font-bold text-sky-300 w-16 shrink-0">Bcc:</span>
          <input id="compose-bcc" type="text" placeholder="Blind carbon copy..." class="flex-1 bg-transparent text-sm text-white placeholder:text-slate-500 outline-none" />
        </div>
      </div>

      <!-- Subject Field -->
      <div class="flex items-center gap-2.5 border-b border-sky-500/15 pb-2">
        <span class="text-xs font-bold text-sky-300 w-16 shrink-0">Subject:</span>
        <input id="compose-subject" type="text" placeholder="Postcard subject..." class="flex-1 bg-transparent text-sm font-semibold text-white placeholder:text-slate-500 outline-none" />
      </div>

      <!-- Rich Formatting Toolbar -->
      <div class="flex flex-wrap items-center gap-1 py-1.5 border-b border-sky-500/15 text-slate-300 bg-[#080D1B]">
        <button type="button" onclick="app.execEditorCommand('bold')" title="Bold" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="bold" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('italic')" title="Italic" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="italic" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('underline')" title="Underline" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="underline" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('strikeThrough')" title="Strikethrough" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="strikethrough" class="h-3.5 w-3.5"></i>
        </button>
        <span class="h-3.5 w-px bg-sky-500/20 mx-1"></span>
        <button type="button" onclick="app.execEditorCommand('insertUnorderedList')" title="Bullet List" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="list" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('insertOrderedList')" title="Numbered List" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="list-ordered" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('formatBlock', 'blockquote')" title="Quote" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="quote" class="h-3.5 w-3.5"></i>
        </button>
        <span class="h-3.5 w-px bg-sky-500/20 mx-1"></span>
        <button type="button" onclick="app.insertLinkPrompt()" title="Insert Link" class="p-1.5 rounded-lg hover:bg-sky-950/60 hover:text-white transition cursor-pointer">
          <i data-lucide="link" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.insertSignature()" title="Insert Signature" class="flex items-center gap-1.5 p-1 px-2.5 rounded-lg hover:bg-sky-950/60 hover:text-white text-xs font-semibold text-sky-300 transition cursor-pointer">
          <i data-lucide="pen-tool" class="h-3.5 w-3.5 text-sky-400"></i>
          <span>Signature</span>
        </button>
      </div>

      <!-- Editable Message Body -->
      <div
        id="compose-editor"
        contenteditable="true"
        placeholder="Write something clever..."
        class="min-h-[160px] max-h-[300px] overflow-y-auto text-sm text-slate-100 leading-relaxed py-2 bg-transparent outline-none placeholder:text-slate-500"
      ></div>

      <!-- File Attachments List -->
      <div id="compose-attachments-list" class="flex flex-wrap gap-2 pt-2 border-t border-sky-500/15"></div>
    </div>

    <!-- Compose Bottom Actions -->
    <div id="compose-footer-container" class="px-3 sm:px-5 py-3 border-t border-sky-500/20 bg-[#080D1B] flex items-center justify-between shrink-0 gap-1.5 sm:gap-2">
      <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
        <label class="flex items-center gap-1 sm:gap-1.5 cursor-pointer rounded-full border border-sky-500/25 bg-[#0D152A] hover:bg-[#132040] px-2.5 sm:px-3.5 py-1.5 text-xs font-semibold text-sky-200 transition shadow-xs" title="Attach Files">
          <i data-lucide="paperclip" class="h-3.5 w-3.5 text-sky-400 shrink-0"></i>
          <span class="hidden sm:inline">Attach</span>
          <input type="file" id="compose-file-input" multiple class="hidden" onchange="app.handleFilesSelected(this.files)" />
        </label>
        <button onclick="app.saveDraft()" class="rounded-full border border-sky-500/25 bg-[#0D152A] hover:bg-[#132040] px-2.5 sm:px-3.5 py-1.5 text-xs font-semibold text-sky-200 transition shadow-xs cursor-pointer shrink-0" title="Save Draft">
          <span class="hidden sm:inline">Save Draft</span>
          <span class="sm:hidden">Save</span>
        </button>
      </div>

      <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
        <button onclick="app.discardDraft()" class="px-2 py-1.5 text-xs font-semibold text-slate-400 hover:text-white transition cursor-pointer shrink-0" title="Discard Draft">
          Discard
        </button>
        <button
          onclick="app.sendMessage()"
          id="send-mail-btn"
          class="flex items-center gap-1.5 sm:gap-2 rounded-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-display font-bold text-xs py-2 px-3.5 sm:px-5 shadow-[0_0_15px_rgba(14,165,233,0.4)] transition transform active:scale-95 cursor-pointer shrink-0 whitespace-nowrap"
          title="Send Postcard"
        >
          <i data-lucide="send" class="h-3.5 w-3.5 text-white shrink-0"></i>
          <span class="hidden sm:inline">Send Postcard</span>
          <span class="sm:hidden">Send</span>
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

