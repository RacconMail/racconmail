# 🦝 Raccon Mail

> **Clever, Masked & Nocturnal Webmail Platform**  
> *Official Open Source Codebase for [racconmail.eu](https://racconmail.eu)*

[![Website](https://img.shields.io/badge/Live%20Webmail-racconmail.eu-208ed3?style=for-the-badge&logo=firefox)](https://racconmail.eu)
[![Community Wall](https://img.shields.io/badge/Community%20Forum-racconmail.eu%2Fforum-10b981?style=for-the-badge&logo=discourse)](https://racconmail.eu/forum)
[![News Wire](https://img.shields.io/badge/24%2F7%20News%20Wire-racconmail.eu%2Fnews-f59e0b?style=for-the-badge&logo=rss)](https://racconmail.eu/news)
[![Security Policy](https://img.shields.io/badge/Security-RFC%209116%20Compliant-emerald?style=for-the-badge&logo=shield)](SECURITY.md)
[![VirusTotal](https://img.shields.io/badge/VirusTotal-0%2F70%20Clean-brightgreen?style=for-the-badge&logo=virustotal)](https://www.virustotal.com/gui/domain/racconmail.eu)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)

---

## 📖 Overview

**Raccon Mail** is an independent, transparent, and open-source communication platform engineered for privacy-conscious users, night owls, and developers. Designed from the ground up to eliminate invasive surveillance, behavioral profiling, and tracking scripts, Raccon Mail provides a modern webmail experience, a developer community forum, and multi-source global news wire.

### 🛡️ Open Source & Trust Guarantees

- **100% Transparent Codebase**: Every single line of frontend JavaScript, PHP backend logic, and database queries is public and open for third-party security audits.
- **Zero Third-Party Telemetry**: Zero Google Analytics, Facebook Pixels, tracking beacons, or telemetry SDKs.
- **Client-Side Isolated Authentication**: Passwords and credentials are never transmitted unhashed or shared with external parties.
- **Clean Executable Builds**: Windows desktop and Android APK builds are generated from clean source code, signed, and scanned with 0/70 detections across VirusTotal vendors.
- **RFC 9116 Compliant**: Published security policy accessible at `https://racconmail.eu/.well-known/security.txt`.

---

## ✨ Key Features

- 💌 **Clever Postcards & Mail**: Clean, distraction-free composer with rich attachments, drafts, fast replies, and custom typography.
- 💬 **Developer & Designer Forum**: Built-in community wall at [racconmail.eu/forum](https://racconmail.eu/forum) with syntax code highlighting, tag filters, discussion threads, and like paws across Tech, Coding, Web Design, and Help.
- 📰 **24/7 Global News Wire**: Verified international news syndicated from major reputable publishers (**BBC News, Reuters, TechCrunch, The Verge, Bloomberg, ESPN, IGN, NASA**) at [racconmail.eu/news](https://racconmail.eu/news).
- 🎨 **Adaptive Themes**: Beautiful Light (Harmony), Dark (Charcoal), and Midnight Green themes tailored for night owls.
- 📱 **Multi-Platform Sync**: Native-feel Android APK and Windows Desktop installer with zero adware, system tray support, and offline caching.
- ⚡ **High-Performance Architecture**: SQLite WAL mode with busy timeout handling, strict parameterized PDO statements, and anti-spam bypass protection.

---

## 🔒 Binary Verification & Checksums

All official binary releases hosted on [GitHub Releases](https://github.com/RacconMail/racconmail/releases) and [racconmail.eu/downloads/](https://racconmail.eu/downloads/) are verifiable against the following SHA-256 cryptographic hashes:

| Release Artifact | Version | SHA-256 Hash | VirusTotal Status |
| :--- | :---: | :--- | :---: |
| `RacconMail-v1.0.0.apk` | v1.0.0 | `664e869fa2e90e2ac516ec0117a07ea23d2cf0088cb18b5d6c2c86754820e6bb` | **Clean (0/70)** |
| `RacconMail-Setup.exe` | v1.0.0 | `5b35a794f029d706945d3c82d46270823b67879ce56064ba477c4a7d2ca69eac` | **Clean (0/70)** |
| `RacconMail-Desktop-Windows.zip` | v1.0.0 | `a0787176034d6cc42abf837e39a5dff788a00240f1e03b48aa726c44b8b95eaa` | **Clean (0/70)** |

To verify any downloaded binary locally:
```bash
# Windows PowerShell
Get-FileHash ./RacconMail-Setup.exe -Algorithm SHA256

# Linux / macOS
sha256sum ./RacconMail-v1.0.0.apk
```

---

## 🌐 Live Ecosystem

- **Main Webmail**: [https://racconmail.eu/](https://racconmail.eu/)
- **Community Forum**: [https://racconmail.eu/forum](https://racconmail.eu/forum)
- **News Wire**: [https://racconmail.eu/news](https://racconmail.eu/news)
- **Security Policy**: [https://racconmail.eu/.well-known/security.txt](https://racconmail.eu/.well-known/security.txt)
- **Legal Terms**: [https://racconmail.eu/legal/terms](https://racconmail.eu/legal/terms)
- **Privacy Policy**: [https://racconmail.eu/legal/privacy](https://racconmail.eu/legal/privacy)

---

## 🚀 Getting Started Locally

### Prerequisites

- PHP 8.1+ with `pdo_sqlite`, `imap`, and `mbstring` extensions
- Web server (Apache with `mod_rewrite` or Nginx)
- Node.js 18+ (for build utilities and local testing)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/RacconMail/racconmail.git
   cd racconmail
   ```

2. Start a local PHP development server:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

3. Open your browser at `http://127.0.0.1:8000`.

---

## 📁 Repository Structure

```
├── public/                 # Web application & frontend assets
│   ├── api.php             # Unified JSON REST backend
│   ├── inbox.php           # Authenticated webmail interface
│   ├── index.html          # Privacy-first landing page
│   ├── news.html           # 24/7 International news wire
│   ├── forum.html          # Developer & designer community forum
│   ├── auth.html           # Sign In & Registration portal
│   ├── js/app.js           # Reactive single-page client application
│   ├── css/raccoon-mail.css # Responsive design system & themes
│   └── .well-known/        # RFC 9116 security.txt
├── src/                    # Core backend PHP Modules
│   ├── Database.php        # SQLite PDO connection pool (WAL mode)
│   ├── IMAPSync.php        # Inbound c-client mail poller
│   ├── PostcardEngine.php  # Postal stationery & card generator
│   └── Security.php        # Rate limiting & input sanitization
├── docs/                   # Architectural & Audit Specifications
│   ├── ARCHITECTURE.md     # System design & concurrency guarantees
│   └── SECURITY.md         # Vulnerability reporting guidelines
└── scripts/                # Verification, build & deployment automation
```

---

## 🛡️ Responsible Disclosure

If you discover a potential security vulnerability, please refer to our [SECURITY.md](SECURITY.md) or contact our security desk directly at `security@racconmail.eu`.

---

## 📄 License

Raccon Mail is open source software released under the [MIT License](LICENSE).
