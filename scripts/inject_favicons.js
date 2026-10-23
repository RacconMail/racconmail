const fs = require('fs');
const path = require('path');

const baseDir = path.join(__dirname, '..', 'public');

const standardFaviconTags = `  <!-- Comprehensive Favicon Suite for Google Search & All Browsers -->
  <link rel="icon" href="/favicon.ico" sizes="48x48" />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
  <link rel="manifest" href="/site.webmanifest" />`;

const files = [
  'index.html',
  'news.html',
  'forum.html',
  'auth.html',
  'inbox.php'
];

for (const file of files) {
  const filePath = path.join(baseDir, file);
  if (!fs.existsSync(filePath)) continue;

  let content = fs.readFileSync(filePath, 'utf8');

  // Replace old simple favicon link
  content = content.replace(
    /<link rel="icon"[\s\S]*?\/>/i,
    standardFaviconTags
  );

  fs.writeFileSync(filePath, content, 'utf8');
  console.log(`Updated favicon tags in ${file}`);
}

console.log('Favicon tags updated across all templates!');
