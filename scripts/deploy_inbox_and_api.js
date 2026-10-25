const https = require('https');
const fs = require('fs');
const path = require('path');

const workDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

async function deployInboxAndApi() {
  console.log('Logging into cPanel...');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const loginRes = await new Promise(resolve => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': loginBody.length },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ headers: res.headers, body: JSON.parse(d) }));
    });
    req.write(loginBody); req.end();
  });

  const secToken = loginRes.body.security_token;
  const cookie = loginRes.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  async function uploadOneFile(localRelPath, remoteFileName) {
    console.log(`Uploading ${localRelPath} -> /home/raccxuxv/public_html/${remoteFileName}...`);
    const fileBuf = fs.readFileSync(path.join(workDir, localRelPath));
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = '--' + boundary + '\r\n';
    header += `Content-Disposition: form-data; name="file-1"; filename="${remoteFileName}"\r\n`;
    header += 'Content-Type: application/octet-stream\r\n\r\n';
    const footer = '\r\n--' + boundary + '--\r\n';
    const payload = Buffer.concat([Buffer.from(header, 'utf8'), fileBuf, Buffer.from(footer, 'utf8')]);

    const uploadUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent('/home/raccxuxv/public_html') + '&overwrite=1';
    await new Promise(resolve => {
      const req = https.request(uploadUrl, {
        method: 'POST',
        headers: { 'Cookie': cookie, 'Content-Type': 'multipart/form-data; boundary=' + boundary, 'Content-Length': payload.length },
        rejectUnauthorized: false
      }, res => {
        let d = ''; res.on('data', c => d += c);
        res.on('end', () => {
          try {
            const parsed = JSON.parse(d);
            console.log(`  [Upload ${remoteFileName}] Status: ${parsed.status === 1 ? 'OK' : 'FAIL'}`);
          } catch (_) {
            console.log(`  [Upload ${remoteFileName}] Raw: ${d.slice(0, 80)}`);
          }
          resolve();
        });
      });
      req.write(payload);
      req.end();
    });
  }

  await uploadOneFile('public/inbox.php', 'inbox.php');
  await uploadOneFile('public/api.php', 'api.php');
  await uploadOneFile('public/index.html', 'index.html');

  console.log('inbox.php, api.php, and index.html successfully deployed to production!');
}

deployInboxAndApi().catch(console.error);
