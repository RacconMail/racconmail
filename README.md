# 🦝 Raccon Mail

> **Clever, Masked & Nocturnal Webmail Platform**  
> *Official Open Source Codebase for [racconmail.eu](https://racconmail.eu)*

[![Website](https://img.shields.io/badge/Live%20Webmail-racconmail.eu-ff6b35?style=for-the-badge&logo=firefox)](https://racconmail.eu)
[![Community Wall](https://img.shields.io/badge/Community%20Forum-racconmail.eu%2Fforum-10b981?style=for-the-badge&logo=discourse)](https://racconmail.eu/forum)
[![Android APK](https://img.shields.io/badge/Android-APK%20Ready-green?style=for-the-badge&logo=android)](https://racconmail.eu/downloads/RacconMail.apk)
[![Windows App](https://img.shields.io/badge/Windows-Setup.exe-0078d7?style=for-the-badge&logo=windows)](https://racconmail.eu/downloads/RacconMail-Setup.exe)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)

---

## 🌟 Overview

Raccon Mail is a modern, lightweight, privacy-focused email and postcard communication platform. Built with native responsive design, smooth theme transitions, live search indexing, offline resilience, and an integrated developer community wall.

### ✨ Key Features

- 💌 **Clever Postcards & Mail**: Clean, distraction-free composer with rich attachments, drafts, fast replies, and custom typography.
- 💬 **Developer & Designer Wall**: Built-in modern community forum at [racconmail.eu/forum](https://racconmail.eu/forum) with syntax code highlighting, tag filters, discussion threads, and paws 🐾 across Tech, Coding, Web Design, Sharing, Help, and Explaining.
- 🗞️ **News Wire with Pictures**: Curated international news syndicated across Tech, Sport, Games, Music, and Weather at [racconmail.eu/news](https://racconmail.eu/news).
- 🌙 **Adaptive Themes**: Beautiful Light (Harmony), Dark (Charcoal), and Midnight Green themes tailored for night owls.
- 📱 **Native Android & Windows Apps**: Integrated responsive UI with bottom navigation bar, safe-area support, signed APK, and standalone Windows desktop client.
- ⚡ **Lightning Fast Backend**: Optimized PHP backend with WAL-mode SQLite database, IMAP sync, and anti-spam bypass protection.
- 🛡️ **Privacy by Design**: End-to-end encrypted postcard headers, zero tracking scripts, sealed envelopes.

---

## 🌐 Live Webmail & Ecosystem

- **Main Webmail**: [https://racconmail.eu/](https://racconmail.eu/)
- **Community Forum & Wall**: [https://racconmail.eu/forum](https://racconmail.eu/forum)
- **News Wire**: [https://racconmail.eu/news](https://racconmail.eu/news)
- **Android APK**: [Download APK](https://racconmail.eu/downloads/RacconMail.apk)
- **Windows Client**: [Download Windows Setup](https://racconmail.eu/downloads/RacconMail-Setup.exe)

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.1+ with `pdo_sqlite`, `imap`, and `mbstring` extensions
- Apache / Nginx with `mod_rewrite` enabled
- Java 17+ (optional, for signing custom Android APK builds)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/RacconMail/racconmail.git
   cd racconmail
   ```

2. Configure your web server document root to point to the `public/` directory.

3. Verify write permissions on database directory for SQLite.

4. Open `https://racconmail.eu` or your local domain in the browser.

---

## 📱 Mobile App (Android APK)

The official Raccon Mail Android application connects seamlessly to your mailbox with full offline caching and native bottom bar navigation.

Download the latest cryptographically signed release from GitHub Releases (avoids unknown-publisher warnings):
👉 **[Download RacconMail-v1.0.0.apk (GitHub Releases)](https://github.com/RacconMail/racconmail/releases/latest)**

---

## 🏗️ Repository Structure

```
├── public/                 # Web application & frontend assets
│   ├── api.php             # Unified JSON REST backend
│   ├── inbox.php           # Reactive authenticated webmail interface
│   ├── index.html          # Privacy-first landing page
│   ├── news.html           # 24/7 International news wire
│   ├── js/app.js           # Single-page client reactive application
│   └── css/raccoon-mail.css # Responsive design system & theme variables
├── src/                    # Core PHP Modules
│   ├── Database.php        # SQLite PDO connection pool (WAL mode & busy timeout)
│   ├── IMAPSync.php        # Inbound c-client mail poller with anti-spam bypass
│   ├── PostcardEngine.php  # Postal stationery & digital card generator
│   ├── StoryEngine.php     # 175+ World folktales reader integration
│   └── Security.php        # Phone verification & zero-knowledge sanitization
├── config/                 # Server environment configurations
│   └── config.sample.php   # Configuration template
├── docs/                   # Architectural & Audit Specifications
│   ├── ARCHITECTURE.md     # System design & concurrency guarantees
│   └── SECURITY.md         # Vulnerability reporting & zero-telemetry policy
└── scripts/                # Packaging & build automation
    ├── build_apk.js        # Android APK signer & alignment utility
    └── sync_stories.js     # Folklore database synchronizer
```

---

## 🤝 Direct Support & Community

For technical assistance, security disclosures, or feature requests:
- **Email Support**: `support@racconmail.eu`
- **Issue Tracker**: [GitHub Issues](https://github.com/RacconMail/racconmail/issues)

---

## 📄 License

Raccon Mail is open source software licensed under the [MIT License](LICENSE).
