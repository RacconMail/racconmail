const https = require('https');

https.get('https://racconmail.eu/stories', res => {
  let d = ''; res.on('data', c => d += c);
  res.on('end', () => {
    console.log('HTTP Status:', res.statusCode);
    console.log('Has toggleReaderMenu:', d.includes('toggleReaderMenu'));
    console.log('Has btn-toggle-reader-menu:', d.includes('btn-toggle-reader-menu'));
    console.log('Has mobile-reader-toggle-pill:', d.includes('mobile-reader-toggle-pill'));
    console.log('Has md:sticky md:top-24 fix:', d.includes('md:sticky md:top-24'));
  });
}).on('error', err => console.error(err));
