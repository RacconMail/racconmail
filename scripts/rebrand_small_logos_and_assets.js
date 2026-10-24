const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const { execSync } = require('child_process');

const workDir = path.resolve(__dirname, '..');
const publicDir = path.join(workDir, 'public');
const svgLogoPath = path.join(publicDir, 'raccoon-logo.svg');
const pngLogoPath = path.join(publicDir, 'raccoon-logo.png');

function createIco(images) {
  const count = images.length;
  const headerSize = 6;
  const entrySize = 16;
  let offset = headerSize + (entrySize * count);

  const header = Buffer.alloc(headerSize);
  header.writeUInt16LE(0, 0);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(count, 4);

  const entries = [];
  const buffers = [];

  for (const img of images) {
    const entry = Buffer.alloc(entrySize);
    entry.writeUInt8(img.width >= 256 ? 0 : img.width, 0);
    entry.writeUInt8(img.height >= 256 ? 0 : img.height, 1);
    entry.writeUInt8(0, 2);
    entry.writeUInt8(0, 3);
    entry.writeUInt16LE(1, 4);
    entry.writeUInt16LE(32, 6);
    entry.writeUInt32LE(img.buffer.length, 8);
    entry.writeUInt32LE(offset, 12);

    entries.push(entry);
    buffers.push(img.buffer);
    offset += img.buffer.length;
  }

  return Buffer.concat([header, ...entries, ...buffers]);
}

async function step1_generateFavicons() {
  console.log('\n=== STEP 1: GENERATE VECTOR & HIGH-DPI FAVICONS ===');
  
  // 1. Copy raccoon-logo.svg to favicon.svg
  fs.copyFileSync(svgLogoPath, path.join(publicDir, 'favicon.svg'));
  console.log('✅ Updated public/favicon.svg from vector raccoon-logo.svg');

  // 2. Multi-size PNGs rasterized cleanly from vector SVG
  const sizes = [
    { name: 'favicon-16x16.png', size: 16 },
    { name: 'favicon-32x32.png', size: 32 },
    { name: 'favicon-48x48.png', size: 48 },
    { name: 'favicon-96x96.png', size: 96 },
    { name: 'apple-touch-icon.png', size: 180 },
    { name: 'android-chrome-192x192.png', size: 192 },
    { name: 'android-chrome-512x512.png', size: 512 }
  ];

  const icoImages = [];
  for (const s of sizes) {
    const buf = await sharp(svgLogoPath)
      .resize(s.size, s.size, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
      .png()
      .toBuffer();
    fs.writeFileSync(path.join(publicDir, s.name), buf);
    console.log(`✅ Generated ${s.name} (${s.size}x${s.size}) - ${buf.length} bytes`);
    if ([16, 32, 48].includes(s.size)) {
      icoImages.push({ width: s.size, height: s.size, buffer: buf });
    }
  }

  // 3. favicon.png (48x48)
  const fav48 = await sharp(svgLogoPath).resize(48, 48).png().toBuffer();
  fs.writeFileSync(path.join(publicDir, 'favicon.png'), fav48);
  console.log('✅ Generated public/favicon.png');

  // 4. favicon.ico
  const icoBuf = createIco(icoImages);
  fs.writeFileSync(path.join(publicDir, 'favicon.ico'), icoBuf);
  console.log('✅ Generated public/favicon.ico with multi-size 16, 32, 48');

  // 5. Update manifest.json
  const manifestPath = path.join(publicDir, 'manifest.json');
  const manifestObj = {
    name: 'Raccon Mail',
    short_name: 'RacconMail',
    description: 'Clever, secure email on web and mobile. Masked from spam.',
    start_url: '/inbox',
    display: 'standalone',
    background_color: '#FFFFFF',
    theme_color: '#FFFFFF',
    icons: [
      {
        src: '/android-chrome-192x192.png',
        sizes: '192x192',
        type: 'image/png'
      },
      {
        src: '/android-chrome-512x512.png',
        sizes: '512x512',
        type: 'image/png'
      },
      {
        src: '/raccoon-logo.svg',
        sizes: 'any',
        type: 'image/svg+xml'
      }
    ]
  };
  fs.writeFileSync(manifestPath, JSON.stringify(manifestObj, null, 2));
  console.log('✅ Updated public/manifest.json');
}

async function step2_updateDesktopAndApkLocalFiles() {
  console.log('\n=== STEP 2: UPDATE DESKTOP & APK LOCAL ASSETS ===');
  
  // Desktop
  const desktopDir = path.join(workDir, 'desktop');
  if (fs.existsSync(desktopDir)) {
    fs.copyFileSync(pngLogoPath, path.join(desktopDir, 'icon.png'));
    fs.copyFileSync(path.join(publicDir, 'favicon.ico'), path.join(desktopDir, 'raccoon.ico'));
    console.log('✅ Updated desktop/icon.png and desktop/raccoon.ico');
  }

  const desktopBuildDir = path.join(workDir, 'desktop_build');
  if (fs.existsSync(desktopBuildDir)) {
    fs.copyFileSync(pngLogoPath, path.join(desktopBuildDir, 'icon.png'));
    fs.copyFileSync(path.join(publicDir, 'favicon.ico'), path.join(desktopBuildDir, 'icon.ico'));
    console.log('✅ Updated desktop_build/icon.png and desktop_build/icon.ico');

    // Repackage Desktop zip
    const zipPath = path.join(publicDir, 'downloads', 'RacconMail-Desktop-v2.0-Windows.zip');
    if (fs.existsSync(zipPath)) fs.unlinkSync(zipPath);
    try {
      execSync(`powershell -Command "Compress-Archive -Path '${desktopBuildDir}/*' -DestinationPath '${zipPath}' -Force"`, { stdio: 'inherit' });
      console.log(`✅ Repackaged Desktop build zip: ${fs.statSync(zipPath).size} bytes`);
    } catch (e) {
      console.warn('Compress-Archive warning:', e.message);
    }
  }

  // APK assets
  const apkBuildDir = path.join(workDir, 'apk_build', 'res', 'drawable');
  if (fs.existsSync(apkBuildDir)) {
    fs.copyFileSync(pngLogoPath, path.join(apkBuildDir, 'icon.png'));
    console.log('✅ Updated apk_build/res/drawable/icon.png');
  }

  const apkExtractedWeb = path.join(workDir, 'apk_extracted', 'assets', 'web');
  if (fs.existsSync(apkExtractedWeb)) {
    fs.copyFileSync(pngLogoPath, path.join(apkExtractedWeb, 'swv_splash.png'));
    fs.copyFileSync(pngLogoPath, path.join(apkExtractedWeb, 'swv_splash_white.png'));
    console.log('✅ Updated apk_extracted swv_splash.png and swv_splash_white.png');
  }

  // Re-run build_clean_apk.js
  try {
    console.log('Re-running build_clean_apk.js...');
    execSync('node build_clean_apk.js', { stdio: 'inherit', cwd: workDir });
    console.log('✅ Android APK built, signed, and aligned successfully!');
  } catch (e) {
    console.error('APK build error:', e.message);
  }
}

function step3_rebrandHtmlPages() {
  console.log('\n=== STEP 3: REBRAND SMALL LOGOS ACROSS ALL HTML & PHP PAGES ===');

  // 1. index.html
  let indexHtml = fs.readFileSync(path.join(publicDir, 'index.html'), 'utf8');
  // Header logo
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail mascot" fetchpriority="high" decoding="async" class="h-8 w-8 sm:h-10 sm:w-10 object-contain shrink-0 drop-shadow-xs"\/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail mascot" fetchpriority="high" decoding="async" class="h-8 w-8 sm:h-10 sm:w-10 object-contain shrink-0 drop-shadow-xs"/>'
  );
  // Speech bubble small mascot icon
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccoon icon" class="h-8 w-8 object-contain shrink-0 mt-0.5 filter drop-shadow-sm" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccoon icon" class="h-8 w-8 object-contain shrink-0 mt-0.5 filter drop-shadow-sm" />'
  );
  // Preview demo sidebar brand logo
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon" class="h-6 w-6 raccoon-anim object-contain"\/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon" class="h-6 w-6 raccoon-anim object-contain"/>'
  );
  // Toast preview pill logo
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-6 w-6 object-contain" alt="Raccon Mail" \/>/,
    '<img src="/raccoon-logo.svg" class="h-6 w-6 object-contain" alt="Raccon Mail" />'
  );
  // Phone mockup header brand logo + remove v2.0
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-6 w-6 raccoon-anim object-contain" \/>\s*<span class="font-display font-extrabold text-xs text-slate-900">Raccon Mail <span class="text-\[9px\] font-mono text-cyan-600 font-bold">Mobile v2\.0<\/span><\/span>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-6 w-6 raccoon-anim object-contain" />\n                      <span class="font-display font-extrabold text-xs text-slate-900">Raccon Mail <span class="text-[9px] font-mono text-slate-600 font-bold">Mobile</span></span>'
  );
  // Comparison table header logo
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-5 w-5 object-contain inline-block" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-5 w-5 object-contain inline-block" />'
  );
  // Footer bottom logo
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-6 w-6 object-contain" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-6 w-6 object-contain" />'
  );
  // Auth modal logo
  indexHtml = indexHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-12 w-12 mx-auto raccoon-anim object-contain mb-2" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-12 w-12 mx-auto raccoon-anim object-contain mb-2" />'
  );
  fs.writeFileSync(path.join(publicDir, 'index.html'), indexHtml);
  console.log('✅ Rebranded small logos in public/index.html');

  // 2. inbox.php
  let inboxPhp = fs.readFileSync(path.join(publicDir, 'inbox.php'), 'utf8');
  // Header top-left brand logo
  inboxPhp = inboxPhp.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-7 w-7 object-contain group-hover:scale-105 transition" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-7 w-7 object-contain group-hover:scale-105 transition" />'
  );
  // Empty conversation
  inboxPhp = inboxPhp.replace(
    /<img src="\/raccoon-logo\.png" class="h-20 w-20 object-contain opacity-60 mb-3" \/>/,
    '<img src="/raccoon-logo.svg" class="h-20 w-20 object-contain opacity-60 mb-3" />'
  );
  // Success modal
  inboxPhp = inboxPhp.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mascot" class="h-24 w-24 mx-auto object-contain filter drop-shadow-md animate-bounce" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mascot" class="h-24 w-24 mx-auto object-contain filter drop-shadow-md animate-bounce" />'
  );
  fs.writeFileSync(path.join(publicDir, 'inbox.php'), inboxPhp);
  console.log('✅ Rebranded small logos in public/inbox.php');

  // 3. forum.html
  let forumHtml = fs.readFileSync(path.join(publicDir, 'forum.html'), 'utf8');
  // Header brand logo
  forumHtml = forumHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mascot" class="h-8 w-8 sm:h-9 sm:w-9 object-contain drop-shadow-xs" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mascot" class="h-8 w-8 sm:h-9 sm:w-9 object-contain drop-shadow-xs" />'
  );
  // Empty state
  forumHtml = forumHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-16 w-16 mx-auto mb-3 opacity-60" \/>/,
    '<img src="/raccoon-logo.svg" class="h-16 w-16 mx-auto mb-3 opacity-60" />'
  );
  fs.writeFileSync(path.join(publicDir, 'forum.html'), forumHtml);
  console.log('✅ Rebranded small logos in public/forum.html');

  // 4. stories.html
  let storiesHtml = fs.readFileSync(path.join(publicDir, 'stories.html'), 'utf8');
  // Header brand logo
  storiesHtml = storiesHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mascot" class="h-8 w-8 sm:h-9 sm:w-9 object-contain drop-shadow-xs" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mascot" class="h-8 w-8 sm:h-9 sm:w-9 object-contain drop-shadow-xs" />'
  );
  // Empty state
  storiesHtml = storiesHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-16 w-16 mx-auto mb-3 opacity-60" \/>/,
    '<img src="/raccoon-logo.svg" class="h-16 w-16 mx-auto mb-3 opacity-60" />'
  );
  fs.writeFileSync(path.join(publicDir, 'stories.html'), storiesHtml);
  console.log('✅ Rebranded small logos in public/stories.html');

  // 5. news.html
  let newsHtml = fs.readFileSync(path.join(publicDir, 'news.html'), 'utf8');
  // Header brand logo
  newsHtml = newsHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon" class="h-8 w-8 sm:h-9 sm:w-9 object-contain group-hover:scale-105 transition shrink-0" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon" class="h-8 w-8 sm:h-9 sm:w-9 object-contain group-hover:scale-105 transition shrink-0" />'
  );
  // Article reader brand icon
  newsHtml = newsHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-4 w-4 object-contain" \/>/,
    '<img src="/raccoon-logo.svg" class="h-4 w-4 object-contain" />'
  );
  // Drawer footer brand
  newsHtml = newsHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-10 w-10 object-contain drop-shadow" alt="Raccon" \/>/,
    '<img src="/raccoon-logo.svg" class="h-10 w-10 object-contain drop-shadow" alt="Raccon" />'
  );
  fs.writeFileSync(path.join(publicDir, 'news.html'), newsHtml);
  console.log('✅ Rebranded small logos in public/news.html');

  // 6. auth.html
  let authHtml = fs.readFileSync(path.join(publicDir, 'auth.html'), 'utf8');
  // Card header brand logo
  authHtml = authHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-8 w-8 raccoon-anim object-contain shrink-0 group-hover:scale-105 transition transform" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-8 w-8 raccoon-anim object-contain shrink-0 group-hover:scale-105 transition transform" />'
  );
  // Runner mascot
  authHtml = authHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Running Raccoon" id="raccoon-runner-img" class="h-12 w-12 object-contain raccoon-running filter drop-shadow\(0 2px 4px rgba\(0,0,0,0\.18\)\)" \/>/,
    '<img src="/raccoon-logo.svg" alt="Running Raccoon" id="raccoon-runner-img" class="h-12 w-12 object-contain raccoon-running filter drop-shadow(0 2px 4px rgba(0,0,0,0.18))" />'
  );
  // Footer brand
  authHtml = authHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-5 w-5 object-contain" \/>/,
    '<img src="/raccoon-logo.svg" class="h-5 w-5 object-contain" />'
  );
  fs.writeFileSync(path.join(publicDir, 'auth.html'), authHtml);
  console.log('✅ Rebranded small logos in public/auth.html');

  // 7. admin.html
  let adminHtml = fs.readFileSync(path.join(publicDir, 'admin.html'), 'utf8');
  // Lock screen brand
  adminHtml = adminHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-14 w-14 mx-auto mb-2\.5 object-contain" alt="Raccon Logo" \/>/,
    '<img src="/raccoon-logo.svg" class="h-14 w-14 mx-auto mb-2.5 object-contain" alt="Raccon Logo" />'
  );
  // Top navbar brand
  adminHtml = adminHtml.replace(
    /<img src="\/raccoon-logo\.png" class="h-8 w-8 sm:h-9 sm:w-9 object-contain group-hover:scale-105 transition shrink-0" alt="Raccon Logo" \/>/,
    '<img src="/raccoon-logo.svg" class="h-8 w-8 sm:h-9 sm:w-9 object-contain group-hover:scale-105 transition shrink-0" alt="Raccon Logo" />'
  );
  fs.writeFileSync(path.join(publicDir, 'admin.html'), adminHtml);
  console.log('✅ Rebranded small logos in public/admin.html');

  // 8. legal_privacy.html
  let privacyHtml = fs.readFileSync(path.join(publicDir, 'legal_privacy.html'), 'utf8');
  privacyHtml = privacyHtml.replace(
    /<link rel="icon" type="image\/png" href="\/raccoon-logo\.png" \/>/,
    `<link rel="icon" href="/favicon.ico" sizes="48x48" />\n  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />\n  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />\n  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />`
  );
  privacyHtml = privacyHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-9 w-9 object-contain shrink-0" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-9 w-9 object-contain shrink-0" />'
  );
  privacyHtml = privacyHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon" class="h-5 w-5 object-contain" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon" class="h-5 w-5 object-contain" />'
  );
  fs.writeFileSync(path.join(publicDir, 'legal_privacy.html'), privacyHtml);
  console.log('✅ Rebranded small logos and favicon suite in public/legal_privacy.html');

  // 9. legal_terms.html
  let termsHtml = fs.readFileSync(path.join(publicDir, 'legal_terms.html'), 'utf8');
  termsHtml = termsHtml.replace(
    /<link rel="icon" type="image\/png" href="\/raccoon-logo\.png" \/>/,
    `<link rel="icon" href="/favicon.ico" sizes="48x48" />\n  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />\n  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />\n  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />`
  );
  termsHtml = termsHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon Mail" class="h-9 w-9 object-contain shrink-0" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-9 w-9 object-contain shrink-0" />'
  );
  termsHtml = termsHtml.replace(
    /<img src="\/raccoon-logo\.png" alt="Raccon" class="h-5 w-5 object-contain" \/>/,
    '<img src="/raccoon-logo.svg" alt="Raccon" class="h-5 w-5 object-contain" />'
  );
  fs.writeFileSync(path.join(publicDir, 'legal_terms.html'), termsHtml);
  console.log('✅ Rebranded small logos and favicon suite in public/legal_terms.html');
}

async function run() {
  await step1_generateFavicons();
  await step2_updateDesktopAndApkLocalFiles();
  step3_rebrandHtmlPages();
  console.log('\n🎉 ALL SMALL LOGOS REBRANDED CLEANLY ACROSS ALL PLATFORMS!');
}

run().catch(console.error);
