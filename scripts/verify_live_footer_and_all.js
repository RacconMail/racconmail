const https = require('https');

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

async function verifyAll() {
  console.log('=== RUNNING LIVE VERIFICATION FOR LEGAL, FOOTERS & VISIBILITY ===\n');
  let passed = 0;
  let total = 0;

  function assert(condition, testName) {
    total++;
    if (condition) {
      passed++;
      console.log(`[PASS] ${testName}`);
    } else {
      console.error(`[FAIL] ${testName}`);
    }
  }

  // Test 1: /legal/privacy
  const p1 = await request({ hostname: 'racconmail.eu', path: '/legal/privacy', method: 'GET' });
  assert(p1.statusCode === 200, '/legal/privacy returns 200 OK');
  assert(p1.body.includes('Privacy Policy'), '/legal/privacy contains "Privacy Policy" title');
  assert(p1.body.includes('color-scheme') && p1.body.includes('light'), '/legal/privacy has color-scheme: light to prevent dark inversion');
  assert(p1.body.includes('support@racconmail.eu'), '/legal/privacy has support email contact');

  // Test 2: /legal/terms
  const t1 = await request({ hostname: 'racconmail.eu', path: '/legal/terms', method: 'GET' });
  assert(t1.statusCode === 200, '/legal/terms returns 200 OK');
  assert(t1.body.includes('Terms of Service'), '/legal/terms contains "Terms of Service" title');
  assert(t1.body.includes('color-scheme') && t1.body.includes('light'), '/legal/terms has color-scheme: light');

  // Test 3: Fallback & Root URLs
  const p2 = await request({ hostname: 'racconmail.eu', path: '/privacy', method: 'GET' });
  assert(p2.statusCode === 200 && p2.body.includes('Privacy Policy'), '/privacy resolves with Privacy Policy content (200 OK)');

  const t2 = await request({ hostname: 'racconmail.eu', path: '/terms', method: 'GET' });
  assert(t2.statusCode === 200 && t2.body.includes('Terms of Service'), '/terms resolves with Terms of Service content (200 OK)');

  const p3 = await request({ hostname: 'racconmail.eu', path: '/privacy.html', method: 'GET' });
  assert(p3.statusCode === 200 && p3.body.includes('Privacy Policy'), '/privacy.html resolves with Privacy Policy content (200 OK)');

  const t3 = await request({ hostname: 'racconmail.eu', path: '/terms.html', method: 'GET' });
  assert(t3.statusCode === 200 && t3.body.includes('Terms of Service'), '/terms.html resolves with Terms of Service content (200 OK)');

  // Test 4: Landing Page Links
  const idx = await request({ hostname: 'racconmail.eu', path: '/', method: 'GET' });
  assert(idx.statusCode === 200, 'Landing page returns 200 OK');
  assert(idx.body.includes('href="/legal/privacy"'), 'Landing page Card 2 links directly to /legal/privacy');
  assert(idx.body.includes('href="/legal/terms"'), 'Landing page links to /legal/terms');
  assert(!idx.body.includes('bg-slate-1000'), 'Landing page has valid tailwind classes (no bg-slate-1000)');

  // Test 5: News Wire Universal Footer
  const news = await request({ hostname: 'racconmail.eu', path: '/news', method: 'GET' });
  assert(news.statusCode === 200, '/news returns 200 OK');
  assert(news.body.includes('<footer') && news.body.includes('/legal/terms') && news.body.includes('/legal/privacy'), '/news has complete footer with legal links');

  // Test 6: Forum Universal Footer
  const forum = await request({ hostname: 'racconmail.eu', path: '/forum', method: 'GET' });
  assert(forum.statusCode === 200, '/forum returns 200 OK');
  assert(forum.body.includes('<footer') && !forum.body.includes('footer class="lg:hidden'), '/forum footer is NOT lg:hidden (visible on all screens)');
  assert(forum.body.includes('/legal/terms') && forum.body.includes('/legal/privacy'), '/forum footer includes Terms and Privacy');

  // Test 7: Stories Universal Footer & Centered Layout on Mobile
  const stories = await request({ hostname: 'racconmail.eu', path: '/stories', method: 'GET' });
  assert(stories.statusCode === 200, '/stories returns 200 OK');
  assert(stories.body.includes('<footer') && !stories.body.includes('footer class="lg:hidden'), '/stories footer is NOT lg:hidden (visible on all screens)');
  assert(stories.body.includes('text-center md:text-left'), '/stories hero text is centered on mobile');
  assert(stories.body.includes('flex flex-col-reverse sm:flex-row items-center sm:items-start justify-between gap-4 text-center sm:text-left'), '/stories catalog cards are centered on mobile');

  // Test 8: Authenticated Webmail Inbox & Trash Alignment
  const loginPost = JSON.stringify({ email: 'test_auto_user_1@racconmail.eu', password: 'TestPassword123!' });
  const loginRes = await request({
    hostname: 'racconmail.eu',
    path: '/api/auth/login',
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(loginPost) }
  }, loginPost);

  let token = null;
  try {
    const parsed = JSON.parse(loginRes.body);
    token = parsed.token || parsed.raccon_token;
  } catch (_) {}

  assert(token !== null, 'Authenticated session token acquired for webmail testing');

  if (token) {
    const inbox = await request({
      hostname: 'racconmail.eu',
      path: '/inbox?token=' + encodeURIComponent(token),
      method: 'GET',
      headers: { 'Cookie': 'raccon_token=' + token }
    });

    assert(inbox.statusCode === 200, '/inbox returns 200 OK for authenticated member');
    assert(inbox.body.includes('id="badge-trash"'), '/inbox contains badge-trash');
    assert(!inbox.body.includes('group/trash flex items-center justify-between'), '/inbox trash button has clean 2-child structure without 24px inner offset');
    assert(inbox.body.includes('id="trash-empty-btn"'), '/inbox contains dedicated Empty Trash action in middle header');
  }

  console.log(`\n=== RESULTS: ${passed} / ${total} TESTS PASSED ===`);
  if (passed === total) {
    console.log('ALL TESTS PASSED WITH 100% SUCCESS!');
  } else {
    process.exit(1);
  }
}

verifyAll().catch(e => {
  console.error('Verification failed with error:', e);
  process.exit(1);
});
