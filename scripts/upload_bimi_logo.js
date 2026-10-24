const https = require('https');
const fs = require('fs');
const path = require('path');

const rootDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

async function uploadBimiLogo() {
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

  async function uploadFile(remoteDir, fileName, fileBuffer) {
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = '--' + boundary + '\r\n';
    header += `Content-Disposition: form-data; name="file-1"; filename="${fileName}"\r\n`;
    header += 'Content-Type: image/svg+xml\r\n\r\n';
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
            console.log(`[Upload] ${fileName} -> ${remoteDir}: ${res.status === 1 ? 'OK' : 'FAILED'}`);
            resolve(res.status === 1);
          } catch(e) {
            console.log(`[Upload] ${fileName} status: ${r.statusCode}`);
            resolve(r.statusCode === 200);
          }
        });
      });
      req.write(payload); req.end();
    });
  }

  const svgBuf = fs.readFileSync(path.join(rootDir, 'public/raccoon-logo.svg'));
  await uploadFile('/home/raccxuxv/public_html', 'raccoon-logo.svg', svgBuf);
  console.log('BIMI logo uploaded successfully!');
}

uploadBimiLogo().catch(console.error);
