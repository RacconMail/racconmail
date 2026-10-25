const https = require('https');

async function testAuthInbox() {
  const email = 'audit_user_' + Math.random().toString(36).substring(2, 7) + '@racconmail.eu';
  const registerBody = JSON.stringify({
    name: 'Audit User',
    email: email,
    password: 'Password123!'
  });

  const regRes = await new Promise((resolve, reject) => {
    const req = https.request('https://racconmail.eu/api/auth/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(registerBody)
      },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: d }));
    });
    req.on('error', reject);
    req.write(registerBody);
    req.end();
  });

  const cookie = (regRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');
  console.log('Registration status:', regRes.status);
  console.log('Cookie received:', !!cookie);

  // Request /inbox with authenticated session
  const inboxRes = await new Promise((resolve, reject) => {
    https.get('https://racconmail.eu/inbox', {
      headers: { 'Cookie': cookie },
      rejectUnauthorized: false
    }, res => {
      let d = ''; res.on('data', c => d += c);
      res.on('end', () => resolve({ status: res.statusCode, body: d }));
    }).on('error', reject);
  });

  console.log('/inbox HTTP status:', inboxRes.status);
  console.log('Contains modal-change-avatar:', inboxRes.body.includes('modal-change-avatar'));
  console.log('Contains avatar-dropzone:', inboxRes.body.includes('avatar-dropzone'));
  console.log('Contains rollDiceAvatars:', inboxRes.body.includes('rollDiceAvatars'));
  console.log('Contains user-avatar-img:', inboxRes.body.includes('user-avatar-img'));
  console.log('Contains /avatars/raccoon-gold-gear.png:', inboxRes.body.includes('/avatars/raccoon-gold-gear.png'));
}

testAuthInbox().catch(console.error);