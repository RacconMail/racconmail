const fs = require('fs');
const path = require('path');

const baseDir = path.join(__dirname, '..', 'public');

// 1. CLEAN public/index.html
const indexHtmlPath = path.join(baseDir, 'index.html');
let indexHtml = fs.readFileSync(indexHtmlPath, 'utf8');

indexHtml = indexHtml.replace(
  /<a href="https:\/\/www\.tiktok\.com\/@canturovibe"[\s\S]*?<\/a>\s*/,
  ''
);
// Restore clean footer row
const oldFooterRow = `<div class="flex items-center gap-3 text-xs flex-wrap">
            <div class="flex items-center gap-1.5 text-xs text-[#64748B]">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Zero trackers</span>
            </div>
          </div>`;
const standardFooterRow = `<div class="flex items-center gap-2 text-xs text-[#64748B]">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>All systems encrypted &bull; Zero trackers</span>
          </div>`;

if (indexHtml.includes(oldFooterRow)) {
  indexHtml = indexHtml.replace(oldFooterRow, standardFooterRow);
}

fs.writeFileSync(indexHtmlPath, indexHtml, 'utf8');
console.log('Cleaned public/index.html (TikTok removed)');


// 2. CLEAN public/news.html
const newsHtmlPath = path.join(baseDir, 'news.html');
let newsHtml = fs.readFileSync(newsHtmlPath, 'utf8');

// Remove ticker spotlight
newsHtml = newsHtml.replace(
  /\s*const canturoTicker = `[\s\S]*?`;\s*const tickerHtml = canturoTicker \+ items\.map/,
  '      const tickerHtml = items.map'
);

// Remove canturovibe article from newsData if present
newsHtml = newsHtml.replace(
  /,\s*\{\s*"id":\s*"music-5"[\s\S]*?"link":\s*"https:\/\/www\.tiktok\.com\/@canturovibe"\s*\}/,
  ''
);

fs.writeFileSync(newsHtmlPath, newsHtml, 'utf8');
console.log('Cleaned public/news.html (TikTok removed)');


// 3. CLEAN public/inbox.php
const inboxPhpPath = path.join(baseDir, 'inbox.php');
let inboxPhp = fs.readFileSync(inboxPhpPath, 'utf8');

inboxPhp = inboxPhp.replace(
  /<!-- Persistent Floating Music Pill: @canturovibe on TikTok -->[\s\S]*?<\/div>/,
  ''
);

fs.writeFileSync(inboxPhpPath, inboxPhp, 'utf8');
console.log('Cleaned public/inbox.php (TikTok removed)');


// 4. CLEAN public/auth.html
const authHtmlPath = path.join(baseDir, 'auth.html');
let authHtml = fs.readFileSync(authHtmlPath, 'utf8');

authHtml = authHtml.replace(
  /<a href="https:\/\/www\.tiktok\.com\/@canturovibe"[\s\S]*?<\/a>\s*/,
  ''
);

fs.writeFileSync(authHtmlPath, authHtml, 'utf8');
console.log('Cleaned public/auth.html (TikTok removed)');


// 5. CLEAN public/api.php
const apiPhpPath = path.join(baseDir, 'api.php');
let apiPhp = fs.readFileSync(apiPhpPath, 'utf8');

apiPhp = apiPhp.replace(
  /\/\/ Ensure @canturovibe music post exists on Forum[\s\S]*?\} catch \(Throwable \$e\) \{\}/,
  ''
);

// Also add a DB cleanup block in api.php so when hit it deletes post_canturovibe from SQLite database!
const dbDeleteCanturo = `
    // One-time purge of TikTok post
    try {
        $db->exec("DELETE FROM forum_posts WHERE id = 'post_canturovibe'");
        $db->exec("DELETE FROM forum_comments WHERE post_id = 'post_canturovibe'");
        $db->exec("DELETE FROM forum_likes WHERE post_id = 'post_canturovibe'");
    } catch (Throwable $e) {}
`;

if (!apiPhp.includes('DELETE FROM forum_posts WHERE id = \'post_canturovibe\'')) {
  apiPhp = apiPhp.replace('// 11.1 GET /api/forum/posts', dbDeleteCanturo + '\n    // 11.1 GET /api/forum/posts');
}

fs.writeFileSync(apiPhpPath, apiPhp, 'utf8');
console.log('Cleaned public/api.php (TikTok removed and purged from DB)');


// 6. CLEAN scripts/update_news_data.js
const updateNewsPath = path.join(__dirname, 'update_news_data.js');
if (fs.existsSync(updateNewsPath)) {
  let updateNews = fs.readFileSync(updateNewsPath, 'utf8');
  updateNews = updateNews.replace(
    /,\s*\{\s*id:\s*'music-5'[\s\S]*?link:\s*'https:\/\/www\.tiktok\.com\/@canturovibe'\s*\}/,
    ''
  );
  fs.writeFileSync(updateNewsPath, updateNews, 'utf8');
  console.log('Cleaned scripts/update_news_data.js');
}

console.log('All TikTok references successfully stripped!');
