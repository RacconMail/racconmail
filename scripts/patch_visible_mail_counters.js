const fs = require('fs');
const path = require('path');

const inboxPath = path.join(__dirname, '../public/inbox.php');
let inbox = fs.readFileSync(inboxPath, 'utf8');

console.log('Original inbox.php length:', inbox.length);

// 1. Sidebar badge-inbox: must show total inbox messages ($counts['inbox_total']) and be VISIBLE!
const oldSidebarInbox = `<span id="badge-inbox" class="sidebar-badge bg-slate-100 text-slate-700 border border-slate-200/90 text-[10px] font-bold px-2.5 py-0.5 rounded-full <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_unread']; ?></span>`;
const newSidebarInbox = `<span id="badge-inbox" class="sidebar-badge bg-slate-100 text-slate-700 border border-slate-200/90 text-[10px] font-bold px-2.5 py-0.5 rounded-full <?php echo ($counts['inbox_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_total']; ?></span>`;

if (inbox.includes(oldSidebarInbox)) {
  inbox = inbox.replace(oldSidebarInbox, newSidebarInbox);
  console.log('SUCCESS: Patched sidebar badge-inbox to show inbox_total.');
} else {
  console.error('ERROR: oldSidebarInbox not found!');
}

// 2. Middle column header: coral-inbox-badge MUST be ALWAYS VISIBLE showing total emails in folder, plus unread pill
const oldHeaderBadges = `<div class="flex items-center gap-2.5">
            <h1 id="current-folder-display-title" class="text-3xl font-black text-slate-900 tracking-tight">Inbox</h1>
            <span id="coral-inbox-badge" class="bg-[#FFEFEA] text-[#E06748] text-xs font-black px-2.5 py-0.5 rounded-full shadow-2xs <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_unread']; ?></span>
            <button id="mark-all-read-btn" onclick="markAllAsReadAction()" class="px-2.5 py-0.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-bold border border-slate-200 transition cursor-pointer flex items-center gap-1 shadow-2xs <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>" title="Mark all as read">
              <i data-lucide="check-check" class="h-3 w-3"></i>
              <span>Read All</span>
            </button>`;

const newHeaderBadges = `<div class="flex items-center gap-2.5">
            <h1 id="current-folder-display-title" class="text-3xl font-black text-slate-900 tracking-tight">Inbox</h1>
            <span id="coral-inbox-badge" class="bg-[#FFEFEA] text-[#E06748] text-xs font-black px-2.5 py-0.5 rounded-full shadow-2xs" title="Total messages"><?php echo $counts['inbox_total']; ?></span>
            <span id="inbox-unread-pill" class="bg-rose-100 text-rose-700 text-[10px] font-extrabold px-2 py-0.5 rounded-full <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>" title="Unread messages"><?php echo $counts['inbox_unread']; ?> unread</span>
            <button id="mark-all-read-btn" onclick="markAllAsReadAction()" class="px-2.5 py-0.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-bold border border-slate-200 transition cursor-pointer flex items-center gap-1 shadow-2xs <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>" title="Mark all as read">
              <i data-lucide="check-check" class="h-3 w-3"></i>
              <span>Read All</span>
            </button>`;

if (inbox.includes(oldHeaderBadges)) {
  inbox = inbox.replace(oldHeaderBadges, newHeaderBadges);
  console.log('SUCCESS: Patched middle column header with persistent coral-inbox-badge and unread pill.');
} else {
  console.error('ERROR: oldHeaderBadges not found!');
}

// 3. Mobile bottom bar: show total inbox count
const oldMobileBadge = `<span id="mobile-bottom-inbox-badge" class="absolute -top-1 -right-1.5 min-w-[14px] h-3.5 px-1 rounded-full bg-[#E06748] text-white text-[8px] font-black flex items-center justify-center shadow-xs <?php echo ($counts['inbox_unread'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_unread']; ?></span>`;
const newMobileBadge = `<span id="mobile-bottom-inbox-badge" class="absolute -top-1 -right-1.5 min-w-[14px] h-3.5 px-1 rounded-full bg-[#E06748] text-white text-[8px] font-black flex items-center justify-center shadow-xs <?php echo ($counts['inbox_total'] > 0) ? '' : 'hidden'; ?>"><?php echo $counts['inbox_total']; ?></span>`;

if (inbox.includes(oldMobileBadge)) {
  inbox = inbox.replace(oldMobileBadge, newMobileBadge);
  console.log('SUCCESS: Patched mobile bottom bar badge to show inbox_total.');
} else {
  console.error('ERROR: oldMobileBadge not found!');
}

// 4. Update renderMessageList badge and pill logic
const oldListBadge = `      // Update Coral Badge count & Mark All Read button
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

const newListBadge = `      // Update Coral Badge count (shows how many emails got in current folder - ALWAYS VISIBLE!)
      const curFolderList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                            (mailDatabase[currentFolder] || list);
      const folderTotal = curFolderList.length;
      const folderUnread = curFolderList.filter(m => !m.isRead).length;

      const badge = document.getElementById('coral-inbox-badge');
      if (badge) {
        badge.innerText = folderTotal;
        badge.classList.remove('hidden'); // ALWAYS VISIBLE! Shows how many emails you got!
      }

      // Middle header Unread Pill (shows when unread emails exist, hides when 0)
      const unreadPill = document.getElementById('inbox-unread-pill');
      if (unreadPill) {
        if (folderUnread > 0) {
          unreadPill.innerText = folderUnread + ' unread';
          unreadPill.classList.remove('hidden');
        } else {
          unreadPill.classList.add('hidden');
        }
      }

      // Read All button (shows when unread emails exist)
      const markAllBtn = document.getElementById('mark-all-read-btn');
      if (markAllBtn) {
        if (folderUnread > 0) markAllBtn.classList.remove('hidden');
        else markAllBtn.classList.add('hidden');
      }`;

if (inbox.includes(oldListBadge)) {
  inbox = inbox.replace(oldListBadge, newListBadge);
  console.log('SUCCESS: Patched renderMessageList with persistent folder total & unread pill.');
} else {
  console.error('ERROR: oldListBadge not found!');
}

// 5. Update refreshFolderCounts and updateBadgeCounts
const oldRefreshCounts = `    // Refresh real counts from SQLite API
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

const newRefreshCounts = `    // Refresh real counts from SQLite API
    async function refreshFolderCounts() {
      try {
        const res = await fetch('/api/folders');
        if (res.ok) {
          const data = await res.json();
          if (data && data.standard) {
            const std = data.standard;
            const inboxTotal = std.inbox?.total || 0;
            const inboxUnread = std.inbox?.unread || 0;

            // 1. Sidebar Inbox badge: shows how many emails you got in Inbox (ALWAYS VISIBLE!)
            const bInbox = document.getElementById('badge-inbox');
            if (bInbox) {
              bInbox.innerText = inboxTotal;
              if (inboxTotal > 0) bInbox.classList.remove('hidden');
              else bInbox.classList.add('hidden');
            }

            // 2. Middle header Coral badge: shows how many emails you got in current folder (ALWAYS VISIBLE!)
            const bCoral = document.getElementById('coral-inbox-badge');
            if (bCoral) {
              let fCount = inboxTotal;
              if (currentFolder === 'Sent') fCount = std.sent?.total || 0;
              else if (currentFolder === 'Trash') fCount = std.trash?.total || 0;
              else if (currentFolder === 'Archive') fCount = std.archive?.total || 0;
              else if (currentFolder === 'Spam') fCount = std.spam?.total || 0;
              else if (currentFolder === 'Starred') fCount = std.favorites?.total || 0;
              else if (currentFolder === 'Drafts') fCount = std.drafts?.total || 0;
              else if (currentFolder === 'Unread') fCount = std.unread?.total || inboxUnread;
              bCoral.innerText = fCount;
              bCoral.classList.remove('hidden'); // ALWAYS VISIBLE! Shows how many emails you got!
            }

            // 3. Middle header Unread pill: shows unread count if any (hides when 0)
            const unreadPill = document.getElementById('inbox-unread-pill');
            if (unreadPill) {
              const curUnread = (currentFolder === 'Inbox' || currentFolder === 'Unread') ? inboxUnread : 0;
              if (curUnread > 0) {
                unreadPill.innerText = curUnread + ' unread';
                unreadPill.classList.remove('hidden');
              } else {
                unreadPill.classList.add('hidden');
              }
            }

            // 4. Mark all read button
            const markAllBtn = document.getElementById('mark-all-read-btn');
            if (markAllBtn) {
              const curUnread = (currentFolder === 'Inbox' || currentFolder === 'Unread') ? inboxUnread : 0;
              if (curUnread > 0) markAllBtn.classList.remove('hidden');
              else markAllBtn.classList.add('hidden');
            }

            // 5. Sidebar Unread: shows unread count (hides when 0)
            setBadgeValue('badge-unread', std.unread?.total || inboxUnread || 0);

            // 6. Mobile bottom bar badge: shows how many emails you got
            const bMobile = document.getElementById('mobile-bottom-inbox-badge');
            if (bMobile) {
              bMobile.innerText = inboxTotal;
              if (inboxTotal > 0) bMobile.classList.remove('hidden');
              else bMobile.classList.add('hidden');
            }

            // 7. Other folders
            setBadgeValue('badge-starred', std.favorites?.total || 0);
            setBadgeValue('badge-drafts', std.drafts?.total || 0);
            setBadgeValue('badge-sent', std.sent?.total || 0);
            setBadgeValue('badge-archive', std.archive?.total || 0);
            setBadgeValue('badge-spam', std.spam?.total || 0);
            setBadgeValue('badge-trash', std.trash?.total || 0);
          }
        }
      } catch (e) {}
    }

    function updateBadgeCounts() {
      const inboxList = mailDatabase['Inbox'] || [];
      const totalInbox = inboxList.length;
      const unreadInbox = inboxList.filter(m => !m.isRead).length;

      // 1. Sidebar Inbox badge: shows how many emails you got in Inbox (ALWAYS VISIBLE!)
      const bInbox = document.getElementById('badge-inbox');
      if (bInbox) {
        bInbox.innerText = totalInbox;
        if (totalInbox > 0) bInbox.classList.remove('hidden');
        else bInbox.classList.add('hidden');
      }

      // 2. Middle header Coral badge: shows how many emails you got in current folder (ALWAYS VISIBLE!)
      const bCoral = document.getElementById('coral-inbox-badge');
      if (bCoral) {
        const curList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                        (mailDatabase[currentFolder] || []);
        bCoral.innerText = curList.length;
        bCoral.classList.remove('hidden'); // ALWAYS VISIBLE! Shows how many emails you got!
      }

      // 3. Middle header Unread pill: shows unread count if any (hides when 0)
      const curList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) :
                      (mailDatabase[currentFolder] || []);
      const curUnread = curList.filter(m => !m.isRead).length;
      const unreadPill = document.getElementById('inbox-unread-pill');
      if (unreadPill) {
        if (curUnread > 0) {
          unreadPill.innerText = curUnread + ' unread';
          unreadPill.classList.remove('hidden');
        } else {
          unreadPill.classList.add('hidden');
        }
      }

      // 4. Mark all read button
      const markAllBtn = document.getElementById('mark-all-read-btn');
      if (markAllBtn) {
        if (curUnread > 0) markAllBtn.classList.remove('hidden');
        else markAllBtn.classList.add('hidden');
      }

      // 5. Sidebar Unread: shows unread count (hides when 0)
      setBadgeValue('badge-unread', unreadInbox);

      // 6. Mobile bottom bar badge: shows how many emails you got
      const bMobile = document.getElementById('mobile-bottom-inbox-badge');
      if (bMobile) {
        bMobile.innerText = totalInbox;
        if (totalInbox > 0) bMobile.classList.remove('hidden');
        else bMobile.classList.add('hidden');
      }

      // 7. Other folders show their counts
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

if (inbox.includes(oldRefreshCounts)) {
  inbox = inbox.replace(oldRefreshCounts, newRefreshCounts);
  console.log('SUCCESS: Patched refreshFolderCounts and updateBadgeCounts with visible mail counts.');
} else {
  console.error('ERROR: oldRefreshCounts not found!');
}

fs.writeFileSync(inboxPath, inbox, 'utf8');
console.log('New inbox.php length:', inbox.length);
console.log('Done applying visible mail counters patch!');
