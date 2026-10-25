const https = require('https');
const fs = require('fs');
const path = require('path');

const workDir = path.resolve(__dirname, '..');

async function deployAll() {
  console.log('Logging into cPanel at premium252.web-hosting.com:2083...');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const loginRes = await new Promise((resolve, reject) => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => {
        try {
          resolve({ headers: res.headers, body: JSON.parse(d) });
        } catch (e) {
          reject(new Error('Login response parse failed: ' + d));
        }
      });
    });
    req.on('error', reject);
    req.write(loginBody);
    req.end();
  });

  const secToken = loginRes.body.security_token;
  const cookie = loginRes.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  console.log('✅ Authenticated to cPanel! Token:', secToken);

  async function uploadFile(localPath, remoteDir, filename) {
    const fileBuf = fs.readFileSync(localPath);
    console.log(`[Uploading] ${filename} (${fileBuf.length} bytes) -> ${remoteDir}...`);
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = '--' + boundary + '\r\n';
    header += `Content-Disposition: form-data; name="file-1"; filename="${filename}"\r\n`;
    header += 'Content-Type: application/octet-stream\r\n\r\n';
    const footer = '\r\n--' + boundary + '--\r\n';
    const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuf, Buffer.from(footer, 'utf8')]);

    const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent(remoteDir) + '&overwrite=1';
    return new Promise((resolve, reject) => {
      const req = https.request(uploadUrl, {
        method: 'POST',
        headers: { 'Cookie': cookie, 'Content-Type': 'multipart/form-data; boundary=' + boundary, 'Content-Length': payload.length },
        rejectUnauthorized: false
      }, res => {
        let d = ''; res.on('data', c => d += c);
        res.on('end', () => {
          try {
            const parsed = JSON.parse(d);
            const ok = parsed.status === 1;
            console.log(`  -> [${filename}] ${ok ? 'SUCCESS ✅' : 'FAILED ❌'}`);
            resolve(ok);
          } catch (_) {
            console.log(`  -> [${filename}] Raw: ${d.slice(0, 80)}`);
            resolve(false);
          }
        });
      });
      req.on('error', reject);
      req.write(payload);
      req.end();
    });
  }

  // 1. Upload inbox.php
  console.log('\n--- 1. UPLOADING INBOX.PHP ---');
  await uploadFile(path.join(workDir, 'public', 'inbox.php'), '/home/raccxuxv/public_html', 'inbox.php');

  // 2. Upload Web Favicons & Logos
  console.log('\n--- 2. UPLOADING ROOT FAVICONS & LOGOS ---');
  const rootIcons = [
    'favicon.ico',
    'favicon.png',
    'favicon-16x16.png',
    'favicon-32x32.png',
    'favicon-48x48.png',
    'favicon-96x96.png',
    'apple-touch-icon.png',
    'android-chrome-192x192.png',
    'android-chrome-512x512.png'
  ];

  for (const ic of rootIcons) {
    const p = path.join(workDir, 'public', ic);
    if (fs.existsSync(p)) {
      await uploadFile(p, '/home/raccxuxv/public_html', ic);
    }
  }

  // 3. Upload Downloads (APKs and Desktop builds)
  console.log('\n--- 3. UPLOADING MOBILE APK & DESKTOP PACKAGES ---');
  const downloads = [
    'RacconMail-Desktop-v2.0-Windows.zip',
    'RacconMail-Setup.exe',
    'RacconMail.apk',
    'RacconMail-v2.0.apk'
  ];

  for (const dl of downloads) {
    const p = path.join(workDir, 'public', 'downloads', dl);
    if (fs.existsSync(p)) {
      await uploadFile(p, '/home/raccxuxv/public_html/downloads', dl);
    }
  }

  console.log('\n🎉 ALL DEPLOYS COMPLETED TO CPANEL PRODUCTION!');
}

deployAll().catch(console.error);
