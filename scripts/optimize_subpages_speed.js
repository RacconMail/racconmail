const fs = require('fs');
const path = require('path');

const prefetchSnippet = `
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

      document.addEventListener('mouseover', function(e) {
        const a = e.target.closest('a');
        if (a && a.href && a.origin === window.location.origin) {
          prefetch(a.pathname + a.search);
        }
      }, { passive: true });

      document.addEventListener('touchstart', function(e) {
        const a = e.target.closest('a');
        if (a && a.href && a.origin === window.location.origin) {
          prefetch(a.pathname + a.search);
        }
      }, { passive: true });
    })();
  </script>
`;

const subpages = [
  'public/news.html',
  'public/stories.html',
  'public/forum.html',
  'public/auth.html',
  'public/legal_terms.html',
  'public/legal_privacy.html'
];

subpages.forEach(rel => {
  const file = path.join(__dirname, '..', rel);
  if (fs.existsSync(file)) {
    let c = fs.readFileSync(file, 'utf8');
    if (!c.includes('Instant Navigation Prefetch Engine')) {
      c = c.replace('</body>', prefetchSnippet + '\n</body>');
      fs.writeFileSync(file, c, 'utf8');
      console.log('Added prefetch engine to', rel);
    }
  }
});
console.log('Subpages optimized!');
