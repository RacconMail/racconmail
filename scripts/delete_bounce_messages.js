const https = require('https');

function post(url, data, cookie) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const postData = JSON.stringify(data || {});
    const req = https.request({
      hostname: u.hostname,
      path: u.pathname + u.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData),
        'Cookie': cookie || ''
      }
    }, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, body }));
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

async function deleteBounceMessages() {
  console.log('Logging in...');
  const loginRes = await post('https://racconmail.eu/api/auth/login', {
    email: 'support@racconmail.eu',
    password: 'Romania1@'
  });
  const json = JSON.parse(loginRes.body);
  const token = json.token;
  const cookie = 'raccon_token=' + token;

  const msgIds = ['in_6ade71b7cbb47', 'in_6ade717ec4da4'];
  for (const id of msgIds) {
    console.log(`Deleting message ${id}...`);
    const delRes = await post(`https://racconmail.eu/api/messages/${id}/delete`, {}, cookie);
    console.log(`Result for ${id}:`, delRes.statusCode, delRes.body);
  }
}

deleteBounceMessages().catch(console.error);
