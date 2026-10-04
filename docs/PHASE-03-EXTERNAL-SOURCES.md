# PHASE 3 — OFFICIAL EXTERNAL TECHNICAL SOURCES

**Project**: UK Tutoring Platform — Production Foundation (Local Windows Development)  
**Document ID**: `DOC-PHASE-03-EXT-SOURCES`  
**Standard**: Authoritative, official documentation only. No random tutorials, blogs, or unverified community snippets.

---

## 1. OFFICIAL TECHNICAL SOURCES UTILIZED

| # | Source Name | Official URL | Technology / Topic | Why Needed in Phase 3 | Specific Implementation Detail Derived |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | **Firebase Admin PHP SDK** (Kreait) | [firebase-php.readthedocs.io](https://firebase-php.readthedocs.io/) | Firebase Authentication / Admin SDK | Required to integrate Firebase token verification into modern PHP backend. | • `Kreait\Firebase\Factory` initialization with service account credentials and project ID.<br>• `Kreait\Firebase\Contract\Auth::verifyIdToken($tokenString)` method signature and verified token claim extraction (`$verifiedToken->claims()->get('sub')`).<br>• Exception hierarchy (`FailedToVerifyToken`, `InvalidArgumentException`) for 401 response mapping. |
| **2** | **Firebase Authentication Official Docs** | [firebase.google.com/docs/auth](https://firebase.google.com/docs/auth) | Identity Management & JWT Lifecycle | Authoritative reference for Firebase ID token claims and security model. | • Confirmation of standard JWT claim names: `sub` (Firebase UID), `email`, `email_verified`, `iss`, `aud`.<br>• Architectural isolation rule: Firebase handles credential storage and authentication; application authorization and RBAC remain external in MySQL. |
| **3** | **PHP Official Documentation: PDO** | [php.net/manual/en/book.pdo.php](https://www.php.net/manual/en/book.pdo.php) | Database Persistence / PDO MySQL Driver | Authoritative guidance for database abstraction and prepared statement security. | • Disabling prepared statement emulation (`PDO::ATTR_EMULATE_PREPARES => false`) for true server-side parameterized queries.<br>• Exception error mode (`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`).<br>• Handling MySQL DDL implicit commit semantics where `CREATE TABLE` commits automatically and must not be wrapped in explicit transactions. |
| **4** | **PHP Official Documentation: Date/Time** | [php.net/manual/en/book.datetime.php](https://www.php.net/manual/en/book.datetime.php) | Timezone Normalization & DST Transitions | Handling UTC database persistence and dynamic UK local time transitions. | • Use of `DateTimeImmutable` and `DateTimeZone('Europe/London')` to dynamically compute GMT (UTC+0 in winter) and BST (UTC+1 in summer).<br>• Querying daylight saving flag via `format('I')` to detect BST active status. |
| **5** | **MySQL 8.4 Reference Manual** | [dev.mysql.com/doc/refman/8.4/en/](https://dev.mysql.com/doc/refman/8.4/en/) | Standalone Community Server / InnoDB Engine | Authoritative documentation for MySQL 8.4 LTS configuration, collations, and locking. | • Enforcing `utf8mb4` character set with `utf8mb4_unicode_ci` collation across all tables.<br>• Session and global timezone configuration (`SET time_zone = '+00:00'`).<br>• Foreign key constraints with `ON DELETE RESTRICT` and `ON DELETE CASCADE` cascading rules. |
| **6** | **Composer Official Documentation** | [getcomposer.org/doc/](https://getcomposer.org/doc/) | Dependency Management & Autoloading | Managing PSR-4 autoloading and cross-version platform configuration. | • Configuring `config.platform.php = "8.2.12"` to ensure seamless compatibility between PHP 8.3 CLI and PHP 8.2 Apache/XAMPP web handler without generating fatal `platform_check.php` version mismatches. |

---

## 2. AREAS NOT REQUIRING EXTERNAL SOURCES

The following core foundation subsystems were designed and implemented strictly from first principles using standard PHP 8 language features and the approved Phase 2 architecture:

1. **Server-Side Authorization Engine** (`src/Authorization/Authorization.php`): Built purely using native PHP type checks and class constants without external third-party authorization libraries.
2. **Environment Variable Loader** (`src/Support/Env.php`): Lightweight, robust `.env` file parser using native string parsing and `putenv()`, eliminating unnecessary external packages.
3. **Database Migration Runner** (`src/Database/Migration.php`): Purpose-built migration tracking system executing atomic SQL schema files and recording batches in `migrations`.
4. **Structured JSON Response Utility** (`src/Support/Response.php`): Native JSON formatting utility enforcing strict HTTP security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`).
5. **Rotating Log Engine & Secret Redactor** (`src/Logging/Logger.php`): Daily rotating flat-file logger with recursive sensitive key masking.
6. **Audit Service** (`src/Services/AuditService.php`): Direct PDO recording to the `audit_logs` table with SHA-256 IP address hashing as a privacy-preserving audit control. Overall UK GDPR compliance depends on the complete implementation, configuration, retention rules, legal basis, policies and approved legal requirements.
