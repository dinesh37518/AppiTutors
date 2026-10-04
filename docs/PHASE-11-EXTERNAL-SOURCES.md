# Phase 11 — Authoritative External Sources Register

This register records all official and authoritative documentation consulted during the execution of **Phase 11: Security & Privacy Hardening**, in strict compliance with Section 2 of the Phase 11 Specification. No unverified tutorials, forum posts, or third-party implementations were consulted.

---

## 1. OWASP (Open Web Application Security Project) Official Documentation

* **OWASP Top 10:2021**
  * Reference: [OWASP Top 10 Web Application Security Risks](https://owasp.org/Top10/)
  * Standards Applied:
    * A01:2021 — Broken Access Control (IDOR / BOLA authorization matrix, server-side ownership verification, least-privilege role boundaries).
    * A02:2021 — Cryptographic Failures (timing-safe string comparison using `hash_equals()`, token hashing via SHA-256, cryptographically secure PRNG via `random_bytes()`).
    * A03:2021 — Injection (PDO prepared statements, strict parameter binding, allowlisting for SQL order columns and directions).
    * A04:2021 — Insecure Design (safeguarding gate `ACTIVE + APPROVED + DBS VERIFIED`, rate-limiting sensitive boundaries).
    * A05:2021 — Security Misconfiguration (hardened HTTP response headers, restrictive CSP without `unsafe-eval`, strict session cookie flags).
    * A07:2021 — Identification and Authentication Failures (centralized session fixation prevention via `session_regenerate_id()`, 30-minute inactivity timeout).
    * A09:2021 — Security Logging and Monitoring Failures (audit logging of all security events, PII and token redaction from log streams).
* **OWASP REST Security Cheat Sheet**
  * Standards Applied: HTTP method enforcement (405 Method Not Allowed), strict Content-Type validation (`application/json`), consistent JSON error schema, error information minimization (no stack traces or internal SQL queries).
* **OWASP Cross-Site Scripting (XSS) Prevention Cheat Sheet**
  * Standards Applied: Context-aware HTML entity encoding using `htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` in `View::e()` and sanitization in `Validator::sanitizeString()`.
* **OWASP File Upload Cheat Sheet**
  * Standards Applied: Strict extension allowlisting (`pdf`, `png`, `jpg`, `jpeg`), MIME verification, randomized storage filenames (`uniqid('dbs_', true)`), storage outside public web root (`storage/private/dbs/`), executable script content detection.

---

## 2. PHP Official Documentation

* **PHP 8.2+ Session Security Manual**
  * Reference: [PHP: Session Security - Manual](https://www.php.net/manual/en/features.session.security.management.php)
  * Standards Applied:
    * `session.use_strict_mode = 1`: Reject uninitialized session IDs provided by browser.
    * `session.use_only_cookies = 1`: Prevent session ID transmission via URL query parameters.
    * `session.cookie_httponly = 1`: Block client-side JavaScript access to session cookie.
    * `session.cookie_samesite = 'Lax'`: Mitigate cross-site request forgery risks while preserving top-level navigation.
    * `session.cookie_secure = true`: Enforced conditionally when running over HTTPS.
    * Inactivity timeout tracking via `$_SESSION['_last_activity']` defaulting to 1800 seconds (30 minutes).
    * Session fixation mitigation using `session_regenerate_id(true)` upon privilege changes and timeouts.
* **PHP Official PDO Documentation**
  * Reference: [PHP: PDO - Manual](https://www.php.net/manual/en/book.pdo.php)
  * Standards Applied: PDO prepared statements, bound parameter execution, `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_EMULATE_PREPARES => false`.

---

## 3. MySQL 8.4 Official Documentation

* **MySQL 8.4 Reference Manual: Locking and Concurrency**
  * Reference: [MySQL 8.4 Reference Manual: Locking Reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)
  * Standards Applied:
    * InnoDB row-level locking (`SELECT ... FOR UPDATE`) inside database transactions to physically prevent race conditions, concurrent slot reservations, and double bookings.
    * Explicit transaction boundaries (`beginTransaction()`, `commit()`, `rollBack()`).

---

## 4. Firebase Official Documentation

* **Firebase Admin Authentication & Token Verification**
  * Reference: [Firebase Admin SDK: Verify ID Tokens](https://firebase.google.com/docs/auth/admin/verify-id-tokens)
  * Standards Applied:
    * Server-side cryptographic verification of Firebase JWT ID tokens via Firebase Admin SDK.
    * Architecture boundary: Firebase UID mapped strictly to MySQL database user record.
    * Client-side claims and browser flags treated as completely untrusted; MySQL remains the sole authority for roles, status, and permissions.

---

## 5. UK Information Commissioner's Office (ICO) Official Guidance

* **UK ICO: UK GDPR and Data Protection Engineering Considerations**
  * Reference: [UK ICO: Guide to Data Protection](https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/)
  * Technical Engineering Capabilities Implemented:
    * **Subject Access Request Hook**: Machine-readable data export endpoint (`/api/privacy.php?action=export`) returning portable JSON containing user account, profile, child records, booking histories, and newsletter preferences.
    * **Data Minimization & Erasure Hook**: Technical anonymization hook (`PrivacyService::prepareAccountErasure()`) scrubbing personal fields (email, display name, phone, address, tutor bio) and deactivating dependent child records while operational booking records remain structurally intact solely for relational database integrity pending client/legal retention policy confirmation. Active/upcoming bookings defer erasure pending operational/cancellation policy confirmation.
    * **Consent & Suppression Tracking**: Tracking explicit consent timestamps (`consent_at`), suppression status (`SUPPRESSED`) to prevent accidental re-solicitation, and automated neutral handling of the open double opt-in decision.
  * *Disclaimer*: Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements. This consultation was for technical architectural reference only and does not constitute legal certification or compliance approval.
