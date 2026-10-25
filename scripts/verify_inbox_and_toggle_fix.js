const https = require('https');
const vm = require('vm');

function request(options, postData = null) {
  return new Promise((resolve, reject) => {
    const req = https.request(options, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, body: data }));
    });
    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

async function verifyFixes() {
  console.log('=== VERIFYING INBOX & TOGGLE MENU FIXES ===\n');
  let passed = 0;
  let total = 0;

  function assert(condition, message) {
    total++;
    if (condition) {
      console.log(`[PASS] ${message}`);
      passed++;
    } else {
      console.error(`[FAIL] ${message}`);
    }
  }

  // 1. Authenticate as support@racconmail.eu
  const loginPost = JSON.stringify({ email: 'support@racconmail.eu', password: 'Romania1@' });
  const loginRes = await request({
    hostname: 'racconmail.eu',
    path: '/api/auth/login',
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(loginPost) }
  }, loginPost);

  assert(loginRes.statusCode === 200, 'Authenticated successfully with Webmail API');
  const cookie = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');
  const loginData = JSON.parse(loginRes.body);
  const token = loginData.token || loginData.raccon_token;

  // 2. Test GET /api/messages?folder=inbox
  const msgsRes = await request({
    hostname: 'racconmail.eu',
    path: '/api/messages?folder=inbox',
    method: 'GET',
    headers: { 'Cookie': cookie + '; raccon_token=' + token }
  });

  assert(msgsRes.statusCode === 200, '/api/messages?folder=inbox returns 200 OK');
  assert(msgsRes.body.length > 0, `/api/messages?folder=inbox returned non-empty body (length: ${msgsRes.body.length})`);
  
  let msgsJson = null;
  try {
    msgsJson = JSON.parse(msgsRes.body);
  } catch (e) {
    console.error('JSON parse error:', e.message);
  }
  assert(msgsJson !== null && Array.isArray(msgsJson.messages), '/api/messages?folder=inbox returns valid JSON with messages array');
  assert(msgsJson.messages.length > 0, `Inbox contains ${msgsJson.messages.length} message(s) successfully decoded`);

  // 3. Fetch rendered live /inbox HTML
  const inboxRes = await request({
    hostname: 'racconmail.eu',
    path: '/inbox?token=' + encodeURIComponent(token),
    method: 'GET',
    headers: { 'Cookie': cookie + '; raccon_token=' + token }
  });

  assert(inboxRes.statusCode === 200, '/inbox returns 200 OK for authenticated session');
  const html = inboxRes.body;

  // 4. Verify no broken initialDbMessages syntax
  assert(!html.includes('const initialDbMessages =  || [];'), 'No broken "initialDbMessages =  || [];" syntax in HTML');
  assert(html.includes('const initialDbMessages = [') || html.includes('const initialDbMessages = []'), 'initialDbMessages is a valid JSON array');

  // 5. Test EVERY script in live inbox HTML for JS syntax errors
  const regex = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
  let match;
  let scriptIndex = 0;
  let syntaxErrors = 0;

  while ((match = regex.exec(html)) !== null) {
    scriptIndex++;
    const code = match[1];
    if (!code.trim()) continue;
    try {
      new vm.Script(code);
    } catch (err) {
      syntaxErrors++;
      console.error(`Script #${scriptIndex} Syntax Error:`, err.message);
    }
  }

  assert(syntaxErrors === 0, `All ${scriptIndex} inline scripts parsed with ZERO syntax errors`);

  // 6. Verify toggle menu buttons & functions
  assert(html.includes('toggleSidebarAction()'), 'toggleSidebarAction function is attached and referenced');
  assert(html.includes('id="btn-toggle-sidebar"'), 'btn-toggle-sidebar button exists in top togglebar');
  assert(html.includes('title="Toggle Folders & Menu"'), 'btn-toggle-sidebar has clean title');
  assert(html.includes('Folders</span>'), 'Mobile bottom bar contains dedicated "Folders" toggle menu button');
  assert(html.includes('Mailbox Folders</span>'), 'Mobile sidebar drawer contains dedicated close header');
  assert(html.includes('toggleAppSwitcher()'), 'toggleAppSwitcher function is referenced');
  assert(html.includes('toggleUserDropdown'), 'toggleUserDropdown function is referenced');

  console.log(`\n=== RESULTS: ${passed} / ${total} TESTS PASSED ===`);
  if (passed === total) {
    console.log('ALL INBOX AND TOGGLE MENU TESTS PASSED WITH 100% SUCCESS!');
  } else {
    process.exit(1);
  }
}

verifyFixes().catch(e => {
  console.error('Verification failed:', e);
  process.exit(1);
});
