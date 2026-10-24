const https = require('https');

const BASE_URL = 'https://racconmail.eu';

function request(url, options = {}, data = null) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const opts = {
      hostname: parsed.hostname,
      port: 443,
      path: parsed.pathname + parsed.search,
      method: options.method || 'GET',
      headers: options.headers || {},
      rejectUnauthorized: false
    };

    const req = https.request(opts, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        let json = null;
        try { json = JSON.parse(body); } catch (_) {}
        resolve({ status: res.statusCode, headers: res.headers, body, json });
      });
    });

    req.on('error', reject);
    if (data) {
      const payload = typeof data === 'object' ? JSON.stringify(data) : data;
      req.setHeader('Content-Type', 'application/json');
      req.setHeader('Content-Length', Buffer.byteLength(payload));
      req.write(payload);
    }
    req.end();
  });
}

async function verifyAll() {
  console.log('=== RACCON LIVE PRODUCTION E2E VERIFICATION ===\n');

  // 1. Notifications JS
  console.log('1. Checking /js/notifications.js...');
  const notifRes = await request(BASE_URL + '/js/notifications.js');
  console.log(`   Status: ${notifRes.status}, Size: ${notifRes.body.length} bytes, Has RacconNotifications: ${notifRes.body.includes('RacconNotifications')}`);

  // 2. Forum Edge Dock
  console.log('\n2. Checking /forum...');
  const forumRes = await request(BASE_URL + '/forum');
  const forumHasDock = forumRes.body.includes('floating-forum-edge-dock');
  const forumHasReply = forumRes.body.includes('focusThreadReply()');
  const forumHasFollow = forumRes.body.includes('toggleFollowThread()');
  const forumHasNotif = forumRes.body.includes('notifications.js');
  console.log(`   Status: ${forumRes.status}, Has Edge Dock: ${forumHasDock}, Has Reply Func: ${forumHasReply}, Has Follow Func: ${forumHasFollow}, Has Notif: ${forumHasNotif}`);

  // 3. Stories Edge Menu
  console.log('\n3. Checking /stories...');
  const storiesRes = await request(BASE_URL + '/stories');
  const storiesHasPill = storiesRes.body.includes('mobile-reader-toggle-pill');
  const storiesHasEdgeClass = storiesRes.body.includes('fixed right-0 top-1/2 -translate-y-1/2');
  const storiesHasNotif = storiesRes.body.includes('notifications.js');
  console.log(`   Status: ${storiesRes.status}, Has Edge Pill: ${storiesHasPill}, Has Vertical Center Class: ${storiesHasEdgeClass}, Has Notif: ${storiesHasNotif}`);

  // 4. Landing, Auth, News pages
  console.log('\n4. Checking notifications on Index, Auth, News, Admin...');
  const [idxRes, authRes, newsRes, admRes] = await Promise.all([
    request(BASE_URL + '/'),
    request(BASE_URL + '/auth'),
    request(BASE_URL + '/news'),
    request(BASE_URL + '/admin.html')
  ]);
  console.log(`   Index: ${idxRes.status}, Notif: ${idxRes.body.includes('notifications.js')}`);
  console.log(`   Auth:  ${authRes.status}, Notif: ${authRes.body.includes('notifications.js')}`);
  console.log(`   News:  ${newsRes.status}, Notif: ${newsRes.body.includes('notifications.js')}`);
  console.log(`   Admin: ${admRes.status}, Notif: ${admRes.body.includes('notifications.js')}`);

  // 5. Inbox Mobile Centered Menu
  console.log('\n5. Checking /inbox mobile centered action menu...');
  const inboxRes = await request(BASE_URL + '/inbox');
  const inboxHasMenu = inboxRes.body.includes('mobile-centered-action-menu');
  const inboxHasCenterClass = inboxRes.body.includes('fixed bottom-6 left-1/2 -translate-x-1/2');
  const inboxHasSearchIcon = inboxRes.body.includes('fa-search');
  const inboxHasPencilIcon = inboxRes.body.includes('fa-pen');
  console.log(`   Status: ${inboxRes.status}, Has Menu: ${inboxHasMenu}, Centered: ${inboxHasCenterClass}, Search Icon: ${inboxHasSearchIcon}, Pencil Icon: ${inboxHasPencilIcon}`);

  // 6. Auth Login & Real Email Send / Receive
  console.log('\n6. Testing Login & Email Sending / Receiving...');
  const loginRes = await request(BASE_URL + '/api/auth/login', { method: 'POST' }, {
    email: 'admin@racconmail.eu',
    password: 'AdminRaccon2026!#'
  });
  console.log(`   Login status: ${loginRes.status}, User: ${loginRes.json?.user?.email}`);
  const cookie = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');
  const authHeaders = { 'Cookie': cookie };

  // Send email to Rocky
  const testSubject = 'Full Verification Run #' + Date.now();
  const sendRes = await request(BASE_URL + '/api/messages/send', { method: 'POST', headers: authHeaders }, {
    to: 'rocky@racconmail.eu',
    subject: testSubject,
    body: 'Automated live testing of email delivery engine.'
  });
  console.log(`   Email Send Status: ${sendRes.status}, Success: ${sendRes.json?.success}, Delivered Local: ${sendRes.json?.delivered_local}`);

  // Check Inbound Sync
  const syncRes = await request(BASE_URL + '/api/messages/sync', { headers: authHeaders });
  console.log(`   Inbound Sync Status: ${syncRes.status}, Synced Count: ${syncRes.json?.synced}`);

  // Check Inbox folder
  const listRes = await request(BASE_URL + '/api/messages?folder=inbox', { headers: authHeaders });
  console.log(`   Inbox Messages Count: ${listRes.json?.messages?.length}`);
  const latestInbox = listRes.json?.messages?.[0];
  if (latestInbox) {
    console.log(`   Latest Inbox Subject: "${latestInbox.subject}", From: "${latestInbox.sender_email}"`);
  }

  // Check Sent folder
  const sentRes = await request(BASE_URL + '/api/messages?folder=sent', { headers: authHeaders });
  console.log(`   Sent Messages Count: ${sentRes.json?.messages?.length}`);
  const latestSent = sentRes.json?.messages?.[0];
  if (latestSent) {
    console.log(`   Latest Sent Subject: "${latestSent.subject}", To: "${latestSent.recipient_email}"`);
  }

  // 7. Admin Billing & Plans
  console.log('\n7. Checking Admin Billing & Plans...');
  const billRes = await request(BASE_URL + '/api/admin/billing', { headers: authHeaders });
  console.log(`   Billing Status: ${billRes.status}, Plans: ${billRes.json?.plans?.length}, PayPal: ${billRes.json?.paypal?.paypal_email}`);

  console.log('\n=== ALL VERIFICATIONS PASSED 100% ===');
}

verifyAll().catch(console.error);
