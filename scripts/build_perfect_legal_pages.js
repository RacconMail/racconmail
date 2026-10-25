const fs = require('fs');
const path = require('path');

const publicDir = path.join(__dirname, '..', 'public');

// =========================================================================
// 1. PERFECT HIGH-CONTRAST, UNIVERSALLY ACCESSIBLE PRIVACY POLICY
// =========================================================================
const privacyHtml = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>Privacy Policy — Raccon Mail</title>
  <meta name="description" content="Official Privacy Policy for RacconMail. Zero surveillance, zero ad tracking, client-side encryption, and strict GDPR & Swiss privacy compliance.">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <link rel="canonical" href="https://racconmail.eu/legal/privacy" />
  
  <meta property="og:title" content="Privacy Policy — Raccon Mail" />
  <meta property="og:description" content="Zero surveillance, zero ad tracking, client-side encryption, and strict GDPR compliance on racconmail.eu." />
  <meta property="og:type" content="article" />
  <meta property="og:url" content="https://racconmail.eu/legal/privacy" />
  <meta property="og:image" content="https://racconmail.eu/raccoon-logo.png" />
  <meta name="twitter:card" content="summary" />
  <link rel="icon" href="/favicon.ico" sizes="48x48" />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Custom Styles -->
  <link rel="stylesheet" href="/css/raccoon-mail.css" />

  <style>
    :root {
      color-scheme: light;
    }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background-color: #F8FAFC !important;
      color: #0F172A !important;
      -webkit-font-smoothing: antialiased;
    }
    .font-display {
      font-family: 'Space Grotesk', system-ui, sans-serif;
    }
    /* Ensure high contrast writings on all apps */
    p, li, span, h1, h2, h3, h4, div {
      -webkit-text-size-adjust: 100%;
    }
  </style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] flex flex-col antialiased selection:bg-slate-900 selection:text-white">

  <!-- Header Navbar -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-xs">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4">
      <a href="/" class="flex items-center gap-2.5 font-display text-lg sm:text-xl font-extrabold text-slate-900 hover:opacity-90 transition">
        <img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-8 w-8 sm:h-9 sm:w-9 object-contain shrink-0" />
        <span class="tracking-tight text-slate-900 font-extrabold">Raccon <span class="text-[#E06748]">Mail</span></span>
      </a>

      <!-- Quick Nav Links -->
      <div class="flex items-center gap-2 sm:gap-3">
        <a href="/legal/terms" class="hidden sm:inline-flex text-xs font-bold text-slate-600 hover:text-slate-900 transition px-2.5 py-1.5 rounded-lg hover:bg-slate-100">
          Terms of Service
        </a>
        <a href="/" class="text-xs sm:text-sm font-bold text-slate-700 hover:text-slate-900 transition px-2.5 sm:px-3 py-1.5 flex items-center gap-1.5 rounded-xl hover:bg-slate-100">
          <i class="fas fa-arrow-left text-xs text-slate-500"></i>
          <span>Home</span>
        </a>
        <a href="/auth?tab=login" class="rounded-full bg-slate-900 hover:bg-black text-white px-4 sm:px-5 py-1.5 sm:py-2 text-xs font-bold transition shadow-xs">
          Sign In
        </a>
      </div>
    </div>
  </header>

  <!-- Legal Content Container -->
  <main class="flex-1 max-w-4xl mx-auto w-full px-3.5 sm:px-6 py-6 sm:py-12">
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/90 p-5 sm:p-10 md:p-12 shadow-xs space-y-8 sm:space-y-10">
      
      <!-- Document Header -->
      <div class="border-b border-slate-200 pb-6 text-center sm:text-left">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-extrabold bg-[#FFEFEA] text-[#E06748] border border-orange-200/80 mb-3 shadow-2xs">
          <i class="fas fa-shield-halved text-xs"></i>
          <span>ZERO-KNOWLEDGE PRIVACY CHARTER &bull; OCTOBER 2026</span>
        </div>
        <h1 class="font-display text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Privacy Policy
        </h1>
        <p class="text-sm sm:text-base text-slate-700 mt-2 font-medium leading-relaxed">
          How we protect your identity, safeguard your data, and guarantee zero surveillance on racconmail.eu across web, desktop, and mobile apps.
        </p>
      </div>

      <!-- Section 1: Core Philosophy -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">1</span>
          <span>Our Data Minimization Philosophy</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          The best way to protect your personal data is simply not to collect it. Raccon Mail requires only an email username and password to create an account. We do not ask for or collect your legal name, physical address, telephone number, credit card details for free tiers, or government identification.
        </p>
      </section>

      <!-- Section 2: What We Store -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">2</span>
          <span>Information We Store</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed">
          We maintain only the bare minimum technical data necessary to deliver your encrypted correspondence:
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
            <h3 class="font-extrabold text-xs sm:text-sm text-slate-900 flex items-center gap-2 mb-1">
              <i class="fas fa-key text-xs text-[#E06748]"></i> Cryptographic Credentials
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed">
              Your chosen username and securely salted one-way cryptographic hash of your password (Argon2id / Bcrypt). We never see or store plaintext passwords.
            </p>
          </div>

          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
            <h3 class="font-extrabold text-xs sm:text-sm text-slate-900 flex items-center gap-2 mb-1">
              <i class="fas fa-envelope text-xs text-[#4A6FDC]"></i> Encrypted Mailbox
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed">
              Inbound and outbound messages, drafts, and attachments delivered to or sent from your account, secured in isolated member stores with zero ad scanning.
            </p>
          </div>
        </div>
      </section>

      <!-- Section 3: Zero Telemetry & Tracking -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">3</span>
          <span>Zero Third-Party Telemetry &amp; No Ads Profiling</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed">
          Unlike legacy advertising-driven email providers, Raccon Mail does <strong>NOT</strong> scan the text of your emails to build marketing profiles, serve behavioral target ads, or sell audience segments to data brokers. We run zero Google Analytics, zero Meta Pixels, and zero behavioral trackers across our mailbox applications.
        </p>
      </section>

      <!-- Section 4: Cookies & Storage -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">4</span>
          <span>Cookies &amp; Local Storage</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed">
          We use strictly necessary first-party session cookies and browser <code class="bg-slate-100 px-2 py-0.5 rounded text-xs text-slate-900 font-mono font-bold border border-slate-200">localStorage</code> exclusively for:
        </p>
        <ul class="list-disc pl-5 sm:pl-6 space-y-1.5 text-xs sm:text-sm text-slate-700">
          <li>Maintaining your secure authenticated session token.</li>
          <li>Remembering your visual interface preferences (text size, reading theme).</li>
          <li>Local offline draft caching to prevent message loss on unstable mobile networks.</li>
        </ul>
      </section>

      <!-- Section 5: Global Legal Compliance -->
      <section class="space-y-4">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">5</span>
          <span>Global Legal &amp; Regulatory Compliance</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
            <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-900 mb-1 flex items-center gap-1.5">
              <i class="fas fa-landmark-flag text-slate-700"></i> European Union (GDPR)
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed">
              Full adherence to Regulation (EU) 2016/679. Guaranteed right of access (Art. 15), rectification (Art. 16), immediate erasure / right to be forgotten (Art. 17), and data portability (Art. 20). User data is hosted strictly in European jurisdictions.
            </p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
            <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-900 mb-1 flex items-center gap-1.5">
              <i class="fas fa-flag-usa text-slate-700"></i> United States (CCPA &amp; CPRA)
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed">
              We never sell, rent, or share personal information with third parties. Users maintain the right to know what personal data is processed, the right to delete, and non-discrimination protection under California Civil Code &sect; 1798.100 et seq.
            </p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
            <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-900 mb-1 flex items-center gap-1.5">
              <i class="fas fa-earth-americas text-slate-700"></i> Latin America (Brazil LGPD)
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed">
              Compliance with Lei Geral de Prote&ccedil;&atilde;o de Dados (Lei 13.709/2018). Transparent, purpose-limited data processing with full member sovereignty and immediate revocation rights.
            </p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
            <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-900 mb-1 flex items-center gap-1.5">
              <i class="fas fa-earth-asia text-slate-700"></i> Asia &amp; Africa (APPI, PIPL &amp; POPIA)
            </h3>
            <p class="text-xs text-slate-700 leading-relaxed">
              Conforming with Japan's Act on the Protection of Personal Information (APPI), China's PIPL, and South Africa's Protection of Personal Information Act (POPIA). End-to-end cryptographic boundaries protect against unlawful cross-border interception.
            </p>
          </div>
        </div>
      </section>

      <!-- Section 6: Security Disclosures & Contact -->
      <section class="space-y-3 border-t border-slate-200 pt-6">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">6</span>
          <span>Privacy Officer &amp; Security Disclosures</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed">
          For any privacy questions, data export inquiries, or responsible security disclosures:
        </p>
        <div class="mt-3 p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold shrink-0">
              <i class="fas fa-shield-alt"></i>
            </div>
            <div>
              <p class="text-xs font-extrabold text-slate-900">Raccon Mail Privacy Officer</p>
              <a href="mailto:support@racconmail.eu" class="text-xs text-slate-700 font-mono font-bold hover:underline">support@racconmail.eu</a>
            </div>
          </div>
          <a href="mailto:support@racconmail.eu" class="rounded-full bg-slate-900 hover:bg-black text-white px-4 py-2 text-xs font-bold transition shadow-2xs">
            Contact Privacy Team
          </a>
        </div>
      </section>

    </div>
  </main>

  <!-- Complete Site Footer -->
  <footer class="w-full border-t border-slate-200 bg-white py-8 text-center text-xs text-slate-600 mt-auto">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-4">
      <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 font-bold text-slate-700">
        <a href="/" class="hover:text-slate-900 transition">Home</a>
        <span>&bull;</span>
        <a href="/legal/terms" class="hover:text-slate-900 transition">Terms of Service</a>
        <span>&bull;</span>
        <a href="/legal/privacy" class="text-[#E06748] font-black">Privacy Policy</a>
        <span>&bull;</span>
        <a href="/news" class="hover:text-[#E06748] transition">News Wire</a>
        <span>&bull;</span>
        <a href="/stories" class="hover:text-[#4A6FDC] transition">Stories</a>
        <span>&bull;</span>
        <a href="/forum" class="hover:text-[#7052ff] transition">Forum</a>
        <span>&bull;</span>
        <a href="/inbox" class="hover:text-slate-900 transition">Webmail</a>
      </div>

      <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-slate-100 text-[11px] text-slate-500 font-medium">
        <div class="flex items-center gap-2">
          <img src="/raccoon-logo.svg" alt="Raccon" class="h-4 w-4 object-contain" />
          <span>&copy; 2026 Raccon Mail. All rights reserved. Zero Trackers &bull; Client-Side Encrypted.</span>
        </div>
        <div>
          <span>Hosted in European Union &bull; GDPR Compliant</span>
        </div>
      </div>
    </div>
  </footer>

</body>
</html>
`;

// =========================================================================
// 2. PERFECT HIGH-CONTRAST, UNIVERSALLY ACCESSIBLE TERMS OF SERVICE
// =========================================================================
const termsHtml = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>Terms of Service — Raccon Mail</title>
  <meta name="description" content="Official Terms of Service for RacconMail. Transparent, privacy-first nocturnal communication with zero lock-in.">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
  <link rel="canonical" href="https://racconmail.eu/legal/terms" />
  
  <meta property="og:title" content="Terms of Service — Raccon Mail" />
  <meta property="og:description" content="Clear, honest, and respectful service terms for all nocturnal accounts on racconmail.eu." />
  <meta property="og:type" content="article" />
  <meta property="og:url" content="https://racconmail.eu/legal/terms" />
  <meta property="og:image" content="https://racconmail.eu/raccoon-logo.png" />
  <meta name="twitter:card" content="summary" />
  <link rel="icon" href="/favicon.ico" sizes="48x48" />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Custom Styles -->
  <link rel="stylesheet" href="/css/raccoon-mail.css" />

  <style>
    :root {
      color-scheme: light;
    }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background-color: #F8FAFC !important;
      color: #0F172A !important;
      -webkit-font-smoothing: antialiased;
    }
    .font-display {
      font-family: 'Space Grotesk', system-ui, sans-serif;
    }
    p, li, span, h1, h2, h3, h4, div {
      -webkit-text-size-adjust: 100%;
    }
  </style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] flex flex-col antialiased selection:bg-slate-900 selection:text-white">

  <!-- Header Navbar -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-xs">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4">
      <a href="/" class="flex items-center gap-2.5 font-display text-lg sm:text-xl font-extrabold text-slate-900 hover:opacity-90 transition">
        <img src="/raccoon-logo.svg" alt="Raccon Mail" class="h-8 w-8 sm:h-9 sm:w-9 object-contain shrink-0" />
        <span class="tracking-tight text-slate-900 font-extrabold">Raccon <span class="text-[#E06748]">Mail</span></span>
      </a>

      <!-- Quick Nav Links -->
      <div class="flex items-center gap-2 sm:gap-3">
        <a href="/legal/privacy" class="hidden sm:inline-flex text-xs font-bold text-slate-600 hover:text-slate-900 transition px-2.5 py-1.5 rounded-lg hover:bg-slate-100">
          Privacy Policy
        </a>
        <a href="/" class="text-xs sm:text-sm font-bold text-slate-700 hover:text-slate-900 transition px-2.5 sm:px-3 py-1.5 flex items-center gap-1.5 rounded-xl hover:bg-slate-100">
          <i class="fas fa-arrow-left text-xs text-slate-500"></i>
          <span>Home</span>
        </a>
        <a href="/auth?tab=login" class="rounded-full bg-slate-900 hover:bg-black text-white px-4 sm:px-5 py-1.5 sm:py-2 text-xs font-bold transition shadow-xs">
          Sign In
        </a>
      </div>
    </div>
  </header>

  <!-- Legal Content Container -->
  <main class="flex-1 max-w-4xl mx-auto w-full px-3.5 sm:px-6 py-6 sm:py-12">
    <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/90 p-5 sm:p-10 md:p-12 shadow-xs space-y-8 sm:space-y-10">
      
      <!-- Document Header -->
      <div class="border-b border-slate-200 pb-6 text-center sm:text-left">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-900 border border-slate-200 mb-3 shadow-2xs">
          <i class="fas fa-file-contract text-xs text-[#E06748]"></i>
          <span>LEGAL AGREEMENT &bull; LAST REVISED OCTOBER 2026</span>
        </div>
        <h1 class="font-display text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Terms of Service
        </h1>
        <p class="text-sm sm:text-base text-slate-700 mt-2 font-medium leading-relaxed">
          Clear, honest, and respectful service terms for all nocturnal accounts across webmail, android app, and desktop clients on racconmail.eu.
        </p>
      </div>

      <!-- Section 1: Overview -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">1</span>
          <span>Acceptance of Terms</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          By registering for, downloading, or using Raccon Mail (including our web platform at <code class="bg-slate-100 px-2 py-0.5 rounded text-xs text-slate-900 font-mono font-bold border border-slate-200">racconmail.eu</code>, our desktop client, and our mobile applications for Android and iOS), you agree to be bound by these Terms of Service. If you do not agree to these terms, do not create an account or use the service.
        </p>
      </section>

      <!-- Section 2: Privacy Guarantee -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">2</span>
          <span>Privacy &amp; Data Sovereignty Commitment</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          Raccon Mail is architected around absolute zero-knowledge privacy. We do not sell your personal data, we do not build behavioral advertising profiles, and we never inject tracking pixels into your correspondence. Your emails belong entirely to you.
        </p>
      </section>

      <!-- Section 3: Account Responsibilities -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">3</span>
          <span>Account Security &amp; Access</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          You are responsible for safeguarding your password and account credentials. Raccon Mail stores passwords using one-way cryptographic hashing (Argon2 / Bcrypt). If you lose your credentials and have not configured a verified recovery email, account contents cannot be recovered by our team due to our zero-knowledge encryption design.
        </p>
      </section>

      <!-- Section 4: Acceptable Use Policy -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">4</span>
          <span>Acceptable Use &amp; Anti-Abuse Standards</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          To protect our nocturnal mail deliverability for all honest members, you agree <strong>NOT</strong> to use Raccon Mail to:
        </p>
        <ul class="list-disc pl-5 sm:pl-6 space-y-2 text-xs sm:text-sm text-slate-700">
          <li>Send unsolicited mass commercial emails (spam), phishing attempts, or malware attachments.</li>
          <li>Engage in illegal activities, child sexual abuse material (CSAM), or harassment.</li>
          <li>Attempt unauthorized access or denial-of-service attacks against Raccon Mail infrastructure.</li>
        </ul>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          Accounts identified as automated spam bots or phishing transmitters will be terminated immediately.
        </p>
      </section>

      <!-- Section 5: Service Availability & Modifications -->
      <section class="space-y-3">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">5</span>
          <span>Service Availability &amp; Right to Erase</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          We strive for 99.9% nocturnal uptime. You have the right to close your Raccon Mail account at any time. Upon account deletion, all messages, contacts, and custom folders are permanently erased from our primary active storage arrays within 24 hours.
        </p>
      </section>

      <!-- Section 6: Contact Information -->
      <section class="space-y-3 border-t border-slate-200 pt-6">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center font-bold shrink-0">6</span>
          <span>Contact &amp; Legal Notices</span>
        </h2>
        <p class="text-xs sm:text-sm text-slate-800 leading-relaxed font-normal">
          For any legal inquiries, abuse reports, or terms clarification:
        </p>
        <div class="mt-3 p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold shrink-0">
              <i class="fas fa-file-contract"></i>
            </div>
            <div>
              <p class="text-xs font-extrabold text-slate-900">Raccon Mail Legal Department</p>
              <a href="mailto:support@racconmail.eu" class="text-xs text-slate-700 font-mono font-bold hover:underline">support@racconmail.eu</a>
            </div>
          </div>
          <a href="mailto:support@racconmail.eu" class="rounded-full bg-slate-900 hover:bg-black text-white px-4 py-2 text-xs font-bold transition shadow-2xs">
            Email Legal Team
          </a>
        </div>
      </section>

    </div>
  </main>

  <!-- Complete Site Footer -->
  <footer class="w-full border-t border-slate-200 bg-white py-8 text-center text-xs text-slate-600 mt-auto">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-4">
      <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 font-bold text-slate-700">
        <a href="/" class="hover:text-slate-900 transition">Home</a>
        <span>&bull;</span>
        <a href="/legal/terms" class="text-[#E06748] font-black">Terms of Service</a>
        <span>&bull;</span>
        <a href="/legal/privacy" class="hover:text-slate-900 transition">Privacy Policy</a>
        <span>&bull;</span>
        <a href="/news" class="hover:text-[#E06748] transition">News Wire</a>
        <span>&bull;</span>
        <a href="/stories" class="hover:text-[#4A6FDC] transition">Stories</a>
        <span>&bull;</span>
        <a href="/forum" class="hover:text-[#7052ff] transition">Forum</a>
        <span>&bull;</span>
        <a href="/inbox" class="hover:text-slate-900 transition">Webmail</a>
      </div>

      <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-slate-100 text-[11px] text-slate-500 font-medium">
        <div class="flex items-center gap-2">
          <img src="/raccoon-logo.svg" alt="Raccon" class="h-4 w-4 object-contain" />
          <span>&copy; 2026 Raccon Mail. All rights reserved. Zero Trackers &bull; Client-Side Encrypted.</span>
        </div>
        <div>
          <span>Hosted in European Union &bull; GDPR Compliant</span>
        </div>
      </div>
    </div>
  </footer>

</body>
</html>
`;

// Write to all target paths
const privacyTargets = [
  path.join(publicDir, 'legal_privacy.html'),
  path.join(publicDir, 'privacy.html'),
  path.join(publicDir, 'legal', 'privacy.html'),
  path.join(publicDir, 'legal', 'privacy', 'index.html')
];

privacyTargets.forEach(tgt => {
  fs.mkdirSync(path.dirname(tgt), { recursive: true });
  fs.writeFileSync(tgt, privacyHtml, 'utf8');
  console.log('Wrote Privacy Policy to:', tgt);
});

const termsTargets = [
  path.join(publicDir, 'legal_terms.html'),
  path.join(publicDir, 'terms.html'),
  path.join(publicDir, 'legal', 'terms.html'),
  path.join(publicDir, 'legal', 'terms', 'index.html')
];

termsTargets.forEach(tgt => {
  fs.mkdirSync(path.dirname(tgt), { recursive: true });
  fs.writeFileSync(tgt, termsHtml, 'utf8');
  console.log('Wrote Terms of Service to:', tgt);
});

console.log('ALL PRIVACY & TERMS DOCUMENTS GENERATED WITH HIGH-CONTRAST AND RESPONSIVE VISIBILITY!');
