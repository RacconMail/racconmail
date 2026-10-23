const fs = require('fs');
const path = require('path');

const png48 = fs.readFileSync(path.join(__dirname, '..', 'public', 'favicon-48x48.png')).toString('base64');
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image href="data:image/png;base64,${png48}" width="64" height="64"/></svg>`;

fs.writeFileSync(path.join(__dirname, '..', 'public', 'favicon.svg'), svg, 'utf8');
console.log('Created public/favicon.svg successfully!');
