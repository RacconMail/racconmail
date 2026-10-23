const https = require('https');
const fs = require('fs');
const path = require('path');

async function deployClean() {
  console.log('Logging in to cPanel...');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const loginRes = await new Promise((resolve) => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ headers: res.headers, body: JSON.parse(data) }));
    });
    req.write(loginBody);
    req.end();
  });

  const secToken = loginRes.body.security_token;
  const cookie = loginRes.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  async function uploadFile(localPath, remoteDir, filename) {
    const fileData = fs.readFileSync(localPath);
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = `--${boundary}\r\n`;
    header += `Content-Disposition: form-data; name="file-1"; filename="${filename}"\r\n`;
    header += `Content-Type: application/octet-stream\r\n\r\n`;
    const footer = `\r\n--${boundary}--\r\n`;

    const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileData, Buffer.from(footer, 'utf8')]);
    const uploadUrl = `https://premium252.web-hosting.com:2083${secToken}/execute/Fileman/upload_files?dir=${encodeURIComponent(remoteDir)}&overwrite=1`;

    return new Promise(resolve => {
      const req = https.request(uploadUrl, {
        method: 'POST',
        headers: { 'Cookie': cookie, 'Content-Type': `multipart/form-data; boundary=${boundary}`, 'Content-Length': payload.length },
        rejectUnauthorized: false
      }, res => {
        let d = '';
        res.on('data', c => d += c);
        res.on('end', () => resolve(JSON.parse(d)));
      });
      req.write(payload);
      req.end();
    });
  }

  const baseDir = path.join(__dirname, '..', 'public');
  const files = [
    { local: path.join(baseDir, 'index.html'), dir: '/home/raccxuxv/public_html', name: 'index.html' },
    { local: path.join(baseDir, 'news.html'), dir: '/home/raccxuxv/public_html', name: 'news.html' },
    { local: path.join(baseDir, 'inbox.php'), dir: '/home/raccxuxv/public_html', name: 'inbox.php' },
    { local: path.join(baseDir, 'auth.html'), dir: '/home/raccxuxv/public_html', name: 'auth.html' },
    { local: path.join(baseDir, 'api.php'), dir: '/home/raccxuxv/public_html', name: 'api.php' }
  ];

  for (const f of files) {
    console.log(`Uploading ${f.name} (${fs.statSync(f.local).size} bytes)...`);
    const r = await uploadFile(f.local, f.dir, f.name);
    console.log(`  -> status: ${r.status === 1 ? 'OK' : JSON.stringify(r)}`);
  }
  console.log('All cleaned files deployed live!');
}

deployClean();
