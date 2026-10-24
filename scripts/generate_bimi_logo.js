const fs = require('fs');
const path = require('path');

const rootDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';
const pngPath = path.join(rootDir, 'public/raccoon-logo.png');
const svgPath = path.join(rootDir, 'public/raccoon-logo.svg');

const pngBuf = fs.readFileSync(pngPath);
const b64 = pngBuf.toString('base64');

// BIMI Indicator SVG: base-profile tiny-ps, square 512x512
const svgContent = `<svg xmlns="http://www.w3.org/2000/svg" version="1.2" baseProfile="tiny-ps" viewBox="0 0 512 512" width="512" height="512">
  <title>RacconMail Official BIMI Indicator Logo</title>
  <desc>Verified Brand Indicator for RacconMail Nocturnal Encrypted Webmail</desc>
  <circle cx="256" cy="256" r="248" fill="#FFFFFF" stroke="#0F172A" stroke-width="8"/>
  <image href="data:image/png;base64,${b64}" x="32" y="32" width="448" height="448"/>
</svg>`;

fs.writeFileSync(svgPath, svgContent);
console.log('Successfully created public/raccoon-logo.svg (' + svgContent.length + ' bytes)');
