# PHASE 3 — APPLICATION FOUNDATION SPECIFICATION & IMPLEMENTATION

**Project**: UK Tutoring Platform — Production Foundation  
**Document ID**: `DOC-PHASE-03-FOUNDATION`  
**Status**: COMPLETE & VERIFIED — BASELINE READY FOR PHASE 4  
**Authoritative Baseline**: Production Master Project Document v2.0 | UK Tutoring Platform  
**Approved Architecture Baseline**: [docs/PHASE-02-ARCHITECTURE.md](file:///c:/Users/Dineshkumar%20M/OneDrive/Desktop/Client%20Apptutors/docs/PHASE-02-ARCHITECTURE.md)  
**External Sources Registry**: [docs/PHASE-03-EXTERNAL-SOURCES.md](file:///c:/Users/Dineshkumar%20M/OneDrive/Desktop/Client%20Apptutors/docs/PHASE-03-EXTERNAL-SOURCES.md)  

---

## 1. PHASE OBJECTIVE

The objective of Phase 3 is to construct the production-grade local application foundation upon which all subsequent phases of the UK Tutoring Platform will build. This establishes:
* The modular PHP 8.x application bootstrap, PSR-4 autoloading, and centralized error handling.
* Composer dependency management utilizing the official Firebase Admin PHP SDK (`kreait/firebase-php`).
* Secure environment variable configuration (`.env` and `src/Support/Env.php`).
* The local standalone MySQL 8.4 LTS application database (`tutoring_platform_dev`) with reproducible migrations covering all 11 domain entities.
* The PDO database abstraction layer enforcing strict prepared statements and UTC timestamp normalization.
* Server-side Firebase Authentication ID-token verification, extracting trusted Firebase UIDs and mapping them strictly to relational MySQL user authority.
* Server-side role-based access control (RBAC), object ownership validation (IDOR prevention), and manager self-assignment guards.
* Secure logging and audit trails with sensitive credential redaction and SHA-256 IP address hashing. IP addresses are hashed before persistence as a privacy-preserving audit control. Overall UK GDPR compliance depends on the complete implementation, configuration, retention rules, legal basis, policies and approved legal requirements.
* A comprehensive automated test harness verifying database integrity, authentication paths, authorization boundaries, and configuration safety.

---

## 2. ENVIRONMENT & RUNTIME CONSISTENCY

The application is built and verified locally on Windows 11 using the verified Phase 00 preflight environment:

| Subsystem | Specification / Version | Local Path / Port | Operational Role |
| :--- | :--- | :--- | :--- |
| **Operating System** | Windows 11 Home Single Language 64-bit | Local Developer Machine | Development and local execution environment. |
| **PHP CLI** | PHP 8.3.33 (cli) | `C:\Users\Dineshkumar M\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_...` | CLI migrations, test execution, administrative tooling. |
| **PHP Web Handler** | PHP 8.2.12 (`apache2handler`) | Loaded dynamically by Apache 2.4.58 | Handles live HTTP web and API traffic. |
| **Web Server** | Apache 2.4.58 (Win64) OpenSSL/3.1.3 | `C:\Users\Dineshkumar M\AppData\Local\Programs\xampp\apache` (Port 80) | Local HTTP reverse proxy and front controller router. |
| **Database Server** | Oracle MySQL Community Server 8.4.9 LTS | `C:\Users\Dineshkumar M\AppData\Local\Programs\mysql-8.4.9-winx64` (Port 3306) | Authoritative relational source of truth (InnoDB, UTC). |
| **Dependency Manager**| Composer 2.11-dev (PHP 8.3) | `C:\Users\Dineshkumar M\AppData\Local\Programs\Composer\composer.bat` | PSR-4 autoloading and third-party vendor management. |
| **Version Control** | Git 2.54.0.windows.1 | Project root `.git` | Source code management with strict `.gitignore` rules. |

### Runtime Consistency Analysis & Verification
* **Dual Runtime Environment**: Development and CLI tooling execute under PHP 8.3.33, while the Apache web server executes under PHP 8.2.12 (`apache2handler`).
* **Composer Platform Pinning**: To ensure 100% interoperability without package resolution conflicts or runtime mismatch errors, `composer.json` explicitly pins `"config": { "platform": { "php": "8.2.12" } }`.
* **Zero PHP 8.3-Only Syntax**: The application code strictly avoids PHP 8.3-only syntax (such as typed class constants, `#[\Override]` attributes, or `json_validate()`). All code is fully compatible with PHP 8.2+.
* **Dual Runtime Verification**: Full verification is not claimed solely based on CLI PHP 8.3 tests. Both runtimes have been actively verified:
  1. CLI test suite passes 25/25 under PHP 8.3.33.
  2. Live HTTP endpoints (`/`, `/api/health.php`, `/api/auth.php`) pass under Apache's PHP 8.2.12 runtime.

---

## 3. DEPENDENCIES INSTALLED

All PHP dependencies are managed deterministically via Composer in `composer.json` and locked in `composer.lock`.

### Mandatory PHP Extensions Verified Active
* `pdo` & `pdo_mysql`: Database connectivity using native MySQL driver.
* `openssl`: Cryptographic token verification and TLS encryption.
* `mbstring`: Multibyte string processing for internationalization.
* `curl`: HTTP transport for Firebase public key fetching.
* `json`: Fast native JSON parsing and encoding.
* `fileinfo`: Server-side MIME validation for private file uploads.
* `sodium`: Required for cryptographic JWT signature verification (`lcobucci/jwt`).

---

## 4. COMPOSER PACKAGES AND VERSIONS

The locked dependency graph (`composer.lock`) incorporates 37 packages without security advisories:

| Package | Version | Purpose |
| :--- | :--- | :--- |
| **`kreait/firebase-php`** | `7.24.1` | Official Firebase Admin SDK for PHP. Provides ID token verification and Google credential transport. |
| **`kreait/firebase-tokens`**| `5.3.0` | JWT token parsing and Google public key verification. |
| **`lcobucci/jwt`** | `5.6.0` | Cryptographic JWT token encoding and decoding standard. |
| **`monolog/monolog`** | `3.12.1` | PSR-3 compliant logging engine. |
| **`guzzlehttp/guzzle`** | `7.15.5` | PSR-18 HTTP client for fetching Google certs and auth keys. |
| **`ramsey/uuid`** | `4.9.4` | RFC 4122 compliant UUID generation for sanitized file storage. |

### Composer Platform Pinning
To ensure 100% interoperability between the PHP 8.3 CLI runner and the Apache PHP 8.2 web handler, `composer.json` explicitly pins:
```json
"config": {
    "platform": {
        "php": "8.2.12"
    }
}
```

---

## 5. PHP APPLICATION STRUCTURE

The codebase strictly adheres to the approved modular architecture:

```text
/
├── .env                                # Local environment secrets (STRICTLY GIT-IGNORED)
├── .env.example                        # Safe repository template without credentials
├── .gitignore                          # Comprehensive exclusion rules
├── composer.json                       # Composer configuration (PSR-4 App\ -> src/)
├── composer.lock                       # Locked dependency versions
│
├── api/                                # Direct API Endpoints
│   ├── auth.php                        # Identity verification & MySQL authority endpoint
│   ├── health.php                      # Comprehensive subsystem health check
│   └── register/                       # Controlled Server-Side Registration
│       ├── student.php                 # Strictly assigns STUDENT_PARENT role (ACTIVE)
│       └── tutor.php                   # Strictly assigns TUTOR role (PENDING status)
│
├── bin/                                # Administrative & CLI Scripts (CLI-Only)
│   └── create_manager.php              # Authorized administrative Manager provisioning
│
├── config/                             # Static Application Configuration Files
│   ├── app.php                         # App name, timezone, base URLs, debug flag
│   ├── database.php                    # MySQL connection parameters and PDO attributes
│   ├── firebase.php                    # Project ID, service account key path
│   └── mail.php                        # Transactional email credentials
│
├── database/                           # Database Schema & Migrations
│   ├── migrate.php                     # CLI migration runner with status reporting
│   └── migrations/                     # Ordered SQL Schema Migrations (001 to 006)
│
├── docs/                               # Project Architecture & Discovery Documentation
│   ├── PHASE-00-PREFLIGHT.md           # Environment setup verification
│   ├── PHASE-01-DISCOVERY.md           # Approved discovery baseline
│   ├── PHASE-02-ARCHITECTURE.md        # Approved technical architecture specification
│   ├── PHASE-03-FOUNDATION.md          # THIS TECHNICAL SPECIFICATION
│   ├── PHASE-03-EXTERNAL-SOURCES.md    # Registry of official technical references
│   └── PHASE-03-COMPLETION-REPORT.md   # Final Phase 3 quality verification report
│
├── public/                             # Public Web Root (Served by Web Server)
│   └── index.php                       # Front controller & REST API router
│
├── src/                                # Core Domain Code (PSR-4 App\)
│   ├── bootstrap.php                   # Central bootstrap, timezone, error handlers
│   ├── Auth/
│   │   ├── Exceptions/                 # Authentication, Token, and Registration Exceptions
│   │   ├── FirebaseTokenVerifier.php   # Token signature verification & MySQL user resolution
│   │   └── UserContext.php             # Authenticated user value object
│   ├── Authorization/
│   │   ├── Authorization.php           # RBAC, ownership (IDOR), and status policies
│   │   └── ForbiddenException.php      # 403 Forbidden domain exception
│   ├── Database/
│   │   ├── Database.php                # PDO connection singleton (utf8mb4, UTC)
│   │   └── Migration.php               # Atomic migration execution & tracking engine
│   ├── Logging/
│   │   └── Logger.php                  # Daily rotating log writer with secret redaction
│   ├── Services/
│   │   └── AuditService.php            # Immutable audit trail logger (audit_logs table)
│   ├── Support/
│   │   ├── Env.php                     # Lightweight, robust .env parser
│   │   ├── Response.php                # Standardized JSON response envelope
│   │   └── Timezone.php                # UTC <-> Europe/London dynamic localization
│   └── Validation/
│       └── Validator.php               # Server-side input validation and sanitization
│
├── storage/                            # Private Storage (OUTSIDE WEB ROOT)
│   ├── credentials/                    # Firebase service-account JSON (GIT-IGNORED)
│   ├── logs/                           # Daily rotating runtime logs (GIT-IGNORED)
│   └── private/                        # Uploaded DBS evidence files (GIT-IGNORED)
│
└── tests/                              # Automated Foundation Test Harness
    ├── AuthTest.php                    # Token verification & MySQL mapping tests
    ├── AuthorizationTest.php           # RBAC, IDOR, Case C & Case D security tests
    ├── ConfigTest.php                  # Env, timezone DST, and secret exclusion tests
    ├── DatabaseTest.php                # PDO, tables, engine, and migration tests
    └── run_tests.php                   # Master CLI test runner
```

---

## 6. ENVIRONMENT CONFIGURATION

Application configuration is loaded from a local `.env` file via `App\Support\Env`. 
* `.env` is **STRICTLY EXCLUDED** from version control in `.gitignore`.
* A clean template [`.env.example`](file:///c:/Users/Dineshkumar%20M/OneDrive/Desktop/Client%20Apptutors/.env.example) is committed to the repository with placeholder values.

### Core Configuration Keys
```dotenv
APP_NAME="UK Tutoring Platform"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Europe/London

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tutoring_platform_dev
DB_USERNAME=root
DB_PASSWORD=
DB_TIMEZONE="+00:00"

FIREBASE_PROJECT_ID=uktutoring-platform-dev
FIREBASE_CREDENTIALS_PATH=storage/credentials/firebase-service-account.json
```

---

## 7. MYSQL DATABASE SETUP

The application utilizes Oracle MySQL 8.4.9 LTS Community Server on port 3306.
* **Database Name**: `tutoring_platform_dev`
* **Character Set**: `utf8mb4`
* **Collation**: `utf8mb4_unicode_ci`
* **Default Storage Engine**: `InnoDB`
* **Server Timezone**: `+00:00` (UTC)

### Database Table Count & Entity Breakdown
**12 physical tables = 11 application domain tables + 1 migration infrastructure table.**

The 11 application domain tables represent the approved Master Document business entities:
1. `users`: Core user accounts and authoritative RBAC role mapping.
2. `tutor_profiles`: Qualifications, bios, JSON subject/curriculum tags, and DBS status.
3. `student_profiles`: Contact details and postcode.
4. `children`: Student profiles linked to parent users.
5. `availability_slots`: Discrete calendar windows for tutoring sessions.
6. `bookings`: Master booking transactional state machine.
7. `booking_status_history`: Append-only transition history ledger.
8. `lesson_notes`: Academic feedback with internal vs parent-visible flags.
9. `audit_logs`: Immutable governance audit log with SHA-256 IP hashing.
10. `newsletter_subscribers`: Subscriber lifecycle and PECR suppression records.
11. `blog_posts`: Tutor-authored articles with managerial moderation workflow.

The 1 migration infrastructure table:
* `migrations`: Operational infrastructure table used solely to track schema migration execution batches. It is not an application business entity.

---

## 8. MIGRATION SYSTEM

Database migrations are managed programmatically via `App\Database\Migration` and executed using `php database/migrate.php`.

### Migration Batches
* `001_create_users_table.sql`: Installs `users` table with unique Firebase UID and email constraints.
* `002_create_profiles_and_children_tables.sql`: Installs `tutor_profiles`, `student_profiles`, and `children` with foreign keys.
* `003_create_availability_and_bookings_tables.sql`: Installs `availability_slots` and `bookings` with check constraints (`ends_at_utc > starts_at_utc`).
* `004_create_booking_history_and_lesson_notes_tables.sql`: Installs `booking_status_history` and `lesson_notes`.
* `005_create_audit_logs_table.sql`: Installs `audit_logs`.
* `006_create_newsletter_and_blog_tables.sql`: Installs `newsletter_subscribers` and `blog_posts`.

### Running Migrations
```bash
# Execute all pending migrations
php database/migrate.php

# Check migration status
php database/migrate.php --status
```

---

## 9. FIREBASE AUTHENTICATION SETUP

Firebase Authentication provides modern, secure user identity management:
* Credential management and password hashing (scrypt).
* Google SSO OAuth token exchange.
* Password reset and email verification workflows.
* Issuance of short-lived (1-hour) cryptographically signed JWT ID tokens.

**Critical Architectural Constraint**: Firebase is **NEVER** used to store application roles, permissions, or business records. Identity and authentication are the sole domain of Firebase.

---

## 10. FIREBASE ADMIN SDK SETUP

The platform initializes the official Firebase Admin SDK via `App\Auth\FirebaseTokenVerifier`:
```php
use Kreait\Firebase\Factory;

$factory = (new Factory())
    ->withProjectId($config['project_id'])
    ->withServiceAccount($fullCredentialsPath);

$auth = $factory->createAuth();
```
* **Credential Isolation**: The Firebase Service Account JSON file is maintained in `storage/credentials/`, completely outside the public web root, and strictly ignored by Git.

---

## 11. AUTHENTICATION FLOW

```
[ Browser / Client ]
       │
       │  1. Client authenticates with Firebase Client SDK (Email/Password or Google)
       │  2. Client receives short-lived Firebase ID Token (JWT)
       ▼
[ Web Server (Apache/PHP) ]
       │  3. HTTP Request sent with "Authorization: Bearer <ID_TOKEN>"
       ▼
[ App\Auth\FirebaseTokenVerifier ]
       │  4. Extracts Bearer token from header
       │  5. Calls verifyFirebaseToken() via Firebase Admin SDK
       │  6. Validates signature cryptographically against Google public keys
       │  7. Extracts verified claims: sub (Firebase UID), email, email_verified
       ▼
[ App\Auth\FirebaseTokenVerifier::resolveUser() ]
       │  8. Queries MySQL: SELECT ... FROM users WHERE firebase_uid = ?
       ▼
[ Result / Outcome ]
       ├── User Exists & Active: Returns UserContext (Role resolved from MySQL)
       ├── User Exists & Pending: Throws AccountInactiveException (403)
       └── User Missing in MySQL: Throws UserNotRegisteredException (404 USER_NOT_REGISTERED)
```

---

## 12. FIREBASE UID → MYSQL MAPPING

The authoritative link between external identity and relational business permissions is anchored by the unique index `idx_users_firebase_uid`:

```sql
SELECT id, firebase_uid, email, display_name, role, status 
FROM users 
WHERE firebase_uid = :firebase_uid 
LIMIT 1;
```

* **Zero Trust Rule**: Any `role`, `permission`, `is_manager`, or `is_admin` parameters submitted by the client are strictly ignored and discarded.
* **Unregistered Users**: If an authenticated Firebase user has no record in MySQL, the system returns a controlled `USER_NOT_REGISTERED` (404) response. The application **NEVER** automatically provisions an application account or assigns privileges upon login.

---

## 13. AUTHORIZATION FLOW & POLICIES

Authorization is governed by `App\Authorization\Authorization`:

1. **`requireAuthenticatedUser(?UserContext $user)`**: Rejects unauthenticated callers with `403 Forbidden` (`UNAUTHENTICATED`).
2. **`requireRole(UserContext $user, array $allowedRoles)`**: Enforces role requirements (`requireRole($user, ['MANAGER'])`). Throws `ForbiddenException` (`INSUFFICIENT_ROLE_PERMISSIONS`).
3. **`assertOwnership(int $resourceOwnerId, int $currentUserId)`**: Prevents Insecure Direct Object Reference (IDOR) attacks by verifying resource ownership. Throws `ForbiddenException` (`UNAUTHORIZED_RESOURCE_OWNERSHIP`).
4. **`requireActiveStatus(UserContext $user)`**: Ensures `PENDING` or `SUSPENDED` users cannot execute protected actions.
5. **`assertCannotSelfAssignManager(string $requestedRole)`**: Hard server-side security check blocking any attempt to self-register as `MANAGER`.

---

## 14. ERROR HANDLING ARCHITECTURE

A unified exception handler is registered in `src/bootstrap.php`:
* **Zero Secret Exposure**: Stack traces, database connection strings, credentials, and raw SQL queries are suppressed from client responses.
* **Standard JSON Error Envelope**:
```json
{
  "success": false,
  "error": {
    "code": "FORBIDDEN",
    "message": "Access denied. Insufficient permissions."
  }
}
```
* **Production vs Development**: When `APP_DEBUG=true`, detailed technical exceptions are visible for local debugging. When `APP_DEBUG=false`, generic messages are returned while full stack traces are safely written to `storage/logs/`.

---

## 15. LOGGING FOUNDATION

The logging engine (`App\Logging\Logger`) provides daily rotating log files in `storage/logs/`:
* **Channels**:
  * `app-YYYY-MM-DD.log`: General application operations and notices.
  * `security-YYYY-MM-DD.log`: Authentication attempts, authorization failures, and token verification rejections.
* **Recursive Secret Redaction**: Automatically scrubs sensitive fields (`password`, `token`, `id_token`, `secret`, `private_key`, `api_key`) from log context before writing to disk.

---

## 16. AUDIT TRAIL FOUNDATION

The `App\Services\AuditService` provides an immutable audit trail recorded directly in the `audit_logs` database table:
* Captures actor user ID, action name, target entity type, entity ID, metadata JSON, and timestamp.
* **Privacy-Preserving Audit Hashing**: Client IP addresses are hashed using SHA-256 (`ip_hash`) before persistence as a privacy-preserving audit control. Overall UK GDPR compliance depends on the complete implementation, configuration, retention rules, legal basis, policies and approved legal requirements.
* **Fault Tolerant**: Audit write failures are caught and logged without crashing active user transactions.

---

## 17. HEALTH CHECK ENDPOINT

A public health check endpoint is implemented at `/api/health.php`:
* Verifies PHP version, SAPI, and memory.
* Verifies environment file loading and local vs UTC time.
* Verifies MySQL connection, server version, global timezone (`+00:00`), and installed table count (12).
* Verifies Firebase configuration status (without leaking secrets or credentials).
* Verifies private storage directory writability across Windows ACLs.

---

## 18. TEST SUITE & VERIFICATION

A dedicated test suite in `tests/` executes 25 automated assertions with 100% pass rate:
```bash
php tests/run_tests.php
```

### Test Coverage Highlights
* **Database Suite**: PDO connection, UTC session timezone, utf8mb4 charset, 100% InnoDB engine verification, and migration status.
* **Authentication Suite**: Missing header, malformed format, empty token, invalid JWT, unregistered UID handling (404), and registered user resolution.
* **Authorization Suite**: Role enforcement, IDOR ownership validation, inactive account denial, **Case C** (client role cannot override MySQL), and **Case D** (manager self-assignment guard).
* **Configuration Suite**: Required `.env` keys, BST/GMT daylight saving transitions, and Git secret exclusion.

> **Firebase Live Verification Boundary**:  
> Local foundation tests pass. Live verification using a real Firebase-issued ID token requires the configured Firebase project and credentials and is not claimed as fully verified unless actually executed.

---

## 19. SECURITY CONSIDERATIONS & PHASE BOUNDARIES

The security architecture strictly distinguishes between foundation controls implemented in Phase 3 and advanced security controls scheduled for subsequent phases:

### Foundation Controls Implemented in Phase 3
* **Zero Client Trust**: All client-supplied authorization fields (`role`, `status`, `is_manager`, `is_admin`) are stripped on arrival; application roles are derived solely from MySQL.
* **Server-Side Token Verification**: Tokens are validated cryptographically against Google's public certificates via the Firebase Admin SDK.
* **Prepared Statements**: 100% of SQL statements utilize PDO prepared statements with native parameter binding (`ATTR_EMULATE_PREPARES => false`).
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

## 20. LOCAL RUN INSTRUCTIONS

```bash
# 1. Verify preflight checks
php preflight_check.php

# 2. Run database migrations
php database/migrate.php

# 3. Provision local administrator account via CLI
php bin/create_manager.php --email admin@tutoringplatform.co.uk --uid LOCAL_ADMIN_UID --name "Platform Administrator"

# 4. Run automated test suite
php tests/run_tests.php

# 5. Access health check via HTTP
curl http://localhost/api/health.php
```

---

## 21. TROUBLESHOOTING

| Symptom | Probable Cause | Corrective Action |
| :--- | :--- | :--- |
| `Database connection failure` | MySQL service not running on port 3306. | Verify standalone MySQL daemon: `powershell -Command "Get-NetTCPConnection -LocalPort 3306"`. |
| `Composer detected issues: requires PHP >= 8.3` | Apache PHP 8.2 running packages resolved against PHP 8.3. | Pin platform in `composer.json`: `"config": {"platform": {"php": "8.2.12"}}` and run `composer update`. |
| `Missing or malformed Authorization header` | Request did not supply `Bearer <token>`. | Include header: `Authorization: Bearer <valid_firebase_id_token>`. |
| `USER_NOT_REGISTERED (404)` | Firebase user has authenticated but has no MySQL record. | Direct user to `/api/register/student.php` or `/api/register/tutor.php`. |

---

## 22. KNOWN LIMITATIONS

1. **Local Development Scope**: The current environment is running on local Windows 11 with standalone MySQL and Apache. Cloud infrastructure, Docker containerization, and production CI/CD pipelines belong to official Phase 13 (Deployment).
2. **Firebase Live Token Verification Limitation**: Local foundation tests pass (25/25, 100%). Live verification using a real Firebase-issued ID token requires the configured Firebase project and credentials and is not claimed as fully verified unless actually executed.
3. **Dual PHP Runtime Environment**: CLI scripts execute under PHP 8.3.33, while the Apache web server executes under PHP 8.2.12. Both runtimes have been actively verified through local testing, with Composer pinned to PHP 8.2.12.

---

## 23. OPEN CLIENT DECISIONS CARRIED FORWARD

In accordance with Phase 1 Discovery and Phase 2 Architecture, all 19 open client business decisions remain uncommitted:
1. Parent vs Direct Student Registration *(Architecture supports both via `children.parent_user_id` nullability)*.
2. Multiple Guardians per Child *(Architecture can support pivot table)*.
3. Lesson Delivery Mode *(Fields ready in schema)*.
4. Payment Model *(Deferred / Open Client Decision)*.
5. Authoritative Subject Taxonomy *(JSON fields ready)*.
6. Tutor Publishing Rules.
7. DBS Evidence Storage Scope.
8. DBS Data Retention Schedule.
9. Reschedule Timeout & Fallback.
10. Cancellation Notice Window.
11. Recurring Availability.
12. Lesson Notes Visibility Policy.
13. Newsletter Double Opt-In.
14. Newsletter Functional Scope.
15. Transactional Email Service Provider.
16. Future Payment Gateway Provider *(Deferred Scope)*.
17. Cookies & Analytics Services.
18. Approved Legal Document Text.
19. Backup RPO/RTO Targets.

---

## 24. EXTERNAL SOURCES

Refer to [docs/PHASE-03-EXTERNAL-SOURCES.md](file:///c:/Users/Dineshkumar%20M/OneDrive/Desktop/Client%20Apptutors/docs/PHASE-03-EXTERNAL-SOURCES.md) for full citations and implementation references for the Firebase Admin PHP SDK, PHP PDO, PHP Date/Time, MySQL 8.4, and Composer documentation.

---

## 25. PHASE 4 HANDOFF BLUEPRINT

Phase 3 establishes all necessary backend foundations for **Phase 4 — Public Website**:
* `public/index.php` front controller is active and ready to serve responsive marketing pages.
* `App\Database\Database` connection singleton is ready for database queries.
* `App\Support\Timezone` is available for lesson time formatting.
* `api/health.php` provides real-time infrastructure monitoring.
* Controlled registration endpoints (`/api/register/student.php` and `/api/register/tutor.php`) are in place.
