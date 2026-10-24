const fs = require('fs');
const path = require('path');

const apiPath = path.join(__dirname, '..', 'public', 'api.php');
let api = fs.readFileSync(apiPath, 'utf8');

const oldCheck = `        if (!$title || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide both a discussion title and message content.']);
            exit;
        }

        $validCats = ['tech', 'coding', 'webdesign', 'sharing', 'help', 'explaining'];
        if (!in_array($category, $validCats)) $category = 'tech';`;

const newCheck = `        if (!$title && $content) {
            $title = mb_substr(strip_tags($content), 0, 50) . (mb_strlen($content) > 50 ? '...' : '');
        }

        if (!$title || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide message content.']);
            exit;
        }

        $validCats = ['general', 'announcements', 'security', 'tech', 'coding', 'webdesign', 'feedback', 'wall', 'sharing', 'help', 'explaining'];
        if (!in_array($category, $validCats)) $category = 'general';`;

if (api.includes(oldCheck)) {
  api = api.replace(oldCheck, newCheck);
  fs.writeFileSync(apiPath, api, 'utf8');
  console.log('Successfully updated forum categories and auto-title in api.php!');
} else {
  console.log('Pattern not matched in api.php');
}
