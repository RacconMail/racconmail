const fs = require('fs');
const path = require('path');

const baseDir = path.resolve(__dirname, '..');
const publicDir = path.join(baseDir, 'public');

console.log('--- AUDITING SMALL LOGO AND BRAND USAGES IN PUBLIC ---');

const files = fs.readdirSync(publicDir).filter(f => f.endsWith('.html') || f.endsWith('.php') || f.endsWith('.json') || f.endsWith('.webmanifest'));

files.forEach(file => {
  const filePath = path.join(publicDir, file);
  if (fs.statSync(filePath).isDirectory()) return;
  const content = fs.readFileSync(filePath, 'utf8');

  // Find all <img ...> tags
  const imgRegex = /<img[^>]*>/gi;
  let match;
  const imgMatches = [];
  while ((match = imgRegex.exec(content)) !== null) {
    const tag = match[0];
    if (/logo|brand|mascot|raccoon|avatar|icon|seal|paw/i.test(tag) || /class="[^"]*h-[45678]|w-[45678]/i.test(tag)) {
      imgMatches.push(tag);
    }
  }

  // Find all <link rel="...icon"...> tags
  const linkRegex = /<link[^>]*icon[^>]*>/gi;
  const linkMatches = [];
  while ((match = linkRegex.exec(content)) !== null) {
    linkMatches.push(match[0]);
  }

  // Find all svg inline logos
  const svgBrandRegex = /<(?:a|div|span)[^>]*class="[^"]*(?:brand|logo)[^"]*"[^>]*>[\s\S]*?<\/(?:a|div|span)>/gi;
  const brandBlocks = [];
  while ((match = svgBrandRegex.exec(content)) !== null) {
    brandBlocks.push(match[0].slice(0, 200).replace(/\s+/g, ' '));
  }

  if (imgMatches.length > 0 || linkMatches.length > 0 || brandBlocks.length > 0) {
    console.log(`\n=== File: ${file} ===`);
    if (linkMatches.length) {
      console.log('  Favicon/Icon links:');
      linkMatches.forEach(l => console.log('   ', l));
    }
    if (imgMatches.length) {
      console.log('  Logo/Mascot/Avatar images:');
      imgMatches.forEach(img => console.log('   ', img));
    }
    if (brandBlocks.length) {
      console.log('  Brand blocks:');
      brandBlocks.forEach(b => console.log('   ', b));
    }
  }
});
