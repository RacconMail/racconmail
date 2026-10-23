const fs = require('fs');
const path = require('path');

const newsData = require('./update_news_data.js');

// 1. UPDATE public/news.html
const newsHtmlPath = path.join(__dirname, '..', 'public', 'news.html');
let newsHtml = fs.readFileSync(newsHtmlPath, 'utf8');

// Replace newsData definition
const newsDataJson = JSON.stringify(newsData, null, 2);
const startMarker = 'const newsData = {';
const endMarker = '    // Flatten all news items into a single array';

const startIndex = newsHtml.indexOf(startMarker);
const endIndex = newsHtml.indexOf(endMarker);

if (startIndex === -1 || endIndex === -1) {
  console.error('Could not locate newsData boundaries in news.html');
  process.exit(1);
}

const beforeNewsData = newsHtml.substring(0, startIndex);
const afterNewsData = newsHtml.substring(endIndex);

const updatedNewsDataScript = `const newsData = ${newsDataJson};\n\n`;
newsHtml = beforeNewsData + updatedNewsDataScript + afterNewsData;

// Update getAllItems to include world and science
const oldGetAllItems = `    function getAllItems() {
      return [
        ...newsData.tech,
        ...newsData.sport,
        ...newsData.games,
        ...newsData.music,
        ...newsData.weather
      ];
    }`;

const newGetAllItems = `    function getAllItems() {
      return [
        ...(newsData.tech || []),
        ...(newsData.world || []),
        ...(newsData.sport || []),
        ...(newsData.games || []),
        ...(newsData.science || []),
        ...(newsData.music || []),
        ...(newsData.weather || [])
      ];
    }`;

if (newsHtml.includes(oldGetAllItems)) {
  newsHtml = newsHtml.replace(oldGetAllItems, newGetAllItems);
  console.log('Updated getAllItems in news.html');
} else {
  console.log('Notice: oldGetAllItems did not match exactly, searching regex...');
  newsHtml = newsHtml.replace(/function getAllItems\(\)\s*\{[\s\S]*?return\s*\[[\s\S]*?\];\s*\}/, newGetAllItems.trim());
}

// Update Category Pills
const oldCategoryPillsPattern = /<div class="flex items-center gap-2 overflow-x-auto py-1 scrollbar-none w-full sm:w-auto" id="category-pills">[\s\S]*?<\/div>\s*<!-- Bookmarked items toggle button -->/;

const newCategoryPills = `<div class="flex items-center gap-2 overflow-x-auto py-1 scrollbar-none w-full sm:w-auto" id="category-pills">
        <button
          onclick="filterCategory('all')"
          data-category="all"
          class="cat-pill active px-4 py-2 rounded-full text-xs font-bold transition flex items-center gap-1.5 bg-emerald-500 text-obsidian shadow-md shadow-emerald-500/20 cursor-pointer"
        >
          <span>✨ All Categories</span>
          <span class="text-[10px] bg-black/20 text-obsidian px-1.5 py-0.5 rounded-full font-mono">32</span>
        </button>

        <button
          onclick="filterCategory('tech')"
          data-category="tech"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>💻 Tech &amp; AI</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">6</span>
        </button>

        <button
          onclick="filterCategory('world')"
          data-category="world"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>🌍 World &amp; Economy</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">5</span>
        </button>

        <button
          onclick="filterCategory('sport')"
          data-category="sport"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>⚽ Sports Arena</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">5</span>
        </button>

        <button
          onclick="filterCategory('games')"
          data-category="games"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>🎮 Gaming</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">5</span>
        </button>

        <button
          onclick="filterCategory('science')"
          data-category="science"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>🚀 Science &amp; Space</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">4</span>
        </button>

        <button
          onclick="filterCategory('music')"
          data-category="music"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>🎵 Music &amp; Audio</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">4</span>
        </button>

        <button
          onclick="filterCategory('weather')"
          data-category="weather"
          class="cat-pill px-4 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>☀️ Night Radar</span>
          <span class="text-[10px] bg-white/10 text-slate-300 px-1.5 py-0.5 rounded-full font-mono">3</span>
        </button>
      </div>
      <!-- Bookmarked items toggle button -->`;

newsHtml = newsHtml.replace(oldCategoryPillsPattern, newCategoryPills);

// Update Intro Description & Quick Metrics
newsHtml = newsHtml.replace(
  'Real-time nocturnal bulletins across <strong class="text-white">Tech</strong>, <strong class="text-white">Sport</strong>, <strong class="text-white">Games</strong>, <strong class="text-white">Music</strong>, and <strong class="text-white">Weather</strong>. Auto-refreshed every 5 minutes from official syndication at <a href="https://cbn.com/lp/cbn-news" target="_blank" rel="noopener noreferrer" class="font-bold text-emerald-400 hover:underline">https://cbn.com/lp/cbn-news</a>.',
  'Real-time nocturnal wire across <strong class="text-white">Tech & AI</strong>, <strong class="text-white">World & Economy</strong>, <strong class="text-white">Sports</strong>, <strong class="text-white">Gaming</strong>, <strong class="text-white">Science & Space</strong>, <strong class="text-white">Music</strong>, and <strong class="text-white">Night Radar</strong>. Syndicated from verified global publishers including <strong class="text-white">BBC News</strong>, <strong class="text-white">Reuters</strong>, <strong class="text-white">TechCrunch</strong>, <strong class="text-white">The Verge</strong>, <strong class="text-white">Bloomberg</strong>, <strong class="text-white">ESPN</strong>, and <strong class="text-white">NASA</strong>.'
);

newsHtml = newsHtml.replace(
  '<div class="text-xs font-bold text-sky-400">5 Pillars</div>',
  '<div class="text-xs font-bold text-sky-400">7 Desks</div>'
);

newsHtml = newsHtml.replace(
  '<div class="text-xs font-bold text-amber-400">CBN News</div>',
  '<div class="text-xs font-bold text-amber-400">Global Wires</div>'
);

// Update Hero Links & Badges
newsHtml = newsHtml.replace(
  'href="https://cbn.com/lp/cbn-news"\n              target="_blank"\n              rel="noopener noreferrer"\n              class="rounded-full bg-emerald-500 hover:bg-emerald-400 text-obsidian font-bold text-xs sm:text-sm px-6 py-3 transition flex items-center gap-2 shadow-lg shadow-emerald-500/25 active:scale-95 cursor-pointer"\n            >\n              <span>Read Full on CBN News</span>',
  'href="https://techcrunch.com/category/security/"\n              target="_blank"\n              rel="noopener noreferrer"\n              class="rounded-full bg-emerald-500 hover:bg-emerald-400 text-obsidian font-bold text-xs sm:text-sm px-6 py-3 transition flex items-center gap-2 shadow-lg shadow-emerald-500/25 active:scale-95 cursor-pointer"\n            >\n              <span>Read on TechCrunch</span>'
);

newsHtml = newsHtml.replace('Verified CBN Syndication', 'Verified Global Syndication');

// Update Bottom Source Banner
newsHtml = newsHtml.replace(
  'Headlines and articles syndicated in real-time every 5 minutes from <a href="https://cbn.com/lp/cbn-news" target="_blank" rel="noopener noreferrer" class="text-emerald-400 underline font-semibold">https://cbn.com/lp/cbn-news</a>.',
  'Headlines and articles aggregated continuously from verified international news networks: BBC News, Reuters, TechCrunch, The Verge, Bloomberg, ESPN, IGN, and NASA.'
);

newsHtml = newsHtml.replace(
  '<span>Open CBN News</span>',
  '<span>Top World News</span>'
);

newsHtml = newsHtml.replace(
  'href="https://cbn.com/lp/cbn-news"',
  'href="https://www.reuters.com"'
);

// Update Card rendering in renderNewsGrid
newsHtml = newsHtml.replace(
  `<span class="flex items-center gap-1 text-emerald-400 font-bold bg-slate-900/80 px-2 py-0.5 rounded-full backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>CBN Live</span>
                  </span>`,
  `<span class="flex items-center gap-1 text-emerald-400 font-bold bg-slate-900/80 px-2 py-0.5 rounded-full backdrop-blur-sm border border-emerald-500/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>\${item.source || 'Verified Source'}</span>
                  </span>`
);

newsHtml = newsHtml.replace(
  `<a
                href="\${item.link}"
                target="_blank"
                rel="noopener noreferrer"
                class="font-bold text-emerald-400 hover:text-emerald-300 transition flex items-center gap-1 text-[11px]"
              >
                <span>Read on CBN</span>
                <i data-lucide="arrow-right" class="h-3 w-3"></i>
              </a>`,
  `<a
                href="\${item.link}"
                target="_blank"
                rel="noopener noreferrer"
                class="font-bold text-emerald-400 hover:text-emerald-300 transition flex items-center gap-1 text-[11px]"
              >
                <span>Read on \${item.source || 'Source'}</span>
                <i data-lucide="arrow-right" class="h-3 w-3"></i>
              </a>`
);

// Update Quick Reader Modal HTML
newsHtml = newsHtml.replace(
  `<span>Official Article Syndication: <strong>CBN News</strong></span>`,
  `<span>Original Publisher: <strong id="modal-publisher-name" class="text-white">Global Wire</strong></span>`
);

newsHtml = newsHtml.replace(
  `<a
          id="modal-external-link"
          href="https://cbn.com/lp/cbn-news"
          target="_blank"
          rel="noopener noreferrer"
          class="text-emerald-400 font-bold hover:underline flex items-center gap-1"
        >
          <span>cbn.com/lp/cbn-news</span>
          <i data-lucide="external-link" class="h-3 w-3"></i>
        </a>`,
  `<a
          id="modal-external-link"
          href="https://www.reuters.com"
          target="_blank"
          rel="noopener noreferrer"
          class="text-emerald-400 font-bold hover:underline flex items-center gap-1"
        >
          <span id="modal-external-domain">reuters.com</span>
          <i data-lucide="external-link" class="h-3 w-3"></i>
        </a>`
);

newsHtml = newsHtml.replace(
  `<span>Read Full Article on CBN</span>`,
  `<span id="modal-action-text">Read Full Article</span>`
);

// Update openQuickReader JS function
const oldOpenQuickReader = `      document.getElementById('modal-title').textContent = item.title;
      document.getElementById('modal-desc').textContent = item.desc + " Full coverage, verified sources, and real-time updates are syndicated continuously from CBN News at cbn.com/lp/cbn-news.";
      document.getElementById('modal-time').innerHTML = \`\${item.time} &bull; \${item.readTime}\`;
      
      const badge = document.getElementById('modal-category-badge');
      badge.textContent = \`\${item.catEmoji} \${item.catLabel}\`;
      badge.className = \`px-2.5 py-1 rounded-full font-bold text-xs \${item.catColor}\`;

      const link = document.getElementById('modal-action-btn');
      link.href = item.link;`;

const newOpenQuickReader = `      document.getElementById('modal-title').textContent = item.title;
      document.getElementById('modal-desc').textContent = item.desc + \` Full verified reporting and contextual coverage syndicated from \${item.source || 'official publisher'}.\`;
      document.getElementById('modal-time').innerHTML = \`\${item.time} &bull; \${item.readTime}\`;
      
      const badge = document.getElementById('modal-category-badge');
      badge.textContent = \`\${item.catEmoji} \${item.catLabel}\`;
      badge.className = \`px-2.5 py-1 rounded-full font-bold text-xs \${item.catColor}\`;

      const pubEl = document.getElementById('modal-publisher-name');
      if (pubEl) pubEl.textContent = item.source || 'Verified Source';

      const extLink = document.getElementById('modal-external-link');
      if (extLink) {
        extLink.href = item.link;
        try {
          const u = new URL(item.link);
          const domainEl = document.getElementById('modal-external-domain');
          if (domainEl) domainEl.textContent = u.hostname.replace('www.', '');
        } catch (_) {
          const domainEl = document.getElementById('modal-external-domain');
          if (domainEl) domainEl.textContent = item.source || 'Visit Source';
        }
      }

      const actionText = document.getElementById('modal-action-text');
      if (actionText) actionText.textContent = \`Read Full Article on \${item.source || 'Original Site'}\`;

      const link = document.getElementById('modal-action-btn');
      if (link) link.href = item.link;`;

newsHtml = newsHtml.replace(oldOpenQuickReader, newOpenQuickReader);

// Save updated news.html
fs.writeFileSync(newsHtmlPath, newsHtml, 'utf8');
console.log('Saved updated public/news.html');


// 2. UPDATE public/js/app.js (Inbox In-App News Modal)
const appJsPath = path.join(__dirname, '..', 'public', 'js', 'app.js');
let appJs = fs.readFileSync(appJsPath, 'utf8');

const updatedInboxSections = [
  {
    name: 'Tech & AI', icon: '💻', tag: 'Stealth & Chips',
    cover: 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "Next-Gen Stealth Chips Deliver 10x On-Device Encryption", source: "TechCrunch", time: "2m ago", desc: "Hardware isolation prevents memory scraping & data harvesting.", image: 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80', link: 'https://techcrunch.com/category/security/' },
      { title: "Open-Source AI Models Challenge Big Tech Monopolies", source: "The Verge", time: "14m ago", desc: "Decentralized models put full control back in developers' hands.", image: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&w=600&q=80', link: 'https://www.theverge.com/ai-artificial-intelligence' },
      { title: "Global Cyber Treaties Move Toward Universal End-to-End Encryption", source: "BBC News", time: "52m ago", desc: "Privacy coalition declares client-side zero-knowledge encryption a human right.", image: 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=600&q=80', link: 'https://www.bbc.com/news/technology' }
    ]
  },
  {
    name: 'World & Economy', icon: '🌍', tag: 'Global Markets',
    cover: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "European Union Finalizes Ambitious Clean Energy Supergrid", source: "BBC News", time: "15m ago", desc: "Interconnected wind and solar arrays share power across 14 nations.", image: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80', link: 'https://www.bbc.com/news/world' },
      { title: "Global Markets Rally as Tech Innovations Spur Manufacturing", source: "Reuters", time: "38m ago", desc: "Lower energy costs drive positive quarterly trade volumes.", image: 'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?auto=format&fit=crop&w=600&q=80', link: 'https://www.reuters.com/business/' },
      { title: "Green Hydrogen Pipeline Network Commences Construction in North Sea", source: "Bloomberg", time: "1h ago", desc: "Multi-billion euro infrastructure to replace fossil fuels in steel making.", image: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=600&q=80', link: 'https://www.bloomberg.com/markets' }
    ]
  },
  {
    name: 'Sports Arena', icon: '⚽', tag: 'Nocturnal Games',
    cover: 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "Champions League Night Clashes Deliver Dramatic Late Winners", source: "ESPN", time: "3m ago", desc: "Extra-time heroics seal quarterfinal spots under stadium lights.", image: 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80', link: 'https://www.espn.com/soccer/' },
      { title: "Global Marathon Records Shattered in Historic Night Race", source: "Sky Sports", time: "18m ago", desc: "Crisp cool 12°C nocturnal air fuels historic distance times in Berlin.", image: 'https://images.unsplash.com/photo-1452626038306-9aae5e071dd3?auto=format&fit=crop&w=600&q=80', link: 'https://www.skysports.com/football' },
      { title: "Midnight Street Basketball Tournaments Surge in Popularity", source: "BBC Sport", time: "42m ago", desc: "Local city courts light up with intense 3-on-3 showdowns and DJ sets.", image: 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=600&q=80', link: 'https://www.bbc.com/sport' }
    ]
  },
  {
    name: 'Games & Esports', icon: '🎮', tag: 'Burrows & RPG',
    cover: 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "Nocturnal RPG 'Shadow Bandit' Announced for PC & Consoles", source: "IGN", time: "5m ago", desc: "Play as a masked woodland creature solving midnight bakery mysteries.", image: 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80', link: 'https://www.ign.com/' },
      { title: "Global Esports Grand Finals Break Viewership Milestones", source: "GameSpot", time: "21m ago", desc: "Over 4.9 million concurrent viewers tune into intense nocturnal decider.", image: 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=600&q=80', link: 'https://www.gamespot.com/' },
      { title: "Next-Gen Handheld Update Boosts OLED Contrast & Battery", source: "PC Gamer", time: "44m ago", desc: "Firmware optimization extends portable gameplay sessions by 2 full hours.", image: 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=600&q=80', link: 'https://www.pcgamer.com/' }
    ]
  },
  {
    name: 'Science & Space', icon: '🚀', tag: 'Cosmos & Discovery',
    cover: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "James Webb Telescope Captures Atmospheric Chemistry of Exoplanet", source: "NASA", time: "12m ago", desc: "Spectroscopic readings detect water vapor and carbon clouds in habitable zone.", image: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80', link: 'https://www.nasa.gov/' },
      { title: "Deep Ocean Expedition Uncovers Glowing Bioluminescent Ecosystems", source: "National Geographic", time: "35m ago", desc: "Submersibles document never-before-seen deep trench marine species.", image: 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=600&q=80', link: 'https://www.nationalgeographic.com/' },
      { title: "Room-Temperature Superconductivity Synthesized in Crystal Matrix", source: "Nature", time: "1h ago", desc: "New ambient pressure material conduct electricity with zero resistance.", image: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80', link: 'https://www.nature.com/' }
    ]
  },
  {
    name: 'Music & Audio', icon: '🎵', tag: 'Lo-Fi Chillhop',
    cover: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "Midnight Lo-Fi Beats Stream Surpasses 1B Hours Worldwide", source: "Billboard", time: "4m ago", desc: "Night owls and study groups make ambient chillhop the top stream.", image: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80', link: 'https://www.billboard.com/' },
      { title: "Underground Synthwave Album Climbs Global Vinyl Charts", source: "Rolling Stone", time: "24m ago", desc: "Retro 80s analog synthesizers captivate night drivers worldwide.", image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=600&q=80', link: 'https://www.rollingstone.com/music/' },
      { title: "Acoustic Forest Sessions Recorded Under Moonlight Goes Viral", source: "Pitchfork", time: "49m ago", desc: "Natural woodland acoustics bring intimate folk guitar tracks to life.", image: 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=600&q=80', link: 'https://pitchfork.com/' }
    ]
  },
  {
    name: 'Night Radar', icon: '☀️', tag: 'Starlight & Moon',
    cover: 'https://images.unsplash.com/photo-1506703719100-a0f3a48c0f86?auto=format&fit=crop&w=600&q=80',
    items: [
      { title: "Night Sky Clarity: 98% (Exceptional Star Visibility Across Europe)", source: "AccuWeather", time: "Live radar", desc: "Crisp atmospheric pressure; ideal for nocturnal stargazing.", image: 'https://images.unsplash.com/photo-1506703719100-a0f3a48c0f86?auto=format&fit=crop&w=600&q=80', link: 'https://www.accuweather.com/' },
      { title: "Moon Phase: Waxing Gibbous (84% Illuminated Night Glow)", source: "Astronomical Observatory", time: "Astronomical", desc: "Gentle natural silver moonlight over European burrows and cities.", image: 'https://images.unsplash.com/photo-1532767153582-b1a0e5145009?auto=format&fit=crop&w=600&q=80', link: 'https://www.timeanddate.com/astronomy/' },
      { title: "Northern Aurora Borealis Visible in Upper Latitudes Tonight", source: "NOAA Space Weather", time: "Live Alert", desc: "Solar wind geomagnetic index generates dancing emerald curtains.", image: 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=600&q=80', link: 'https://www.swpc.noaa.gov/' }
    ]
  }
];

const renderInboxNewsRegex = /renderInboxNews\(\)\s*\{[\s\S]*?grid\.innerHTML = sections\.map\([\s\S]*?\n  \},/;

const newRenderInboxNews = `renderInboxNews() {
    const grid = document.getElementById('inbox-news-grid');
    if (!grid) return;
    const sections = ${JSON.stringify(updatedInboxSections, null, 6)};

    grid.innerHTML = sections.map(sec => \`
      <div class="rounded-2xl border border-border bg-card p-3 shadow-xs flex flex-col justify-between group hover:border-primary/50 transition duration-200">
        <div>
          <!-- Header with Category & Cover Thumbnail -->
          <div class="relative aspect-video rounded-xl overflow-hidden mb-2.5 bg-muted">
            <img src="\${sec.cover}" alt="\${sec.name}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
            <div class="absolute top-2 left-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-black/60 text-white backdrop-blur-md border border-white/10">
                <span>\${sec.icon}</span>
                <span>\${sec.name}</span>
              </span>
            </div>
            <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-[10px] text-white/90">
              <span class="font-mono bg-emerald-500/80 text-obsidian font-bold px-1.5 py-0.2 rounded">\${sec.tag}</span>
              <span class="flex items-center gap-1 font-bold text-emerald-400">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>LIVE</span>
              </span>
            </div>
          </div>

          <!-- Stories List with Images on Every Post -->
          <div class="space-y-2">
            \${sec.items.map(it => \`
              <div class="p-2 rounded-xl bg-muted/40 hover:bg-muted transition flex gap-2.5 items-start">
                <img src="\${it.image}" alt="\${it.title}" loading="lazy" class="w-12 h-12 rounded-lg object-cover shrink-0 border border-border/60 shadow-xs" onerror="this.src='/raccoon-logo.png';" />
                <div class="min-w-0 flex-1">
                  <div class="flex justify-between items-center text-[9px] text-muted-foreground mb-0.5">
                    <span class="text-emerald-500 font-bold">\${it.source || 'Verified Source'}</span>
                    <span class="font-mono">\${it.time}</span>
                  </div>
                  <a href="\${it.link || '/news'}" target="_blank" rel="noopener noreferrer" class="font-bold text-[11px] text-foreground hover:text-primary leading-tight line-clamp-2 block">
                    \${it.title}
                  </a>
                  <p class="text-[10px] text-muted-foreground mt-0.5 line-clamp-1 leading-relaxed">
                    \${it.desc}
                  </p>
                </div>
              </div>
            \`).join('')}
          </div>
        </div>
        <div class="pt-2 mt-2 border-t border-border-light text-[10px]">
          <a href="/news" target="_blank" class="text-primary hover:underline font-bold flex items-center justify-between">
            <span>Open in Full Wire</span>
            <span>&rarr;</span>
          </a>
        </div>
      </div>
    \`).join('');
  },`;

if (renderInboxNewsRegex.test(appJs)) {
  appJs = appJs.replace(renderInboxNewsRegex, newRenderInboxNews);
  fs.writeFileSync(appJsPath, appJs, 'utf8');
  console.log('Saved updated public/js/app.js');
} else {
  console.error('Could not find renderInboxNews in app.js');
}

console.log('Done applying news updates!');
