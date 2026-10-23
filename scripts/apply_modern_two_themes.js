const fs = require('fs');
const path = require('path');

const baseDir = path.join(__dirname, '..', 'public');

// ==============================================================================
// 1. UPDATE public/css/raccoon-mail.css
// ==============================================================================
const cssPath = path.join(baseDir, 'css', 'raccoon-mail.css');
let css = fs.readFileSync(cssPath, 'utf8');

// Replace top theme definitions with pure 2-theme modern system
const modernThemeTokens = `/* ==========================================================================
   THEME SPECIFICATIONS: Clean Modern Light & Obsidian Dark (STRICTLY 2 THEMES)
   ========================================================================== */

/* 1. ☀️ Clean Modern Light Theme */
:root, html, body, html.theme-light, body.theme-light, html.theme-harmony, body.theme-harmony, html.theme-raccon, body.theme-raccon {
  --background: #F8FAFC; /* Crisp Clean Slate-50 body canvas */
  --foreground: #0F172A; /* Deep Slate-900 High Readability Headline */
  --card: #FFFFFF; /* Pure White Elevated Card */
  --card-alt: #F1F5F9;
  --card-foreground: #0F172A;
  --primary: #208ED3; /* Signature Modern Azure */
  --primary-hover: #1A7BB8;
  --primary-foreground: #FFFFFF;
  --primary-glow: rgba(32, 142, 211, 0.3);
  --secondary: #F1F5F9;
  --secondary-foreground: #0F172A;
  --secondary-hover: #E2E8F0;
  --muted: #F1F5F9;
  --muted-foreground: #64748B;
  --accent: #E0F2FE;
  --accent-foreground: #0284C7;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  --mask: #0F172A;
  --fur: #334155;
  --cream: #F8FAFC;
  --themewidget-radius: 16px;
  --btn-radius: 50px;
  --shadow-soft: 0 10px 30px -5px rgba(0, 0, 0, 0.05), 0 2px 6px rgba(0, 0, 0, 0.03);
  --shadow-card: 0 2px 12px rgba(0, 0, 0, 0.04);
  --stamp-red: #E11D48;
  --stamp-blue: #208ED3;
}

/* 2. 🌙 Obsidian Modern Dark Theme */
html.dark, body.dark, html.theme-dark, body.theme-dark, html.theme-midnight, body.theme-midnight {
  --background: #0B0F17; /* Deep Velvet Obsidian */
  --foreground: #F8FAFC; /* Crisp Slate-50 Text */
  --card: #131B2E; /* Sleek Dark Glass Card */
  --card-alt: #1E293B;
  --card-foreground: #F8FAFC;
  --primary: #38BDF8; /* Electric Sky Blue */
  --primary-hover: #0EA5E9;
  --primary-foreground: #0B0F17;
  --primary-glow: rgba(56, 189, 248, 0.4);
  --secondary: #1E293B;
  --secondary-foreground: #F8FAFC;
  --secondary-hover: #334155;
  --muted: #1E293B;
  --muted-foreground: #94A3B8;
  --accent: #1E293B;
  --accent-foreground: #38BDF8;
  --border: #1E293B;
  --border-light: #334155;
  --mask: #0B0F17;
  --fur: #1E293B;
  --cream: #131B2E;
  --shadow-soft: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
  --shadow-card: 0 4px 16px rgba(0, 0, 0, 0.5);
  --stamp-red: #F43F5E;
  --stamp-blue: #38BDF8;
}`;

// Replace lines from /* 1. ☀️ Harmony ... to the end of theme definitions
css = css.replace(
  /\/\* 1\. ☀️ Harmony[\s\S]*?--stamp-red: #F43F5E;[\s\S]*?}/,
  modernThemeTokens
);

// Clean Compose Button in CSS: strictly Light vs Dark
const modernComposeButtonCss = `/* ==========================================================================
   THEME-ADAPTIVE COMPOSE BUTTON (#btn-compose) - 2 CLEAN THEMES
   ========================================================================== */
#btn-compose {
  position: relative !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 10px !important;
  padding: 8px 18px 8px 10px !important;
  border-radius: 9999px !important;
  font-family: inherit !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  letter-spacing: -0.01em !important;
  cursor: pointer !important;
  user-select: none !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

/* 1. Light Theme Compose Button */
html:not(.dark):not(.theme-dark) #btn-compose,
html.theme-light #btn-compose,
body.theme-light #btn-compose {
  background-color: #FFFFFF !important;
  border: 1.5px solid #CBD5E1 !important;
  color: #0F172A !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04) !important;
}
html:not(.dark):not(.theme-dark) #btn-compose:hover,
html.theme-light #btn-compose:hover,
body.theme-light #btn-compose:hover {
  background-color: #F8FAFC !important;
  border-color: #208ED3 !important;
  transform: translateY(-1.5px) !important;
  box-shadow: 0 6px 18px rgba(32, 142, 211, 0.18) !important;
}
html:not(.dark):not(.theme-dark) #btn-compose .compose-pencil-badge,
html.theme-light #btn-compose .compose-pencil-badge {
  background-color: #EBF5FB !important;
  border: 1px solid #BAE6FD !important;
}
html:not(.dark):not(.theme-dark) #btn-compose svg,
html.theme-light #btn-compose svg {
  stroke: #208ED3 !important;
  color: #208ED3 !important;
}
html:not(.dark):not(.theme-dark) #btn-compose .compose-btn-text,
html.theme-light #btn-compose .compose-btn-text {
  color: #0F172A !important;
}

/* 2. Dark Theme Compose Button */
html.dark #btn-compose,
html.theme-dark #btn-compose,
body.theme-dark #btn-compose {
  background-color: #131B2E !important;
  border: 1.5px solid #1E293B !important;
  color: #F8FAFC !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
}
html.dark #btn-compose:hover,
html.theme-dark #btn-compose:hover,
body.theme-dark #btn-compose:hover {
  background-color: #1A2438 !important;
  border-color: #38BDF8 !important;
  transform: translateY(-1.5px) !important;
  box-shadow: 0 6px 18px rgba(56, 189, 248, 0.25) !important;
}
html.dark #btn-compose .compose-pencil-badge,
html.theme-dark #btn-compose .compose-pencil-badge {
  background-color: rgba(56, 189, 248, 0.15) !important;
  border: 1px solid rgba(56, 189, 248, 0.3) !important;
}
html.dark #btn-compose svg,
html.theme-dark #btn-compose svg {
  stroke: #38BDF8 !important;
  color: #38BDF8 !important;
}
html.dark #btn-compose .compose-btn-text,
html.theme-dark #btn-compose .compose-btn-text {
  color: #F8FAFC !important;
}

#btn-compose:active {
  transform: translateY(0px) scale(0.97) !important;
}`;

css = css.replace(
  /\/\* ==========================================================================\s*THEME-ADAPTIVE COMPOSE POSTCARD BUTTON[\s\S]*?#btn-compose:active\s*\{[\s\S]*?\}/,
  modernComposeButtonCss
);

fs.writeFileSync(cssPath, css, 'utf8');
console.log('Cleaned public/css/raccoon-mail.css (2 modern themes only)');


// ==============================================================================
// 2. UPDATE public/inbox.php (Navbar Theme Toggle & 2-Choice Settings Modal)
// ==============================================================================
const inboxPhpPath = path.join(baseDir, 'inbox.php');
let inboxPhp = fs.readFileSync(inboxPhpPath, 'utf8');

// Remove midnight-green compose css from inline style
inboxPhp = inboxPhp.replace(
  /\/\* 3\. Midnight Green Theme[\s\S]*?color: #E6F2F2 !important;\s*\}/,
  ''
);

// Update Settings modal theme choices to strictly 2 cards
const modernInboxSettingsThemeCards = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            <!-- Theme 1: Clean Light -->
            <button
              type="button"
              onclick="app.setTheme('light')"
              id="theme-btn-light"
              class="theme-choice-btn rounded-2xl border-2 p-3.5 text-left transition flex flex-col justify-between group cursor-pointer relative bg-card hover:border-primary shadow-xs"
            >
              <div class="flex items-center gap-2 mb-2">
                <span class="text-lg">☀️</span>
                <span class="font-bold text-xs truncate text-foreground">Clean Modern Light</span>
              </div>
              <div class="flex items-center gap-1.5 my-1.5">
                <span class="h-4 w-4 rounded-full" style="background: #F8FAFC; border: 1.5px solid #CBD5E1;"></span>
                <span class="h-4 w-4 rounded-full" style="background: #FFFFFF; border: 1.5px solid #CBD5E1;"></span>
                <span class="h-4 w-4 rounded-full" style="background: #208ED3;"></span>
                <span class="h-4 w-4 rounded-full" style="background: #0F172A;"></span>
              </div>
              <span class="text-[11px] text-muted-foreground mt-1">Crisp white canvas, high-clarity typography</span>
            </button>

            <!-- Theme 2: Obsidian Dark -->
            <button
              type="button"
              onclick="app.setTheme('dark')"
              id="theme-btn-dark"
              class="theme-choice-btn rounded-2xl border-2 p-3.5 text-left transition flex flex-col justify-between group cursor-pointer relative bg-card hover:border-primary shadow-xs"
            >
              <div class="flex items-center gap-2 mb-2">
                <span class="text-lg">🌙</span>
                <span class="font-bold text-xs truncate text-foreground">Obsidian Modern Dark</span>
              </div>
              <div class="flex items-center gap-1.5 my-1.5">
                <span class="h-4 w-4 rounded-full" style="background: #0B0F17; border: 1.5px solid #334155;"></span>
                <span class="h-4 w-4 rounded-full" style="background: #131B2E; border: 1.5px solid #334155;"></span>
                <span class="h-4 w-4 rounded-full" style="background: #38BDF8;"></span>
                <span class="h-4 w-4 rounded-full" style="background: #F8FAFC;"></span>
              </div>
              <span class="text-[11px] text-muted-foreground mt-1">Velvet obsidian night &amp; vibrant sky blue</span>
            </button>
          </div>`;

inboxPhp = inboxPhp.replace(
  /<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">[\s\S]*?<\/div>\s*<\/div>\s*<!-- PROFILE DETAILS FORM -->/,
  modernInboxSettingsThemeCards + '\n        </div>\n\n        <!-- PROFILE DETAILS FORM -->'
);

// Add 1-Click Sun / Moon Toggle Button to top header in inbox.php
const headerThemeBtn = `
      <!-- 1-Click Sun/Moon Theme Toggle Button -->
      <button
        onclick="app.toggleTheme()"
        id="app-theme-toggle-btn"
        type="button"
        title="Toggle Light / Dark mode"
        class="flex items-center justify-center w-8 h-8 rounded-full border border-border bg-card hover:bg-muted text-foreground transition cursor-pointer active:scale-95 shadow-2xs shrink-0"
      >
        <i data-lucide="sun" id="app-theme-sun" class="h-4 w-4 text-amber-500 hidden"></i>
        <i data-lucide="moon" id="app-theme-moon" class="h-4 w-4 text-foreground"></i>
      </button>
`;

if (!inboxPhp.includes('app-theme-toggle-btn')) {
  inboxPhp = inboxPhp.replace(
    '<!-- World Stories Button -->',
    headerThemeBtn + '\n      <!-- World Stories Button -->'
  );
}

fs.writeFileSync(inboxPhpPath, inboxPhp, 'utf8');
console.log('Cleaned public/inbox.php (2-theme settings & 1-click header toggle)');


// ==============================================================================
// 3. UPDATE public/js/app.js (2-Theme System & Toggle Logic)
// ==============================================================================
const appJsPath = path.join(baseDir, 'js', 'app.js');
let appJs = fs.readFileSync(appJsPath, 'utf8');

// Replace Command Palette items with 2 themes
appJs = appJs.replace(
  /\{ id: 'theme_harmony'[\s\S]*?\{ id: 'theme_midnight'[\s\S]*?\},/,
  `{ id: 'theme_light', group: 'Appearance', title: 'Theme: Clean Modern Light', icon: 'sun', shortcut: 'T 1' },
      { id: 'theme_dark', group: 'Appearance', title: 'Theme: Obsidian Modern Dark', icon: 'moon', shortcut: 'T 2' },`
);

appJs = appJs.replace(
  /else if \(actionId === 'theme_harmony'\)[\s\S]*?else if \(actionId === 'theme_midnight'\) this\.switchTheme\('midnight-green'\);/,
  `else if (actionId === 'theme_light' || actionId === 'theme_harmony') this.setTheme('light');
    else if (actionId === 'theme_dark') this.setTheme('dark');`
);

// Replace Theme definitions & methods
const modernAppJsThemes = `  // ==========================================
  // THEME SYSTEM: STRICTLY 2 MODERN THEMES (LIGHT & DARK)
  // ==========================================
  themeDefinitions: {
    light: {
      name: '☀️ Clean Modern Light',
      vars: {
        '--background': '#F8FAFC',
        '--foreground': '#0F172A',
        '--card': '#FFFFFF',
        '--card-alt': '#F1F5F9',
        '--card-foreground': '#0F172A',
        '--primary': '#208ED3',
        '--primary-hover': '#1A7BB8',
        '--primary-foreground': '#FFFFFF',
        '--primary-glow': 'rgba(32, 142, 211, 0.35)',
        '--secondary': '#F1F5F9',
        '--secondary-foreground': '#0F172A',
        '--muted': '#F1F5F9',
        '--muted-foreground': '#64748B',
        '--accent': '#E0F2FE',
        '--accent-foreground': '#0284C7',
        '--border': '#E2E8F0',
        '--border-light': '#F1F5F9',
        '--mask': '#0F172A',
        '--fur': '#334155',
        '--cream': '#F8FAFC'
      },
      cover: 'linear-gradient(135deg, #208ED3 0%, #1A7BB8 50%, #0F5E97 100%)',
      colorScheme: 'light'
    },
    dark: {
      name: '🌙 Obsidian Modern Dark',
      vars: {
        '--background': '#0B0F17',
        '--foreground': '#F8FAFC',
        '--card': '#131B2E',
        '--card-alt': '#1E293B',
        '--card-foreground': '#F8FAFC',
        '--primary': '#38BDF8',
        '--primary-hover': '#0EA5E9',
        '--primary-foreground': '#0B0F17',
        '--primary-glow': 'rgba(56, 189, 248, 0.45)',
        '--secondary': '#1E293B',
        '--secondary-foreground': '#F8FAFC',
        '--muted': '#1E293B',
        '--muted-foreground': '#94A3B8',
        '--accent': '#1E293B',
        '--accent-foreground': '#38BDF8',
        '--border': '#1E293B',
        '--border-light': '#334155',
        '--mask': '#0B0F17',
        '--fur': '#1E293B',
        '--cream': '#131B2E'
      },
      cover: 'linear-gradient(135deg, #0B0F17 0%, #131B2E 50%, #1E293B 100%)',
      colorScheme: 'dark'
    }
  },

  normalizeTheme(t) {
    if (!t) return 'light';
    t = t.toLowerCase().trim();
    if (t === 'dark' || t === 'midnight' || t === 'midnight-green' || t === 'forest' || t === 'cyber') return 'dark';
    return 'light';
  },

  toggleTheme() {
    const current = this.normalizeTheme(localStorage.getItem('raccon_theme') || this.currentUser?.theme || 'light');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    this.setTheme(newTheme, true);
  },

  setTheme(themeName, persist = true) {
    const canonicalTheme = this.normalizeTheme(themeName);
    const def = this.themeDefinitions[canonicalTheme] || this.themeDefinitions.light;

    // 1. Update document classes
    const allThemeClasses = ['light', 'dark', 'midnight-green', 'raccon', 'harmony', 'midnight', 'forest', 'cyber'];
    allThemeClasses.forEach(t => {
      document.documentElement.classList.remove('theme-' + t);
      if (document.body) document.body.classList.remove('theme-' + t);
    });
    document.documentElement.classList.remove('dark');

    if (canonicalTheme === 'dark') {
      document.documentElement.classList.add('dark', 'theme-dark');
      if (document.body) document.body.classList.add('dark', 'theme-dark');
    } else {
      document.documentElement.classList.add('theme-light');
      if (document.body) document.body.classList.add('theme-light');
    }

    // 2. Set colorScheme for OS native controls
    document.documentElement.style.colorScheme = def.colorScheme;

    // 3. Set custom variables
    for (const [k, v] of Object.entries(def.vars)) {
      document.documentElement.style.setProperty(k, v);
    }

    // 4. Update Sun/Moon icon on toggle button
    const sunIcon = document.getElementById('app-theme-sun');
    const moonIcon = document.getElementById('app-theme-moon');
    if (sunIcon && moonIcon) {
      if (canonicalTheme === 'dark') {
        sunIcon.classList.remove('hidden');
        moonIcon.classList.add('hidden');
      } else {
        sunIcon.classList.add('hidden');
        moonIcon.classList.remove('hidden');
      }
    }

    // 5. Store in localStorage
    localStorage.setItem('raccon_theme', canonicalTheme);

    // 6. Highlight active theme button
    this.highlightActiveTheme(canonicalTheme);

    // 7. Persist to backend if requested
    if (persist) {
      if (this.currentUser) this.currentUser.theme = canonicalTheme;
      fetch('/api/user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: canonicalTheme })
      }).catch(() => {});
      this.showToast(\`Theme: \${def.name}\`);
    }

    if (window.lucide) {
      lucide.createIcons();
    }
  },

  highlightActiveTheme(themeName) {
    const canonical = this.normalizeTheme(themeName);
    const themeButtons = [
      { id: 'theme-btn-light', active: canonical === 'light' },
      { id: 'theme-btn-harmony', active: canonical === 'light' },
      { id: 'theme-btn-dark', active: canonical === 'dark' }
    ];

    themeButtons.forEach(({ id, active }) => {
      const btn = document.getElementById(id);
      if (btn) {
        if (active) {
          btn.classList.add('border-primary', 'ring-2', 'ring-primary/20');
          btn.classList.remove('border-border');
          let check = btn.querySelector('.theme-check-icon');
          if (!check) {
            check = document.createElement('span');
            check.className = 'theme-check-icon absolute top-2 right-2 text-xs font-bold text-primary flex items-center justify-center h-4 w-4 rounded-full bg-primary/10';
            check.innerHTML = '✓';
            btn.appendChild(check);
          }
        } else {
          btn.classList.remove('border-primary', 'ring-2', 'ring-primary/20');
          btn.classList.add('border-border');
          const check = btn.querySelector('.theme-check-icon');
          if (check) check.remove();
        }
      }
    });
  },`;

appJs = appJs.replace(
  /\/\/ ==========================================\s*\/\/ THEME SWITCHER \(HARMONY, DARK, MIDNIGHT GREEN\)[\s\S]*?highlightActiveTheme\(themeName\) \{[\s\S]*?\}\s*\},/,
  modernAppJsThemes
);

fs.writeFileSync(appJsPath, appJs, 'utf8');
console.log('Cleaned public/js/app.js (2 themes only with toggle logic)');


// ==============================================================================
// 4. UPDATE public/index.html (Modern Light / Dark styling & Header Toggle)
// ==============================================================================
const indexHtmlPath = path.join(baseDir, 'index.html');
let indexHtml = fs.readFileSync(indexHtmlPath, 'utf8');

// Early theme initializer script in head
const earlyThemeScript = `
  <!-- Early Theme Initializer (Strictly Light vs Dark) -->
  <script>
    (function() {
      const saved = localStorage.getItem('raccon_theme');
      const isDark = saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches);
      if (isDark) {
        document.documentElement.classList.add('dark', 'theme-dark');
        document.documentElement.classList.remove('theme-light', 'theme-harmony');
      } else {
        document.documentElement.classList.remove('dark', 'theme-dark');
        document.documentElement.classList.add('theme-light');
      }
    })();

    function toggleTheme() {
      const isDark = document.documentElement.classList.contains('dark');
      const newTheme = isDark ? 'light' : 'dark';
      localStorage.setItem('raccon_theme', newTheme);
      if (newTheme === 'dark') {
        document.documentElement.classList.add('dark', 'theme-dark');
        document.documentElement.classList.remove('theme-light', 'theme-harmony');
      } else {
        document.documentElement.classList.remove('dark', 'theme-dark');
        document.documentElement.classList.add('theme-light');
      }
      updateThemeToggleUI();
    }

    function updateThemeToggleUI() {
      const isDark = document.documentElement.classList.contains('dark');
      const sun = document.getElementById('theme-icon-sun');
      const moon = document.getElementById('theme-icon-moon');
      if (sun && moon) {
        if (isDark) {
          sun.classList.remove('hidden');
          moon.classList.add('hidden');
        } else {
          sun.classList.add('hidden');
          moon.classList.remove('hidden');
        }
      }
    }
    window.addEventListener('DOMContentLoaded', updateThemeToggleUI);
  </script>
`;

if (!indexHtml.includes('Early Theme Initializer')) {
  indexHtml = indexHtml.replace('</head>', earlyThemeScript + '\n</head>');
}

// Body modern classes
indexHtml = indexHtml.replace(
  '<body class="bg-[#EFEDE9] text-[#1E293B] antialiased selection:bg-[#EBF5FB] flex flex-col justify-between">',
  '<body class="bg-[#F8FAFC] dark:bg-[#0B0F17] text-slate-900 dark:text-slate-100 antialiased selection:bg-[#208ED3]/20 flex flex-col justify-between transition-colors duration-200">'
);

// Header background in index.html
indexHtml = indexHtml.replace(
  '<header class="bg-white border-b border-[#E2E8F0] sticky top-0 z-40 shadow-xs">',
  '<header class="bg-white/95 dark:bg-[#0F172A]/95 backdrop-blur-md border-b border-slate-200 dark:border-white/10 sticky top-0 z-40 shadow-xs transition-colors duration-200">'
);

// Brand logo text in header
indexHtml = indexHtml.replace(
  '<a href="/" class="flex items-center gap-1.5 sm:gap-2 font-display text-sm sm:text-xl font-bold text-[#1E293B] hover:opacity-95 transition shrink-0">',
  '<a href="/" class="flex items-center gap-1.5 sm:gap-2 font-display text-sm sm:text-xl font-bold text-slate-900 dark:text-white hover:opacity-95 transition shrink-0">'
);

// Add Sun/Moon Toggle button into index.html header
const indexHeaderThemeToggle = `
          <!-- 1-Click Sun / Moon Toggle Button -->
          <button
            type="button"
            onclick="toggleTheme()"
            class="w-8 h-8 rounded-full border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-700 dark:text-slate-200 transition shadow-2xs flex items-center justify-center cursor-pointer shrink-0"
            title="Toggle Light / Dark mode"
          >
            <span id="theme-icon-sun" class="hidden text-sm">☀️</span>
            <span id="theme-icon-moon" class="text-sm">🌙</span>
          </button>
`;

if (!indexHtml.includes('theme-icon-sun')) {
  indexHtml = indexHtml.replace(
    '<a href="#app" class="text-sm font-semibold text-[#333333] hover:text-[#208ED3] transition px-2.5 py-1.5">Apps</a>',
    indexHeaderThemeToggle + '\n          <a href="#app" class="text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-[#208ED3] transition px-2.5 py-1.5">Apps</a>'
  );
}

fs.writeFileSync(indexHtmlPath, indexHtml, 'utf8');
console.log('Cleaned public/index.html (Modern Light/Dark toggle & canvas)');


// ==============================================================================
// 5. UPDATE public/auth.html (Modern Light / Dark styling & Header Toggle)
// ==============================================================================
const authHtmlPath = path.join(baseDir, 'auth.html');
let authHtml = fs.readFileSync(authHtmlPath, 'utf8');

// Modernized 2-theme script in auth.html
const modernAuthThemeScript = `  <!-- Early Theme Initializer (Strictly 2 Themes: Light vs Dark) -->
  <script>
    (function() {
      let theme = localStorage.getItem('raccon_theme') || 'light';
      if (theme !== 'dark') theme = 'light';
      document.documentElement.className = theme === 'dark' ? 'dark theme-dark' : 'theme-light';
      const themeVars = {
        light: { '--background': '#F8FAFC', '--foreground': '#0F172A', '--card': '#FFFFFF', '--primary': '#208ED3', '--muted': '#F1F5F9', '--border': '#E2E8F0' },
        dark: { '--background': '#0B0F17', '--foreground': '#F8FAFC', '--card': '#131B2E', '--primary': '#38BDF8', '--muted': '#1E293B', '--border': '#1E293B' }
      };
      for (const [k, v] of Object.entries(themeVars[theme])) {
        document.documentElement.style.setProperty(k, v);
      }
    })();

    function toggleAuthTheme() {
      const isDark = document.documentElement.classList.contains('dark');
      const newTheme = isDark ? 'light' : 'dark';
      localStorage.setItem('raccon_theme', newTheme);
      document.documentElement.className = newTheme === 'dark' ? 'dark theme-dark' : 'theme-light';
      const themeVars = {
        light: { '--background': '#F8FAFC', '--foreground': '#0F172A', '--card': '#FFFFFF', '--primary': '#208ED3', '--muted': '#F1F5F9', '--border': '#E2E8F0' },
        dark: { '--background': '#0B0F17', '--foreground': '#F8FAFC', '--card': '#131B2E', '--primary': '#38BDF8', '--muted': '#1E293B', '--border': '#1E293B' }
      };
      for (const [k, v] of Object.entries(themeVars[newTheme])) {
        document.documentElement.style.setProperty(k, v);
      }
      updateAuthThemeToggleUI();
    }

    function updateAuthThemeToggleUI() {
      const isDark = document.documentElement.classList.contains('dark');
      const sun = document.getElementById('auth-theme-sun');
      const moon = document.getElementById('auth-theme-moon');
      if (sun && moon) {
        if (isDark) {
          sun.classList.remove('hidden');
          moon.classList.add('hidden');
        } else {
          sun.classList.add('hidden');
          moon.classList.remove('hidden');
        }
      }
    }
    window.addEventListener('DOMContentLoaded', updateAuthThemeToggleUI);
  </script>`;

authHtml = authHtml.replace(
  /<!-- Early Theme Initializer -->[\s\S]*?<\/script>\s*<script src="https:\/\/cdn\.tailwindcss\.com"><\/script>/,
  modernAuthThemeScript + '\n  <script src="https://cdn.tailwindcss.com"></script>'
);

// Add Sun/Moon Toggle into auth header
const authHeaderToggle = `
        <!-- Theme Toggle -->
        <button
          type="button"
          onclick="toggleAuthTheme()"
          class="w-8 h-8 rounded-full border border-[#E2E8F0] dark:border-white/10 bg-white/80 dark:bg-white/5 hover:bg-slate-100 dark:hover:bg-white/10 text-slate-700 dark:text-slate-200 transition shadow-2xs flex items-center justify-center cursor-pointer"
          title="Toggle Light / Dark mode"
        >
          <span id="auth-theme-sun" class="hidden text-sm">☀️</span>
          <span id="auth-theme-moon" class="text-sm">🌙</span>
        </button>
`;

if (!authHtml.includes('auth-theme-sun')) {
  authHtml = authHtml.replace(
    '<a href="/" class="text-xs sm:text-sm font-semibold text-[#4F4F4F]',
    authHeaderToggle + '\n        <a href="/" class="text-xs sm:text-sm font-semibold text-[#4F4F4F]'
  );
}

fs.writeFileSync(authHtmlPath, authHtml, 'utf8');
console.log('Cleaned public/auth.html (Modern Light/Dark toggle)');

console.log('All files modernized to strictly 2 clean themes!');
