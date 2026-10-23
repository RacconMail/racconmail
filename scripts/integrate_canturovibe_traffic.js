const fs = require('fs');
const path = require('path');

const baseDir = path.join(__dirname, '..', 'public');

// 1. UPDATE public/api.php (Add pinned post for @canturovibe on the forum)
const apiPhpPath = path.join(baseDir, 'api.php');
let apiPhp = fs.readFileSync(apiPhpPath, 'utf8');

const canturovibeForumSeed = `
    // Ensure @canturovibe music post exists on Forum
    try {
        $stmtC = $db->prepare("SELECT COUNT(*) FROM forum_posts WHERE id = 'post_canturovibe'");
        $stmtC->execute();
        if ((int)$stmtC->fetchColumn() === 0) {
            $db->prepare("INSERT INTO forum_posts (id, user_id, title, category, content, code_snippet, code_lang, image_url, tags, likes_count, comments_count, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
               ->execute([
                   'post_canturovibe',
                   'usr_admin_master',
                   '🎵 Official Music Spotlight: Stream @canturovibe on TikTok for Atmospheric Beats & Chillhop',
                   'sharing',
                   "Hey community owls & music lovers! 🎧\n\nLooking for the perfect late-night coding beats, ambient chillhop and driving soundscapes? Check out the official TikTok channel @canturovibe!\n\nStream now on TikTok: https://www.tiktok.com/@canturovibe\n\nDrop your favorite track in the replies, share feedback, and let us know what you want to hear next!",
                   "// Stream @canturovibe on TikTok\\nwindow.open('https://www.tiktok.com/@canturovibe', '_blank');",
                   'javascript',
                   'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=800&q=80',
                   'music,beats,canturovibe,sharing,tiktok',
                   54,
                   8
               ]);
        }
    } catch (Throwable $e) {}
`;

if (!apiPhp.includes('post_canturovibe')) {
  apiPhp = apiPhp.replace('// 11.1 GET /api/forum/posts', canturovibeForumSeed + '\n    // 11.1 GET /api/forum/posts');
  fs.writeFileSync(apiPhpPath, apiPhp, 'utf8');
  console.log('Added @canturovibe to public/api.php');
}


// 2. UPDATE public/news.html (Prepend spotlight link into ticker)
const newsHtmlPath = path.join(baseDir, 'news.html');
let newsHtml = fs.readFileSync(newsHtmlPath, 'utf8');

const oldTickerMap = `      const tickerHtml = items.map(item => \`
        <span onclick="openQuickReader('\${item.id}')" class="inline-flex items-center gap-2 hover:text-emerald-400 transition cursor-pointer">
          <span class="text-amber-400 font-bold">\${item.catEmoji} \${item.catLabel.toUpperCase()}:</span>
          <span>\${item.title}</span>
          <span class="text-slate-500 text-[10px]">(\${item.time})</span>
          <span class="text-emerald-500/60 ml-4">&bull;</span>
        </span>
      \`).join('');`;

const newTickerMap = `      const canturoTicker = \`
        <a href="https://www.tiktok.com/@canturovibe" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-rose-500/25 text-rose-300 border border-rose-500/40 hover:bg-rose-500 hover:text-white transition cursor-pointer font-bold mr-4 shadow-sm">
          <span>🎵 TIKTOK SPOTLIGHT: @canturovibe (Viral Beats) &bull; Listen Now &rarr;</span>
        </a>
      \`;
      const tickerHtml = canturoTicker + items.map(item => \`
        <span onclick="openQuickReader('\${item.id}')" class="inline-flex items-center gap-2 hover:text-emerald-400 transition cursor-pointer">
          <span class="text-amber-400 font-bold">\${item.catEmoji} \${item.catLabel.toUpperCase()}:</span>
          <span>\${item.title}</span>
          <span class="text-slate-500 text-[10px]">(\${item.time})</span>
          <span class="text-emerald-500/60 ml-4">&bull;</span>
        </span>
      \`).join('');`;

if (newsHtml.includes(oldTickerMap)) {
  newsHtml = newsHtml.replace(oldTickerMap, newTickerMap);
  fs.writeFileSync(newsHtmlPath, newsHtml, 'utf8');
  console.log('Updated ticker in public/news.html');
}


// 3. UPDATE public/inbox.php (Persistent floating pill in webmail)
const inboxPhpPath = path.join(baseDir, 'inbox.php');
let inboxPhp = fs.readFileSync(inboxPhpPath, 'utf8');

const floatingMusicPill = `
  <!-- Persistent Floating Music Pill: @canturovibe on TikTok -->
  <div id="canturovibe-floating-pill" class="fixed bottom-4 right-4 z-40 hidden sm:flex items-center gap-2.5 px-3.5 py-2 rounded-full bg-slate-900/90 hover:bg-black text-white text-xs border border-rose-500/40 shadow-xl backdrop-blur-md transition-all hover:scale-105 group">
    <span class="flex h-2 w-2 relative">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
      <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
    </span>
    <i class="fab fa-tiktok text-rose-400 text-sm"></i>
    <span class="font-medium text-[11px] text-slate-300">Nocturnal Beats: <strong class="text-white font-bold group-hover:text-rose-400 transition">@canturovibe</strong></span>
    <a href="https://www.tiktok.com/@canturovibe" target="_blank" rel="noopener noreferrer" class="px-2.5 py-0.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-[10px] tracking-wide ml-1 transition shadow-xs flex items-center gap-1">
      <span>Play on TikTok</span>
      <span>&rarr;</span>
    </a>
  </div>
`;

if (!inboxPhp.includes('canturovibe-floating-pill')) {
  inboxPhp = inboxPhp.replace('</body>', floatingMusicPill + '\n</body>');
  fs.writeFileSync(inboxPhpPath, inboxPhp, 'utf8');
  console.log('Added floating music pill in public/inbox.php');
}


// 4. UPDATE public/index.html (Add TikTok badge to footer)
const indexHtmlPath = path.join(baseDir, 'index.html');
let indexHtml = fs.readFileSync(indexHtmlPath, 'utf8');

const oldFooterLinks = `<div class="flex items-center gap-2 text-xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>All systems encrypted &bull; Zero trackers</span>
          </div>`;

const newFooterLinks = `<div class="flex items-center gap-3 text-xs flex-wrap">
            <a href="https://www.tiktok.com/@canturovibe" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#1E293B] hover:bg-[#000000] text-white font-bold text-xs transition shadow-xs hover:scale-105">
              <i class="fab fa-tiktok text-rose-400"></i>
              <span>Music by @canturovibe</span>
            </a>
            <div class="flex items-center gap-1.5 text-xs text-[#64748B]">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Zero trackers</span>
            </div>
          </div>`;

if (indexHtml.includes(oldFooterLinks)) {
  indexHtml = indexHtml.replace(oldFooterLinks, newFooterLinks);
  fs.writeFileSync(indexHtmlPath, indexHtml, 'utf8');
  console.log('Added TikTok badge to public/index.html footer');
}

console.log('Traffic funnel setup complete!');
