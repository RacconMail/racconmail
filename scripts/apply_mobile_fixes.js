const fs = require('fs');
const path = require('path');

const inboxFile = path.join(__dirname, '..', 'public', 'inbox.php');
let content = fs.readFileSync(inboxFile, 'utf8');

console.log('Read inbox.php, length:', content.length);

// 1. Add CSS rules in <style>
const styleTarget = '.pb-safe {\n      padding-bottom: env(safe-area-inset-bottom, 0px);\n    }';
const styleReplacement = `.pb-safe {
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    /* Mobile-First Layout Protection & Strict No-Overlap Constraints */
    @media (max-width: 1279px) {
      #quick-rail {
        display: none !important;
      }
    }
    #mobile-compose-fab.hidden {
      display: none !important;
    }
    #sidebar-panel .sidebar-badge {
      flex-shrink: 0 !important;
      margin-left: auto !important;
    }`;

if (!content.includes('#quick-rail {\n        display: none !important;')) {
  if (content.includes(styleTarget)) {
    content = content.replace(styleTarget, styleReplacement);
    console.log('1. CSS rules added successfully');
  } else {
    console.warn('1. styleTarget not found');
  }
} else {
  console.log('1. CSS rules already present');
}

// 2. Thread conversation container padding
const oldContainer = '<div id="thread-conversation-container" class="flex-1 overflow-y-auto p-6 md:p-8 space-y-6 pb-36">';
const newContainer = '<div id="thread-conversation-container" class="flex-1 overflow-y-auto p-3 sm:p-6 md:p-8 space-y-4 sm:space-y-6 pb-32">';
if (content.includes(oldContainer)) {
  content = content.replace(oldContainer, newContainer);
  console.log('2. Thread container padding updated');
} else {
  console.log('2. Thread container already updated or pattern differs');
}

// 3. toggleReaderFullscreen fix
const oldTog = "quickRail?.classList.remove('hidden');";
const newTog = "if (window.innerWidth >= 1280) { quickRail?.classList.remove('hidden'); }";
if (content.includes(oldTog)) {
  content = content.replace(oldTog, newTog);
  console.log('3. toggleReaderFullscreen quickRail check added');
} else {
  console.log('3. toggleReaderFullscreen already updated');
}

// 4. selectThreadItem hide fab on mobile
const oldSelectMobile = `      if (window.innerWidth < 768) {
        const colInbox = document.getElementById('col-inbox');
        const colReader = document.getElementById('col-reader');
        if (colInbox) colInbox.classList.add('hidden');
        if (colReader) {
          colReader.classList.remove('hidden');
          colReader.classList.add('flex');
        }
      }`;

const newSelectMobile = `      if (window.innerWidth < 768) {
        const colInbox = document.getElementById('col-inbox');
        const colReader = document.getElementById('col-reader');
        if (colInbox) colInbox.classList.add('hidden');
        if (colReader) {
          colReader.classList.remove('hidden');
          colReader.classList.add('flex');
        }
        const fab = document.getElementById('mobile-compose-fab');
        if (fab) {
          fab.classList.add('hidden');
          fab.style.setProperty('display', 'none', 'important');
        }
      }`;

if (content.includes(oldSelectMobile)) {
  content = content.replace(oldSelectMobile, newSelectMobile);
  console.log('4. selectThreadItem FAB hiding added');
} else {
  console.log('4. selectThreadItem already updated or pattern differs');
}

// 5. backToMobileInbox restore fab
const oldBack = `    function backToMobileInbox() {
      const colReader = document.getElementById('col-reader');
      const colInbox = document.getElementById('col-inbox');
      if (colReader) {
        colReader.classList.add('hidden');
        colReader.classList.remove('flex');
      }
      if (colInbox) {
        colInbox.classList.remove('hidden');
      }
      window.scrollTo(0, 0);
    }`;

const newBack = `    function backToMobileInbox() {
      const colReader = document.getElementById('col-reader');
      const colInbox = document.getElementById('col-inbox');
      if (colReader) {
        colReader.classList.add('hidden');
        colReader.classList.remove('flex');
      }
      if (colInbox) {
        colInbox.classList.remove('hidden');
      }
      const fab = document.getElementById('mobile-compose-fab');
      if (fab) {
        fab.classList.remove('hidden');
        fab.style.removeProperty('display');
      }
      window.scrollTo(0, 0);
    }`;

if (content.includes(oldBack)) {
  content = content.replace(oldBack, newBack);
  console.log('5. backToMobileInbox FAB restore added');
} else {
  console.log('5. backToMobileInbox already updated or pattern differs');
}

// 6. switchFolder restore fab
const oldSwitch = `      if (window.innerWidth < 768) {
        document.getElementById('sidebar-panel')?.classList.add('-translate-x-full');
        document.getElementById('mobile-sidebar-backdrop')?.classList.add('hidden');
        document.getElementById('col-inbox')?.classList.remove('hidden');
        document.getElementById('col-reader')?.classList.add('hidden');
      }`;

const newSwitch = `      if (window.innerWidth < 768) {
        document.getElementById('sidebar-panel')?.classList.add('-translate-x-full');
        document.getElementById('mobile-sidebar-backdrop')?.classList.add('hidden');
        document.getElementById('col-inbox')?.classList.remove('hidden');
        document.getElementById('col-reader')?.classList.add('hidden');
        const fab = document.getElementById('mobile-compose-fab');
        if (fab) {
          fab.classList.remove('hidden');
          fab.style.removeProperty('display');
        }
      }`;

if (content.includes(oldSwitch)) {
  content = content.replace(oldSwitch, newSwitch);
  console.log('6. switchFolder FAB restore added');
} else {
  console.log('6. switchFolder already updated or pattern differs');
}

// 7. FAB inline style fix: remove display: flex !important from style, add flex to class
content = content.replace(
  'display: flex !important; align-items: center !important;',
  'align-items: center !important;'
);
content = content.replace(
  'class="md:hidden active:scale-95 transition-transform"',
  'class="flex md:hidden active:scale-95 transition-transform"'
);
console.log('7. FAB inline style updated');

// 8. Update thread cards HTML in renderConversationThread
const oldCardsMap = `      const cardsHtml = threadList.map((card, i) => {
        const rawBody = card.body || '';
        const cleanedBody = cleanMimeEmailContent(rawBody);

        return \`
          <div class="mb-6 rounded-2xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-7 relative select-text w-full">
            <!-- Sender & Recipient Information Row -->
            <div class="flex items-start justify-between gap-3 pb-4 mb-5 border-b border-slate-100 flex-wrap">
              <div class="flex items-center gap-3.5 min-w-0">
                <!-- Circular Avatar -->
                <div class="w-10 h-10 rounded-full \${avatarColor} flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden shadow-2xs border border-slate-200/80">
                  \${card.avatar && card.avatar !== '/raccoon-logo.svg' ? \`<img src="\${escapeHtml(card.avatar)}" class="w-full h-full object-cover">\` : \`<span>\${initial}</span>\`}
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-bold text-sm sm:text-base text-slate-900 leading-tight">\${escapeHtml(card.sender || senderDisplayName)}</h3>
                    <span class="inline-flex items-center text-sky-500" title="Verified"><i data-lucide="badge-check" class="w-4 h-4 text-sky-500"></i></span>
                    <span class="text-xs text-slate-400 font-mono">&lt;\${escapeHtml(senderEmail)}&gt;</span>
                  </div>
                  <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                    <span>To: <strong class="text-slate-700 font-semibold">\${escapeHtml(card.to || recipientText)}</strong></span>
                  </div>
                </div>
              </div>

              <!-- Date & Relative Time -->
              <div class="text-right shrink-0">
                <div class="text-xs font-semibold text-slate-600">\${escapeHtml(dateInfo.full)}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">(\${escapeHtml(dateInfo.relative)})</div>
              </div>
            </div>

            <!-- Full Message Body -->
            <div class="text-[15px] text-slate-800 leading-relaxed space-y-4 whitespace-pre-wrap break-words [word-break:break-word] select-text font-normal pt-1">
              \${cleanedBody}
            </div>
          </div>
        \`;
      }).join('');`;

const newCardsMap = `      const cardsHtml = threadList.map((card, i) => {
        const rawBody = card.body || '';
        const cleanedBody = cleanMimeEmailContent(rawBody);

        const cardSender = card.sender || senderDisplayName;
        const displaySender = (cardSender || 'Raccon Member').trim();
        const displayEmail = (senderEmail || '').trim();
        const isEmailSameAsName = displayEmail.toLowerCase() === displaySender.toLowerCase();

        return \`
          <div class="mb-4 sm:mb-6 rounded-2xl bg-white border border-slate-200/90 shadow-2xs p-4 sm:p-6 md:p-7 relative select-text w-full">
            <!-- Sender & Recipient Information Row -->
            <div class="flex items-start justify-between gap-2.5 pb-3.5 mb-4 border-b border-slate-100 flex-wrap sm:flex-nowrap">
              <div class="flex items-center gap-3 min-w-0 max-w-[calc(100%-110px)] sm:max-w-none">
                <!-- Circular Avatar -->
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full \${avatarColor} flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden shadow-2xs border border-slate-200/80">
                  \${card.avatar && card.avatar !== '/raccoon-logo.svg' ? \`<img src="\${escapeHtml(card.avatar)}" class="w-full h-full object-cover">\` : \`<span>\${initial}</span>\`}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <h3 class="font-bold text-sm sm:text-base text-slate-900 leading-tight truncate max-w-[160px] sm:max-w-xs md:max-w-sm">\${escapeHtml(displaySender)}</h3>
                    <span class="inline-flex items-center text-sky-500 shrink-0" title="Verified"><i data-lucide="badge-check" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-sky-500"></i></span>
                    \${!isEmailSameAsName && displayEmail ? \`<span class="text-[11px] sm:text-xs text-slate-400 font-mono truncate max-w-[140px] sm:max-w-none">&lt;\${escapeHtml(displayEmail)}&gt;</span>\` : ''}
                  </div>
                  <div class="text-[11px] sm:text-xs text-slate-500 mt-0.5 flex items-center gap-1.5 truncate">
                    <span>To: <strong class="text-slate-700 font-semibold truncate">\${escapeHtml(card.to || recipientText)}</strong></span>
                  </div>
                </div>
              </div>

              <!-- Date & Relative Time -->
              <div class="text-right shrink-0 whitespace-nowrap ml-auto self-start pt-0.5">
                <div class="text-xs font-semibold text-slate-600">\${escapeHtml(dateInfo.full)}</div>
                <div class="text-[10px] sm:text-[11px] text-slate-400 mt-0.5">(\${escapeHtml(dateInfo.relative)})</div>
              </div>
            </div>

            <!-- Full Message Body -->
            <div class="text-sm sm:text-[15px] text-slate-800 leading-relaxed space-y-4 whitespace-pre-wrap break-words [word-break:break-word] select-text font-normal pt-1 overflow-hidden">
              \${cleanedBody}
            </div>
          </div>
        \`;
      }).join('');`;

if (content.includes(oldCardsMap)) {
  content = content.replace(oldCardsMap, newCardsMap);
  console.log('8. Card map updated successfully');
} else {
  console.warn('8. oldCardsMap not matched');
}

// 9. Update inline docked reply card top row
const oldReplyTop = `            <!-- Top Row of Reply Card: Reply Dropdown, To: Recipient Pill, Cc, Bcc, Popout -->
            <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100 text-xs">
              <div class="flex items-center gap-2 min-w-0 flex-wrap">
                <div class="flex items-center gap-1 text-slate-500 font-semibold">
                  <i data-lucide="corner-up-left" class="w-3.5 h-3.5 text-slate-600"></i>
                  <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                </div>
                <span class="text-slate-400 font-medium">To:</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 border border-slate-200/80 text-xs font-semibold text-slate-800 shadow-2xs">
                  <div class="w-4 h-4 rounded-full \${avatarColor} flex items-center justify-center text-[9px] font-bold overflow-hidden">
                    \${msg.senderAvatar ? \`<img src="\${escapeHtml(msg.senderAvatar)}" class="w-full h-full object-cover">\` : \`<span>\${initial}</span>\`}
                  </div>
                  <span id="inline-reply-recipient-name" class="truncate max-w-[140px] sm:max-w-xs">\${escapeHtml(senderDisplayName)}</span>
                  <button type="button" onclick="clearInlineReplyRecipient()" class="text-slate-400 hover:text-rose-500 transition cursor-pointer ml-0.5" title="Remove recipient">
                    <i data-lucide="x" class="w-3 h-3"></i>
                  </button>
                </span>
              </div>

              <div class="flex items-center gap-3 shrink-0 text-slate-500 font-semibold text-xs">
                <button type="button" onclick="toggleInlineCc()" class="hover:text-slate-900 transition cursor-pointer">Cc</button>
                <button type="button" onclick="toggleInlineBcc()" class="hover:text-slate-900 transition cursor-pointer">Bcc</button>
                <button type="button" onclick="replyCurrentThread()" class="text-slate-400 hover:text-slate-800 transition cursor-pointer" title="Expand to popup modal">
                  <i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </div>`;

const newReplyTop = `            <!-- Top Row of Reply Card: Reply Dropdown, To: Recipient Pill, Cc, Bcc, Popout -->
            <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100 text-xs flex-wrap">
              <div class="flex items-center gap-1.5 min-w-0 max-w-[calc(100%-85px)] sm:max-w-none flex-wrap">
                <div class="flex items-center gap-1 text-slate-500 font-semibold shrink-0">
                  <i data-lucide="corner-up-left" class="w-3.5 h-3.5 text-slate-600"></i>
                  <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                </div>
                <span class="text-slate-400 font-medium shrink-0">To:</span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200/80 text-xs font-semibold text-slate-800 shadow-2xs min-w-0 max-w-full">
                  <div class="w-4 h-4 rounded-full \${avatarColor} flex items-center justify-center text-[9px] font-bold overflow-hidden shrink-0">
                    \${msg.senderAvatar ? \`<img src="\${escapeHtml(msg.senderAvatar)}" class="w-full h-full object-cover">\` : \`<span>\${initial}</span>\`}
                  </div>
                  <span id="inline-reply-recipient-name" class="truncate max-w-[95px] sm:max-w-xs">\${escapeHtml(senderDisplayName)}</span>
                  <button type="button" onclick="clearInlineReplyRecipient()" class="text-slate-400 hover:text-rose-500 transition cursor-pointer ml-0.5 shrink-0" title="Remove recipient">
                    <i data-lucide="x" class="w-3 h-3"></i>
                  </button>
                </span>
              </div>

              <div class="flex items-center gap-3 shrink-0 ml-auto text-slate-500 font-semibold text-xs">
                <button type="button" onclick="toggleInlineCc()" class="hover:text-slate-900 transition cursor-pointer">Cc</button>
                <button type="button" onclick="toggleInlineBcc()" class="hover:text-slate-900 transition cursor-pointer">Bcc</button>
                <button type="button" onclick="replyCurrentThread()" class="text-slate-400 hover:text-slate-800 transition cursor-pointer" title="Expand to popup modal">
                  <i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </div>`;

if (content.includes(oldReplyTop)) {
  content = content.replace(oldReplyTop, newReplyTop);
  console.log('9. Reply card top row updated successfully');
} else {
  console.warn('9. oldReplyTop not matched');
}

// 10. Update sidebar items to ensure min-w-0 flex-1 truncate and shrink-0 ml-auto on badges
// Let's replace each folder button's inner structure cleanly
const folderBadges = [
  { id: 'inbox', title: 'Inbox' },
  { id: 'unread', title: 'Unread' },
  { id: 'starred', title: 'Starred' },
  { id: 'drafts', title: 'Drafts' },
  { id: 'sent', title: 'Sent' },
  { id: 'archive', title: 'Archive' },
  { id: 'spam', title: 'Spam' },
  { id: 'trash', title: 'Trash' }
];

folderBadges.forEach(item => {
  // ensure badge has shrink-0 ml-auto
  const regex = new RegExp(`id="badge-${item.id}"\\s+class="sidebar-badge([^"]*)"`, 'g');
  content = content.replace(regex, (match, classes) => {
    let cls = classes;
    if (!cls.includes('shrink-0')) cls = ' shrink-0' + cls;
    if (!cls.includes('ml-auto')) cls = ' ml-auto' + cls;
    return `id="badge-${item.id}" class="sidebar-badge${cls}"`;
  });
});
console.log('10. Sidebar badges updated with shrink-0 ml-auto');

fs.writeFileSync(inboxFile, content, 'utf8');
console.log('File written successfully, new length:', content.length);
