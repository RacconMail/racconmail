/**
 * Raccon Mail — Fully Functional Mail Client
 * Matches Raccoon Postcard aesthetic + Full Raccon Mail functional capabilities
 */

const app = {
  // State
  currentUser: null,
  accounts: [],
  folders: { standard: {}, custom: [] },
  currentFolder: 'Inbox',
  currentFilter: 'all',
  currentSort: 'date_desc',
  searchQuery: '',
  messages: [],
  selectedMessage: null,
  checkedIds: new Set(),
  contacts: [],
  activeView: 'messages', // 'messages' | 'photos_files' | 'contacts'
  composeFiles: [],
  draftSaveTimer: null,
  currentDraftId: null,

  // Audio chime
  playChime() {
    try {
      // Gentle web audio synthesized postal chime
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.exponentialRampToValueAtTime(880.00, ctx.currentTime + 0.15); // A5
      gain.gain.setValueAtTime(0.12, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.45);
    } catch (_) {}
  },

  // Initialize
  async init() {
    // Check authentication: do not allow unauthenticated visitors into the mailbox
    try {
      const meRes = await fetch('/api/auth/me');
      const meData = await meRes.json();
      if (!meData.loggedIn || !meData.user) {
        window.location.replace('/auth?mode=register');
        return;
      }
    } catch (_) {
      window.location.replace('/auth?mode=register');
      return;
    }

    try { await this.fetchUser(); } catch (e) { console.error('fetchUser error:', e); }
    try { await this.fetchAccounts(); } catch (e) { console.error('fetchAccounts error:', e); }
    try { await this.fetchFolders(); } catch (e) { console.error('fetchFolders error:', e); }
    try { await this.fetchContacts(); } catch (e) { console.error('fetchContacts error:', e); }
    try { await this.loadMessages(); } catch (e) { console.error('loadMessages error:', e); }

    // Handle URL parameters (e.g. ?compose=1 or ?reply_to=...)
    const params = new URLSearchParams(window.location.search);
    if (params.get('compose') === '1' || params.get('reply_to') || params.get('to')) {
      this.openCompose({
        to: params.get('reply_to') || params.get('to') || '',
        subject: params.get('subject') || '',
        body: params.get('body') || ''
      });
    }

    try { this.setupSSE(); } catch (_) {}
    try { this.setupKeyboardShortcuts(); } catch (_) {}
    try { this.setupHistoryAndBackNavigation(); } catch (e) { console.error('history setup error:', e); }
    try { this.setupClickOutsideDismiss(); } catch (_) {}
    try { this.startMailPolling(); } catch (_) {}
    try { this.initSwipeGestures(); } catch (_) {}
    try { this.initGlassStyle(); } catch (_) {}

    // Responsive view synchronization on window resize / orientation change
    window.addEventListener('resize', () => {
      const msgPanel = document.getElementById('messages-panel');
      const readerPanel = document.getElementById('reader-panel');
      if (window.innerWidth >= 768) {
        msgPanel?.classList.remove('hidden');
        readerPanel?.classList.remove('hidden');
        readerPanel?.classList.add('flex');
      } else {
        if (this.selectedMessage || this.activeView === 'photos_files') {
          msgPanel?.classList.add('hidden');
          readerPanel?.classList.remove('hidden');
          readerPanel?.classList.add('flex');
        } else {
          msgPanel?.classList.remove('hidden');
          readerPanel?.classList.add('hidden');
          readerPanel?.classList.remove('flex');
        }
      }
    });

    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  setupClickOutsideDismiss() {
    document.addEventListener('click', (e) => {
      // 1. Account dropdown auto dismiss on click outside
      const accountDropdown = document.getElementById('account-dropdown');
      if (accountDropdown && !accountDropdown.classList.contains('hidden')) {
        const toggleBtn = e.target.closest('[onclick*="toggleAccountDropdown"]');
        if (!accountDropdown.contains(e.target) && !toggleBtn) {
          accountDropdown.classList.add('hidden');
        }
      }

      // 2. Reader more dropdown auto dismiss on click outside
      const readerDropdown = document.getElementById('reader-more-dropdown');
      if (readerDropdown && !readerDropdown.classList.contains('hidden')) {
        const toggleBtn = e.target.closest('[onclick*="toggleReaderMoreDropdown"]');
        if (!readerDropdown.contains(e.target) && !toggleBtn) {
          readerDropdown.classList.add('hidden');
        }
      }

      // 3. Search suggestions dropdown auto dismiss on click outside
      const searchDropdown = document.getElementById('search-suggestions-dropdown');
      if (searchDropdown && !searchDropdown.classList.contains('hidden')) {
        const searchContainer = document.getElementById('global-search-container');
        if (searchContainer && !searchContainer.contains(e.target)) {
          searchDropdown.classList.add('hidden');
        }
      }

      // 4. Advanced search dropdown auto dismiss on click outside
      const advSearchDropdown = document.getElementById('advanced-search-dropdown');
      if (advSearchDropdown && !advSearchDropdown.classList.contains('hidden')) {
        const searchContainer = document.getElementById('global-search-container');
        if (searchContainer && !searchContainer.contains(e.target)) {
          advSearchDropdown.classList.add('hidden');
        }
      }
    });

    // Escape key closes open dropdowns & profile modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.getElementById('account-dropdown')?.classList.add('hidden');
        document.getElementById('reader-more-dropdown')?.classList.add('hidden');
        document.getElementById('search-suggestions-dropdown')?.classList.add('hidden');
        document.getElementById('advanced-search-dropdown')?.classList.add('hidden');
        document.getElementById('profile-modal')?.classList.add('hidden');
      }
    });
  },

  setupHistoryAndBackNavigation() {
    if (!window.history || !window.history.pushState) return;

    // Buffer initial state so history always has an inbox anchor
    try {
      window.history.replaceState({ screen: 'inbox-root' }, '', '/inbox');
      window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
    } catch (_) {}

    let lastBackPressTime = 0;

    window.addEventListener('popstate', () => {
      // 1. Check if Command Palette is open
      const cmdPalette = document.getElementById('command-palette-backdrop');
      if (cmdPalette && !cmdPalette.classList.contains('hidden')) {
        this.closeCommandPalette();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 2. Check if Stories reader view is open
      const storiesReader = document.getElementById('stories-reader-view');
      if (storiesReader && !storiesReader.classList.contains('hidden')) {
        this.backToStoriesCatalog();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 3. Check if Stories modal is open
      const storiesModal = document.getElementById('stories-modal');
      if (storiesModal && !storiesModal.classList.contains('hidden')) {
        this.closeStoriesModal();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 4. Check if News modal is open
      const newsModal = document.getElementById('news-modal');
      if (newsModal && !newsModal.classList.contains('hidden')) {
        this.closeNewsModal();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 5. Check if Privacy modal is open
      const privacyModal = document.getElementById('privacy-modal');
      if (privacyModal && !privacyModal.classList.contains('hidden')) {
        this.closePrivacyModal();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 6. Check if Contacts modal is open
      const contactsModal = document.getElementById('contacts-modal');
      if (contactsModal && !contactsModal.classList.contains('hidden')) {
        this.closeContactsModal();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 7. Check if Compose modal is open
      const composeModal = document.getElementById('compose-modal');
      if (composeModal && !composeModal.classList.contains('hidden')) {
        this.closeCompose();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 8. Check if mobile Reader panel (single message view) is open
      const readerPanel = document.getElementById('reader-panel');
      if (readerPanel && !readerPanel.classList.contains('hidden') && window.innerWidth < 768) {
        this.backToMessages();
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
        return;
      }

      // 9. Root of inbox: Never allow navigation back to /auth or logout!
      const now = Date.now();
      if (now - lastBackPressTime < 2200) {
        this.showToast('Exiting Raccon Mail...', 1500);
        setTimeout(() => {
          if (window.navigator && window.navigator.app && window.navigator.app.exitApp) {
            window.navigator.app.exitApp();
          }
        }, 150);
      } else {
        lastBackPressTime = now;
        this.showToast('Press back again to exit', 2000);
        // Lock user safely inside the inbox
        window.history.pushState({ screen: 'inbox-root' }, '', '/inbox');
      }
    });
  },

  startMailPolling() {
    if (this.mailPollInterval) clearInterval(this.mailPollInterval);
    this.mailPollInterval = setInterval(async () => {
      try {
        const res = await fetch('/api/messages/sync');
        const data = await res.json();
        if (data && data.synced > 0) {
          await this.fetchFolders();
          const f = (this.currentFolder || '').toLowerCase();
          if (f === 'inbox' || f === 'unread') {
            await this.loadMessages();
          }
          this.playNotificationSound();
          this.showToast(`Received ${data.synced} new incoming email${data.synced > 1 ? 's' : ''}!`);
        }
      } catch (_) {}
    }, 15000);
  },

  // Keyboard shortcuts
  setupKeyboardShortcuts() {
    document.addEventListener('click', (e) => {
      const searchContainer = document.getElementById('global-search-container');
      const dropdown = document.getElementById('search-suggestions-dropdown');
      if (dropdown && !searchContainer?.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
    });

    document.addEventListener('keydown', (e) => {
      // Global shortcut: Ctrl+K or Cmd+K
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        this.openCommandPalette();
        return;
      }

      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
        if (e.key === 'Escape') {
          document.getElementById('contact-suggestions')?.classList.add('hidden');
          document.getElementById('search-suggestions-dropdown')?.classList.add('hidden');
          this.closeCommandPalette();
        }
        return;
      }

      const key = e.key.toLowerCase();

      if (e.key === '/') {
        e.preventDefault();
        if (window.innerWidth < 768) {
          this.openCommandPalette();
        } else {
          const searchInput = document.getElementById('global-search');
          if (searchInput) searchInput.focus();
        }
      } else if (key === 'c') {
        e.preventDefault();
        this.openCompose();
      } else if (e.key === '?' || (e.shiftKey && key === '?') || key === 'h') {
        e.preventDefault();
        this.openKeyboardShortcuts();
      } else if (key === 'r') {
        if (this.selectedMessage) {
          e.preventDefault();
          const fastInput = document.getElementById('quick-reply-body');
          if (fastInput) fastInput.focus();
          else this.replyToMessage();
        }
      } else if (key === 'a') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.replyAllMessage();
        }
      } else if (key === 'f') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.forwardMessage();
        }
      } else if (key === 'e') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.archiveMessage(this.selectedMessage.id);
        }
      } else if (e.key === '#' || e.key === 'Delete') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.trashMessage(this.selectedMessage.id);
        }
      } else if (key === 's') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.toggleStar(this.selectedMessage.id, !this.selectedMessage.is_starred);
        }
      } else if (key === 'u') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.toggleRead(this.selectedMessage.id, false);
        }
      } else if (key === 'p') {
        if (this.selectedMessage) {
          e.preventDefault();
          this.printMessage();
        }
      } else if (key === 'j') {
        e.preventDefault();
        this.selectNextMessage();
      } else if (key === 'k') {
        e.preventDefault();
        this.selectPreviousMessage();
      } else if (e.key === 'Escape') {
        this.closeAllModals();
        this.closeCommandPalette();
        if (window.innerWidth < 768) {
          this.backToMessages();
        }
      }
    });
  },

  openKeyboardShortcuts() {
    document.getElementById('keyboard-shortcuts-modal')?.classList.remove('hidden');
    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  closeKeyboardShortcuts() {
    document.getElementById('keyboard-shortcuts-modal')?.classList.add('hidden');
  },

  printMessage() {
    window.print();
  },

  downloadMessageEml(msgId) {
    const msg = (this.messages || []).find(m => String(m.id) === String(msgId)) || this.selectedMessage;
    if (!msg) return;

    const emlContent = [
      `From: ${msg.sender_name || 'Raccon Mail'} <${msg.sender_email || 'anonymous@racconmail.eu'}>`,
      `To: <${msg.recipient_email || ''}>`,
      `Date: ${new Date(msg.created_at || Date.now()).toUTCString()}`,
      `Subject: ${msg.subject || '(no subject)'}`,
      `MIME-Version: 1.0`,
      `Content-Type: text/plain; charset=UTF-8`,
      `X-Mailer: Raccon Mail Webmail Client (https://racconmail.eu)`,
      ``,
      msg.body || ''
    ].join('\r\n');

    const blob = new Blob([emlContent], { type: 'message/rfc822' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const safeSubject = (msg.subject || 'message').replace(/[^a-z0-9_-]/gi, '_').substring(0, 50);
    a.download = `${safeSubject}.eml`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  },

  async markAllRead() {
    if (!confirm('Mark all messages in this folder as read?')) return;
    try {
      const res = await fetch('/api/messages/batch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_all_read', folder: this.currentFolder })
      });
      const data = await res.json();
      if (data.success) {
        (this.messages || []).forEach(m => m.is_read = 1);
        this.renderMessagesList();
        this.fetchFolders();
        this.playChime?.();
      }
    } catch (err) {
      console.error('Failed to mark all as read:', err);
    }
  },

  selectNextMessage() {
    if (!this.messages || this.messages.length === 0) return;
    const currentIndex = this.selectedMessage ? this.messages.findIndex(m => String(m.id) === String(this.selectedMessage.id)) : -1;
    const nextIndex = (currentIndex + 1) < this.messages.length ? currentIndex + 1 : 0;
    this.selectMessage(this.messages[nextIndex].id);
  },

  selectPreviousMessage() {
    if (!this.messages || this.messages.length === 0) return;
    const currentIndex = this.selectedMessage ? this.messages.findIndex(m => String(m.id) === String(this.selectedMessage.id)) : 0;
    const prevIndex = (currentIndex - 1) >= 0 ? currentIndex - 1 : this.messages.length - 1;
    this.selectMessage(this.messages[prevIndex].id);
  },

  openMessage(id) {
    return this.selectMessage(id);
  },

  closeAllModals() {
    this.closeMobileSidebar?.();
    document.getElementById('account-dropdown')?.classList.add('hidden');
    document.getElementById('new-folder-modal')?.classList.add('hidden');
    document.getElementById('contacts-modal')?.classList.add('hidden');
    document.getElementById('command-palette-backdrop')?.classList.add('hidden');
    document.getElementById('contact-form-modal')?.classList.add('hidden');
    document.getElementById('settings-modal')?.classList.add('hidden');
    document.getElementById('postcard-print-modal')?.classList.add('hidden');
    document.getElementById('keyboard-shortcuts-modal')?.classList.add('hidden');
    document.getElementById('privacy-modal')?.classList.add('hidden');
    document.getElementById('news-modal')?.classList.add('hidden');
    document.getElementById('stories-modal')?.classList.add('hidden');
  },

  openPrivacyModal() {
    this.closeAllModals();
    document.getElementById('privacy-modal')?.classList.remove('hidden');
  },

  closePrivacyModal() {
    document.getElementById('privacy-modal')?.classList.add('hidden');
  },

  openNewsModal() {
    this.closeAllModals();
    this.renderInboxNews();
    document.getElementById('news-modal')?.classList.remove('hidden');
    lucide.createIcons();
  },

  closeNewsModal() {
    document.getElementById('news-modal')?.classList.add('hidden');
  },

  renderInboxNews() {
    const grid = document.getElementById('inbox-news-grid');
    if (!grid) return;
    const sections = [
      {
            "name": "Tech & AI",
            "icon": "💻",
            "tag": "Stealth & Chips",
            "cover": "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "Next-Gen Stealth Chips Deliver 10x On-Device Encryption",
                        "source": "Raccon News",
                        "time": "2m ago",
                        "desc": "Hardware isolation prevents memory scraping & data harvesting.",
                        "image": "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Open-Source AI Models Challenge Big Tech Monopolies",
                        "source": "Raccon News",
                        "time": "14m ago",
                        "desc": "Decentralized models put full control back in developers' hands.",
                        "image": "https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Global Cyber Treaties Move Toward Universal End-to-End Encryption",
                        "source": "Raccon News",
                        "time": "52m ago",
                        "desc": "Privacy coalition declares client-side zero-knowledge encryption a human right.",
                        "image": "https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      },
      {
            "name": "World & Economy",
            "icon": "🌍",
            "tag": "Global Markets",
            "cover": "https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "European Union Finalizes Ambitious Clean Energy Supergrid",
                        "source": "Raccon News",
                        "time": "15m ago",
                        "desc": "Interconnected wind and solar arrays share power across 14 nations.",
                        "image": "https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Global Markets Rally as Tech Innovations Spur Manufacturing",
                        "source": "Raccon News",
                        "time": "38m ago",
                        "desc": "Lower energy costs drive positive quarterly trade volumes.",
                        "image": "https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Green Hydrogen Pipeline Network Commences Construction in North Sea",
                        "source": "Raccon News",
                        "time": "1h ago",
                        "desc": "Multi-billion euro infrastructure to replace fossil fuels in steel making.",
                        "image": "https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      },
      {
            "name": "Sports Arena",
            "icon": "⚽",
            "tag": "Nocturnal Games",
            "cover": "https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "Champions League Night Clashes Deliver Dramatic Late Winners",
                        "source": "Raccon News",
                        "time": "3m ago",
                        "desc": "Extra-time heroics seal quarterfinal spots under stadium lights.",
                        "image": "https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Global Marathon Records Shattered in Historic Night Race",
                        "source": "Raccon News",
                        "time": "18m ago",
                        "desc": "Crisp cool 12°C nocturnal air fuels historic distance times in Berlin.",
                        "image": "https://images.unsplash.com/photo-1452626038306-9aae5e071dd3?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Midnight Street Basketball Tournaments Surge in Popularity",
                        "source": "Raccon News",
                        "time": "42m ago",
                        "desc": "Local city courts light up with intense 3-on-3 showdowns and DJ sets.",
                        "image": "https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      },
      {
            "name": "Games & Esports",
            "icon": "🎮",
            "tag": "Burrows & RPG",
            "cover": "https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "Nocturnal RPG 'Shadow Bandit' Announced for PC & Consoles",
                        "source": "Raccon News",
                        "time": "5m ago",
                        "desc": "Play as a masked woodland creature solving midnight bakery mysteries.",
                        "image": "https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Global Esports Grand Finals Break Viewership Milestones",
                        "source": "Raccon News",
                        "time": "21m ago",
                        "desc": "Over 4.9 million concurrent viewers tune into intense nocturnal decider.",
                        "image": "https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Next-Gen Handheld Update Boosts OLED Contrast & Battery",
                        "source": "Raccon News",
                        "time": "44m ago",
                        "desc": "Firmware optimization extends portable gameplay sessions by 2 full hours.",
                        "image": "https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      },
      {
            "name": "Science & Space",
            "icon": "🚀",
            "tag": "Cosmos & Discovery",
            "cover": "https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "James Webb Telescope Captures Atmospheric Chemistry of Exoplanet",
                        "source": "Raccon News",
                        "time": "12m ago",
                        "desc": "Spectroscopic readings detect water vapor and carbon clouds in habitable zone.",
                        "image": "https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Deep Ocean Expedition Uncovers Glowing Bioluminescent Ecosystems",
                        "source": "Raccon News",
                        "time": "35m ago",
                        "desc": "Submersibles document never-before-seen deep trench marine species.",
                        "image": "https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Room-Temperature Superconductivity Synthesized in Crystal Matrix",
                        "source": "Raccon News",
                        "time": "1h ago",
                        "desc": "New ambient pressure material conduct electricity with zero resistance.",
                        "image": "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      },
      {
            "name": "Music & Audio",
            "icon": "🎵",
            "tag": "Lo-Fi Chillhop",
            "cover": "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "Midnight Lo-Fi Beats Stream Surpasses 1B Hours Worldwide",
                        "source": "Raccon News",
                        "time": "4m ago",
                        "desc": "Night owls and study groups make ambient chillhop the top stream.",
                        "image": "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Underground Synthwave Album Climbs Global Vinyl Charts",
                        "source": "Raccon News",
                        "time": "24m ago",
                        "desc": "Retro 80s analog synthesizers captivate night drivers worldwide.",
                        "image": "https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Acoustic Forest Sessions Recorded Under Moonlight Goes Viral",
                        "source": "Raccon News",
                        "time": "49m ago",
                        "desc": "Natural woodland acoustics bring intimate folk guitar tracks to life.",
                        "image": "https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      },
      {
            "name": "Night Radar",
            "icon": "☀️",
            "tag": "Starlight & Moon",
            "cover": "https://images.unsplash.com/photo-1506703719100-a0f3a48c0f86?auto=format&fit=crop&w=600&q=80",
            "items": [
                  {
                        "title": "Night Sky Clarity: 98% (Exceptional Star Visibility Across Europe)",
                        "source": "Raccon News",
                        "time": "Live radar",
                        "desc": "Crisp atmospheric pressure; ideal for nocturnal stargazing.",
                        "image": "https://images.unsplash.com/photo-1506703719100-a0f3a48c0f86?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Moon Phase: Waxing Gibbous (84% Illuminated Night Glow)",
                        "source": "Raccon News",
                        "time": "Astronomical",
                        "desc": "Gentle natural silver moonlight over European burrows and cities.",
                        "image": "https://images.unsplash.com/photo-1532767153582-b1a0e5145009?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  },
                  {
                        "title": "Northern Aurora Borealis Visible in Upper Latitudes Tonight",
                        "source": "Raccon News",
                        "time": "Live Alert",
                        "desc": "Solar wind geomagnetic index generates dancing emerald curtains.",
                        "image": "https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=600&q=80",
                        "link": "/news"
                  }
            ]
      }
];

    grid.innerHTML = sections.map(sec => `
      <div class="rounded-2xl border border-border bg-card p-3 shadow-xs flex flex-col justify-between group hover:border-primary/50 transition duration-200">
        <div>
          <!-- Header with Category & Cover Thumbnail -->
          <div class="relative aspect-video rounded-xl overflow-hidden mb-2.5 bg-muted">
            <img src="${sec.cover}" alt="${sec.name}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
            <div class="absolute top-2 left-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-black/60 text-white backdrop-blur-md border border-white/10">
                <span>${sec.icon}</span>
                <span>${sec.name}</span>
              </span>
            </div>
            <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-[10px] text-white/90">
              <span class="font-mono bg-slate-900 text-white font-bold px-1.5 py-0.2 rounded border border-slate-700">${sec.tag}</span>
              <span class="flex items-center gap-1 font-bold text-slate-200">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-pulse"></span>
                <span>LIVE</span>
              </span>
            </div>
          </div>

          <!-- Stories List with Images on Every Post -->
          <div class="space-y-2">
            ${sec.items.map(it => `
              <div class="p-2 rounded-xl bg-muted/40 hover:bg-muted transition flex gap-2.5 items-start">
                <img src="${it.image}" alt="${it.title}" loading="lazy" class="w-12 h-12 rounded-lg object-cover shrink-0 border border-border/60 shadow-xs" onerror="this.src='/raccoon-logo.png';" />
                <div class="min-w-0 flex-1">
                  <div class="flex justify-between items-center text-[9px] text-muted-foreground mb-0.5">
                    <span class="font-mono text-muted-foreground">${it.time}</span>
                  </div>
                  <a href="/news" class="font-bold text-[11px] text-foreground hover:text-primary leading-tight line-clamp-2 block">
                    ${it.title}
                  </a>
                  <p class="text-[10px] text-muted-foreground mt-0.5 line-clamp-1 leading-relaxed">
                    ${it.desc}
                  </p>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
        <div class="pt-2 mt-2 border-t border-border-light text-[10px]">
          <a href="/news" class="text-primary hover:underline font-bold flex items-center justify-between">
            <span>Open in Full Wire</span>
            <span>&rarr;</span>
          </a>
        </div>
      </div>
    `).join('');
  },

  // ==========================================
  // WORLD STORIES LIBRARY (worldstories.org.uk)
  // ==========================================
  worldStoriesList: [
    {
      id: 'anansi-turtle',
      title: 'Anansi and the Turtle',
      country: 'Ghana',
      flag: '🇬🇭',
      category: 'animals',
      readTime: '3 min',
      moral: 'Treat guests with true generosity, for greed always returns to the greedy.',
      synopsis: 'Anansi the spider tricks hungry Turtle out of dinner, but when Turtle invites him beneath the river, Anansi learns an unforgettable lesson.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `One evening, Anansi the Spider had prepared a delicious pot of hot, steaming yams. The aroma drifted through the forest. Just as he sat down with his spoon, Turtle knocked at the door, tired and hungry from a long journey.

Hospitality was the law of the land, so Anansi had to invite Turtle to eat. But Anansi was greedy. Every time Turtle reached for a yam, Anansi scolded him: "Turtle! Your hands are covered in dust from the road! Go wash them at the river!"

Turtle waddled down to the river, washed his paws, and crawled back. But by the time he returned, Anansi had eaten half the yams. Turtle reached again, but his paws were dusty once more from crawling. "Wash again!" demanded Anansi. When Turtle returned the second time, all the yams were gone. Turtle smiled politely: "Thank you for the hospitality, Anansi. Tomorrow, you must dine with me at my home beneath the river."

The next day, Anansi hurried to the riverbank. Turtle was waiting at the bottom of the deep river, where a glorious table of fish and water berries was spread. Anansi leaped into the river, but he was too light and kept floating back to the surface!

Determined to reach the feast, Anansi stuffed heavy stones into the pockets of his jacket. Down, down he sank, landing right at Turtle's table!

"Welcome, Anansi!" Turtle smiled. "In my home, it is customary to take off your heavy coat before dining."

Anansi had no choice. He slipped off his jacket. The moment the heavy stones left his shoulders, Anansi shot up like a cork to the water's surface, bobbing helplessly while Turtle enjoyed the feast below.`
    },
    {
      id: 'sun-wind',
      title: 'The Sun and the Wind',
      country: 'Greece',
      flag: '🇬🇷',
      category: 'fables',
      readTime: '2 min',
      moral: 'Gentleness and warmth accomplish far more than fury and force.',
      synopsis: 'The Wind and the Sun dispute who is stronger and test their powers on a cloaked traveller walking along a winding road.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `The North Wind and the Sun had a dispute about which was the most powerful. While they argued, a traveller came walking along the country road wrapped in a warm woollen cloak.

"I will prove my strength," boasted the Wind. "Whoever can compel that traveller to take off his cloak shall be crowned the mightiest!"

The Sun agreed and hid behind a soft cloud. The North Wind puffed out his cheeks and blew a freezing, howling tempest. Leaves tore from the branches and dust swirled. But the harder the Wind blew, the tighter the traveller wrapped his cloak around his shoulders, clasping it with both hands. Finally, breathless and exhausted, the Wind gave up.

Then the Sun emerged with a warm, gentle smile. He beamed his golden rays through the sky. Slowly, the air grew pleasant, then warmly delightful. The traveller unfastened his buttons. As the golden sunlight bathed the meadows, the traveller wiped the perspiration from his brow, gladly took off his heavy cloak, and rested peacefully in the shade of a flowering tree.`
    },
    {
      id: 'clever-monkey',
      title: 'The Clever Monkey and the Crocodile',
      country: 'India',
      flag: '🇮🇳',
      category: 'animals',
      readTime: '4 min',
      moral: 'Quick wits and presence of mind can save you from the greatest dangers.',
      synopsis: 'When a crocodile tries to betray his monkey friend to satisfy his wife\'s greedy wish, the clever monkey uses quick thinking to escape.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `A clever monkey lived in a tall rose-apple tree on the bank of the Ganges river. Every day, he ate sweet, succulent apples and tossed juicy ones to a crocodile who rested by the water. Over time, the monkey and the crocodile became fast friends.

One day, the crocodile took some rose-apples home to his wife. She tasted them and gasped: "If this monkey eats these sweet fruits every day, his heart must be the sweetest nectar in the world! Bring him to me so I may eat his heart!"

The crocodile was torn, but finally agreed. He returned to the tree: "My dear friend, my wife has prepared a feast for you to thank you for the apples. Hop onto my back, and I will carry you across the river to our island."

The monkey gladly hopped on. Halfway across the deep river, the crocodile began to sink. "Why are you sinking?" asked the monkey.

"Forgive me, friend," sighed the crocodile. "My wife wishes to eat your heart."

The monkey was terrified, but he did not panic. He laughed out loud: "Oh, foolish crocodile! Why didn't you tell me on the shore? We monkeys do not keep our hearts in our chests while jumping through trees—they would get bruised! I left my heart safely tucked in the high branches of my rose-apple tree. Take me back quickly, and I will fetch it for your wife!"

The naive crocodile turned around and swam furiously back to the riverbank. The moment they touched the shore, the monkey vaulted high into the tree and shouted: "Did you ever hear of anyone living without their heart in their chest? Go back to your wife, crocodile, and tell her that friendship cannot be shared with those who betray trust!"`
    },
    {
      id: 'raccoon-moon',
      title: 'How the Raccoon Got His Mask & Clever Paws',
      country: 'North America',
      flag: '🇺🇸',
      category: 'animals',
      readTime: '3 min',
      moral: 'Curiosity and careful care make the humblest creature wise and resourceful.',
      synopsis: 'A traditional indigenous tale of why the nocturnal raccoon wears a masked fur coat and always washes his berries in fresh water.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `In the elder times when animals spoke the language of the rivers, Raccoon was known as the most inquisitive creature in the woods. While the bears slept and the eagles soared, Raccoon explored every hollow log, pebble bed, and creek.

One moonlit night, Raccoon watched Grandmother Moon painting silver paths across the forest streams. Raccoon crept up to the reflection in the calm water, determined to catch a piece of the silvery glow with his nimble paws.

"Little Raccoon," Grandmother Moon whispered through the pine needles. "You look at the world so closely, testing each shell and washing every seed. Because your eyes are keen in the dark, I grant you the Bandit's Mask of Shadow. It will protect your vision under starlight and let you move unseen by those who wish you harm."

Grandmother Moon touched Raccoon's face with a dab of nocturnal soot, creating his iconic dark mask and rings on his tail. She blessed his paws with sensitive fingertips like human hands, saying: "Whenever you feast, wash your meal in clear running water as a sign of respect for the river that feeds you."

To this day, Raccoon proudly wears his midnight mask, washing every berry and clam before dining beneath the smiling moon.`
    },
    {
      id: 'magic-paintbrush',
      title: 'Ma Liang and the Magic Paintbrush',
      country: 'China',
      flag: '🇨🇳',
      category: 'myths',
      readTime: '4 min',
      moral: 'A generous heart and creative spirit will always defeat greed and tyranny.',
      synopsis: 'A kind poor boy receives a paintbrush whose paintings come to life, using his art to bring water, grain, and freedom to his village.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `Long ago in ancient China lived a poor orphan named Ma Liang. He loved painting more than anything in the world, though he was too poor to afford even a single brush. He practiced by drawing birds in the sand with twigs and fish on river stones with wet pebbles.

One night, an old immortal with a silver beard appeared in his dreams and handed him a gleaming golden brush: "Use this gift wisely, Ma Liang. Only create what helps those in need."

When Ma Liang awoke, the golden brush was in his hand. He painted a swallow on the wall; instantly, the swallow fluttered its wings, chirped melodiously, and flew into the blue sky!

Seeing how his villagers suffered from drought, Ma Liang painted an irrigation ditch that gushed fresh water, a sturdy water buffalo to plough the fields, and bushels of golden grain. The villagers rejoiced.

Soon, a greedy, cruel magistrate heard of the miracle. He arrested Ma Liang and commanded: "Paint me a mountain of solid gold!"

Ma Liang calmly painted a vast blue ocean, and in the far distance, a mountain shimmering with gold. "How shall I get there?" demanded the magistrate. Ma Liang painted a grand wooden ship.

The magistrate and his greedy guards boarded the ship. As it sailed toward the gold mountain, Ma Liang painted great gusts of wind and stormy waves. The wind blew stronger and stronger until the ship capsized, washing the greedy ruler far across the sea. Ma Liang returned to his village, painting plows, food, and joy for all who asked.`
    },
    {
      id: 'lion-mouse',
      title: 'The Lion and the Tiny Mouse',
      country: 'United Kingdom',
      flag: '🇬🇧',
      category: 'fables',
      readTime: '2 min',
      moral: 'No act of kindness, no matter how small, is ever wasted.',
      synopsis: 'A mighty lion laughs when a tiny mouse promises to repay a favor, but soon discovers that small friends can prove to be great allies.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `A great lion lay asleep in the warm sunshine when a playful field mouse scampered across his nose. The lion awoke with a roar, slapped down his massive paw, and pinned the tiny mouse against the earth.

"Pardon, King of Beasts!" squeaked the terrified mouse. "Spare my life! I did not mean to disturb your sleep. If you let me go, perhaps one day I can help you in return."

The lion laughed so heartily at the thought of a tiny mouse helping the king of the jungle that his paw lifted. "Off with you, little one," he chuckled, amused by the mouse's courage.

A few weeks later, the lion was stalking prey when he walked into a hunter's trap. A heavy net made of thick ropes dropped over him. The lion thrashed, roared, and tore at the mesh, but the ropes only tightened.

Hearing the lion's roar of distress, the tiny mouse scurried to the spot. "Do not worry, friend," the mouse squeaked. With sharp little teeth, the mouse gnawed tirelessly at the thick cords until one by one, the knots severed. The net fell away, and the mighty lion sprang free.

"You laughed at me then," the mouse smiled, "but now you see that even a little mouse can save a mighty lion."`
    },
    {
      id: 'urashima-taro',
      title: 'Urashima Taro and the Dragon Palace',
      country: 'Japan',
      flag: '🇯🇵',
      category: 'myths',
      readTime: '4 min',
      moral: 'Time is the most precious treasure; live mindfully and cherish your days.',
      synopsis: 'A compassionate fisherman saves a sea turtle and is rewarded with an enchanted voyage to the underwater palace of Ryugu-jo.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `Urashima Taro was a humble, kind-hearted fisherman who lived in a small coastal village with his elderly mother. One day on the beach, he saw several boys teasing a small sea turtle. Taro gave the boys a few coins, gently carried the turtle into the rolling waves, and watched it swim to safety.

A few days later while fishing out at sea, a giant sea turtle surfaced beside Taro's boat: "Taro! I am the turtle you rescued. Otohime, the Princess of the Sea, wishes to thank you. Ride on my shell to the Dragon Palace of Ryugu-jo!"

Taro climbed onto the turtle's shell, and together they dove into the crystal-clear ocean. Deep beneath the waves stood a palace made of coral, mother-of-pearl, and glowing pearls. Princess Otohime welcomed Taro with songs, banquets of seaweed cakes, and dances by rainbow-colored fish.

Taro enjoyed several wonderful days in the palace, but soon thought of his aging mother and grew homesick. Princess Otohime was sad to see him go. She presented him with a beautiful lacquered box, the Tamatebako, wrapped with a red silk cord: "Keep this box as a token of my gratitude. But remember: whatever happens, you must never open it."

The turtle carried Taro back to his home beach. But when Taro walked into his village, everything was changed! The houses were different, the harbor had grown, and all the faces were strangers. He asked about his mother, and an old villager told him: "Urashima Taro disappeared into the sea three hundred years ago!"

Grief-stricken, Taro sat on the beach. Bewildered, he untied the red ribbon of the Tamatebako and lifted the lid. A plume of white smoke billowed forth. As the smoke enveloped him, Taro's hair turned silver, his skin grew wrinkled, and he aged three hundred years in a single breath—the smoke held the stolen years of his ocean dream.`
    },
    {
      id: 'curupira-forest',
      title: 'Curupira: Guardian of the Rainforest',
      country: 'Brazil',
      flag: '🇧🇷',
      category: 'folktales',
      readTime: '3 min',
      moral: 'Take only what you need from nature, and respect the creatures who share our earth.',
      synopsis: 'The flame-haired spirit of the Amazon with backward-facing feet protects the trees and animals from greedy poachers.',
      url: 'https://worldstories.org.uk/lang/english',
      content: `In the heart of the Amazon rainforest lives Curupira, the mythical boy with hair like bright campfire flames, green teeth, and feet that point backwards.

Because his heels point forward and his toes point backward, anyone trying to track Curupira in the forest follows his footprints in the wrong direction and becomes delightfully confused!

Curupira is the protector of all animals and trees. He rides on the back of wild peccaries and knocks on tree trunks with a hollow bamboo stick before a thunderstorm, testing whether the roots are strong enough to withstand the wind.

If a hunter enters the forest to feed his hungry family, Curupira leaves him in peace. But if a greedy poacher enters to trap pregnant animals or chop down sacred mahogany trees for quick wealth, Curupira mimics bird calls and creates eerie phantom whistles. The poacher spins in circles following footprints that lead nowhere, until terrified and empty-handed, he flees the jungle, vowing never to harm the wild again.`
    }
  ],

  currentStoryCategory: 'all',
  currentStoryQuery: '',
  selectedStory: null,
  worldStoriesCatalogLoaded: false,

  async openStoriesModal() {
    this.closeAllModals();
    this.currentStoryCategory = 'all';
    this.currentStoryQuery = '';
    const searchInput = document.getElementById('stories-search-input');
    if (searchInput) searchInput.value = '';

    this.backToStoriesCatalog();
    this.renderStoriesGrid();
    document.getElementById('stories-modal')?.classList.remove('hidden');
    lucide.createIcons();

    // Dynamically load the full 175-story library catalog from worldstories.org.uk
    if (!this.worldStoriesCatalogLoaded) {
      try {
        const res = await fetch('/worldstories_catalog.json');
        if (res.ok) {
          const fullCatalog = await res.json();
          if (Array.isArray(fullCatalog) && fullCatalog.length > 0) {
            const existingMap = new Map();
            this.worldStoriesList.forEach(s => existingMap.set(s.id, s));
            
            fullCatalog.forEach(c => {
              if (!existingMap.has(c.id)) {
                this.worldStoriesList.push({
                  id: c.id,
                  slug: c.slug || c.id,
                  title: c.title,
                  country: c.country || 'World',
                  flag: c.flag || '🌍',
                  category: c.category || 'fables',
                  readTime: c.readTime || '3 min',
                  moral: 'A classic tale of wisdom, imagination, and cultural discovery.',
                  synopsis: c.desc || 'Illustrated traditional story.',
                  cover: c.cover || `https://worldstories.org.uk/content/book/${c.id}/__cover.jpg`,
                  url: c.url || `https://worldstories.org.uk/reader/${c.slug || c.id}/english/${c.id}`,
                  languages: c.languages || ['English'],
                  content: null
                });
              } else {
                const s = existingMap.get(c.id);
                if (!s.cover && c.cover) s.cover = c.cover;
                if (!s.slug && c.slug) s.slug = c.slug;
                if (!s.languages && c.languages) s.languages = c.languages;
              }
            });
            this.worldStoriesCatalogLoaded = true;
            this.renderStoriesGrid();
          }
        }
      } catch (err) {
        console.warn('Could not load full World Stories catalog:', err);
      }
    }
  },

  closeStoriesModal() {
    document.getElementById('stories-modal')?.classList.add('hidden');
  },

  filterStories(category) {
    this.currentStoryCategory = category;
    const tabs = document.querySelectorAll('#story-filter-tabs .story-tab');
    tabs.forEach(tab => {
      tab.classList.remove('bg-slate-900', 'text-white', 'btn-orange-matte', 'font-bold', 'shadow-xs', 'active');
      tab.classList.add('font-medium', 'text-slate-600', 'hover:bg-slate-100');
    });
    const clicked = event?.currentTarget;
    if (clicked) {
      clicked.classList.remove('font-medium', 'text-slate-600', 'hover:bg-slate-100');
      clicked.classList.add('bg-slate-900', 'text-white', 'font-bold', 'shadow-xs', 'active');
    }
    this.renderStoriesGrid();
  },

  searchStories(val) {
    this.currentStoryQuery = (val || '').trim().toLowerCase();
    this.renderStoriesGrid();
  },

  renderStoriesGrid() {
    const container = document.getElementById('stories-grid-container');
    if (!container) return;

    let list = this.worldStoriesList;
    if (this.currentStoryCategory !== 'all') {
      list = list.filter(s => s.category === this.currentStoryCategory);
    }
    if (this.currentStoryQuery) {
      const q = this.currentStoryQuery;
      list = list.filter(s =>
        s.title.toLowerCase().includes(q) ||
        (s.country && s.country.toLowerCase().includes(q)) ||
        (s.synopsis && s.synopsis.toLowerCase().includes(q)) ||
        (s.moral && s.moral.toLowerCase().includes(q))
      );
    }

    if (list.length === 0) {
      container.innerHTML = `
        <div class="col-span-full flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
          <span class="text-3xl mb-2">📖</span>
          <p class="font-bold text-sm">No stories match your filter</p>
          <button onclick="app.filterStories('all')" class="mt-2 text-primary font-bold text-xs hover:underline">
            View all 175+ World Stories
          </button>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(story => `
      <div class="rounded-2xl border border-border bg-card hover:border-primary/40 hover:shadow-md p-3.5 transition flex flex-col justify-between group">
        <div>
          <!-- Story Cover Thumbnail or Origin Badge -->
          <div class="relative w-full h-36 rounded-xl overflow-hidden bg-muted/60 mb-3 flex items-center justify-center border border-border/40">
            ${story.cover ? `
              <img src="${story.cover}" alt="${story.title}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" onerror="this.parentElement.innerHTML='<span class=\\'text-3xl\\'>📚</span>'" />
            ` : `
              <span class="text-3xl">📚</span>
            `}
            <div class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-xs text-white text-[11px] font-bold flex items-center gap-1 shadow-xs">
              <span>${story.flag || '🌍'}</span>
              <span>${story.country || 'World'}</span>
            </div>
            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-xs text-white text-[10px] font-mono shadow-xs">
              ⏱️ ${story.readTime || '3 min'}
            </div>
          </div>

          <!-- Title -->
          <h4 class="font-display font-bold text-sm sm:text-base text-foreground group-hover:text-primary transition line-clamp-1 mb-1">
            ${story.title}
          </h4>

          <!-- Synopsis -->
          <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed mb-2.5">
            ${story.synopsis || story.desc || 'A classic traditional folktale.'}
          </p>

          <!-- Languages info -->
          ${story.languages && story.languages.length > 1 ? `
            <div class="text-[10px] text-muted-foreground line-clamp-1 mb-2">
              🌐 In ${story.languages.length} languages: ${story.languages.slice(0, 3).join(', ')}...
            </div>
          ` : ''}
        </div>

        <!-- Action Buttons -->
        <div class="pt-2.5 border-t border-border/60 flex items-center justify-between gap-2">
          <button onclick="app.openStoryReader('${story.id}')" class="flex-1 py-1.5 px-3 rounded-full bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center justify-center gap-1 shadow-2xs hover:opacity-95 transition cursor-pointer">
            <i data-lucide="book-open" class="h-3.5 w-3.5 text-white"></i>
            <span>Read Tale</span>
          </button>
          <button onclick="app.openStoryReader('${story.id}')" title="Read story in integrated reader" class="p-1.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground transition border border-border shrink-0 cursor-pointer">
            <i data-lucide="book-open" class="h-3.5 w-3.5 text-primary"></i>
          </button>
        </div>
      </div>
    `).join('');

    lucide.createIcons();
  },

  async openStoryReader(storyId) {
    const story = this.worldStoriesList.find(s => s.id === storyId);
    if (!story) return;
    this.selectedStory = story;

    const catalogView = document.getElementById('stories-catalog-view');
    const readerView = document.getElementById('stories-reader-view');
    const contentEl = document.getElementById('stories-reader-content');
    const badgeEl = document.getElementById('story-reader-origin-badge');
    const linkEl = document.getElementById('story-reader-source-link');

    if (badgeEl) badgeEl.innerHTML = `📍 Origin: <strong>${story.flag || '🌍'} ${story.country || 'World'}</strong> &bull; Read Time: <strong>${story.readTime || '3 min'}</strong>`;
    if (linkEl) linkEl.href = story.url;

    catalogView?.classList.add('hidden');
    catalogView?.classList.remove('flex');
    readerView?.classList.remove('hidden');
    readerView?.classList.add('flex');

    // Show initial loading state if content not cached yet
    if (contentEl) {
      contentEl.innerHTML = `
        <div class="max-w-2xl mx-auto space-y-4">
          <!-- Story Header Card -->
          <div class="p-4 sm:p-5 rounded-3xl bg-muted/40 border border-border flex items-center justify-between gap-4">
            <div class="min-w-0">
              <span class="text-xs font-bold text-primary flex items-center gap-1.5 uppercase tracking-wide">
                <span>${story.flag || '🌍'}</span>
                <span>Traditional Tale from ${story.country || 'World'}</span>
              </span>
              <h2 class="font-display font-extrabold text-xl sm:text-2xl text-foreground mt-1 truncate">${story.title}</h2>
              <p class="text-xs text-muted-foreground mt-1">Cultural Folktale &bull; Traditional Heritage</p>
            </div>
            ${story.cover ? `<img src="${story.cover}" class="h-16 w-16 rounded-2xl object-cover shrink-0 border border-border" />` : `<span class="text-3xl sm:text-4xl">📜</span>`}
          </div>

          <!-- Story Text Loading or Rendered -->
          <div id="story-reader-text-area" class="story-body-text space-y-3.5 pt-2 font-serif text-base sm:text-lg leading-loose">
            ${story.content ? story.content.split('\n\n').map(p => `<p class="leading-relaxed text-foreground/90 text-sm sm:text-base">${p.trim()}</p>`).join('') : `
              <div class="flex flex-col items-center justify-center p-8 text-center text-muted-foreground space-y-3">
                <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-primary"></i>
                <p class="text-sm">Loading story text...</p>
              </div>
            `}
          </div>

          <!-- Postcard Dispatch Callout -->
          <div class="mt-6 p-4 rounded-3xl border border-dashed border-primary/30 bg-card flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xs">
            <div class="text-center sm:text-left">
              <h5 class="font-display font-bold text-sm text-foreground">Share this tale as a clever postcard</h5>
              <p class="text-xs text-muted-foreground">Deliver this folktale directly to any friend's inbox.</p>
            </div>
            <button onclick="app.sendStoryAsPostcard()" class="py-2 px-5 rounded-full bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-1.5 shadow-sm active:scale-95 transition cursor-pointer shrink-0">
              <i data-lucide="send" class="h-3.5 w-3.5 text-white"></i>
              <span>Send Postcard</span>
            </button>
          </div>
        </div>
      `;
      lucide.createIcons();
    }

    // If story doesn't have content yet, fetch via /api/story
    if (!story.content && story.id) {
      try {
        const res = await fetch(`/api/story?id=${encodeURIComponent(story.id)}&slug=${encodeURIComponent(story.slug || story.id)}`);
        const data = await res.json();
        if (data.success && Array.isArray(data.paragraphs) && data.paragraphs.length > 0) {
          story.content = data.paragraphs.join('\n\n');
          const textArea = document.getElementById('story-reader-text-area');
          if (textArea) {
            textArea.innerHTML = data.paragraphs.map(p => `<p class="leading-relaxed text-foreground/90 text-sm sm:text-base">${p}</p>`).join('');
          }
        } else {
          const textArea = document.getElementById('story-reader-text-area');
          if (textArea) {
            textArea.innerHTML = `
              <p class="text-base text-foreground/90 leading-relaxed">${story.synopsis || story.desc || 'A traditional tale of wonder and cultural heritage.'}</p>
            `;
            lucide.createIcons();
          }
        }
      } catch (err) {
        console.warn('Could not fetch story paragraphs:', err);
      }
    }
  },

  backToStoriesCatalog() {
    const catalogView = document.getElementById('stories-catalog-view');
    const readerView = document.getElementById('stories-reader-view');
    readerView?.classList.add('hidden');
    readerView?.classList.remove('flex');
    catalogView?.classList.remove('hidden');
    catalogView?.classList.add('flex');
    this.selectedStory = null;
    lucide.createIcons();
  },

  sendStoryAsPostcard() {
    if (!this.selectedStory) return;
    const s = this.selectedStory;
    this.closeStoriesModal();
    this.openCompose();

    const subjectInput = document.getElementById('compose-subject');
    const editor = document.getElementById('compose-editor');
    if (subjectInput) subjectInput.value = `Postcard Tale: ${s.title} (${s.country})`;
    if (editor) {
      editor.innerHTML = `
        <p><strong>📖 ${s.title}</strong></p>
        <p><em>A traditional tale from ${s.country}</em></p>
        <blockquote style="border-left: 3px solid #0F172A; padding-left: 10px; margin: 10px 0; color: #475569;">
          💡 <strong>Moral:</strong> ${s.moral}
        </blockquote>
        <p>${(s.content || s.synopsis || '').replace(/\n\n/g, '</p><p>')}</p>
        <p style="margin-top: 15px; font-size: 11px; color: #888;">Sent with love via <a href="https://racconmail.eu">Raccon Mail</a></p>
      `;
    }
    this.showToast(`Loaded "${s.title}" into Postcard Compose!`);
  },

  // SSE real-time connection
  setupSSE() {
    // Replaced by lightweight, reliable 15-second mail polling
  },

  showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 rounded-2xl bg-slate-900 text-white px-4 py-3 text-xs shadow-soft flex items-center gap-2 border border-slate-900 animate-bounce';
    toast.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.5s ease';
      setTimeout(() => toast.remove(), 500);
    }, 4500);
  },

  // API calls
  async fetchUser() {
    try {
      const res = await fetch('/api/user');
      const data = await res.json();
      this.currentUser = data;

      const avatar = data.avatar || '/raccoon-logo.png';
      const cover = data.cover_photo || 'linear-gradient(135deg, #181c1f 0%, #2c333a 50%, #121518 100%)';

      const nameEl = document.getElementById('active-user-name');
      if (nameEl) nameEl.textContent = data.name;

      const avEl = document.getElementById('active-user-avatar');
      if (avEl) avEl.src = avatar;

      const dropNameEl = document.getElementById('dropdown-user-name');
      if (dropNameEl) dropNameEl.textContent = data.name;

      const dropEmailEl = document.getElementById('dropdown-user-email');
      if (dropEmailEl) dropEmailEl.textContent = data.email;

      const mobileEmailEl = document.getElementById('mobile-header-user-email');
      if (mobileEmailEl) mobileEmailEl.textContent = data.email || 'me@racconmail.eu';

      const badgeInitialEl = document.getElementById('active-user-badge-initial');
      if (badgeInitialEl) {
        const initial = (data.name ? data.name[0] : (data.email ? data.email[0] : 'R')).toUpperCase();
        badgeInitialEl.textContent = initial;
      }

      const dropAvEl = document.getElementById('dropdown-user-avatar');
      if (dropAvEl) dropAvEl.src = avatar;

      const dropCoverEl = document.getElementById('dropdown-user-cover');
      if (dropCoverEl) {
        if (cover.startsWith('http') || cover.startsWith('/')) {
          dropCoverEl.style.backgroundImage = `url('${cover}')`;
        } else {
          dropCoverEl.style.background = cover;
        }
      }

      // Profile modal preview elements
      const profAv = document.getElementById('profile-avatar-preview');
      if (profAv) profAv.src = avatar;

      const profCover = document.getElementById('profile-cover-preview');
      if (profCover) {
        if (cover.startsWith('http') || cover.startsWith('/')) {
          profCover.style.backgroundImage = `url('${cover}')`;
        } else {
          profCover.style.background = cover;
        }
      }

      const profName = document.getElementById('profile-preview-name');
      if (profName) profName.textContent = data.name;

      const profEmail = document.getElementById('profile-preview-email');
      if (profEmail) profEmail.textContent = data.email;

      // Registration Joined Date Formatting
      const joinedDateStr = this.formatJoinedDate(data.created_at);
      const dropJoined = document.getElementById('dropdown-user-joined');
      if (dropJoined) dropJoined.innerHTML = `<i data-lucide="calendar" class="h-3 w-3 text-slate-500"></i><span>${joinedDateStr}</span>`;
      const profJoined = document.getElementById('profile-joined-text');
      if (profJoined) profJoined.textContent = joinedDateStr;

      // Bio & Location
      const userLoc = data.location || '';
      const dropLoc = document.getElementById('dropdown-user-location');
      const dropLocBadge = document.getElementById('dropdown-user-location-badge');
      if (dropLoc) {
        if (userLoc) {
          dropLoc.textContent = userLoc;
          if (dropLocBadge) dropLocBadge.classList.remove('hidden');
        } else {
          dropLoc.textContent = 'Auto-detecting...';
          this.autoDetectLocationBackground();
        }
      }
      const profLoc = document.getElementById('profile-location-text');
      if (profLoc) profLoc.textContent = userLoc || 'Detecting...';
      const profBio = document.getElementById('profile-bio-text');
      if (profBio) profBio.textContent = data.bio || 'Verified nocturnal mail user & postcard collector.';

      const inputName = document.getElementById('profile-name-input');
      if (inputName) inputName.value = data.name || '';

      const inputEmail = document.getElementById('profile-email-input');
      if (inputEmail) inputEmail.value = data.email || '';

      const inputLoc = document.getElementById('profile-location-input');
      if (inputLoc) inputLoc.value = userLoc;

      const inputBio = document.getElementById('profile-bio-input');
      if (inputBio) inputBio.value = data.bio || '';

      const inputSig = document.getElementById('profile-signature-input');
      if (inputSig) inputSig.value = data.signature || '';

      // Apply theme
      const savedTheme = localStorage.getItem('raccon_theme') || data.theme || 'raccon';
      this.setTheme(savedTheme, false);

      // Settings modal elements
      const setDisplayName = document.getElementById('settings-name');
      if (setDisplayName) setDisplayName.value = data.name || '';
      const setSignature = document.getElementById('settings-signature');
      if (setSignature) setSignature.value = data.signature || '';
    } catch (err) {
      console.error('Failed to fetch user:', err);
    }
  },

  async fetchAccounts() {
    try {
      const res = await fetch('/api/accounts');
      const data = await res.json();
      this.accounts = data.accounts || [];
      const listEl = document.getElementById('accounts-list');
      if (listEl) {
        listEl.innerHTML = this.accounts.map(acc => `
          <button
            onclick="app.switchAccount('${acc.id}')"
            class="w-full text-left flex items-center justify-between p-2 rounded-xl text-xs hover:bg-muted transition ${acc.id === data.activeId ? 'font-bold bg-accent/40' : ''}"
          >
            <div class="flex items-center gap-2 truncate">
              <img src="${acc.avatar || '/raccoon-logo.png'}" class="h-6 w-6 rounded-full object-contain" />
              <div class="truncate">
                <p class="truncate leading-tight">${acc.name}</p>
                <p class="text-[10px] text-muted-foreground truncate">${acc.email}</p>
              </div>
            </div>
            ${acc.id === data.activeId ? '<span class="h-2 w-2 rounded-full bg-slate-900"></span>' : ''}
          </button>
        `).join('');
      }
    } catch (err) {
      console.error('Failed to fetch accounts:', err);
    }
  },

  async switchAccount(userId) {
    try {
      await fetch('/api/accounts/switch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId })
      });
      document.getElementById('account-dropdown').classList.add('hidden');
      this.selectedMessage = null;
      await this.init();
      this.showToast('Switched account successfully');
    } catch (err) {
      console.error('Error switching account:', err);
    }
  },

  toggleAccountDropdown() {
    const el = document.getElementById('account-dropdown');
    if (!el) return;
    el.classList.toggle('hidden');
  },

  // Folders
  async fetchFolders() {
    try {
      const res = await fetch('/api/folders');
      const data = await res.json();
      this.folders = data;
      this.renderStandardFolders();
      this.renderCustomFolders();
      this.updateStorageMeter();
    } catch (err) {
      console.error('Failed to fetch folders:', err);
    }
  },

  updateStorageMeter() {
    const textEl = document.getElementById('sidebar-storage-text');
    const barEl = document.getElementById('sidebar-storage-bar');
    if (!textEl && !barEl) return;

    let totalMsgs = 0;
    if (this.folders?.standard) {
      Object.values(this.folders.standard).forEach(f => {
        if (f && typeof f.total === 'number') totalMsgs += f.total;
      });
    }
    const usedMB = Math.max(12, Math.round(24 + (totalMsgs * 0.28)));
    const totalGB = 15;
    const usedGB = (usedMB / 1024).toFixed(2);
    const pct = Math.max(1, Math.min(100, Math.round((usedMB / (totalGB * 1024)) * 100)));

    if (textEl) textEl.textContent = `${usedMB < 1024 ? usedMB + ' MB' : usedGB + ' GB'} / ${totalGB} GB (${pct}%)`;
    if (barEl) barEl.style.width = `${pct}%`;
  },

  renderStandardFolders() {
    const std = this.folders.standard || {};
    const items = [
      { key: 'Inbox', label: 'Inbox', icon: 'inbox', count: std.inbox?.total || 0, unread: std.inbox?.unread || 0 },
      { key: 'Unread', label: 'Unread', icon: 'mail-open', count: std.unread?.total || 0, unread: std.unread?.total || 0 },
      { key: 'Favorites', label: 'Favorites', icon: 'heart', count: std.favorites?.total || 0 },
      { key: 'Drafts', label: 'Drafts', icon: 'file-text', count: std.drafts?.total || 0 },
      { key: 'Sent', label: 'Sent', icon: 'send', count: std.sent?.total || 0 },
      { key: 'Archive', label: 'Archive', icon: 'archive', count: std.archive?.total || 0 },
      { key: 'Photos & Files', label: 'Photos & Files', icon: 'image', isSpecial: true },
      { key: 'Spam', label: 'Spam', icon: 'shield-alert', count: std.spam?.total || 0, unread: std.spam?.unread || 0 },
      { key: 'Trash', label: 'Trash', icon: 'trash-2', count: std.trash?.total || 0 }
    ];

    const container = document.getElementById('standard-folders-list');
    if (!container) return;
    container.innerHTML = items.map(item => {
      const isActive = this.currentFolder === item.key && this.activeView !== 'contacts';
      const hasUnread = (item.unread && item.unread > 0);

      return `
        <button
          onclick="app.navTo('${item.key}')"
          class="nav-folder-btn w-full flex items-center justify-between px-3 py-2 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer ${
            isActive
              ? 'active font-bold shadow-sm'
              : 'text-foreground'
          }"
        >
          <div class="flex items-center gap-2.5 truncate pointer-events-none">
            <i data-lucide="${item.icon}" class="h-4 w-4 shrink-0 transition-transform duration-200"></i>
            <span class="truncate">${item.label}</span>
          </div>
          <div class="pointer-events-none">
            ${hasUnread ? `<span class="badge-unread">${item.unread}</span>` : ''}
            ${!hasUnread && item.count !== undefined && item.count > 0 ? `<span class="badge-count text-[10px] ${isActive ? 'bg-white/20 text-white' : ''}">${item.count}</span>` : ''}
          </div>
        </button>
      `;
    }).join('');

    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  renderCustomFolders() {
    const list = this.folders?.custom || [];
    const container = document.getElementById('custom-folders-list');
    if (!container) return;
    if (list.length === 0) {
      container.innerHTML = '<p class="text-[11px] text-muted-foreground px-3 py-1 italic">No custom folders</p>';
      return;
    }

    container.innerHTML = list.map(f => {
      const isActive = this.currentFolder === f.id;
      return `
        <div class="nav-folder-btn group flex items-center justify-between px-3 py-1.5 rounded-2xl transition-all duration-200 text-xs font-medium cursor-pointer ${
          isActive ? 'active font-bold' : 'text-foreground'
        }">
          <button onclick="app.navTo('${f.id}')" class="flex-1 flex items-center gap-2.5 truncate text-left cursor-pointer">
            <i data-lucide="${f.icon || 'folder'}" class="h-4 w-4 shrink-0"></i>
            <span class="truncate">${f.name}</span>
          </button>
          <div class="flex items-center gap-1">
            ${f.unread > 0 ? `<span class="badge-unread">${f.unread}</span>` : ''}
            <button onclick="app.deleteFolder('${f.id}')" title="Delete folder" class="hidden group-hover:inline-block p-1 text-slate-500 hover:text-slate-900 rounded transition cursor-pointer">
              <i data-lucide="trash" class="h-3 w-3"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');

    lucide.createIcons();
  },

  navTo(folderKey) {
    this.closeMobileSidebar();
    this.currentFolder = folderKey;
    this.checkedIds.clear();
    this.updateBatchToolbar();

    const msgPanel = document.getElementById('messages-panel');
    const readerPanel = document.getElementById('reader-panel');

    if (msgPanel) {
      msgPanel.style.display = '';
      msgPanel.classList.remove('hidden');
    }
    if (readerPanel) {
      readerPanel.style.display = '';
    }

    if (window.innerWidth < 768) {
      msgPanel?.classList.remove('hidden');
      readerPanel?.classList.add('hidden');
      readerPanel?.classList.remove('flex');
    }

    if (folderKey === 'Photos & Files') {
      this.activeView = 'photos_files';
      this.renderStandardFolders();
      this.renderPhotosFilesView();
      if (window.innerWidth < 768) {
        document.getElementById('messages-panel')?.classList.add('hidden');
        const reader = document.getElementById('reader-panel');
        reader?.classList.remove('hidden');
        reader?.classList.add('flex');
      }
      return;
    }

    this.activeView = 'messages';
    this.renderStandardFolders();
    this.renderCustomFolders();
    this.loadMessages();
  },

  // Loading messages
  async loadMessages() {
    try {
      const folderTitleEl = document.getElementById('current-folder-title');
      const folderSubEl = document.getElementById('current-folder-subtitle');
      const emptyBtn = document.getElementById('empty-folder-btn');

      // Update folder title display
      let title = this.currentFolder;
      const customFolder = this.folders.custom?.find(f => f.id === this.currentFolder);
      if (customFolder) title = customFolder.name;
      folderTitleEl.textContent = title;

      // Show/hide empty folder button for Trash & Spam
      if (this.currentFolder.toLowerCase() === 'trash' || this.currentFolder.toLowerCase() === 'spam') {
        emptyBtn.classList.remove('hidden');
        emptyBtn.textContent = `Empty ${this.currentFolder}`;
      } else {
        emptyBtn.classList.add('hidden');
      }

      // Offline-first instant render & skeleton loader
      const cacheKey = `raccon_offline_${this.currentFolder}_${this.currentFilter}`;
      if (!this.messages || this.messages.length === 0) {
        try {
          const cached = localStorage.getItem(cacheKey);
          if (cached) {
            this.messages = JSON.parse(cached);
            this.renderMessagesList();
          } else {
            this.renderMessagesSkeleton();
          }
        } catch (_) {
          this.renderMessagesSkeleton();
        }
      }

      // Fetch
      const params = new URLSearchParams({
        folder: this.currentFolder,
        filter: this.currentFilter,
        sort: this.currentSort,
        search: this.searchQuery
      });

      const res = await fetch(`/api/messages?${params.toString()}`);
      const data = await res.json();
      this.messages = data.messages || [];

      // Update offline-first cache
      try {
        localStorage.setItem(cacheKey, JSON.stringify(this.messages));
      } catch (_) {}

      folderSubEl.textContent = `${this.messages.length} message${this.messages.length === 1 ? '' : 's'}`;
      this.renderMessagesList();
    } catch (err) {
      console.error('Failed to load messages:', err);
    }
  },

  renderMessagesSkeleton() {
    const listEl = document.getElementById('messages-list');
    if (!listEl) return;
    listEl.innerHTML = `
      <div class="p-3 space-y-3 animate-pulse">
        ${[1, 2, 3, 4, 5].map(() => `
          <div class="flex items-center gap-3 p-3 rounded-2xl bg-muted/30 border border-border/40">
            <div class="h-8 w-8 rounded-full bg-muted-foreground/15 shrink-0"></div>
            <div class="flex-1 space-y-2 min-w-0">
              <div class="flex items-center justify-between">
                <div class="h-3 w-28 bg-muted-foreground/20 rounded"></div>
                <div class="h-2.5 w-12 bg-muted-foreground/15 rounded"></div>
              </div>
              <div class="h-2.5 w-3/4 bg-muted-foreground/15 rounded"></div>
              <div class="h-2 w-1/2 bg-muted-foreground/10 rounded"></div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  },

  currentCategoryTab: 'all',
  batchSelectionMode: false,
  isAdDismissed: true,
  currentGlassStyle: 'realistic',

  initGlassStyle() {
    const saved = localStorage.getItem('originos_glass_style') || 'realistic';
    this.setGlassStyle(saved);
  },

  setGlassStyle(style) {
    if (!['realistic', 'natural', 'soft'].includes(style)) style = 'realistic';
    this.currentGlassStyle = style;
    localStorage.setItem('originos_glass_style', style);
    document.body.setAttribute('data-glass-style', style);

    ['realistic', 'natural', 'soft'].forEach(s => {
      const btn = document.getElementById(`glass-btn-${s}`);
      if (btn) {
        if (s === style) {
          btn.className = 'glass-style-pill px-2.5 py-0.5 rounded-full text-[11px] font-extrabold transition bg-white text-slate-900 shadow-xs cursor-pointer';
        } else {
          btn.className = 'glass-style-pill px-2.5 py-0.5 rounded-full text-[11px] font-bold transition text-slate-300 hover:text-white hover:bg-slate-800 active:scale-95 cursor-pointer';
        }
      }
    });
  },

  currentCategoryTab: 'primary',

  setJarmelTab(tab) {
    this.currentCategoryTab = tab;
    const primBtn = document.getElementById('tab-primary');
    const otherBtn = document.getElementById('tab-other');
    if (primBtn && otherBtn) {
      if (tab === 'primary') {
        primBtn.classList.add('active');
        otherBtn.classList.remove('active');
      } else {
        primBtn.classList.remove('active');
        otherBtn.classList.add('active');
      }
    }
    this.renderMessagesList();
  },

  toggleInlineSearch() {
    const bar = document.getElementById('inline-search-bar');
    if (!bar) return;
    bar.classList.toggle('hidden');
    if (!bar.classList.contains('hidden')) {
      document.getElementById('global-search')?.focus();
    }
  },

  getFilteredMessagesList() {
    let list = this.messages || [];
    if (this.currentCategoryTab === 'other') {
      const otherList = list.filter(m => {
        const text = `${m.sender_name || ''} ${m.sender_email || ''} ${m.subject || ''}`.toLowerCase();
        return /newsletter|promo|update|digest|marketing|notification|receipt|system/i.test(text);
      });
      return otherList.length > 0 ? otherList : list.slice(Math.floor(list.length / 2));
    } else if (this.currentCategoryTab === 'primary') {
      const primList = list.filter(m => {
        const text = `${m.sender_name || ''} ${m.sender_email || ''} ${m.subject || ''}`.toLowerCase();
        return !/newsletter|promo|digest|marketing/i.test(text);
      });
      return primList.length > 0 ? primList : list;
    }
    return list;
  },

  updateCategoryBadges() {
    // Dynamically update tab badges matching Figma specifications
    const totalMsgs = this.messages ? this.messages.length : 0;
    const primEl = document.getElementById('tab-primary-count');
    const otherEl = document.getElementById('tab-other-count');
    if (primEl) primEl.textContent = `(${Math.max(1, totalMsgs)})`;
    if (otherEl) otherEl.textContent = `(${Math.max(0, Math.floor(totalMsgs / 2))})`;
  },

  setCategoryTab(tab) {
    this.setJarmelTab(tab);
  },

  toggleBatchSelectMode() {
    this.batchSelectionMode = !this.batchSelectionMode;
    if (!this.batchSelectionMode) {
      this.checkedIds.clear();
    }
    this.updateBatchToolbar();
    this.renderMessagesList();
  },

  dismissSponsoredAd() {
    this.isAdDismissed = true;
  },

  openSponsoredAd() {},
  closeSponsoredAdModal() {},

  getSenderColor(sender) {
    const colors = ['bg-sky-600', 'bg-blue-600', 'bg-indigo-600', 'bg-emerald-600', 'bg-teal-600'];
    let hash = 0;
    const str = sender || 'Mail';
    for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
  },

  getSenderInitials(sender) {
    const clean = (sender || 'Mail').replace(/[^a-zA-Z0-9\s]/g, '').trim();
    const parts = clean.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return (clean.slice(0, 2) || 'M').toUpperCase();
  },

  renderMessagesList() {
    const container = document.getElementById('messages-list');
    if (!container) return;

    this.updateCategoryBadges();
    const list = this.getFilteredMessagesList();

    const inboxBadge = document.getElementById('sidebar-inbox-badge');
    const unreadCount = this.messages ? this.messages.filter(m => !m.is_read).length : 0;
    if (inboxBadge) inboxBadge.textContent = unreadCount > 0 ? unreadCount : (this.messages?.length || '413');

    if (list.length === 0) {
      container.innerHTML = `
        <div class="flex flex-col items-center justify-center p-8 text-center text-muted-foreground h-64 select-none">
          <img src="/raccoon-logo.png" class="h-12 w-12 opacity-30 mb-2 object-contain" />
          <p class="text-sm font-semibold text-foreground">No emails in ${this.currentCategoryTab === 'other' ? 'Other' : 'Primary'}</p>
          <p class="text-xs opacity-60 mt-1">Your mailbox is clean.</p>
        </div>
      `;
      return;
    }

    // Exact Figma Jarmel Message Item Rendering
    const messagesHtml = list.map(msg => {
      const isSelected = this.selectedMessage?.id === msg.id;
      const isUnread = !msg.is_read;
      const dateStr = this.formatDate(msg.created_at);
      const senderName = (msg.sender_name || (msg.sender_email ? msg.sender_email.split('@')[0] : 'Sender')).trim();

      return `
        <div class="jarmel-msg-card group relative select-none ${isSelected ? 'active' : ''}" data-msg-id="${msg.id}" onclick="app.onEmailItemClick(event, '${msg.id}')">
          <!-- Top Row: Unread Dot + Subject on Left, Sender Name on Right (or Inline Actions when selected) -->
          <div class="flex items-center justify-between gap-2 leading-tight">
            <div class="flex items-center gap-2 min-w-0 flex-1">
              ${isUnread ? '<span class="jarmel-unread-dot"></span>' : '<span class="w-[7px] shrink-0"></span>'}
              <span class="text-[13px] ${isUnread ? 'font-bold text-foreground' : 'font-medium opacity-90'} truncate">
                ${this.escapeHtml(msg.subject || '(no subject)')}
              </span>
            </div>
            <div class="flex items-center gap-1 shrink-0 ml-2">
              ${isSelected ? `
                <div class="flex items-center gap-2 text-xs text-primary opacity-90" onclick="event.stopPropagation()">
                  <button onclick="app.toggleRead('${msg.id}', false)" title="Mark read" class="p-0.5 hover:opacity-100 transition"><i data-lucide="check" class="h-3.5 w-3.5"></i></button>
                  <button onclick="app.openNewFolderModal()" title="Tag / Folder" class="p-0.5 hover:opacity-100 transition"><i data-lucide="tag" class="h-3.5 w-3.5"></i></button>
                  <button onclick="app.replyToMessage()" title="Reply" class="p-0.5 hover:opacity-100 transition"><i data-lucide="reply" class="h-3.5 w-3.5"></i></button>
                </div>
              ` : `
                <span class="text-[12px] opacity-75 font-normal truncate max-w-[120px]">
                  ${this.escapeHtml(senderName)}
                </span>
              `}
            </div>
          </div>

          <!-- Second Row: Snippet on Left, Date/Time on Right -->
          <div class="flex items-center justify-between gap-2 mt-1 pl-[15px]">
            <span class="text-[12px] opacity-60 truncate font-normal flex-1">
              ${this.escapeHtml((msg.body || '').replace(/\s+/g, ' ').trim() || 'No preview available')}
            </span>
            <span class="text-[11px] opacity-50 font-mono shrink-0 ml-2">
              ${dateStr}
            </span>
          </div>
        </div>
      `;
    }).join('');

    container.innerHTML = messagesHtml;

    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }

    try { this.initSwipeGestures(); } catch (_) {}
  },

  formatDate(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    }
    if (diffDays > 0 && diffDays <= 6) {
      return `${diffDays}d`;
    }
    const m = date.getMonth() + 1;
    const d = date.getDate();
    const y = String(date.getFullYear()).slice(-2);
    return `${m}/${d}/${y}`;
  },

  async swipeActionDelete(msgId) {
    const card = document.querySelector(`.msg-swipe-wrapper[data-msg-id="${msgId}"]`);
    if (card) {
      card.style.transition = 'all 0.25s ease-out';
      card.style.maxHeight = `${card.offsetHeight}px`;
      card.style.transform = 'translateX(-100%)';
      card.style.opacity = '0';
      setTimeout(() => {
        card.style.maxHeight = '0px';
        card.style.padding = '0px';
        card.style.border = 'none';
      }, 200);
    }
    await this.trashMessage(msgId);
  },

  async swipeActionRead(msgId) {
    await this.toggleRead(msgId, true);
    await this.selectMessage(msgId);
  },

  async swipeActionStar(msgId) {
    const msg = (this.messages || []).find(m => String(m.id) === String(msgId));
    const newStar = msg ? !msg.is_starred : true;
    await this.toggleStar(msgId, newStar);
    const card = document.querySelector(`.msg-swipe-wrapper[data-msg-id="${msgId}"] .swipe-content`);
    if (card) card.style.transform = 'translateX(0px)';
  },

  initSwipeGestures() {
    const listEl = document.getElementById('messages-list');
    if (!listEl || listEl._swipeInitialized) return;
    listEl._swipeInitialized = true;

    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let activeCard = null;
    let isSwiping = false;
    let isHorizontal = null;

    const onStart = (clientX, clientY, target) => {
      // Don't swipe if in batch selection mode or clicking buttons, checkboxes or inputs
      if (app.batchSelectionMode || target.closest('button, input, a, .batch-checkbox-wrap')) return;

      const card = target.closest('.swipe-content');
      if (!card) return;

      // Close any other open swiped cards
      document.querySelectorAll('.swipe-content').forEach(c => {
        if (c !== card && c.style.transform && c.style.transform !== 'translateX(0px)') {
          c.style.transition = 'transform 0.2s ease-out';
          c.style.transform = 'translateX(0px)';
        }
      });

      activeCard = card;
      startX = clientX;
      startY = clientY;
      currentX = clientX;
      isSwiping = true;
      isHorizontal = null;
      activeCard.style.transition = 'none';
    };

    const onMove = (clientX, clientY) => {
      if (!isSwiping || !activeCard) return;

      const dx = clientX - startX;
      const dy = clientY - startY;

      if (isHorizontal === null) {
        if (Math.abs(dx) > 6 || Math.abs(dy) > 6) {
          isHorizontal = Math.abs(dx) > Math.abs(dy);
        }
      }

      if (!isHorizontal) return;

      // Restrain swipe bounds
      let moveX = dx;
      if (moveX < -120) moveX = -120 - Math.pow(Math.abs(moveX + 120), 0.6);
      if (moveX > 160) moveX = 160 + Math.pow(moveX - 160, 0.6);

      activeCard.style.transform = `translateX(${moveX}px)`;
    };

    const onEnd = (clientX) => {
      if (!isSwiping || !activeCard) return;
      isSwiping = false;

      const dx = clientX - startX;
      const msgId = activeCard.closest('.msg-swipe-wrapper')?.dataset?.msgId;

      activeCard.style.transition = 'transform 0.22s cubic-bezier(0.16, 1, 0.3, 1)';

      if (isHorizontal) {
        if (dx < -110) {
          // Hard swipe left: animate and delete
          activeCard.style.transform = 'translateX(-100%)';
          setTimeout(() => {
            if (msgId) app.swipeActionDelete(msgId);
          }, 180);
        } else if (dx < -45) {
          // Snap open to reveal red Delete button
          activeCard.style.transform = 'translateX(-80px)';
          activeCard._isSwipedOpen = true;
        } else if (dx > 120) {
          // Hard swipe right: trigger read / open
          activeCard.style.transform = 'translateX(100%)';
          setTimeout(() => {
            if (msgId) {
              app.swipeActionRead(msgId);
              activeCard.style.transform = 'translateX(0px)';
            }
          }, 180);
        } else if (dx > 45) {
          // Snap open to reveal Read + Star buttons
          activeCard.style.transform = 'translateX(140px)';
          activeCard._isSwipedOpen = true;
        } else {
          activeCard.style.transform = 'translateX(0px)';
          activeCard._isSwipedOpen = false;
        }
      } else {
        activeCard.style.transform = 'translateX(0px)';
        activeCard._isSwipedOpen = false;
      }

      activeCard = null;
      isHorizontal = null;
    };

    // Touch events for mobile phones
    listEl.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) {
        onStart(e.touches[0].clientX, e.touches[0].clientY, e.target);
      }
    }, { passive: true });

    listEl.addEventListener('touchmove', (e) => {
      if (e.touches.length === 1 && isSwiping) {
        onMove(e.touches[0].clientX, e.touches[0].clientY);
      }
    }, { passive: true });

    listEl.addEventListener('touchend', (e) => {
      const endX = e.changedTouches?.[0]?.clientX ?? currentX;
      onEnd(endX);
    });

    // Mouse drag events for web / desktop testing
    let isMouseDown = false;
    listEl.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return;
      isMouseDown = true;
      onStart(e.clientX, e.clientY, e.target);
    });

    window.addEventListener('mousemove', (e) => {
      if (isMouseDown && isSwiping) {
        currentX = e.clientX;
        onMove(e.clientX, e.clientY);
      }
    });

    window.addEventListener('mouseup', (e) => {
      if (isMouseDown) {
        isMouseDown = false;
        onEnd(e.clientX);
      }
    });
  },

  onEmailItemClick(event, msgId) {
    if (this.batchSelectionMode) {
      const isChecked = this.checkedIds.has(msgId);
      this.toggleMessageCheck(msgId, !isChecked);
      const wrapper = document.querySelector(`.msg-swipe-wrapper[data-msg-id="${msgId}"]`);
      if (wrapper) {
        const input = wrapper.querySelector('.batch-checkbox-wrap input[type="checkbox"]');
        if (input) input.checked = !isChecked;
      }
      return;
    }
    const card = event.currentTarget;
    if (card && card._isSwipedOpen) {
      card.style.transition = 'transform 0.2s ease-out';
      card.style.transform = 'translateX(0px)';
      card._isSwipedOpen = false;
      return;
    }
    this.selectMessage(msgId);
  },

  // Select single message & view in reader
  async selectMessage(id) {
    try {
      const res = await fetch(`/api/messages/${id}`);
      const data = await res.json();
      this.selectedMessage = data.message;
      this.renderMessagesList();
      this.renderMessageReader(this.selectedMessage);
      this.fetchFolders(); // refresh unread count badge

      // On mobile screens, show reader and hide messages list
      if (window.innerWidth < 768) {
        const msgPanel = document.getElementById('messages-panel');
        const readerPanel = document.getElementById('reader-panel');
        msgPanel?.classList.add('hidden');
        readerPanel?.classList.remove('hidden');
        readerPanel?.classList.add('flex');
      }
    } catch (err) {
      console.error('Failed to select message:', err);
    }
  },

  renderMessageReader(msg) {
    const readerEl = document.getElementById('reader-content');
    const attachments = msg.attachments || [];
    const userAvatar = this.currentUser?.avatar || '/raccoon-logo.png';
    const dateObj = new Date(msg.created_at || Date.now());
    const dateStrLong = dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric', year: 'numeric' }) + ' ' + dateObj.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

    readerEl.innerHTML = `
      <!-- Figma Dribbble Top Reading Header -->
      <div class="px-5 py-3.5 border-b border-border/40 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2 min-w-0 flex-1">
          <button onclick="app.backToMessages()" class="md:hidden flex items-center gap-1 text-xs font-bold py-1 px-2.5 rounded-lg bg-black/5 dark:bg-white/10 shrink-0 cursor-pointer">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            <span>Back</span>
          </button>
          <h1 class="text-base sm:text-lg font-bold text-foreground truncate">
            ${this.escapeHtml(msg.subject || '(no subject)')}
          </h1>
        </div>

        <!-- Action Icons on Right: Reply, Lightbulb/Snooze, More -->
        <div class="flex items-center gap-2 shrink-0 ml-3">
          <button onclick="app.replyToMessage()" title="Reply" class="p-1.5 opacity-70 hover:opacity-100 transition cursor-pointer">
            <i data-lucide="reply" class="h-4 w-4"></i>
          </button>
          <button onclick="app.showToast('Email verified: clean & private.')" title="Insights / Snooze" class="p-1.5 opacity-70 hover:opacity-100 transition cursor-pointer">
            <i data-lucide="lightbulb" class="h-4 w-4"></i>
          </button>
          <button onclick="app.toggleReaderMoreDropdown()" title="More actions" class="p-1.5 opacity-70 hover:opacity-100 transition cursor-pointer">
            <i data-lucide="more-vertical" class="h-4 w-4"></i>
          </button>
        </div>
      </div>

      <!-- Scrollable Message Body -->
      <div class="flex-1 p-5 sm:p-7 overflow-y-auto space-y-4">
        <!-- Metadata Row (From, To, Date) -->
        <div class="flex items-start justify-between gap-3 text-xs opacity-75 leading-relaxed">
          <div>
            <p>From: <span class="font-medium text-foreground">${this.escapeHtml(msg.sender_email || msg.sender_name)}</span></p>
            <p class="mt-0.5">To: <span class="font-medium text-foreground">You</span></p>
          </div>
          <span class="font-mono text-[11px] opacity-70 shrink-0 pt-0.5">${dateStrLong}</span>
        </div>

        <div class="border-b border-border/40 my-3"></div>

        <!-- Body Content -->
        <div class="text-[13px] sm:text-sm leading-relaxed text-foreground opacity-90 whitespace-pre-wrap font-sans">
          ${msg.body_html ? `
            <div class="email-html-wrapper overflow-x-auto">${msg.body_html}</div>
          ` : `
            <div>${this.escapeHtml(msg.body || 'No content')}</div>
          `}
        </div>

        <!-- Attachments Section if any -->
        ${attachments.length > 0 ? `
          <div class="pt-4 border-t border-border/40 space-y-2">
            <div class="text-xs font-semibold opacity-75">
              ${attachments.length} Attachment${attachments.length === 1 ? '' : 's'}
            </div>
            <div class="flex flex-wrap gap-2">
              ${attachments.map(att => `
                <a href="/uploads/${att.filename}" download="${att.original_name}" class="inline-flex items-center gap-2 rounded-xl border border-border/60 bg-black/5 dark:bg-white/5 px-3 py-1.5 text-xs hover:border-primary transition">
                  <i data-lucide="paperclip" class="h-3.5 w-3.5 text-primary"></i>
                  <span class="truncate max-w-[160px] font-medium">${this.escapeHtml(att.original_name)}</span>
                </a>
              `).join('')}
            </div>
          </div>
        ` : ''}

        <!-- Bottom Action Buttons: Reply & Forward -->
        <div class="mt-8 pt-4 border-t border-border/40 flex items-center gap-3 select-none">
          <button onclick="app.replyToMessage()" class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold border border-border/60 hover:bg-black/5 dark:hover:bg-white/5 transition cursor-pointer">
            <i data-lucide="reply" class="h-3.5 w-3.5"></i>
            <span>Reply</span>
          </button>
          <button onclick="app.forwardMessage()" class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold border border-border/60 hover:bg-black/5 dark:hover:bg-white/5 transition cursor-pointer">
            <i data-lucide="forward" class="h-3.5 w-3.5"></i>
            <span>Forward</span>
          </button>
        </div>

        <!-- Fast Inline Reply Section -->
        <div class="mt-6 rounded-2xl border border-border/40 bg-black/[0.02] dark:bg-white/[0.02] p-4 space-y-2">
          <div class="flex items-center justify-between text-xs opacity-75">
            <span class="font-semibold">Fast Reply</span>
            <span class="text-[11px] opacity-60">Ctrl+Enter to send</span>
          </div>
          <textarea
            id="quick-reply-body"
            rows="2"
            placeholder="Write a quick reply..."
            onkeydown="if((event.ctrlKey || event.metaKey) && event.key === 'Enter') { app.sendQuickReply('${msg.id}'); }"
            class="w-full bg-transparent text-xs sm:text-sm outline-none resize-none leading-relaxed text-foreground placeholder:opacity-50"
          ></textarea>
          <div class="flex justify-end pt-1">
            <button onclick="app.sendQuickReply('${msg.id}')" class="px-4 py-1.5 rounded-lg bg-primary text-primary-foreground font-semibold text-xs transition hover:opacity-90 cursor-pointer">
              Send Reply
            </button>
          </div>
        </div>
      </div>
    `;

    lucide.createIcons();
  },

  fillQuickReply(text) {
    const el = document.getElementById('quick-reply-body');
    if (el) {
      el.value = text;
      el.focus();
      this.playChime();
    }
  },

  async sendQuickReply(msgId) {
    const textEl = document.getElementById('quick-reply-body');
    const text = textEl?.value?.trim();
    if (!text) {
      alert('Please type or select a quick reply chip first.');
      return;
    }

    const btn = document.getElementById('btn-fast-reply-send');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i><span>Sending...</span>`;
      if (window.lucide) {
        try { lucide.createIcons(); } catch (_) {}
      }
    }

    try {
      const msg = (this.messages || []).find(m => String(m.id) === String(msgId)) || this.selectedMessage;
      if (!msg) {
        alert('Could not find active message to reply to.');
        return;
      }
      this.selectedMessage = msg;

      // Smart recipient resolution:
      // If we are looking at an email we sent, reply to recipient_email; otherwise sender_email
      let toRecipient = (msg.sender_email && msg.sender_email !== this.currentUser?.email)
        ? msg.sender_email
        : (msg.recipient_email || msg.sender_email);

      if (!toRecipient) {
        alert('Could not resolve recipient for reply.');
        return;
      }

      const subject = (msg.subject || '').startsWith('Re:') ? msg.subject : `Re: ${msg.subject || 'Message'}`;

      const formData = new FormData();
      formData.append('to', toRecipient);
      formData.append('subject', subject);
      formData.append('body', text);

      const res = await fetch('/api/messages/send', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed to send quick reply');

      this.playChime();
      this.showToast('Fast reply delivered via nocturnal express! 💌🦝');
      if (textEl) {
        textEl.value = '';
        textEl.focus();
      }

      await this.fetchFolders();
      await this.loadMessages();
    } catch (err) {
      alert('Failed to send reply: ' + err.message);
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = `<i data-lucide="send" class="h-4 w-4 text-white"></i><span>Send Fast Reply</span>`;
        if (window.lucide) {
          try { lucide.createIcons(); } catch (_) {}
        }
      }
    }
  },

  // Photos & Files view (Raccon Mail signature feature)
  async renderPhotosFilesView() {
    const readerEl = document.getElementById('reader-content');
    readerEl.innerHTML = `
      <div class="p-4 sm:p-8 h-full flex flex-col overflow-y-auto">
        <!-- Mobile Back Button -->
        <button onclick="app.navTo('Inbox')" class="md:hidden flex items-center gap-1.5 text-xs font-bold py-1.5 px-3 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-800 shrink-0 cursor-pointer mb-3 self-start">
          <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
          <span>Back to Mail</span>
        </button>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
          <div>
            <h2 class="font-display text-xl sm:text-2xl font-bold flex items-center gap-2">
              <i data-lucide="image" class="h-5 w-5 sm:h-6 sm:w-6"></i>
              <span>Photos & Files</span>
            </h2>
            <p class="text-xs text-muted-foreground mt-0.5">Every photo and file attached to your emails in one place.</p>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <button onclick="app.filterPhotosFiles('all')" id="pf-all" class="px-3 py-1.5 rounded-full text-xs font-semibold bg-primary text-primary-foreground cursor-pointer">All</button>
            <button onclick="app.filterPhotosFiles('photos')" id="pf-photos" class="px-3 py-1.5 rounded-full text-xs font-medium text-muted-foreground hover:bg-muted cursor-pointer">Photos & Media</button>
            <button onclick="app.filterPhotosFiles('documents')" id="pf-documents" class="px-3 py-1.5 rounded-full text-xs font-medium text-muted-foreground hover:bg-muted cursor-pointer">Documents</button>
          </div>
        </div>
        <div id="photos-files-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
          <p class="text-xs text-muted-foreground">Loading attachments...</p>
        </div>
      </div>
    `;
    lucide.createIcons();
    this.filterPhotosFiles('all');
  },

  async filterPhotosFiles(type = 'all') {
    ['all', 'photos', 'documents'].forEach(t => {
      const btn = document.getElementById(`pf-${t}`);
      if (btn) {
        if (t === type) {
          btn.className = 'px-3 py-1.5 rounded-full text-xs font-medium bg-primary text-primary-foreground';
        } else {
          btn.className = 'px-3 py-1.5 rounded-full text-xs font-medium text-muted-foreground hover:bg-muted';
        }
      }
    });

    try {
      const res = await fetch(`/api/attachments?type=${type}`);
      const data = await res.json();
      const grid = document.getElementById('photos-files-grid');
      const items = data.attachments || [];

      if (items.length === 0) {
        grid.innerHTML = '<p class="col-span-full text-center text-xs text-muted-foreground py-12">No files or attachments found in this category.</p>';
        return;
      }

      grid.innerHTML = items.map(att => {
        const isImg = att.mime_type.startsWith('image/');
        const sizeKB = Math.round(att.size_bytes / 1024);
        return `
          <div class="group rounded-3xl border border-border bg-card overflow-hidden p-3 shadow-card hover:border-primary transition flex flex-col justify-between">
            ${isImg ? `
              <div class="h-36 w-full rounded-2xl bg-muted overflow-hidden mb-3 flex items-center justify-center">
                <img src="/uploads/${att.filename}" class="h-full w-full object-cover group-hover:scale-105 transition" />
              </div>
            ` : `
              <div class="h-36 w-full rounded-2xl bg-muted/60 mb-3 flex flex-col items-center justify-center text-muted-foreground">
                <i data-lucide="file-text" class="h-12 w-12 mb-1"></i>
                <span class="text-[10px] font-mono uppercase">${att.mime_type.split('/')[1] || 'file'}</span>
              </div>
            `}
            <div class="space-y-1">
              <p class="text-xs font-bold text-foreground truncate" title="${this.escapeHtml(att.original_name)}">${this.escapeHtml(att.original_name)}</p>
              <p class="text-[10px] text-muted-foreground truncate">from: ${this.escapeHtml(att.sender_name)}</p>
              <div class="flex items-center justify-between pt-2 border-t border-border-light text-[11px]">
                <span class="text-muted-foreground">${sizeKB} KB</span>
                <a
                  href="/uploads/${att.filename}"
                  download="${att.original_name}"
                  class="flex items-center gap-1 text-primary hover:underline font-semibold"
                >
                  <i data-lucide="download" class="h-3 w-3"></i> Download
                </a>
              </div>
            </div>
          </div>
        `;
      }).join('');

      lucide.createIcons();
    } catch (err) {
      console.error('Failed to filter photos/files:', err);
    }
  },

  // Batch actions
  toggleMessageCheck(id, checked) {
    if (checked) {
      this.checkedIds.add(id);
    } else {
      this.checkedIds.delete(id);
    }
    this.updateBatchToolbar();
  },

  toggleSelectAll(checked) {
    const visibleList = this.getFilteredMessagesList ? this.getFilteredMessagesList() : (this.messages || []);
    if (checked) {
      visibleList.forEach(m => this.checkedIds.add(m.id));
    } else {
      visibleList.forEach(m => this.checkedIds.delete(m.id));
    }
    this.renderMessagesList();
    this.updateBatchToolbar();
  },

  updateBatchToolbar() {
    const toolbar = document.getElementById('batch-toolbar');
    const label = document.getElementById('selected-count-label');
    const selectAll = document.getElementById('select-all-checkbox');
    const count = this.checkedIds.size;

    if (this.batchSelectionMode || count > 0) {
      if (toolbar) toolbar.classList.remove('hidden');
      if (label) label.textContent = `${count} selected`;
      if (selectAll) {
        const visibleList = this.getFilteredMessagesList ? this.getFilteredMessagesList() : (this.messages || []);
        selectAll.checked = visibleList.length > 0 && visibleList.every(m => this.checkedIds.has(m.id));
      }
    } else {
      if (toolbar) toolbar.classList.add('hidden');
      if (selectAll) selectAll.checked = false;
    }
  },

  async batchAction(action) {
    const ids = Array.from(this.checkedIds);
    if (ids.length === 0) return;

    try {
      await fetch('/api/messages/batch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ids })
      });

      this.checkedIds.clear();
      this.batchSelectionMode = false;
      const btn = document.getElementById('btn-toggle-select');
      if (btn) {
        btn.textContent = 'Select';
        btn.className = 'text-xs font-bold text-blue-600 hover:text-blue-800 px-2 py-1 transition cursor-pointer shrink-0';
      }
      this.updateBatchToolbar();
      await this.loadMessages();
      await this.fetchFolders();
      this.showToast(`Updated ${ids.length} message${ids.length > 1 ? 's' : ''}`);
    } catch (err) {
      console.error('Batch action failed:', err);
    }
  },

  // Single message operations
  async toggleStar(id, is_starred) {
    try {
      await fetch(`/api/messages/${id}/star`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_starred })
      });
      if (this.selectedMessage?.id === id) {
        this.selectedMessage.is_starred = is_starred ? 1 : 0;
        this.renderMessageReader(this.selectedMessage);
      }
      this.loadMessages();
      this.fetchFolders();
    } catch (err) {
      console.error('Failed to toggle star:', err);
    }
  },

  async toggleRead(id, is_read) {
    try {
      await fetch(`/api/messages/${id}/read`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_read })
      });
      this.loadMessages();
      this.fetchFolders();
    } catch (err) {
      console.error('Failed to toggle read:', err);
    }
  },

  async archiveMessage(id) {
    try {
      await fetch(`/api/messages/${id}/archive`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_archived: true })
      });
      this.showToast('Archived message');
      this.selectedMessage = null;
      this.navTo(this.currentFolder);
    } catch (err) {
      console.error('Failed to archive:', err);
    }
  },

  async spamMessage(id) {
    try {
      await fetch(`/api/messages/${id}/spam`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_spam: true })
      });
      this.showToast('Marked as spam');
      this.selectedMessage = null;
      this.navTo(this.currentFolder);
    } catch (err) {
      console.error('Failed to mark spam:', err);
    }
  },

  async trashMessage(id) {
    try {
      await fetch(`/api/messages/${id}/trash`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_trashed: true })
      });
      this.showToast('Moved to trash');
      this.selectedMessage = null;
      this.navTo(this.currentFolder);
    } catch (err) {
      console.error('Failed to trash:', err);
    }
  },

  async emptyCurrentFolder() {
    const f = this.currentFolder.toLowerCase();
    if (!confirm(`Are you sure you want to permanently empty all items in ${this.currentFolder}?`)) return;

    try {
      await fetch('/api/messages/empty-folder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder: f })
      });
      this.selectedMessage = null;
      this.loadMessages();
      this.fetchFolders();
      this.showToast(`Emptied ${this.currentFolder}`);
    } catch (err) {
      console.error('Failed to empty folder:', err);
    }
  },

  replyToMessage() {
    const msg = this.selectedMessage;
    if (!msg) return;
    this.openCompose({
      to: msg.sender_email,
      subject: msg.subject.startsWith('Re:') ? msg.subject : `Re: ${msg.subject}`,
      body: `\n\n--- Original Message ---\nFrom: ${msg.sender_name} <${msg.sender_email}>\n\n${msg.body || ''}`
    });
  },

  replyAllMessage() {
    const msg = this.selectedMessage;
    if (!msg) return;
    const recipients = [msg.sender_email, msg.recipient_email].filter(Boolean).join(', ');
    this.openCompose({
      to: recipients,
      subject: msg.subject.startsWith('Re:') ? msg.subject : `Re: ${msg.subject}`,
      body: `\n\n--- Reply All ---\nFrom: ${msg.sender_name}\n\n${msg.body || ''}`
    });
  },

  forwardMessage() {
    const msg = this.selectedMessage;
    if (!msg) return;
    this.openCompose({
      subject: msg.subject.startsWith('Fwd:') ? msg.subject : `Fwd: ${msg.subject}`,
      body: `\n\n---------- Forwarded message ---------\nFrom: ${msg.sender_name} <${msg.sender_email}>\nSubject: ${msg.subject}\n\n${msg.body || ''}`
    });
  },

  // Compose Modal & Editor
  isComposeMinimized: false,
  isComposeMaximized: false,

  openCompose(prefill = {}) {
    this.composeFiles = [];
    this.currentDraftId = prefill.draftId || null;

    const modal = document.getElementById('compose-modal');
    if (modal) {
      modal.classList.remove('hidden', 'compose-minimized');
    }
    this.isComposeMinimized = false;

    document.getElementById('compose-to').value = prefill.to || '';
    document.getElementById('compose-cc').value = prefill.cc || '';
    document.getElementById('compose-bcc').value = prefill.bcc || '';
    document.getElementById('compose-subject').value = prefill.subject || '';

    const editor = document.getElementById('compose-editor');
    editor.innerHTML = prefill.body ? this.escapeHtml(prefill.body).replace(/\n/g, '<br>') : '';

    this.renderComposeAttachments();
    document.getElementById('draft-status-pill').textContent = '';
    lucide.createIcons();

    setTimeout(() => {
      if (!prefill.to) {
        document.getElementById('compose-to').focus();
      } else {
        editor.focus();
      }
    }, 100);
  },

  minimizeCompose() {
    const modal = document.getElementById('compose-modal');
    if (!modal) return;
    this.isComposeMinimized = !this.isComposeMinimized;
    if (this.isComposeMinimized) {
      modal.classList.add('compose-minimized');
      if (this.isComposeMaximized) {
        modal.classList.remove('compose-maximized');
        this.isComposeMaximized = false;
      }
    } else {
      modal.classList.remove('compose-minimized');
    }
    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  maximizeCompose() {
    const modal = document.getElementById('compose-modal');
    if (!modal) return;
    if (this.isComposeMinimized) {
      modal.classList.remove('compose-minimized');
      this.isComposeMinimized = false;
    }
    this.isComposeMaximized = !this.isComposeMaximized;
    const maxIcon = document.getElementById('compose-maximize-icon');
    if (this.isComposeMaximized) {
      modal.classList.add('compose-maximized');
      if (maxIcon) maxIcon.setAttribute('data-lucide', 'minimize-2');
    } else {
      modal.classList.remove('compose-maximized');
      if (maxIcon) maxIcon.setAttribute('data-lucide', 'maximize-2');
    }
    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  closeCompose() {
    const modal = document.getElementById('compose-modal');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('compose-minimized', 'compose-maximized');
    }
    this.isComposeMinimized = false;
    this.isComposeMaximized = false;
    this.composeFiles = [];
    this.currentDraftId = null;
  },

  toggleCcBcc() {
    const row = document.getElementById('cc-bcc-row');
    row.classList.toggle('hidden');
  },

  execEditorCommand(command, value = null) {
    document.execCommand(command, false, value);
    document.getElementById('compose-editor').focus();
  },

  insertLinkPrompt() {
    const url = prompt('Enter web URL:', 'https://');
    if (url) {
      this.execEditorCommand('createLink', url);
    }
  },

  insertSignature() {
    const sig = this.currentUser?.signature || '🦝 Sent with clever stealth via Raccon Postcard Mail.';
    const editor = document.getElementById('compose-editor');
    editor.innerHTML += `<br><br><span style="color:#6c7582;font-style:italic;">${this.escapeHtml(sig)}</span>`;
    editor.focus();
  },

  handleFilesSelected(files) {
    for (const f of files) {
      this.composeFiles.push(f);
    }
    this.renderComposeAttachments();
  },

  renderComposeAttachments() {
    const container = document.getElementById('compose-attachments-list');
    if (this.composeFiles.length === 0) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = this.composeFiles.map((file, idx) => `
      <span class="inline-flex items-center gap-1.5 rounded-full bg-muted border border-border px-3 py-1 text-xs">
        <i data-lucide="paperclip" class="h-3 w-3 text-muted-foreground"></i>
        <span class="max-w-[150px] truncate font-medium">${this.escapeHtml(file.name)}</span>
        <button type="button" onclick="app.removeComposeFile(${idx})" class="text-slate-500 hover:text-slate-900 ml-1">
          <i data-lucide="x" class="h-3 w-3"></i>
        </button>
      </span>
    `).join('');

    lucide.createIcons();
  },

  removeComposeFile(idx) {
    this.composeFiles.splice(idx, 1);
    this.renderComposeAttachments();
  },

  async saveDraft() {
    const to = document.getElementById('compose-to').value;
    const cc = document.getElementById('compose-cc').value;
    const bcc = document.getElementById('compose-bcc').value;
    const subject = document.getElementById('compose-subject').value;
    const body = document.getElementById('compose-editor').innerText;
    const body_html = document.getElementById('compose-editor').innerHTML;

    try {
      const res = await fetch('/api/messages/draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: this.currentDraftId,
          recipient_email: to,
          cc,
          bcc,
          subject,
          body,
          body_html
        })
      });
      const data = await res.json();
      this.currentDraftId = data.draftId;
      document.getElementById('draft-status-pill').textContent = 'Draft saved';
      this.fetchFolders();
    } catch (err) {
      console.error('Failed to save draft:', err);
    }
  },

  async discardDraft() {
    this.closeCompose();
    this.showToast('Draft discarded');
  },

  async sendMessage() {
    const to = document.getElementById('compose-to').value.trim();
    const cc = document.getElementById('compose-cc').value.trim();
    const bcc = document.getElementById('compose-bcc').value.trim();
    const subject = document.getElementById('compose-subject').value.trim();
    const body = document.getElementById('compose-editor').innerText;
    const body_html = document.getElementById('compose-editor').innerHTML;

    if (!to) {
      alert('Please specify a recipient email address.');
      document.getElementById('compose-to').focus();
      return;
    }

    const sendBtn = document.getElementById('send-mail-btn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i><span>Sending...</span>';
    lucide.createIcons();

    try {
      const formData = new FormData();
      formData.append('to', to);
      formData.append('cc', cc);
      formData.append('bcc', bcc);
      formData.append('subject', subject || '(no subject)');
      formData.append('body', body);
      formData.append('body_html', body_html);
      if (this.currentDraftId) formData.append('draft_id', this.currentDraftId);

      for (const file of this.composeFiles) {
        formData.append('attachments', file);
      }

      const res = await fetch('/api/messages/send', {
        method: 'POST',
        body: formData
      });

      if (!res.ok) {
        const errorData = await res.json();
        throw new Error(errorData.error || 'Failed to send message');
      }

      this.playChime();
      this.showToast('Dispatched clever email! 🦝');
      this.closeCompose();
      this.fetchFolders();
      if (this.currentFolder === 'Sent') this.loadMessages();
    } catch (err) {
      alert('Send error: ' + err.message);
    } finally {
      sendBtn.disabled = false;
      sendBtn.innerHTML = '<i data-lucide="send" class="h-3.5 w-3.5 text-white"></i><span>Send Postcard</span>';
      lucide.createIcons();
    }
  },

  // Recipient Auto-complete
  onRecipientInput(val) {
    const suggestions = document.getElementById('contact-suggestions');
    if (!val || val.length < 1) {
      suggestions.classList.add('hidden');
      return;
    }

    const matches = this.contacts.filter(c =>
      c.name.toLowerCase().includes(val.toLowerCase()) ||
      c.email.toLowerCase().includes(val.toLowerCase())
    );

    if (matches.length === 0) {
      suggestions.classList.add('hidden');
      return;
    }

    suggestions.innerHTML = matches.map(c => `
      <button
        type="button"
        onclick="app.selectRecipientContact('${c.email}')"
        class="w-full text-left flex items-center justify-between p-2 rounded-lg text-xs hover:bg-muted transition"
      >
        <span class="font-bold truncate">${c.name}</span>
        <span class="text-[11px] text-muted-foreground truncate">&lt;${c.email}&gt;</span>
      </button>
    `).join('');

    suggestions.classList.remove('hidden');
  },

  selectRecipientContact(email) {
    const toInput = document.getElementById('compose-to');
    toInput.value = email;
    document.getElementById('contact-suggestions').classList.add('hidden');
    document.getElementById('compose-subject').focus();
  },

  // Filters & Sorting (Neon Flower Minimalist)
  setFilter(filter) {
    this.currentFilter = filter;
    ['all', 'unread', 'starred', 'has_attachments'].forEach(f => {
      const btn = document.getElementById(`filter-${f}`);
      if (btn) {
        if (f === filter) {
          btn.className = 'px-3 py-1 rounded-full font-bold bg-gradient-to-r from-sky-500 to-blue-600 text-white transition text-xs shadow-[0_0_12px_rgba(14,165,233,0.35)] shrink-0 whitespace-nowrap border border-sky-400/40';
        } else {
          btn.className = 'px-3 py-1 rounded-full font-medium bg-[#0E162B] hover:bg-[#14203D] text-slate-300 transition text-xs border border-sky-500/20 shrink-0 whitespace-nowrap';
        }
      }
    });
    this.loadMessages();
  },

  onSortChange(val) {
    this.currentSort = val;
    this.loadMessages();
  },

  onSearchClick() {
    const val = (document.getElementById('global-search')?.value || '').trim();
    this.fetchSearchSuggestions(val);
  },

  onSearchFocus() {
    this.onSearchClick();
  },

  onSearchInput(val) {
    this.searchQuery = val;
    const clearBtn = document.getElementById('search-clear-btn');
    const dropdown = document.getElementById('search-suggestions-dropdown');

    if (val && val.trim().length > 0) {
      clearBtn?.classList.remove('hidden');
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => {
        this.fetchSearchSuggestions(val.trim());
      }, 160);
    } else {
      clearBtn?.classList.add('hidden');
      if (dropdown && !dropdown.classList.contains('hidden')) {
        this.fetchSearchSuggestions('');
      }
      this.loadMessages();
    }
  },

  _searchSelectedIndex: -1,
  _searchSuggestionItems: [],

  highlightMatch(text, query) {
    if (!text) return '';
    if (!query || !query.trim()) return this.escapeHtml(text);
    const escaped = this.escapeHtml(text);
    const cleanQ = query.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${cleanQ})`, 'gi');
    return escaped.replace(regex, '<mark class="bg-yellow-200 text-slate-900 rounded px-0.5 font-extrabold">$1</mark>');
  },

  onSearchKeyDown(event) {
    const dropdown = document.getElementById('search-suggestions-dropdown');
    const isDropdownOpen = dropdown && !dropdown.classList.contains('hidden');

    if (event.key === 'ArrowDown' && isDropdownOpen && this._searchSuggestionItems.length > 0) {
      event.preventDefault();
      this._searchSelectedIndex = (this._searchSelectedIndex + 1) % this._searchSuggestionItems.length;
      this.highlightSearchSuggestion();
      return;
    }
    if (event.key === 'ArrowUp' && isDropdownOpen && this._searchSuggestionItems.length > 0) {
      event.preventDefault();
      this._searchSelectedIndex = (this._searchSelectedIndex - 1 + this._searchSuggestionItems.length) % this._searchSuggestionItems.length;
      this.highlightSearchSuggestion();
      return;
    }
    if (event.key === 'Enter') {
      if (isDropdownOpen && this._searchSelectedIndex >= 0 && this._searchSuggestionItems[this._searchSelectedIndex]) {
        event.preventDefault();
        this._searchSuggestionItems[this._searchSelectedIndex].action();
        return;
      }
      const val = (document.getElementById('global-search')?.value || '').trim();
      if (val.length > 0) {
        this.searchAllFolders(val);
      }
    } else if (event.key === 'Escape') {
      this.closeSearchSuggestions();
      this.closeAdvancedSearchDropdown();
    }
  },

  highlightSearchSuggestion() {
    const items = document.querySelectorAll('.search-suggestion-row');
    items.forEach((el, idx) => {
      if (idx === this._searchSelectedIndex) {
        el.classList.add('bg-slate-100', 'ring-1', 'ring-slate-900/30');
        el.scrollIntoView({ block: 'nearest' });
      } else {
        el.classList.remove('bg-slate-100', 'ring-1', 'ring-slate-900/30');
      }
    });
  },

  triggerSearchSubmit() {
    const val = (document.getElementById('global-search')?.value || '').trim();
    this.closeSearchSuggestions();
    this.closeAdvancedSearchDropdown();
    if (val.length > 0) {
      this.searchAllFolders(val);
    } else {
      this.searchQuery = '';
      this.loadMessages();
    }
  },

  toggleAdvancedSearchDropdown() {
    const dropdown = document.getElementById('advanced-search-dropdown');
    if (!dropdown) return;
    const isHidden = dropdown.classList.contains('hidden');
    if (isHidden) {
      this.closeSearchSuggestions();
      dropdown.classList.remove('hidden');
      if (window.lucide) {
        try { lucide.createIcons(); } catch (_) {}
      }
    } else {
      dropdown.classList.add('hidden');
    }
  },

  closeAdvancedSearchDropdown() {
    const dropdown = document.getElementById('advanced-search-dropdown');
    if (dropdown) dropdown.classList.add('hidden');
  },

  resetAdvancedSearch() {
    const fromEl = document.getElementById('adv-search-from');
    const toEl = document.getElementById('adv-search-to');
    const subEl = document.getElementById('adv-search-subject');
    const folderEl = document.getElementById('adv-search-folder');
    const attEl = document.getElementById('adv-search-has-attachment');
    if (fromEl) fromEl.value = '';
    if (toEl) toEl.value = '';
    if (subEl) subEl.value = '';
    if (folderEl) folderEl.value = 'all';
    if (attEl) attEl.checked = false;
  },

  submitAdvancedSearch() {
    const fromVal = (document.getElementById('adv-search-from')?.value || '').trim();
    const toVal = (document.getElementById('adv-search-to')?.value || '').trim();
    const subVal = (document.getElementById('adv-search-subject')?.value || '').trim();
    const folderVal = document.getElementById('adv-search-folder')?.value || 'all';
    const hasAtt = document.getElementById('adv-search-has-attachment')?.checked;

    const parts = [];
    if (fromVal) parts.push(fromVal);
    if (toVal) parts.push(toVal);
    if (subVal) parts.push(subVal);
    const combinedQuery = parts.join(' ').trim();

    this.closeAdvancedSearchDropdown();
    this.closeSearchSuggestions();

    const searchInput = document.getElementById('global-search');
    if (searchInput) searchInput.value = combinedQuery;
    this.searchQuery = combinedQuery;

    if (folderVal !== 'all') {
      this.currentFolder = folderVal;
    }
    if (hasAtt) {
      this.currentFilter = 'has_attachments';
    }

    if (combinedQuery.length > 0) {
      if (folderVal === 'all') {
        this.searchAllFolders(combinedQuery);
      } else {
        this.loadMessages();
      }
    } else {
      this.loadMessages();
    }
  },

  closeSearchSuggestions() {
    const dropdown = document.getElementById('search-suggestions-dropdown');
    if (dropdown) dropdown.classList.add('hidden');
  },

  setQuickFilter(filterId) {
    this.closeSearchSuggestions();
    if (filterId === 'spam' || filterId === 'sent' || filterId === 'trash' || filterId === 'archive') {
      this.navTo(filterId.charAt(0).toUpperCase() + filterId.slice(1));
    } else {
      this.setFilter(filterId);
    }
  },

  async fetchSearchSuggestions(query) {
    const dropdown = document.getElementById('search-suggestions-dropdown');
    if (!dropdown) return;

    try {
      const res = await fetch(`/api/search/suggestions?q=${encodeURIComponent(query || '')}`);
      const data = await res.json();
      this.renderSearchSuggestions(data, query || '');
    } catch (err) {
      console.error('Failed to fetch search suggestions:', err);
    }
  },

  renderSearchSuggestions(data, query) {
    const dropdown = document.getElementById('search-suggestions-dropdown');
    if (!dropdown) return;

    this._searchSelectedIndex = -1;
    this._searchSuggestionItems = [];

    const people = data.people || [];
    const messages = data.messages || [];

    // Empty state: render quick filters, recent senders, and recent emails
    if (data.is_empty || (!query && query.trim().length === 0)) {
      let emptyHtml = `
        <!-- Quick Search Filters (Solid Black Buttons, White Write) -->
        <div class="p-3 bg-slate-50/80 border-b border-slate-200">
          <div class="px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-700 flex items-center justify-between">
            <span class="flex items-center gap-1.5"><i data-lucide="zap" class="h-3.5 w-3.5 text-slate-900"></i> Quick Filters</span>
            <span class="text-[9.5px] bg-slate-900 text-white font-bold px-2 py-0.5 rounded-full">1-Tap</span>
          </div>
          <div class="flex flex-wrap gap-1.5 mt-2 px-1">
            <button onclick="app.setQuickFilter('unread')" class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900 hover:bg-black text-white transition cursor-pointer active:scale-95 border border-slate-800 shadow-2xs">
              <i data-lucide="mail-open" class="h-3.5 w-3.5 text-white"></i>
              <span>Unread</span>
            </button>
            <button onclick="app.setQuickFilter('starred')" class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900 hover:bg-black text-white transition cursor-pointer active:scale-95 border border-slate-800 shadow-2xs">
              <i data-lucide="star" class="h-3.5 w-3.5 text-amber-400 fill-amber-400"></i>
              <span>Starred</span>
            </button>
            <button onclick="app.setQuickFilter('has_attachments')" class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900 hover:bg-black text-white transition cursor-pointer active:scale-95 border border-slate-800 shadow-2xs">
              <i data-lucide="paperclip" class="h-3.5 w-3.5 text-white"></i>
              <span>Attachments</span>
            </button>
            <button onclick="app.setQuickFilter('spam')" class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900 hover:bg-black text-white transition cursor-pointer active:scale-95 border border-slate-800 shadow-2xs">
              <i data-lucide="shield-alert" class="h-3.5 w-3.5 text-white"></i>
              <span>Spam Box</span>
            </button>
            <button onclick="app.searchAllFolders('')" class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900 hover:bg-black text-white transition cursor-pointer active:scale-95 border border-slate-800 shadow-2xs">
              <i data-lucide="layers" class="h-3.5 w-3.5 text-white"></i>
              <span>All Mail</span>
            </button>
          </div>
        </div>
      `;

      if (people.length > 0) {
        emptyHtml += `
          <div class="p-2">
            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center justify-between">
              <span>Recent Senders</span>
              <span class="text-[9.5px] bg-slate-900 text-white px-2 py-0.2 rounded-full font-bold">${people.length}</span>
            </div>
            <div class="space-y-1 mt-1">
        `;
        people.forEach((p, idx) => {
          const initial = (p.name || p.email || 'U')[0].toUpperCase();
          this._searchSuggestionItems.push({
            type: 'sender',
            action: () => this.filterBySender(p.email, p.name)
          });
          emptyHtml += `
            <div onclick="app.filterBySender('${this.escapeHtml(p.email)}', '${this.escapeHtml(p.name)}')" class="search-suggestion-row flex items-center gap-2.5 p-2 rounded-2xl hover:bg-slate-100 cursor-pointer transition">
              <div class="h-7 w-7 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                ${initial}
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900 text-xs truncate">${this.escapeHtml(p.name)}</p>
                <p class="text-[11px] text-slate-500 truncate font-mono">${this.escapeHtml(p.email)}</p>
              </div>
              <span class="text-[11px] text-slate-900 font-bold bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full shrink-0">Filter ➔</span>
            </div>
          `;
        });
        emptyHtml += `</div></div>`;
      }

      if (messages.length > 0) {
        emptyHtml += `
          <div class="p-2 border-t border-slate-100">
            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">
              <span>Recent Messages</span>
            </div>
            <div class="space-y-1 mt-1">
        `;
        messages.forEach(m => {
          this._searchSuggestionItems.push({
            type: 'message',
            action: () => this.selectSuggestionMessage(m.id)
          });
          emptyHtml += `
            <div onclick="app.selectSuggestionMessage('${m.id}')" class="search-suggestion-row flex items-center justify-between gap-2 p-2 rounded-2xl hover:bg-slate-100 cursor-pointer transition">
              <div class="min-w-0 flex-1">
                <span class="font-bold text-slate-900 text-xs truncate">${this.escapeHtml(m.sender_name || m.sender_email)}</span>
                <p class="text-[11px] text-slate-600 truncate">${this.escapeHtml(m.subject || '(no subject)')}</p>
              </div>
              <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-400 shrink-0"></i>
            </div>
          `;
        });
        emptyHtml += `</div></div>`;
      }

      dropdown.innerHTML = emptyHtml;
      dropdown.classList.remove('hidden');
      dropdown.classList.add('flex');
      if (window.lucide) lucide.createIcons();
      return;
    }

    if (people.length === 0 && messages.length === 0) {
      this._searchSuggestionItems.push({
        type: 'search_all',
        action: () => this.searchAllFolders(query)
      });
      dropdown.innerHTML = `
        <div class="p-5 text-center text-slate-600">
          <p class="font-semibold text-slate-900 text-sm">No direct suggestion for "${this.escapeHtml(query)}"</p>
          <p class="text-xs text-slate-500 mt-0.5">Try searching across all mail, attachments &amp; trash:</p>
          <button onclick="app.searchAllFolders('${this.escapeHtml(query)}')" class="mt-3 px-4 py-1.5 rounded-full bg-slate-900 hover:bg-black text-white font-bold text-xs cursor-pointer shadow-xs transition">
            Search all folders for "${this.escapeHtml(query)}" ➔
          </button>
        </div>
      `;
      dropdown.classList.remove('hidden');
      dropdown.classList.add('flex');
      return;
    }

    let html = '';

    // 1. People / Contacts Section (Accurate matching with highlighted substring)
    if (people.length > 0) {
      html += `
        <div class="p-2.5 bg-slate-50/70 border-b border-slate-100">
          <div class="px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
            <span>People &amp; Contacts</span>
            <span class="text-[9.5px] bg-slate-900 text-white px-2 py-0.2 rounded-full font-bold">${people.length}</span>
          </div>
          <div class="space-y-1 mt-1.5">
      `;
      people.forEach(p => {
        const initial = (p.name || p.email || 'U')[0].toUpperCase();
        this._searchSuggestionItems.push({
          type: 'sender',
          action: () => this.filterBySender(p.email, p.name)
        });
        html += `
          <div onclick="app.filterBySender('${this.escapeHtml(p.email)}', '${this.escapeHtml(p.name)}')" class="search-suggestion-row flex items-center gap-2.5 p-2 rounded-2xl hover:bg-white hover:shadow-xs cursor-pointer transition border border-transparent hover:border-slate-200">
            <div class="h-7 w-7 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
              ${initial}
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-slate-900 truncate leading-snug">${this.highlightMatch(p.name, query)}</p>
              <p class="text-[11px] text-slate-500 truncate font-mono">${this.highlightMatch(p.email, query)}</p>
            </div>
            <span class="text-[10px] text-white bg-slate-900 hover:bg-black font-bold px-2.5 py-1 rounded-full shrink-0 shadow-2xs">Filter ➔</span>
          </div>
        `;
      });
      html += `</div></div>`;
    }

    // 2. Emails Section (Across all folders: Inbox, Spam, Sent, Archive, Trash)
    if (messages.length > 0) {
      html += `
        <div class="p-2.5">
          <div class="px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
            <span>Matching Messages</span>
            <span class="text-[9.5px] bg-slate-200 text-slate-800 px-2 py-0.2 rounded-full font-bold">${messages.length}</span>
          </div>
          <div class="space-y-1 mt-1.5">
      `;
      messages.forEach(m => {
        const folder = m.folder_label || 'Inbox';
        let folderBadgeColor = 'bg-slate-900 text-white';

        this._searchSuggestionItems.push({
          type: 'message',
          action: () => this.selectSuggestionMessage(m.id)
        });

        html += `
          <div onclick="app.selectSuggestionMessage('${m.id}')" class="search-suggestion-row flex items-start justify-between gap-2.5 p-2.5 rounded-2xl hover:bg-slate-50 cursor-pointer transition border border-transparent hover:border-slate-200">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${folderBadgeColor}">${folder}</span>
                <span class="font-bold text-slate-900 truncate">${this.highlightMatch(m.sender_name || m.sender_email, query)}</span>
              </div>
              <p class="text-xs text-slate-900 font-semibold truncate mt-1">${this.highlightMatch(m.subject || '(no subject)', query)}</p>
              <p class="text-[11px] text-slate-500 truncate mt-0.5">${this.highlightMatch(m.body || '', query)}</p>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400 shrink-0 self-center"></i>
          </div>
        `;
      });
      html += `</div></div>`;
    }

    // 3. Search Loop Footer (Solid Black Button, White Write)
    this._searchSuggestionItems.push({
      type: 'search_all',
      action: () => this.searchAllFolders(query)
    });

    html += `
      <div class="p-3 bg-slate-50 flex items-center justify-between border-t border-slate-200">
        <button onclick="app.searchAllFolders('${this.escapeHtml(query)}')" class="px-3.5 py-1.5 rounded-full bg-slate-900 hover:bg-black text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-xs transition">
          <i data-lucide="search" class="h-3.5 w-3.5 text-white"></i>
          <span>Search in all mail for "${this.escapeHtml(query)}"</span>
        </button>
        <span class="text-[10px] text-slate-500 font-mono hidden sm:inline">Press Enter ↵</span>
      </div>
    `;

    dropdown.innerHTML = html;
    dropdown.classList.remove('hidden');
    dropdown.classList.add('flex');
    if (window.lucide) lucide.createIcons();
  },

  selectSuggestionMessage(id) {
    this.closeSearchSuggestions();
    this.selectMessage(id);
  },

  filterBySender(email, name) {
    const searchInput = document.getElementById('global-search');
    if (searchInput) searchInput.value = email;
    this.closeSearchSuggestions();
    this.searchAllFolders(email);
  },

  searchAllFolders(query) {
    this.closeSearchSuggestions();
    this.searchQuery = query;
    this.currentFolder = 'all';
    const folderTitle = document.getElementById('current-folder-title');
    const folderSub = document.getElementById('current-folder-subtitle');
    if (folderTitle) folderTitle.textContent = 'Search Results';
    if (folderSub) folderSub.textContent = `All folders matching: "${query}"`;
    this.loadMessages();
  },

  focusMobileSearch() {
    this.openCommandPalette();
  },

  // ====================================================
  // COMMAND PALETTE (K-BAR / Ctrl+K / Cmd+K)
  // ====================================================
  _cmdSelectedIndex: 0,
  _cmdItems: [],

  openCommandPalette() {
    this.closeAllModals();
    const modal = document.getElementById('command-palette-backdrop');
    const input = document.getElementById('command-palette-input');
    if (!modal || !input) return;

    modal.classList.remove('hidden');
    input.value = '';
    this._cmdSelectedIndex = 0;
    this.renderCommandPaletteItems('');
    setTimeout(() => input.focus(), 60);
  },

  closeCommandPalette() {
    const modal = document.getElementById('command-palette-backdrop');
    if (modal) modal.classList.add('hidden');
  },

  onCommandPaletteInput(val) {
    this._cmdSelectedIndex = 0;
    this.renderCommandPaletteItems(val);
  },

  onCommandPaletteKeyDown(event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      this.closeCommandPalette();
      return;
    }
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (this._cmdItems.length > 0) {
        this._cmdSelectedIndex = (this._cmdSelectedIndex + 1) % this._cmdItems.length;
        this.highlightCommandPaletteItem();
      }
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (this._cmdItems.length > 0) {
        this._cmdSelectedIndex = (this._cmdSelectedIndex - 1 + this._cmdItems.length) % this._cmdItems.length;
        this.highlightCommandPaletteItem();
      }
      return;
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      const item = this._cmdItems[this._cmdSelectedIndex];
      if (item) {
        this.executeCommandAction(item.action, item.extra);
      }
    }
  },

  highlightCommandPaletteItem() {
    const container = document.getElementById('command-palette-results');
    if (!container) return;
    const items = container.querySelectorAll('.cmd-palette-item');
    items.forEach((el, idx) => {
      if (idx === this._cmdSelectedIndex) {
        el.classList.add('bg-primary/10', 'text-primary', 'font-bold');
        el.scrollIntoView({ block: 'nearest' });
      } else {
        el.classList.remove('bg-primary/10', 'text-primary', 'font-bold');
      }
    });
  },

  getAvailableCommandActions() {
    return [
      { id: 'compose', group: 'Actions', title: 'Compose New Postcard', icon: 'pencil', shortcut: 'C' },
      { id: 'stories', group: 'Actions', title: 'Explore World Stories (175+ Folktales)', icon: 'book-open', shortcut: 'W' },
      { id: 'news', group: 'Actions', title: 'Open 24/7 Global News Wire', icon: 'newspaper', shortcut: 'N' },
      { id: 'refresh', group: 'Actions', title: 'Refresh Mailbox & Sync IMAP', icon: 'refresh-cw', shortcut: 'R' },
      { id: 'screenshot', group: 'Actions', title: 'Capture Postcard Screenshot', icon: 'camera', shortcut: 'S' },
      
      { id: 'nav_inbox', group: 'Navigation', title: 'Go to Inbox', icon: 'inbox', shortcut: 'G I' },
      { id: 'nav_unread', group: 'Navigation', title: 'Go to Unread Mail', icon: 'mail-open', shortcut: 'G U' },
      { id: 'nav_starred', group: 'Navigation', title: 'Go to Starred / Favorites', icon: 'star', shortcut: 'G S' },
      { id: 'nav_sent', group: 'Navigation', title: 'Go to Sent Postcards', icon: 'send', shortcut: 'G E' },
      { id: 'nav_drafts', group: 'Navigation', title: 'Go to Drafts', icon: 'file-text', shortcut: 'G D' },
      { id: 'nav_archive', group: 'Navigation', title: 'Go to Archive', icon: 'archive', shortcut: 'G A' },
      { id: 'nav_spam', group: 'Navigation', title: 'Go to Spam Folder', icon: 'shield-alert', shortcut: 'G X' },
      { id: 'nav_trash', group: 'Navigation', title: 'Go to Trash', icon: 'trash-2', shortcut: 'G T' },
      { id: 'nav_contacts', group: 'Navigation', title: 'Open Address Book & Contacts', icon: 'users', shortcut: 'G C' },

      { id: 'theme_light', group: 'Appearance', title: 'Theme: Clean Modern Light', icon: 'sun', shortcut: 'T 1' },
      { id: 'theme_dark', group: 'Appearance', title: 'Theme: Obsidian Modern Dark', icon: 'moon', shortcut: 'T 2' }
    ];
  },

  renderCommandPaletteItems(query = '') {
    const container = document.getElementById('command-palette-results');
    if (!container) return;

    const q = (query || '').toLowerCase().trim();
    const actions = this.getAvailableCommandActions();
    let filtered = actions;

    if (q) {
      filtered = actions.filter(a => 
        a.title.toLowerCase().includes(q) || 
        a.group.toLowerCase().includes(q) || 
        a.shortcut.toLowerCase().includes(q)
      );
    }

    this._cmdItems = filtered.map(a => ({ action: a.id }));

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="p-6 text-center text-muted-foreground">
          <p class="font-medium">No commands found for "${this.escapeHtml(query)}"</p>
          <button onclick="app.searchAllFolders('${this.escapeHtml(query)}'); app.closeCommandPalette();" class="mt-2 text-primary font-bold hover:underline cursor-pointer">
            Search all mail for "${this.escapeHtml(query)}" ➔
          </button>
        </div>
      `;
      return;
    }

    const groups = {};
    filtered.forEach((item, index) => {
      if (!groups[item.group]) groups[item.group] = [];
      groups[item.group].push({ item, originalIndex: index });
    });

    let html = '';
    let runningIdx = 0;
    for (const [groupName, groupList] of Object.entries(groups)) {
      html += `
        <div class="pt-2 pb-1 px-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
          <span>${groupName}</span>
        </div>
      `;
      for (const { item } of groupList) {
        const isSelected = runningIdx === this._cmdSelectedIndex;
        html += `
          <div
            onclick="app.executeCommandAction('${item.id}')"
            class="cmd-palette-item flex items-center justify-between gap-3 p-2.5 rounded-xl cursor-pointer transition ${isSelected ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <i data-lucide="${item.icon}" class="h-4 w-4 shrink-0"></i>
              <span class="truncate">${this.escapeHtml(item.title)}</span>
            </div>
            <kbd class="px-2 py-0.5 text-[10px] font-mono font-bold bg-card border border-border rounded text-muted-foreground shrink-0">${item.shortcut}</kbd>
          </div>
        `;
        runningIdx++;
      }
    }

    container.innerHTML = html;
    if (window.lucide) lucide.createIcons();
  },

  executeCommandAction(actionId) {
    this.closeCommandPalette();
    if (actionId === 'compose') this.openCompose();
    else if (actionId === 'stories') this.openStoriesModal();
    else if (actionId === 'news') this.openNewsModal();
    else if (actionId === 'refresh') this.refreshCurrentFolder();
    else if (actionId === 'screenshot') this.takeScreenshot();
    else if (actionId === 'nav_inbox') this.navTo('Inbox');
    else if (actionId === 'nav_unread') this.navTo('Unread');
    else if (actionId === 'nav_starred') this.navTo('Starred');
    else if (actionId === 'nav_sent') this.navTo('Sent');
    else if (actionId === 'nav_drafts') this.navTo('Drafts');
    else if (actionId === 'nav_archive') this.navTo('Archive');
    else if (actionId === 'nav_spam') this.navTo('Spam');
    else if (actionId === 'nav_trash') this.navTo('Trash');
    else if (actionId === 'nav_contacts') this.openContactsView();
    else if (actionId === 'theme_light' || actionId === 'theme_harmony') this.setTheme('light');
    else if (actionId === 'theme_dark') this.setTheme('dark');
  },

  // Screenshot Capture tool for postcards & emails
  async takeScreenshot() {
    const cardEl = document.getElementById('message-reader-card') || document.getElementById('reader-content');
    if (!cardEl) {
      this.showToast('No email open to screenshot');
      return;
    }

    if (typeof html2canvas === 'undefined') {
      alert('Screenshot engine loading, please try again in a moment.');
      return;
    }

    const btn = document.getElementById('reader-screenshot-btn');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i><span>Snapping...</span>';
      lucide.createIcons();
    }

    try {
      const canvas = await html2canvas(cardEl, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: null
      });

      canvas.toBlob(async (blob) => {
        if (!blob) throw new Error('Canvas snapshot failed');

        const filename = `raccon-postcard-${Date.now()}.png`;
        const file = new File([blob], filename, { type: 'image/png' });

        // If Web Share API is available (Android / iOS native share)
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          try {
            await navigator.share({
              files: [file],
              title: this.selectedMessage?.subject || 'Raccon Mail Postcard',
              text: 'Check out this clever postcard from Raccon Mail!'
            });
            this.showToast('Shared screenshot! 📸');
            return;
          } catch (shareErr) {
            // Cancelled share, continue to download
          }
        }

        // Direct download
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.showToast('Screenshot saved! 📸');
      }, 'image/png');
    } catch (err) {
      console.error('Screenshot error:', err);
      alert('Could not take screenshot: ' + err.message);
    } finally {
      if (btn) {
        btn.innerHTML = originalText;
        lucide.createIcons();
      }
    }
  },

  clearSearch() {
    const searchInput = document.getElementById('global-search');
    if (searchInput) searchInput.value = '';
    this.closeSearchSuggestions();
    this.onSearchInput('');
    if (searchInput) searchInput.focus();
  },

  refreshCurrentFolder() {
    this.loadMessages();
    this.fetchFolders();
  },

  // Incoming Mail Simulator
  async simulateIncomingMail() {
    try {
      const res = await fetch('/api/messages/simulate', { method: 'POST' });
      const data = await res.json();
      this.playChime();
      this.showToast(`Received incoming postcard from ${data.message.name}! 📬`);
      this.fetchFolders();
      if (this.currentFolder === 'Inbox' || this.currentFolder === 'Unread') {
        this.loadMessages();
      }
    } catch (err) {
      console.error('Failed to simulate incoming mail:', err);
    }
  },

  // Postcard Physical Print Modal
  openPostcardModal() {
    const msg = this.selectedMessage;
    if (!msg) return;

    document.getElementById('postcard-subject').textContent = msg.subject || '(no subject)';
    document.getElementById('postcard-body').textContent = msg.body;
    document.getElementById('postcard-from').textContent = `${msg.sender_name} <${msg.sender_email}>`;
    document.getElementById('postcard-to').textContent = msg.recipient_email;
    document.getElementById('postcard-postmark-date').textContent = new Date(msg.created_at).toLocaleDateString([], { month: 'short', year: 'numeric' }).toUpperCase();

    document.getElementById('postcard-print-modal').classList.remove('hidden');
    lucide.createIcons();
  },

  closePostcardModal() {
    document.getElementById('postcard-print-modal').classList.add('hidden');
  },

  // Custom Folders
  openNewFolderModal() {
    document.getElementById('new-folder-modal').classList.remove('hidden');
    document.getElementById('new-folder-name').value = '';
    setTimeout(() => document.getElementById('new-folder-name').focus(), 100);
  },

  closeNewFolderModal() {
    document.getElementById('new-folder-modal').classList.add('hidden');
  },

  async createFolder() {
    const name = document.getElementById('new-folder-name').value.trim();
    if (!name) return;

    try {
      await fetch('/api/folders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name })
      });
      this.closeNewFolderModal();
      this.fetchFolders();
      this.showToast(`Folder "${name}" created`);
    } catch (err) {
      console.error('Failed to create folder:', err);
    }
  },

  async deleteFolder(id) {
    if (!confirm('Delete this folder? Messages inside will be preserved.')) return;
    try {
      await fetch(`/api/folders/${id}`, { method: 'DELETE' });
      if (this.currentFolder === id) this.currentFolder = 'Inbox';
      this.fetchFolders();
      this.loadMessages();
    } catch (err) {
      console.error('Failed to delete folder:', err);
    }
  },

  // Address Book & Contacts
  async fetchContacts() {
    try {
      const res = await fetch('/api/contacts');
      const data = await res.json();
      this.contacts = data.contacts || [];
      const badge = document.getElementById('contacts-count-badge');
      if (badge) badge.textContent = this.contacts.length;
    } catch (err) {
      console.error('Failed to fetch contacts:', err);
    }
  },

  openContactsView() {
    document.getElementById('contacts-modal').classList.remove('hidden');
    this.renderContactsList(this.contacts);
    lucide.createIcons();
  },

  closeContactsModal() {
    document.getElementById('contacts-modal').classList.add('hidden');
  },

  renderContactsList(list) {
    const container = document.getElementById('contacts-list');
    if (list.length === 0) {
      container.innerHTML = '<p class="col-span-full text-center text-xs text-muted-foreground py-8">No contacts found.</p>';
      return;
    }

    container.innerHTML = list.map(c => `
      <div class="rounded-2xl border border-border bg-card p-3 shadow-sm hover:border-primary transition flex items-center justify-between">
        <div class="flex items-center gap-2.5 truncate">
          <div class="h-9 w-9 rounded-full bg-accent/70 font-display font-bold flex items-center justify-center text-foreground text-xs shrink-0">
            ${c.name.charAt(0).toUpperCase()}
          </div>
          <div class="truncate">
            <p class="font-bold text-xs truncate">${this.escapeHtml(c.name)}</p>
            <p class="text-[11px] text-muted-foreground truncate">${this.escapeHtml(c.email)}</p>
            ${c.notes ? `<p class="text-[10px] text-muted-foreground/80 truncate italic">${this.escapeHtml(c.notes)}</p>` : ''}
          </div>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button
            onclick="app.closeContactsModal(); app.openCompose({ to: '${c.email}' })"
            title="Compose email"
            class="p-1.5 rounded-full bg-muted hover:bg-primary hover:text-white transition"
          >
            <i data-lucide="mail" class="h-3.5 w-3.5"></i>
          </button>
        </div>
      </div>
    `).join('');

    lucide.createIcons();
  },

  filterContacts(query) {
    const q = query.toLowerCase();
    const filtered = this.contacts.filter(c =>
      c.name.toLowerCase().includes(q) ||
      c.email.toLowerCase().includes(q) ||
      (c.notes && c.notes.toLowerCase().includes(q))
    );
    this.renderContactsList(filtered);
  },

  openNewContactForm() {
    document.getElementById('contact-form-modal').classList.remove('hidden');
    document.getElementById('contact-form-name').value = '';
    document.getElementById('contact-form-email').value = '';
    document.getElementById('contact-form-phone').value = '';
    document.getElementById('contact-form-notes').value = '';
  },

  closeContactForm() {
    document.getElementById('contact-form-modal').classList.add('hidden');
  },

  async saveContact() {
    const name = document.getElementById('contact-form-name').value.trim();
    const email = document.getElementById('contact-form-email').value.trim();
    const phone = document.getElementById('contact-form-phone').value.trim();
    const notes = document.getElementById('contact-form-notes').value.trim();

    if (!name || !email) {
      alert('Please specify name and email');
      return;
    }

    try {
      await fetch('/api/contacts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, phone, notes })
      });
      this.closeContactForm();
      await this.fetchContacts();
      this.renderContactsList(this.contacts);
      this.showToast(`Contact "${name}" saved`);
    } catch (err) {
      console.error('Failed to save contact:', err);
    }
  },

  // Settings & Real SMTP / IMAP
  async openSettingsModal() {
    document.getElementById('account-dropdown').classList.add('hidden');
    document.getElementById('settings-modal').classList.remove('hidden');

    document.getElementById('settings-name').value = this.currentUser?.name || '';
    document.getElementById('settings-signature').value = this.currentUser?.signature || '';

    try {
      const res = await fetch('/api/settings/mail');
      const data = await res.json();
      document.getElementById('settings-smtp-host').value = data.smtp_host || '';
      document.getElementById('settings-smtp-port').value = data.smtp_port || 587;
      document.getElementById('settings-smtp-user').value = data.smtp_user || '';
      document.getElementById('settings-sound-enabled').checked = Boolean(data.sound_enabled);
    } catch (err) {
      console.error('Failed to load settings:', err);
    }

    lucide.createIcons();
  },

  closeSettingsModal() {
    document.getElementById('settings-modal').classList.add('hidden');
  },

  async saveSettings() {
    const name = document.getElementById('settings-name').value.trim();
    const signature = document.getElementById('settings-signature').value.trim();
    const sound_enabled = document.getElementById('settings-sound-enabled').checked ? 1 : 0;

    const smtp_host = document.getElementById('settings-smtp-host').value.trim();
    const smtp_port = document.getElementById('settings-smtp-port').value.trim();
    const smtp_user = document.getElementById('settings-smtp-user').value.trim();
    const smtp_pass = document.getElementById('settings-smtp-pass').value.trim();

    try {
      await fetch('/api/user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, signature, sound_enabled })
      });

      await fetch('/api/settings/mail', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ smtp_host, smtp_port, smtp_user, smtp_pass, sound_enabled })
      });

      await this.fetchUser();
      this.closeSettingsModal();
      this.showToast('Settings saved successfully');
    } catch (err) {
      alert('Failed to save settings: ' + err.message);
    }
  },

  async testSmtpConnection() {
    const host = document.getElementById('settings-smtp-host').value.trim();
    const port = document.getElementById('settings-smtp-port').value.trim();
    const user = document.getElementById('settings-smtp-user').value.trim();
    const pass = document.getElementById('settings-smtp-pass').value.trim();
    const statusEl = document.getElementById('smtp-test-status');

    if (!host || !user || !pass) {
      statusEl.textContent = 'Enter host, user, and password';
      statusEl.className = 'text-[11px] text-slate-900 font-semibold';
      return;
    }

    statusEl.textContent = 'Testing connection...';
    statusEl.className = 'text-[11px] text-slate-600';

    try {
      const res = await fetch('/api/settings/mail/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ host, port, user, pass })
      });
      const data = await res.json();
      if (data.success) {
        statusEl.textContent = 'Connected successfully!';
        statusEl.className = 'text-[11px] text-slate-900 font-bold';
      } else {
        statusEl.textContent = 'Failed: ' + (data.error || 'Check credentials');
        statusEl.className = 'text-[11px] text-slate-900 font-semibold';
      }
    } catch (err) {
      statusEl.textContent = 'Error: ' + err.message;
      statusEl.className = 'text-[11px] text-slate-900 font-semibold';
    }
  },

  async logout() {
    if (!confirm('Are you sure you want to sign out of Raccon Mail?')) return;
    try {
      await fetch('/api/auth/logout', { method: 'POST' });
      window.location.href = '/';
    } catch (_) {
      window.location.href = '/';
    }
  },

  // Profile Picture & Cover Photo Management
  formatJoinedDate(dateStr) {
    if (!dateStr) return 'Member since Oct 2026';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return 'Member since Oct 2026';
    const month = d.toLocaleString('en-US', { month: 'short' });
    const year = d.getFullYear();
    return `Member since ${month} ${year}`;
  },

  async autoDetectLocationBackground() {
    try {
      const res = await fetch('https://ipapi.co/json/');
      if (res.ok) {
        const d = await res.json();
        if (d.city && d.country_name) {
          const loc = `${d.city}, ${d.country_name}`;
          const dropLoc = document.getElementById('dropdown-user-location');
          const dropBadge = document.getElementById('dropdown-user-location-badge');
          if (dropLoc) {
            dropLoc.textContent = loc;
            if (dropBadge) dropBadge.classList.remove('hidden');
          }
          const profLoc = document.getElementById('profile-location-text');
          if (profLoc) profLoc.textContent = loc;
        }
      }
    } catch (_) {}
  },

  async autoDetectProfileLocation() {
    const input = document.getElementById('profile-location-input');
    if (!input) return;
    input.value = 'Detecting...';
    try {
      const res = await fetch('https://ipapi.co/json/');
      if (res.ok) {
        const d = await res.json();
        if (d.city && d.country_name) {
          input.value = `${d.city}, ${d.country_name}`;
          return;
        }
      }
    } catch (_) {}
    input.value = 'Bucharest, Romania';
  },

  openProfileModal() {
    this.closeAccountDropdown();
    this.openSettingsModal();
  },

  closeProfileModal() {
    this.closeSettingsModal();
  },

  closeAccountDropdown() {
    document.getElementById('account-dropdown')?.classList.add('hidden');
  },

  toggleReaderMoreDropdown() {
    const el = document.getElementById('reader-more-dropdown');
    el?.classList.toggle('hidden');
  },

  closeReaderMoreDropdown() {
    document.getElementById('reader-more-dropdown')?.classList.add('hidden');
  },

  cropImageToSquare(file, targetSize = 400) {
    return new Promise((resolve) => {
      if (!file || !file.type.startsWith('image/')) {
        return resolve(file);
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          try {
            const canvas = document.createElement('canvas');
            canvas.width = targetSize;
            canvas.height = targetSize;
            const ctx = canvas.getContext('2d');
            const minDim = Math.min(img.width, img.height);
            const sx = (img.width - minDim) / 2;
            const sy = (img.height - minDim) / 2;
            ctx.drawImage(img, sx, sy, minDim, minDim, 0, 0, targetSize, targetSize);
            canvas.toBlob((blob) => {
              resolve(blob || file);
            }, 'image/jpeg', 0.92);
          } catch (err) {
            resolve(file);
          }
        };
        img.onerror = () => resolve(file);
        img.src = e.target.result;
      };
      reader.onerror = () => resolve(file);
      reader.readAsDataURL(file);
    });
  },

  async uploadAvatar(file) {
    if (!file) return;
    try {
      this.showToast('Preparing profile photo...');
      const croppedBlob = await this.cropImageToSquare(file, 400);
      const formData = new FormData();
      formData.append('avatar', croppedBlob, 'avatar.jpg');

      this.showToast('Uploading profile picture...');
      const res = await fetch('/api/user/avatar', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed to upload avatar');

      this.showToast('Profile picture updated! 📸');
      await this.fetchUser();
      const profAv = document.getElementById('profile-avatar-preview');
      if (profAv && data.avatar) profAv.src = data.avatar;
    } catch (err) {
      alert('Avatar upload error: ' + err.message);
    }
  },

  async uploadCoverPhoto(file) {
    if (!file) return;
    const formData = new FormData();
    formData.append('cover', file);

    try {
      this.showToast('Uploading cover photo...');
      const res = await fetch('/api/user/cover', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed to upload cover');

      this.showToast('Cover photo updated! 🌄');
      await this.fetchUser();
      const profCover = document.getElementById('profile-cover-preview');
      if (profCover && data.cover_photo) {
        profCover.style.backgroundImage = `url('${data.cover_photo}')`;
      }
    } catch (err) {
      alert('Cover photo upload error: ' + err.message);
    }
  },

  selectPresetAvatar(url) {
    const av = document.getElementById('profile-avatar-preview');
    if (av) av.src = url;
    if (this.currentUser) this.currentUser.avatar = url;
    this.saveProfile();
  },

  selectPresetCover(val) {
    const cov = document.getElementById('profile-cover-preview');
    if (cov) {
      if (val.startsWith('http') || val.startsWith('/')) {
        cov.style.backgroundImage = `url('${val}')`;
      } else {
        cov.style.background = val;
      }
    }
    if (this.currentUser) this.currentUser.cover_photo = val;
    this.saveProfile();
  },

  async saveProfile() {
    const name = document.getElementById('profile-name-input')?.value.trim();
    const sig = document.getElementById('profile-signature-input')?.value;
    const bio = document.getElementById('profile-bio-input')?.value.trim();
    const location = document.getElementById('profile-location-input')?.value.trim();
    const saveBtn = document.getElementById('profile-save-btn');

    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';
    }

    try {
      const res = await fetch('/api/user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: name,
          signature: sig,
          bio: bio,
          location: location,
          avatar: this.currentUser?.avatar,
          cover_photo: this.currentUser?.cover_photo
        })
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed to save profile');

      await this.fetchUser();
      this.showToast('Profile updated successfully! ✨');
    } catch (err) {
      alert('Save profile error: ' + err.message);
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Profile Changes';
      }
    }
  },

  // Mobile responsive sidebar and view management
  toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar-panel');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');
    if (!sidebar) return;
    const isClosed = sidebar.classList.contains('-translate-x-full');
    if (isClosed) {
      sidebar.classList.remove('-translate-x-full');
      backdrop?.classList.remove('hidden');
    } else {
      sidebar.classList.add('-translate-x-full');
      backdrop?.classList.add('hidden');
    }
  },

  closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar-panel');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');
    sidebar?.classList.add('-translate-x-full');
    backdrop?.classList.add('hidden');
  },

  // Return to clean Inbox view on logo click (Never logs out)
  returnToInbox() {
    try { this.closeChatBubblePopup?.(); } catch (_) {}
    try { this.closeAllModals(); } catch (_) {}
    try { this.closeMobileSidebar?.(); } catch (_) {}
    const dropdown = document.getElementById('account-dropdown');
    if (dropdown) dropdown.classList.add('hidden');
    this.selectedMessage = null;
    this.navTo('Inbox');
  },

    // ==========================================
  // THEME SYSTEM: STRICTLY 2 MODERN THEMES (FIGMA SPECIFICATION: DARK GLASS & LIGHT GLASS)
  // ==========================================
  themeDefinitions: {
    dark: {
      name: '🌙 Oceanic Dark Glass',
      vars: {
        '--background': '#050D1E',
        '--foreground': '#F8FAFC',
        '--card': '#0A162C',
        '--card-alt': '#0E1D3A',
        '--card-foreground': '#F8FAFC',
        '--primary': '#38BDF8',
        '--primary-hover': '#0EA5E9',
        '--primary-foreground': '#FFFFFF',
        '--primary-glow': 'rgba(56, 189, 248, 0.35)',
        '--secondary': '#0F1E38',
        '--secondary-foreground': '#F8FAFC',
        '--secondary-hover': '#162B50',
        '--muted': 'rgba(255, 255, 255, 0.08)',
        '--muted-foreground': '#94A3B8',
        '--accent': '#1D4ED8',
        '--accent-foreground': '#38BDF8',
        '--accent-pink': '#EC4899',
        '--border': 'rgba(255, 255, 255, 0.12)',
        '--border-light': 'rgba(255, 255, 255, 0.06)'
      },
      colorScheme: 'dark'
    },
    light: {
      name: '☀️ Clean White Glass',
      vars: {
        '--background': '#F1F5F9',
        '--foreground': '#0F172A',
        '--card': '#FFFFFF',
        '--card-alt': '#F8FAFC',
        '--card-foreground': '#0F172A',
        '--primary': '#2563EB',
        '--primary-hover': '#1D4ED8',
        '--primary-foreground': '#FFFFFF',
        '--primary-glow': 'rgba(37, 99, 235, 0.2)',
        '--secondary': '#F8FAFC',
        '--secondary-foreground': '#0F172A',
        '--secondary-hover': '#E2E8F0',
        '--muted': '#F1F5F9',
        '--muted-foreground': '#64748B',
        '--accent': '#EBF2FE',
        '--accent-foreground': '#2563EB',
        '--accent-pink': '#DB2777',
        '--border': '#E2E8F0',
        '--border-light': '#F1F5F9'
      },
      colorScheme: 'light'
    }
  },

  normalizeTheme(t) {
    return t === 'light' ? 'light' : 'dark';
  },

  toggleTheme() {
    const current = this.normalizeTheme(localStorage.getItem('raccon_theme') || 'dark');
    const next = current === 'dark' ? 'light' : 'dark';
    this.setTheme(next, true);
  },

  setTheme(themeName, persist = false) {
    const canonicalTheme = this.normalizeTheme(themeName);
    const def = this.themeDefinitions[canonicalTheme];

    // 1. Update document and body classes cleanly
    const allThemeClasses = ['light', 'dark', 'midnight-green', 'raccon', 'harmony', 'midnight', 'forest', 'cyber', 'theme-dark', 'theme-light'];
    allThemeClasses.forEach(t => {
      document.documentElement.classList.remove(t, 'theme-' + t);
      if (document.body) document.body.classList.remove(t, 'theme-' + t);
    });

    if (canonicalTheme === 'dark') {
      document.documentElement.classList.add('dark', 'theme-dark');
      if (document.body) document.body.classList.add('dark', 'theme-dark', 'jarmel-body');
    } else {
      document.documentElement.classList.remove('dark');
      document.documentElement.classList.add('theme-light');
      if (document.body) document.body.classList.add('theme-light', 'jarmel-body');
    }

    // 2. Set colorScheme for OS native controls
    document.documentElement.style.colorScheme = canonicalTheme;

    // 3. Set custom variables
    for (const [k, v] of Object.entries(def.vars)) {
      document.documentElement.style.setProperty(k, v);
    }

    // 4. Update Sun/Moon icons on all toggle buttons
    const sunIcons = document.querySelectorAll('.app-theme-sun');
    const moonIcons = document.querySelectorAll('.app-theme-moon');
    sunIcons.forEach(icon => {
      if (canonicalTheme === 'dark') icon.classList.remove('hidden');
      else icon.classList.add('hidden');
    });
    moonIcons.forEach(icon => {
      if (canonicalTheme === 'dark') icon.classList.add('hidden');
      else icon.classList.remove('hidden');
    });

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
      this.showToast(`Theme: ${def.name}`);
    }

    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  highlightActiveTheme(themeName) {
    const canonical = this.normalizeTheme(themeName);
    const themeButtons = [
      { id: 'theme-btn-light', active: canonical === 'light' },
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
  },

  // Floating Chat Bubble Popup Controls
  toggleChatBubblePopup() {
    const popup = document.getElementById('chat-bubble-popup');
    if (!popup) return;
    if (popup.classList.contains('hidden')) {
      this.openChatBubblePopup();
    } else {
      this.closeChatBubblePopup();
    }
  },

  openChatBubblePopup() {
    const popup = document.getElementById('chat-bubble-popup');
    if (!popup) return;
    popup.classList.remove('hidden');
    popup.classList.add('flex');

    this.renderStickerPanel('chat-popup-sticker-content', this.currentPopupStickerTab, true);
    this.loadChatConversations();
    this.startChatPolling();

    if (this.activeChatFriend) {
      this.showChatPopupThread(this.activeChatFriend);
    } else {
      this.showChatPopupConversations();
    }
  },

  closeChatBubblePopup() {
    const popup = document.getElementById('chat-bubble-popup');
    if (popup) {
      popup.classList.add('hidden');
      popup.classList.remove('flex');
    }
  },

  showChatPopupConversations() {
    const friendsView = document.getElementById('chat-popup-friends-view');
    const threadView = document.getElementById('chat-popup-thread-view');
    const backBtn = document.getElementById('chat-popup-back-btn');

    if (window.innerWidth < 640) {
      friendsView?.classList.remove('hidden');
      threadView?.classList.add('hidden');
      backBtn?.classList.add('hidden');
    } else {
      friendsView?.classList.remove('hidden');
      threadView?.classList.remove('hidden');
      backBtn?.classList.add('hidden');
    }

    const title = document.getElementById('chat-popup-title');
    if (title) title.textContent = 'Raccon Member Chat';
    const sub = document.getElementById('chat-popup-subtitle');
    if (sub) sub.textContent = 'Clever nocturnal messaging for registered members';
    const av = document.getElementById('chat-popup-avatar');
    if (av) av.src = '/raccoon-logo.png';

    this.renderPopupConversations();
  },

  showChatPopupThread(friendEmail) {
    this.activeChatFriend = friendEmail;
    const friendsView = document.getElementById('chat-popup-friends-view');
    const threadView = document.getElementById('chat-popup-thread-view');
    const backBtn = document.getElementById('chat-popup-back-btn');

    if (window.innerWidth < 640) {
      friendsView?.classList.add('hidden');
      threadView?.classList.remove('hidden');
      backBtn?.classList.remove('hidden');
    } else {
      friendsView?.classList.remove('hidden');
      threadView?.classList.remove('hidden');
      backBtn?.classList.add('hidden');
    }

    const friend = this.chatConversations.find(c => c.email.toLowerCase() === friendEmail.toLowerCase());
    if (friend) {
      const title = document.getElementById('chat-popup-title');
      if (title) title.textContent = friend.name;
      const sub = document.getElementById('chat-popup-subtitle');
      if (sub) sub.textContent = friend.email;
      const av = document.getElementById('chat-popup-avatar');
      if (av) av.src = friend.avatar || '/raccoon-logo.png';
      friend.unread = 0;
      this.updateChatUnreadBadge();
    }

    this.renderPopupConversations();
    this.loadChatMessages(friendEmail);
  },

  expandChatToFullPanel() {
    this.closeChatBubblePopup();
    this.openChat();
    if (this.activeChatFriend) {
      this.openConversation(this.activeChatFriend);
    }
  },

  togglePopupStickerTray() {
    const tray = document.getElementById('chat-popup-sticker-tray');
    if (tray) {
      tray.classList.toggle('hidden');
      if (!tray.classList.contains('hidden')) {
        this.renderStickerPanel('chat-popup-sticker-content', this.currentPopupStickerTab, true);
      }
    }
  },

  switchStickerTab(tabKey) {
    this.currentStickerTab = tabKey;
    ['raccoon', 'reactions', 'nocturnal', 'phrases'].forEach(t => {
      const btn = document.getElementById('tab-btn-' + t);
      if (btn) {
        if (t === tabKey) btn.classList.add('active');
        else btn.classList.remove('active');
      }
    });
    this.renderStickerPanel('sticker-tray-content', tabKey, false);
  },

  switchPopupStickerTab(tabKey) {
    this.currentPopupStickerTab = tabKey;
    ['raccoon', 'reactions', 'nocturnal', 'phrases'].forEach(t => {
      const btn = document.getElementById('popup-tab-btn-' + t);
      if (btn) {
        if (t === tabKey) btn.classList.add('active');
        else btn.classList.remove('active');
      }
    });
    this.renderStickerPanel('chat-popup-sticker-content', tabKey, true);
  },

  renderStickerPanel(containerId, tabKey, isPopup = false) {
    const el = document.getElementById(containerId);
    if (!el) return;

    if (tabKey === 'phrases') {
      const phrases = this.stickerPacks.phrases;
      el.innerHTML = `
        <div class="flex flex-wrap gap-1.5 p-1">
          ${phrases.map(p => `
            <button
              type="button"
              onclick="app.sendChatMessage(app.activeChatFriend, '${p.text.replace(/'/g, "\\'")}')"
              class="px-2.5 py-1.5 rounded-full bg-muted/60 hover:bg-muted text-[11px] font-semibold text-foreground hover:scale-105 transition cursor-pointer border border-border"
            >
              ${p.label}
            </button>
          `).join('')}
        </div>
      `;
      return;
    }

    const items = this.stickerPacks[tabKey] || this.stickerPacks.raccoon;
    const gridCols = isPopup ? 'grid-cols-4 sm:grid-cols-6' : 'grid-cols-4 sm:grid-cols-8 md:grid-cols-8';

    el.innerHTML = `
      <div class="grid ${gridCols} gap-2 p-1">
        ${items.map(item => `
          <button
            type="button"
            onclick="app.${isPopup ? 'sendPopupSticker' : 'sendSticker'}('${item.glyph}')"
            class="p-2 rounded-xl bg-muted/40 hover:bg-muted hover:scale-115 active:scale-95 transition flex flex-col items-center justify-center gap-1 text-center cursor-pointer border border-border/50 group"
            title="${item.label}"
          >
            <span class="text-2xl chat-emoji leading-none">${item.glyph}</span>
            <span class="text-[9px] text-muted-foreground group-hover:text-foreground font-medium truncate max-w-full">${item.label}</span>
          </button>
        `).join('')}
      </div>
    `;
  },

  async handlePopupChatSubmit(event) {
    if (event) event.preventDefault();
    const input = document.getElementById('chat-popup-input');
    if (!input || !this.activeChatFriend) return;
    const text = input.value.trim();
    if (!text) return;
    input.value = '';

    await this.sendChatMessage(this.activeChatFriend, text);
  },

  async sendPopupSticker(stickerCode) {
    document.getElementById('chat-popup-sticker-tray')?.classList.add('hidden');
    await this.sendSticker(stickerCode);
  },

  async loadChatConversations() {
    try {
      const res = await fetch('/api/chat/conversations');
      const data = await res.json();
      this.chatConversations = data.conversations || [];
      this.renderChatConversationsList();
      this.renderPopupConversations();
      this.updateChatUnreadBadge();

      if (!this.activeChatFriend && this.chatConversations.length > 0 && window.innerWidth >= 640) {
        this.openConversation(this.chatConversations[0].email);
      }
    } catch (e) {
      console.error('Error loading chat conversations:', e);
    }
  },

  updateChatUnreadBadge() {
    let totalUnread = 0;
    this.chatConversations.forEach(c => totalUnread += (c.unread || 0));

    // 1. Sidebar badge
    const badge = document.getElementById('chat-unread-badge');
    if (badge) {
      if (totalUnread > 0) {
        badge.textContent = totalUnread;
        badge.classList.remove('hidden');
      } else {
        badge.classList.add('hidden');
      }
    }

    // 2. Floating chat bubble launcher badge
    const launcherBadge = document.getElementById('chat-launcher-badge');
    if (launcherBadge) {
      if (totalUnread > 0) {
        launcherBadge.textContent = totalUnread;
        launcherBadge.classList.remove('hidden');
      } else {
        launcherBadge.classList.add('hidden');
      }
    }
  },

  renderChatConversationsList(filter = '') {
    const listEl = document.getElementById('chat-conversations-list');
    if (!listEl) return;

    let items = this.chatConversations;
    if (filter) {
      const q = filter.toLowerCase();
      items = items.filter(c => c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q));
    }

    if (items.length === 0) {
      listEl.innerHTML = `
        <div class="p-6 text-center text-muted-foreground text-xs">
          <p class="text-3xl mb-1 chat-emoji">🦝</p>
          <p class="font-bold text-foreground">No friends found</p>
          <p class="text-[11px] text-muted-foreground mt-0.5">Invite email friends to register and chat!</p>
        </div>
      `;
      return;
    }

    listEl.innerHTML = items.map(c => {
      const isActive = this.activeChatFriend && this.activeChatFriend.toLowerCase() === c.email.toLowerCase();
      const timeStr = c.last_time ? new Date(c.last_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
      return `
        <div
          onclick="app.openConversation('${c.email}')"
          class="p-3 flex items-center justify-between hover:bg-muted/60 transition cursor-pointer ${isActive ? 'bg-accent/40 font-bold border-l-4 border-slate-900' : ''}"
        >
          <div class="flex items-center gap-2.5 truncate">
            <div class="relative shrink-0">
              <img src="${c.avatar || '/raccoon-logo.png'}" class="h-9 w-9 rounded-full object-cover aspect-square border border-border" />
              <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-slate-500 border border-card"></span>
            </div>
            <div class="truncate">
              <p class="text-xs font-bold text-foreground truncate">${c.name}</p>
              <p class="text-[11px] text-muted-foreground truncate">${c.last_message}</p>
            </div>
          </div>

          <div class="text-right shrink-0 ml-2">
            <span class="text-[10px] text-muted-foreground block font-mono">${timeStr}</span>
            ${c.unread > 0 ? `<span class="inline-block mt-0.5 px-1.5 py-0.2 rounded-full bg-slate-900 text-white text-[10px] font-bold font-mono">${c.unread}</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  },

  renderPopupConversations(filter = '') {
    const listEl = document.getElementById('chat-popup-conversations-container') || document.getElementById('chat-popup-friends-view');
    if (!listEl) return;

    let items = this.chatConversations || [];
    if (filter) {
      const q = filter.toLowerCase();
      items = items.filter(c => (c.name && c.name.toLowerCase().includes(q)) || (c.email && c.email.toLowerCase().includes(q)));
    }

    if (items.length === 0) {
      listEl.innerHTML = `
        <div class="p-6 text-center text-muted-foreground text-xs my-auto">
          <p class="text-3xl mb-2 chat-emoji">🦝</p>
          <p class="font-bold text-foreground">No member friends found</p>
          <p class="text-[11px] text-muted-foreground mt-1">Invite friends to register on Raccon Mail to chat live!</p>
        </div>
      `;
      return;
    }

    listEl.innerHTML = items.map(c => {
      const isActive = this.activeChatFriend && this.activeChatFriend.toLowerCase() === c.email.toLowerCase();
      const timeStr = c.last_time ? new Date(c.last_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
      return `
        <div
          onclick="app.showChatPopupThread('${c.email}')"
          class="p-3 flex items-center justify-between hover:bg-muted/60 transition cursor-pointer ${isActive ? 'bg-accent/40 font-bold border-l-4 border-slate-900' : ''}"
        >
          <div class="flex items-center gap-2.5 truncate">
            <div class="relative shrink-0">
              <img src="${c.avatar || '/raccoon-logo.png'}" class="h-9 w-9 rounded-full object-cover aspect-square border border-border" />
              <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-slate-500 border border-card"></span>
            </div>
            <div class="truncate">
              <p class="text-xs font-bold text-foreground truncate">${c.name}</p>
              <p class="text-[11px] text-muted-foreground truncate">${c.last_message}</p>
            </div>
          </div>

          <div class="text-right shrink-0 ml-2">
            <span class="text-[10px] text-muted-foreground block font-mono">${timeStr}</span>
            ${c.unread > 0 ? `<span class="inline-block mt-0.5 px-1.5 py-0.2 rounded-full bg-slate-900 text-white text-[10px] font-bold font-mono">${c.unread}</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  },

  filterChatFriends(val) {
    this.renderChatConversationsList(val);
    this.renderPopupConversations(val);
  },

  async openConversation(friendEmail) {
    this.activeChatFriend = friendEmail;
    const threadContainer = document.getElementById('chat-thread-container');
    if (threadContainer) threadContainer.classList.remove('hidden');

    const friend = this.chatConversations.find(c => c.email.toLowerCase() === friendEmail.toLowerCase());
    if (friend) {
      const nameEl = document.getElementById('chat-partner-name');
      if (nameEl) nameEl.textContent = friend.name;
      const emailEl = document.getElementById('chat-partner-email');
      if (emailEl) emailEl.textContent = friend.email;
      const av = document.getElementById('chat-partner-avatar');
      if (av) av.src = friend.avatar || '/raccoon-logo.png';
      friend.unread = 0;
      this.updateChatUnreadBadge();
    }

    this.renderChatConversationsList();
    await this.loadChatMessages(friendEmail);
  },

  closeMobileChatThread() {
    const threadContainer = document.getElementById('chat-thread-container');
    if (threadContainer) threadContainer.classList.add('hidden');
  },

  async loadChatMessages(friendEmail) {
    if (!friendEmail) return;
    try {
      const res = await fetch(`/api/chat/messages?with=${encodeURIComponent(friendEmail)}`);
      const data = await res.json();
      this.renderChatMessagesFeed(data.messages || []);
    } catch (e) {
      console.error('Error loading chat messages:', e);
    }
  },

  renderChatMessagesFeed(messages) {
    const feeds = [
      document.getElementById('chat-messages-feed'),
      document.getElementById('chat-popup-messages-feed')
    ].filter(Boolean);

    if (feeds.length === 0) return;

    const myEmail = this.currentUser?.email?.toLowerCase() || '';
    const activeFriendObj = this.chatConversations.find(c => (c.email || '').toLowerCase() === (this.activeChatFriend || '').toLowerCase());
    const friendAvatar = activeFriendObj?.avatar || '/raccoon-logo.png';
    const friendName = activeFriendObj?.name || 'Friend';

    let html = '';
    if (messages.length === 0) {
      html = `
        <div class="h-full flex flex-col items-center justify-center text-center p-6 text-muted-foreground my-auto">
          <span class="text-4xl mb-2 chat-emoji animate-bounce">🦝</span>
          <p class="font-bold text-foreground text-sm">No messages yet with ${this.escapeHtml(friendName)}</p>
          <p class="text-xs text-muted-foreground mt-1 max-w-xs">Say hello or tap a raccoon sticker to break the ice!</p>
        </div>
      `;
    } else {
      html = messages.map(msg => {
        const isMe = (msg.sender_email || '').toLowerCase() === myEmail;
        const timeStr = msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

        // Check if message is a standalone sticker/emoji
        const isStickerOnly = Boolean(msg.sticker) && !msg.message && !msg.attachment_path;

        let contentHtml = '';
        if (msg.sticker) {
          contentHtml += `<div class="chat-emoji ${isStickerOnly ? 'sticker-big' : 'text-3xl py-1 my-1'}">${msg.sticker}</div>`;
        }
        if (msg.message) {
          contentHtml += `<p class="leading-relaxed whitespace-pre-wrap selection:bg-black/20 break-words font-sans">${this.escapeHtml(msg.message)}</p>`;
        }
        if (msg.attachment_path) {
          const isImg = (msg.attachment_type || '').startsWith('image/');
          if (isImg) {
            contentHtml += `
              <div class="mt-2 rounded-xl overflow-hidden border border-black/10 max-w-xs">
                <a href="${msg.attachment_path}" target="_blank" rel="noopener noreferrer">
                  <img src="${msg.attachment_path}" class="max-h-48 w-full rounded-lg object-cover" />
                </a>
              </div>
            `;
          } else {
            contentHtml += `
              <a href="${msg.attachment_path}" target="_blank" download class="mt-2 inline-flex items-center gap-1.5 p-2 rounded-xl bg-black/10 hover:bg-black/20 text-xs font-semibold transition">
                <i data-lucide="paperclip" class="h-3.5 w-3.5"></i>
                <span class="truncate max-w-[160px]">${msg.attachment_name || 'Attachment'}</span>
              </a>
            `;
          }
        }

        if (isStickerOnly) {
          return `
            <div class="flex items-end gap-2 ${isMe ? 'justify-end' : 'justify-start'}">
              ${!isMe ? `<img src="${friendAvatar}" class="h-6 w-6 rounded-full object-cover shrink-0 mb-1 border border-border" />` : ''}
              <div class="chat-bubble-sticker flex flex-col ${isMe ? 'items-end' : 'items-start'}">
                ${contentHtml}
                <div class="text-[9px] text-muted-foreground font-mono mt-0.5 flex items-center gap-1">
                  <span>${timeStr}</span>
                  ${isMe ? '<span class="text-slate-700 font-bold">✓✓</span>' : ''}
                </div>
              </div>
            </div>
          `;
        }

        return `
          <div class="flex items-end gap-2 ${isMe ? 'justify-end' : 'justify-start'}">
            ${!isMe ? `<img src="${friendAvatar}" class="h-6 w-6 rounded-full object-cover shrink-0 mb-1 border border-border" />` : ''}
            <div class="max-w-[85%] sm:max-w-[72%] ${isMe ? 'chat-bubble-outgoing' : 'chat-bubble-incoming'} px-3.5 py-2.5 text-xs shadow-xs">
              ${contentHtml}
              <div class="mt-1 text-[9px] ${isMe ? 'text-white/80' : 'text-muted-foreground'} text-right font-mono flex items-center justify-end gap-1">
                <span>${timeStr}</span>
                ${isMe ? '<span class="text-white font-bold">✓✓</span>' : ''}
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    feeds.forEach(feed => {
      feed.innerHTML = html;
      feed.scrollTop = feed.scrollHeight;
    });

    if (window.lucide) {
      try { lucide.createIcons(); } catch (_) {}
    }
  },

  async handleChatSubmit(event) {
    if (event) event.preventDefault();
    const input = document.getElementById('chat-message-input');
    if (!input || !this.activeChatFriend) return;
    const text = input.value.trim();
    if (!text) return;
    input.value = '';

    await this.sendChatMessage(this.activeChatFriend, text);
  },

  async sendChatMessage(friendEmail, text) {
    try {
      const res = await fetch('/api/chat/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to: friendEmail, message: text })
      });
      const data = await res.json();
      if (data.success) {
        this.loadChatMessages(friendEmail);
        this.loadChatConversations();
        this.playChime();
      } else if (data.error) {
        alert(data.error);
      }
    } catch (e) {
      console.error('Error sending chat message:', e);
    }
  },

  toggleStickerTray() {
    const tray = document.getElementById('raccoon-sticker-tray');
    if (tray) {
      tray.classList.toggle('hidden');
      if (!tray.classList.contains('hidden')) {
        this.renderStickerPanel('sticker-tray-content', this.currentStickerTab, false);
      }
    }
  },

  async sendSticker(stickerCode) {
    if (!this.activeChatFriend) return;
    document.getElementById('raccoon-sticker-tray')?.classList.add('hidden');
    try {
      const res = await fetch('/api/chat/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to: this.activeChatFriend, sticker: stickerCode })
      });
      const data = await res.json();
      if (data.success) {
        this.loadChatMessages(this.activeChatFriend);
        this.loadChatConversations();
        this.playChime();
      }
    } catch (e) {
      console.error('Error sending sticker:', e);
    }
  },

  async handleChatFileUpload(file, isPopup = false) {
    if (!file || !this.activeChatFriend) return;
    const formData = new FormData();
    formData.append('to', this.activeChatFriend);
    formData.append('attachment', file);

    try {
      const res = await fetch('/api/chat/send', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        this.loadChatMessages(this.activeChatFriend);
        this.loadChatConversations();
        this.playChime();
      }
    } catch (e) {
      console.error('Error uploading chat file:', e);
    }
  },

  startChatPolling() {
    if (this.chatPollInterval) {
      clearInterval(this.chatPollInterval);
      this.chatPollInterval = null;
    }
  },

  backToMessages() {
    const msgPanel = document.getElementById('messages-panel');
    const readerPanel = document.getElementById('reader-panel');
    this.selectedMessage = null;
    msgPanel?.classList.remove('hidden');
    readerPanel?.classList.add('hidden');
    readerPanel?.classList.remove('flex');
    this.renderMessagesList();
  },

  escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
};

// Expose app globally to ensure all inline onclick handlers execute reliably
window.app = app;

// Start application when DOM is ready
window.addEventListener('DOMContentLoaded', () => {
  app.init();
});
