const fs = require('fs');
const path = require('path');

const indexPath = path.join(__dirname, '..', 'public', 'index.html');
let html = fs.readFileSync(indexPath, 'utf8');

// 1. Remove blocking no-cache meta tags and replace with high-performance caching
const noCacheBlockRegex = /<meta\s+http-equiv="Cache-Control"\s+content="no-cache,\s*no-store,\s*must-revalidate"\s*\/?>\s*<meta\s+http-equiv="Pragma"\s+content="no-cache"\s*\/?>\s*<meta\s+http-equiv="Expires"\s+content="0"\s*\/?>/i;

if (noCacheBlockRegex.test(html)) {
  html = html.replace(noCacheBlockRegex, '<meta http-equiv="Cache-Control" content="public, max-age=3600" />');
  console.log('Removed blocking no-cache meta tags from index.html!');
}

// 2. Add Preconnects & DNS Prefetch
const preconnects = `  <!-- High-Speed CDN & Font Preconnects -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
  <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin />
  <link rel="dns-prefetch" href="https://fonts.googleapis.com" />
  <link rel="dns-prefetch" href="https://fonts.gstatic.com" />
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com" />
  <link rel="dns-prefetch" href="https://cdn.tailwindcss.com" />`;

if (!html.includes('dns-prefetch')) {
  html = html.replace('<link rel="canonical" href="https://racconmail.eu/" />', '<link rel="canonical" href="https://racconmail.eu/" />\n' + preconnects);
  console.log('Added high-speed preconnects to index.html!');
}

// 3. Add content-auto to below-the-fold sections
if (html.includes('id="compare" class="w-full')) {
  html = html.replace('id="compare" class="w-full', 'id="compare" class="content-auto w-full');
  console.log('Added content-auto to #compare section!');
}

if (html.includes('<footer class="w-full')) {
  html = html.replace('<footer class="w-full', '<footer class="content-auto w-full');
  console.log('Added content-auto to footer!');
}

// 4. Optimize image loading attributes (async decoding & lazy loading)
html = html.replace(/<img\s+src="\/raccoon-logo\.png"\s+alt="Raccon Mail mascot"\s+class="h-8 w-8/g, '<img src="/raccoon-logo.png" alt="Raccon Mail mascot" fetchpriority="high" decoding="async" class="h-8 w-8');
html = html.replace(/<img\s+src="\/raccoon-logo\.png"\s+alt="Raccon Mail mascot with envelope"/g, '<img src="/raccoon-logo.png" alt="Raccon Mail mascot with envelope" fetchpriority="high" decoding="async"');

// 5. Zero-Lag Instant Prefetch Engine
const prefetchEngine = `
  <!-- Ultra-Fast Zero-Lag Instant Navigation Prefetch Engine -->
  <script>
    (function() {
      const prefetched = new Set();
      function prefetch(url) {
        if (!url || prefetched.has(url) || url.startsWith('#') || url.startsWith('javascript:') || url.startsWith('mailto:') || url.startsWith('tel:')) return;
        prefetched.add(url);
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);
      }

      // Prefetch on mouse hover
      document.addEventListener('mouseover', function(e) {
        const a = e.target.closest('a');
        if (a && a.href && a.origin === window.location.origin) {
          prefetch(a.pathname + a.search);
        }
      }, { passive: true });

      // Prefetch on mobile touchstart
      document.addEventListener('touchstart', function(e) {
        const a = e.target.closest('a');
        if (a && a.href && a.origin === window.location.origin) {
          prefetch(a.pathname + a.search);
        }
      }, { passive: true });

      // Immediate prefetch of primary subpages after initial render
      window.addEventListener('load', function() {
        setTimeout(function() {
          ['/inbox', '/news', '/stories', '/forum', '/auth'].forEach(prefetch);
        }, 1200);
      });
    })();
  </script>
`;

if (!html.includes('Instant Navigation Prefetch Engine')) {
  html = html.replace('</body>', prefetchEngine + '\n</body>');
  console.log('Added Zero-Lag Instant Navigation Prefetch Engine to index.html!');
}

fs.writeFileSync(indexPath, html, 'utf8');
console.log('Successfully optimized public/index.html for speed and fluidity!');
