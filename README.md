# 🦝 Raccon Mail

> **Clever, Masked & Nocturnal Webmail Platform**  
> *Official Open Source Codebase for [racconmail.eu](https://racconmail.eu)*

[![Website](https://img.shields.io/badge/Live%20Webmail-racconmail.eu-0F172A?style=for-the-badge&logo=firefox)](https://racconmail.eu)
[![GitHub Stars](https://img.shields.io/github/stars/RacconMail/racconmail?style=for-the-badge&logo=github&color=0ea5e9)](https://github.com/RacconMail/racconmail/stargazers)
[![GitHub Release](https://img.shields.io/github/v/release/RacconMail/racconmail?style=for-the-badge&logo=github&color=10b981)](https://github.com/RacconMail/racconmail/releases)
[![Domain Auth](https://img.shields.io/badge/SPF%20%7C%20DKIM%20%7C%20DMARC%20%7C%20BIMI-Active%20%26%20Valid-10b981?style=for-the-badge&logo=checkmarx)](https://racconmail.eu)
[![Compliance](https://img.shields.io/badge/Privacy-GDPR%20%7C%20CCPA%20%7C%20POPIA%20%7C%20APPI-6366f1?style=for-the-badge&logo=shield)](https://racconmail.eu/legal/privacy)
[![VirusTotal](https://img.shields.io/badge/VirusTotal-0%2F70%20Clean-brightgreen?style=for-the-badge&logo=virustotal)](https://www.virustotal.com/gui/domain/racconmail.eu)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)

---

## 📖 Global Overview

**Raccon Mail** is an independent, transparent, and audited open-source communication platform engineered for privacy-conscious individuals, developers, and night owls worldwide. Built from the ground up to eliminate invasive surveillance, behavioral ad profiling, and telemetry scripts, Raccon Mail delivers an ultra-fast webmail client, an interactive developer forum, an international news wire, native Android APK, and Windows desktop application.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                             🦝 RACCONMAIL ECOSYSTEM                         │
├──────────────────────┬──────────────────────┬───────────────────────────────┤
│  Web Application     │  Desktop Client      │  Mobile Android APK           │
│  https://racconmail  │  Windows x64 Setup   │  v2.0 Native Shell            │
│  Strict Privacy      │  Authenticode Signed │  120Hz Fluid Design           │
├──────────────────────┴──────────────────────┴───────────────────────────────┤
│                Instant Inbound Engine & High-Performance Core               │
│  Direct Maildir (<1ms) • DMARC Quarantine • BIMI Brand Indicator • SQLite   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🛡️ Enterprise Domain Authentication & Security (100% Configured)

Raccon Mail enforces strict domain authentication across all outbound and inbound email pipelines to eliminate spoofing, guarantee high inbox placement, and present verified branding:

1. **SPF (Sender Policy Framework)**:
   - Configured via DNS TXT with authorized IP addresses and secure hosting relay:
     `v=spf1 +a +mx +ip4:66.29.141.101 +ip4:67.223.118.30 include:spf.web-hosting.com ~all`
2. **DKIM (DomainKeys Identified Mail)**:
   - 2048-bit RSA cryptographic signing active on `default._domainkey.racconmail.eu` verifying message integrity in transit.
3. **DMARC (Domain-based Message Authentication, Reporting & Conformance)**:
   - Strict enterprise quarantine policy actively protecting user trust:
     `v=DMARC1; p=quarantine; sp=quarantine; pct=100; rua=mailto:dmarc-reports@racconmail.eu; ruf=mailto:dmarc-forensics@racconmail.eu; fo=1; aspf=r; adkim=r`
4. **BIMI (Brand Indicators for Message Identification)**:
   - Official SVG indicator published at `default._bimi.racconmail.eu` displaying the verified Raccon Mail avatar directly in Apple Mail, Gmail, and Yahoo inboxes:
     `v=BIMI1; l=https://racconmail.eu/raccoon-logo.svg; a=;`
5. **Authenticode Code Signing**:
   - Desktop executable releases (`RacconMail-Setup.exe`) are digitally signed with RFC 3161 SHA-256 DigiCert timestamping, eliminating untrusted publisher prompts.

---

## ⚡ Zero-Lag Instant Inbound Email Engine

- **Microsecond Inbound Processing**: High-speed Exim Maildir ingestion processes inbound mail on local NVMe storage in **under 1 millisecond**, bypassing network socket bottlenecks.
- **Adaptive Sync**: 3-second rapid background cycle coupled with instant event triggers (`visibilitychange`, `window.focus`, and folder navigation) ensures immediate delivery.
- **Signature Raccoon Acoustics**:
  - 🐾 **Send Confirmation**: 1.00-second joyful raccoon chitter trill (`/sounds/happy-raccoon.wav`) with harmonic pitch glides ($1150\text{ Hz} \to 1980\text{ Hz}$).
  - ⚡ **Inbound Alert**: 0.85-second startled squeak notification (`/sounds/afraid-raccoon.wav`) with rapid panicky flutter, paired with visual sliding toast alerts.
  - **Embedded Web Audio Synthesizer**: Zero-latency algorithmic audio synthesis fallback runs natively without external network requests.

---

## 🌍 Internationalization (i18n) & Global SEO Architecture

Raccon Mail is architected for top-tier indexing across all global regions and search engines:

| Region | Primary Search Engines | Target Language / Hreflang | Regional Gateway |
| :--- | :--- | :---: | :--- |
| **Americas & UK** | Google, Bing, DuckDuckGo | `en`, `x-default` | `https://racconmail.eu/` |
| **Latin America & Spain** | Google, Yahoo | `es` | `https://racconmail.eu/?lang=es` |
| **DACH Europe** | Google, Ecosia | `de` | `https://racconmail.eu/?lang=de` |
| **France & Francophone Africa** | Google, Qwant | `fr` | `https://racconmail.eu/?lang=fr` |
| **Greater China & Asia** | Baidu, Sogou | `zh` | `https://racconmail.eu/?lang=zh` |
| **Japan** | Yahoo! Japan, Google | `ja` | `https://racconmail.eu/?lang=ja` |
| **South Korea** | Naver (Yeti), Google | `ko` | `https://racconmail.eu/?lang=ko` |
| **Eastern Europe & Central Asia** | Yandex, Google | `ru` | `https://racconmail.eu/?lang=ru` |
| **Middle East & North Africa** | Google, Bing | `ar` | `https://racconmail.eu/?lang=ar` |
| **Brazil & Portugal** | Google | `pt` | `https://racconmail.eu/?lang=pt` |

- **Search Engine Verifications Active**: Google Search Console, Microsoft Bing Webmaster, Yandex Webmaster, Baidu Spider, and Naver Search Advisor.
- **XML Sitemaps**: Comprehensive 25+ URL deep sitemap indexed with IndexNow protocol across `api.indexnow.org`, `bing.com`, and `yandex.com`.

---

## 🔒 Global Privacy & Legal Compliance

Raccon Mail guarantees strict data sovereignty and zero commercial surveillance:

- **European Union**: Regulation (EU) 2016/679 (GDPR) Articles 15–20 compliance. Complete right of access, rectification, and 1-click permanent erasure.
- **United States**: California Consumer Privacy Act (CCPA) and CPRA compliance. Zero sale or sharing of user information; no tracking cookies.
- **Latin America**: Brazil Lei Geral de Proteção de Dados (LGPD).
- **Asia & Africa**: South Africa POPIA, Japan APPI, and China PIPL conforming data handling.
- **Storage Standards**: Isolated SQLite data files with AES-256 resting encryption and TLS 1.3 encrypted transport.

---

## 📥 Download Official Builds

| Platform | Package | Architecture | Verification | Direct Download |
| :--- | :--- | :---: | :---: | :--- |
| **Windows** | `RacconMail-Setup.exe` | x64 / x86 | Authenticode Signed | [Download Setup](https://racconmail.eu/downloads/RacconMail-Setup.exe) |
| **Android** | `RacconMail-v2.0.apk` | ARM64 / Universal | v2 Signed | [Download APK](https://racconmail.eu/downloads/RacconMail-v2.0.apk) |
| **Web App** | Webmail Client | Universal PWA | 100% Responsive | [Open Webmail](https://racconmail.eu/) |

All builds are mirrored on [GitHub Releases](https://github.com/RacconMail/racconmail/releases/tag/v2.0).

---

## 🚀 Local Development

### Prerequisites

- PHP 8.1+ with `pdo_sqlite`, `mbstring`, and `fileinfo`
- Apache (`mod_rewrite`, `mod_deflate`, `mod_headers`) or Nginx
- Node.js 18+

### Setup

```bash
# 1. Clone repository
git clone https://github.com/RacconMail/racconmail.git
cd racconmail

# 2. Launch development server
php -S 127.0.0.1:8000 -t public

# 3. Open in browser
open http://127.0.0.1:8000
```

---

## 📁 Repository Structure

```
├── public/                 # Web application & frontend assets
│   ├── api.php             # High-speed REST backend & Maildir synchronizer
│   ├── inbox.php           # Authenticated webmail interface & sound engine
│   ├── index.html          # Internationalized landing page
│   ├── news.html           # 24/7 International news wire
│   ├── forum.html          # Developer & designer community forum
│   ├── auth.html           # Sign In & Registration portal
│   ├── sounds/             # Happy & afraid raccoon WAV audio assets
│   ├── js/app.js           # Reactive single-page client application
│   ├── css/raccoon-mail.css # Responsive design system & themes
│   ├── sitemap.xml         # Multi-regional sitemap index
│   └── raccoon-logo.svg    # BIMI-compliant indicator SVG
├── src/                    # Core backend PHP Modules
│   ├── Database.php        # SQLite PDO connection pool (WAL mode)
│   ├── IMAPSync.php        # Inbound mail parser & fallback
│   ├── PostcardEngine.php  # Postal stationery generator
│   └── Security.php        # Rate limiting & cryptographic hashing
├── desktop/                # Native Windows Client (C# & WebView)
│   └── Program.cs          # Modern Chromium desktop launcher
└── scripts/                # Indexing, deployment & audio synthesis scripts
```

---

## 🛡️ Security Disclosures

Please report any security findings to `security@racconmail.eu` in accordance with our [SECURITY.md](SECURITY.md) policy.

---

## 📄 License

Raccon Mail is open-source software licensed under the [MIT License](LICENSE).
