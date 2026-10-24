const https = require('https');
const fs = require('fs');
const path = require('path');

const indexPath = path.join(__dirname, '..', 'public', 'index.html');

async function uploadIndex() {
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const auth = await new Promise((resolve, reject) => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, r => {
      let d = ''; r.on('data', c => d += c);
      r.on('end', () => {
        try {
          resolve({ headers: r.headers, body: JSON.parse(d) });
        } catch (e) {
          reject(e);
        }
      });
    });
    req.write(loginBody); req.end();
  });

  const secToken = auth.body.security_token;
  const cookie = auth.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  const fileBuf = fs.readFileSync(indexPath);
  const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
  let header = '--' + boundary + '\r\n';
  header += 'Content-Disposition: form-data; name="file-1"; filename="index.html"\r\n';
  header += 'Content-Type: text/html\r\n\r\n';
  const footer = '\r\n--' + boundary + '--\r\n';
  const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuf, Buffer.from(footer, 'utf8')]);

  const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent('/home/raccxuxv/public_html') + '&overwrite=1';
  await new Promise((resolve, reject) => {
    const req = https.request(uploadUrl, {
      method: 'POST',
      headers: { 'Cookie': cookie, 'Content-Type': 'multipart/form-data; boundary=' + boundary, 'Content-Length': payload.length },
      rejectUnauthorized: false
    }, r => {
      let d = ''; r.on('data', c => d += c);
      r.on('end', () => {
        console.log('index.html upload response:', d.slice(0, 200));
        resolve();
      });
    });
    req.write(payload); req.end();
  });
}

uploadIndex().catch(err => {
  console.error('Upload error:', err);
  process.exit(1);
});
