const https = require('https');

https.get('https://racconmail.eu/api/community/stories', res => {
  let d = ''; res.on('data', c => d += c);
  res.on('end', () => {
    try {
      const j = JSON.parse(d);
      console.log('HTTP Status:', res.statusCode);
      console.log('Stories count:', j.count);
      if (j.stories && j.stories.length > 0) {
        console.log('First story title:', j.stories[0].title);
        console.log('First story category:', j.stories[0].category);
      }
    } catch (e) {
      console.error('Error parsing:', e.message, d.slice(0, 200));
    }
  });
}).on('error', err => console.error(err));
