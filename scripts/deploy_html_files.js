const https = require('https');
const fs = require('fs');
const path = require('path');

const workDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

async function deployOne(item, cookie, secToken) {
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
          console.log(`[Upload ${item.name}] Status: ${parsed.status === 1 ? 'OK' : 'FAIL'}`);
          if (parsed.errors && parsed.errors.length) console.log('Errors:', parsed.errors);
          resolve(parsed.status === 1);
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

  const targets = [
    { local: 'public/index.html', remoteDir: '/home/raccxuxv/public_html', name: 'index.html' },
    { local: 'public/admin.html', remoteDir: '/home/raccxuxv/public_html', name: 'admin.html' }
  ];

  for (const t of targets) {
    console.log(`Starting upload for ${t.name} (${fs.statSync(path.join(workDir, t.local)).size} bytes)...`);
    const ok = await deployOne(t, cookie, secToken);
    console.log(`Completed ${t.name}: ${ok ? 'SUCCESS' : 'FAILED'}`);
  }
}

main().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
