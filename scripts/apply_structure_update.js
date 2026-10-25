const fs = require('fs');

const file = 'public/inbox.php';
let content = fs.readFileSync(file, 'utf8');

// 1. Update col-inbox width to balanced 2-column layout matching reference screenshot
const colInboxTarget = `<section id="col-inbox" class="w-full md:w-[500px] lg:w-[580px] xl:w-[680px] border-r border-slate-100 flex flex-col shrink-0 bg-white h-full overflow-hidden select-none">`;
const colInboxReplacement = `<section id="col-inbox" class="w-full md:w-[370px] lg:w-[410px] xl:w-[450px] border-r border-slate-200/80 flex flex-col shrink-0 bg-white h-full overflow-hidden select-none">`;

if (content.includes(colInboxTarget)) {
  content = content.replace(colInboxTarget, colInboxReplacement);
  console.log('Updated col-inbox width successfully');
} else {
  console.log('colInboxTarget not found directly, checking regex...');
  content = content.replace(/<section id="col-inbox" class="[^"]*"/, `<section id="col-inbox" class="w-full md:w-[370px] lg:w-[410px] xl:w-[450px] border-r border-slate-200/80 flex flex-col shrink-0 bg-white h-full overflow-hidden select-none"`);
}

// 2. Replace the old desktop header & Rise table header in col-inbox with the exact blueprint structure
const headerTargetStart = `<!-- Unified Desktop Workspace Header Bar (Matching Rise SaaS cleanly) -->`;
const headerTargetEnd = `<!-- Messages Thread List Container -->`;

const newColInboxHeader = `<!-- Middle Column Desktop Header (Matching 2-Column Email Reference Blueprint) -->
      <div class="hidden md:flex items-center justify-between px-4 h-14 border-b border-slate-200/80 bg-white shrink-0 select-none gap-2">
        <!-- Left: Checkbox dropdown + Filter tabs: All, Read, Unread -->
        <div class="flex items-center gap-2 min-w-0">
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
          <div class="flex items-center gap-1 bg-slate-100/90 p-0.5 rounded-lg border border-slate-200/80 ml-1">
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

        <!-- Right: Refresh, Filter/Sliders, Search -->
        <div class="flex items-center gap-1.5 shrink-0">
          <!-- Refresh Button -->
          <button type="button" onclick="refreshEmailListAction()" id="list-refresh-btn" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="Refresh messages">
            <i data-lucide="rotate-cw" id="list-refresh-icon" class="w-3.5 h-3.5"></i>
          </button>

          <!-- Filter / Sorting Button -->
          <div class="relative">
            <button type="button" onclick="toggleDesktopFilterMenu(event)" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="Filters & Sorting">
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

          <!-- Compact Search Input -->
          <div class="relative w-28 sm:w-36 lg:w-44">
            <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none"></i>
            <input
              type="text"
              id="global-search"
              placeholder="Search..."
              oninput="handleSearch(this.value)"
              class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg pl-7 pr-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-slate-400 transition"
            />
          </div>
        </div>
      </div>

      `;

const startIdx = content.indexOf(headerTargetStart);
const endIdx = content.indexOf(headerTargetEnd);
if (startIdx !== -1 && endIdx !== -1) {
  content = content.slice(0, startIdx) + newColInboxHeader + content.slice(endIdx);
  console.log('Replaced middle column header successfully');
} else {
  console.error('Could not locate headerTargetStart or headerTargetEnd');
}

// 3. Replace col-reader header with pagination & right action tools matching blueprint
const colReaderTarget = `<main id="col-reader" class="hidden md:flex flex-1 flex-col bg-white h-full overflow-hidden relative">
      
      <!-- Right Top Bar: All Emails + Top Actions + [ Compose ] + [ + New Identity ] -->
      <header class="h-16 px-4 sm:px-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white select-none z-20 gap-2">
        <div class="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
          <button onclick="backToMobileInbox()" class="md:hidden p-1.5 rounded-lg border border-slate-200 text-slate-600 shrink-0 hover:bg-slate-50 transition" title="Back to Inbox">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
          </button>
          <h2 id="reader-top-title" class="text-xs sm:text-sm font-bold text-slate-800 tracking-tight truncate max-w-[130px] sm:max-w-xs md:max-w-md">All Emails</h2>
        </div>

        <!-- Action Buttons: Reply, Read/Unread, Star, Trash, Archive -->
        <div class="flex items-center gap-1 shrink-0">
          <div class="flex items-center gap-0.5 sm:gap-1 text-slate-400">
            <button onclick="replyCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Reply">
              <i data-lucide="reply" class="h-4 w-4"></i>
            </button>
            <button id="reader-toggle-read-btn" onclick="toggleCurrentThreadReadState()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Toggle Read/Unread">
              <i data-lucide="mail" id="reader-mail-icon" class="h-4 w-4"></i>
            </button>
            <button onclick="starCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-amber-500 transition cursor-pointer" title="Star message">
              <i data-lucide="star" id="reader-star-icon" class="h-4 w-4"></i>
            </button>
            <button onclick="archiveCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Archive">
              <i data-lucide="archive" class="h-4 w-4"></i>
            </button>
            <button onclick="trashCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-rose-600 text-rose-500 transition cursor-pointer" title="Delete Email">
              <i data-lucide="trash-2" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </header>`;

const newColReader = `<main id="col-reader" class="hidden md:flex flex-1 flex-col bg-white h-full overflow-hidden relative">
      
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
      </header>`;

if (content.includes(colReaderTarget)) {
  content = content.replace(colReaderTarget, newColReader);
  console.log('Replaced col-reader top bar successfully');
} else {
  console.error('colReaderTarget not found directly');
}

fs.writeFileSync(file, content, 'utf8');
console.log('Saved initial HTML replacements in inbox.php');
