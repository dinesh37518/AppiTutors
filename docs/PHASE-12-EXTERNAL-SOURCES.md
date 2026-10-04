# Phase 12 — QA / UAT Authoritative External Sources Register

**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 12 — QA & User Acceptance Testing (Final Implementation Phase)  
**Scope**: Local Windows implementation and QA verification only  

In strict accordance with Section 1 of the Phase 12 prompt, external sources are consulted ONLY when required for technical verification against official standards. No random tutorials, unofficial blogs, competitor platforms, or Stack Overflow posts were used.

---

## 1. W3C — Web Content Accessibility Guidelines (WCAG) 2.2

* **Official Specification**: [W3C WCAG 2.2 Recommendation (October 2023)](https://www.w3.org/TR/WCAG22/)
* **Relevant Success Criteria Verified in Phase 12**:
  * **SC 1.3.1 Info and Relationships (Level A)**: Semantic HTML structure, landmark elements (`<header>`, `<nav>`, `<main id="main-content">`, `<footer>`), hierarchical heading structure (single `<h1>` per page, logical `<h2>`/`<h3>` progression), table headers with `scope="col"`.
  * **SC 1.4.3 Contrast (Minimum) (Level AA)**: Minimum contrast ratio of 4.5:1 for standard text and 3:1 for large text across UI components and typography.
  * **SC 2.1.1 Keyboard (Level A)**: All interactive controls (buttons, links, form inputs, modal dialogs) navigable and operable via keyboard (`Tab`, `Shift+Tab`, `Enter`, `Space`, `Esc`).
  * **SC 2.4.1 Bypass Blocks (Level A)**: Prominent, focusable skip link (`<a href="#main-content" class="skip-link">Skip to main content</a>`) present as the first focusable element on every view.
  * **SC 2.4.7 Focus Visible (Level AA)**: Highly visible, standardized focus indicator (`outline: 3px solid #2563eb; outline-offset: 2px`) styled across interactive elements via `:focus-visible`.
  * **SC 3.3.1 Error Identification (Level A)**: Form validation errors clearly identified in text, programmatically associated with inputs via `aria-describedby` or error alert containers.
  * **SC 3.3.2 Labels or Instructions (Level A)**: Form controls explicitly associated with `<label for="...">` matching input `id` attributes.
  * **SC 4.1.2 Name, Role, Value (Level A)**: Mobile navigation toggle and interactive controls include explicit `aria-expanded`, `aria-controls`, and `aria-label` attributes.

---

## 2. OWASP — Web Security Testing Guide (WSTG v4.2)

* **Official Specification**: [OWASP Web Security Testing Guide v4.2](https://owasp.org/www-project-web-security-testing-guide/v42/)
* **Relevant Testing Methodologies Applied**:
  * **WSTG-ATHN-01/02 (Authentication Testing)**: Verification of authentication enforcement across all protected endpoints, rejection of missing, expired, forged, or malformed JWT Bearer tokens, and session fixation defense via session regeneration on login.
  * **WSTG-ATHZ-01/02 (Authorization & IDOR Testing)**: Fine-grained horizontal and vertical authorization verification; asserting that users cannot view, modify, or delete resources (profiles, bookings, availability, children, blog articles, audit logs) belonging to other tenants.
  * **WSTG-CONF-04/07 (Configuration & Error Handling)**: Web server hardening verification; blocking direct HTTP access to sensitive files (`.env`, `.git`, `.json`, `composer.lock`, private storage); ensuring database and PHP exceptions do not leak stack traces or credentials in HTTP responses.
  * **WSTG-INPV-01/02 (Input Validation & Injection)**: Verification of parameter allowlists, PDO prepared statements for SQL injection immunity, entity escaping via `View::e()` (`ENT_QUOTES | ENT_SUBSTITUTE`) for XSS defense, and strict JSON body parsing via `Request::getJsonBody()`.
  * **WSTG-CRYP-01/03 (Session & Secret Management)**: Verification of `HttpOnly`, `SameSite=Lax`, environmental `Secure` cookie flags, timing-safe string comparison (`hash_equals()`), and SHA-256 IP address hashing.

---

## 3. PHP Official Documentation (PHP 8.2 / 8.4)

* **Official Manual**: [PHP Official Documentation (php.net)](https://www.php.net/manual/en/)
* **Relevant Sections**:
  * **Session Management**: Session strict mode (`session.use_strict_mode`), secure cookie configuration (`session_set_cookie_params`), and session ID regeneration (`session_regenerate_id`).
  * **Data Objects (PDO)**: Prepared statements with bound parameters (`PDO::prepare()`, `PDOStatement::execute()`), transaction isolation and rollback (`beginTransaction()`, `commit()`, `rollBack()`), and error mode configuration (`PDO::ERRMODE_EXCEPTION`).
  * **Filter & String Functions**: `filter_var()` with `FILTER_VALIDATE_EMAIL`, multi-byte string functions (`mb_strlen()`, `mb_substr()`), and `random_bytes()` / `bin2hex()` for cryptographically secure pseudo-random token generation.
  * **JSON Handling**: `json_encode()` and `json_decode()` error inspection via `json_last_error()` and `json_last_error_msg()` (RFC 8259 compliance).

---

## 4. MySQL 8.4 Reference Manual

* **Official Documentation**: [MySQL 8.4 Reference Manual](https://dev.mysql.com/doc/refman/8.4/en/)
* **Relevant Sections**:
  * **Section 17.7.2.4 (Locking Reads)**: Implementation and verification of `SELECT ... FOR UPDATE` row-level exclusive locks within InnoDB transactions to guarantee concurrency protection and double-booking prevention.
  * **Section 17.2.1 (InnoDB Storage Engine)**: Foreign key constraint enforcement (`CASCADE`, `RESTRICT`), transaction atomicity (ACID compliance), and deadlock / lock-wait timeout handling (`SQLSTATE[HY000] [1205]`).
  * **Section 13.1.20 (CREATE TABLE / Constraints)**: Primary key constraints, unique key constraints (preventing duplicate email registrations, duplicate availability overlaps, and duplicate subscriber emails), and UTF-8 multi-byte character set (`utf8mb4_unicode_ci`).

---

## 5. Google Firebase Official Documentation

* **Official Documentation**: [Firebase Admin SDK for PHP](https://firebase.google.com/docs/auth/admin/verify-id-tokens)
* **Relevant Sections**:
  * **Verify ID Tokens**: Server-side cryptographic signature verification of RS256 JWT tokens using Google's public key sets.
  * **Trust Boundary Enforcement**: Client-side custom claims and browser attributes are strictly untrusted; MySQL application database remains the sole authoritative source for user roles, account lifecycle status, and business permissions.

---

## 6. Official Authority Hierarchy

In accordance with Section 1 of the Phase 12 prompt:
1. **Primary Authority**: Production Master Project Document v2.0 (UK Tutoring Platform).
2. **Secondary Authority**: Approved Phase 1–11 specifications, completion reports, and active codebase.
3. **External Standards**: Only the 5 official standards listed above for technical criteria validation.
4. **Open Client Decisions**: All 11 open decisions remain explicitly OPEN and are NOT resolved by external standards.
