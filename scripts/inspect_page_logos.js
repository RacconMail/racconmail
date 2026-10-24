const fs = require('fs');
const path = require('path');

const publicDir = path.resolve(__dirname, '..', 'public');
const files = ['index.html', 'inbox.php', 'forum.html', 'stories.html', 'news.html', 'auth.html', 'admin.html', 'legal_privacy.html', 'legal_terms.html'];

files.forEach(f => {
  const p = path.join(publicDir, f);
  if (!fs.existsSync(p)) return;
  const lines = fs.readFileSync(p, 'utf8').split('\n');
  console.log(`\n================= ${f} =================`);
  lines.forEach((line, idx) => {
    if (/raccoon-logo|logo|mascot|favicon/i.test(line) && !line.includes('meta property') && !line.includes('font-')) {
      console.log(`Line ${idx + 1}: ${line.trim()}`);
    }
  });
});
