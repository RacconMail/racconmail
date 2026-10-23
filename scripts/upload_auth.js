const https = require('https');
const fs = require('fs');

async function uploadSingle() {
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

  const fileData = fs.readFileSync('public/auth.html');
  const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
  let header = `--${boundary}\r\n`;
  header += `Content-Disposition: form-data; name="file-1"; filename="auth.html"\r\n`;
  header += `Content-Type: application/octet-stream\r\n\r\n`;
  const footer = `\r\n--${boundary}--\r\n`;

  const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileData, Buffer.from(footer, 'utf8')]);
  const uploadUrl = `https://premium252.web-hosting.com:2083${secToken}/execute/Fileman/upload_files?dir=${encodeURIComponent('/home/raccxuxv/public_html')}&overwrite=1`;

  https.request(uploadUrl, {
    method: 'POST',
    headers: { 'Cookie': cookie, 'Content-Type': `multipart/form-data; boundary=${boundary}`, 'Content-Length': payload.length },
    rejectUnauthorized: false
  }, res => {
    let d = '';
    res.on('data', c => d += c);
    res.on('end', () => console.log('Uploaded auth.html live:', JSON.parse(d).status === 1 ? 'SUCCESS' : d));
  }).end(payload);
}
uploadSingle();
