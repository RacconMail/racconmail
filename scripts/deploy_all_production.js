const https = require('https');
const fs = require('fs');
const path = require('path');

const workDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

async function deployAll() {
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

  // 1. Files in public_html root
  const rootFiles = [
    'inbox.php',
    'api.php',
    'admin.html',
    'forum.html',
    'stories.html',
    'index.html',
    'auth.html',
    'news.html'
  ];

  for (const f of rootFiles) {
    console.log(`Uploading ${f} to public_html...`);
    const fileBuf = fs.readFileSync(path.join(workDir, 'public', f));

    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let header = '--' + boundary + '\r\n';
    header += `Content-Disposition: form-data; name="file-1"; filename="${f}"\r\n`;
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
            console.log(`[Upload ${f}] Status: ${parsed.status === 1 ? 'OK' : 'FAIL'}`);
          } catch (_) {
            console.log(`[Upload ${f}] Raw: ${d.slice(0, 80)}`);
          }
          resolve();
        });
      });
      req.write(payload);
      req.end();
    });
  }

  // 2. Upload js/notifications.js to /home/raccxuxv/public_html/js
  console.log('Uploading js/notifications.js to public_html/js...');
  const notifBuf = fs.readFileSync(path.join(workDir, 'public', 'js', 'notifications.js'));
  const boundaryNotif = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
  let hNotif = '--' + boundaryNotif + '\r\n';
  hNotif += 'Content-Disposition: form-data; name="file-1"; filename="notifications.js"\r\n';
  hNotif += 'Content-Type: application/javascript\r\n\r\n';
  const fNotif = '\r\n--' + boundaryNotif + '--\r\n';
  const payloadNotif = Buffer.concat([Buffer.from(hNotif, 'utf8'), notifBuf, Buffer.from(fNotif, 'utf8')]);

  const notifUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent('/home/raccxuxv/public_html/js') + '&overwrite=1';
  await new Promise(resolve => {
    const req = https.request(notifUrl, {
      method: 'POST',
      headers: { 'Cookie': cookie, 'Content-Type': 'multipart/form-data; boundary=' + boundaryNotif, 'Content-Length': payloadNotif.length },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => {
        try {
          const parsed = JSON.parse(d);
          console.log(`[Upload js/notifications.js] Status: ${parsed.status === 1 ? 'OK' : 'FAIL'}`);
        } catch (_) {
          console.log(`[Upload js/notifications.js] Raw: ${d.slice(0, 80)}`);
        }
        resolve();
      });
    });
    req.write(payloadNotif);
    req.end();
  });

  // 3. Upload images/abstract_1.svg ... abstract_4.svg to /home/raccxuxv/public_html/images
  const imageFiles = ['abstract_1.svg', 'abstract_2.svg', 'abstract_3.svg', 'abstract_4.svg'];
  for (const img of imageFiles) {
    console.log(`Uploading images/${img} to public_html/images...`);
    const imgBuf = fs.readFileSync(path.join(workDir, 'public', 'images', img));
    const bImg = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    let hImg = '--' + bImg + '\r\n';
    hImg += `Content-Disposition: form-data; name="file-1"; filename="${img}"\r\n`;
    hImg += 'Content-Type: image/svg+xml\r\n\r\n';
    const fImg = '\r\n--' + bImg + '--\r\n';
    const pImg = Buffer.concat([Buffer.from(hImg, 'utf8'), imgBuf, Buffer.from(fImg, 'utf8')]);

    const imgUrl = 'https://premium252.web-hosting.com:2083' + secToken + '/execute/Fileman/upload_files?dir=' + encodeURIComponent('/home/raccxuxv/public_html/images') + '&overwrite=1';
    await new Promise(resolve => {
      const req = https.request(imgUrl, {
        method: 'POST',
        headers: { 'Cookie': cookie, 'Content-Type': 'multipart/form-data; boundary=' + bImg, 'Content-Length': pImg.length },
        rejectUnauthorized: false
      }, res => {
        let d = ''; res.on('data', c => d += c);
        res.on('end', () => {
          try {
            const parsed = JSON.parse(d);
            console.log(`[Upload images/${img}] Status: ${parsed.status === 1 ? 'OK' : 'FAIL'}`);
          } catch (_) {
            console.log(`[Upload images/${img}] Raw: ${d.slice(0, 80)}`);
          }
          resolve();
        });
      });
      req.write(pImg);
      req.end();
    });
  }

  console.log('All files & artworks deployed to production successfully!');
}

deployAll().catch(console.error);
