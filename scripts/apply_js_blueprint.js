const fs = require('fs');

const file = 'public/inbox.php';
let content = fs.readFileSync(file, 'utf8');

// Target start: function renderMessageList(searchQuery = '') {
// Target end: function renderEmptyConversation() {
const startToken = `function renderMessageList(searchQuery = '') {`;
const endToken = `function renderEmptyConversation() {`;

const startIdx = content.indexOf(startToken);
const endIdx = content.indexOf(endToken);

if (startIdx === -1 || endIdx === -1) {
  console.error('Could not find start or end token in public/inbox.php');
  process.exit(1);
}

const replacementJs = `
    // =========================================================================
    // 2-COLUMN BLUEPRINT: STATE, DATE GROUPING & FILTERING
    // =========================================================================
    function getEmailDateInfo(dateStr) {
      if (!dateStr) return { group: 'OLDER', display: 'Recent', full: 'Recently', relative: 'Recently' };
      const d = new Date(String(dateStr).replace(' ', 'T'));
      if (isNaN(d.getTime())) {
        return { group: 'THIS MONTH', display: String(dateStr).slice(0, 10), full: String(dateStr), relative: 'Recently' };
      }
      const now = new Date();
      const diffMs = now - d;
      const diffSec = Math.floor(diffMs / 1000);
      const diffMin = Math.floor(diffSec / 60);
      const diffHours = Math.floor(diffMin / 60);
      const diffDays = Math.floor(diffHours / 24);

      let relative = 'Just now';
      if (diffMin >= 1 && diffMin < 60) relative = \`\${diffMin}m ago\`;
      else if (diffHours >= 1 && diffHours < 24) relative = \`\${diffHours}h ago\`;
      else if (diffDays === 1) relative = 'Yesterday';
      else if (diffDays > 1 && diffDays < 30) relative = \`\${diffDays} days ago\`;
      else if (diffDays >= 30) relative = \`\${Math.floor(diffDays / 30)} month\${Math.floor(diffDays / 30) > 1 ? 's' : ''} ago\`;

      const isToday = d.toDateString() === now.toDateString();
      const yesterday = new Date(now);
      yesterday.setDate(now.getDate() - 1);
      const isYesterday = d.toDateString() === yesterday.toDateString();

      let group = 'THIS MONTH';
      if (isToday) group = 'TODAY';
      else if (isYesterday) group = 'YESTERDAY';
      else if (d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()) group = 'THIS MONTH';
      else group = 'OLDER';

      const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const fullMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
      
      let display = \`\${monthNames[d.getMonth()]} \${d.getDate()}\`;
      const hours = d.getHours();
      const minutes = d.getMinutes().toString().padStart(2, '0');
      const ampm = hours >= 12 ? 'PM' : 'AM';
      const formattedHours = hours % 12 || 12;

      if (isToday) {
        display = \`\${formattedHours}:\${minutes} \${ampm}\`;
      }

      const full = \`\${fullMonthNames[d.getMonth()]} \${d.getDate()}, \${d.getFullYear()}, \${formattedHours}:\${minutes} \${ampm}\`;

      return { group, display, full, relative };
    }
    window.getEmailDateInfo = getEmailDateInfo;

    function getCurrentFilteredList(searchQuery = '') {
      let list = [];
      if (currentFolder === 'All') {
        list = [].concat(...Object.values(mailDatabase));
      } else if (currentFolder === 'Unread') {
        list = (mailDatabase['Inbox'] || []).filter(m => !m.isRead);
      } else if (currentFolder === 'Starred') {
        list = [].concat(...Object.values(mailDatabase)).filter(m => m.isStarred);
      } else if (currentFolder === 'Updates') {
        list = (mailDatabase['Updates'] && mailDatabase['Updates'].length > 0) ? mailDatabase['Updates'] : [].concat(...Object.values(mailDatabase)).filter(m => m.is_reply || m.category === 'updates' || (m.subject && m.subject.toLowerCase().startsWith('re:')));
      } else {
        list = mailDatabase[currentFolder] || [];
      }

      if (currentFilter === 'unread') list = list.filter(m => !m.isRead);
      if (currentFilter === 'read') list = list.filter(m => m.isRead);
      if (currentFilter === 'starred') list = list.filter(m => m.isStarred);
      if (currentFilter === 'attachments') list = list.filter(m => m.hasAttachments || (m.attachments && m.attachments.length > 0));

      const q = (searchQuery || document.getElementById('global-search')?.value || '').toLowerCase().trim();
      if (q) {
        list = list.filter(m => 
          (m.senderName || '').toLowerCase().includes(q) ||
          (m.subject || '').toLowerCase().includes(q) ||
          (m.sender || '').toLowerCase().includes(q) ||
          (m.preview || '').toLowerCase().includes(q)
        );
      }
      return list;
    }
    window.getCurrentFilteredList = getCurrentFilteredList;

    function applyEmailFilter(mode) {
      currentFilter = mode; // 'all', 'read', 'unread', 'starred', 'attachments'
      
      // Update top tab pills in middle column
      ['all', 'read', 'unread'].forEach(m => {
        const dBtn = document.getElementById('d-filter-' + m);
        if (dBtn) {
          if (m === mode) {
            dBtn.className = 'px-2.5 py-1 rounded-md text-xs font-bold bg-white text-slate-900 shadow-2xs transition cursor-pointer';
          } else {
            dBtn.className = 'px-2.5 py-1 rounded-md text-xs font-semibold text-slate-600 hover:text-slate-900 transition cursor-pointer';
          }
        }
      });

      const q = document.getElementById('global-search')?.value || '';
      renderMessageList(q);
      closeMobileFilterPopover();

      if (typeof showToast === 'function') {
        const label = mode === 'unread' ? 'Filter: Unread' : (mode === 'read' ? 'Filter: Read' : (mode === 'starred' ? 'Filter: Starred' : 'All messages'));
        showToast(label, 'info');
      }
    }
    window.applyEmailFilter = applyEmailFilter;

    // =========================================================================
    // RENDER MESSAGE LIST (MIDDLE COLUMN)
    // =========================================================================
    function renderMessageList(searchQuery = '') {
      const container = document.getElementById('threads-container');
      if (!container) return;

      const list = getCurrentFilteredList(searchQuery);

      // Update counter badges
      const curFolderList = (currentFolder === 'All') ? [].concat(...Object.values(mailDatabase)) : (mailDatabase[currentFolder] || list);
      const folderTotal = curFolderList.length;
      const folderUnread = curFolderList.filter(m => !m.isRead).length;

      const badge = document.getElementById('coral-inbox-badge');
      if (badge) {
        badge.innerText = folderTotal;
        badge.classList.remove('hidden');
      }

      const unreadPill = document.getElementById('inbox-unread-pill');
      if (unreadPill) {
        if (folderUnread > 0) {
          unreadPill.innerText = folderUnread + ' unread';
          unreadPill.classList.remove('hidden');
        } else {
          unreadPill.classList.add('hidden');
        }
      }

      if (list.length === 0) {
        container.innerHTML = \`
          <div class="p-10 text-center text-slate-400 select-none">
            <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center mx-auto mb-3 text-slate-300 border border-slate-100">
              <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5v-3h3.56c.69 1.19 1.97 2 3.44 2s2.75-.81 3.44-2H19v3zm0-5h-4.99c0 1.1-.9 2-2.01 2s-2.01-.9-2.01-2H5V5h14v9z"/></svg>
            </div>
            <p class="font-bold text-sm text-slate-700">No messages in \${escapeHtml(currentFolder)}</p>
            <p class="text-xs text-slate-400 mt-1">Your inbox is clear and up to date.</p>
          </div>
        \`;
        renderEmptyConversation();
        return;
      }

      if (currentSelectedThreadIndex >= list.length) {
        currentSelectedThreadIndex = 0;
      }

      let currentGroup = null;
      let itemsHtml = '';

      list.forEach((msg, idx) => {
        const isActive = idx === currentSelectedThreadIndex;
        const isRead = !!msg.isRead;
        const rawSnippet = cleanMimeEmailContent(msg.thread && msg.thread.length > 0 ? (msg.thread[msg.thread.length - 1]?.body || '') : (msg.preview || ''));
        const parsedReply = parseEmailReplyAndQuote(rawSnippet);
        const snippetText = (parsedReply.isReply && parsedReply.replyText) ? parsedReply.replyText : (msg.reply_text || rawSnippet);
        const cleanSnippet = snippetText.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').replace(/\\s+/g, ' ').slice(0, 95).trim();
        const senderDisplayName = msg.senderName || (msg.sender ? msg.sender.split('@')[0] : 'Raccon Mail');
        const initial = (senderDisplayName || 'R').trim().charAt(0).toUpperCase();
        const avatarColor = getSenderAvatarColor(senderDisplayName);
        const dateInfo = getEmailDateInfo(msg.date || msg.created_at || msg.time);
        const threadCount = Array.isArray(msg.thread) ? msg.thread.length : 1;

        // Date Group Heading Divider (e.g. THIS MONTH, TODAY, YESTERDAY, OLDER)
        if (dateInfo.group !== currentGroup) {
          currentGroup = dateInfo.group;
          itemsHtml += \`
            <div class="px-4 py-1.5 text-[10px] sm:text-[11px] font-extrabold text-slate-400 tracking-wider uppercase bg-slate-50/70 border-y border-slate-100 select-none my-1">
              \${escapeHtml(currentGroup)}
            </div>
          \`;
        }

        itemsHtml += \`
          <div onclick="selectThreadItem(\${idx})"
               ontouchstart="handleCardTouchStart(event, '\${msg.id}', \${idx})"
               ontouchmove="handleCardTouchMove(event)"
               ontouchend="handleCardTouchEnd(event, \${idx})"
               oncontextmenu="handleCardContextMenu(event, '\${msg.id}', \${idx})"
               id="thread-item-\${idx}"
               class="group cursor-pointer select-none transition border-b border-slate-100/90 relative \${isActive ? 'bg-slate-50/90 border-r-2 border-r-slate-900 shadow-2xs font-semibold' : 'bg-white hover:bg-slate-50/60 border-r-2 border-r-transparent'}">
            
            <div class="flex items-start gap-3 p-3.5">
              <!-- Selection Checkbox -->
              <input type="checkbox"
                     data-msg-id="\${msg.id}"
                     onclick="event.stopPropagation(); handleEmailItemCheck(event, '\${msg.id}')"
                     class="email-select-checkbox rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-4 h-4 mt-0.5 shrink-0" />

              <!-- Circular Avatar -->
              <div class="w-9 h-9 rounded-full \${avatarColor} flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden mt-0.5 shadow-2xs">
                \${msg.senderAvatar ? \`<img src="\${escapeHtml(msg.senderAvatar)}" class="w-full h-full object-cover" onerror="this.remove()">\` : ''}
                <span>\${initial}</span>
              </div>

              <!-- Main Text Block -->
              <div class="flex-1 min-w-0 pr-0.5">
                <!-- Line 1: Sender Name (+ thread count if > 1) + Date on right -->
                <div class="flex items-center justify-between gap-2 mb-0.5">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <span class="text-xs sm:text-[13px] \${isRead ? 'font-semibold text-slate-700' : 'font-extrabold text-slate-900'} truncate">
                      \${escapeHtml(senderDisplayName)}
                    </span>
                    \${threadCount > 1 ? \`<span class="px-1.5 py-0.2 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold shrink-0">\${threadCount}</span>\` : ''}
                  </div>
                  <span class="text-[11px] text-slate-400 font-medium shrink-0 ml-auto whitespace-nowrap font-mono">
                    \${escapeHtml(dateInfo.display)}
                  </span>
                </div>

                <!-- Line 2: Red Unread Dot (if unread) + Bold Subject -->
                <div class="flex items-center gap-1.5 mb-0.5">
                  \${!isRead ? \`<span class="w-2 h-2 rounded-full bg-rose-500 shrink-0 inline-block unread-dot mr-0.5" title="Unread"></span>\` : ''}
                  <h4 class="text-xs sm:text-[13px] \${isRead ? 'font-medium text-slate-700' : 'font-bold text-slate-900'} truncate leading-tight">
                    \${escapeHtml(msg.subject || '(no subject)')}
                  </h4>
                </div>

                <!-- Line 3: Snippet Preview (1 line truncated) -->
                <p class="text-xs text-slate-400 truncate leading-snug line-clamp-1">
                  \${escapeHtml(cleanSnippet || 'No additional text')}
                </p>
              </div>
            </div>
          </div>
        \`;
      });

      container.innerHTML = itemsHtml;
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
      renderConversationThread(list[currentSelectedThreadIndex] || list[0]);
    }
    window.renderMessageList = renderMessageList;

    function selectThreadItem(index) {
      if (cardLongPressFired) return;
      const list = getCurrentFilteredList();
      if (!list.length) return;
      if (index < 0) index = 0;
      if (index >= list.length) index = list.length - 1;

      const prevActive = document.getElementById('thread-item-' + currentSelectedThreadIndex);
      if (prevActive) {
        prevActive.classList.remove('bg-slate-50/90', 'border-r-slate-900', 'shadow-2xs', 'font-semibold');
        prevActive.classList.add('bg-white', 'border-r-transparent');
      }

      currentSelectedThreadIndex = index;
      const nextActive = document.getElementById('thread-item-' + index);
      if (nextActive) {
        nextActive.classList.remove('bg-white', 'border-r-transparent');
        nextActive.classList.add('bg-slate-50/90', 'border-r-slate-900', 'shadow-2xs', 'font-semibold');
      }

      const selected = list[index];
      if (selected) {
        if (!selected.isRead) {
          selected.isRead = true;
          Object.values(mailDatabase).forEach(arr => {
            if (Array.isArray(arr)) {
              const found = arr.find(m => m.id === selected.id);
              if (found) found.isRead = true;
            }
          });
          if (nextActive) {
            const dot = nextActive.querySelector('.unread-dot');
            if (dot) dot.remove();
          }
          updateBadgeCounts();
          fetch('/api/messages/' + encodeURIComponent(selected.id) + '/read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_read: 1 })
          }).then(() => refreshFolderCounts()).catch(() => {});
        }
        renderConversationThread(selected);
      }

      if (window.innerWidth < 768) {
        const colInbox = document.getElementById('col-inbox');
        const colReader = document.getElementById('col-reader');
        if (colInbox) colInbox.classList.add('hidden');
        if (colReader) {
          colReader.classList.remove('hidden');
          colReader.classList.add('flex');
        }
      }
    }
    window.selectThreadItem = selectThreadItem;

    // =========================================================================
    // RENDER READING PANE & INLINE QUICK REPLY COMPOSER (RIGHT COLUMN)
    // =========================================================================
    function renderConversationThread(msg) {
      const container = document.getElementById('thread-conversation-container');
      if (!container || !msg) return;

      const list = getCurrentFilteredList();
      const counterEl = document.getElementById('reader-counter');
      if (counterEl) {
        counterEl.innerText = \`\${currentSelectedThreadIndex + 1} of \${list.length}\`;
      }

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
        }
        updateBadgeCounts();
        fetch('/api/messages/' + encodeURIComponent(msg.id) + '/read', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ is_read: 1 })
        }).then(() => refreshFolderCounts()).catch(() => {});
      }

      const starIcon = document.getElementById('reader-star-icon');
      if (starIcon) {
        starIcon.className = \`w-4 h-4 \${msg.isStarred ? 'text-amber-500 fill-amber-500' : 'text-slate-400'}\`;
      }

      const senderDisplayName = msg.senderName || (msg.sender ? msg.sender.split('@')[0] : 'Raccon Member');
      const senderEmail = msg.sender || (msg.senderEmail || 'user@racconmail.eu');
      const recipientText = msg.to || (msg.recipient || 'support@racconmail.eu');
      const dateInfo = getEmailDateInfo(msg.date || msg.created_at || msg.time);
      const initial = (senderDisplayName || 'R').trim().charAt(0).toUpperCase();
      const avatarColor = getSenderAvatarColor(senderDisplayName);

      const threadList = (Array.isArray(msg.thread) && msg.thread.length > 0) ? msg.thread : [
        {
          sender: msg.sender || senderDisplayName,
          to: recipientText,
          date: dateInfo.full,
          body: msg.body || msg.body_html || msg.preview || '(No content)',
          avatar: msg.senderAvatar || '/raccoon-logo.svg'
        }
      ];

      const cardsHtml = threadList.map((card, i) => {
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
      }).join('');

      container.innerHTML = \`
        <div class="w-full max-w-4xl mx-auto px-1 sm:px-4 py-2">
          <!-- Prominent Subject Header (Matching Blueprint) -->
          <div class="mb-5 pb-2">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-snug break-words">
              \${escapeHtml(msg.subject || '(no subject)')}
            </h1>
          </div>

          <!-- Thread Message Cards -->
          \${cardsHtml}

          <!-- INLINE DOCKED QUICK REPLY CARD (Docked below message canvas matching Blueprint) -->
          <div class="rounded-2xl border border-slate-200/90 bg-white shadow-xs p-4 sm:p-5 mt-6 mb-8 transition focus-within:border-slate-300 focus-within:shadow-sm">
            <!-- Top Row of Reply Card: Reply Dropdown, To: Recipient Pill, Cc, Bcc, Popout -->
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
            </div>

            <!-- Collapsible CC row -->
            <div id="inline-cc-row" class="hidden flex items-center gap-2 py-2 border-b border-slate-100 text-xs">
              <span class="text-slate-400 font-medium w-8">Cc:</span>
              <input id="inline-cc-input" type="text" placeholder="Add Cc email addresses..." class="flex-1 bg-transparent text-xs text-slate-800 outline-none" />
            </div>

            <!-- Collapsible BCC row -->
            <div id="inline-bcc-row" class="hidden flex items-center gap-2 py-2 border-b border-slate-100 text-xs">
              <span class="text-slate-400 font-medium w-8">Bcc:</span>
              <input id="inline-bcc-input" type="text" placeholder="Add Bcc email addresses..." class="flex-1 bg-transparent text-xs text-slate-800 outline-none" />
            </div>

            <!-- Inline Textarea Editor -->
            <div class="py-3">
              <textarea id="quick-reply-input"
                        rows="4"
                        placeholder="Write your reply..."
                        onkeydown="handleQuickReplyKey(event)"
                        class="w-full bg-transparent text-sm text-slate-800 placeholder-slate-400 outline-none resize-none leading-relaxed min-h-[90px]"></textarea>
            </div>

            <!-- Attached Files container -->
            <div id="inline-attached-files-container" class="flex flex-wrap gap-2 pb-2 empty:hidden"></div>

            <!-- Bottom Actions Toolbar (Matching Blueprint) -->
            <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 flex-wrap">
              <!-- Left Formatting & Attachment Tools -->
              <div class="flex items-center gap-0.5 sm:gap-1 text-slate-500">
                <!-- AI Assist Pill -->
                <button type="button" onclick="triggerInlineAiAssist()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer shadow-2xs mr-1" title="AI Reply Assistant">
                  <i data-lucide="sparkles" class="w-3.5 h-3.5 text-purple-600"></i>
                  <span>AI</span>
                </button>
                <!-- Formatting TT -->
                <button type="button" onclick="toggleInlineFormattingBar()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Formatting options">
                  <i data-lucide="type" class="w-4 h-4"></i>
                </button>
                <!-- Attach File -->
                <button type="button" onclick="document.getElementById('inline-file-picker').click()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Attach files">
                  <i data-lucide="paperclip" class="w-4 h-4"></i>
                </button>
                <input type="file" id="inline-file-picker" class="hidden" multiple onchange="handleInlineFileAttach(this)" />
                <!-- Insert Link -->
                <button type="button" onclick="insertInlineLink()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert link">
                  <i data-lucide="link" class="w-4 h-4"></i>
                </button>
                <!-- Emoji -->
                <button type="button" onclick="insertInlineEmoji()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert emoji">
                  <i data-lucide="smile" class="w-4 h-4"></i>
                </button>
                <!-- Image -->
                <button type="button" onclick="document.getElementById('inline-image-picker').click()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert image">
                  <i data-lucide="image" class="w-4 h-4"></i>
                </button>
                <input type="file" id="inline-image-picker" class="hidden" accept="image/*" onchange="handleInlineImageAttach(this)" />
                <!-- Pen / Signature -->
                <button type="button" onclick="insertInlineSignature()" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Insert signature">
                  <i data-lucide="pen-tool" class="w-4 h-4"></i>
                </button>
                <!-- Lock / Confidential -->
                <button type="button" onclick="toggleInlineConfidential()" id="inline-confidential-btn" class="p-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer" title="Confidential mode">
                  <i data-lucide="lock" class="w-4 h-4"></i>
                </button>
              </div>

              <!-- Right Action Tools: Discard, More, Send Split Button -->
              <div class="flex items-center gap-1.5 shrink-0">
                <!-- Discard -->
                <button type="button" onclick="clearInlineReply()" class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition cursor-pointer" title="Discard reply">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
                <!-- More -->
                <button type="button" onclick="toggleInlineMoreOptions()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition cursor-pointer" title="More options">
                  <i data-lucide="more-vertical" class="w-4 h-4"></i>
                </button>
                <!-- Send Split Button -->
                <div class="inline-flex rounded-xl shadow-xs overflow-hidden">
                  <button type="button" onclick="sendQuickReply()" id="inline-send-btn" class="px-4 py-2 bg-slate-900 hover:bg-black text-white text-xs font-bold transition cursor-pointer flex items-center gap-1.5 active:scale-95">
                    <span>Send</span>
                  </button>
                  <button type="button" onclick="toggleScheduleSendDropdown()" class="px-2 py-2 bg-slate-900 hover:bg-black text-white text-xs font-bold border-l border-slate-700 transition cursor-pointer" title="Schedule send">
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      \`;

      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.renderConversationThread = renderConversationThread;

    // =========================================================================
    // TOP NAVIGATION & BULK ACTIONS
    // =========================================================================
    function navigateThread(delta) {
      const q = document.getElementById('global-search')?.value || '';
      const list = getCurrentFilteredList(q);
      if (!list.length) return;
      let nextIdx = currentSelectedThreadIndex + delta;
      if (nextIdx < 0) nextIdx = 0;
      if (nextIdx >= list.length) nextIdx = list.length - 1;
      selectThreadItem(nextIdx);
    }
    window.navigateThread = navigateThread;

    let isReaderFullscreen = false;
    function toggleReaderFullscreen() {
      const colInbox = document.getElementById('col-inbox');
      const sidebar = document.getElementById('sidebar-panel');
      const quickRail = document.getElementById('quick-rail');
      const icon = document.getElementById('reader-fullscreen-icon');
      isReaderFullscreen = !isReaderFullscreen;

      if (isReaderFullscreen) {
        colInbox?.classList.add('hidden');
        sidebar?.classList.add('hidden');
        quickRail?.classList.add('hidden');
        if (icon) icon.setAttribute('data-lucide', 'minimize-2');
      } else {
        colInbox?.classList.remove('hidden');
        sidebar?.classList.remove('hidden');
        quickRail?.classList.remove('hidden');
        if (icon) icon.setAttribute('data-lucide', 'maximize-2');
      }
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.toggleReaderFullscreen = toggleReaderFullscreen;

    function toggleMasterSelect(checkbox) {
      const isChecked = checkbox.checked;
      const boxes = document.querySelectorAll('.email-select-checkbox');
      boxes.forEach(b => b.checked = isChecked);
    }
    window.toggleMasterSelect = toggleMasterSelect;

    function selectFilteredBatch(type) {
      const dropdown = document.getElementById('select-dropdown-menu');
      if (dropdown) dropdown.classList.add('hidden');
      const master = document.getElementById('master-select-checkbox');

      const list = getCurrentFilteredList();
      const checkboxes = document.querySelectorAll('.email-select-checkbox');

      checkboxes.forEach((cb, idx) => {
        const msg = list[idx];
        if (!msg) return;
        if (type === 'all') cb.checked = true;
        else if (type === 'none') cb.checked = false;
        else if (type === 'read') cb.checked = !!msg.isRead;
        else if (type === 'unread') cb.checked = !msg.isRead;
        else if (type === 'starred') cb.checked = !!msg.isStarred;
      });

      if (master) {
        master.checked = type === 'all';
      }
    }
    window.selectFilteredBatch = selectFilteredBatch;

    function toggleSelectDropdown(event) {
      event?.stopPropagation();
      const dropdown = document.getElementById('select-dropdown-menu');
      if (dropdown) dropdown.classList.toggle('hidden');
    }
    window.toggleSelectDropdown = toggleSelectDropdown;

    function toggleDesktopFilterMenu(event) {
      event?.stopPropagation();
      const menu = document.getElementById('desktop-filter-menu');
      if (menu) menu.classList.toggle('hidden');
    }
    window.toggleDesktopFilterMenu = toggleDesktopFilterMenu;

    function handleEmailItemCheck(event, id) {
      // Checked state maintained
    }
    window.handleEmailItemCheck = handleEmailItemCheck;

    async function refreshEmailListAction() {
      const icon = document.getElementById('list-refresh-icon');
      if (icon) icon.classList.add('animate-spin');
      try {
        await fetchFolderMessages(currentFolder);
        await refreshFolderCounts();
        if (typeof showToast === 'function') showToast('Inbox updated', 'success');
      } catch (_) {
      } finally {
        setTimeout(() => {
          if (icon) icon.classList.remove('animate-spin');
        }, 600);
      }
    }
    window.refreshEmailListAction = refreshEmailListAction;

    // =========================================================================
    // INLINE DOCKED REPLY CARD ACTIONS
    // =========================================================================
    function triggerInlineAiAssist() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const list = getCurrentFilteredList();
      const msg = list[currentSelectedThreadIndex];
      const senderName = msg?.senderName || 'there';
      const promptReplies = [
        \`Hi \${senderName},\\n\\nThank you for reaching out. I've received your message and will review this shortly.\\n\\nBest regards,\`,
        \`Hi \${senderName},\\n\\nThanks for the update! Everything looks great on my end.\\n\\nBest regards,\`,
        \`Hi \${senderName},\\n\\nCould you please provide a few more details regarding this? Thanks in advance!\\n\\nBest regards,\`
      ];
      const chosen = promptReplies[Math.floor(Math.random() * promptReplies.length)];
      inp.value = chosen;
      inp.focus();
      if (typeof showToast === 'function') showToast('AI response suggestion inserted', 'info');
    }
    window.triggerInlineAiAssist = triggerInlineAiAssist;

    function toggleInlineCc() {
      const row = document.getElementById('inline-cc-row');
      if (row) {
        row.classList.toggle('hidden');
        if (!row.classList.contains('hidden')) document.getElementById('inline-cc-input')?.focus();
      }
    }
    window.toggleInlineCc = toggleInlineCc;

    function toggleInlineBcc() {
      const row = document.getElementById('inline-bcc-row');
      if (row) {
        row.classList.toggle('hidden');
        if (!row.classList.contains('hidden')) document.getElementById('inline-bcc-input')?.focus();
      }
    }
    window.toggleInlineBcc = toggleInlineBcc;

    function clearInlineReply() {
      const inp = document.getElementById('quick-reply-input');
      if (inp) inp.value = '';
      const attachCont = document.getElementById('inline-attached-files-container');
      if (attachCont) attachCont.innerHTML = '';
      document.getElementById('inline-cc-row')?.classList.add('hidden');
      document.getElementById('inline-bcc-row')?.classList.add('hidden');
      if (typeof showToast === 'function') showToast('Reply discarded', 'info');
    }
    window.clearInlineReply = clearInlineReply;

    function clearInlineReplyRecipient() {
      const nameEl = document.getElementById('inline-reply-recipient-name');
      const newRecip = prompt('Enter reply recipient email:', '');
      if (newRecip && newRecip.includes('@')) {
        if (nameEl) nameEl.innerText = newRecip;
      }
    }
    window.clearInlineReplyRecipient = clearInlineReplyRecipient;

    function insertInlineSignature() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const userSig = \`\\n\\n--\\n<?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?>\\n<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>\`;
      inp.value = (inp.value.trim() + userSig).trim();
      inp.focus();
    }
    window.insertInlineSignature = insertInlineSignature;

    function insertInlineEmoji() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const emojis = ['👍', '😊', '🙏', '🚀', '✅', '👋', '🎉'];
      const em = emojis[Math.floor(Math.random() * emojis.length)];
      inp.value += ' ' + em;
      inp.focus();
    }
    window.insertInlineEmoji = insertInlineEmoji;

    function insertInlineLink() {
      const inp = document.getElementById('quick-reply-input');
      if (!inp) return;
      const url = prompt('Enter URL (e.g. https://example.com):', 'https://');
      if (url) {
        inp.value += ' ' + url;
        inp.focus();
      }
    }
    window.insertInlineLink = insertInlineLink;

    let isInlineConfidential = false;
    function toggleInlineConfidential() {
      isInlineConfidential = !isInlineConfidential;
      const btn = document.getElementById('inline-confidential-btn');
      if (btn) {
        if (isInlineConfidential) {
          btn.classList.add('bg-amber-100', 'text-amber-700');
          if (typeof showToast === 'function') showToast('Confidential mode enabled', 'info');
        } else {
          btn.classList.remove('bg-amber-100', 'text-amber-700');
          if (typeof showToast === 'function') showToast('Confidential mode disabled', 'info');
        }
      }
    }
    window.toggleInlineConfidential = toggleInlineConfidential;

    function handleInlineFileAttach(input) {
      const container = document.getElementById('inline-attached-files-container');
      if (!container || !input.files.length) return;
      Array.from(input.files).forEach(file => {
        const pill = document.createElement('div');
        pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs text-slate-700 font-medium';
        pill.innerHTML = \`
          <i data-lucide="paperclip" class="w-3 h-3 text-slate-500"></i>
          <span class="truncate max-w-[150px]">\${escapeHtml(file.name)}</span>
          <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500 cursor-pointer ml-1">✕</button>
        \`;
        container.appendChild(pill);
      });
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.handleInlineFileAttach = handleInlineFileAttach;

    function handleInlineImageAttach(input) {
      const container = document.getElementById('inline-attached-files-container');
      if (!container || !input.files.length) return;
      Array.from(input.files).forEach(file => {
        const pill = document.createElement('div');
        pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs text-slate-700 font-medium';
        pill.innerHTML = \`
          <i data-lucide="image" class="w-3 h-3 text-slate-500"></i>
          <span class="truncate max-w-[150px]">\${escapeHtml(file.name)}</span>
          <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500 cursor-pointer ml-1">✕</button>
        \`;
        container.appendChild(pill);
      });
      try { if (window.lucide && lucide.createIcons) lucide.createIcons(); } catch (_) {}
    }
    window.handleInlineImageAttach = handleInlineImageAttach;

    function toggleScheduleSendDropdown() {
      const scheduleTime = prompt('Schedule Send: enter time (e.g. "Tomorrow 8:00 AM" or "Monday 9:00 AM"):', 'Tomorrow 8:00 AM');
      if (scheduleTime) {
        if (typeof showToast === 'function') showToast(\`Email scheduled for \${scheduleTime}\`, 'success');
        sendQuickReply();
      }
    }
    window.toggleScheduleSendDropdown = toggleScheduleSendDropdown;

    function handleQuickReplyKey(event) {
      if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        sendQuickReply();
      }
    }
    window.handleQuickReplyKey = handleQuickReplyKey;

    function toggleInlineFormattingBar() {
      if (typeof showToast === 'function') showToast('Markdown & rich formatting enabled', 'info');
    }
    window.toggleInlineFormattingBar = toggleInlineFormattingBar;

    function toggleInlineMoreOptions() {
      if (typeof showToast === 'function') showToast('Plain text, request read receipt, set priority', 'info');
    }
    window.toggleInlineMoreOptions = toggleInlineMoreOptions;

    // Global listener for closing dropdowns on outside click
    document.addEventListener('click', () => {
      document.getElementById('select-dropdown-menu')?.classList.add('hidden');
      document.getElementById('desktop-filter-menu')?.classList.add('hidden');
    });

`;

content = content.slice(0, startIdx) + replacementJs + content.slice(endIdx);
fs.writeFileSync(file, content, 'utf8');
console.log('Replaced JavaScript functions successfully!');
