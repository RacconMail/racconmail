const https = require('https');

https.get('https://racconmail.eu/', (res) => {
  let data = '';
  res.on('data', chunk => data += chunk);
  res.on('end', () => {
    console.log('HTTP Status:', res.statusCode);
    console.log('Contains id="compare":', data.includes('id="compare"'));
    console.log('Contains href="#compare":', data.includes('href="#compare"'));
    console.log('Contains "Raccon Mail":', data.includes('Raccon Mail'));
  });
}).on('error', err => {
  console.error('Request error:', err);
});
