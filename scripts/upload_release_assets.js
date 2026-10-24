const https = require('https');
const fs = require('fs');
const path = require('path');

const ROOT_DIR = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';
const token = fs.readFileSync(path.join(ROOT_DIR, 'gh_token_clean.txt'), 'utf8').trim();

function ghRequest(method, host, reqPath, headers = {}, body = null) {
  return new Promise((resolve, reject) => {
    const req = https.request({
      hostname: host,
      path: reqPath,
      method: method,
      headers: {
        'User-Agent': 'RacconMail-Uploader',
        'Authorization': `Bearer ${token}`,
        ...headers
      }
    }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, headers: res.headers, body: JSON.parse(data) });
        } catch (_) {
          resolve({ status: res.statusCode, headers: res.headers, raw: data });
        }
      });
    });
    req.on('error', reject);
    if (body) req.write(body);
    req.end();
  });
}

async function uploadReleaseAssets() {
  console.log('=== UPLOADING UPDATED BUILDS TO GITHUB RELEASES ===');

  const exePath = path.join(ROOT_DIR, 'public/downloads/RacconMail-Setup.exe');
  const apkPath = path.join(ROOT_DIR, 'public/downloads/RacconMail-v2.0.apk');

  const setupExeBuf = fs.readFileSync(exePath);
  const apkBuf = fs.readFileSync(apkPath);

  console.log(`RacconMail-Setup.exe: ${setupExeBuf.length} bytes`);
  console.log(`RacconMail-v2.0.apk: ${apkBuf.length} bytes`);

  const relRes = await ghRequest('GET', 'api.github.com', '/repos/RacconMail/racconmail/releases/tags/v2.0', {
    'Accept': 'application/vnd.github.v3+json'
  });

  if (relRes.status !== 200) {
    throw new Error(`Failed to find release v2.0: status ${relRes.status}`);
  }

  const releaseId = relRes.body.id;
  console.log(`Found GitHub Release v2.0 (id: ${releaseId})`);

  // Delete existing assets if they match
  for (const a of relRes.body.assets) {
    if (a.name === 'RacconMail-Setup.exe' || a.name === 'RacconMail-v2.0.apk') {
      console.log(`Deleting existing release asset ${a.name} (id: ${a.id})...`);
      await ghRequest('DELETE', 'api.github.com', `/repos/RacconMail/racconmail/releases/assets/${a.id}`);
    }
  }

  // Upload RacconMail-Setup.exe
  console.log('Uploading new RacconMail-Setup.exe...');
  const upExe = await ghRequest('POST', 'uploads.github.com', `/repos/RacconMail/racconmail/releases/${releaseId}/assets?name=RacconMail-Setup.exe`, {
    'Content-Type': 'application/x-msdownload',
    'Content-Length': setupExeBuf.length
  }, setupExeBuf);
  console.log('Setup.exe upload response:', upExe.status);

  // Upload RacconMail-v2.0.apk
  console.log('Uploading new RacconMail-v2.0.apk...');
  const upApk = await ghRequest('POST', 'uploads.github.com', `/repos/RacconMail/racconmail/releases/${releaseId}/assets?name=RacconMail-v2.0.apk`, {
    'Content-Type': 'application/vnd.android.package-archive',
    'Content-Length': apkBuf.length
  }, apkBuf);
  console.log('APK upload response:', upApk.status);

  console.log('=== GITHUB RELEASE ASSETS UPDATE COMPLETE! ===');
}

uploadReleaseAssets().catch(err => {
  console.error('Error uploading assets:', err);
  process.exit(1);
});
