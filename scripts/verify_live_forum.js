const https = require('https');

https.get('https://racconmail.eu/forum', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('HTTP Status:', res.statusCode);
    console.log('Has Flarum architecture:', d.includes('flarum') || d.includes('Flarum'));
    console.log('Has Community Wall view:', d.includes('view-wall'));
    console.log('Has Discussion thread view:', d.includes('view-thread'));
    console.log('Has English titles:', d.includes('Welcome to Raccon Forum'));
  });
}).on('error', err => {
  console.error('Request error:', err);
});
