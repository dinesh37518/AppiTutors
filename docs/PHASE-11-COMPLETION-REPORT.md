# Phase 11 — Security & Privacy Hardening Completion Report

## 1. Phase 11 Status
- **Status**: COMPLETE & FULLY VERIFIED (CORRECTION-ONLY PASS APPLIED)
- **Phase Boundary**: STRICTLY ENFORCED (Phase 12 QA/UAT, Phase 13 Deployment, and Phase 14 Handover tasks have NOT been started).
- **Test Results**:
  - Phase 11 Security & Privacy Suite: **78/78 PASSED (100%)**
  - Full Cumulative Regression Suite (Phases 3–11): **424/424 PASSED (100%)**
- **Approved Compliance Phrasing**:
  > *"Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements."*
  No false claims of cryptographic immutability or formal legal compliance certifications are made.

---

## 2. Exact Files Created
1. `src/Support/Request.php` — Centralized HTTP request helper providing strict JSON decoding validation (`json_last_error()` check throwing HTTP 400 `MALFORMED_JSON`), Bearer token extraction, and IP resolution.
2. `src/Services/PrivacyService.php` — Core privacy engineering service implementing Subject Access Request (DSAR) JSON exports and technical account anonymization/erasure hooks.
3. `api/privacy.php` — Secure REST endpoint for privacy operations (`GET action=export`, `POST action=erase`, `POST action=consent`).
4. `docs/PHASE-11-EXTERNAL-SOURCES.md` — Register of official authoritative external sources consulted (OWASP, PHP, MySQL 8.4, Firebase, UK ICO).
5. `docs/PHASE-11-SECURITY-PRIVACY.md` — Comprehensive security architecture, threat model, control matrix, and rate limiting specifications.
6. `docs/PHASE-11-COMPLETION-REPORT.md` — This official Phase 11 completion and verification report.
7. `tests/Phase11SecurityPrivacyTest.php` — 78-test automated security and privacy verification test suite.

---

## 3. Exact Files Modified
1. `src/Services/DbsService.php` — Restricted raw sensitive DBS document retrieval strictly to authorized `MANAGER` only (`getDbsDocument`). Tutors are strictly prevented from retrieving raw DBS document files.
2. `api/dbs/document.php` — Enforced manager-only access control, supported both `tutor_id` and `tutor_user_id` query parameters, and integrated structured 401/403/404 exception handling.
3. `src/Support/SecurityHeaders.php` — Hardened CSP directives (`object-src 'none'`, no `unsafe-eval`), documented `style-src` inline compatibility exception, and refined conditional HSTS header emission.
4. `src/Support/Session.php` — Centralized session security defaults: `session.use_strict_mode = 1`, `session.cookie_httponly = 1`, `session.cookie_samesite = 'Lax'`, `session.cookie_secure` enabled for HTTPS/production and documented as a deliberate limitation on local plain HTTP, 30-minute inactivity timeout, and fixation regeneration.
5. `src/Support/RateLimiter.php` — Added `RateLimitExceededException` (HTTP 429) and `enforce()` method with sliding-window `Retry-After` calculation.
6. `src/Logging/Logger.php` — Broadened sensitive keys redaction list (`dbs_certificate`, `dbs_certificate_number`, `auth_token`, `bearer`, `session_id`, `client_secret`, `secret_key`).
7. `src/Services/AuditService.php` — Enhanced payload sanitization to automatically strip sensitive authentication tokens and DBS certificate numbers before database persistence.
8. `public/.htaccess` — Hardened web server rewrite rules blocking direct HTTP access to hidden files (`.env`, `.git`), lockfiles, markdown, composer files, and PHP templates.
9. `api/auth.php` — Integrated rate limiting (60/min) and centralized JSON body parsing.
10. `api/register/tutor.php` — Integrated rate limiting (30/min), strict JSON decoding, and explicit manager self-registration prevention.
11. `api/register/student.php` — Integrated rate limiting (30/min), strict JSON decoding, and manager self-registration block.
12. `api/bookings.php` — Integrated rate limiting (60/min), strict JSON decoding, and reinforced 7-state booking validation.
13. `api/newsletter/subscribe.php` — Integrated rate limiting (30/min) and strict JSON decoding.
14. `api/newsletter/unsubscribe.php` — Integrated rate limiting (60/min) and strict JSON decoding.
15. `api/tutors.php` — Integrated strict JSON decoding and bookability status checks.
16. `api/student/profile.php` — Integrated strict JSON decoding and IDOR ownership enforcement.
17. `api/parent/children.php` — Integrated strict JSON decoding and parent ownership verification.
18. `api/blog.php` — Integrated strict JSON decoding and role-based post publication boundaries.
19. `api/availability.php` — Integrated strict JSON decoding and slot ownership validation.
20. `api/manager/tutors.php` — Integrated strict JSON decoding and manager-only authorization checks.
21. `api/manager/subscribers.php` — Integrated strict JSON decoding and double opt-in protection gate.
22. `api/manager/dbs.php` — Integrated strict JSON decoding and manager-only verification queue access.
23. `api/manager/blog.php` — Integrated strict JSON decoding and manager editorial authorization.
24. `public/contact.php` — Integrated rate limiting (20/min) on public contact form submissions.
25. `public/newsletter.php` — Integrated rate limiting (30/min) on public subscription web form.
26. `public/unsubscribe.php` — Integrated rate limiting (60/min) on public unsubscribe web form.

---

## 4. Database Migration Status
- Total Migrations Applied: **6 Migration Batches (100% Tracked, 0 Pending)**
  - `001_create_users_table.sql`
  - `002_create_profiles_table.sql`
  - `003_create_bookings_availability_tables.sql`
  - `004_create_audit_logs_table.sql`
  - `005_create_blog_tables.sql`
  - `006_create_newsletter_rate_limits_tables.sql`
- All 11 domain entities + `migrations` + `rate_limits` tables verified.
- Engine: 100% InnoDB storage engine with physical row-level locking support (`SELECT ... FOR UPDATE`).
- Session timezone: UTC (`+00:00`) strictly enforced upon PDO connection.

---

## 5. Security Findings Table

| ID | Area | Finding | Severity | Evidence | Action | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **SEC-11-01** | Content-Security-Policy | Inconsistent CSP emitted on API routes | Medium | Headers missing on direct API endpoints | Centralized in `SecurityHeaders::apply()` across all routes | **FIXED** |
| **SEC-11-02** | Input Validation | Raw `json_decode(file_get_contents('php://input'))` allowed unvalidated JSON syntax | Medium | `json_last_error()` was uninspected in several API endpoints | Created `Request::getJsonBody()` throwing HTTP 400 `MALFORMED_JSON` | **FIXED** |
| **SEC-11-03** | Logging | DBS certificate numbers could appear in audit payloads | High | Audit payload contained raw certificate number | Enhanced `Logger` and `AuditService` with automatic masking | **FIXED** |
| **SEC-11-04** | Session Management | Session cookie settings relied on default php.ini | High | Default php.ini lacked explicit `SameSite=Lax` and `Strict` mode | Centralized in `Session::configure()` and `Session::start()` | **FIXED** |
| **SEC-11-05** | Rate Limiting | Rate limiting was absent on registration and booking APIs | Medium | Automated scripts could flood registrations or booking slots | Implemented DB-backed `RateLimiter` and wired into 9 sensitive endpoints | **FIXED** |
| **SEC-11-06** | Web Server Config | Direct HTTP access to hidden files and lockfiles permitted if mod_rewrite bypassed | High | `curl http://localhost/composer.json` returned 200 | Hardened `public/.htaccess` with regex denying sensitive extensions | **FIXED** |
| **SEC-11-07** | Privacy Engineering | Lack of automated DSAR export and account anonymization hook | Medium | Manual database querying needed for subject access requests | Created `PrivacyService` and `/api/privacy.php` endpoint | **FIXED** |
| **SEC-11-08** | DBS Confidentiality | Owning tutor could previously retrieve raw sensitive DBS document | High | `DbsService::getDbsDocument` allowed owning tutor access | Restricted raw DBS document retrieval strictly to **MANAGER ONLY** | **FIXED** |
| **SEC-11-09** | CSP Inline Styles | `style-src` requires `'unsafe-inline'` for view templates | Low | View templates use inline style attributes | Documented application compatibility exception; `script-src` strictly maintained without unsafe inline/eval | **ACCEPTED EXCEPTION** |
| **SEC-11-10** | HSTS Header | HSTS cannot be served over plain HTTP in local development | Low | Browsers ignore or warn on HSTS over HTTP | Implemented conditional emission: enabled on HTTPS/production, omitted on local HTTP | **ACCEPTED LIMITATION** |
| **SEC-11-11** | Double Opt-In Policy | Business policy for double opt-in confirmation email remains undecided | Low | Client decision open in Master Project Document v2.0 | Enforced neutral PENDING state; manager bypass blocked with 422 | **OPEN CLIENT DECISION** |
| **SEC-11-12** | DBS Retention Policy | Retention duration for DBS documentation remains undecided | Low | Client decision open in Master Project Document v2.0 | Files stored in private storage; retention policy deferred to client approval | **OPEN CLIENT DECISION** |

---

## 6. Security Fixes
1. **DBS Confidentiality Restriction**: In strict accordance with Master Project Document v2.0 and Phase 5 architecture, updated `DbsService::getDbsDocument()` and `/api/dbs/document.php` so that raw sensitive DBS certificate files are accessible strictly by authorized **MANAGER ONLY**. Owning tutors, other tutors, students/parents, and public users are strictly blocked (HTTP 403/401). Tutors may view their non-sensitive DBS status/badge only.
2. **JSON Payload Hardening**: Centralized request body decoding in `Request::getJsonBody()`, strictly rejecting malformed JSON syntax with HTTP 400 across 14 API endpoints.
3. **Session Cookie Defenses**: Applied `session.use_strict_mode = 1`, `session.cookie_httponly = 1`, `session.cookie_samesite = 'Lax'`, enabled `session.cookie_secure` for HTTPS/production while documenting the deliberate local HTTP development limitation, and configured 30-minute inactivity timeout.
4. **Web Server Hardening**: Updated `public/.htaccess` to block direct HTTP access to `.env`, `.git`, `.log`, `.lock`, `.json`, `composer.json`, and private storage directories.
5. **Log Sanitization**: Configured automatic masking for sensitive parameters (`password`, `token`, `dbs_certificate_number`) in both `Logger` and `AuditService`.
6. **Abuse Mitigation**: Deployed database-backed sliding-window rate limiter across authentication, registration, bookings, newsletter, and contact endpoints.

---

## 7. Privacy Engineering Controls
1. **Subject Access Request Hook**: Implemented `PrivacyService::exportUserData()` providing portable JSON export of account profile, children, bookings, and newsletter records.
2. **Data Minimization & Erasure Hook**: Implemented `PrivacyService::prepareAccountErasure()`. Active/upcoming bookings defer execution (`DEFERRED_PENDING_POLICY_REVIEW`) pending operational and cancellation policy confirmation. When eligible, personal non-operational fields (email, display name, phone, address, tutor bio) are scrubbed and anonymized, and child records deactivated. Operational booking records remain structurally intact solely for relational database integrity pending client/legal retention and deletion policy confirmation. The system does not claim statutory or safeguarding authorization to permanently retain records, does not hard-code fixed retention periods, and maintains DBS document retention as an open client decision (`DISC-020`).
3. **PECR Suppression**: Subscribers marked as `SUPPRESSED` cannot be resubscribed to receive marketing communications, preventing accidental re-solicitation.
4. **Double Opt-In Neutrality**: Subscribers remain in neutral `PENDING` status; manager activation bypass is strictly prohibited (`DOUBLE_OPT_IN_OPEN_DECISION`).

---

## 8. Authentication Review
- Missing Authorization Header → HTTP 401 (`InvalidTokenException`).
- Malformed / Non-Bearer Header → HTTP 401.
- Invalid or Expired Firebase Token → HTTP 401.
- Valid Firebase Token without MySQL Mapping → HTTP 404 (`UserNotRegisteredException`).
- Inactive User Account → HTTP 403 (`AccountInactiveException`).
- Role / Authority Source: MySQL database is the sole application authority; client-supplied claims and flags are strictly discarded.

---

## 9. Authorization Review (IDOR / BOLA / RBAC)
- Fine-grained ownership checks enforced on all domain resources:
  - Student A cannot view or update Student B's profile or child records.
  - Tutor A cannot view or modify Tutor B's availability slots, profile, or bookings.
  - Raw DBS documents can only be accessed by authorized managers; tutors and students cannot access them.
  - Tutors and Students cannot access manager dashboards, administrative reports, or DBS review queues.
  - Non-managers cannot publish or edit blog articles authored by other users.
- Server-side role authority strictly overrides all client-side inputs.

---

## 10. CSP and Security Headers Review
- `Content-Security-Policy`:
  `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none';`
  - *Compatibility Note*: `style-src 'unsafe-inline'` is an application compatibility exception required by inline styles in server-rendered PHP templates.
  - *Script Hardening*: `script-src` contains strictly NO `unsafe-inline` and NO `unsafe-eval`.
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()`
- `Strict-Transport-Security`: Omitted in local plain HTTP development; conditionally emitted (`max-age=31536000; includeSubDomains`) when HTTPS is detected or in production.

---

## 11. CSRF, XSS, and SQL Injection Review
- **Anti-CSRF**: Synchronizer token pattern with timing-safe `hash_equals()` verification applied to all cookie/session state-changing actions. Bearer-token APIs remain decoupled from session cookies.
- **XSS Prevention**: Centralized HTML entity escaping via `View::e()` using `ENT_QUOTES | ENT_SUBSTITUTE` with UTF-8 character encoding; server-side sanitization via `Validator::sanitizeString()`.
- **SQL Injection Prevention**: 100% PDO prepared statements with parameter binding; dynamic sort columns and directions restricted to strict allowlists; pagination validated as positive integers.

---

## 12. Upload & DBS Security Review
- **Private Storage Location**: `storage/private/dbs/` located completely outside Apache public web root.
- **File Validation**: Maximum file size 5MB; extension allowlist (`PDF`, `PNG`, `JPG`, `JPEG`); body inspection blocking embedded executable PHP scripts.
- **Access Boundary**: Raw, sensitive DBS document retrieval is restricted to authorized **MANAGER ONLY**. Owning tutors, different tutors, students/parents, and public users are strictly blocked (HTTP 403/401). Tutors may view their non-sensitive DBS status/badge only.
- **Retention Policy**: Open client decision preserved; no unapproved automated purge rules introduced.

---

## 13. Secret Handling Review
- Zero credentials or API keys hardcoded in version-controlled files.
- `.env` and `storage/credentials/service-account.json` verified in `.gitignore`.
- Direct web access to `.env` returns HTTP 403 Forbidden.
- Sensitive environment variables are never displayed on error pages.

---

## 14. Rate Limiting Review
- Mechanism: Sliding 60-second window backed by MySQL `rate_limits` table.
- Throttled Endpoints:
  - Authentication (`/api/auth.php`): 60/min
  - Registration (`/api/register/tutor.php`, `/api/register/student.php`): 30/min
  - Bookings (`/api/bookings.php`): 60/min
  - Newsletter (`/api/newsletter/subscribe.php`, `/public/newsletter.php`): 30/min
  - Unsubscribe (`/api/newsletter/unsubscribe.php`, `/public/unsubscribe.php`): 60/min
  - Contact Form (`/public/contact.php`): 20/min
- Response on breach: HTTP 429 Too Many Requests with `Retry-After: <seconds>` header.

---

## 15. Audit & Logging Review
- Audit trail: All security-sensitive actions (approvals, suspensions, DBS verifications, bookings, blog moderation, privacy erasures) recorded via `AuditService`.
- Phrasing standard:
  > *"Audit records protected by server-side authorization and controlled application access."*
- Log redaction: Passwords, bearer tokens, API secrets, and DBS certificate numbers automatically stripped before writing to log streams.

---

## 16. Firebase Security Review
- Verification boundary: Firebase Admin SDK cryptographically verifies Firebase ID tokens.
- Identity mapping: Firebase UID mapped strictly to MySQL database user record.
- Trust boundary: Client-side Firebase custom claims or browser user properties are never used to determine application permissions.
- Local limitation: Live Firebase token verification requires network access or mocked service credentials; local test suite verifies boundary via mock verifier and malformed token rejection.

---

## 17. Dependency Review & Complete Composer Audit Evidence

### Platform Requirements & Extensions
- **PHP Platform Requirement**: `php: >=8.2` (Locked platform configured in `composer.json` config: `8.2.12`).
- **Required PHP Extensions**:
  - `ext-pdo` (Database connectivity and prepared statement security)
  - `ext-json` (RFC 8259 JSON parsing and response formatting)
  - `ext-curl` (HTTP transport for external service APIs)
  - `ext-openssl` (Cryptographic tokens, hash generation, and TLS encryption)
  - `ext-mbstring` (Multi-byte UTF-8 string encoding and validation)

### Complete Dependency Inventory (37 Packages Installed and Locked)

| Package Name | Installed Version | Type | License | Description / Purpose |
| :--- | :--- | :---: | :--- | :--- |
| **kreait/firebase-php** | `7.24.1` | **DIRECT** | MIT | Firebase Admin SDK (Authentication token verification) |
| **beste/clock** | `3.1.0` | Transitive | MIT | Collection of PSR-20 Clock implementations |
| **beste/in-memory-cache** | `1.4.0` | Transitive | MIT | PSR-6 In-Memory cache implementation |
| **beste/json** | `1.7.0` | Transitive | MIT | JSON encoding and decoding helper |
| **brick/math** | `1.0.0` | Transitive | MIT | Arbitrary-precision arithmetic library |
| **cuyz/valinor** | `2.6.0` | Transitive | MIT | Strongly-typed object mapping library |
| **fig/http-message-util** | `1.1.5` | Transitive | MIT | Utility classes and constants for PSR-7 HTTP messages |
| **firebase/php-jwt** | `v7.2.1` | Transitive | BSD-3-Clause | JSON Web Token (JWT) encoding and decoding library |
| **google/auth** | `v1.55.1` | Transitive | Apache-2.0 | Google Authentication Library for PHP |
| **google/cloud-core** | `v1.73.5` | Transitive | Apache-2.0 | Shared Google Cloud PHP dependency |
| **google/cloud-storage** | `v1.51.0` | Transitive | Apache-2.0 | Google Cloud Storage client for PHP |
| **google/common-protos** | `4.14.3` | Transitive | Apache-2.0 | Google API Common Protos for PHP |
| **google/gax** | `v1.51.0` | Transitive | BSD-3-Clause | Google API Core extensions for PHP |
| **google/grpc-gcp** | `0.4.2` | Transitive | Apache-2.0 | gRPC GCP library for channel management |
| **google/longrunning** | `0.8.5` | Transitive | Apache-2.0 | Google LongRunning operations client |
| **google/protobuf** | `v5.36.2` | Transitive | BSD-3-Clause | Protocol Buffers library for PHP |
| **grpc/grpc** | `1.82.0` | Transitive | Apache-2.0 | gRPC library for PHP |
| **guzzlehttp/guzzle** | `7.15.5` | Transitive | MIT | PHP HTTP client library |
| **guzzlehttp/promises** | `2.5.3` | Transitive | MIT | Promises/A+ library for PHP |
| **guzzlehttp/psr7** | `2.13.1` | Transitive | MIT | PSR-7 HTTP message implementation |
| **kreait/firebase-tokens** | `5.3.0` | Transitive | MIT | Firebase token verification and minting library |
| **lcobucci/jwt** | `5.6.0` | Transitive | BSD-3-Clause | Cryptographic JSON Web Token parser and validator |
| **monolog/monolog** | `3.12.1` | Transitive | MIT | Structured logging library |
| **mtdowling/jmespath.php** | `2.9.2` | Transitive | MIT | Declarative JSON querying library |
| **psr/cache** | `3.0.0` | Transitive | MIT | Common PSR-6 interface for caching libraries |
| **psr/clock** | `1.0.0` | Transitive | MIT | Common PSR-20 interface for reading the clock |
| **psr/http-client** | `1.0.3` | Transitive | MIT | Common PSR-18 interface for HTTP clients |
| **psr/http-factory** | `1.1.0` | Transitive | MIT | Common PSR-17 interfaces for HTTP message factories |
| **psr/http-message** | `2.0` | Transitive | MIT | Common PSR-7 interface for HTTP messages |
| **psr/log** | `3.0.2` | Transitive | MIT | Common PSR-3 interface for logging libraries |
| **ralouphie/getallheaders** | `3.0.3` | Transitive | MIT | HTTP header polyfill |
| **ramsey/collection** | `2.1.1` | Transitive | MIT | Strongly-typed collections library |
| **ramsey/uuid** | `4.9.4` | Transitive | MIT | RFC 4122 universally unique identifier (UUID) library |
| **rize/uri-template** | `0.4.2` | Transitive | MIT | RFC 6570 URI template expansion and extraction |
| **symfony/deprecation-contracts** | `v3.7.1` | Transitive | MIT | Generic deprecation notice convention |
| **symfony/polyfill-mbstring** | `v1.43.0` | Transitive | MIT | Polyfill for Mbstring extension |
| **symfony/polyfill-php80** | `v1.43.0` | Transitive | MIT | Polyfill backporting PHP 8.0+ features |

### Composer Security Audit Verification
- **Command Executed**: `composer audit`
- **Output**: `No security vulnerability advisories found.`
- **Exit Code**: `0`
- **Audit Confirmation**: Verified directly against the active `composer.lock` file and `vendor/` directory state. All 37 locked packages are clean of known security vulnerability advisories. No blind upgrades, downgrades, or dependency additions were introduced.

---

## 18. External Sources
Documented in `docs/PHASE-11-EXTERNAL-SOURCES.md`:
- OWASP Top 10:2021 & Cheat Sheets (XSS, File Upload, REST Security)
- PHP Official Manual (Session Security & PDO)
- MySQL 8.4 Official Manual (InnoDB Row-Level Locking Reads)
- Firebase Official Documentation (Admin SDK ID Token Verification)
- UK ICO Official Guidance (UK GDPR & PECR Engineering Considerations)

---

## 19. Phase 11 Tests
File: `tests/Phase11SecurityPrivacyTest.php`
- Assertions: **78/78 Passed (100%)**
- Execution Time: ~2.5 seconds
- Categories Covered: Authentication, Authorization/IDOR, Role Tampering, Session & Cookie Security (including Cookie Secure environmental testing), Anti-CSRF, Output Escaping & XSS, SQL Injection Hardening, Security Headers & CSP, Secret Management, File Upload & DBS Security (including complete 7-point DBS authorization matrix), Newsletter Privacy, Rate Limiting, Audit Logging, UK GDPR Engineering Hooks, and Booking Safeguards.

---

## 20. Full Regression Results

| Phase | Test Suite | Passing Tests | Status |
| :--- | :--- | :---: | :---: |
| **Phase 3** | Foundation Suite (`run_tests.php`) | 25 / 25 | **PASSED** |
| **Phase 4** | Public Website Suite (`Phase4PublicWebsiteTest.php`) | 30 / 30 | **PASSED** |
| **Phase 5** | Tutor Workflow Suite (`Phase5TutorWorkflowTest.php`) | 51 / 51 | **PASSED** |
| **Phase 6** | Student/Parent Suite (`Phase6StudentParentTest.php`) | 45 / 45 | **PASSED** |
| **Phase 7** | Booking Engine Suite (`Phase7BookingEngineTest.php`) | 40 / 40 | **PASSED** |
| **Phase 8** | Email Integration Suite (`Phase8EmailTest.php`) | 33 / 33 | **PASSED** |
| **Phase 9** | Manager Admin Suite (`Phase9ManagerAdminTest.php`) | 39 / 39 | **PASSED** |
| **Phase 10** | Blog & Newsletter Suite (`Phase10BlogNewsletterTest.php`) | 83 / 83 | **PASSED** |
| **Phase 11** | Security & Privacy Suite (`Phase11SecurityPrivacyTest.php`) | 78 / 78 | **PASSED** |
| **TOTAL** | **Cumulative Platform Regression Suite** | **424 / 424** | **100% PASSED** |

---

## 21. Open Client Decisions
The following 11 business decisions remain OPEN and have NOT been unilaterally resolved:
1. **Double Opt-In Workflow**: Maintained in neutral `PENDING` state; manager activation bypass is strictly blocked.
2. **Email Provider**: Provider-agnostic adapter architecture maintained; zero commercial vendor credentials hardcoded.
3. **Payment Provider**: Payment processing deferred; no vendor selected or installed.
4. **Cancellation Policy**: Seven-state lifecycle preserved; refund/cancellation business rules remain open.
5. **Rescheduling Policy**: Vocabulary strictly limited to 7 states; no `RESCHEDULED` state introduced.
6. **Delivery Mode**: In-person vs online lesson delivery flags remain open.
7. **Lesson Note Visibility**: Student/parent visibility rules for tutor notes remain open.
8. **Recurring Availability**: Recurring schedule generation remains open; discrete slots only.
9. **Multi-Guardian Family Access**: Multi-guardian delegation remains open; single-parent relationship preserved.
10. **DBS Retention / Deletion Policy**: Documents stored securely outside web root; automated purge policy deferred.
11. **Tutor Publishing Toggle**: Manual publishing toggle vs automatic approved status remains open.

---

## 22. Known Limitations
1. **Local HSTS Limitation**: HSTS is omitted in local development over plain HTTP (port 80) per RFC 6797 and emitted only when HTTPS is active or in production.
2. **Local Session Secure Cookie Limitation**: In local development over plain HTTP, `session.cookie_secure` is deliberately omitted to prevent session breakage on localhost; it is strictly enabled for production/HTTPS environments.
3. **CSP Inline Styles Exception**: `style-src` retains `'unsafe-inline'` to support view template inline styling; `script-src` strictly forbids `unsafe-inline` and `unsafe-eval`.
4. **Local Single-Node Rate Limiting**: The database-backed sliding-window rate limiter is engineered for the current single-database architecture; multi-node horizontal clustering would require a centralized Redis cache.
5. **Mock Firebase Verifier in CLI**: Local CLI testing uses mock JWT verification when live Firebase network credentials are unconfigured.

---

## 23. Phase Boundary Confirmation
- Phase 12 (QA/UAT), Phase 13 (Deployment), and Phase 14 (Handover) have NOT been started.
- No production servers, DNS, SSL certificates, commercial email accounts, or payment gateways have been provisioned or configured.

---

PHASE 11 — FINAL VERIFICATION COMPLETE; AWAITING APPROVAL.
