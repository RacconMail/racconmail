const https = require('https');

function checkUrl(url, label) {
  return new Promise((resolve) => {
    const t0 = Date.now();
    https.get(url, { headers: { 'Accept-Encoding': 'gzip, deflate, br' } }, (res) => {
      let bytes = 0;
      res.on('data', chunk => bytes += chunk.length);
      res.on('end', () => {
        const duration = Date.now() - t0;
        console.log(`[${label}] Status: ${res.statusCode} | Duration: ${duration}ms | Transfer size: ${bytes} bytes | Content-Encoding: ${res.headers['content-encoding'] || 'none'} | Cache-Control: ${res.headers['cache-control'] || 'none'}`);
        resolve();
      });
    }).on('error', err => {
      console.error(`[${label}] Error:`, err.message);
      resolve();
    });
  });
}

async function run() {
  console.log('Testing Live Website Speed & Compression:');
  await checkUrl('https://racconmail.eu/', 'Landing Page');
  await checkUrl('https://racconmail.eu/inbox', 'Webmail Inbox');
  await checkUrl('https://racconmail.eu/news', 'News Wire');
  await checkUrl('https://racconmail.eu/api/news', 'Backend API');
  await checkUrl('https://racconmail.eu/css/raccoon-mail.css', 'Fluid CSS');
}

run();
