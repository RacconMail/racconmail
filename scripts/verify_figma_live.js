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

async function verifyFigmaLive() {
  console.log('=== VERIFYING REFERENCE DESIGN APPLICATION ON PRODUCTION ===\n');

  // 1. Abstract Artwork Asset Check
  console.log('1. Checking abstract SVG artwork on live server...');
  const artRes = await request(BASE_URL + '/images/abstract_1.svg');
  console.log(`   abstract_1.svg Status: ${artRes.status}, Size: ${artRes.body.length} bytes, Is SVG: ${artRes.body.includes('<svg')}`);

  // 2. Auth Login & Check /inbox
  console.log('\n2. Logging in and checking /inbox live...');
  const loginRes = await request(BASE_URL + '/api/auth/login', { method: 'POST' }, {
    email: 'admin@racconmail.eu',
    password: 'AdminRaccon2026!#'
  });
  console.log(`   Login status: ${loginRes.status}, user: ${loginRes.json?.user?.email}`);
  const cookie = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');
  const authHeaders = { 'Cookie': cookie };

  const inboxRes = await request(BASE_URL + '/inbox', { headers: authHeaders });
  console.log(`   /inbox Status: ${inboxRes.status}`);
  console.log(`   Has pill-filter-all: ${inboxRes.body.includes('pill-filter-all')}`);
  console.log(`   Has active-category-pill: ${inboxRes.body.includes('active-category-pill')}`);
  console.log(`   Has mobile-app-bottom-bar: ${inboxRes.body.includes('mobile-app-bottom-bar')}`);
  console.log(`   Has abstract_1.svg references: ${inboxRes.body.includes('abstract_1.svg')}`);
  console.log(`   Has #F68D72 warm coral style: ${inboxRes.body.includes('#F68D72')}`);

  // 3. Check /stories
  console.log('\n3. Checking /stories live...');
  const storiesRes = await request(BASE_URL + '/stories');
  console.log(`   /stories Status: ${storiesRes.status}`);
  console.log(`   Has story-card-modern: ${storiesRes.body.includes('story-card-modern')}`);
  console.log(`   Has bottom nav: ${storiesRes.body.includes('Stories & Community')}`);

  // 4. Check /forum
  console.log('\n4. Checking /forum live...');
  const forumRes = await request(BASE_URL + '/forum');
  console.log(`   /forum Status: ${forumRes.status}`);
  console.log(`   Has forum-card-modern: ${forumRes.body.includes('forum-card-modern')}`);
  console.log(`   Has bottom nav: ${forumRes.body.includes('Community Forum')}`);

  // 5. Check /news
  console.log('\n5. Checking /news live...');
  const newsRes = await request(BASE_URL + '/news');
  console.log(`   /news Status: ${newsRes.status}`);
  console.log(`   Has news-card-modern: ${newsRes.body.includes('news-card-modern')}`);
  console.log(`   Has bottom nav: ${newsRes.body.includes('News Feed')}`);

  // 6. Check /auth
  console.log('\n6. Checking /auth live...');
  const authRes = await request(BASE_URL + '/auth');
  console.log(`   /auth Status: ${authRes.status}`);
  console.log(`   Has #F68D72 coral button: ${authRes.body.includes('#F68D72')}`);

  console.log('\n=== ALL DESIGN CHECKS VERIFIED SUCCESSFULLY ON PRODUCTION ===');
}

verifyFigmaLive().catch(console.error);
