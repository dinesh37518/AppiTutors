# Phase 6: External Technical Sources Register

**Project**: UK Tutoring Platform  
**Document**: Phase 6 External Sources Log  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 6 — Student / Parent Workflow Implementation  
**Status**: APPROVED BASELINE VERIFIED  

---

## 1. Compliance Rule

In strict compliance with the project specifications, external sources are permitted **only** when technical implementation details require official verification. External sources may clarify technical implementation, but they **must not** introduce new business requirements or alter architectural baselines.

Unapproved sources (random tutorials, Stack Overflow, competitor platforms, arbitrary GitHub repositories, or unofficial architecture blogs) are strictly prohibited.

---

## 2. Official Technical Sources Consulted

### Source 1: OWASP Cheat Sheet Series — Insecure Direct Object Reference (IDOR) & BOLA Prevention
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html)
* **Technical Topic Consulted**: Broken Object Level Authorization (BOLA) and Insecure Direct Object Reference (IDOR) mitigation strategies in relational database schemas.
* **Why It Was Needed**: To ensure that child record identifiers (`child_id`) and student profile user identifiers (`user_id`) submitted in HTTP requests are verified server-side against the authenticated actor's authoritative database identity prior to granting access or performing mutations.
* **Implementation Decision Informed**:
  - Implemented server-side ownership assertions in `App\Services\StudentParentService::getChild()`, `updateChild()`, and `deleteChild()`, verifying `parent_user_id === $currentUser->id`.
  - Configured error responses so that querying non-owned resources returns HTTP 403 `ForbiddenException` (`UNAUTHORIZED_RESOURCE_OWNERSHIP`) and querying non-existent resources returns safe HTTP 404 responses without disclosing record existence across tenant boundaries.

---

### Source 2: PHP Official Documentation — `DateTimeImmutable::createFromFormat` & Date Validation
* **Source Organization**: The PHP Group
* **Official URL**: [https://www.php.net/manual/en/datetimeimmutable.createfromformat.php](https://www.php.net/manual/en/datetimeimmutable.createfromformat.php)
* **Technical Topic Consulted**: Strict Gregorian calendar date parsing, format validation, and overflow handling.
* **Why It Was Needed**: To prevent malformed date inputs for dependent `date_of_birth` records (such as `2026-02-31` rolling over into March) and prevent future birthdates from being persisted.
* **Implementation Decision Informed**:
  - Added `App\Validation\Validator::validateDate(string $date, string $format = 'Y-m-d'): bool` to check that the formatted date matches the input string exactly.
  - Implemented date of birth boundary checks ensuring child birthdates are valid calendar dates and strictly less than or equal to `today`.

---

### Source 3: MySQL 8.4 Reference Manual — Foreign Key Constraints & Referential Integrity
* **Source Organization**: Oracle Corporation / MySQL Documentation
* **Official URL**: [https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html)
* **Technical Topic Consulted**: Foreign key constraint actions (`ON DELETE RESTRICT`), index requirements, and cascading behaviors on dependent child tables.
* **Why It Was Needed**: To evaluate deletion semantics on the `children` entity given foreign key constraints referencing `users(id)` and forthcoming Phase 7 references from `bookings(child_id)`.
* **Implementation Decision Informed**:
  - Utilized soft-deletion (`active = 0`) as the primary deletion mechanism in `StudentParentService::deleteChild()`. This guarantees that historical audit logs and upcoming booking transactional records retain integrity without triggering `FOREIGN KEY RESTRICT` violations.

---

### Source 4: Firebase Authentication Documentation — Verify ID Tokens (Admin SDK)
* **Source Organization**: Google Firebase Documentation
* **Official URL**: [https://firebase.google.com/docs/auth/adminverify-id-tokens](https://firebase.google.com/docs/auth/adminverify-id-tokens)
* **Technical Topic Consulted**: Decoupled cryptographic token verification vs application role authorization.
* **Why It Was Needed**: Reaffirming that Firebase Authentication acts exclusively as an identity provider (authenticating credentials and issuing cryptographically signed ID tokens), while MySQL retains 100% authoritative control over roles, status, and permissions.
* **Implementation Decision Informed**:
  - Client-supplied authorization fields (`role`, `status`, `is_manager`, `is_admin`) in student/parent registration and profile updates are strictly stripped and discarded.
  - Role assignment is governed entirely on the server side: registration assigns safe default role `STUDENT_PARENT` and status `ACTIVE`; self-assigned `MANAGER` authority is blocked with HTTP 403 `SELF_REGISTRATION_MANAGER_FORBIDDEN`.

---

## 3. Topics Where No External Source Was Needed

The following technical areas were fully covered by existing internal project code, Phase 1–5 baselines, and architectural documentation:
1. **CSRF Protection**: Reused existing `App\Support\Csrf` token generation and verification architecture.
2. **Database PDO Connectivity**: Reused existing `App\Database\Database` singleton connection pool configured with session timezone `UTC` (+00:00).
3. **Audit Logging**: Reused existing `App\Services\AuditService` immutable audit ledger writing to `audit_logs`.
4. **HTML Escaping & View Rendering**: Reused existing `App\Support\View::render` and global `e()` escaping helper.
