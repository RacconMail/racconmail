const fs = require('fs');
const path = require('path');

const inboxPath = path.join(__dirname, '..', 'public', 'inbox.php');
let php = fs.readFileSync(inboxPath, 'utf8');

const regex = /\/\/\s*Select Thread Item\r?\n\s*function selectThreadItem\(index\)[\s\S]*?if\s*\(window\.innerWidth\s*<\s*768\)[\s\S]*?\}\s*\}/;

const newSelectThread = `// Select Thread Item (Instant Zero-Lag Class Swap)
    function selectThreadItem(index) {
      const prevActive = document.querySelector('.msg-card-active');
      if (prevActive) {
        prevActive.classList.remove('msg-card-active');
        prevActive.classList.add('msg-card-inactive');
      }
      const nextActive = document.getElementById('thread-item-' + index);
      if (nextActive) {
        nextActive.classList.remove('msg-card-inactive');
        nextActive.classList.add('msg-card-active');
      }
      currentSelectedThreadIndex = index;
      
      const q = (document.getElementById('global-search')?.value || '').toLowerCase().trim();
      let list = [];
      if (currentFolder === 'All') list = [].concat(...Object.values(mailDatabase));
      else if (currentFolder === 'Unread') list = (mailDatabase['Inbox'] || []).filter(m => !m.isRead);
      else if (currentFolder === 'Starred') list = [].concat(...Object.values(mailDatabase)).filter(m => m.isStarred);
      else list = mailDatabase[currentFolder] || [];

      if (currentFilter === 'unread') list = list.filter(m => !m.isRead);
      if (currentFilter === 'starred') list = list.filter(m => m.isStarred);
      if (q) list = list.filter(m => (m.senderName||'').toLowerCase().includes(q) || (m.subject||'').toLowerCase().includes(q) || (m.sender||'').toLowerCase().includes(q));

      const selected = list[index];
      if (selected) {
        if (!selected.isRead) {
          selected.isRead = true;
          // Optimistically show dot as read
          const dot = nextActive?.querySelector('.ring-cyan-400\\/20');
          if (dot) dot.classList.add('opacity-0');
        }
        renderConversationThread(selected);
      }

      // On mobile view: show reading pane and hide middle column
      if (window.innerWidth < 768) {
        document.getElementById('col-inbox')?.classList.add('hidden');
        document.getElementById('col-reader')?.classList.remove('hidden');
        document.getElementById('col-reader')?.classList.add('flex');
      }
    }`;

if (regex.test(php)) {
  php = php.replace(regex, newSelectThread);
  fs.writeFileSync(inboxPath, php, 'utf8');
  console.log('Successfully optimized selectThreadItem with zero-lag class swap!');
} else {
  console.log('Regex did not match selectThreadItem!');
}
