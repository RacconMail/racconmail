const https = require('https');
const fs = require('fs');
const path = require('path');

const workDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

async function makeCpanelRequest(urlPath, cookie, secToken, method = 'GET', postData = null) {
  const fullUrl = 'https://premium252.web-hosting.com:2083' + secToken + urlPath;
  return new Promise((resolve, reject) => {
    const opts = {
      method: method,
      headers: {
        'Cookie': cookie
      },
      rejectUnauthorized: false
    };
    if (postData) {
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      opts.headers['Content-Length'] = Buffer.byteLength(postData);
    }
    const req = https.request(fullUrl, opts, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => {
        try {
          resolve(JSON.parse(d));
        } catch (_) {
          resolve({ raw: d });
        }
      });
    });
    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

async function uploadFile(item, cookie, secToken) {
  const fileBuf = fs.readFileSync(path.join(workDir, item.local));
  const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
  let header = '--' + boundary + '\r\n';
  header += `Content-Disposition: form-data; name="file-1"; filename="${item.name}"\r\n`;
  header += 'Content-Type: application/octet-stream\r\n\r\n';
  const footer = '\r\n--' + boundary + '--\r\n';
  const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuf, Buffer.from(footer, 'utf8')]);

  const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent(item.remoteDir) + '&overwrite=1';

  return new Promise((resolve, reject) => {
    const req = https.request(uploadUrl, {
      method: 'POST',
      headers: {
        'Cookie': cookie,
        'Content-Type': 'multipart/form-data; boundary=' + boundary,
        'Content-Length': payload.length
      },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => {
        try {
          const parsed = JSON.parse(d);
          const ok = parsed.status === 1;
          console.log(`[Upload ${item.name} -> ${item.remoteDir}] ${ok ? 'SUCCESS' : 'FAILED'}`);
          if (parsed.errors && parsed.errors.length) console.log('Errors:', parsed.errors);
          resolve(ok);
        } catch (e) {
          console.log(`[Upload ${item.name}] Raw:`, d.slice(0, 100));
          resolve(false);
        }
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

async function main() {
  console.log('Logging in to cPanel...');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const loginRes = await new Promise((resolve, reject) => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ headers: res.headers, body: JSON.parse(d) }));
    });
    req.on('error', reject);
    req.write(loginBody); req.end();
  });

  const secToken = loginRes.body.security_token;
  const cookie = loginRes.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');
  console.log('Logged in successfully. SecToken:', secToken);

  // 1. Ensure /home/raccxuxv/public_html/avatars exists
  console.log('Creating avatars directory in cPanel if needed...');
  const mkdirRes = await makeCpanelRequest('/execute/Fileman/mkdir?dir=' + encodeURIComponent('/home/raccxuxv/public_html') + '&name=avatars', cookie, secToken);
  console.log('mkdir result:', mkdirRes.status === 1 ? 'OK (Created or exists)' : (mkdirRes.errors || 'Skipped'));

  // 2. Upload avatar assets
  const avatarFiles = [
    'raccoon-default.svg',
    'raccoon-gold-gear.png',
    'raccoon-cyber-mechanic.png',
    'raccoon-mascot.png',
    'raccoon-portrait.png',
    'raccoon-stealth-agent.svg',
    'raccoon-sunset-coral.svg',
    'raccoon-neon-matrix.svg'
  ];

  for (const f of avatarFiles) {
    await uploadFile({
      local: `public/avatars/${f}`,
      remoteDir: '/home/raccxuxv/public_html/avatars',
      name: f
    }, cookie, secToken);
  }

  // 3. Upload core web application files
  const coreTargets = [
    { local: 'public/api.php', remoteDir: '/home/raccxuxv/public_html', name: 'api.php' },
    { local: 'public/inbox.php', remoteDir: '/home/raccxuxv/public_html', name: 'inbox.php' },
    { local: 'public/forum.html', remoteDir: '/home/raccxuxv/public_html', name: 'forum.html' },
    { local: 'public/stories.html', remoteDir: '/home/raccxuxv/public_html', name: 'stories.html' }
  ];

  for (const t of coreTargets) {
    const size = fs.statSync(path.join(workDir, t.local)).size;
    console.log(`Starting upload for ${t.name} (${size} bytes)...`);
    const ok = await uploadFile(t, cookie, secToken);
    if (!ok) {
      console.error(`FAILED to upload ${t.name}`);
    }
  }

  console.log('--- ALL DEPLOYMENTS COMPLETED ---');
}

main().catch(err => {
  console.error('Fatal error during deploy:', err);
  process.exit(1);
});