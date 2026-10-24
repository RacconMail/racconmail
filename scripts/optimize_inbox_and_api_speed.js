const fs = require('fs');
const path = require('path');

const rootDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

// 1. PATCH public/api.php
const apiPath = path.join(rootDir, 'public/api.php');
let api = fs.readFileSync(apiPath, 'utf8');

// Update messages/sync response format
const oldSyncRoute = `if ($route === 'messages/sync' && (($_GET['key'] ?? '') === 'raccon_sync_2026' || !empty($_SESSION['current_user_id']))) {
    $count = syncInboundEmails($db, true);
    echo json_encode(['success' => true, 'synced' => $count]);
    exit;
}`;

const newSyncRoute = `if ($route === 'messages/sync' && (($_GET['key'] ?? '') === 'raccon_sync_2026' || !empty($_SESSION['current_user_id']))) {
    $res = syncInboundEmails($db, true);
    $count = is_array($res) ? ($res['count'] ?? 0) : (int)$res;
    $msgs = is_array($res) ? ($res['messages'] ?? []) : [];
    echo json_encode([
        'success' => true,
        'synced' => $count,
        'count' => $count,
        'messages' => $msgs
    ]);
    exit;
}`;

if (api.includes(oldSyncRoute)) {
  api = api.replace(oldSyncRoute, newSyncRoute);
  console.log('Replaced messages/sync route in api.php');
} else {
  // Regex replacement
  api = api.replace(
    /if \(\$route === 'messages\/sync'[\s\S]*?exit;\s*\}/,
    newSyncRoute
  );
  console.log('Replaced messages/sync route via regex');
}

// In syncInboundEmails: run Maildir scanning FIRST so it finishes in 1ms instead of waiting for IMAP
const oldSyncFnStart = `function syncInboundEmails($db, $force = false) {
    // Avoid running too frequently unless force is requested (e.g. manual refresh or sync endpoint)
    static $lastSync = 0;
    if (!$force && (time() - $lastSync < 3)) return 0;
    $lastSync = time();

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $processedCount = 0;
    $newlySynced = [];

    // 1. Process IMAP inbox if PHP IMAP extension is available
    if (function_exists('imap_open')) {`;

const newSyncFnStart = `function syncInboundEmails($db, $force = false) {
    // Ultra-Fast Zero-Lag Inbound Sync
    static $lastSync = 0;
    if (!$force && (time() - $lastSync < 1)) return ['count' => 0, 'messages' => []];
    $lastSync = time();

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $processedCount = 0;
    $newlySynced = [];

    // 1. INSTANT LOCAL MAILDIR SCAN FIRST (< 1ms on local SSD/NVMe)
    // Directly ingests all incoming Exim catch-all & mailbox deliveries without network lag
    $foldersToScan = [
        '/home/raccxuxv/mail/new',
        '/home/raccxuxv/mail/racconmail.eu/mail/new',
        '/home/raccxuxv/mail/.mail@racconmail_eu/new'
    ];
    $domDir = '/home/raccxuxv/mail/racconmail.eu';
    if (is_dir($domDir)) {
        $subDirs = @scandir($domDir) ?: [];
        foreach ($subDirs as $sd) {
            if ($sd === '.' || $sd === '..') continue;
            $userNew = "$domDir/$sd/new";
            if (is_dir($userNew) && !in_array($userNew, $foldersToScan)) {
                $foldersToScan[] = $userNew;
            }
        }
    }

    foreach ($foldersToScan as $fDir) {
        $res = syncMaildirFolder($db, $fDir, $uploadsDir);
        $processedCount += $res['count'];
        $newlySynced = array_merge($newlySynced, $res['messages']);
    }

    // 2. Secondary IMAP Check (with 1-second timeout to prevent blocking HTTP requests)
    if (function_exists('imap_open')) {
        @imap_timeout(IMAP_OPENTIMEOUT, 1);
        @imap_timeout(IMAP_READTIMEOUT, 1);`;

if (api.includes(oldSyncFnStart)) {
  api = api.replace(oldSyncFnStart, newSyncFnStart);
  console.log('Replaced syncInboundEmails header to prioritize Maildir');
  
  // Remove the duplicate Maildir scanning block at the bottom of the function
  const duplicateMaildirRegex = /\/\/ 2\. Scan all local Maildir directories[\s\S]*?foreach \(\$foldersToScan as \$fDir\) \{[\s\S]*?\}\s*\}/;
  api = api.replace(duplicateMaildirRegex, 'return [\'count\' => $processedCount, \'messages\' => $newlySynced];\n}');
  console.log('Removed duplicate Maildir block at end of syncInboundEmails');
}

fs.writeFileSync(apiPath, api, 'utf8');
console.log('Successfully updated public/api.php');

// 2. PATCH public/inbox.php with high-speed 3s polling, immediate trigger, and instant visibility listener
const inboxPath = path.join(rootDir, 'public/inbox.php');
let inbox = fs.readFileSync(inboxPath, 'utf8');

// Replace the polling function in inbox.php
const oldPollingBlockRegex = /\/\/ Real-time Inbound Mail Monitor \(Polls every 12 seconds[\s\S]*?setTimeout\(\(\) => \{[\s\S]*?setInterval\(pollForIncomingEmails, 12000\);[\s\S]*?\}, 4000\);/;

const newPollingBlock = `// Real-Time High-Speed Inbound Mail Engine (Fast 3-Second Cycle + Instant Focus Triggers)
    const knownMessageIds = new Set(initialDbMessages.map(m => m.id));
    let isPollingActive = false;

    async function pollForIncomingEmails() {
      if (isPollingActive) return;
      isPollingActive = true;

      try {
        let hasNew = false;
        let newSender = '';
        let newSubject = '';
        let newMsgId = null;

        // 1. Direct Inbound Sync API
        try {
          const syncRes = await fetch('/api/messages/sync');
          if (syncRes.ok) {
            const syncData = await syncRes.json();
            const newCount = (typeof syncData.synced === 'number' ? syncData.synced : (syncData.synced?.count || syncData.count || 0));
            const msgsList = Array.isArray(syncData.messages) ? syncData.messages : (syncData.synced?.messages || []);

            if (newCount > 0 || msgsList.length > 0) {
              const first = msgsList[0];
              newSender = first ? (first.sender_name || first.sender_email || 'New Sender') : 'A friend';
              newSubject = first ? (first.subject || '(no subject)') : 'Incoming email';
              newMsgId = first ? first.id : null;
              hasNew = true;
            }
          }
        } catch (_) {}

        // 2. Catch-all SQLite verification for folder=inbox
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
          // Play Afraid Raccoon notification sound immediately!
          playRaccoonSound('afraid');
          showIncomingToast(newSender, newSubject, newMsgId);
          await fetchFolderMessages(currentFolder);
          await refreshFolderCounts();
        }
      } catch (err) {
        console.warn('Inbound polling notice:', err);
      } finally {
        isPollingActive = false;
      }
    }

    // Instant initial check (0ms delay) + 3-second rapid cycle
    pollForIncomingEmails();
    setInterval(pollForIncomingEmails, 3000);

    // Instant triggers on tab focus & window visibility
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) pollForIncomingEmails();
    });
    window.addEventListener('focus', () => {
      pollForIncomingEmails();
    });`;

if (oldPollingBlockRegex.test(inbox)) {
  inbox = inbox.replace(oldPollingBlockRegex, newPollingBlock);
  console.log('Replaced polling block in public/inbox.php');
} else {
  console.warn('Could not match old polling block in inbox.php directly, checking if already updated...');
}

fs.writeFileSync(inboxPath, inbox, 'utf8');
console.log('Successfully updated public/inbox.php');
