const https = require('https');
const vm = require('vm');

async function runLiveVerification() {
  console.log('=== STARTING DEEP LIVE VERIFICATION FOR INBOX & COUNTERS ===');

  // Step 1: Login
  const postData = JSON.stringify({ email: 'support@racconmail.eu', password: 'Romania1@' });
  const loginRes = await new Promise(resolve => {
    const req = https.request('https://racconmail.eu/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(postData) }
    }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(JSON.parse(data)));
    });
    req.write(postData);
    req.end();
  });

  const token = loginRes.token;
  if (!token) throw new Error('Could not log in to obtain token');
  console.log('1. Logged in successfully. Token acquired.');

  // Find message id
  const msgsRes = await new Promise(resolve => {
    https.get('https://racconmail.eu/api/messages?folder=inbox&token=' + token, {
      headers: { 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
  });

  const msgId = msgsRes.messages?.[0]?.id;
  if (!msgId) throw new Error('No message found in inbox to test with');
  console.log(`2. Found test message in inbox: ${msgId}`);

  // Subtest A: Mark as read (is_read = 1) and test that counter hides!
  await new Promise(resolve => {
    const req = https.request(`https://racconmail.eu/api/messages/${encodeURIComponent(msgId)}/read?token=${token}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
    req.write(JSON.stringify({ is_read: 1 }));
    req.end();
  });

  // Verify /api/folders shows unread = 0
  const foldersRead = await new Promise(resolve => {
    https.get(`https://racconmail.eu/api/folders?token=${token}`, {
      headers: { 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
  });
  console.log('3. Folders unread count after marking read:', foldersRead.standard?.inbox?.unread);
  if (foldersRead.standard?.inbox?.unread !== 0) throw new Error('Expected inbox unread to be 0');

  // Fetch live /inbox HTML
  const inboxHtmlRead = await new Promise(resolve => {
    https.get(`https://racconmail.eu/inbox?token=${token}`, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(d));
    });
  });

  // Check badges in HTML
  const checkBadgeHidden = (id, html) => {
    const re = new RegExp(`<[^>]+id="${id}"[^>]*class="([^"]*)"[^>]*>`, 'i');
    const m = html.match(re);
    if (!m) return { found: false, hidden: false, tag: '' };
    const classes = m[1].split(/\s+/);
    return { found: true, hidden: classes.includes('hidden'), tag: m[0] };
  };

  const badgeInboxRead = checkBadgeHidden('badge-inbox', inboxHtmlRead);
  const coralInboxRead = checkBadgeHidden('coral-inbox-badge', inboxHtmlRead);
  const badgeUnreadRead = checkBadgeHidden('badge-unread', inboxHtmlRead);
  const markAllBtnRead = checkBadgeHidden('mark-all-read-btn', inboxHtmlRead);
  const mobileBadgeRead = checkBadgeHidden('mobile-bottom-inbox-badge', inboxHtmlRead);

  console.log('4. Badges hidden when count is 0:');
  console.log('   #badge-inbox hidden:', badgeInboxRead.hidden, badgeInboxRead.tag);
  console.log('   #coral-inbox-badge hidden:', coralInboxRead.hidden, coralInboxRead.tag);
  console.log('   #badge-unread hidden:', badgeUnreadRead.hidden, badgeUnreadRead.tag);
  console.log('   #mark-all-read-btn hidden:', markAllBtnRead.hidden, markAllBtnRead.tag);
  console.log('   #mobile-bottom-inbox-badge hidden:', mobileBadgeRead.hidden, mobileBadgeRead.tag);

  if (!badgeInboxRead.hidden) throw new Error('Expected #badge-inbox to be hidden when unread is 0');
  if (!coralInboxRead.hidden) throw new Error('Expected #coral-inbox-badge to be hidden when unread is 0');
  if (!badgeUnreadRead.hidden) throw new Error('Expected #badge-unread to be hidden when unread is 0');

  // Subtest B: Mark as unread (is_read = 0) and verify that badge appears with count 1!
  await new Promise(resolve => {
    const req = https.request(`https://racconmail.eu/api/messages/${encodeURIComponent(msgId)}/read?token=${token}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
    req.write(JSON.stringify({ is_read: 0 }));
    req.end();
  });

  const foldersUnread = await new Promise(resolve => {
    https.get(`https://racconmail.eu/api/folders?token=${token}`, {
      headers: { 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
  });
  console.log('5. Folders unread count after marking unread:', foldersUnread.standard?.inbox?.unread);
  if (foldersUnread.standard?.inbox?.unread !== 1) throw new Error('Expected inbox unread to be 1');

  const inboxHtmlUnread = await new Promise(resolve => {
    https.get(`https://racconmail.eu/inbox?token=${token}`, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(d));
    });
  });

  const badgeInboxUnread = checkBadgeHidden('badge-inbox', inboxHtmlUnread);
  const coralInboxUnread = checkBadgeHidden('coral-inbox-badge', inboxHtmlUnread);
  const markAllBtnUnread = checkBadgeHidden('mark-all-read-btn', inboxHtmlUnread);

  console.log('6. Badges visible when count is 1:');
  console.log('   #badge-inbox visible (not hidden):', !badgeInboxUnread.hidden, badgeInboxUnread.tag);
  console.log('   #coral-inbox-badge visible (not hidden):', !coralInboxUnread.hidden, coralInboxUnread.tag);
  console.log('   #mark-all-read-btn visible (not hidden):', !markAllBtnUnread.hidden, markAllBtnUnread.tag);

  if (badgeInboxUnread.hidden) throw new Error('Expected #badge-inbox to be visible when unread is 1');
  if (coralInboxUnread.hidden) throw new Error('Expected #coral-inbox-badge to be visible when unread is 1');

  // Subtest C: Test Mark All Read batch endpoint
  const batchRes = await new Promise(resolve => {
    const req = https.request(`https://racconmail.eu/api/messages/batch?token=${token}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
    req.write(JSON.stringify({ action: 'mark_all_read', folder: 'inbox' }));
    req.end();
  });
  console.log('7. Mark all read batch endpoint response:', batchRes);
  if (!batchRes.success) throw new Error('Batch mark_all_read failed');

  // Verify count is back to 0
  const foldersAfterBatch = await new Promise(resolve => {
    https.get(`https://racconmail.eu/api/folders?token=${token}`, {
      headers: { 'Authorization': 'Bearer ' + token }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
  });
  console.log('8. Folders unread count after mark_all_read:', foldersAfterBatch.standard?.inbox?.unread);
  if (foldersAfterBatch.standard?.inbox?.unread !== 0) throw new Error('Expected inbox unread to be 0 after batch mark all read');

  // Subtest D: Reader Toolbar elements
  const readerToggleBtn = inboxHtmlRead.includes('id="reader-toggle-read-btn"');
  console.log('9. Reader toolbar toggle read button present:', readerToggleBtn);
  if (!readerToggleBtn) throw new Error('Missing reader-toggle-read-btn in inbox HTML');

  console.log('=== ALL LIVE VERIFICATION CHECKS PASSED PERFECTLY (9/9) ===');
}

runLiveVerification().catch(err => {
  console.error('VERIFICATION ERROR:', err);
  process.exit(1);
});
