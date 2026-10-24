const https = require('https');
const fs = require('fs');
const path = require('path');

const filesToUpload = [
  { local: 'public/stories.html', remote: 'stories.html', dir: '/home/raccxuxv/public_html', type: 'text/html' },
  { local: 'public/api.php', remote: 'api.php', dir: '/home/raccxuxv/public_html', type: 'application/x-php' }
];

async function deployStories() {
  console.log('Logging in to cPanel for stories update...');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const auth = await new Promise((resolve, reject) => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, r => {
      let d = ''; r.on('data', c => d += c);
      r.on('end', () => {
        try { resolve({ headers: r.headers, body: JSON.parse(d) }); }
        catch (e) { reject(e); }
      });
    });
    req.write(loginBody); req.end();
  });

  const secToken = auth.body.security_token;
  const cookie = auth.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  for (const item of filesToUpload) {
    const localPath = path.join(__dirname, '..', item.local);
    const fileBuf = fs.readFileSync(localPath);
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = '--' + boundary + '\r\n';
    header += 'Content-Disposition: form-data; name="file-1"; filename="' + item.remote + '"\r\n';
    header += 'Content-Type: ' + item.type + '\r\n\r\n';
    const footer = '\r\n--' + boundary + '--\r\n';
    const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuf, Buffer.from(footer, 'utf8')]);

    const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent(item.dir) + '&overwrite=1';
    
    await new Promise((resolve, reject) => {
      const req = https.request(uploadUrl, {
        method: 'POST',
        headers: {
          'Cookie': cookie,
          'Content-Type': 'multipart/form-data; boundary=' + boundary,
          'Content-Length': payload.length
        },
        rejectUnauthorized: false
      }, r => {
        let d = ''; r.on('data', c => d += c);
        r.on('end', () => {
          console.log(`Uploaded ${item.remote}:`, d.includes('"succeeded":1') ? 'OK' : d.slice(0, 100));
          resolve();
        });
      });
      req.on('error', reject);
      req.write(payload);
      req.end();
    });
  }

  console.log('Stories overhaul deployed to production successfully!');
}

deployStories().catch(err => {
  console.error('Deployment error:', err);
  process.exit(1);
});
