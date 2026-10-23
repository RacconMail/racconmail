const fs = require('fs');
const content = fs.readFileSync('C:/Users/Osarius/.gemini/antigravity/scratch/raccoon-mail/public/api.php', 'utf8');
const lines = content.split('\n');

console.log('Total lines:', lines.length);

lines.forEach((line, idx) => {
  // Check for $db in double quotes
  const dquoteMatches = line.match(/"[^"]*\$db[^"]*"/g);
  if (dquoteMatches) {
    console.log('Double-quote interpolation line ' + (idx + 1) + ':', line.trim());
  }

  // Check for . $db or $db .
  if (/\.\s*\$db\b/.test(line) || /\$db\s*\./.test(line)) {
    console.log('Concat line ' + (idx + 1) + ':', line.trim());
  }

  // Check for echo $db or print $db
  if (/\b(echo|print)\s+.*\$db/.test(line)) {
    console.log('Echo line ' + (idx + 1) + ':', line.trim());
  }
});
