# Phase 10: External Technical Sources Register

**Project**: UK Tutoring Platform  
**Document**: Phase 10 External Sources Log  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 10 — Blog & Newsletter  
**Status**: APPROVED BASELINE VERIFIED  

---

## 1. Compliance Rule

In strict compliance with project specifications, external sources are permitted **only** when technical implementation details require official verification. External sources may clarify technical implementation, but they **must not** introduce new business requirements, alter architectural baselines, or weaken previously approved security boundaries.

Unapproved sources (random tutorials, Stack Overflow, competitor platforms, arbitrary GitHub repositories, or unofficial architecture blogs) are strictly prohibited.

---

## 2. Official Technical Sources Consulted

### Source 1: PHP Official Documentation — String Functions & Input Filtering
* **Source Organization**: The PHP Group
* **Official URL**: [https://www.php.net/manual/en/function.filter-var.php](https://www.php.net/manual/en/function.filter-var.php) and [https://www.php.net/manual/en/function.htmlspecialchars.php](https://www.php.net/manual/en/function.htmlspecialchars.php)
* **Technical Topic Consulted**: PHP `FILTER_VALIDATE_EMAIL` email validation via `filter_var(..., FILTER_VALIDATE_EMAIL)` and context-aware output encoding via `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
* **Why It Was Needed**: To ensure robust, server-side validation of newsletter subscriber emails and strict neutralization of Stored Cross-Site Scripting (XSS) vectors in manager- and tutor-authored blog titles, excerpts, slugs, and bodies.
* **Implementation Decision Informed**:
  - Validates subscriber email addresses using PHP `FILTER_VALIDATE_EMAIL` email validation, rejecting malformed, newline-injected, or oversized entries before database insertion.
  - Enforces `htmlspecialchars()` with `ENT_QUOTES` across all view templates rendering blog content and subscriber records (`nl2br(e($body))`).

---

### Source 2: MySQL 8.4 Reference Manual — ENUM Columns, Unique Indexes & Pagination
* **Source Organization**: Oracle Corporation / MySQL
* **Official URL**: [https://dev.mysql.com/doc/refman/8.4/en/enum.html](https://dev.mysql.com/doc/refman/8.4/en/enum.html) and [https://dev.mysql.com/doc/refman/8.4/en/constraint-primary-key.html](https://dev.mysql.com/doc/refman/8.4/en/constraint-primary-key.html)
* **Technical Topic Consulted**: MySQL `ENUM` storage validation, `UNIQUE` index collision handling (`ER_DUP_ENTRY` / SQLSTATE 23000), and deterministic pagination.
* **Why It Was Needed**: To leverage the existing `blog_posts` status ENUM (`'DRAFT', 'SUBMITTED', 'APPROVED', 'PUBLISHED', 'REJECTED', 'ARCHIVED'`) and `newsletter_subscribers` status ENUM (`'PENDING', 'ACTIVE', 'UNSUBSCRIBED', 'SUPPRESSED'`) without requiring database migrations.
* **Implementation Decision Informed**:
  - Enforced strict whitelist validation matching database ENUM values in `BlogService` and `NewsletterService`.
  - Reused `UNIQUE` constraints on `blog_posts.slug` and `newsletter_subscribers.email`, handling duplicates gracefully with HTTP 409 Conflict for duplicate slugs and safe idempotency for newsletter subscriptions.
  - Implemented parameterized two-pass pagination (`COUNT(*)` followed by `LIMIT ... OFFSET ...`) with secondary order keys (`ORDER BY bp.created_at DESC, bp.id DESC`).

---

### Source 3: OWASP Official Guidance — XSS Prevention & Access Control Cheat Sheets
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html) and [https://cheatsheetseries.owasp.org/cheatsheets/Access_Control_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Access_Control_Cheat_Sheet.html)
* **Technical Topic Consulted**: Defense-in-depth against Stored XSS in content management workflows, and server-side RBAC verification for administrative publishing endpoints.
* **Why It Was Needed**: Editorial interfaces can be prime targets for privilege escalation and content injection. Administrative actions (creating, updating, publishing, archiving posts) must be strictly isolated to active managers.
* **Implementation Decision Informed**:
  - Manager authentication and active status enforcement (`requireRole([ROLE_MANAGER])` and `requireActiveStatus()`) are executed server-side prior to any editorial or subscriber modification.
  - Client-supplied author IDs or role claims are ignored; author and reviewer IDs are resolved solely from the authenticated `UserContext`.
  - All public views render escaped content (`nl2br(e($body))`), ensuring untrusted HTML tags cannot execute in student, tutor, or public browser contexts.

---

### Source 4: OWASP Official Guidance — Cross-Site Request Forgery (CSRF) Prevention
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
* **Technical Topic Consulted**: Synchronizer token pattern, timing-safe token verification, and state-changing request segregation.
* **Why It Was Needed**: To protect state-changing manager operations (creating blog posts, publishing articles, archiving posts) against cross-site request forgery attacks.
* **Implementation Decision Informed**:
  - Integrated `Csrf::generateToken()` and timing-safe `Csrf::validateToken()` across all web forms submitting state changes in manager views.
  - GET requests are strictly read-only and never perform state mutations.

---

## 3. Topics Where No External Source Was Needed

The following technical areas were fully covered by existing internal project code and approved Phases 1–9 architecture:
1. **Authentication & Identity**: Reused `FirebaseTokenVerifier` and `UserContext` mapping from `users` table.
2. **Audit Logging**: Reused `AuditService` writing to `audit_logs` with automated PII redaction.
3. **Email Abstraction**: Reused Phase 8 provider-neutral `EmailService` without assuming any commercial email vendor.
4. **Timezone Handling**: Reused `App\Support\Timezone` for dual-timezone London (GMT/BST) and UTC conversions.
5. **Public Blog Display**: Reused existing Phase 4 public views and routes (`/blog.php` and `/blog-post.php`).
6. **Frontend Design Tokens**: Reused existing CSS variables and components in `public/assets/css/app.css`.
