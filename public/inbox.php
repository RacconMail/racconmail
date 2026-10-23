<?php
session_start();
if (empty($_SESSION['current_user_id'])) {
    header("Location: /auth?mode=register");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <title>Raccon Mail — Clever email for web & mobile</title>
  <meta name="description" content="Raccon Mail: a super modern, secure inbox on the web and your smartphone." />
  <link rel="icon" href="/favicon.png" type="image/png" />
  
  <!-- FontAwesome 6 (Harmony SocialEngine icons) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Google AdSense -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4083745907535838" crossorigin="anonymous"></script>

  <!-- Early Theme Initializer to prevent flash -->
  <script>
    (function() {
      let theme = localStorage.getItem('raccon_theme') || 'harmony';
      if (theme === 'raccon' || theme === 'light') theme = 'harmony';
      if (theme === 'midnight') theme = 'dark';
      document.documentElement.className = 'theme-' + theme;
      const themeVars = {
        harmony: { '--background': '#EFEDE9', '--foreground': '#1E293B', '--card': '#FFFFFF', '--card-foreground': '#1E293B', '--primary': '#208ED3', '--primary-foreground': '#FFFFFF', '--secondary': '#F2F2F2', '--muted': '#F6F6F6', '--muted-foreground': '#64748B', '--accent': '#EBF5FB', '--border': '#D5D5D5', '--border-light': '#E2E8F0', '--mask': '#1E293B', '--fur': '#334155', '--cream': '#EFEDE9' },
        dark: { '--background': '#1D1D1F', '--foreground': '#FFFFFF', '--card': '#2B2C2E', '--card-foreground': '#FFFFFF', '--primary': '#208ED3', '--primary-foreground': '#FFFFFF', '--secondary': '#343536', '--muted': '#242526', '--muted-foreground': '#9CA3AF', '--accent': '#203345', '--border': '#404245', '--border-light': '#4F5155', '--mask': '#1D1D1F', '--fur': '#2B2C2E', '--cream': '#1D1D1F' },
        'midnight-green': { '--background': '#0A1414', '--foreground': '#E6F2F2', '--card': '#112222', '--card-foreground': '#E6F2F2', '--primary': '#00B392', '--primary-foreground': '#0A1414', '--secondary': '#1C3838', '--muted': '#162E2E', '--muted-foreground': '#80A6A6', '--accent': '#162E2E', '--border': '#1C3838', '--border-light': '#254A4A', '--mask': '#0A1414', '--fur': '#112222', '--cream': '#1C3838' }
      };
      if (themeVars[theme]) {
        for (const [k, v] of Object.entries(themeVars[theme])) {
          document.documentElement.style.setProperty(k, v);
        }
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

    /* 1. Light / Harmony Theme: White Card Pill, Bold Slate Text, Vibrant Azure Pencil */
    html.theme-harmony #btn-compose,
    body.theme-harmony #btn-compose,
    html:not(.theme-dark):not(.theme-midnight-green) #btn-compose {
      background-color: #FFFFFF !important;
      border: 1.5px solid #CBD5E1 !important;
      color: #1E293B !important;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.04) !important;
    }
    html.theme-harmony #btn-compose:hover,
    body.theme-harmony #btn-compose:hover,
    html:not(.theme-dark):not(.theme-midnight-green) #btn-compose:hover {
      background-color: #F8FAFC !important;
      border-color: #208ED3 !important;
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 18px rgba(32, 142, 211, 0.2) !important;
    }
    html.theme-harmony #btn-compose .compose-pencil-badge,
    body.theme-harmony #btn-compose .compose-pencil-badge,
    html:not(.theme-dark):not(.theme-midnight-green) #btn-compose .compose-pencil-badge {
      background-color: #EBF5FB !important;
      border: 1px solid #BAE6FD !important;
    }
    html.theme-harmony #btn-compose svg,
    html.theme-harmony #btn-compose i,
    body.theme-harmony #btn-compose svg,
    body.theme-harmony #btn-compose i,
    html:not(.theme-dark):not(.theme-midnight-green) #btn-compose svg,
    html:not(.theme-dark):not(.theme-midnight-green) #btn-compose i {
      stroke: #208ED3 !important;
      color: #208ED3 !important;
    }
    html.theme-harmony #btn-compose .compose-btn-text,
    body.theme-harmony #btn-compose .compose-btn-text,
    html:not(.theme-dark):not(.theme-midnight-green) #btn-compose .compose-btn-text {
      color: #1E293B !important;
    }

    /* 2. Dark Theme: Charcoal Card Pill, White Text, White Pencil */
    html.theme-dark #btn-compose,
    body.theme-dark #btn-compose {
      background-color: #2B2C2E !important;
      border: 1.5px solid #404245 !important;
      color: #FFFFFF !important;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.5) !important;
    }
    html.theme-dark #btn-compose:hover,
    body.theme-dark #btn-compose:hover {
      background-color: #353638 !important;
      border-color: #208ED3 !important;
      transform: translateY(-1.5px) !important;
    }
    html.theme-dark #btn-compose .compose-pencil-badge,
    body.theme-dark #btn-compose .compose-pencil-badge {
      background-color: rgba(255, 255, 255, 0.16) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
    }
    html.theme-dark #btn-compose svg,
    html.theme-dark #btn-compose i,
    body.theme-dark #btn-compose svg,
    body.theme-dark #btn-compose i {
      stroke: #FFFFFF !important;
      color: #FFFFFF !important;
    }
    html.theme-dark #btn-compose .compose-btn-text,
    body.theme-dark #btn-compose .compose-btn-text {
      color: #FFFFFF !important;
    }

    /* 3. Midnight Green Theme: Deep Pine Card Pill, Mint Text, White Pencil */
    html.theme-midnight-green #btn-compose,
    body.theme-midnight-green #btn-compose {
      background-color: #112222 !important;
      border: 1.5px solid #1C3838 !important;
      color: #E6F2F2 !important;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.5) !important;
    }
    html.theme-midnight-green #btn-compose:hover,
    body.theme-midnight-green #btn-compose:hover {
      background-color: #162E2E !important;
      border-color: #00B392 !important;
      transform: translateY(-1.5px) !important;
    }
    html.theme-midnight-green #btn-compose .compose-pencil-badge,
    body.theme-midnight-green #btn-compose .compose-pencil-badge {
      background-color: rgba(0, 179, 146, 0.22) !important;
      border: 1px solid rgba(0, 179, 146, 0.35) !important;
    }
    html.theme-midnight-green #btn-compose svg,
    html.theme-midnight-green #btn-compose i,
    body.theme-midnight-green #btn-compose svg,
    body.theme-midnight-green #btn-compose i {
      stroke: #FFFFFF !important;
      color: #FFFFFF !important;
    }
    html.theme-midnight-green #btn-compose .compose-btn-text,
    body.theme-midnight-green #btn-compose .compose-btn-text {
      color: #E6F2F2 !important;
    }
  </style>
</head>
<body class="bg-background text-foreground flex flex-col h-screen select-none">

  <!-- TOP POSTAL STRIPE ACCENT -->
  <div class="stripes h-1.5 w-full shrink-0"></div>

  <!-- TOP APP BAR -->
  <header class="h-14 border-b border-border bg-card px-3 md:px-4 flex items-center justify-between shrink-0 shadow-sm z-20">
    <!-- Brand / Mascot / Hamburger (Fixed stationary logo so it NEVER overlaps menus or buttons) -->
    <div class="flex items-center gap-2 md:gap-3 shrink-0">
      <!-- Mobile Sidebar Toggle -->
      <button
        onclick="app.toggleMobileSidebar()"
        class="md:hidden p-2 rounded-xl hover:bg-muted text-foreground transition"
        title="Open Folders"
      >
        <i data-lucide="menu" class="h-5 w-5"></i>
      </button>

      <!-- Logo returns cleanly to Inbox -->
      <div class="flex items-center gap-2 md:gap-2.5 font-display text-base md:text-xl font-bold tracking-tight cursor-pointer shrink-0 group" onclick="app.returnToInbox()" title="Return to Inbox">
        <img src="/raccoon-logo.png" alt="Raccon Mail" class="h-8 w-8 md:h-9 md:w-9 object-contain raccoon-wiggle shrink-0" />
        <span class="text-foreground hidden sm:inline shrink-0">Raccon Mail</span>
      </div>
      <span class="hidden lg:inline-flex items-center gap-1.5 rounded-full bg-accent/70 px-2.5 py-0.5 text-xs font-medium text-foreground/80 shrink-0">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
        Masked & Secure
      </span>
    </div>

    <!-- Raccon Mail Global Search Bar (Responsive with min-w-0 so it never pushes or overlaps buttons) -->
    <div class="flex-1 min-w-0 max-w-xl ml-1.5 mr-2 sm:mx-4 relative" id="global-search-container">
      <div class="relative flex items-center">
        <i data-lucide="search" class="absolute left-3.5 h-4 w-4 text-muted-foreground pointer-events-none"></i>
        <input
          id="global-search"
          type="text"
          autocomplete="off"
          placeholder="Search mail..."
          class="w-full rounded-full border border-border bg-muted/60 pl-9 pr-8 sm:pr-20 py-1.5 text-xs sm:text-sm outline-none transition focus:bg-card focus:border-primary focus:ring-1 focus:ring-primary text-foreground"
          onclick="app.onSearchClick()"
          oninput="app.onSearchInput(this.value)"
          onfocus="app.onSearchClick()"
          onkeydown="app.onSearchKeyDown(event)"
        />
        <button id="search-clear-btn" class="hidden absolute right-3 sm:right-10 text-muted-foreground hover:text-foreground text-xs" onclick="app.clearSearch()">
          <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
        <span class="absolute right-3 hidden sm:inline text-[11px] font-mono text-muted-foreground bg-card px-1.5 py-0.5 rounded border border-border">/</span>
      </div>

      <!-- Live Search Suggestions Dropdown (Loop across Inbox, Spam, Sent & Senders) -->
      <div id="search-suggestions-dropdown" class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-card rounded-2xl border border-border shadow-2xl overflow-hidden max-h-96 flex-col text-xs divide-y divide-border/60">
        <!-- Populated dynamically by app.renderSearchSuggestions -->
      </div>
    </div>

    <!-- Header Actions & Account Switcher -->
    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
      <!-- World Stories Button -->
      <button
        onclick="app.openStoriesModal()"
        title="World Stories — Read global folktales & fables"
        class="flex items-center gap-1.5 rounded-full bg-primary/10 hover:bg-primary/20 text-primary border border-primary/25 px-2.5 sm:px-3 py-1.5 text-xs font-bold transition cursor-pointer active:scale-95 shadow-2xs shrink-0"
      >
        <i data-lucide="book-open" class="h-3.5 w-3.5 text-primary shrink-0"></i>
        <span class="hidden sm:inline">Stories</span>
        <span class="text-[10px] bg-primary text-white px-1.5 py-0.2 rounded-full font-bold hidden md:inline">175+</span>
      </button>

      <!-- Account Switcher Dropdown -->
      <div class="relative">
        <button
          onclick="app.toggleAccountDropdown()"
          class="flex items-center gap-2 rounded-full border border-border bg-card hover:bg-muted/50 p-1 pl-2 text-xs transition"
        >
          <span id="active-user-name" class="font-medium max-w-[100px] truncate hidden md:inline">Clever Raccoon</span>
          <img id="active-user-avatar" src="/raccoon-logo.png" class="h-7 w-7 rounded-full bg-muted object-cover aspect-square border border-border shrink-0" />
          <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-muted-foreground mr-1"></i>
        </button>

        <!-- Dropdown Menu -->
        <div id="account-dropdown" class="hidden absolute right-0 mt-2 w-72 rounded-3xl border border-border bg-card overflow-hidden shadow-2xl z-50">
          <!-- Cover Photo Header -->
          <div id="dropdown-user-cover" class="h-20 w-full bg-gradient-to-r from-fur via-mask to-fur relative bg-cover bg-center">
            <button
              onclick="app.openProfileModal()"
              class="absolute top-2 right-2 p-1.5 rounded-full bg-black/40 hover:bg-black/60 text-white transition text-xs flex items-center gap-1 backdrop-blur-xs"
              title="Edit Profile & Photos"
            >
              <i data-lucide="camera" class="h-3.5 w-3.5"></i>
            </button>
          </div>

          <!-- User Info with overlapping Avatar -->
          <div class="px-4 pb-3 pt-0 relative border-b border-border">
            <div class="flex items-end justify-between -mt-8 mb-2">
              <img id="dropdown-user-avatar" src="/raccoon-logo.png" class="h-16 w-16 rounded-full bg-card object-cover aspect-square border-4 border-card shadow-md cursor-pointer shrink-0" onclick="app.openProfileModal()" />
              <button
                onclick="app.openProfileModal()"
                class="rounded-full border border-border bg-muted/60 hover:bg-muted px-3 py-1 text-[11px] font-bold text-foreground transition flex items-center gap-1"
              >
                <i data-lucide="edit-3" class="h-3 w-3"></i>
                <span>Edit Profile</span>
              </button>
            </div>
            <p id="dropdown-user-name" class="text-sm font-bold truncate text-foreground">Clever Raccoon</p>
            <p id="dropdown-user-email" class="text-xs text-muted-foreground truncate">me@racconmail.eu</p>
          </div>

          <!-- Dropdown Navigation Links -->
          <div class="p-2 space-y-0.5">
            <button onclick="app.openProfileModal()" class="w-full text-left flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs hover:bg-muted font-medium transition text-foreground">
              <i data-lucide="user" class="h-4 w-4 text-muted-foreground"></i>
              Profile &amp; Themes
            </button>
            <button onclick="app.openSettingsModal()" class="w-full text-left flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs hover:bg-muted font-medium transition text-foreground">
              <i data-lucide="settings" class="h-4 w-4 text-muted-foreground"></i>
              Mail Settings &amp; SMTP
            </button>
            <a href="/" class="w-full text-left flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs hover:bg-muted font-medium transition text-muted-foreground hover:text-foreground">
              <i data-lucide="home" class="h-4 w-4"></i>
              Landing Page
            </a>
            <button onclick="app.logout()" class="w-full text-left flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs hover:bg-red-50 text-red-600 font-bold transition">
              <i data-lucide="log-out" class="h-4 w-4"></i>
              Sign Out
            </button>
          </div>

          <!-- Switchable accounts container (prevents app.js null crash) -->
          <div id="accounts-list" class="p-2 border-t border-border space-y-1 bg-muted/20"></div>
        </div>
      </div>

      <!-- Prominent Header Sign Out Button -->
      <button
        onclick="app.logout()"
        title="Sign Out of Raccon Mail"
        class="flex items-center gap-1.5 rounded-full border border-border bg-card hover:bg-red-50 hover:border-red-200 text-muted-foreground hover:text-red-600 px-3 py-1.5 text-xs font-semibold transition"
      >
        <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
        <span class="hidden md:inline">Sign Out</span>
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
          class="w-full flex items-center justify-center gap-2.5 rounded-full font-display font-bold text-sm py-3 px-4 shadow-md transition transform active:scale-95 cursor-pointer bg-card border border-border"
        >
          <span class="compose-pencil-badge w-6 h-6 rounded-full flex items-center justify-center shrink-0 transition bg-accent border border-primary/25">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0 text-primary">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
              <path d="m15 5 4 4"/>
            </svg>
          </span>
          <span class="compose-btn-text font-bold text-foreground">Compose Postcard</span>
          <span class="paw-bounce">🐾</span>
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

      <!-- Bottom Sidebar Utilities -->
      <div class="border-t border-border p-2 space-y-1 text-xs font-medium">
        <button onclick="app.openNewsModal()" class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-foreground bg-muted hover:bg-card transition font-bold border border-border mb-1 cursor-pointer">
          <div class="flex items-center gap-2">
            <span>🗞️</span>
            <span>Raccon News Wire</span>
          </div>
          <span class="text-[9px] bg-red-500 text-white px-1.5 py-0.2 rounded-full font-bold">5m Live</span>
        </button>
        <button onclick="app.openStoriesModal()" class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-foreground bg-primary/10 hover:bg-primary/20 transition font-bold border border-primary/20 mb-1 cursor-pointer">
          <div class="flex items-center gap-2">
            <span>📚</span>
            <span>World Stories</span>
          </div>
          <span class="text-[9px] bg-primary text-white px-1.5 py-0.2 rounded-full font-bold">175+ Tales</span>
        </button>
        <button onclick="app.openContactsView()" class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground transition">
          <div class="flex items-center gap-2.5">
            <i data-lucide="contact" class="h-4 w-4"></i>
            <span>Address Book</span>
          </div>
          <span id="contacts-count-badge" class="badge-count text-[10px]">5</span>
        </button>
        <button onclick="app.openSettingsModal()" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground transition">
          <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
          <span>Raccon / SMTP Config</span>
        </button>
        <button onclick="app.openPrivacyModal()" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-emerald-800 bg-emerald-500/10 hover:bg-emerald-500/20 transition font-bold border border-emerald-300/30">
          <i data-lucide="shield-check" class="h-4 w-4 text-emerald-600"></i>
          <span>Privacy &amp; Policy</span>
        </button>
        <button onclick="app.logout()" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-red-600 hover:bg-red-50 transition font-bold">
          <i data-lucide="log-out" class="h-4 w-4"></i>
          <span>Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- 2. MESSAGES LIST PANEL (Full width on mobile until email opened, Middle Panel on Desktop) -->
    <section id="messages-panel" class="w-full md:w-96 border-r border-border bg-card flex flex-col shrink-0 overflow-hidden">
      <!-- Folder Header & Filter Toolbar -->
      <div class="p-3 border-b border-border bg-card shrink-0 space-y-2">
        <div class="flex items-center justify-between">
          <div>
            <h1 id="current-folder-title" class="font-display text-lg font-bold">Inbox</h1>
            <p id="current-folder-subtitle" class="text-xs text-muted-foreground">Loading emails...</p>
          </div>
          <div class="flex items-center gap-1.5">
            <button onclick="app.openNewsModal()" title="Raccon News Wire (Updated every 5 mins from CBN)" class="px-2.5 py-1 rounded-full text-foreground bg-muted hover:bg-card border border-border transition flex items-center gap-1 text-xs font-bold shadow-xs cursor-pointer">
              <span>🗞️</span>
              <span class="hidden sm:inline">News</span>
              <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
            </button>
            <button onclick="app.refreshCurrentFolder()" title="Refresh" class="p-1.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition cursor-pointer">
              <i data-lucide="refresh-cw" class="h-4 w-4"></i>
            </button>
            <button id="empty-folder-btn" onclick="app.emptyCurrentFolder()" title="Empty Folder" class="hidden text-xs text-red-600 hover:bg-red-50 px-2 py-1 rounded-md transition font-medium">
              Empty
            </button>
          </div>
        </div>

        <!-- Filter Pills & Sorting -->
        <div class="flex items-center justify-between gap-1 pt-1">
          <div class="flex items-center gap-1.5 text-xs">
            <button onclick="app.setFilter('all')" id="filter-all" class="px-3 py-1 rounded-full font-bold btn-orange-matte transition shadow-xs">All</button>
            <button onclick="app.setFilter('unread')" id="filter-unread" class="px-3 py-1 rounded-full font-medium text-muted-foreground hover:bg-muted transition border border-transparent hover:border-border">Unread</button>
            <button onclick="app.setFilter('starred')" id="filter-starred" class="px-3 py-1 rounded-full font-medium text-muted-foreground hover:bg-muted transition border border-transparent hover:border-border">Starred</button>
            <button onclick="app.setFilter('has_attachments')" id="filter-has_attachments" class="px-3 py-1 rounded-full font-medium text-muted-foreground hover:bg-muted transition border border-transparent hover:border-border flex items-center gap-1">
              <i data-lucide="paperclip" class="h-3 w-3"></i> Files
            </button>
          </div>

          <!-- Sort menu -->
          <select id="sort-select" onchange="app.onSortChange(this.value)" class="text-xs bg-muted/60 border border-border rounded-lg px-2 py-1 outline-none text-muted-foreground cursor-pointer">
            <option value="date_desc">Newest</option>
            <option value="date_asc">Oldest</option>
            <option value="sender">Sender</option>
            <option value="subject">Subject</option>
          </select>
        </div>
      </div>

      <!-- Batch Action Toolbar (Reveals when items are checked) -->
      <div id="batch-toolbar" class="hidden bg-accent/80 border-b border-border px-3 py-2 flex items-center justify-between text-xs shrink-0 transition">
        <div class="flex items-center gap-2">
          <input type="checkbox" id="select-all-checkbox" onchange="app.toggleSelectAll(this.checked)" class="rounded border-border cursor-pointer" />
          <span id="selected-count-label" class="font-bold">0 selected</span>
        </div>
        <div class="flex items-center gap-1">
          <button onclick="app.batchAction('mark_read')" title="Mark as read" class="p-1 rounded hover:bg-card text-muted-foreground hover:text-foreground">
            <i data-lucide="mail-open" class="h-4 w-4"></i>
          </button>
          <button onclick="app.batchAction('mark_unread')" title="Mark as unread" class="p-1 rounded hover:bg-card text-muted-foreground hover:text-foreground">
            <i data-lucide="mail" class="h-4 w-4"></i>
          </button>
          <button onclick="app.batchAction('star')" title="Star" class="p-1 rounded hover:bg-card text-amber-500">
            <i data-lucide="star" class="h-4 w-4"></i>
          </button>
          <button onclick="app.batchAction('archive')" title="Archive" class="p-1 rounded hover:bg-card text-muted-foreground hover:text-foreground">
            <i data-lucide="archive" class="h-4 w-4"></i>
          </button>
          <button onclick="app.batchAction('trash')" title="Move to trash" class="p-1 rounded hover:bg-card text-red-600">
            <i data-lucide="trash-2" class="h-4 w-4"></i>
          </button>
        </div>
      </div>

      <!-- Messages Scrollable List -->
      <div id="messages-list" class="flex-1 overflow-y-auto divide-y divide-border-light">
        <!-- Rendered by JS -->
      </div>
    </section>

    <!-- 3. EMAIL READER & DETAILS PANEL (Right Panel, Full width on mobile when open) -->
    <main class="hidden md:flex flex-1 flex-col bg-background overflow-hidden relative" id="reader-panel">
      <!-- Rendered dynamically: Empty State OR Message Reader OR Photos & Files Gallery OR Contacts View -->
      <div id="reader-content" class="h-full flex flex-col overflow-y-auto">
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
            <button onclick="app.openCompose()" class="rounded-full bg-primary px-5 py-2 text-xs font-medium text-primary-foreground shadow-sm hover:bg-black transition">
              Write something clever
            </button>
            <button onclick="app.openStoriesModal()" class="rounded-full border border-border bg-card px-4 py-2 text-xs font-medium hover:bg-muted transition flex items-center gap-1.5 cursor-pointer">
              <span>📚</span>
              <span>Explore World Stories</span>
            </button>
          </div>
        </div>
      </div>
    </main>

  </div>

  <!-- NATIVE MOBILE BOTTOM NAVIGATION BAR (Polished Android & iOS App Navigation) -->
  <nav id="mobile-bottom-bar" class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-card/95 backdrop-blur-md border-t border-border flex items-center justify-around py-2 px-1 shadow-2xl safe-area-bottom">
    <button onclick="app.returnToInbox()" class="flex flex-col items-center justify-center gap-1 text-primary py-1 px-3 rounded-xl transition cursor-pointer" title="Inbox Mail">
      <i data-lucide="inbox" class="h-5 w-5"></i>
      <span class="text-[10px] font-bold">Mail</span>
    </button>
    <button onclick="app.focusMobileSearch()" class="flex flex-col items-center justify-center gap-1 text-muted-foreground hover:text-foreground py-1 px-3 rounded-xl transition cursor-pointer" title="Search all mail">
      <i data-lucide="search" class="h-5 w-5"></i>
      <span class="text-[10px] font-medium">Search</span>
    </button>
    <!-- Compose Center Button (Scaled to fit seamlessly in bottom nav bar) -->
    <button onclick="app.openCompose()" class="flex items-center justify-center -mt-2.5 h-10 w-10 rounded-full btn-orange-matte text-white shadow-md transform active:scale-90 transition border-2 border-card cursor-pointer shrink-0" title="Compose Postcard">
      <i data-lucide="pencil" class="h-4.5 w-4.5"></i>
    </button>
    <button onclick="app.openStoriesModal()" class="flex flex-col items-center justify-center gap-1 text-primary py-1 px-3 rounded-xl transition cursor-pointer" title="World Stories (worldstories.org.uk)">
      <i data-lucide="book-open" class="h-5 w-5 text-primary"></i>
      <span class="text-[10px] font-bold text-primary">Stories</span>
    </button>
    <button onclick="app.toggleMobileSidebar()" class="flex flex-col items-center justify-center gap-1 text-muted-foreground hover:text-foreground py-1 px-3 rounded-xl transition cursor-pointer" title="All Folders">
      <i data-lucide="menu" class="h-5 w-5"></i>
      <span class="text-[10px] font-medium">Folders</span>
    </button>
  </nav>

  <!-- FLOATING ACTION BUTTON (FAB) FOR DESKTOP / TABLET QUICK COMPOSE -->
  <button
    id="desktop-compose-fab"
    onclick="app.openCompose()"
    title="Compose New Message"
    class="hidden fixed bottom-6 right-6 z-30 h-14 w-14 rounded-full btn-orange-matte shadow-2xl items-center justify-center transform active:scale-90 transition border-2 border-white/20 cursor-pointer"
  >
    <i data-lucide="pencil" class="h-6 w-6 text-white"></i>
  </button>

  <!-- FLOATING / FULLSCREEN RICH COMPOSE MODAL (Mobile friendly) -->
  <div id="compose-modal" class="hidden fixed inset-0 md:inset-auto md:bottom-4 md:right-6 z-50 w-full md:max-w-2xl bg-card md:rounded-3xl border border-border shadow-soft flex flex-col overflow-hidden transition-all duration-200">
    <!-- Postcard top strip -->
    <div class="stripes-subtle h-2 w-full shrink-0"></div>

    <!-- Header bar -->
    <div class="px-5 py-3 border-b border-border bg-card flex items-center justify-between shrink-0">
      <div class="flex items-center gap-2">
        <img src="/raccoon-logo.png" class="h-5 w-5 object-contain" />
        <h3 id="compose-title" class="font-display font-bold text-sm">New Clever Postcard</h3>
      </div>
      <div class="flex items-center gap-2 text-muted-foreground">
        <span id="draft-status-pill" class="text-[11px] text-muted-foreground"></span>
        <button onclick="app.closeCompose()" class="p-1 hover:text-foreground rounded-full hover:bg-muted transition">
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>
    </div>

    <!-- Compose Form -->
    <div class="p-5 flex-1 flex flex-col gap-3 overflow-y-auto max-h-[75vh]">
      <!-- To Field -->
      <div class="relative flex items-center border-b border-border pb-1.5">
        <span class="text-xs font-semibold text-muted-foreground w-12">To:</span>
        <input
          id="compose-to"
          type="text"
          placeholder="Recipient email address..."
          class="flex-1 bg-transparent text-sm outline-none"
          oninput="app.onRecipientInput(this.value)"
        />
        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
          <button type="button" onclick="app.toggleCcBcc()" class="hover:text-foreground">Cc/Bcc</button>
        </div>
        <!-- Contact Auto-complete Suggestions Dropdown -->
        <div id="contact-suggestions" class="hidden absolute left-12 top-full mt-1 w-72 rounded-xl border border-border bg-card p-1 shadow-soft z-50"></div>
      </div>

      <!-- CC / BCC Rows (Collapsible) -->
      <div id="cc-bcc-row" class="hidden space-y-2 border-b border-border pb-2">
        <div class="flex items-center">
          <span class="text-xs font-semibold text-muted-foreground w-12">Cc:</span>
          <input id="compose-cc" type="text" placeholder="Carbon copy..." class="flex-1 bg-transparent text-sm outline-none" />
        </div>
        <div class="flex items-center">
          <span class="text-xs font-semibold text-muted-foreground w-12">Bcc:</span>
          <input id="compose-bcc" type="text" placeholder="Blind carbon copy..." class="flex-1 bg-transparent text-sm outline-none" />
        </div>
      </div>

      <!-- Subject Field -->
      <div class="flex items-center border-b border-border pb-1.5">
        <span class="text-xs font-semibold text-muted-foreground w-12">Subject:</span>
        <input id="compose-subject" type="text" placeholder="Postcard subject..." class="flex-1 bg-transparent text-sm font-medium outline-none" />
      </div>

      <!-- Rich Formatting Toolbar (Raccon Mail style) -->
      <div class="flex flex-wrap items-center gap-1 py-1 border-b border-border text-muted-foreground">
        <button type="button" onclick="app.execEditorCommand('bold')" title="Bold" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="bold" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('italic')" title="Italic" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="italic" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('underline')" title="Underline" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="underline" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('strikeThrough')" title="Strikethrough" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="strikethrough" class="h-3.5 w-3.5"></i>
        </button>
        <span class="h-3 w-px bg-border mx-1"></span>
        <button type="button" onclick="app.execEditorCommand('insertUnorderedList')" title="Bullet List" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="list" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('insertOrderedList')" title="Numbered List" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="list-ordered" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.execEditorCommand('formatBlock', 'blockquote')" title="Quote" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="quote" class="h-3.5 w-3.5"></i>
        </button>
        <span class="h-3 w-px bg-border mx-1"></span>
        <button type="button" onclick="app.insertLinkPrompt()" title="Insert Link" class="p-1 rounded hover:bg-muted hover:text-foreground">
          <i data-lucide="link" class="h-3.5 w-3.5"></i>
        </button>
        <button type="button" onclick="app.insertSignature()" title="Insert Postcard Signature" class="p-1 rounded hover:bg-muted hover:text-foreground text-xs font-semibold px-2">
          ✍️ Signature
        </button>
      </div>

      <!-- Editable Message Body -->
      <div
        id="compose-editor"
        contenteditable="true"
        placeholder="Write something clever..."
        class="min-h-[160px] max-h-[300px] overflow-y-auto text-sm outline-none leading-relaxed py-2 bg-transparent"
      ></div>

      <!-- File Attachments List -->
      <div id="compose-attachments-list" class="flex flex-wrap gap-2 pt-2 border-t border-border-light"></div>
    </div>

    <!-- Compose Bottom Actions (Optimized to fit all mobile viewports cleanly) -->
    <div class="px-2.5 sm:px-5 py-2 sm:py-3 border-t border-border bg-muted/40 flex items-center justify-between shrink-0 gap-1 sm:gap-2">
      <div class="flex items-center gap-1 sm:gap-2 shrink-0">
        <label class="flex items-center gap-1 cursor-pointer rounded-full border border-border bg-card hover:bg-muted px-2.5 sm:px-3 py-1.5 text-xs font-medium text-foreground transition shrink-0" title="Attach Files">
          <i data-lucide="paperclip" class="h-3.5 w-3.5"></i>
          <span class="hidden sm:inline">Attach</span>
          <input type="file" id="compose-file-input" multiple class="hidden" onchange="app.handleFilesSelected(this.files)" />
        </label>
        <button onclick="app.saveDraft()" class="rounded-full border border-border bg-card hover:bg-muted px-2 sm:px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition shrink-0" title="Save Draft">
          <span class="hidden sm:inline">Save </span>Draft
        </button>
      </div>

      <div class="flex items-center gap-1 sm:gap-2 shrink-0">
        <button onclick="app.discardDraft()" class="px-1.5 sm:px-2.5 py-1.5 text-xs font-medium text-muted-foreground hover:text-red-600 transition shrink-0" title="Discard Draft">
          Discard
        </button>
        <button
          onclick="app.sendMessage()"
          id="send-mail-btn"
          class="flex items-center gap-1.5 rounded-full btn-orange-matte font-display font-bold text-xs py-1.5 px-3 sm:px-3.5 shadow-sm transition transform active:scale-95 cursor-pointer shrink-0 whitespace-nowrap"
          title="Send Postcard"
        >
          <i data-lucide="send" class="h-3.5 w-3.5 text-white"></i>
          <span>Send Postcard</span>
          <span class="paw-bounce text-xs">🐾</span>
        </button>
      </div>
    </div>
  </div>

  <!-- NEW CUSTOM FOLDER MODAL -->
  <div id="new-folder-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
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
  <div id="contacts-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
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
  <div id="contact-form-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
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

  <!-- PROFILE & COVER PHOTO MODAL -->
  <div id="profile-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-3xl bg-card shadow-2xl border border-border flex flex-col max-h-[92vh] overflow-hidden">
      <div class="stripes-subtle h-2 w-full"></div>

      <div class="p-4 sm:p-5 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-2 font-display text-base sm:text-lg font-bold">
          <i data-lucide="user-check" class="h-5 w-5 text-primary"></i>
          <span>Profile Picture &amp; Cover Photo</span>
        </div>
        <button onclick="app.closeProfileModal()" class="p-1.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <div class="p-5 flex-1 overflow-y-auto space-y-6 text-xs">
        <!-- COVER PHOTO SECTION -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold text-foreground text-sm">Cover Photo</span>
            <label class="cursor-pointer rounded-full bg-primary hover:bg-black text-primary-foreground px-3 py-1 text-xs font-semibold flex items-center gap-1.5 transition shadow-sm">
              <i data-lucide="upload" class="h-3.5 w-3.5"></i>
              <span>Upload Cover</span>
              <input type="file" id="cover-file-input" accept="image/*" class="hidden" onchange="app.uploadCoverPhoto(this.files[0])" />
            </label>
          </div>

          <div class="relative rounded-2xl overflow-hidden border border-border bg-fur">
            <div id="profile-cover-preview" class="h-32 sm:h-36 w-full bg-cover bg-center bg-gradient-to-r from-fur via-mask to-fur"></div>

            <div class="absolute bottom-2 left-4 flex items-end gap-3">
              <div class="relative group">
                <img id="profile-avatar-preview" src="/raccoon-logo.png" class="h-16 w-16 sm:h-20 sm:w-20 rounded-full bg-card object-cover aspect-square border-4 border-card shadow-lg shrink-0" />
                <label class="absolute inset-0 rounded-full bg-black/40 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer text-[10px] font-bold">
                  <i data-lucide="camera" class="h-4 w-4 mb-0.5"></i>
                  <span>Change</span>
                  <input type="file" id="avatar-file-input" accept="image/*" class="hidden" onchange="app.uploadAvatar(this.files[0])" />
                </label>
              </div>

              <div class="mb-2 text-white drop-shadow-md">
                <p id="profile-preview-name" class="font-bold text-sm sm:text-base leading-tight">Clever Raccoon</p>
                <p id="profile-preview-email" class="text-xs opacity-90">user@racconmail.eu</p>
              </div>
            </div>
          </div>

          <!-- Preset Cover Photos -->
          <div class="mt-3">
            <span class="text-[11px] font-semibold text-muted-foreground block mb-1.5">Or choose a scenic nocturnal cover:</span>
            <div class="grid grid-cols-4 gap-2">
              <button
                type="button"
                onclick="app.selectPresetCover('linear-gradient(135deg, #181c1f 0%, #2c333a 50%, #121518 100%)')"
                class="h-10 rounded-xl border border-border overflow-hidden hover:ring-2 hover:ring-primary transition"
                style="background: linear-gradient(135deg, #181c1f 0%, #2c333a 50%, #121518 100%);"
                title="Deep Midnight"
              ></button>
              <button
                type="button"
                onclick="app.selectPresetCover('linear-gradient(135deg, #1e3a2f 0%, #0f1f18 100%)')"
                class="h-10 rounded-xl border border-border overflow-hidden hover:ring-2 hover:ring-primary transition"
                style="background: linear-gradient(135deg, #1e3a2f 0%, #0f1f18 100%);"
                title="Pine Forest"
              ></button>
              <button
                type="button"
                onclick="app.selectPresetCover('linear-gradient(135deg, #3b281c 0%, #1a120c 100%)')"
                class="h-10 rounded-xl border border-border overflow-hidden hover:ring-2 hover:ring-primary transition"
                style="background: linear-gradient(135deg, #3b281c 0%, #1a120c 100%);"
                title="Campfire Glow"
              ></button>
              <button
                type="button"
                onclick="app.selectPresetCover('linear-gradient(135deg, #1a2a44 0%, #2a1b3d 100%)')"
                class="h-10 rounded-xl border border-border overflow-hidden hover:ring-2 hover:ring-primary transition"
                style="background: linear-gradient(135deg, #1a2a44 0%, #2a1b3d 100%);"
                title="Northern Aurora"
              ></button>
            </div>
          </div>
        </div>

        <!-- PROFILE AVATAR SECTION -->
        <div class="border-t border-border pt-4">
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold text-foreground text-sm">Profile Picture (Avatar)</span>
            <label class="cursor-pointer rounded-full border border-border bg-muted/60 hover:bg-muted px-3 py-1 text-xs font-semibold flex items-center gap-1.5 transition">
              <i data-lucide="camera" class="h-3.5 w-3.5"></i>
              <span>Upload Custom Avatar</span>
              <input type="file" accept="image/*" class="hidden" onchange="app.uploadAvatar(this.files[0])" />
            </label>
          </div>

          <p class="text-[11px] text-muted-foreground mb-2">Select a nocturnal woodland mascot or upload your own photo:</p>
          <div class="flex flex-wrap items-center gap-3">
            <button type="button" onclick="app.selectPresetAvatar('/raccoon-logo.png')" class="p-1 rounded-2xl border-2 border-primary bg-muted/40 hover:scale-105 transition overflow-hidden">
              <img src="/raccoon-logo.png" class="h-10 w-10 object-cover aspect-square rounded-xl" />
            </button>
            <button type="button" onclick="app.selectPresetAvatar('https://api.dicebear.com/7.x/bottts/svg?seed=ShadowRaccoon')" class="p-1 rounded-2xl border border-border hover:border-primary bg-muted/40 hover:scale-105 transition overflow-hidden">
              <img src="https://api.dicebear.com/7.x/bottts/svg?seed=ShadowRaccoon" class="h-10 w-10 object-cover aspect-square rounded-xl" />
            </button>
            <button type="button" onclick="app.selectPresetAvatar('https://api.dicebear.com/7.x/bottts/svg?seed=NocturnalBandit')" class="p-1 rounded-2xl border border-border hover:border-primary bg-muted/40 hover:scale-105 transition overflow-hidden">
              <img src="https://api.dicebear.com/7.x/bottts/svg?seed=NocturnalBandit" class="h-10 w-10 object-cover aspect-square rounded-xl" />
            </button>
            <button type="button" onclick="app.selectPresetAvatar('https://api.dicebear.com/7.x/bottts/svg?seed=ForestScout')" class="p-1 rounded-2xl border border-border hover:border-primary bg-muted/40 hover:scale-105 transition overflow-hidden">
              <img src="https://api.dicebear.com/7.x/bottts/svg?seed=ForestScout" class="h-10 w-10 object-cover aspect-square rounded-xl" />
            </button>
            <button type="button" onclick="app.selectPresetAvatar('https://api.dicebear.com/7.x/bottts/svg?seed=MidnightOwl')" class="p-1 rounded-2xl border border-border hover:border-primary bg-muted/40 hover:scale-105 transition overflow-hidden">
              <img src="https://api.dicebear.com/7.x/bottts/svg?seed=MidnightOwl" class="h-10 w-10 object-cover aspect-square rounded-xl" />
            </button>
            <button type="button" onclick="app.selectPresetAvatar('https://api.dicebear.com/7.x/bottts/svg?seed=LunaStar')" class="p-1 rounded-2xl border border-border hover:border-primary bg-muted/40 hover:scale-105 transition overflow-hidden">
              <img src="https://api.dicebear.com/7.x/bottts/svg?seed=LunaStar" class="h-10 w-10 object-cover aspect-square rounded-xl" />
            </button>
          </div>
        </div>

        <!-- EMAIL THEME COLOR SELECTION (4 DISTINCT THEMES) -->
        <div class="border-t border-border pt-4">
          <div class="flex items-center justify-between mb-1.5">
            <div>
              <span class="font-bold text-foreground text-sm flex items-center gap-1.5">
                <i data-lucide="palette" class="h-4 w-4 text-primary"></i>
                <span>Mailbox Color Theme</span>
              </span>
              <p class="text-[11px] text-muted-foreground mt-0.5">Choose your preferred visual atmosphere for inside webmail:</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">
            <!-- Theme 1: Harmony / Light -->
            <button
              type="button"
              onclick="app.setTheme('harmony')"
              id="theme-btn-harmony"
              class="theme-choice-btn rounded-2xl border-2 p-3 text-left transition flex flex-col justify-between group cursor-pointer relative bg-card hover:border-primary"
            >
              <div class="flex items-center gap-1.5 mb-1.5">
                <span class="text-base">☀️</span>
                <span class="font-bold text-xs truncate text-foreground">Harmony Light</span>
              </div>
              <div class="flex items-center gap-1.5 my-1.5">
                <span class="h-3.5 w-3.5 rounded-full" style="background: #EFEDE9; border: 1px solid #D5D5D5;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #FFFFFF; border: 1px solid #D5D5D5;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #208ED3;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #1E293B;"></span>
              </div>
              <span class="text-[10px] text-muted-foreground mt-1">Azure Sky &amp; Sand</span>
            </button>

            <!-- Theme 2: Harmony Dark -->
            <button
              type="button"
              onclick="app.setTheme('dark')"
              id="theme-btn-dark"
              class="theme-choice-btn rounded-2xl border-2 p-3 text-left transition flex flex-col justify-between group cursor-pointer relative bg-card hover:border-primary"
            >
              <div class="flex items-center gap-1.5 mb-1.5">
                <span class="text-base">🌙</span>
                <span class="font-bold text-xs truncate text-foreground">Harmony Dark</span>
              </div>
              <div class="flex items-center gap-1.5 my-1.5">
                <span class="h-3.5 w-3.5 rounded-full" style="background: #1D1D1F; border: 1px solid #404245;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #2B2C2E; border: 1px solid #404245;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #208ED3;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #FFFFFF;"></span>
              </div>
              <span class="text-[10px] text-muted-foreground mt-1">OLED Charcoal</span>
            </button>

            <!-- Theme 3: Midnight Green -->
            <button
              type="button"
              onclick="app.setTheme('midnight-green')"
              id="theme-btn-midnight-green"
              class="theme-choice-btn rounded-2xl border-2 p-3 text-left transition flex flex-col justify-between group cursor-pointer relative bg-card hover:border-primary"
            >
              <div class="flex items-center gap-1.5 mb-1.5">
                <span class="text-base">🌲</span>
                <span class="font-bold text-xs truncate text-foreground">Midnight Green</span>
              </div>
              <div class="flex items-center gap-1.5 my-1.5">
                <span class="h-3.5 w-3.5 rounded-full" style="background: #0A1414; border: 1px solid #1C3838;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #112222; border: 1px solid #1C3838;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #00B392;"></span>
                <span class="h-3.5 w-3.5 rounded-full" style="background: #E6F2F2;"></span>
              </div>
              <span class="text-[10px] text-muted-foreground mt-1">Cyber Teal</span>
            </button>
          </div>
        </div>

        <!-- PROFILE DETAILS FORM -->
        <div class="border-t border-border pt-4 space-y-3">
          <div>
            <label class="block text-xs font-bold text-muted-foreground mb-1">Display Name</label>
            <input
              id="profile-name-input"
              type="text"
              class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none focus:bg-card focus:border-primary"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-muted-foreground mb-1">Email Address</label>
            <input
              id="profile-email-input"
              type="text"
              readonly
              class="w-full rounded-xl border border-border bg-muted/20 p-2.5 text-xs text-muted-foreground font-mono cursor-not-allowed"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-muted-foreground mb-1">Custom Postcard Signature</label>
            <textarea
              id="profile-signature-input"
              rows="2"
              class="w-full rounded-xl border border-border bg-muted/40 p-2.5 text-xs outline-none focus:bg-card focus:border-primary"
            ></textarea>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="p-4 border-t border-border bg-muted/30 flex items-center justify-end gap-2">
        <button
          onclick="app.closeProfileModal()"
          class="rounded-full px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted transition"
        >
          Cancel
        </button>
        <button
          onclick="app.saveProfile()"
          id="profile-save-btn"
          class="rounded-full bg-primary hover:bg-black text-primary-foreground px-6 py-2 text-xs font-semibold transition shadow-sm"
        >
          Save Profile Changes
        </button>
      </div>
    </div>
  </div>

  <!-- SETTINGS & REAL IMAP / SMTP MODAL -->
  <div id="settings-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
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
              <i data-lucide="check-circle" class="h-3.5 w-3.5 text-emerald-600"></i>
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
  <div id="postcard-print-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
    <div class="w-full max-w-3xl bg-[#fdfaf5] rounded-3xl p-8 border-4 border-[#e0d6c5] shadow-2xl relative overflow-hidden text-foreground">
      <button onclick="app.closePostcardModal()" class="absolute top-4 right-4 p-1 rounded-full bg-muted/80 hover:bg-muted text-muted-foreground hover:text-foreground">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>

      <!-- Printable Postcard Body -->
      <div id="printable-postcard" class="grid grid-cols-1 md:grid-cols-2 gap-8 relative">
        <!-- Airmail border inner -->
        <div class="col-span-full stripes-subtle h-3 rounded-full mb-2"></div>

        <!-- Left Half: Postcard Letter Message -->
        <div class="flex flex-col justify-between pr-4 border-r border-[#d4c6b0]">
          <div>
            <div class="flex items-center gap-2 mb-4">
              <img src="/raccoon-logo.png" class="h-8 w-8 object-contain" />
              <span class="font-display font-bold text-xs uppercase tracking-widest text-[#786b59]">Official Raccon Postcard</span>
            </div>
            <h2 id="postcard-subject" class="font-display text-xl font-bold mb-3 text-[#2a2620]"></h2>
            <div id="postcard-body" class="font-handwriting text-2xl text-[#3b342a] leading-relaxed whitespace-pre-wrap min-h-[140px]"></div>
          </div>
          <div class="mt-6 pt-4 border-t border-[#e2d8c9] text-xs font-display text-[#786b59]">
            From: <span id="postcard-from" class="font-bold"></span>
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
              <div class="text-[9px] font-bold text-[#8b7355] leading-tight">
                RACCON<br/>POSTAGE<br/>25 ACORNS
              </div>
            </div>
          </div>

          <!-- Bottom: Postcard Address Lines -->
          <div class="space-y-4 my-8">
            <div class="border-b border-[#a89880] pb-1 text-sm font-handwriting text-xl text-[#3b342a]">
              To: <span id="postcard-to"></span>
            </div>
            <div class="border-b border-[#a89880] pb-1 text-sm font-handwriting text-xl text-[#786b59]">
              Forest Burrow #42, Whispering Pines
            </div>
            <div class="border-b border-[#a89880] pb-1 text-sm font-handwriting text-xl text-[#786b59]">
              The Great Northern Woodland, ZIP 00042
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-4">
            <button onclick="window.print()" class="rounded-full bg-primary text-primary-foreground px-5 py-2 text-xs font-medium hover:bg-black transition flex items-center gap-1.5">
              <i data-lucide="printer" class="h-3.5 w-3.5"></i>
              <span>Print Physical Postcard</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PRIVACY POLICY MODAL -->
  <div id="privacy-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-2xl rounded-3xl bg-card p-6 sm:p-8 shadow-2xl border border-border relative overflow-hidden max-h-[90vh] flex flex-col">
      <div class="stripes-subtle h-2.5 -mx-8 -mt-8 mb-4 shrink-0"></div>

      <!-- Header -->
      <div class="flex items-start justify-between gap-4 pb-4 border-b border-border shrink-0">
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-2xl bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
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
            <i data-lucide="lock" class="h-4 w-4 text-emerald-600"></i>
            <span>Our Nocturnal Guarantee:</span>
          </p>
          <p class="text-xs text-muted-foreground mt-1">
            Raccon Mail operates with zero third-party telemetry, zero ad-network tracking, and zero email content analysis. Your emails, attachments, and contacts belong exclusively to you.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            1. Zero Tracker Policy &amp; No Device Fingerprinting
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            We do not collect IP addresses, hardware identifiers, or tracking cookies for marketing. No surveillance agents or data brokers monitor your mailbox.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            2. Nocturnal Postcard Masking &amp; Mail Transmission
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            When you send or receive postcards via <strong>@racconmail.eu</strong>, communications are routed through secure MIME multipart envelopes. Outgoing mail sent through our system aligns strictly with SPF, DKIM, and DMARC authentication protocols.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            3. Isolated Encrypted Mailboxes &amp; Server Storage
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            User credentials are protected using industry-standard salted <code>bcrypt/argon2</code> password hashing. Mailbox databases and uploaded attachments are guarded in isolated SQLite storage with strict filesystem ACLs.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            4. Absolute Data Ownership &amp; Right to Erasure (GDPR)
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            You have complete sovereignty over your data. You may delete individual messages, purge attachment stashes, or request complete account erasure at any time.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-foreground flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            5. Security Contacts &amp; Inquiries
          </h4>
          <p class="mt-1 text-xs text-muted-foreground">
            For security disclosures or compliance inquiries, contact our postmaster directly at <code class="text-primary font-bold">postmaster@racconmail.eu</code>.
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="pt-4 border-t border-border flex items-center justify-between shrink-0">
        <span class="text-xs text-muted-foreground flex items-center gap-1.5">
          <i data-lucide="lock" class="h-3.5 w-3.5 text-emerald-600"></i>
          <span>100% Masked Nocturnal Privacy Guaranteed</span>
        </span>
        <button onclick="app.closePrivacyModal()" class="rounded-full bg-primary hover:bg-black text-primary-foreground font-semibold px-5 py-2 text-xs transition cursor-pointer">
          Got It, Keep Me Safe
        </button>
      </div>
    </div>
  </div>

  <!-- RACCON NEWS MODAL (In-Mailbox 5-Minute Live Wire) -->
  <div id="news-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3 sm:p-6 backdrop-blur-sm">
    <div class="w-full max-w-5xl rounded-3xl bg-card p-5 sm:p-7 shadow-2xl border border-border relative overflow-hidden max-h-[92vh] flex flex-col">
      <div class="stripes-subtle h-2.5 -mx-8 -mt-8 mb-4 shrink-0"></div>

      <!-- Header -->
      <div class="flex items-start justify-between gap-4 pb-4 border-b border-border shrink-0">
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-2xl bg-orange-500/10 text-orange-700 border border-orange-500/20">
            <span class="text-xl">🗞️</span>
          </div>
          <div>
            <h3 class="font-display text-xl sm:text-2xl font-bold text-foreground flex items-center gap-2">
              <span>Raccon News Wire</span>
              <span class="text-xs font-mono bg-red-500/10 text-red-600 border border-red-500/20 px-2 py-0.5 rounded-full font-bold">Live 5m Sync</span>
            </h3>
            <p class="text-xs text-muted-foreground">Tech, Sport, Games, Music, and Weather &bull; Syndicated live from <a href="https://cbn.com/lp/cbn-news" target="_blank" rel="noopener noreferrer" class="font-bold text-primary underline">CBN News</a></p>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="/news" target="_blank" class="rounded-full bg-emerald-500 hover:bg-emerald-600 text-obsidian font-bold text-xs px-3.5 py-1.5 transition flex items-center gap-1.5 shadow-sm" title="Open modern full-page news wire">
            <i data-lucide="maximize-2" class="h-3.5 w-3.5"></i>
            <span>Open Full Page News</span>
          </a>
          <a href="https://cbn.com/lp/cbn-news" target="_blank" rel="noopener noreferrer" class="hidden sm:flex items-center gap-1 rounded-full bg-primary hover:bg-black text-primary-foreground text-xs font-semibold px-3 py-1.5 transition">
            <span>CBN News</span>
            <i data-lucide="external-link" class="h-3 w-3"></i>
          </a>
          <button onclick="app.closeNewsModal()" class="p-2 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>
      </div>

      <!-- 5 Columns Container -->
      <div class="overflow-y-auto py-4 pr-1">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5" id="inbox-news-grid">
          <!-- Populated by app.js -->
        </div>
      </div>

      <!-- Footer Bar -->
      <div class="pt-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground shrink-0">
        <div class="flex items-center gap-2">
          <span>⏱️ Auto-updates every 5 mins &bull; Source: <a href="https://cbn.com/lp/cbn-news" target="_blank" class="font-bold underline text-foreground">CBN News</a></span>
          <span class="text-muted-foreground">&bull;</span>
          <a href="/news" target="_blank" class="font-bold text-primary hover:underline flex items-center gap-1">
            <span>Open Full Page</span>
            <i data-lucide="arrow-up-right" class="h-3 w-3"></i>
          </a>
        </div>
        <button onclick="app.closeNewsModal()" class="rounded-full bg-primary text-primary-foreground font-semibold px-4 py-1.5 transition">
          Close Wire
        </button>
      </div>
    </div>
  </div>

  <!-- WORLD STORIES MODAL (Modern Reader Module with folktales from worldstories.org.uk) -->
  <div id="stories-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3 sm:p-6 backdrop-blur-sm">
    <div class="w-full max-w-5xl rounded-3xl bg-card p-5 sm:p-7 shadow-2xl border border-border relative overflow-hidden max-h-[92vh] flex flex-col">
      <div class="stripes-subtle h-2.5 -mx-8 -mt-8 mb-4 shrink-0"></div>

      <!-- 1. LIBRARY CATALOG VIEW -->
      <div id="stories-catalog-view" class="flex flex-col flex-1 overflow-hidden">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 pb-4 border-b border-border shrink-0">
          <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-2xl bg-primary/10 text-primary border border-primary/20">
              <span class="text-2xl">📚</span>
            </div>
            <div>
              <h3 class="font-display text-xl sm:text-2xl font-bold text-foreground flex items-center gap-2">
                <span>World Stories</span>
                <span class="text-xs font-mono bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-full font-bold">English Library</span>
              </h3>
              <p class="text-xs text-muted-foreground">Folktales, myths, and fables from cultures around the globe &bull; Curated from <a href="https://worldstories.org.uk/lang/english" target="_blank" rel="noopener noreferrer" class="font-bold text-primary underline">worldstories.org.uk</a></p>
            </div>
          </div>
          <button onclick="app.closeStoriesModal()" class="p-1.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>

        <!-- Filter Pills & Search Bar -->
        <div class="pt-3 pb-2 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0 text-xs" id="story-filter-tabs">
            <button onclick="app.filterStories('all')" class="story-tab active px-3 py-1.5 rounded-full font-bold btn-orange-matte transition shadow-xs shrink-0 cursor-pointer">All Tales</button>
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

        <!-- Stories Grid -->
        <div class="flex-1 overflow-y-auto py-3 pr-1">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5" id="stories-grid-container">
            <!-- Rendered by app.renderStoriesGrid() -->
          </div>
        </div>

        <!-- Footer Bar -->
        <div class="pt-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-muted-foreground shrink-0">
          <div class="flex items-center gap-2">
            <span>🌍 175+ traditional stories &bull; Supporting literacy &amp; cultural heritage</span>
            <span class="hidden sm:inline">&bull;</span>
            <a href="https://worldstories.org.uk/lang/english" target="_blank" rel="noopener noreferrer" class="font-bold text-primary hover:underline flex items-center gap-1">
              <span>Open worldstories.org.uk</span>
              <i data-lucide="external-link" class="h-3 w-3"></i>
            </a>
          </div>
          <button onclick="app.closeStoriesModal()" class="rounded-full bg-muted hover:bg-accent text-foreground font-semibold px-4 py-1.5 transition cursor-pointer">
            Close Stories
          </button>
        </div>
      </div>

      <!-- 2. STORY READER VIEW (Slides in when a story is clicked) -->
      <div id="stories-reader-view" class="hidden flex-col flex-1 overflow-hidden">
        <!-- Reader Header -->
        <div class="flex items-center justify-between pb-3 border-b border-border shrink-0">
          <button onclick="app.backToStoriesCatalog()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-muted hover:bg-accent text-foreground font-bold text-xs transition cursor-pointer">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            <span>Back to Stories</span>
          </button>
          <div class="flex items-center gap-2">
            <button onclick="app.sendStoryAsPostcard()" class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full btn-orange-matte text-white font-bold text-xs transition cursor-pointer active:scale-95 shadow-xs">
              <i data-lucide="send" class="h-3.5 w-3.5"></i>
              <span>Send as Postcard</span>
              <span class="paw-bounce text-xs">🐾</span>
            </button>
            <button onclick="app.closeStoriesModal()" class="p-1.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition cursor-pointer">
              <i data-lucide="x" class="h-5 w-5"></i>
            </button>
          </div>
        </div>

        <!-- Reader Scrollable Content -->
        <div id="stories-reader-content" class="flex-1 overflow-y-auto py-5 px-2 sm:px-6 space-y-4">
          <!-- Populated by app.openStoryReader(storyId) -->
        </div>

        <!-- Reader Footer -->
        <div class="pt-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground shrink-0">
          <span id="story-reader-origin-badge">Origin</span>
          <a id="story-reader-source-link" href="https://worldstories.org.uk/lang/english" target="_blank" rel="noopener noreferrer" class="font-bold text-primary hover:underline flex items-center gap-1">
            <span>Listen &amp; read with audio on WorldStories UK</span>
            <i data-lucide="external-link" class="h-3 w-3"></i>
          </a>
        </div>
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

