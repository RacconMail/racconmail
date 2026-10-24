const https = require('https');

async function get(url) {
  return new Promise(resolve => {
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0' }, rejectUnauthorized: false }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: d }));
    });
  });
}

async function verify() {
  console.log('=== VERIFYING LIVE PRODUCTION REBRANDING ===\n');

  // 1. Assets
  const assets = [
    '/favicon.svg',
    '/favicon.ico',
    '/favicon.png',
    '/favicon-32x32.png',
    '/apple-touch-icon.png',
    '/raccoon-logo.svg',
    '/raccoon-logo.png',
    '/manifest.json'
  ];

  for (const a of assets) {
    const r = await get('https://racconmail.eu' + a);
    const ct = r.headers['content-type'] || 'unknown';
    const cl = r.headers['content-length'] || r.body.length;
    console.log(`[Asset] ${a}: Status ${r.status} (${ct}) - ${cl} bytes`);
  }

  // 2. Pages
  const pages = [
    { url: 'https://racconmail.eu/', name: 'Landing (index.html)' },
    { url: 'https://racconmail.eu/inbox', name: 'Webmail (inbox.php)' },
    { url: 'https://racconmail.eu/forum', name: 'Forum (forum.html)' },
    { url: 'https://racconmail.eu/stories', name: 'Stories (stories.html)' },
    { url: 'https://racconmail.eu/news', name: 'News (news.html)' },
    { url: 'https://racconmail.eu/auth', name: 'Auth (auth.html)' },
    { url: 'https://racconmail.eu/admin', name: 'Admin (admin.html)' },
    { url: 'https://racconmail.eu/legal_privacy.html', name: 'Privacy Policy' },
    { url: 'https://racconmail.eu/legal_terms.html', name: 'Terms of Service' }
  ];

  console.log('\n=== VERIFYING PAGES FOR SVG LOGOS & FAVICON SUITE ===\n');
  for (const p of pages) {
    const r = await get(p.url);
    const hasSvgLogo = r.body.includes('/raccoon-logo.svg');
    const hasFaviconSvg = r.body.includes('/favicon.svg');
    const hasOldV2 = /Mobile v2\.0/i.test(r.body);
    console.log(`[Page] ${p.name}: Status ${r.status} | Has SVG logo: ${hasSvgLogo} | Has SVG favicon: ${hasFaviconSvg} | Has old v2.0 text: ${hasOldV2}`);
  }

  // 3. Downloads
  console.log('\n=== VERIFYING DOWNLOADS ===\n');
  const downloads = [
    '/downloads/RacconMail.apk',
    '/downloads/RacconMail-v2.0.apk',
    '/downloads/RacconMail-Desktop-v2.0-Windows.zip'
  ];

  for (const d of downloads) {
    const r = await get('https://racconmail.eu' + d);
    const cl = r.headers['content-length'] || r.body.length;
    console.log(`[Download] ${d}: Status ${r.status} - ${cl} bytes`);
  }
}

verify().catch(console.error);
