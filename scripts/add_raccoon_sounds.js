const fs = require('fs');
const path = require('path');

const ROOT_DIR = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

// 1. PATCH public/inbox.php
const inboxPath = path.join(ROOT_DIR, 'public/inbox.php');
let inbox = fs.readFileSync(inboxPath, 'utf8');

const soundEngineCode = `
    // =========================================================================
    // RACCON SOUND SYSTEM
    // Happy Raccoon (1.00s joyful chitter) on Send
    // Afraid Raccoon (0.85s startled alert) on Receive
    // =========================================================================
    const happyRaccoonAudio = new Audio('/sounds/happy-raccoon.wav');
    const afraidRaccoonAudio = new Audio('/sounds/afraid-raccoon.wav');
    happyRaccoonAudio.preload = 'auto';
    afraidRaccoonAudio.preload = 'auto';

    let audioContextInstance = null;
    function getAudioContext() {
      if (!audioContextInstance) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
          audioContextInstance = new AudioContextClass();
        }
      }
      if (audioContextInstance && audioContextInstance.state === 'suspended') {
        audioContextInstance.resume().catch(() => {});
      }
      return audioContextInstance;
    }

    // Web Audio Synthesizer: 1-Second Happy Raccoon Joyful Chittering
    function synthHappyRaccoon() {
      try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;

        // 4 rapid happy chitter pulses (duration: exactly 1.0s)
        const pulses = [
          { start: 0.02, end: 0.22, f1: 1150, f2: 1550, vib: 28, vibD: 70 },
          { start: 0.26, end: 0.46, f1: 1350, f2: 1850, vib: 32, vibD: 90 },
          { start: 0.50, end: 0.72, f1: 1450, f2: 1980, vib: 35, vibD: 110 },
          { start: 0.76, end: 0.98, f1: 1300, f2: 1700, vib: 26, vibD: 60 }
        ];

        pulses.forEach(p => {
          const osc1 = ctx.createOscillator();
          const osc2 = ctx.createOscillator();
          const gain = ctx.createGain();
          const lfo = ctx.createOscillator();
          const lfoGain = ctx.createGain();

          osc1.type = 'triangle';
          osc2.type = 'sine';
          lfo.type = 'sine';

          const pStart = now + p.start;
          const pEnd = now + p.end;
          const pDur = p.end - p.start;

          osc1.frequency.setValueAtTime(p.f1, pStart);
          osc1.frequency.exponentialRampToValueAtTime(p.f2, pEnd);

          osc2.frequency.setValueAtTime(p.f1 * 2, pStart);
          osc2.frequency.exponentialRampToValueAtTime(p.f2 * 2, pEnd);

          lfo.frequency.setValueAtTime(p.vib, pStart);
          lfoGain.gain.setValueAtTime(p.vibD, pStart);
          lfo.connect(osc1.frequency);
          lfo.connect(osc2.frequency);

          gain.gain.setValueAtTime(0.0001, pStart);
          gain.gain.exponentialRampToValueAtTime(0.28, pStart + pDur * 0.35);
          gain.gain.exponentialRampToValueAtTime(0.0001, pEnd);

          osc1.connect(gain);
          osc2.connect(gain);
          gain.connect(ctx.destination);

          osc1.start(pStart);
          osc2.start(pStart);
          lfo.start(pStart);
          osc1.stop(pEnd + 0.05);
          osc2.stop(pEnd + 0.05);
          lfo.stop(pEnd + 0.05);
        });
      } catch (_) {}
    }

    // Web Audio Synthesizer: Afraid / Startled Raccoon Sound Notification
    function synthAfraidRaccoon() {
      try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;

        // Alarmed stages:
        // 1. Sudden startled screech (sharp high attack 3100Hz -> 2100Hz with panicky flutter)
        // 2. Anxious tremor squeak (2400Hz -> 1750Hz)
        // 3. Alarmed whimper chitter (1950Hz -> 1450Hz)
        const stages = [
          { start: 0.00, end: 0.32, f1: 3100, f2: 2100, vib: 42, vibD: 180, vol: 0.35, sharp: true },
          { start: 0.35, end: 0.56, f1: 2400, f2: 1750, vib: 48, vibD: 140, vol: 0.28, sharp: false },
          { start: 0.59, end: 0.80, f1: 1950, f2: 1450, vib: 52, vibD: 110, vol: 0.22, sharp: false }
        ];

        stages.forEach(s => {
          const osc = ctx.createOscillator();
          const oscHarm = ctx.createOscillator();
          const gain = ctx.createGain();
          const lfo = ctx.createOscillator();
          const lfoGain = ctx.createGain();

          osc.type = 'sawtooth';
          oscHarm.type = 'triangle';
          lfo.type = 'sine';

          const sStart = now + s.start;
          const sEnd = now + s.end;
          const sDur = s.end - s.start;

          osc.frequency.setValueAtTime(s.f1, sStart);
          osc.frequency.exponentialRampToValueAtTime(s.f2, sEnd);

          oscHarm.frequency.setValueAtTime(s.f1 * 1.5, sStart);
          oscHarm.frequency.exponentialRampToValueAtTime(s.f2 * 1.5, sEnd);

          lfo.frequency.setValueAtTime(s.vib, sStart);
          lfoGain.gain.setValueAtTime(s.vibD, sStart);
          lfo.connect(osc.frequency);
          lfo.connect(oscHarm.frequency);

          if (s.sharp) {
            gain.gain.setValueAtTime(s.vol, sStart);
            gain.gain.exponentialRampToValueAtTime(0.0001, sEnd);
          } else {
            gain.gain.setValueAtTime(0.0001, sStart);
            gain.gain.exponentialRampToValueAtTime(s.vol, sStart + sDur * 0.25);
            gain.gain.exponentialRampToValueAtTime(0.0001, sEnd);
          }

          osc.connect(gain);
          oscHarm.connect(gain);
          gain.connect(ctx.destination);

          osc.start(sStart);
          oscHarm.start(sStart);
          lfo.start(sStart);
          osc.stop(sEnd + 0.05);
          oscHarm.stop(sEnd + 0.05);
          lfo.stop(sEnd + 0.05);
        });
      } catch (_) {}
    }

    // Unified Sound Player
    function playRaccoonSound(type = 'happy') {
      const isHappy = (type === 'happy' || type === 'send');
      const targetAudio = isHappy ? happyRaccoonAudio : afraidRaccoonAudio;

      try {
        targetAudio.currentTime = 0;
        const playPromise = targetAudio.play();
        if (playPromise !== undefined) {
          playPromise.catch(() => {
            // Audio element failed or blocked: fallback to Web Audio synthesizer
            if (isHappy) {
              synthHappyRaccoon();
            } else {
              synthAfraidRaccoon();
            }
          });
        }
      } catch (_) {
        if (isHappy) {
          synthHappyRaccoon();
        } else {
          synthAfraidRaccoon();
        }
      }
    }

    // Auto-unlock audio on first user interaction
    (function initAudioUnlock() {
      function unlock() {
        getAudioContext();
        happyRaccoonAudio.load();
        afraidRaccoonAudio.load();
        window.removeEventListener('click', unlock);
        window.removeEventListener('keydown', unlock);
        window.removeEventListener('touchstart', unlock);
      }
      window.addEventListener('click', unlock, { once: true });
      window.addEventListener('keydown', unlock, { once: true });
      window.addEventListener('touchstart', unlock, { once: true });
    })();

    let latestIncomingMsgId = null;
    function showIncomingToast(sender, subject, msgId = null) {
      latestIncomingMsgId = msgId;
      const toast = document.getElementById('raccoon-incoming-toast');
      const senderEl = document.getElementById('incoming-toast-sender');
      const subjEl = document.getElementById('incoming-toast-subject');
      if (senderEl) senderEl.innerText = sender || 'New Sender';
      if (subjEl) subjEl.innerText = subject || '(no subject)';
      if (toast) {
        toast.classList.remove('hidden');
        setTimeout(() => {
          toast.classList.remove('-translate-y-4', 'opacity-0');
        }, 10);
        clearTimeout(window.__incomingToastTimer);
        window.__incomingToastTimer = setTimeout(closeIncomingToast, 6500);
      }
    }

    function closeIncomingToast() {
      const toast = document.getElementById('raccoon-incoming-toast');
      if (toast) {
        toast.classList.add('-translate-y-4', 'opacity-0');
        setTimeout(() => {
          toast.classList.add('hidden');
        }, 300);
      }
    }

    function clickIncomingToast() {
      closeIncomingToast();
      if (currentFolder !== 'Inbox') {
        selectFolder('Inbox');
      } else {
        renderMessageList();
      }
      if (latestIncomingMsgId) {
        const list = mailDatabase['Inbox'] || [];
        const idx = list.findIndex(m => m.id === latestIncomingMsgId);
        if (idx !== -1) {
          selectMessageThread(idx);
        }
      }
    }
`;

// Insert sound engine right after let isSidebarCollapsed = false;
if (!inbox.includes('RACCON SOUND SYSTEM')) {
  inbox = inbox.replace(
    /let isSidebarCollapsed = false;/,
    `let isSidebarCollapsed = false;\n${soundEngineCode}`
  );
}

// Inbound polling integration
const pollingCode = `
    // Real-time Inbound Mail Monitor (Polls every 12 seconds with afraid raccoon sound)
    const knownMessageIds = new Set(initialDbMessages.map(m => m.id));

    async function pollForIncomingEmails() {
      try {
        let hasNew = false;
        let newSender = '';
        let newSubject = '';
        let newMsgId = null;

        try {
          const syncRes = await fetch('/api/messages/sync');
          if (syncRes.ok) {
            const syncData = await syncRes.json();
            if (syncData && syncData.synced > 0 && Array.isArray(syncData.messages) && syncData.messages.length > 0) {
              const first = syncData.messages[0];
              newSender = first.sender_name || first.sender_email || 'New Sender';
              newSubject = first.subject || '(no subject)';
              newMsgId = first.id;
              hasNew = true;
            }
          }
        } catch (_) {}

        const res = await fetch('/api/messages?folder=inbox');
        if (res.ok) {
          const data = await res.json();
          const msgs = data.messages || [];
          for (const m of msgs) {
            if (!knownMessageIds.has(m.id)) {
              knownMessageIds.add(m.id);
              if (!hasNew) {
                hasNew = true;
                newSender = m.sender_name || m.sender_email || 'New Sender';
                newSubject = m.subject || '(no subject)';
                newMsgId = m.id;
              }
            }
          }
        }

        if (hasNew) {
          // Play Afraid Raccoon notification sound!
          playRaccoonSound('afraid');
          showIncomingToast(newSender, newSubject, newMsgId);
          await fetchFolderMessages(currentFolder);
          await refreshFolderCounts();
        }
      } catch (err) {
        console.warn('Inbound polling warning:', err);
      }
    }

    // Start background inbound polling after 4 seconds
    setTimeout(() => {
      setInterval(pollForIncomingEmails, 12000);
    }, 4000);
`;

if (!inbox.includes('pollForIncomingEmails')) {
  inbox = inbox.replace(
    /(const initialDbMessages = [\s\S]*?\|\|\s*\[\];)/,
    `$1\n${pollingCode}`
  );
}

// Add playRaccoonSound('happy') to sendEmailNow()
if (!inbox.includes("playRaccoonSound('happy');")) {
  inbox = inbox.replace(
    /showRaccoonSuccessModal\(\);(\s*refreshFolderCounts\(\);)/,
    "playRaccoonSound('happy');\n      showRaccoonSuccessModal();$1"
  );
  
  // Also in quick reply
  inbox = inbox.replace(
    /(inp\.value = '';\s*)showRaccoonSuccessModal\(\);/,
    "$1playRaccoonSound('happy');\n        showRaccoonSuccessModal();"
  );
}

// In refreshMailbox(), also poll
if (!inbox.includes('pollForIncomingEmails()')) {
  inbox = inbox.replace(
    /(async function refreshMailbox\(\)\s*\{[\s\S]*?await Promise\.all\(\[)/,
    `$1\n          pollForIncomingEmails(),`
  );
}

// Add #raccoon-incoming-toast before </body>
const toastHtml = `
  <!-- ========================================================================= -->
  <!-- AFRAID RACCOON INCOMING EMAIL NOTIFICATION TOAST                         -->
  <!-- ========================================================================= -->
  <div id="raccoon-incoming-toast" class="hidden fixed top-5 right-5 z-[999999] max-w-sm w-[calc(100vw-40px)] bg-white/95 backdrop-blur-md rounded-2xl p-4 shadow-2xl border border-slate-200/90 flex items-start gap-3.5 transition-all duration-300 transform -translate-y-4 opacity-0 pointer-events-auto">
    <div class="relative shrink-0">
      <img src="/raccoon-logo.png" alt="Raccon Alert" class="h-10 w-10 object-contain drop-shadow-xs animate-bounce" />
      <span class="absolute -top-1 -right-1 text-xs">⚡</span>
    </div>
    <div class="min-w-0 flex-1 cursor-pointer" onclick="clickIncomingToast()">
      <div class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider uppercase text-slate-500">
        <span class="w-1.5 h-1.5 rounded-full bg-slate-900 animate-ping"></span>
        <span>New Incoming Mail</span>
      </div>
      <h4 id="incoming-toast-sender" class="font-bold text-xs text-slate-900 mt-0.5 truncate">Someone sent an email</h4>
      <p id="incoming-toast-subject" class="text-[11px] text-slate-500 mt-0.5 line-clamp-1 truncate font-normal">Click to read</p>
    </div>
    <button onclick="closeIncomingToast()" class="text-slate-400 hover:text-slate-700 transition p-1 -mr-1 -mt-1 cursor-pointer">
      <i class="fas fa-times text-xs"></i>
    </button>
  </div>
`;

if (!inbox.includes('raccoon-incoming-toast')) {
  inbox = inbox.replace('</body>', `${toastHtml}\n</body>`);
}

fs.writeFileSync(inboxPath, inbox, 'utf8');
console.log('Successfully patched public/inbox.php with Raccoon Sound Engine & Alerts!');

// 2. PATCH public/js/app.js
const appJsPath = path.join(ROOT_DIR, 'public/js/app.js');
let appJs = fs.readFileSync(appJsPath, 'utf8');

// Replace playNotificationSound in app.js
const appJsSoundReplacement = `  playNotificationSound(type = 'receive') {
    const isHappy = (type === 'send');
    if (typeof playRaccoonSound === 'function') {
      playRaccoonSound(isHappy ? 'happy' : 'afraid');
    } else {
      try {
        const audio = new Audio(isHappy ? '/sounds/happy-raccoon.wav' : '/sounds/afraid-raccoon.wav');
        audio.play().catch(() => {});
      } catch (_) {}
    }
  },`;

appJs = appJs.replace(/playNotificationSound\(type = 'receive'\)\s*\{[\s\S]*?playChime\(\)/, `${appJsSoundReplacement}\n\n  playChime()`);

fs.writeFileSync(appJsPath, appJs, 'utf8');
console.log('Successfully patched public/js/app.js with Raccoon Sound triggers!');
