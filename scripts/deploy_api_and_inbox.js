const https = require('https');
const fs = require('fs');
const path = require('path');

const workDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

async function uploadFile(secToken, cookie, localPath, remoteFilename) {
  const fileBuf = fs.readFileSync(localPath);
  const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
  let header = '--' + boundary + '\r\n';
  header += `Content-Disposition: form-data; name="file-1"; filename="${remoteFilename}"\r\n`;
  header += 'Content-Type: application/octet-stream\r\n\r\n';
  const footer = '\r\n--' + boundary + '--\r\n';
  const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuf, Buffer.from(footer, 'utf8')]);

  const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent('/home/raccxuxv/public_html') + '&overwrite=1';
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
          console.log(`[Upload ${remoteFilename}] Status: ${parsed.status === 1 ? 'OK' : 'FAIL'}`);
          resolve(parsed);
        } catch (_) {
          console.log(`[Upload ${remoteFilename}] Raw: ${d.slice(0, 100)}`);
          resolve(d);
        }
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

async function deploy() {
  console.log('Logging into cPanel...');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const loginRes = await new Promise(resolve => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ headers: res.headers, body: JSON.parse(d) }));
    });
    req.write(loginBody); req.end();
  });

  const secToken = loginRes.body.security_token;
  const cookie = loginRes.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  console.log('Authenticated! Security Token:', secToken);

  console.log('Uploading api.php...');
  await uploadFile(secToken, cookie, path.join(workDir, 'public/api.php'), 'api.php');

  console.log('Uploading inbox.php...');
  await uploadFile(secToken, cookie, path.join(workDir, 'public/inbox.php'), 'inbox.php');

  console.log('Deployment complete!');
}

deploy().catch(console.error);
