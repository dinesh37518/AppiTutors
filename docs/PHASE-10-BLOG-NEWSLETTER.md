# Phase 10: Blog & Newsletter Technical Specification & Architecture

**Project**: UK Tutoring Platform  
**Document**: Phase 10 Implementation Guide  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 10 — Blog & Newsletter  
**Status**: APPROVED IMPLEMENTATION BASELINE  

---

## 1. Executive Summary & Objective

Phase 10 implements the manager-controlled **Blog & Editorial Management** system and the public **Newsletter Subscription Workflow** for the UK Tutoring Platform. The implementation builds directly upon the existing architectural foundations established in Phases 1 through 9:

1. **Blog Scope**:
   - Comprehensive workflow: Tutor draft authoring -> submission to moderation queue -> Manager review/moderation -> Manager approval -> Manager publication.
   - Non-destructive status transitions using the existing MySQL `blog_posts` table schema (`DRAFT`, `SUBMITTED`, `APPROVED`, `PUBLISHED`, `REJECTED`, `ARCHIVED`).
   - Server-side ownership and RBAC enforcement: Tutors can only edit and submit their own drafts; cannot approve, publish, archive, or tamper with other tutors' posts. Managers review submitted posts, approve, reject with reason, publish approved posts, or archive.
   - Direct `DRAFT -> PUBLISHED` bypass strictly forbidden (HTTP 422).
   - Server-side visibility enforcement: public readers access strictly `status = 'PUBLISHED'` posts; draft, submitted, rejected, and archived articles return HTTP 404 to unauthorized users.
   - Complete XSS defense with context-aware output escaping (`e()`), URL slug sanitization, and parameterized query execution.

2. **Newsletter Scope**:
   - Public subscription processing with server-side PHP `FILTER_VALIDATE_EMAIL` email validation, string normalization, and explicit consent tracking.
   - Secure token-based unsubscribe workflow (`POST /api/newsletter/unsubscribe.php` and `/unsubscribe.php`) transitioning status to `UNSUBSCRIBED` and recording `unsubscribed_at` timestamp with zero raw-token logging.
   - Duplicate prevention leveraging MySQL `UNIQUE (email)` constraint without data corruption or duplicate rows.
   - **Double Opt-In Boundary Preservation**: New and reactivated subscribers are persisted in neutral `PENDING` status with `confirmed_at = NULL`. The decision regarding mandatory double opt-in verification remains an explicit **OPEN CLIENT DECISION** and has not been silently resolved.
   - Managerial audience administration allowing subscriber registry inspection, status filtering, and suppression management; manager status controls strictly prevent arbitrary `PENDING -> ACTIVE` transitions to preserve the open double opt-in decision.

3. **Database Changes**:
   - **NO DATABASE MIGRATION REQUIRED**: The existing migration `006_create_newsletter_and_blog_tables.sql` fully specifies all required tables (`blog_posts`, `newsletter_subscribers`), columns, indexes, foreign keys, and ENUM types.

4. **Phase Boundaries**:
   - **Commercial Email Provider**: NOT selected. Phase 8 provider-agnostic abstraction is preserved.
   - **Payment Gateway**: NOT implemented. Deferred to commercial setup.
   - **Lesson Notes**: NOT implemented.
   - **Rescheduling**: Complete rescheduling not implemented; `RESCHEDULE_PROPOSED` preserved; `RESCHEDULED` state NOT created.
   - **Phases 11–14**: NOT started.

---

## 2. System Architecture & Components

```
+----------------------------------------------------------------------------------------------------+
|                                    PUBLIC CLIENTS / BROWSERS                                       |
|  - GET /blog.php (Published list)                 - POST /newsletter.php (Form submission)        |
|  - GET /blog-post.php?slug=... (Published reader)  - POST /api/newsletter/subscribe.php (JSON)     |
+---------------------------------+----------------------------------+-------------------------------+
                                  |                                  |
                                  v                                  v
+----------------------------------------------------------------------------------------------------+
|                                  MANAGER ADMINISTRATION PORTAL                                     |
|  - GET /manager-blog.php (Library & editor)        - GET /manager-newsletter.php (Audience list)   |
|  - POST /manager-blog.php (Create/Edit/Pub/Arch)   - POST /manager-newsletter.php (Status controls)|
|  - /api/manager/blog.php                           - /api/manager/subscribers.php                  |
+---------------------------------+----------------------------------+-------------------------------+
                                  |
                                  v (Strict RBAC: users.role = 'MANAGER' AND status = 'ACTIVE')
+----------------------------------------------------------------------------------------------------+
|                                      APPLICATION SERVICE LAYER                                     |
|                                                                                                    |
|   +------------------------------------+             +---------------------------------------+     |
|   |         BlogService                |             |         NewsletterService             |     |
|   | - listPosts()                      |             | - subscribe()                         |     |
|   | - getPostBySlug() / getPostById()  |             | - listSubscribers()                   |     |
|   | - createPost() / updatePost()      |             | - updateSubscriberStatus()            |     |
|   | - publishPost() / archivePost()    |             | - getSubscriberStats()                |     |
|   | - getBlogStats()                   |             |                                       |     |
|   +-----------------+------------------+             +-------------------+-------------------+     |
|                     |                                                    |                         |
|                     v                                                    v                         |
|   +------------------------------------+             +---------------------------------------+     |
|   |         AuditService               |             |         EmailService (Phase 8)        |     |
|   | - log('BLOG_POST_CREATED', ...)    |             | - Provider-agnostic abstraction       |     |
|   | - Sensitive data redaction         |             | - Decoupled DB transaction boundaries |     |
|   +------------------------------------+             +---------------------------------------+     |
+----------------------------------------------------------------------------------------------------+
                                  |
                                  v (InnoDB, utf8mb4, Parameterized Prepared Statements)
+----------------------------------------------------------------------------------------------------+
|                                       MYSQL 8.4 DATABASE LAYER                                     |
|  - blog_posts (id, author_user_id, reviewed_by_user_id, title, slug, excerpt, body, status, ...)  |
|  - newsletter_subscribers (id, email, consent_at, confirmed_at, status, confirmation_token_hash...) |
|  - audit_logs (actor_user_id, action, entity_type, entity_id, metadata_json, created_at)          |
+----------------------------------------------------------------------------------------------------+
```

---

## 3. Blog Subsystem Workflow & Lifecycle

### 3.1 Status Model
The blog workflow utilizes the existing MySQL ENUM values in `blog_posts.status`:
* `DRAFT`: Initial state for newly authored posts (created by tutors or managers). Strictly invisible to public website readers.
* `SUBMITTED`: Post submitted by author (tutor) for managerial moderation. Enters the manager moderation queue.
* `APPROVED`: Candidate article reviewed and approved by a manager. Ready for scheduled or immediate publication.
* `PUBLISHED`: Publicly accessible article. Has non-null `published_at`, `reviewed_by_user_id`, and `reviewed_at`. Transitioned exclusively from `APPROVED` by a manager.
* `REJECTED`: Candidate post rejected during editorial review with feedback. Tutor can edit the rejected post, returning it to `DRAFT` for revision and re-submission.
* `ARCHIVED`: Withdrawn from public view by a manager without destructive data deletion. Historical records and URL slugs remain intact in the database.

### 3.2 Blog Authoring, Moderation & Validation
All authoring and editing requests are validated server-side by `BlogService`:
* **Tutor Authoring Workflow**:
  - Tutors can create new posts in `DRAFT` status (`POST /api/blog.php` with `action=create`). Direct creation in non-draft status by tutors is blocked (HTTP 403).
  - Tutors can edit their own `DRAFT` or `REJECTED` posts (`POST /api/blog.php` with `action=update`).
  - When a tutor edits a `REJECTED` post, it seamlessly transitions back to `DRAFT`, enabling iterative editorial revision.
  - Tutors can submit their own drafts for moderation (`POST /api/blog.php` with `action=submit`), transitioning `DRAFT -> SUBMITTED`.
  - **Server-Side Ownership Enforcement (BOLA / IDOR Defense)**: Tutors can only edit or submit posts where `author_user_id` matches their own authenticated user ID. Attempting to modify or submit another tutor's post is strictly blocked with HTTP 403 Forbidden.
  - **Tutor Privilege Boundaries**: Tutors cannot approve, publish, or archive any post (HTTP 403 Forbidden).
* **Manager Moderation Workflow**:
  - Managers inspect submitted articles awaiting review via `/manager-blog.php` and `GET /api/manager/blog.php?status=SUBMITTED`.
  - Managers can approve candidate posts (`POST /api/manager/blog.php` with `action=approve`), transitioning `SUBMITTED -> APPROVED` and recording `reviewed_by_user_id` and `reviewed_at`.
  - Managers can reject candidate posts (`POST /api/manager/blog.php` with `action=reject`), transitioning `SUBMITTED / APPROVED -> REJECTED` with an optional review reason.
  - Managers can publish approved posts (`POST /api/manager/blog.php` with `action=publish`), transitioning `APPROVED -> PUBLISHED` and stamping `published_at`.
  - **Direct Publication Blocked**: Attempting to transition `DRAFT -> PUBLISHED` directly is blocked (HTTP 422 `INVALID_STATUS_TRANSITION`) unless the post is in `APPROVED` status.
  - Managers can archive published or approved posts (`POST /api/manager/blog.php` with `action=archive`), transitioning `PUBLISHED / APPROVED -> ARCHIVED`.
* **Field Validation**:
  - **Title**: Required, trimmed string, 1 to 255 characters (`TITLE_REQUIRED`, `TITLE_TOO_LONG`).
  - **Slug**: Alphanumeric and hyphens only (`/^[a-z0-9]+(?:-[a-z0-9]+)*$/`), 1 to 255 characters (`INVALID_SLUG`, `SLUG_TOO_LONG`). Auto-generated from title if omitted via `slugify()`. Automatic collision resolution appends numeric suffixes (`-1`, `-2`). Custom colliding slugs return HTTP 409 Conflict (`SLUG_ALREADY_EXISTS`). Path traversal sequences (`../`) and illegal characters are stripped via `sanitizeSlug()`.
  - **Body**: Required, non-empty article text (`BODY_REQUIRED`).
  - **Excerpt**: Optional summary up to 500 characters.
  - **Author / Reviewer Identity**: Client-supplied author IDs or role flags are discarded. Author ID is resolved exclusively from the authenticated `UserContext`.

### 3.3 Public Visibility Controls
* **Server-Side Filtering**: `public/blog.php` and `BlogService::listPosts()` execute `WHERE bp.status = 'PUBLISHED'` for all public / unauthenticated requests.
* **Slug Lookup Guard**: `public/blog-post.php` queries `WHERE bp.slug = :slug AND bp.status = 'PUBLISHED'`. If a user requests a slug corresponding to an unpublished post (`DRAFT`, `SUBMITTED`, `REJECTED`, `ARCHIVED`) without active manager authorization, an HTTP 404 Not Found error is returned.
* **Tutor Ownership Protection**: When accessing post details via `getPostById()`, tutors may view their own unpublished posts, but attempting to view another author's unpublished post is blocked with HTTP 403 Forbidden.
* **No Reliance on Client-Side Hiding**: Public pages do not render hidden links or rely on JavaScript filtering to hide unpublished posts.

### 3.4 Content Security & XSS Prevention
* Blog titles, excerpts, and body text are stored cleanly and escaped context-sensitively upon display using `e()` (`htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`).
* The single article reader view (`src/Views/blog-post.php`) renders formatted article content with `nl2br(e($post['body']))`.
* The application intentionally treats blog content as plain text with line breaks; no raw unescaped HTML from user inputs is sent to client browsers.

---

## 4. Newsletter Subsystem Workflow & Boundaries

### 4.1 Subscription Processing
Public users subscribe via `/newsletter.php` or `/api/newsletter/subscribe.php`:
* **Email Validation**: Validated server-side using PHP `FILTER_VALIDATE_EMAIL` email validation via `Validator::validateEmail()` / `filter_var()`. Empty, malformed, or oversized (>255 chars) emails trigger HTTP 422 (`INVALID_EMAIL`, `EMAIL_TOO_LONG`).
* **Explicit Consent**: Explicit checkbox confirmation is required. Missing consent triggers HTTP 422 (`CONSENT_REQUIRED`).
* **Normalization**: Emails are converted to lowercase and trimmed before lookup or insertion.

### 4.2 Neutral PENDING State & Open Double Opt-In Decision
In strict adherence to project scope:
* All new subscribers are inserted with `status = 'PENDING'` and `confirmed_at = NULL`.
* Cryptographic 64-character hex token hashes (`confirmation_token_hash`, `unsubscribe_token_hash`) are provisioned via `hash('sha256', bin2hex(random_bytes(32)))` for forward-compatible technical readiness.
* **No Automatic Promotion**: Subscribers are **never** automatically promoted to `ACTIVE` without formal client approval.
* **Open Decision Documented**: The user interface and technical documentation prominently display:
  > **OPEN CLIENT DECISION — Double Opt-In Email Workflow**: Client commercial and legal policy regarding double opt-in confirmation remains an open decision. Records are maintained in a neutral state.

### 4.3 Secure Token-Based Unsubscribe Workflow
* **Public Unsubscribe Endpoint**: Available via `POST /api/newsletter/unsubscribe.php` and web controller `/unsubscribe.php` (rendering accessible view `src/Views/unsubscribe.php`).
* **Cryptographic Token Verification**: Unsubscribe requests require a raw 64-character hex token (`token`), which is verified by computing `hash('sha256', $rawToken)` and querying against `newsletter_subscribers.unsubscribe_token_hash`.
* **State Transition**: Valid tokens transition the subscriber to `status = 'UNSUBSCRIBED'` and record `unsubscribed_at = nowUtc()`.
* **Security & Privacy**:
  - Raw tokens are never logged in application logs or audit metadata.
  - Subscriber IDs are never accepted as the sole authorization mechanism for unsubscribe operations.
  - Invalid, empty, or malformed tokens return HTTP 404 / 422 without exposing subscriber data.
  - Operation is idempotent: repeating an unsubscribe for an already-unsubscribed subscriber returns success without creating duplicate rows or erroring.

### 4.4 Duplicate Handling & Idempotency
* The database enforces `UNIQUE (email)` on `newsletter_subscribers`.
* Re-submitting an existing email does not throw database errors or create duplicate records.
* If an existing subscriber has `status = 'UNSUBSCRIBED'`, re-submitting updates `consent_at` and transitions status back to `PENDING` with `unsubscribed_at = NULL`, maintaining the open double opt-in scope.
* If an existing subscriber is already `PENDING` or `ACTIVE`, the neutral acknowledgment is returned safely.

### 4.5 Manager Administration & Status Controls
Manager users can access the audience registry via `/manager-newsletter.php` and `/api/manager/subscribers.php`:
* Paginated listing with status filtering (`ALL`, `PENDING`, `ACTIVE`, `UNSUBSCRIBED`, `SUPPRESSED`) and email search.
* Aggregate audience statistics (Total, Pending, Active, Unsubscribed, Suppressed) calculated without financial assumptions.
* **Double Opt-In Boundary Enforcement**: Manager status controls in `NewsletterService::updateSubscriberStatus` and `/api/manager/subscribers.php` strictly prohibit arbitrary transitions from `PENDING -> ACTIVE` (returns HTTP 422 with code `DOUBLE_OPT_IN_OPEN_DECISION`). Managers can manage list hygiene via `SUPPRESSED` and `UNSUBSCRIBED`.
* **Commercial Email Boundary**: No commercial marketing automation, bulk email dispatchers, or third-party mailing list SDKs are integrated.

---

## 5. Security Controls & Governance

| Threat / Vulnerability | Engineering Mitigation | Verification |
| :--- | :--- | :--- |
| **Privilege Escalation** | `requireRole([ROLE_MANAGER])` and `requireActiveStatus()` verified server-side on all admin endpoints. Client claims ignored. | `Phase10BlogNewsletterTest::testBlogAuthenticationAndAuthorization` (Tutor, Student, Suspended Mgr denied with 403). |
| **Stored Cross-Site Scripting (XSS)** | All user/manager inputs escaped at view rendering using `htmlspecialchars()` with `ENT_QUOTES \| ENT_SUBSTITUTE`. Body rendered via `nl2br(e($body))`. | `Phase10BlogNewsletterTest::testBlogSecurityControls` verifies `<script>` tags neutralized. |
| **SQL Injection (SQLi)** | 100% prepared PDO statements with parameterized query placeholders. Sort columns checked against strict whitelist arrays. | Parameterized search filter tested with `' UNION SELECT ... -- `. Whitelist rejects invalid sort fields with 422. |
| **Cross-Site Request Forgery (CSRF)** | Synchronizer token generated via `Csrf::generateToken()` and verified using `Csrf::validateToken()` on all state-changing POST requests. | `Phase10BlogNewsletterTest::testBlogSecurityControls` verifies forged token rejection. |
| **Insecure Direct Object Reference (IDOR / BOLA)** | Non-manager requests cannot fetch draft/archived articles by slug or ID. Missing or unowned resources return 404. | Tested draft slug access returning 404 for public users. |
| **Path Traversal / Malformed Slugs** | Regex slug sanitization strips `/`, `..`, spaces, and special characters. | Adversarial slug `../../etc/passwd` sanitized to clean URL slug. |
| **Audit Logging & Terminology** | Key lifecycle events (`BLOG_POST_CREATED`, `BLOG_POST_UPDATED`, `BLOG_POST_PUBLISHED`, `BLOG_POST_ARCHIVED`, `NEWSLETTER_SUBSCRIBER_STATUS_UPDATED`) logged with redacted metadata. | Described accurately as "Audit records protected by server-side authorization and controlled application access" (not immutable). |

---

## 6. Email Architecture Resilience

In accordance with Phase 8 architecture, the platform maintains a provider-neutral email abstraction:
1. **No Commercial Provider**: No third-party transactional or marketing email vendors (SendGrid, Mailgun, Postmark, AWS SES) are hardcoded or selected.
2. **Local / In-Memory Transport**: During testing and local development, `ArrayEmailAdapter` or `LogEmailAdapter` is utilized.
3. **Database Transaction Decoupling**: Database state changes are committed independently of email dispatch. Any downstream external email failure does not roll back committed database records.

---

## 7. Automated Test Coverage & Verification

Phase 10 includes a dedicated automated test suite: `tests/Phase10BlogNewsletterTest.php`.

### Test Summary
* **Test Count**: 83 / 83 tests passed (100%).
* **Failures**: 0.
* **Test Groups**:
  1. Blog Authentication & RBAC Authorization (6 tests)
  2. Blog CRUD Lifecycle & Transitions (5 tests)
  3. Blog Validation & Slug Handling (5 tests)
  4. Blog Publication Visibility Rules (4 tests)
  5. Blog Security Controls: XSS, SQLi, CSRF, IDOR (7 tests)
  6. Blog Audit Logging & Sensitive Data Protection (5 tests)
  7. Newsletter Subscription Workflow (3 tests)
  8. Newsletter Duplicate Handling (2 tests)
  9. Newsletter PENDING Status & Open Double Opt-In Boundary (3 tests)
  10. Newsletter Manager Administration & Statistics (5 tests)
  11. Email Architecture Resilience (2 tests)
  12. HTTP Endpoints & Accessibility (10 tests)
  13. Blog Moderation Workflow & Authorization (Tutor Draft -> Submit, Manager Queue -> Approve/Reject -> Publish, IDOR/BOLA) (15 tests)
  14. Newsletter Unsubscribe Workflow (SHA-256 Token Verification, Idempotency, No Secret Logging) (10 tests)

### Regression Test Suite Verification
All existing test suites were executed sequentially with zero regressions:
* Phase 3 Foundation: 25 / 25
* Phase 4 Public Website: 30 / 30
* Phase 5 Tutor Workflow: 51 / 51
* Phase 6 Student / Parent Workflow: 45 / 45
* Phase 7 Booking Engine: 40 / 40
* Phase 8 Email Integration: 33 / 33
* Phase 9 Manager Administration: 39 / 39
* Phase 10 Blog & Newsletter: 83 / 83
* **Cumulative Total: 346 / 346 tests passing (100%)**

---

## 8. Open Client Decisions & Phase Boundaries

The following items are strictly preserved as open decisions or deferred scope:
1. **Newsletter Double Opt-In**: Commercial/legal decision on mandatory confirmation tokens remains OPEN. All subscribers remain in neutral `PENDING` status.
2. **Commercial Email Provider**: Unselected; platform maintains provider-neutral abstraction.
3. **Payment Provider & Gateway**: NOT implemented; no payment SDKs, payout processors, or checkout forms.
4. **Lesson Notes**: NOT implemented.
5. **Rescheduling**: Complete rescheduling NOT implemented; `RESCHEDULE_PROPOSED` preserved; `RESCHEDULED` state NOT created.
6. **Cancellation & Rescheduling Commercial Policies**: Unresolved client commercial decisions.
7. **Phases 11–14**: Security hardening (Phase 11), QA/UAT (Phase 12), Deployment (Phase 13), and Handover (Phase 14) are NOT started.
