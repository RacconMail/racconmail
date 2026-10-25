const https = require('https');

async function testFinal() {
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

  const extract = id => {
    const re = new RegExp('<[^>]+id="' + id + '"[^>]*>[\\s\\S]*?<\\/[^>]+>', 'i');
    const m = html.match(re);
    return m ? m[0] : 'NOT FOUND';
  };

  console.log('--- LIVE HTML VERIFICATION ---');
  console.log('coral-inbox-badge:', extract('coral-inbox-badge'));
  console.log('inbox-unread-pill:', extract('inbox-unread-pill'));
  console.log('mark-all-read-btn:', extract('mark-all-read-btn'));
  console.log('badge-inbox:', extract('badge-inbox'));
  console.log('badge-unread:', extract('badge-unread'));
  console.log('mobile-bottom-inbox-badge:', extract('mobile-bottom-inbox-badge'));
}

testFinal();
