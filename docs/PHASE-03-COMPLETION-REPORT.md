# PHASE 3 — FOUNDATION COMPLETION REPORT

**Project**: UK Tutoring Platform — Production Foundation  
**Phase**: Phase 3 (Foundation Implementation)  
**Document ID**: `DOC-PHASE-03-REPORT`  
**Execution Timestamp**: 2026-10-02  
**Final Status**: **PHASE 3 — FOUNDATION VERIFIED AND READY FOR PHASE 4** (Halted per stop condition)

---

## 1. IMPLEMENTED COMPONENTS

The following foundation subsystems were implemented and verified in the local development environment:

### Core Framework & Bootstrap
* Central application bootstrap (`src/bootstrap.php`) with exception safety, SAPI detection, and production error masking.
* PSR-4 autoloading (`App\` &rarr; `src/`, `Tests\` &rarr; `tests/`) configured via Composer.
* Lightweight, robust environment parser (`src/Support/Env.php`) and `.env` mechanism.
* Dynamic timezone localization utility (`src/Support/Timezone.php`) reconciling UTC database storage with UK local time (`Europe/London` GMT/BST).
* Uniform JSON API response envelope (`src/Support/Response.php`) enforcing security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`).
* Server-side input validator and XSS sanitizer (`src/Validation/Validator.php`).
* Daily rotating logger (`src/Logging/Logger.php`) with automated recursive secret redaction.

### Persistence & Schema Layer
* Reusable PDO database connection singleton (`src/Database/Database.php`) configured with `ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`, `utf8mb4`, and UTC session timezone.
* Atomic database migration runner (`src/Database/Migration.php` and `database/migrate.php`) with tracking ledger (`migrations` table).
* Database table count breakdown: **12 physical tables = 11 application domain tables + 1 migration infrastructure table.**
  * The 11 application domain tables represent the approved Master Document business entities:
    1. `users`
    2. `tutor_profiles`
    3. `student_profiles`
    4. `children`
    5. `availability_slots`
    6. `bookings`
    7. `booking_status_history`
    8. `lesson_notes`
    9. `audit_logs`
    10. `newsletter_subscribers`
    11. `blog_posts`
  * The 1 migration infrastructure table:
    * `migrations`: Operational infrastructure table used solely to track schema migration execution batches. It is not an application business entity.

### Identity & Authorization Engine
* Firebase Admin PHP SDK (`kreait/firebase-php` v7.24.1) installed and configured outside the public web root.
* Server-side Firebase ID token verifier (`src/Auth/FirebaseTokenVerifier.php`) extracting cryptographically validated claims (`sub`, `email`, `email_verified`).
* Relational user mapping establishing MySQL as the sole source of truth for application roles (`STUDENT_PARENT`, `TUTOR`, `MANAGER`).
* Server-side authorization engine (`src/Authorization/Authorization.php`) enforcing role capabilities, object ownership (IDOR prevention), and active account status.
* Controlled registration endpoints:
  - `/api/register/student.php` (strictly assigns `STUDENT_PARENT` role with `ACTIVE` status).
  - `/api/register/tutor.php` (strictly assigns `TUTOR` role with `PENDING` status).
* Administrative CLI manager provisioning script (`bin/create_manager.php`).

### Operational & Observability Endpoints
* Public subsystem health check (`/api/health.php` and HTTP router `http://localhost/api/health.php`) verifying PHP, MySQL connectivity, global timezone, table count, and storage writability.
* Front controller and REST router (`public/index.php`).
* Immutable audit logger (`src/Services/AuditService.php`) capturing security events with SHA-256 IP address hashing as a privacy-preserving audit control.

---

## 2. VERIFIED CHECKS AND RESULTS

Every item listed below was executed and verified locally:

| Category | Verification Item | Execution Method | Result |
| :--- | :--- | :--- | :--- |
| **Preflight** | PHP 8.3 CLI & 7 Required Extensions + Sodium | `php preflight_check.php` | **PASS (100%)** |
| **Dependencies** | Composer 37 packages installed cleanly | `composer install / update` | **PASS (0 vulnerabilities)** |
| **Database** | Standalone MySQL 8.4.9 PDO connection | `php database/migrate.php` | **PASS (Connected)** |
| **Schema** | All 6 migration files applied, 12 tables installed | `php database/migrate.php --status` | **PASS (All 12 tables InnoDB)** |
| **HTTP Web** | Apache port 80 serving `/` and `/api/health.php` | `Invoke-RestMethod http://localhost/` | **PASS (HTTP 200 OK)** |
| **HTTP Health**| Live database, PHP, and storage checks via HTTP | `Invoke-RestMethod http://localhost/api/health.php` | **PASS (HTTP 200 OK)** |
| **HTTP Auth** | Unauthenticated request rejected via HTTP | `curl.exe -i http://localhost/api/auth.php` | **PASS (HTTP 401 Unauthorized)** |
| **HTTP Token**| Invalid Bearer token rejected via HTTP | `curl.exe -i -H "Authorization: Bearer bad" http://localhost/api/auth.php` | **PASS (HTTP 401 Unauthorized)** |
| **Test Suite** | Comprehensive 25-point automated test harness | `php tests/run_tests.php` | **PASS (25/25, 100%)** |
| **Case A** | Registered user exists in MySQL &rarr; Authentication succeeds | `AuthTest::testRegisteredUserResolved()` | **PASS** |
| **Case B** | Unregistered Firebase UID &rarr; `USER_NOT_REGISTERED` (404) | `AuthTest::testUnregisteredFirebaseUserRejected()` | **PASS** |
| **Case C** | Client sends `{"role": "MANAGER"}`, MySQL says `STUDENT_PARENT` &rarr; Role remains `STUDENT_PARENT`, access blocked | `AuthorizationTest::testClientRoleCannotOverrideMySQL()` | **PASS** |
| **Case D** | Client attempts to self-register as `MANAGER` &rarr; Blocked | `AuthorizationTest::testManagerSelfAssignmentRejected()` | **PASS** |
| **Timezone** | Dynamic GMT (UTC+0) vs BST (UTC+1) localization | `ConfigTest::testTimezoneTranslations()` | **PASS** |
| **Git Safety** | `.env` and service credentials excluded from Git | `git check-ignore .env` / `git status` | **PASS (Zero secrets tracked)** |

---

## 3. NOT VERIFIED (DELIBERATE SCOPE EXCLUSIONS)

The following items were **deliberately not verified** in accordance with project constraints:
1. **Live Production Firebase Project Credential Verification**: Live end-to-end token verification against a production Google Cloud Console project requires real client interactive browser login and private service account JSON keys. These will be configured during staging/production onboarding without affecting local foundation validity.
2. **Cloud Infrastructure & Live Hosting**: Live deployment, production Linux Nginx reverse proxy, and SSL certificate installation belong strictly to official Phase 13 (Deployment).
3. **Transactional SMTP Relay Dispatch**: Live email delivery via third-party providers (SendGrid, Mailgun) was not executed, preserving the open client decision on email provider selection.

---

## 4. PHP RUNTIME CONSISTENCY

* **CLI Runtime**: PHP 8.3.33 (64-bit) used for CLI migrations, preflight checks, and automated test harness execution.
* **Web Runtime**: PHP 8.2.12 (`apache2handler`) loaded dynamically by Apache 2.4.58 for serving live HTTP web and API traffic on port 80.
* **Composer Platform Pin**: `composer.json` explicitly pins `"platform": { "php": "8.2.12" }`, guaranteeing that all installed packages and the generated autoloader remain fully compatible with PHP 8.2 without version check exceptions.
* **Dual Runtime Verification**: Full verification is not claimed solely based on CLI PHP 8.3 tests. Both runtimes have been actively verified:
  1. CLI test suite passes 25/25 under PHP 8.3.33.
  2. Live HTTP endpoints (`/`, `/api/health.php`, `/api/auth.php`) pass under Apache's PHP 8.2.12 runtime.
* **No PHP 8.3-Only Syntax**: The application code strictly avoids PHP 8.3-only syntax (e.g. typed class constants, `#[\Override]` attributes, or `json_validate()`).

---

## 5. AUTHENTICATION VS AUTHORIZATION HTTP STATUS BEHAVIOR

The implementation enforces a strict HTTP status code distinction:
* **Authentication Failures (HTTP 401 Unauthorized)**:
  - Missing `Authorization` header &rarr; 401 (`INVALID_TOKEN`)
  - Malformed non-Bearer header &rarr; 401 (`INVALID_TOKEN`)
  - Empty Bearer token &rarr; 401 (`INVALID_TOKEN`)
  - Invalid, expired, or forged JWT signature &rarr; 401 (`INVALID_TOKEN`)
  *Observed via live HTTP curl*: `HTTP/1.1 401 Unauthorized` with `Content-Type: application/json`.
* **Authorization Failures (HTTP 403 Forbidden)**:
  - Valid token, but user account is `PENDING` or `SUSPENDED` &rarr; 403 (`ACCOUNT_INACTIVE`)
  - Valid user, but insufficient role capability (e.g. Student accessing Manager endpoint) &rarr; 403 (`INSUFFICIENT_ROLE_PERMISSIONS`)
  - Valid user, but accessing another user's private resource &rarr; 403 (`UNAUTHORIZED_RESOURCE_OWNERSHIP`)
  - Client attempting to self-register as Manager &rarr; 403 (`SELF_REGISTRATION_MANAGER_FORBIDDEN`)
* **Unregistered Identity (HTTP 404 Not Found)**:
  - Valid Firebase token, but UID has no application record in MySQL &rarr; 404 (`USER_NOT_REGISTERED`). No privileges are granted.

---

## 6. ROLE AUTHORITY IMPLEMENTATION

The architecture guarantees that:
```text
Firebase UID
    ↓
users.firebase_uid
    ↓
MySQL application role
    ↓
server-side Authorization
```
is the **sole authoritative role path**.

Client-supplied role and permission fields cannot supply or override authorization through:
* Browser input
* POST body
* JSON request payload
* URL query parameter
* Hidden form field
* Firebase client-side state
* Client-supplied `role`, `status`, `is_manager`, or `is_admin` parameters

All client-supplied authorization fields are explicitly discarded on arrival, and Manager provisioning is strictly restricted to authorized server-side CLI bootstrap (`bin/create_manager.php`).

---

## 7. AUDIT & IP HASHING SPECIFICATION

IP addresses are hashed using SHA-256 before persistence as a privacy-preserving audit control. Overall UK GDPR compliance depends on the complete implementation, configuration, retention rules, legal basis, policies and approved legal requirements. No premature legal certification claims are made.

---

## 8. SECURITY CONTROLS: PHASE BOUNDARIES

The security architecture strictly distinguishes between foundation controls implemented in Phase 3 and advanced security controls scheduled for subsequent phases:

### Foundation Controls Implemented in Phase 3
* **Zero Client Trust**: Client authorization fields are discarded; application roles are derived solely from MySQL.
* **Server-Side Token Verification**: Tokens are validated cryptographically against Google's public certificates via the Firebase Admin SDK.
* **Prepared Statements**: 100% of SQL queries utilize PDO prepared statements with native parameter binding (`ATTR_EMULATE_PREPARES => false`).
* **Private Storage Outside Web Root**: Sensitive credentials (`storage/credentials/`) and uploaded evidence (`storage/private/`) reside strictly outside Apache's `public/` web root.
* **HTTP Security Headers**: Native headers attached to all API responses: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and `Referrer-Policy: strict-origin-when-cross-origin`.
* **Credential Redaction in Logs**: Sensitive fields (`password`, `token`, `secret`, `api_key`) are masked recursively before log output.
* **Privacy-Preserving Audit Hashing**: IP addresses are hashed using SHA-256 before audit persistence.

### Security Controls Deferred to Later Phases (Phases 4–13)
The following controls belong to subsequent official phases and are not part of the Phase 3 foundation:
* **CSRF Protection**: Form token generation and validation for session-based browser submissions (Phase 4+).
* **Rate Limiting**: IP and user-based throttling on login verification, booking submissions, and newsletter endpoints (Phase 4+ / Phase 12).
* **Complete Content Security Policy (CSP)**: Fine-grained CSP directive enforcement for frontend script, style, and frame sources (Phase 13 — Deployment).
* **Secure Cookie / Session Hardening**: Production cookie flags (`SameSite=Strict`, `Secure`, `HttpOnly`) when stateful session cookies are introduced (Phase 4+).
* **Advanced Upload Security**: Antivirus scanning, file content inspection, and strict MIME type enforcement for DBS certificates (Phase 6).
* **Automated Penetration & SAST Testing**: Static and dynamic vulnerability assessments (Phase 13 — Deployment).
* **Automated Data Retention Purging**: Cron-based scheduled purging of expired DBS data and audit logs (Phase 12 / Phase 13).

---

## 9. FIREBASE LIVE TOKEN VERIFICATION LIMITATION

> **Explicit Limitation Statement**:  
> Local foundation tests pass (25/25, 100%). Live verification using a real Firebase-issued ID token requires the configured Firebase project and credentials and is not claimed as fully verified unless actually executed.

---

## 10. EXTERNAL TECHNICAL SOURCES SUMMARY

As documented in [docs/PHASE-03-EXTERNAL-SOURCES.md](file:///c:/Users/Dineshkumar%20M/OneDrive/Desktop/Client%20Apptutors/docs/PHASE-03-EXTERNAL-SOURCES.md):
1. **Firebase Admin PHP SDK (Kreait)**: [firebase-php.readthedocs.io](https://firebase-php.readthedocs.io/)
2. **Firebase Authentication Official Documentation**: [firebase.google.com/docs/auth](https://firebase.google.com/docs/auth)
3. **PHP Official Documentation (PDO)**: [php.net/manual/en/book.pdo.php](https://www.php.net/manual/en/book.pdo.php)
4. **PHP Official Documentation (Date/Time)**: [php.net/manual/en/book.datetime.php](https://www.php.net/manual/en/book.datetime.php)
5. **MySQL 8.4 Reference Manual**: [dev.mysql.com/doc/refman/8.4/en/](https://dev.mysql.com/doc/refman/8.4/en/)
6. **Composer Official Documentation**: [getcomposer.org/doc/](https://getcomposer.org/doc/)

---

## 11. OPEN CLIENT DECISIONS CARRIED FORWARD

All 19 open client decisions identified in Phase 1 Discovery and Phase 2 Architecture remain strictly open and uncommitted:
* The payment model remains an open client decision without committing to a provider (`[FUTURE / DEFERRED / OPEN CLIENT DECISION]`).
* No commercial payment gateway is assumed or selected.
* All other 17 discovery items (taxonomy, multiple guardians, lesson delivery mode, DBS scope/retention, email provider, etc.) remain decoupled and ready for business approval.

---

## 12. PHASE 4 READINESS

> **PHASE 3 — FOUNDATION IS 100% COMPLETE, FUNCTIONAL, AND READY FOR PHASE 4.**
> 
> The local development platform is stable, verified, and ready to support **Phase 4 — Public Website Implementation**.

---

## 13. STOP CONDITION

In accordance with Phase 3 instructions, **execution is now stopped**. No Phase 4 features have been implemented. Awaiting explicit user approval to proceed.
