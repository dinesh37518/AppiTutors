# Phase 9: External Technical Sources Register

**Project**: UK Tutoring Platform  
**Document**: Phase 9 External Sources Log  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 9 — Manager Administration Dashboard  
**Status**: APPROVED BASELINE VERIFIED  

---

## 1. Compliance Rule

In strict compliance with project specifications, external sources are permitted **only** when technical implementation details require official verification. External sources may clarify technical implementation, but they **must not** introduce new business requirements, alter architectural baselines, or weaken previously approved security boundaries.

Unapproved sources (random tutorials, Stack Overflow, competitor platforms, arbitrary GitHub repositories, or unofficial architecture blogs) are strictly prohibited.

---

## 2. Official Technical Sources Consulted

### Source 1: PHP Official Documentation — `PDO::prepare` & Parameterized Queries
* **Source Organization**: The PHP Group
* **Official URL**: [https://www.php.net/manual/en/pdo.prepare.php](https://www.php.net/manual/en/pdo.prepare.php)
* **Technical Topic Consulted**: Parameterized query construction, typed parameter binding (`PDO::PARAM_INT`, `PDO::PARAM_STR`), and SQL injection mitigation in administrative search/filter endpoints.
* **Why It Was Needed**: To ensure that all dynamic search terms, filters, pagination limits, and sorting criteria implemented in `ManagerService` are immune to SQL injection and never concatenate raw user input into SQL statements.
* **Implementation Decision Informed**:
  - All dynamic queries in `ManagerService` utilize parameterized placeholders (`?` or named parameters) for search keywords, status filters, and ID lookups.
  - Sorting parameters (`$sortBy`) are strictly validated against hardcoded allowlists per domain entity before being bound to SQL query strings.
  - Pagination limits and offsets are cast to positive integers via `max(1, (int)$page)` and `min(100, max(1, (int)$perPage))`.

---

### Source 2: MySQL 8.4 Reference Manual — Pagination & Aggregate Counting
* **Source Organization**: Oracle Corporation / MySQL
* **Official URL**: [https://dev.mysql.com/doc/refman/8.4/en/select.html](https://dev.mysql.com/doc/refman/8.4/en/select.html)
* **Technical Topic Consulted**: `COUNT(*)` aggregate performance, `LIMIT` with `OFFSET` syntax, and deterministic index ordering.
* **Why It Was Needed**: To implement high-performance, consistent pagination across manager directories (tutors, students, bookings, audit logs) without table-scanning bottlenecks or inconsistent page reads.
* **Implementation Decision Informed**:
  - Implemented two-pass pagination in `ManagerService`: a count query (`SELECT COUNT(*)`) followed by a slice query (`LIMIT :limit OFFSET :offset`).
  - Required deterministic secondary sort order (e.g., `ORDER BY tp.created_at DESC, tp.id DESC`) to prevent pagination drift when records have identical timestamps.

---

### Source 3: OWASP Official Guidance — Access Control & Administrative Interface Protection
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://cheatsheetseries.owasp.org/cheatsheets/Access_Control_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Access_Control_Cheat_Sheet.html)
* **Technical Topic Consulted**: Role-Based Access Control (RBAC), administrative endpoint segregation, and Insecure Direct Object References (IDOR/BOLA) in administrative panels.
* **Why It Was Needed**: Administrative endpoints provide powerful capabilities (approving/suspending tutors, inspecting children relationships, reviewing audit trails). Robust, centralized access-control enforcement is mandatory on every request.
* **Implementation Decision Informed**:
  - Enforced `Authorization::requireRole($manager, [Authorization::ROLE_MANAGER])` and `Authorization::requireActiveStatus($manager)` as the first operational line of code in every `ManagerService` method and manager API endpoint.
  - Completely disassociated client-supplied role parameters from server-side authority: only the verified MySQL database role associated with the authenticated user context is respected.
  - Manager endpoints return HTTP 401 when unauthenticated and HTTP 403 when authenticated as non-manager roles (`TUTOR`, `STUDENT_PARENT`).

---

### Source 4: OWASP Official Guidance — Sensitive Data Exposure & PII Masking
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
* **Technical Topic Consulted**: Data sanitization in administrative log views, redaction of sensitive credentials, and personal data protection.
* **Why It Was Needed**: While managers require visibility into system audit logs and DBS compliance statuses, administrative interfaces must never display passwords, session tokens, Firebase private keys, or unmasked credentials.
* **Implementation Decision Informed**:
  - Sanitized and redacted metadata displayed in `ManagerService::listAuditLogs()`.
  - Sensitive keys (`password`, `token`, `secret`, `private_key`, `api_key`, `credentials`) are stripped or masked before being presented in dashboard views.
  - DBS certificate numbers are presented in safe, administrative review format without exposing raw storage tokens or sensitive private documents to unauthorized actors.

---

## 3. Topics Where No External Source Was Needed

The following technical areas were fully covered by existing internal project code and Phase 1–8 approved architecture:
1. **Authentication Flow**: Reused existing `FirebaseTokenVerifier` and `UserContext` mapping from `users` table.
2. **Authorization Rules**: Reused existing `App\Authorization\Authorization` class.
3. **Audit Logging**: Reused existing `App\Services\AuditService` ledger writing to `audit_logs`.
4. **Tutor Lifecycle**: Reused existing `TutorService` approval, rejection, and suspension methods to ensure the bookability gate (`ACTIVE + APPROVED + VERIFIED`) is preserved.
5. **Booking Lifecycle**: Reused existing 7 approved booking states (`PENDING`, `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`, `COMPLETED`).
6. **Timezone Conversion**: Reused existing `App\Support\Timezone` for UTC-to-London display formatting.
7. **Frontend Design Tokens**: Reused existing `public/assets/css/app.css` classes, CSS variables, and layout conventions.
