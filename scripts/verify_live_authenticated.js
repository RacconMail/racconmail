const https = require('https');

function checkHead(url) {
  return new Promise(resolve => {
    const req = https.request(url, { method: 'HEAD', rejectUnauthorized: false }, res => {
      resolve({ status: res.statusCode, headers: res.headers });
    });
    req.on('error', e => resolve({ error: e.message }));
    req.end();
  });
}

async function verify() {
  console.log('=== VERIFYING LIVE PRODUCTION (racconmail.eu) ===\n');

  // 1. Check Authenticated Inbox
  console.log('1. Logging in as support@racconmail.eu to fetch live /inbox HTML...');
  const postData = JSON.stringify({ email: 'support@racconmail.eu', password: 'Romania1@' });

  const loginRes = await new Promise(resolve => {
    const req = https.request('https://racconmail.eu/api/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData)
      }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: d }));
    });
    req.write(postData); req.end();
  });

  const cookies = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');
  console.log('Login Status:', loginRes.status);

  const inboxRes = await new Promise(resolve => {
    https.get('https://racconmail.eu/inbox', {
      headers: {
        'Cookie': cookies,
        'User-Agent': 'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/UQ1A.240105.004) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
      }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, body: d }));
    });
  });

  console.log(`Live Inbox Status: HTTP ${inboxRes.status} (Length: ${inboxRes.body.length} bytes)`);

  const hasQuickRailMediaCss = inboxRes.body.includes('@media (max-width: 1279px)') && inboxRes.body.includes('#quick-rail {\n        display: none !important;\n      }');
  const hasFabFix = inboxRes.body.includes('id="mobile-compose-fab"') && inboxRes.body.includes('class="flex md:hidden');
  const hasReplyTopFix = inboxRes.body.includes('inline-reply-recipient-name') && inboxRes.body.includes('max-w-[calc(100%-85px)]');
  const hasSidebarBadgeFix = inboxRes.body.includes('#sidebar-panel .sidebar-badge');
  const hasThreadPaddingFix = inboxRes.body.includes('p-3 sm:p-6 md:p-8 space-y-4 sm:space-y-6 pb-32');

  console.log('\n--- VERIFICATION CHECKS ---');
  console.log('1. Quick-Rail hidden under 1280px via CSS:', hasQuickRailMediaCss ? 'PASS ✅' : 'FAIL ❌');
  console.log('2. Compose FAB display & class fix:', hasFabFix ? 'PASS ✅' : 'FAIL ❌');
  console.log('3. Reply Card recipient pill & Cc/Bcc no-overlap:', hasReplyTopFix ? 'PASS ✅' : 'FAIL ❌');
  console.log('4. Expandable menu badges ml-auto shrink-0 CSS:', hasSidebarBadgeFix ? 'PASS ✅' : 'FAIL ❌');
  console.log('5. Mobile thread conversation padding fix:', hasThreadPaddingFix ? 'PASS ✅' : 'FAIL ❌');

  // 2. Check Downloads
  console.log('\n--- 2. VERIFYING LIVE DOWNLOAD ENDPOINTS ---');
  const files = [
    'https://racconmail.eu/downloads/RacconMail.apk',
    'https://racconmail.eu/downloads/RacconMail-v2.0.apk',
    'https://racconmail.eu/downloads/RacconMail-Desktop-v2.0-Windows.zip',
    'https://racconmail.eu/downloads/RacconMail-Setup.exe'
  ];

  for (const f of files) {
    const head = await checkHead(f);
    console.log(`${f.split('/').pop()}: HTTP ${head.status} | Size: ${head.headers['content-length']} bytes ${head.status === 200 ? '✅' : '❌'}`);
  }
}

verify().catch(console.error);
