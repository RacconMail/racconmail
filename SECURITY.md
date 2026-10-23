# Security Policy

## 🦝 Raccon Mail Security & Transparency Commitment

We take security and privacy seriously. Raccon Mail was engineered specifically to protect users from behavioral profiling, unconsented telemetry, and credential interception.

---

## 🛡️ Core Security Guarantees

1. **Zero Third-Party Telemetry**:
   - Zero Google Analytics, Facebook Pixels, Mixpanel, or advertising beacons.
   - All assets (fonts, icons, stylesheets) are self-hosted or served from audited CDNs without tracking headers.

2. **Client-Side Isolated Authentication**:
   - Authentication hashes are calculated client-side before submission.
   - We never request third-party passwords (e.g. Google or Microsoft account credentials).

3. **Database Scoping & Prepared Statements**:
   - Every user's mail store is strictly scoped by parameterized PDO queries (`user_id = ?`).
   - SQLite WAL mode prevents read/write starvation and race conditions.

4. **Inbound Mail Filtering & Anti-Spam Safeguards**:
   - Known senders and member-to-member communications bypass arbitrary spam filtering to prevent false positive message drops.
   - External headers and MIME attachments are sanitized and validated against safe extension lists.

5. **Binary Integrity & Code Signing**:
   - All binary releases for Android and Windows have verifiable SHA-256 hashes published in `README.md`.
   - Continuous scanning ensures 0/70 detections across VirusTotal security vendors.

---

## 🔍 Reporting a Vulnerability

If you discover a security vulnerability within Raccon Mail, we appreciate your help in disclosing it to us responsibly:

1. **Email Disclosure**: Send details to `security@racconmail.eu` or `admin@racconmail.eu`.
2. **RFC 9116 Policy**: Read our live policy at [https://racconmail.eu/.well-known/security.txt](https://racconmail.eu/.well-known/security.txt).
3. **Response Time**: Our team reviews disclosures within 24 hours.

Please do not open public GitHub issues for undisclosed security vulnerabilities.
