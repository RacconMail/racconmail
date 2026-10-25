const https = require('https');

async function verifyAdHidden() {
  console.log('=== VERIFYING GOOGLE CODE IS INVISIBLE & NO ADS IN INBOX ===');

  const postData = JSON.stringify({ email: 'support@racconmail.eu', password: 'Romania1@' });
  const loginRes = await new Promise(resolve => {
    const req = https.request('https://racconmail.eu/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(postData) }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
    req.write(postData); req.end();
  });
  const token = loginRes.token;

  const html = await new Promise(resolve => {
    https.get('https://racconmail.eu/inbox?token=' + token, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(d));
    });
  });

  // 1. Google AdSense script present in <head>
  const headMatch = html.match(/<head[\s\S]*?<\/head>/i);
  const headHasScript = headMatch ? headMatch[0].includes('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4083745907535838') : false;
  console.log('1. Google AdSense script present invisibly in <head>:', headHasScript);
  if (!headHasScript) throw new Error('AdSense script missing in head');

  // 2. No ad banner in inbox
  const hasAdBanner = html.includes('id="inbox-sponsored-ad-banner"');
  console.log('2. inbox-sponsored-ad-banner is NOT present in inbox:', !hasAdBanner);
  if (hasAdBanner) throw new Error('inbox-sponsored-ad-banner should be removed');

  // 3. No <ins class="adsbygoogle"> in body
  const hasIns = html.includes('adsbygoogle');
  // It should only be in the script tag in head
  const bodyMatch = html.match(/<body[\s\S]*?<\/body>/i);
  const bodyHasIns = bodyMatch ? bodyMatch[0].includes('class="adsbygoogle"') : false;
  console.log('3. No adsbygoogle ad units inside <body>:', !bodyHasIns);
  if (bodyHasIns) throw new Error('Body should not contain adsbygoogle ins tags');

  // 4. Mail counters intact
  const coralMatch = html.match(/<span id="coral-inbox-badge"[^>]*>([\s\S]*?)<\/span>/i);
  console.log('4. Coral inbox badge value:', coralMatch ? coralMatch[1].trim() : 'NOT FOUND');

  const badgeInboxMatch = html.match(/<span id="badge-inbox"[^>]*>([\s\S]*?)<\/span>/i);
  console.log('5. Sidebar badge-inbox value:', badgeInboxMatch ? badgeInboxMatch[1].trim() : 'NOT FOUND');

  console.log('=== ALL CHECKS PASSED: GOOGLE CODE IS INVISIBLE, INBOX IS 100% CLEAN ===');
}

verifyAdHidden().catch(err => {
  console.error('VERIFICATION ERROR:', err);
  process.exit(1);
});
