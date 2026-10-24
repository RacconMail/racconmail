const https = require('https');

https.get('https://racconmail.eu/stories', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('HTTP Status:', res.statusCode);
    console.log('Has Stories title:', d.includes('Raccon Stories'));
    console.log('Has Post a Story button:', d.includes('Post a Story'));
    console.log('Has Romance category:', d.includes('Romance'));
    console.log('Has LitRPG category:', d.includes('LitRPG'));
    console.log('Has Werewolf & Vampire:', d.includes('Werewolf &amp; Vampire'));
    console.log('Has Story Wall view:', d.includes('view-wall'));
    console.log('Has Story Reader view:', d.includes('view-reader'));
  });
}).on('error', err => console.error(err));
