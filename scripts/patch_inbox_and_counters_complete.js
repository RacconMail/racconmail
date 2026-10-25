const fs = require('fs');
const path = require('path');

const inboxPath = path.join(__dirname, '../public/inbox.php');
let inbox = fs.readFileSync(inboxPath, 'utf8');

console.log('Original inbox.php length:', inbox.length);

// 1. Sidebar badges replacement
const oldSidebarBadges = `        <!-- 1. INBOX (ACTIVE BY DEFAULT) -->
        <button onclick="switchFolder('Inbox')" id="folder-btn-inbox" class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold nav-pill-active cursor-pointer">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="inbox" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Inbox</span>
          </div>
          <span id="badge-inbox" class="sidebar-badge bg-slate-100 text-slate-700 border border-slate-200/90 text-[10px] font-bold px-2.5 py-0.5 rounded-full"><?php echo $counts['inbox_unread']; ?></span>
        </button>

        <!-- 2. UNREAD -->
        <button onclick="switchFolder('Unread')" id="folder-btn-unread" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="mail" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Unread</span>
          </div>
          <span id="badge-unread" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['unread_total']; ?></span>
        </button>

        <!-- 3. STARRED / FAVORITES -->
        <button onclick="switchFolder('Starred')" id="folder-btn-starred" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="star" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Starred</span>
          </div>
          <span id="badge-starred" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['starred_total']; ?></span>
        </button>

        <!-- 4. DRAFTS -->
        <button onclick="switchFolder('Drafts')" id="folder-btn-drafts" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="file-edit" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Drafts</span>
          </div>
          <span id="badge-drafts" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['drafts_total']; ?></span>
        </button>

        <!-- 5. SENT -->
        <button onclick="switchFolder('Sent')" id="folder-btn-sent" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="send" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Sent</span>
          </div>
          <span id="badge-sent" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['sent_total']; ?></span>
        </button>

        <!-- 6. ARCHIVE -->
        <button onclick="switchFolder('Archive')" id="folder-btn-archive" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="archive" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Archive</span>
          </div>
          <span id="badge-archive" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['archive_total']; ?></span>
        </button>

        <!-- 7. SPAM -->
        <button onclick="switchFolder('Spam')" id="folder-btn-spam" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="shield-alert" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Spam</span>
          </div>
          <span id="badge-spam" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['spam_total']; ?></span>
        </button>

        <!-- 8. TRASH (100% Identical Structure and Column Alignment) -->
        <button onclick="switchFolder('Trash')" id="folder-btn-trash" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold nav-pill-inactive">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="trash-2" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Trash</span>
          </div>
          <span id="badge-trash" class="sidebar-badge text-xs font-mono text-slate-400"><?php echo $counts['trash_total']; ?></span>
        </button>`;

const newSidebarBadges = `        <!-- 1. INBOX (ACTIVE BY DEFAULT) -->
        <button onclick="switchFolder('Inbox')" id="folder-btn-inbox" class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold nav-pill-active cursor-pointer">
          <div class="flex items-center gap-2.5 truncate">
            <i data-lucide="inbox" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate">Inbox</span>
          </div>
          <span id="badge-inbox" class="sidebar-badge bg-slate-100 text-slate-700 border border-slate-200/90 text-[10px] font-bold px-2.5 py-0.5 rounded-full <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_unread']; ?></span>
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
        </button>`;

if (!inbox.includes(oldSidebarBadges)) {
  console.error('ERROR: oldSidebarBadges not found!');
} else {
  inbox = inbox.replace(oldSidebarBadges, newSidebarBadges);
  console.log('SUCCESS: Patched sidebar badges.');
}

// 2. Middle column header replacement
const oldHeader = `          <div class="flex items-center gap-2.5">
            <h1 id="current-folder-display-title" class="text-3xl font-black text-slate-900 tracking-tight">Inbox</h1>
            <span id="coral-inbox-badge" class="bg-[#FFEFEA] text-[#E06748] text-xs font-black px-2.5 py-0.5 rounded-full shadow-2xs"><?php echo $counts['inbox_unread']; ?></span>
            <button id="trash-empty-btn" onclick="emptyTrashAction()" class="hidden px-2.5 py-1 rounded-full bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold border border-rose-200 transition cursor-pointer flex items-center gap-1 shadow-2xs" title="Empty Trash">
              <i data-lucide="eraser" class="h-3 w-3"></i>
              <span>Empty Trash</span>
            </button>
          </div>`;

const newHeader = `          <div class="flex items-center gap-2.5">
            <h1 id="current-folder-display-title" class="text-3xl font-black text-slate-900 tracking-tight">Inbox</h1>
            <span id="coral-inbox-badge" class="bg-[#FFEFEA] text-[#E06748] text-xs font-black px-2.5 py-0.5 rounded-full shadow-2xs <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_unread']; ?></span>
            <button id="mark-all-read-btn" onclick="markAllAsReadAction()" class="px-2.5 py-0.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-bold border border-slate-200 transition cursor-pointer flex items-center gap-1 shadow-2xs <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>" title="Mark all as read">
              <i data-lucide="check-check" class="h-3 w-3"></i>
              <span>Read All</span>
            </button>
            <button id="trash-empty-btn" onclick="emptyTrashAction()" class="hidden px-2.5 py-1 rounded-full bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold border border-rose-200 transition cursor-pointer flex items-center gap-1 shadow-2xs" title="Empty Trash">
              <i data-lucide="eraser" class="h-3 w-3"></i>
              <span>Empty Trash</span>
            </button>
          </div>`;

if (!inbox.includes(oldHeader)) {
  console.error('ERROR: oldHeader not found!');
} else {
  inbox = inbox.replace(oldHeader, newHeader);
  console.log('SUCCESS: Patched middle column header.');
}

// 3. Reader toolbar toggle read button
const oldReaderToolbar = `        <!-- Action Buttons: Reply, Star, Trash, Archive -->
        <div class="flex items-center gap-1 shrink-0">
          <div class="flex items-center gap-0.5 sm:gap-1 text-slate-400">
            <button onclick="replyCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Reply">
              <i data-lucide="reply" class="h-4 w-4"></i>
            </button>
            <button onclick="starCurrentThread()" class="p-2 rounded-xl hover:bg-slate-100 hover:text-amber-500 transition cursor-pointer" title="Star message">
              <i data-lucide="star" id="reader-star-icon" class="h-4 w-4"></i>
            </button>`;

const newReaderToolbar = `        <!-- Action Buttons: Reply, Read/Unread, Star, Trash, Archive -->
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
            </button>`;

if (!inbox.includes(oldReaderToolbar)) {
  console.error('ERROR: oldReaderToolbar not found!');
} else {
  inbox = inbox.replace(oldReaderToolbar, newReaderToolbar);
  console.log('SUCCESS: Patched reader toolbar.');
}

// 4. Mobile bottom bar badge
const oldMobileBottomInbox = `    <!-- Inbox Tray (Active Coral) -->
    <button onclick="switchFolder('Inbox'); backToMobileInbox();" class="flex flex-col items-center gap-1 cursor-pointer group" title="Inbox">
      <div class="w-6 h-6 flex items-center justify-center text-[#F68D72]">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5v-3h3.56c.69 1.19 1.97 2 3.44 2s2.75-.81 3.44-2H19v3zm0-5h-4.99c0 1.1-.9 2-2.01 2s-2.01-.9-2.01-2H5V5h14v9z"/></svg>
      </div>
      <span class="text-[10px] font-bold text-slate-800">Inbox</span>
    </button>`;

const newMobileBottomInbox = `    <!-- Inbox Tray (Active Coral) -->
    <button onclick="switchFolder('Inbox'); backToMobileInbox();" class="flex flex-col items-center gap-1 cursor-pointer group relative" title="Inbox">
      <div class="w-6 h-6 flex items-center justify-center text-[#F68D72] relative">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5v-3h3.56c.69 1.19 1.97 2 3.44 2s2.75-.81 3.44-2H19v3zm0-5h-4.99c0 1.1-.9 2-2.01 2s-2.01-.9-2.01-2H5V5h14v9z"/></svg>
        <span id="mobile-bottom-inbox-badge" class="absolute -top-1 -right-1.5 min-w-[14px] h-3.5 px-1 rounded-full bg-[#E06748] text-white text-[8px] font-black flex items-center justify-center shadow-xs ${"<?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>"}>${"<?php echo $counts['inbox_unread']; ?>" }</span>
      </div>
      <span class="text-[10px] font-bold text-slate-800">Inbox</span>
    </button>`;

if (!inbox.includes(oldMobileBottomInbox)) {
  console.error('ERROR: oldMobileBottomInbox not found!');
} else {
  inbox = inbox.replace(oldMobileBottomInbox, newMobileBottomInbox);
  console.log('SUCCESS: Patched mobile bottom bar inbox badge.');
}

// 5. Update renderMessageList, selectThreadItem, renderConversationThread, refreshFolderCounts, updateBadgeCounts
const oldMessageListSection = `      // Update Coral Badge count (show unread count for Inbox and Unread, total for others)
      const badge = document.getElementById('coral-inbox-badge');
      if (badge) {
        if (currentFolder === 'Inbox' || currentFolder === 'Unread') {
          const unreadCount = (mailDatabase['Inbox'] || []).filter(m => !m.isRead).length;
          badge.innerText = unreadCount;
        } else {
          badge.innerText = list.length;
        }
      }`;

const newMessageListSection = `      // Update Coral Badge count & Mark All Read button
      const unreadCount = (mailDatabase['Inbox'] || []).filter(m => !m.isRead).length;
      const badge = document.getElementById('coral-inbox-badge');
      if (badge) {
        const count = (currentFolder === 'Inbox' || currentFolder === 'Unread') ? unreadCount : list.length;
        badge.innerText = count;
        if (count > 0) badge.classList.remove('hidden');
        else badge.classList.add('hidden');
      }
      const markAllBtn = document.getElementById('mark-all-read-btn');
      if (markAllBtn) {
        if (unreadCount > 0 && (currentFolder === 'Inbox' || currentFolder === 'Unread')) markAllBtn.classList.remove('hidden');
        else markAllBtn.classList.add('hidden');
      }`;

if (!inbox.includes(oldMessageListSection)) {
  console.error('ERROR: oldMessageListSection not found!');
} else {
  inbox = inbox.replace(oldMessageListSection, newMessageListSection);
  console.log('SUCCESS: Patched renderMessageList badge handling.');
}

// 6. Update card mapping in renderMessageList
const oldCardMap = `        return \`
          <div onclick="selectThreadItem(\${idx})" 
               ontouchstart="handleCardTouchStart(event, '\${msg.id}', \${idx})"
               ontouchmove="handleCardTouchMove(event)"
               ontouchend="handleCardTouchEnd(event, \${idx})"
               oncontextmenu="handleCardContextMenu(event, '\${msg.id}', \${idx})"
               id="thread-item-\${idx}" 
               class="\${isActive ? 'msg-card-active' : 'msg-card-inactive'} p-4 sm:p-5 transition-all duration-200 cursor-pointer mb-3.5 relative overflow-hidden group select-none">
            <div class="flex items-start justify-between gap-3.5">
              <!-- Left Column: Content -->
              <div class="flex-1 min-w-0 pr-1">
                <!-- Metadata Row: Date & Reading indicator & Quick Delete -->
                <div class="flex items-center justify-between mb-1.5">
                  <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold font-mono">
                    <span>\${msg.time || 'Today, 08:15 PM'}</span>
                    <span class="text-slate-300">•</span>
                    <span>\${readMinutes} min read</span>
                  </div>
                  <!-- Quick delete button for smartphone & desktop -->
                  <button type="button" 
                          onclick="event.stopPropagation(); triggerCardDelete('\${msg.id}', \${idx});" 
                          class="p-1.5 -mr-1 text-slate-300 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer" 
                          title="\${currentFolder.toLowerCase() === 'trash' ? 'Delete Permanently' : 'Delete / Move to Trash'}">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </div>

                <!-- Bold Title -->
                <h3 class="font-bold text-[15px] sm:text-base text-slate-900 leading-snug tracking-tight mb-1.5 line-clamp-2">
                  \${escapeHtml(msg.subject || '(no subject)')}
                </h3>`;

const newCardMap = `        const cardClass = isActive
          ? 'msg-card-active p-4 sm:p-5 transition-all duration-200 cursor-pointer mb-3.5 relative overflow-hidden group select-none shadow-sm'
          : (isRead
              ? 'msg-card-inactive p-4 sm:p-5 transition-all duration-200 cursor-pointer mb-3.5 relative overflow-hidden group select-none opacity-90'
              : 'msg-card-inactive p-4 sm:p-5 transition-all duration-200 cursor-pointer mb-3.5 relative overflow-hidden group select-none bg-white border-l-[3px] border-l-[#E06748] shadow-2xs');

        return \`
          <div onclick="selectThreadItem(\${idx})" 
               ontouchstart="handleCardTouchStart(event, '\${msg.id}', \${idx})"
               ontouchmove="handleCardTouchMove(event)"
               ontouchend="handleCardTouchEnd(event, \${idx})"
               oncontextmenu="handleCardContextMenu(event, '\${msg.id}', \${idx})"
               id="thread-item-\${idx}" 
               class="\${cardClass}">
            <div class="flex items-start justify-between gap-3.5">
              <!-- Left Column: Content -->
              <div class="flex-1 min-w-0 pr-1">
                <!-- Metadata Row: Date & Reading indicator & Quick Delete -->
                <div class="flex items-center justify-between mb-1.5">
                  <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold font-mono">
                    \${!isRead ? '<span class="unread-dot w-2 h-2 rounded-full bg-[#E06748] inline-block shrink-0 shadow-2xs" title="Unread message"></span>' : ''}
                    <span>\${msg.time || 'Today, 08:15 PM'}</span>
                    <span class="text-slate-300">•</span>
                    <span>\${readMinutes} min read</span>
                  </div>
                  <!-- Quick delete button for smartphone & desktop -->
                  <button type="button" 
                          onclick="event.stopPropagation(); triggerCardDelete('\${msg.id}', \${idx});" 
                          class="p-1.5 -mr-1 text-slate-300 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer" 
                          title="\${currentFolder.toLowerCase() === 'trash' ? 'Delete Permanently' : 'Delete / Move to Trash'}">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </div>

                <!-- Bold Title -->
                <h3 class="\${isRead ? 'font-semibold text-slate-700' : 'font-extrabold text-slate-900'} text-[15px] sm:text-base leading-snug tracking-tight mb-1.5 line-clamp-2">
                  \${escapeHtml(msg.subject || '(no subject)')}
                </h3>`;

if (!inbox.includes(oldCardMap)) {
  console.error('ERROR: oldCardMap not found!');
} else {
  inbox = inbox.replace(oldCardMap, newCardMap);
  console.log('SUCCESS: Patched card map with unread-dot.');
}

// 7. Update renderConversationThread to auto-mark as read & update reader read button
const oldRenderConv = `    // Render Conversation in Right Column
    function renderConversationThread(msg) {
      const container = document.getElementById('thread-conversation-container');
      const titleEl = document.getElementById('reader-top-title');
      if (!container || !msg) return;

      if (titleEl) titleEl.innerText = msg.subject || 'All Emails';

      // Update reader star icon
      const starIcon = document.getElementById('reader-star-icon');
      if (starIcon) {
        starIcon.className = \`h-4 w-4 \${msg.isStarred ? 'text-amber-500 fill-amber-500' : 'text-slate-400'}\`;
      }`;

const newRenderConv = `    // Render Conversation in Right Column
    function renderConversationThread(msg) {
      const container = document.getElementById('thread-conversation-container');
      const titleEl = document.getElementById('reader-top-title');
      if (!container || !msg) return;

      if (titleEl) titleEl.innerText = msg.subject || 'All Emails';

      // Auto-mark email as read immediately upon viewing in reading pane
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
          activeCard.classList.remove('border-l-[3px]', 'border-l-[#E06748]');
        }
        updateBadgeCounts();
        fetch('/api/messages/' + encodeURIComponent(msg.id) + '/read', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ is_read: 1 })
        }).then(() => refreshFolderCounts()).catch(() => {});
      }

      // Update reader star icon
      const starIcon = document.getElementById('reader-star-icon');
      if (starIcon) {
        starIcon.className = \`h-4 w-4 \${msg.isStarred ? 'text-amber-500 fill-amber-500' : 'text-slate-400'}\`;
      }

      // Update reader read icon/button
      updateReaderReadButton(msg.isRead);`;

if (!inbox.includes(oldRenderConv)) {
  console.error('ERROR: oldRenderConv not found!');
} else {
  inbox = inbox.replace(oldRenderConv, newRenderConv);
  console.log('SUCCESS: Patched renderConversationThread auto-read.');
}

// 8. Update refreshFolderCounts and updateBadgeCounts, plus add reader toggle functions
const oldCountsSection = `    // Refresh real counts from SQLite API
    async function refreshFolderCounts() {
      try {
        const res = await fetch('/api/folders');
        if (res.ok) {
          const data = await res.json();
          if (data && data.standard) {
            const std = data.standard;
            const unreadCount = (typeof std.inbox?.unread === 'number') ? std.inbox.unread : (std.inbox?.total || 0);
            const bInbox = document.getElementById('badge-inbox');
            if (bInbox) bInbox.innerText = unreadCount;
            const bCoral = document.getElementById('coral-inbox-badge');
            if (bCoral && currentFolder === 'Inbox') bCoral.innerText = unreadCount;
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
      const inboxList = mailDatabase['Inbox'] || [];
      const unreadInbox = inboxList.filter(m => !m.isRead).length;
      
      const bInbox = document.getElementById('badge-inbox');
      if (bInbox) bInbox.innerText = unreadInbox;
      
      const bUnread = document.getElementById('badge-unread');
      if (bUnread) bUnread.innerText = unreadInbox;

      const bCoral = document.getElementById('coral-inbox-badge');
      if (bCoral) {
        if (currentFolder === 'Inbox' || currentFolder === 'Unread') {
          bCoral.innerText = unreadInbox;
        } else {
          bCoral.innerText = (mailDatabase[currentFolder] || []).length;
        }
      }

      const bTrash = document.getElementById('badge-trash');
      if (bTrash && mailDatabase['Trash']) bTrash.innerText = mailDatabase['Trash'].length;
      
      const bStarred = document.getElementById('badge-starred');
      if (bStarred) {
        const totalStarred = [].concat(...Object.values(mailDatabase)).filter(m => m.isStarred).length;
        bStarred.innerText = totalStarred;
      }
    }`;

const newCountsSection = `    // Safe DOM badge updater (hides when count is 0)
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
            const unreadCount = (typeof std.inbox?.unread === 'number') ? std.inbox.unread : (std.inbox?.total || 0);

            setBadgeValue('badge-inbox', unreadCount);
            if (currentFolder === 'Inbox' || currentFolder === 'Unread') {
              setBadgeValue('coral-inbox-badge', unreadCount);
              const markAllBtn = document.getElementById('mark-all-read-btn');
              if (markAllBtn) {
                if (unreadCount > 0) markAllBtn.classList.remove('hidden');
                else markAllBtn.classList.add('hidden');
              }
            }
            setBadgeValue('badge-unread', std.unread?.total || std.unread?.unread || 0);
            setBadgeValue('badge-starred', std.favorites?.total || 0);
            setBadgeValue('badge-drafts', std.drafts?.total || 0);
            setBadgeValue('badge-sent', std.sent?.total || 0);
            setBadgeValue('badge-archive', std.archive?.total || 0);
            setBadgeValue('badge-spam', std.spam?.total || 0);
            setBadgeValue('badge-trash', std.trash?.total || 0);
            setBadgeValue('mobile-bottom-inbox-badge', unreadCount);
          }
        }
      } catch (e) {}
    }

    function updateBadgeCounts() {
      const inboxList = mailDatabase['Inbox'] || [];
      const unreadInbox = inboxList.filter(m => !m.isRead).length;

      setBadgeValue('badge-inbox', unreadInbox);
      setBadgeValue('badge-unread', unreadInbox);
      setBadgeValue('mobile-bottom-inbox-badge', unreadInbox);

      if (currentFolder === 'Inbox' || currentFolder === 'Unread') {
        setBadgeValue('coral-inbox-badge', unreadInbox);
      } else {
        const totalInFolder = (mailDatabase[currentFolder] || []).length;
        setBadgeValue('coral-inbox-badge', totalInFolder);
      }

      const markAllBtn = document.getElementById('mark-all-read-btn');
      if (markAllBtn) {
        if (unreadInbox > 0 && (currentFolder === 'Inbox' || currentFolder === 'Unread')) {
          markAllBtn.classList.remove('hidden');
        } else {
          markAllBtn.classList.add('hidden');
        }
      }

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
    }`;

if (!inbox.includes(oldCountsSection)) {
  console.error('ERROR: oldCountsSection not found!');
} else {
  inbox = inbox.replace(oldCountsSection, newCountsSection);
  console.log('SUCCESS: Patched counts section with hide/show logic and reader toggle.');
}

fs.writeFileSync(inboxPath, inbox, 'utf8');
console.log('New inbox.php length:', inbox.length);
console.log('All inbox patches applied successfully!');
