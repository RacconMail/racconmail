const https = require('https');

function post(url, data) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const postData = JSON.stringify(data);
    const req = https.request({
      hostname: u.hostname,
      path: u.pathname + u.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData)
      }
    }, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, body }));
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

function getWithCookie(url, cookie) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const req = https.request({
      hostname: u.hostname,
      path: u.pathname + u.search,
      method: 'GET',
      headers: {
        'Cookie': cookie || '',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      }
    }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, body: data }));
    });
    req.on('error', reject);
    req.end();
  });
}

async function verifyLive() {
  console.log('Logging in to live app...');
  const loginRes = await post('https://racconmail.eu/api/auth/login', {
    email: 'support@racconmail.eu',
    password: 'Romania1@'
  });

  const json = JSON.parse(loginRes.body);
  const token = json.token;
  const cookie = 'raccon_token=' + token;

  console.log('Fetching live /inbox with authenticated session...');
  const inboxRes = await getWithCookie('https://racconmail.eu/inbox?token=' + encodeURIComponent(token), cookie);
  console.log('HTTP Status:', inboxRes.statusCode);

  const html = inboxRes.body;
  const checks = [
    { label: 'Master Select Checkbox', pass: html.includes('master-select-checkbox') },
    { label: 'Filter All Tab', pass: html.includes('d-filter-all') },
    { label: 'Filter Read Tab', pass: html.includes('d-filter-read') },
    { label: 'Filter Unread Tab', pass: html.includes('d-filter-unread') },
    { label: 'List Refresh Button', pass: html.includes('list-refresh-btn') },
    { label: 'Reader Counter < 1 of X >', pass: html.includes('reader-counter') },
    { label: 'Docked Inline Quick Reply Textarea', pass: html.includes('quick-reply-input') },
    { label: 'Inline Send Split Button', pass: html.includes('inline-send-btn') },
    { label: 'Inline CC Row', pass: html.includes('inline-cc-row') },
    { label: 'Inline BCC Row', pass: html.includes('inline-bcc-row') },
    { label: 'AI Assist Trigger', pass: html.includes('triggerInlineAiAssist') },
    { label: 'Date Grouping Function', pass: html.includes('getEmailDateInfo') },
    { label: 'Navigation Thread Function', pass: html.includes('navigateThread') },
    { label: 'Reader Fullscreen Function', pass: html.includes('toggleReaderFullscreen') },
    { label: 'Active Right Indicator Styling', pass: html.includes('border-r-slate-900') }
  ];

  console.table(checks);
  const allPassed = checks.every(c => c.pass);
  console.log('All verification checks passed:', allPassed);
  if (!allPassed) {
    process.exit(1);
  }
}

verifyLive().catch(console.error);
