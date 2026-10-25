const https = require('https');

function post(url, data) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const postData = JSON.stringify(data);
    const req = https.request({
      hostname: u.hostname,
      path: u.pathname + u.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData)
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

function getWithCookie(url, cookie) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const req = https.request({
      hostname: u.hostname,
      path: u.pathname + u.search,
      method: 'GET',
      headers: {
        'Cookie': cookie || '',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      }
    }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, body: data }));
    });
    req.on('error', reject);
    req.end();
  });
}

async function searchLiveMessages() {
  const loginRes = await post('https://racconmail.eu/api/auth/login', {
    email: 'support@racconmail.eu',
    password: 'Romania1@'
  });
  const json = JSON.parse(loginRes.body);
  const token = json.token;
  const cookie = 'raccon_token=' + token;

  const res = await getWithCookie('https://racconmail.eu/api/messages?folder=all', cookie);
  const data = JSON.parse(res.body);
  const msgs = data.messages || [];
  console.log('Total messages in folder all:', msgs.length);
  msgs.forEach(m => {
    console.log(`[${m.id}] From: "${m.sender_name}" <${m.sender_email}> | Subject: "${m.subject}"`);
    if (m.body && (m.body.includes('mail delivery') || m.body.includes('boostmobile') || m.body.includes('1xJzWG') || m.body.includes('queue on premium252'))) {
      console.log('  --> MATCH FOUND in body! id:', m.id);
    }
  });
}

searchLiveMessages().catch(console.error);
