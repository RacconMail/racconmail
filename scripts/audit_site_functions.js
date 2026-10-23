const fs = require('fs');
const path = require('path');

const publicDir = path.join(__dirname, '..', 'public');

console.log('=== COMPREHENSIVE SITE AUDIT: LINKS, BUTTONS, FUNCTIONS ===\n');

const htmlFiles = ['index.html', 'auth.html', 'news.html', 'forum.html', 'inbox.php'];
let issues = [];

// 1. Audit Links in all pages
htmlFiles.forEach(file => {
  const content = fs.readFileSync(path.join(publicDir, file), 'utf8');
  const links = [...content.matchAll(/href="([^"#][^"]*)"/g)].map(m => m[1]);
  console.log(`[${file}] Found ${links.length} external/internal links:`);

  links.forEach(l => {
    if (l.includes('${')) return; // Ignore template literals in JS scripts
    if (l.startsWith('http://') || l.startsWith('https://') || l.startsWith('mailto:')) {
      // External link - check format
      if (l.includes('undefined') || l.includes('null')) {
        issues.push(`Invalid external link in ${file}: ${l}`);
      }
    } else {
      // Internal link
      const cleanPath = l.split('?')[0].replace(/^\//, '');
      if (!cleanPath) return; // root '/' is valid
      const targetLocal = path.join(publicDir, cleanPath);
      const isRoute = ['inbox', 'auth', 'news', 'forum', 'legal/terms', 'legal/privacy', 'admin', 'downloads/RacconMail.apk', 'downloads/RacconMail-Setup.exe'].includes(cleanPath);
      const existsOnDisk = fs.existsSync(targetLocal) || fs.existsSync(targetLocal + '.html') || fs.existsSync(targetLocal + '.php');
      
      if (!isRoute && !existsOnDisk) {
        issues.push(`Broken internal link in ${file}: ${l} (target not found on disk or known routes)`);
      }
    }
  });
});

// 2. Audit Button onclick handlers
htmlFiles.forEach(file => {
  const content = fs.readFileSync(path.join(publicDir, file), 'utf8');
  const buttons = [...content.matchAll(/onclick="([^"]+)"/g)].map(m => m[1]);
  console.log(`[${file}] Found ${buttons.length} onclick handlers`);

  buttons.forEach(b => {
    if (b.includes('undefined') || b.includes('null')) {
      issues.push(`Suspicious onclick in ${file}: ${b}`);
    }
  });
});

// 3. Verify downloads exist on disk
const requiredDownloads = [
  'downloads/RacconMail.apk',
  'downloads/RacconMail-Setup.exe',
  'downloads/RacconMail-Desktop-v1.0.0-Windows.zip'
];

requiredDownloads.forEach(d => {
  const p = path.join(publicDir, d);
  if (!fs.existsSync(p)) {
    issues.push(`Missing download file: ${d}`);
  } else {
    console.log(`Verified download asset: ${d} (${fs.statSync(p).size} bytes)`);
  }
});

// 4. Verify icons and favicon suite exist on disk
const requiredIcons = [
  'favicon.ico',
  'favicon.svg',
  'favicon-48x48.png',
  'favicon-32x32.png',
  'favicon-16x16.png',
  'apple-touch-icon.png',
  'android-chrome-192x192.png',
  'site.webmanifest'
];

requiredIcons.forEach(ic => {
  const p = path.join(publicDir, ic);
  if (!fs.existsSync(p)) {
    issues.push(`Missing icon asset: ${ic}`);
  } else {
    console.log(`Verified icon asset: ${ic} (${fs.statSync(p).size} bytes)`);
  }
});

console.log('\n=== AUDIT RESULTS ===');
if (issues.length === 0) {
  console.log('✅ ALL CHECKS PASSED! Zero broken links, zero invalid onclicks, all downloads and icons verified.');
} else {
  console.error(`❌ Found ${issues.length} potential issues:`);
  issues.forEach(i => console.error('  - ' + i));
}
