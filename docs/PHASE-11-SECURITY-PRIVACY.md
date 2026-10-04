# Phase 11 — Security & Privacy Hardening Specification & Architecture

## 1. Architectural Scope & Boundary Enforcement

The platform enforces a strict security perimeter where the browser is completely untrusted. The request-response flow follows this boundary:

```
Browser Client
    │
    ▼ (HTTPS / TLS)
PHP Web Application / REST APIs
    │
    ├─► Request Validation & JSON Parsing (Request::getJsonBody())
    ├─► Rate Limiter Enforcement (RateLimiter::enforce())
    ├─► Anti-CSRF Token Validation (Csrf::validateToken()) [Session/Cookie endpoints]
    ├─► Identity Verification (Firebase Admin SDK verifies JWT ID token)
    │
    ▼ (Firebase UID -> MySQL user ID)
MySQL 8.4 Application Authority
    │
    ├─► Authoritative Role Resolution (`users.role`: MANAGER, TUTOR, STUDENT_PARENT)
    ├─► Account Status Gate (`users.status`: ACTIVE, PENDING, SUSPENDED)
    ├─► Safeguarding Gate (`ACTIVE + APPROVED + DBS VERIFIED`)
    ├─► IDOR / Fine-Grained Ownership Verification
    ├─► Concurrency Row-Level Locks (`SELECT ... FOR UPDATE`)
    │
    ▼
Audit Log & Privacy Minimization (AuditService + Logger PII Masking)
```

### Critical Rules
1. **Server-Side Authority**: Browser-supplied roles, hidden form fields, Firebase custom claims, or user IDs are never trusted to make authorization decisions.
2. **Safeguarding Gate**: Tutors must satisfy `ACTIVE + APPROVED + DBS VERIFIED` before publishing availability or receiving bookings.
3. **DBS Confidentiality Boundary**: Raw, sensitive DBS document retrieval is restricted to authorized **MANAGER ONLY**. Owning tutors, other tutors, students/parents, and public users are strictly blocked from retrieving raw DBS document files. Tutors may only access their non-sensitive DBS status/lifecycle badge.
4. **Immutability Statement**: Audit records are described using the approved phrasing:
   > "Audit records protected by server-side authorization and controlled application access."
   No false claims of cryptographic immutability are made.
5. **Legal & Compliance Disclaimer**:
   > "Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements."
   No claims of formal legal certification, GDPR compliance, or PECR compliance are made.

---

## 2. Threat Modeling & Control Matrix

| Threat Category | Potential Attack Vector | Applied Engineering Control | Verification Test |
| :--- | :--- | :--- | :--- |
| **Authentication** | Token forgery, missing headers, unverified JWT | `FirebaseTokenVerifier::verifyIdToken()`, HTTP 401 response | `tests/Phase11SecurityPrivacyTest.php` Section 1 |
| **Authorization / IDOR** | Manipulating user/child/booking IDs | `Authorization::assertCanAccessChild()`, `assertCanAccessBooking()`, `assertTutorOwnsResource()`, `assertManager()` | Section 2 |
| **Role Tampering** | Injecting `"role": "MANAGER"` in JSON payload | `Authorization::assertCannotSelfAssignManager()`, server-side role override | Section 3 |
| **Session Fixation** | Stealing or adopting pre-login session ID | `Session::regenerate(true)`, `session.use_strict_mode = 1`, `session.cookie_httponly = 1`, `session.cookie_samesite = 'Lax'` | Section 4 |
| **Cookie Insecurity** | Cookie transmission over plain HTTP in production | `session.cookie_secure` enabled for HTTPS/production; deliberately omitted in local plain HTTP | Section 4 |
| **CSRF** | Cross-origin form forgery | Cryptographic 64-char hex synchronizer token in `Csrf.php`, timing-safe `hash_equals()` | Section 5 |
| **Cross-Site Scripting** | Script tag in tutor bio, child notes, blog body | `View::e()` entity encoding with `ENT_QUOTES \| ENT_SUBSTITUTE`, `Validator::sanitizeString()` | Section 6 |
| **SQL Injection** | SQL fragment injection in search, sort, or pagination | PDO prepared statements with parameter binding, allowlists for sort fields and directions | Section 7 |
| **Clickjacking / Sniffing** | Frame embedding, MIME confusion | Security headers: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` | Section 8 |
| **Content Security Policy** | Unauthorized scripts, inline execution | CSP: `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; base-uri 'self';` (Note: `style-src 'unsafe-inline'` is an application compatibility exception for view styles; `script-src` contains NO `unsafe-inline` or `unsafe-eval`) | Section 8 |
| **Secret Leakage** | Direct HTTP access to `.env`, service accounts | Apache `.htaccess` deny rules, `.gitignore` exclusions, `Logger` sensitive key masking | Section 9 |
| **File Upload Abuse** | Uploading `.php` shells or polyglots | Extension allowlist (`pdf`, `png`, `jpg`, `jpeg`), MIME verification, body script detection, 5MB limit, storage outside public web root | Section 10 |
| **DBS Privacy** | Non-managers retrieving raw DBS files | Raw DBS retrieval restricted to **MANAGER ONLY**; owning tutor, other tutors, students/parents, and public strictly blocked (403/401) | Section 10 |
| **Unsolicited Email** | Marketing spam, non-consensual signups | Explicit consent checkbox validation, neutral `PENDING` state, `SUPPRESSED` status enforcement | Section 11 |
| **Brute Force / Abuse** | Endpoint flooding, credential stuffing | Database-backed `RateLimiter` with sliding 60-second window, HTTP 429 response | Section 12 |
| **Logging Data Leakage** | Passwords, tokens, or DBS certs in logs | `Logger` and `AuditService` automatic redaction of sensitive keys (`token`, `password`, `dbs_certificate_number`) | Section 13 |
| **Privacy / DSAR** | Lack of portable export mechanism | `PrivacyService::exportUserData()` machine-readable portable JSON hook | Section 14 |
| **Privacy / Erasure** | Uncontrolled deletion vs application integrity | `PrivacyService::prepareAccountErasure()` technical anonymization hook; active/upcoming bookings defer execution pending operational/cancellation policy confirmation; no hardcoded retention rules | Section 14 |
| **Double Booking** | Race conditions on slot reservation | MySQL 8.4 InnoDB `SELECT ... FOR UPDATE` row-level locks, transaction rollback | Section 15 |

---

## 3. Rate Limiting Architecture

A resilient, database-backed rate limiter is implemented in `src/Support/RateLimiter.php` utilizing the `rate_limits` table:

* **Table Schema**:
  * `rate_key VARCHAR(191) PRIMARY KEY`
  * `hits INT UNSIGNED NOT NULL DEFAULT 1`
  * `reset_at DATETIME NOT NULL`
  * `created_at DATETIME NOT NULL`
* **Mechanism**:
  * Client IP + Action hashed into a discrete rate key.
  * Window duration defaults to 60 seconds.
  * When hits exceed the configured threshold, throws `RateLimitExceededException` (HTTP 429) returning `Retry-After: <seconds>` header.
* **Protected Endpoints**:
  * `/api/auth.php` (60 requests/min)
  * `/api/register/tutor.php` (30 requests/min)
  * `/api/register/student.php` (30 requests/min)
  * `/api/bookings.php` (60 requests/min)
  * `/api/newsletter/subscribe.php` (30 requests/min)
  * `/api/newsletter/unsubscribe.php` (60 requests/min)
  * `/public/newsletter.php` (30 requests/min)
  * `/public/unsubscribe.php` (60 requests/min)
  * `/public/contact.php` (20 requests/min)
* **Local vs Production**: Database-backed implementation requires no external dependencies (e.g. Redis) and operates cleanly in both local development and single-server production.

---

## 4. Privacy Engineering Controls & Policy Separation

> **Guiding Principle**: Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.

### Subject Access Requests (DSAR Hook)
* Endpoint: `GET /api/privacy.php?action=export`
* Authorization: User can export only their own data. Managers can export any user's data for compliance processing.
* Response: Machine-readable JSON package structured into:
  1. `account`: Core user profile, role, status, registration date.
  2. `profile`: Extended profile attributes (tutor subjects, student address/phone).
  3. `children`: Associated child profiles (first name, last name, DOB, school year, curriculum).
  4. `bookings`: Complete booking records and status transition history.
  5. `newsletter`: Subscription records, consent timestamps, suppression state.

### Technical Erasure & Minimization Hook
* Endpoint: `POST /api/privacy.php?action=erase`
* Technical Erasure Capability & Policy Separation:
  1. **Technical Mechanism**: Provides server-side technical data minimization and account anonymization.
  2. **Operational Deferral**: When active or upcoming lesson bookings exist, technical erasure execution is deferred (`DEFERRED_PENDING_POLICY_REVIEW`) pending operational and cancellation policy confirmation.
  3. **Relational Application Integrity**: Personal identifying fields (display name, email, phone, postcode, bio) are scrubbed/anonymized while operational records remain structurally intact solely for relational database integrity pending client/legal retention and deletion policy confirmation.
  4. **No Hard-Coded Retention Rules**: The system does NOT silently decide that booking histories or audit records must permanently be retained for "safeguarding", "commercial integrity", or another legal ground.
  5. **No Legal Claims**: No claim is made that booking history or audit logs are legally required to be retained, nor that safeguarding or commercial integrity automatically authorizes retention under GDPR Article 17 or any statutory ground.
  6. **Erasure Auditability**: Auditability of the erasure action itself is technically preserved in audit logs without inventing an unapproved permanent retention period.
  7. **DBS Retention Kept Open**: Physical DBS document retention schedules remain explicitly an open client decision (`CLIENT_DECISION_OPEN` / `DISC-020`); no fixed retention period (e.g. 1 year, 7 years) is hard-coded.
  8. **Separation of Concerns**:
     * *Technical mechanism*: Anonymization routines, relational constraints, status transition to `DELETED`.
     * *Client/business policy*: Cancellation terms, inactive account offboarding timelines, DBS evidence archiving.
     * *Legal approval*: Lawful basis, statutory retention schedules, DSAR/erasure policy formalization.
* Technical Execution (when eligible):
  * Personal email replaced with `erased_<id>_<hash>@anonymized.invalid`.
  * Display name replaced with `Erased User`.
  * Phone, address, postcode, and tutor bio cleared.
  * Dependent child records deactivated (`active = 0`).
  * Account status transitioned to `DELETED`.
  * All actions recorded in audit log with anonymized actor context.

### Newsletter Privacy & Suppression
* Neutral `PENDING` Status: New subscribers are created in `PENDING` status with `confirmed_at = NULL`.
* Double Opt-In Boundary: Manager activation is strictly blocked (`DOUBLE_OPT_IN_OPEN_DECISION`) to prevent administrative bypass of the open client policy.
* Anti-Re-solicitation Suppression: Subscribers marked as `SUPPRESSED` cannot be resubscribed to receive marketing materials, ensuring PECR suppression compliance.

---

## 5. Security Findings Summary

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

## 6. Complete Composer Dependency Inventory & Security Audit Evidence

### Platform Requirements & Extensions
* **PHP Platform Requirement**: `php: >=8.2` (Locked platform configured in `composer.json` config: `8.2.12`).
* **Required PHP Extensions**:
  * `ext-pdo` (Database connectivity and prepared statement security)
  * `ext-json` (RFC 8259 JSON parsing and response formatting)
  * `ext-curl` (HTTP transport for external service APIs)
  * `ext-openssl` (Cryptographic tokens, hash generation, and TLS encryption)
  * `ext-mbstring` (Multi-byte UTF-8 string encoding and validation)

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
* **Command Executed**: `composer audit`
* **Output**: `No security vulnerability advisories found.`
* **Exit Code**: `0`
* **Audit Confirmation**: Verified directly against the active `composer.lock` file and `vendor/` directory state. All 37 locked packages are clean of known security vulnerability advisories. No blind upgrades, downgrades, or dependency additions were introduced.
