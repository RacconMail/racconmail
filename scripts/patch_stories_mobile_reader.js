const fs = require('fs');
const path = require('path');

const storiesPath = path.join(__dirname, '..', 'public', 'stories.html');
let html = fs.readFileSync(storiesPath, 'utf8');

// 1. Add Early Native App Initializer in <head>
if (!html.includes('is-native-app')) {
  const nativeInit = `  <!-- Early Theme & Native App Initializer (Strict Clean Modern White) -->
  <script>
    (function() {
      localStorage.setItem('raccon_theme', 'light');
      document.documentElement.className = 'theme-light';
      document.documentElement.classList.remove('dark', 'theme-dark');

      // Native App Mode: Detect Desktop app or Android APK
      if (window.location.search.includes('app=desktop') || 
          window.location.search.includes('app=android') || 
          window.location.search.includes('app=1') || 
          navigator.userAgent.includes('SWVAndroid') || 
          navigator.userAgent.includes('RacconMailDesktop') || 
          navigator.userAgent.includes('Electron')) {
        document.documentElement.classList.add('is-native-app');
      }
    })();
  </script>`;
  html = html.replace('<meta name="theme-color" content="#FFFFFF" />', '<meta name="theme-color" content="#FFFFFF" />\n' + nativeInit);
  console.log('Added native app initializer to stories.html');
}

// 2. Update view-reader top breadcrumbs with prominent "Hide / Show Menu" button
const oldBreadcrumb = `          <!-- Back to Library & Category Breadcrumb -->
          <div class="flex items-center justify-between gap-4">
            <button onclick="setNavView('all')" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 bg-white border border-slate-200 px-3.5 py-2 rounded-xl shadow-2xs hover:bg-slate-50 transition cursor-pointer">
              <i class="fas fa-arrow-left"></i>
              <span>Back to Library</span>
            </button>

            <div class="flex items-center gap-2">
              <span id="reader-status-badge" class="px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800">Completed</span>
              <span id="reader-category-badge" class="tag-badge tag-fantasy">Fantasy</span>
            </div>
          </div>`;

const newBreadcrumb = `          <!-- Back to Library, Menu Toggle & Category Breadcrumb -->
          <div class="flex items-center justify-between gap-2 sm:gap-4 flex-wrap">
            <button onclick="setNavView('all')" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 bg-white border border-slate-200 px-3.5 py-2 rounded-xl shadow-2xs hover:bg-slate-50 transition cursor-pointer">
              <i class="fas fa-arrow-left"></i>
              <span>Back to Library</span>
            </button>

            <!-- Hide / Show Menu Toggle Button (Prominent for Mobile & APK) -->
            <button 
              id="btn-toggle-reader-menu" 
              onclick="toggleReaderMenu()" 
              class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 shadow-2xs transition cursor-pointer"
              title="Toggle reading menu (Text Size & Canvas)"
            >
              <i class="fas fa-sliders text-purple-500" id="toggle-menu-icon"></i>
              <span id="toggle-menu-text">Hide Menu</span>
            </button>

            <div class="flex items-center gap-2">
              <span id="reader-status-badge" class="px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800">Completed</span>
              <span id="reader-category-badge" class="tag-badge tag-fantasy">Fantasy</span>
            </div>
          </div>`;

if (html.includes(oldBreadcrumb)) {
  html = html.replace(oldBreadcrumb, newBreadcrumb);
  console.log('Updated reader top breadcrumbs with menu toggle button!');
} else {
  console.log('oldBreadcrumb not found');
}

// 3. Update Reader Layout Grid with IDs and Mobile Non-Sticky Fix
const oldReaderGrid = `          <!-- Reader Layout: Left Sticky Controls & Right Story Canvas -->
          <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
            
            <!-- Left Sticky Reader Controls -->
            <div class="md:col-span-3 space-y-3 sticky top-24">`;

const newReaderGrid = `          <!-- Reader Layout: Left Controls & Right Story Canvas -->
          <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
            
            <!-- Left Reader Controls Panel (Collapsible & Non-Sticky on Mobile) -->
            <div id="reader-controls-panel" class="md:col-span-3 space-y-3 md:sticky md:top-24 transition-all duration-200">
              <!-- Mobile Header with Hide Button -->
              <div class="flex items-center justify-between pb-2 border-b border-slate-100 md:hidden bg-white p-2.5 rounded-xl border border-slate-200">
                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                  <i class="fas fa-sliders text-purple-500"></i>
                  <span>Reading Options</span>
                </span>
                <button onclick="toggleReaderMenu(false)" class="p-1 px-2.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold flex items-center gap-1">
                  <i class="fas fa-times"></i> Hide
                </button>
              </div>`;

if (html.includes(oldReaderGrid)) {
  html = html.replace(oldReaderGrid, newReaderGrid);
  console.log('Updated reader controls panel with ID and mobile fix!');
} else {
  console.log('oldReaderGrid not found');
}

// 4. Update Right Reading Canvas Column with ID
const oldCanvasCol = `            <!-- Right Reading Canvas & Reviews -->
            <div class="md:col-span-9 space-y-6">`;

const newCanvasCol = `            <!-- Right Reading Canvas & Reviews (Expands to 12 cols when menu hidden) -->
            <div id="reader-canvas-column" class="md:col-span-9 space-y-6 transition-all duration-200">`;

if (html.includes(oldCanvasCol)) {
  html = html.replace(oldCanvasCol, newCanvasCol);
  console.log('Updated reader canvas column with ID!');
} else {
  console.log('oldCanvasCol not found');
}

// 5. Add floating mobile toggle button before </body>
const floatingMobilePill = `
  <!-- Floating Mobile & Android APK Quick Reading Settings Pill -->
  <div id="mobile-reader-toggle-pill" class="hidden md:hidden fixed bottom-6 right-4 z-40 transition">
    <button 
      onclick="toggleReaderMenu()" 
      class="flex items-center gap-2 px-4 py-2.5 rounded-full bg-slate-900/95 hover:bg-black text-white text-xs font-bold shadow-2xl backdrop-blur-md border border-white/20 active:scale-95 transition cursor-pointer"
      title="Toggle reading settings"
    >
      <i class="fas fa-sliders text-purple-400"></i>
      <span id="mobile-toggle-text">Menu</span>
    </button>
  </div>
`;

if (!html.includes('mobile-reader-toggle-pill')) {
  html = html.replace('</body>', floatingMobilePill + '\n</body>');
  console.log('Added floating mobile reader toggle pill!');
}

// 6. Update JavaScript with toggleReaderMenu and Mobile/APK Auto-Hide
const oldJsReader = `    // =========================================================================
    // VIEW 3: DEDICATED STORY READER (#read/:id)
    // =========================================================================
    async function openStoryReader(storyId) {
      currentNavView = 'reader';
      window.location.hash = \`read/\${storyId}\`;

      document.getElementById('view-stories')?.classList.add('hidden');
      document.getElementById('view-wall')?.classList.add('hidden');
      document.getElementById('view-reader')?.classList.remove('hidden');
      document.getElementById('stories-hero')?.classList.add('hidden');`;

const newJsReader = `    // =========================================================================
    // VIEW 3: DEDICATED STORY READER (#read/:id) & MENU HIDE/SHOW LOGIC
    // =========================================================================
    let isReaderMenuVisible = true;

    function toggleReaderMenu(forceState = null) {
      const panel = document.getElementById('reader-controls-panel');
      const canvasCol = document.getElementById('reader-canvas-column');
      const btnText = document.getElementById('toggle-menu-text');
      const btnIcon = document.getElementById('toggle-menu-icon');
      const mobileText = document.getElementById('mobile-toggle-text');

      if (forceState !== null) {
        isReaderMenuVisible = forceState;
      } else {
        isReaderMenuVisible = !isReaderMenuVisible;
      }

      if (isReaderMenuVisible) {
        panel?.classList.remove('hidden');
        canvasCol?.classList.remove('md:col-span-12');
        canvasCol?.classList.add('md:col-span-9');
        if (btnText) btnText.innerText = 'Hide Menu';
        if (btnIcon) btnIcon.className = 'fas fa-eye-slash text-purple-500';
        if (mobileText) mobileText.innerText = 'Hide Menu';
      } else {
        panel?.classList.add('hidden');
        canvasCol?.classList.remove('md:col-span-9');
        canvasCol?.classList.add('md:col-span-12');
        if (btnText) btnText.innerText = 'Show Menu';
        if (btnIcon) btnIcon.className = 'fas fa-sliders text-purple-500';
        if (mobileText) mobileText.innerText = 'Show Menu';
      }

      localStorage.setItem('raccon_reader_menu_hidden', isReaderMenuVisible ? '0' : '1');
    }

    async function openStoryReader(storyId) {
      currentNavView = 'reader';
      window.location.hash = \`read/\${storyId}\`;

      document.getElementById('view-stories')?.classList.add('hidden');
      document.getElementById('view-wall')?.classList.add('hidden');
      document.getElementById('view-reader')?.classList.remove('hidden');
      document.getElementById('stories-hero')?.classList.add('hidden');

      // Detect mobile view or Android APK / Native mode
      const isMobileOrApk = (window.innerWidth < 1024) || 
                            document.documentElement.classList.contains('is-native-app') || 
                            window.location.search.includes('app=android') || 
                            window.location.search.includes('app=1') ||
                            navigator.userAgent.includes('SWVAndroid') ||
                            navigator.userAgent.includes('Android');

      // On mobile view / Android APK: hide the main 22-category sidebar for full focus
      if (isMobileOrApk) {
        document.querySelector('main > div > aside')?.classList.add('hidden');
        document.querySelector('main > div > section')?.classList.remove('lg:col-span-9');
        document.querySelector('main > div > section')?.classList.add('col-span-12');

        // Show floating quick-access button on mobile/APK
        document.getElementById('mobile-reader-toggle-pill')?.classList.remove('hidden');

        // On mobile view and Android APK: auto-hide the menu so it never overlaps the canvas
        toggleReaderMenu(false);
      } else {
        document.getElementById('mobile-reader-toggle-pill')?.classList.add('hidden');
        const pref = localStorage.getItem('raccon_reader_menu_hidden');
        if (pref === '1') {
          toggleReaderMenu(false);
        } else {
          toggleReaderMenu(true);
        }
      }`;

if (html.includes(oldJsReader)) {
  html = html.replace(oldJsReader, newJsReader);
  console.log('Updated openStoryReader with mobile/APK hide menu logic!');
} else {
  console.log('oldJsReader not found');
}

// 7. Update switchToStoriesView and switchToWallView to restore sidebar and hide floating pill
const oldSwitchStories = `    function switchToStoriesView() {
      currentNavView = 'all';
      document.getElementById('view-stories')?.classList.remove('hidden');
      document.getElementById('view-wall')?.classList.add('hidden');
      document.getElementById('view-reader')?.classList.add('hidden');
      document.getElementById('stories-hero')?.classList.remove('hidden');

      document.getElementById('nav-btn-all')?.classList.add('nav-item-active');
      document.getElementById('nav-btn-wall')?.classList.remove('nav-item-active');

      loadStoriesFeed();
    }

    function switchToWallView() {
      currentNavView = 'wall';
      window.location.hash = 'wall';
      document.getElementById('view-stories')?.classList.add('hidden');
      document.getElementById('view-wall')?.classList.remove('hidden');
      document.getElementById('view-reader')?.classList.add('hidden');
      document.getElementById('stories-hero')?.classList.remove('hidden');

      document.getElementById('nav-btn-all')?.classList.remove('nav-item-active');
      document.getElementById('nav-btn-wall')?.classList.add('nav-item-active');

      loadWallFeed();
    }`;

const newSwitchStories = `    function switchToStoriesView() {
      currentNavView = 'all';
      document.getElementById('view-stories')?.classList.remove('hidden');
      document.getElementById('view-wall')?.classList.add('hidden');
      document.getElementById('view-reader')?.classList.add('hidden');
      document.getElementById('stories-hero')?.classList.remove('hidden');

      // Restore main sidebar and column layouts
      document.querySelector('main > div > aside')?.classList.remove('hidden');
      document.querySelector('main > div > section')?.classList.remove('col-span-12');
      document.querySelector('main > div > section')?.classList.add('lg:col-span-9');
      document.getElementById('mobile-reader-toggle-pill')?.classList.add('hidden');

      document.getElementById('nav-btn-all')?.classList.add('nav-item-active');
      document.getElementById('nav-btn-wall')?.classList.remove('nav-item-active');

      loadStoriesFeed();
    }

    function switchToWallView() {
      currentNavView = 'wall';
      window.location.hash = 'wall';
      document.getElementById('view-stories')?.classList.add('hidden');
      document.getElementById('view-wall')?.classList.remove('hidden');
      document.getElementById('view-reader')?.classList.add('hidden');
      document.getElementById('stories-hero')?.classList.remove('hidden');

      // Restore main sidebar and column layouts
      document.querySelector('main > div > aside')?.classList.remove('hidden');
      document.querySelector('main > div > section')?.classList.remove('col-span-12');
      document.querySelector('main > div > section')?.classList.add('lg:col-span-9');
      document.getElementById('mobile-reader-toggle-pill')?.classList.add('hidden');

      document.getElementById('nav-btn-all')?.classList.remove('nav-item-active');
      document.getElementById('nav-btn-wall')?.classList.add('nav-item-active');

      loadWallFeed();
    }`;

if (html.includes(oldSwitchStories)) {
  html = html.replace(oldSwitchStories, newSwitchStories);
  console.log('Updated switchToStoriesView and switchToWallView to restore layouts!');
} else {
  console.log('oldSwitchStories not found');
}

fs.writeFileSync(storiesPath, html, 'utf8');
console.log('Successfully patched public/stories.html for mobile/APK menu hiding!');
