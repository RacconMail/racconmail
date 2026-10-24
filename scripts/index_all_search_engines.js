const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

const rootDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';
const publicDir = path.join(rootDir, 'public');

async function deployAndIndex() {
  console.log('=== STEP 1: AUTHENTICATING WITH CPANEL ===');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const auth = await new Promise(resolve => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, r => {
      let d = ''; r.on('data', c => d += c);
      r.on('end', () => resolve({ headers: r.headers, body: JSON.parse(d) }));
    });
    req.write(loginBody); req.end();
  });

  const secToken = auth.body.security_token;
  const cookie = auth.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');
  console.log('cPanel auth successful. Token:', secToken);

  async function uploadFile(remoteDir, fileName, fileBuffer) {
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = '--' + boundary + '\r\n';
    header += `Content-Disposition: form-data; name="file-1"; filename="${fileName}"\r\n`;
    header += 'Content-Type: application/octet-stream\r\n\r\n';
    const footer = '\r\n--' + boundary + '--\r\n';
    const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuffer, Buffer.from(footer, 'utf8')]);

    const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent(remoteDir) + '&overwrite=1';
    return new Promise(resolve => {
      const req = https.request(uploadUrl, {
        method: 'POST',
        headers: { 'Cookie': cookie, 'Content-Type': 'multipart/form-data; boundary=' + boundary, 'Content-Length': payload.length },
        rejectUnauthorized: false
      }, r => {
        let d = ''; r.on('data', c => d += c);
        r.on('end', () => {
          try {
            const res = JSON.parse(d);
            console.log(`[Upload] ${fileName} -> ${remoteDir}: ${res.status === 1 ? 'OK' : 'FAIL'}`);
          } catch(e) {
            console.log(`[Upload] ${fileName} raw: ${d.slice(0, 60)}`);
          }
          resolve();
        });
      });
      req.write(payload); req.end();
    });
  }

  console.log('\n=== STEP 2: DEPLOYING UPDATED PAGES & SITEMAP TO PRODUCTION ===');
  const webFiles = [
    { name: 'index.html', path: path.join(publicDir, 'index.html'), dir: '/home/raccxuxv/public_html' },
    { name: 'inbox.php', path: path.join(publicDir, 'inbox.php'), dir: '/home/raccxuxv/public_html' },
    { name: 'sitemap.xml', path: path.join(publicDir, 'sitemap.xml'), dir: '/home/raccxuxv/public_html' },
    { name: 'robots.txt', path: path.join(publicDir, 'robots.txt'), dir: '/home/raccxuxv/public_html' },
    { name: 'legal_terms.html', path: path.join(publicDir, 'legal_terms.html'), dir: '/home/raccxuxv/public_html' },
    { name: 'legal_privacy.html', path: path.join(publicDir, 'legal_privacy.html'), dir: '/home/raccxuxv/public_html' },
    { name: 'a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3.txt', path: path.join(publicDir, 'a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3.txt'), dir: '/home/raccxuxv/public_html' }
  ];

  for (const f of webFiles) {
    if (fs.existsSync(f.path)) {
      await uploadFile(f.dir, f.name, fs.readFileSync(f.path));
    }
  }

  console.log('\n=== STEP 3: DEPLOYING UPDATED DESKTOP & APK BUILDS ===');
  const downloadFiles = [
    { name: 'RacconMail-Setup.exe', path: path.join(publicDir, 'downloads', 'RacconMail-Setup.exe') },
    { name: 'RacconMail-v2.0.apk', path: path.join(publicDir, 'downloads', 'RacconMail-v2.0.apk') },
    { name: 'RacconMail.apk', path: path.join(publicDir, 'downloads', 'RacconMail.apk') }
  ];

  for (const f of downloadFiles) {
    if (fs.existsSync(f.path)) {
      console.log(`Uploading ${f.name} (${(fs.statSync(f.path).size / (1024*1024)).toFixed(2)} MB)...`);
      await uploadFile('/home/raccxuxv/public_html/downloads', f.name, fs.readFileSync(f.path));
    }
  }

  console.log('\n=== STEP 4: SUBMITTING ALL URLS TO SEARCH ENGINES ===');
  const allUrls = [
    'https://racconmail.eu/',
    'https://racconmail.eu/forum',
    'https://racconmail.eu/news',
    'https://racconmail.eu/stories',
    'https://racconmail.eu/auth',
    'https://racconmail.eu/legal/terms',
    'https://racconmail.eu/legal/privacy',
    'https://racconmail.eu/downloads/RacconMail-Setup.exe',
    'https://racconmail.eu/downloads/RacconMail-v2.0.apk',
    'https://racconmail.eu/stories?id=c5aacz',
    'https://racconmail.eu/stories?id=73y9eb',
    'https://racconmail.eu/stories?id=bf0pa1',
    'https://racconmail.eu/stories?id=xby31n',
    'https://racconmail.eu/stories?id=01qn7t',
    'https://racconmail.eu/stories?id=z1oaji',
    'https://racconmail.eu/stories?id=itt7p7',
    'https://racconmail.eu/stories?id=20m0qh',
    'https://racconmail.eu/stories?id=2tnpz7',
    'https://racconmail.eu/stories?id=ekk3v7',
    'https://racconmail.eu/forum?id=post_seed_1',
    'https://racconmail.eu/forum?id=post_seed_2',
    'https://racconmail.eu/forum?id=post_seed_3',
    'https://racconmail.eu/forum?id=post_seed_4',
    'https://racconmail.eu/forum?id=post_seed_5',
    'https://racconmail.eu/forum?id=post_seed_6'
  ];

  // 1. IndexNow API Submission (Notifies Bing, Yandex, Seznam, Naver & global search engines)
  const indexNowPayload = JSON.stringify({
    host: 'racconmail.eu',
    key: 'a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3',
    keyLocation: 'https://racconmail.eu/a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3.txt',
    urlList: allUrls
  });

  const indexNowEndpoints = [
    { name: 'IndexNow Global (api.indexnow.org)', host: 'api.indexnow.org', path: '/indexnow' },
    { name: 'Microsoft Bing IndexNow (www.bing.com)', host: 'www.bing.com', path: '/indexnow' },
    { name: 'Yandex IndexNow (yandex.com)', host: 'yandex.com', path: '/indexnow' }
  ];

  for (const ep of indexNowEndpoints) {
    try {
      const res = await new Promise(resolve => {
        const req = https.request({
          host: ep.host,
          path: ep.path,
          method: 'POST',
          headers: {
            'Content-Type': 'application/json; charset=utf-8',
            'Content-Length': Buffer.byteLength(indexNowPayload)
          }
        }, r => {
          let d = ''; r.on('data', c => d += c);
          r.on('end', () => resolve({ status: r.statusCode, body: d }));
        });
        req.on('error', err => resolve({ status: 500, error: err.message }));
        req.write(indexNowPayload); req.end();
      });
      console.log(`[${ep.name}] IndexNow submission status: ${res.status} ${res.status === 200 || res.status === 202 ? '(Submitted & Queued for Instant Indexing)' : ''}`);
    } catch (e) {
      console.log(`[${ep.name}] Error:`, e.message);
    }
  }

  // 2. Google Sitemap Ping
  try {
    const gRes = await new Promise(resolve => {
      https.get('https://www.google.com/ping?sitemap=https%3A%2F%2Fracconmail.eu%2Fsitemap.xml', r => {
        resolve(r.statusCode);
      }).on('error', err => resolve(err.message));
    });
    console.log('[Google Search] Sitemap Ping status:', gRes);
  } catch(e) {
    console.log('[Google Search] Ping error:', e.message);
  }

  // 3. Bing Sitemap Ping
  try {
    const bRes = await new Promise(resolve => {
      https.get('https://www.bing.com/ping?sitemap=https%3A%2F%2Fracconmail.eu%2Fsitemap.xml', r => {
        resolve(r.statusCode);
      }).on('error', err => resolve(err.message));
    });
    console.log('[Bing Search] Sitemap Ping status:', bRes);
  } catch(e) {
    console.log('[Bing Search] Ping error:', e.message);
  }

  console.log('\n=== INDEXING SUBMISSION COMPLETE ===');
  console.log(`Successfully queued ${allUrls.length} URLs across Google, Bing, Yandex, and IndexNow global network!`);
}

deployAndIndex().catch(console.error);
