const fs = require('fs');
const path = require('path');

const apiPath = path.join(__dirname, '..', 'public', 'api.php');
let api = fs.readFileSync(apiPath, 'utf8');

// Ensure PRAGMA cache_size is right after synchronous = NORMAL;
if (!api.includes('PRAGMA cache_size = -64000;')) {
  api = api.replace(
    "$db->exec('PRAGMA synchronous = NORMAL;');",
    "$db->exec('PRAGMA synchronous = NORMAL;');\n    $db->exec('PRAGMA cache_size = -64000;');\n    $db->exec('PRAGMA mmap_size = 268435456;');"
  );
}

fs.writeFileSync(apiPath, api, 'utf8');
console.log('API speed optimization confirmed!');
