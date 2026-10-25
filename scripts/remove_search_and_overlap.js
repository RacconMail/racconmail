const fs = require('fs');

const file = 'public/inbox.php';
let content = fs.readFileSync(file, 'utf8');

// Target the middle column desktop header
const targetOld = `      <!-- Middle Column Desktop Header (Matching 2-Column Email Reference Blueprint) -->
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
      </div>`;

const targetNew = `      <!-- Middle Column Desktop Header (No Search Bar, Zero Overlap, Clean 2-Column Layout) -->
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
      </div>`;

if (content.includes(targetOld)) {
  content = content.replace(targetOld, targetNew);
  fs.writeFileSync(file, content, 'utf8');
  console.log('Successfully removed search bar and eliminated overlap!');
} else {
  console.error('Target not found in public/inbox.php');
}
