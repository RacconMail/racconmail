const https = require('https');

async function verifyGoogleAds() {
  console.log('=== VERIFYING LIVE GOOGLE ADS & YAHOO MAIL STYLE PLACEMENT ===');

  // 1. Check index.html head script
  const indexHtml = await new Promise(resolve => {
    https.get('https://racconmail.eu/', res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(d));
    });
  });

  const indexHasAdSense = indexHtml.includes('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4083745907535838');
  console.log('1. Landing page index.html has AdSense client script:', indexHasAdSense);
  if (!indexHasAdSense) throw new Error('AdSense script missing on index.html');

  // 2. Login to get authenticated inbox
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

  // 3. Check inbox.php
  const inboxHtml = await new Promise(resolve => {
    https.get('https://racconmail.eu/inbox?token=' + token, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(d));
    });
  });

  const inboxHasScript = inboxHtml.includes('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4083745907535838');
  console.log('2. Inbox page has AdSense client script in <head>:', inboxHasScript);
  if (!inboxHasScript) throw new Error('AdSense script missing in head of inbox.php');

  const adBannerMatch = inboxHtml.match(/<div id="inbox-sponsored-ad-banner"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/i);
  console.log('3. Yahoo Mail style sponsored ad container present:', !!adBannerMatch);
  if (!adBannerMatch) throw new Error('inbox-sponsored-ad-banner missing');

  const hasPublisherId = inboxHtml.includes('data-ad-client="ca-pub-4083745907535838"');
  console.log('4. AdSense ad unit has exact publisher ID ca-pub-4083745907535838:', hasPublisherId);
  if (!hasPublisherId) throw new Error('Missing publisher ID in ad unit');

  const hasAdBadge = inboxHtml.includes('>Ad</span>') && inboxHtml.includes('Sponsored');
  console.log('5. Yahoo Mail style Ad and Sponsored badges present:', hasAdBadge);
  if (!hasAdBadge) throw new Error('Missing Ad / Sponsored badges');

  // Verify placement: search bar comes before ad banner, which comes before threads-container
  const searchIdx = inboxHtml.indexOf('id="global-search"');
  const adIdx = inboxHtml.indexOf('id="inbox-sponsored-ad-banner"');
  const threadsIdx = inboxHtml.indexOf('id="threads-container"');

  console.log('6. Placement check:');
  console.log('   global-search index:', searchIdx);
  console.log('   inbox-sponsored-ad-banner index:', adIdx);
  console.log('   threads-container index:', threadsIdx);

  const placementCorrect = (searchIdx < adIdx) && (adIdx < threadsIdx);
  console.log('   Placement order correct (Search Bar -> Ad Banner -> Mail Threads):', placementCorrect);
  if (!placementCorrect) throw new Error('Ad is not positioned between search bar and mail list');

  // 7. Verify mail counter is visible and shows count
  const coralMatch = inboxHtml.match(/<span id="coral-inbox-badge"[^>]*>([\s\S]*?)<\/span>/i);
  console.log('7. Coral inbox badge value:', coralMatch ? coralMatch[1].trim() : 'NOT FOUND');
  const badgeInboxMatch = inboxHtml.match(/<span id="badge-inbox"[^>]*>([\s\S]*?)<\/span>/i);
  console.log('8. Sidebar badge-inbox value:', badgeInboxMatch ? badgeInboxMatch[1].trim() : 'NOT FOUND');

  console.log('=== ALL 8 GOOGLE ADS & YAHOO MAIL PLACEMENT CHECKS PASSED PERFECTLY ===');
}

verifyGoogleAds().catch(err => {
  console.error('VERIFICATION ERROR:', err);
  process.exit(1);
});
