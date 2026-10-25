const https = require('https');

async function testBar() {
  const postData = JSON.stringify({ email: 'support@racconmail.eu', password: 'Romania1@' });
  const loginRes = await new Promise(resolve => {
    const req = https.request('https://racconmail.eu/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(postData) }
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(JSON.parse(d)));
    });
    req.write(postData); req.end();
  });
  const token = loginRes.token;

  const html = await new Promise(resolve => {
    https.get('https://racconmail.eu/inbox?token=' + token, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve(d));
    });
  });

  const m = html.match(/<nav id="mobile-app-bottom-bar"[\s\S]*?<\/nav>/i);
  if (m) {
    console.log('--- LIVE MOBILE BOTTOM BAR ---');
    console.log(m[0]);
  } else {
    console.log('NOT FOUND');
  }
}

testBar();
