# Raccon Mail System Architecture & Privacy Design

## 1. Architectural Philosophy

Raccon Mail is architected as a lightweight, zero-surveillance webmail platform designed for desktop and mobile clients. The platform adheres to three core tenets:

1. **Zero External Tracking**: No third-party analytics, tracking pixels, or cross-origin surveillance scripts.
2. **Local Encrypted Storage**: Mail messages and metadata are isolated per user in an optimized SQLite database using Write-Ahead Logging (WAL) and `busy_timeout = 15000` to prevent database locking concurrency conflicts.
3. **Hybrid Postcard Model**: Combines standard RFC 5322 email with custom-styled postal stationery and embedded world folklore literature.

---

## 2. Component Hierarchy

```
├── public/                     # Public webroot
│   ├── api.php                 # Core REST API endpoint router
│   ├── inbox.php               # Authenticated reactive webmail client
│   ├── index.html              # Landing presentation page
│   ├── news.html               # 24/7 Global news wire (CBN News)
│   ├── js/app.js               # Front-end reactive state manager
│   ├── css/raccoon-mail.css    # Custom design system tokens & themes
│   └── worldstories_catalog.json # 175+ curated folk tales database
├── src/                        # Modular PHP Core Backend
│   ├── Database.php            # SQLite PDO connection pool & migrations
│   ├── IMAPSync.php            # Inbound c-client mail poller & anti-spam bypass
│   ├── PostcardEngine.php      # Physical stationery & digital card generator
│   ├── StoryEngine.php         # WorldStories UK folk tale integration
│   └── Security.php            # Phone verification, CSRF & authentication
├── scripts/                    # Build & Utility Automation
│   ├── build_apk.js            # Android APK asset unpacker, aligner & signer
│   └── sync_stories.js         # Scrapes & structures worldstories.org.uk
└── docs/                       # Technical & Architectural Documentation
    ├── ARCHITECTURE.md
    └── SECURITY.md
```

---

## 3. Database Layer & Concurrency

Raccon Mail leverages SQLite 3 configured with high-performance concurrent PRAGMA directives:

```sql
PRAGMA journal_mode = WAL;
PRAGMA busy_timeout = 15000;
PRAGMA synchronous = NORMAL;
PRAGMA foreign_keys = ON;
```

This guarantees lock contention is eliminated under multi-user access while maintaining ACID transactional guarantees.

---

## 4. Android APK Integration (SmartWebView)

The Android package (`RacconMail-v1.0.0.apk`) wraps the reactive web application with:
- Native hardware acceleration (`RenderThread`).
- Full safe-area inset adaptation for edge-to-edge displays.
- Dual APK Signature Scheme v2 & v3 verification.
- Offline DOM caching for uninterrupted reading of World Stories.
