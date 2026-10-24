const fs = require('fs');
const path = require('path');

const rootDir = 'C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail';

const filesToProcess = [
  'public/index.html',
  'public/inbox.php',
  'public/auth.html',
  'public/admin.html',
  'public/news.html',
  'public/forum.html',
  'public/stories.html',
  'public/legal_terms.html',
  'public/legal_privacy.html',
  'public/site.webmanifest',
  'public/robots.txt',
  'public/js/app.js',
  'README.md'
];

// Regex that matches standalone "RacconMail" but never when part of an email, url, github path, or file extension
const brandRegex = /(?<!@|https?:\/\/|github\.com\/|[\w\-\.\/])RacconMail(?![\w\-\.\/])/g;

let totalReplacements = 0;

for (const relPath of filesToProcess) {
  const fullPath = path.join(rootDir, relPath);
  if (!fs.existsSync(fullPath)) continue;

  const original = fs.readFileSync(fullPath, 'utf8');
  let count = 0;
  const replaced = original.replace(brandRegex, () => {
    count++;
    return 'Raccon Mail';
  });

  if (count > 0) {
    fs.writeFileSync(fullPath, replaced, 'utf8');
    console.log(`[Replaced] ${relPath}: ${count} occurrences changed to "Raccon Mail"`);
    totalReplacements += count;
  } else {
    console.log(`[Unchanged] ${relPath}: no standalone "RacconMail" found`);
  }
}

console.log(`\nTotal occurrences updated: ${totalReplacements}`);
