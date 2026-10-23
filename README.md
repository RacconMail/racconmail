# 🦝 Raccon Mail

> **Clever, Masked & Nocturnal Webmail Platform**  
> *Official Open Source Codebase for [racconmail.eu](https://racconmail.eu)*

[![Website](https://img.shields.io/badge/Live-racconmail.eu-ff6b35?style=flat-square&logo=firefox)](https://racconmail.eu)
[![Android APK](https://img.shields.io/badge/Android-APK%20Ready-green?style=flat-square&logo=android)](https://racconmail.eu/downloads/RacconMail.apk)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

---

## 🌟 Overview

Raccon Mail is a modern, lightweight, privacy-focused email and postcard communication platform. Built with native responsive design, smooth theme transitions, live search indexing, offline resilience, and integrated cultural folk stories.

### ✨ Key Features

- 💌 **Clever Postcards & Mail**: Clean, distraction-free composer with rich attachments, drafts, and custom typography.
- 🌙 **Adaptive Themes**: Beautiful Light (Harmony), Dark (Charcoal), and Midnight Green themes tailored for night owls.
- 📚 **World Stories Integration**: 175+ traditional folktales from `worldstories.org.uk` directly accessible inside the webmail client.
- 📱 **Native Android Experience**: Integrated responsive UI with bottom navigation bar, safe-area support, and lightweight signed APK.
- ⚡ **Lightning Fast Backend**: Optimized PHP backend with WAL-mode SQLite database, IMAP sync, and anti-spam bypass protection.
- 🛡️ **Privacy by Design**: End-to-end encrypted postcard headers, no tracking scripts, zero surveillance.

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

Download the latest signed release:
👉 [Download RacconMail.apk](https://racconmail.eu/downloads/RacconMail.apk)

---

## 🤝 Direct Support & Community

For technical assistance, security disclosures, or feature requests:
- **Email Support**: `support@racconmail.eu`
- **Issue Tracker**: [GitHub Issues](https://github.com/RacconMail/racconmail/issues)

---

## 📄 License

Raccon Mail is open source software licensed under the [MIT License](LICENSE).
