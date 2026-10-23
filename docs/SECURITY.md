# Security & Vulnerability Disclosure Policy

## 🦝 Raccon Mail Security Commitment

We take security and privacy seriously. Raccon Mail was engineered specifically to protect users from behavioral profiling, unconsented telemetry, and intrusive inbox scanning.

---

## 🛡️ Core Security Architecture

1. **Zero Third-Party Telemetry**:
   - Zero Google Analytics, Facebook Pixels, Mixpanel, or advertising beacons.
   - All assets (fonts, icons, stylesheets) are self-hosted or served from audited CDNs without tracking headers.

2. **Database Isolation**:
   - Every user's mail store is strictly scoped by parameterized PDO queries (`user_id = ?`).
   - SQLite WAL mode prevents read/write starvation and race conditions.

3. **Inbound Mail Filtering & Anti-Spam Bypass**:
   - Known senders and member-to-member communications bypass arbitrary spam filtering to prevent false positive message drops.
   - External headers and MIME attachments are sanitized and validated against allowed extension lists.

4. **Android APK Signing**:
   - APK releases are signed with RSA-384 keys and dual Scheme v2 + v3 validation.
   - Checksums and release hashes are published directly to GitHub Releases.

---

## 📬 Reporting Vulnerabilities

If you identify a security issue, vulnerability, or potential exploit in Raccon Mail:

1. **Email our Security Team**:
   - Contact: `support@racconmail.eu`
   - Subject: `[SECURITY DISCLOSURE] Brief summary`
2. **Include**:
   - Description of the vulnerability.
   - Proof-of-concept steps or payload.
   - Impact assessment on user privacy or data integrity.
3. **Response SLA**:
   - Initial acknowledgement: within 24 hours.
   - Status update & remediation plan: within 72 hours.
   - Responsible public disclosure after patch deployment.
