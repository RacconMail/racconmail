const https = require('https');

function httpsGet(urlPath) {
  return new Promise((resolve, reject) => {
    https.get('https://racconmail.eu' + urlPath, { rejectUnauthorized: false }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: d }));
    }).on('error', reject);
  });
}

function httpsPost(urlPath, postData = {}) {
  const payload = JSON.stringify(postData);
  return new Promise((resolve, reject) => {
    const req = https.request('https://racconmail.eu' + urlPath, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload)
      },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: d }));
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

async function runVerification() {
  console.log('=== STARTING LIVE PRODUCTION VERIFICATION (racconmail.eu) ===\n');

  // 1. Check Hourly Sync Status API
  console.log('1. Testing GET /api/admin/hourly-sync-status...');
  const statusRes = await httpsGet('/api/admin/hourly-sync-status');
  console.log('   Status Code:', statusRes.status);
  let statusJson = null;
  try {
    statusJson = JSON.parse(statusRes.body);
    console.log('   Success:', statusJson.success);
    console.log('   Auto-Sync Enabled:', statusJson.enabled);
    console.log('   Interval:', statusJson.interval_minutes, 'minutes');
    console.log('   Last Sync:', statusJson.last_sync);
    console.log('   Next Run in:', statusJson.next_sync_seconds, 'seconds');
    console.log('   Active Counts:', JSON.stringify(statusJson.counts));
  } catch (e) {
    console.error('   Failed to parse JSON:', statusRes.body.slice(0, 200));
  }

  // 2. Trigger Hourly Sync via API Key
  console.log('\n2. Testing POST /api/admin/hourly-sync?key=raccon_update_key_2026...');
  const syncRes = await httpsPost('/api/admin/hourly-sync?key=raccon_update_key_2026', {});
  console.log('   Status Code:', syncRes.status);
  try {
    const syncJson = JSON.parse(syncRes.body);
    console.log('   Sync Success:', syncJson.success);
    console.log('   Sync Message:', syncJson.message);
    console.log('   Execution Duration:', syncJson.duration, 'seconds');
    console.log('   Sync Results:', JSON.stringify(syncJson.sync?.counts || {}));
  } catch (e) {
    console.error('   Sync Response:', syncRes.body.slice(0, 200));
  }

  // 3. Verify Preset Avatars on Live Server
  console.log('\n3. Testing Preset Avatar Assets on Live CDN...');
  const avatarTests = [
    '/avatars/raccoon-default.svg',
    '/avatars/raccoon-gold-gear.png',
    '/avatars/raccoon-cyber-mechanic.png',
    '/avatars/raccoon-mascot.png',
    '/avatars/raccoon-portrait.png',
    '/avatars/raccoon-stealth-agent.svg',
    '/avatars/raccoon-sunset-coral.svg',
    '/avatars/raccoon-neon-matrix.svg'
  ];
  for (const av of avatarTests) {
    const avRes = await httpsGet(av);
    console.log(`   ${av} -> HTTP ${avRes.status} (${avRes.body.length} bytes)`);
  }

  // 4. Verify Sitemap.xml indexing dates
  console.log('\n4. Testing Sitemap.xml for search engine indexing...');
  const sitemapRes = await httpsGet('/sitemap.xml');
  console.log('   Sitemap Status:', sitemapRes.status);
  const hasToday = sitemapRes.body.includes('<lastmod>2026-10-25</lastmod>');
  console.log('   Contains 2026-10-25 indexing timestamp:', hasToday);
  const urlMatches = (sitemapRes.body.match(/<loc>/g) || []).length;
  console.log('   Indexed URL count in sitemap:', urlMatches);

  // 5. Verify Admin HTML contains Hourly Sync Widget
  console.log('\n5. Testing admin.html for Hourly Syndication Widget...');
  const adminRes = await httpsGet('/admin.html');
  const hasHourlyWidget = adminRes.body.includes('Automated Hourly Syndication Engine');
  const hasCountdown = adminRes.body.includes('hourly-next-countdown');
  const hasTriggerMethod = adminRes.body.includes('triggerHourlySyncNow');
  console.log('   admin.html contains Hourly Widget:', hasHourlyWidget);
  console.log('   admin.html contains Live Countdown element:', hasCountdown);
  console.log('   admin.html contains triggerHourlySyncNow JS method:', hasTriggerMethod);

  // 6. Verify Inbox Profile Photo Modal
  console.log('\n6. Testing inbox.php for Profile Photo Modal & Avatars...');
  const inboxRes = await httpsGet('/inbox');
  const hasAvatarModal = inboxRes.body.includes('modal-change-avatar');
  const hasAvatarDnd = inboxRes.body.includes('avatar-dropzone');
  const hasDiceRoll = inboxRes.body.includes('rollDiceAvatars');
  console.log('   inbox.php contains modal-change-avatar:', hasAvatarModal);
  console.log('   inbox.php contains drag & drop dropzone:', hasAvatarDnd);
  console.log('   inbox.php contains rollDiceAvatars method:', hasDiceRoll);

  // 7. Verify News Page
  console.log('\n7. Testing news.html live display...');
  const newsRes = await httpsGet('/news');
  const hasInPageReader = newsRes.body.includes('openArticleOnSamePage');
  const hasPaletteColors = newsRes.body.includes('#F68D72') && newsRes.body.includes('#0F172A');
  console.log('   news.html HTTP status:', newsRes.status);
  console.log('   news.html in-page reader present:', hasInPageReader);
  console.log('   news.html Webmail palette colors present:', hasPaletteColors);

  // 8. Verify Stories Page
  console.log('\n8. Testing stories.html live display...');
  const storiesRes = await httpsGet('/stories');
  const storiesHasAvatar = storiesRes.body.includes('modal-change-avatar');
  console.log('   stories.html HTTP status:', storiesRes.status);
  console.log('   stories.html change photo modal present:', storiesHasAvatar);

  console.log('\n=== ALL VERIFICATIONS FINISHED ===');
}

runVerification().catch(err => {
  console.error('Verification error:', err);
});