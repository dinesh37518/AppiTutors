# Phase 6: Student & Parent Workflow Specification

**Project**: UK Tutoring Platform  
**Document**: Phase 6 Architecture & Workflow Specification  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Status**: APPROVED IMPLEMENTATION  
**Test Suite**: `tests/Phase6StudentParentTest.php` (45/45 PASS — 100%)  

---

## 1. Objectives

The primary objective of Phase 6 is to implement the end-to-end Student and Parent/Guardian workflows required by the Production Master Project Document v2.0 without implementing later-phase capabilities such as the booking engine, tutor search/directory, or automated emails.

Specifically, Phase 6 delivers:
1. **Student/Parent Identity & Registration**: Registration pathway mapping Firebase UIDs to MySQL application identities with strict `STUDENT_PARENT` role authority.
2. **Student Profile Management**: Viewing and updating student contact details (phone, UK postcode, display name) with strict ownership boundaries.
3. **Parent / Guardian Workflow**: Management of family account details and dependent educational profiles.
4. **Child Management & IDOR Protection**: Secure creation, retrieval, updating, and soft-deletion of dependent child profiles under UK curriculum standards, protected by server-side object-level access controls.
5. **Multiple Children Support**: Robust support for managing multiple siblings under a single parent account as defined in the approved database schema.
6. **Accessible User Interfaces**: Desktop and mobile responsive, keyboard-navigable interfaces for `/student-profile.php` and `/parent-children.php`.
7. **Strict Phase Boundaries**: Complete exclusion of booking creation, checkout, payment processing, tutor search/ranking, and transactional emails.

---

## 2. Role & Identity Architecture

Phase 6 strictly preserves the Phase 3 identity and access management architecture:

* **Firebase Authentication = Identity Provider**: Authenticates client credentials (email/password, OAuth) and issues cryptographically signed JSON Web Tokens (JWT).
* **MySQL Database = Application Authority**: The single source of truth for platform roles (`STUDENT_PARENT`, `TUTOR`, `MANAGER`), account status (`ACTIVE`, `PENDING`, `SUSPENDED`, `DELETED`), and resource ownership.

```
+-----------------------------------------------------------------------------------+
| CLIENT BROWSER                                                                    |
| 1. Sends Firebase Bearer Token or Session Cookie                                  |
| 2. Submits profile or child payload                                               |
+------------------------------------------+----------------------------------------+
                                           |
                                           v
+-----------------------------------------------------------------------------------+
| SERVER ARCHITECTURE (ZERO TRUST)                                                  |
| 1. FirebaseTokenVerifier extracts and cryptographically verifies Firebase ID token|
| 2. Resolves authoritative MySQL user record via firebase_uid                      |
| 3. Enforces account status (ACTIVE required)                                      |
| 4. Discards all client-supplied role/authority fields                             |
| 5. Verifies server-side resource ownership (parent_user_id === actor.id)           |
+-----------------------------------------------------------------------------------+
```

### Critical Security Boundaries
* **No Client Role Authority**: Client requests can never create or elevate roles. Client-supplied `role`, `status`, `is_manager`, or `is_admin` fields are unconditionally discarded.
* **Self-Registration Manager Forbidden**: Any attempt to submit a `role` of `MANAGER` during registration is intercepted and blocked with HTTP 403 `SELF_REGISTRATION_MANAGER_FORBIDDEN`.
* **Zero Trust Output**: All user context returned to the client is derived strictly from MySQL queries.

---

## 3. Student Workflow

A student acting independently or managing their personal tutoring details can:
1. **Access Profile Portal**: Navigate to `/student-profile.php` to review their current account state and contact details.
2. **View Own Profile**: Access personal email (managed by Firebase identity authority), display name, contact telephone, and UK postal code.
3. **Update Profile Fields**: Modify display name, telephone, and postcode through validated forms protected by CSRF tokens.
4. **Ownership Boundary**: Student A can never access, read, or modify Student B's profile. Any cross-tenant attempt is blocked server-side with HTTP 403 `UNAUTHORIZED_RESOURCE_OWNERSHIP`.

---

## 4. Parent / Guardian Workflow & Child Management

A parent or guardian managing educational support for dependents can:
1. **Access Children Portal**: Navigate to `/parent-children.php` to view registered dependents.
2. **Register a Child Profile**: Submit educational details for a child, including:
   - First Name (required, max 100 characters)
   - Last Name (optional, max 100 characters)
   - Date of Birth (optional, format `YYYY-MM-DD`, cannot be in the future)
   - School Academic Year (e.g., Reception, Year 1 to Year 13)
   - Curriculum Focus (e.g., National Curriculum, GCSE / IGCSE, A-Level, 11+ Entrance, IB, Scottish Highers)
3. **List Own Children**: View all active children associated with their parent account.
4. **Update Child Profile**: Modify academic year, curriculum syllabus, or name.
5. **Remove Child Profile**: Soft-delete a child profile (`active = 0`) to preserve referential integrity for future audit and booking records while removing the child from active management views.

### Child Ownership & IDOR Protection Model
Every child operation enforces relationship verification server-side:
```
Actor (UserContext)
  -> Check authenticated & active
  -> Check role === 'STUDENT_PARENT' (or 'MANAGER')
  -> Fetch child by child_id
  -> Assert child.parent_user_id === actor.id
  -> Perform operation
```
* **No Trust in Submitted IDs**: A parent cannot manipulate another family's child by changing the `child_id` parameter in a GET, PUT, or DELETE request.
* **Safe Error Responses**: Cross-parent queries throw `ForbiddenException` (HTTP 403 `UNAUTHORIZED_RESOURCE_OWNERSHIP`); non-existent child IDs return HTTP 404 `CHILD_NOT_FOUND` without leaking whether records exist across tenant boundaries.

---

## 5. Multiple Children & Multi-Guardian Boundary

### Multiple Children (Supported)
The existing database schema provides a 1-to-many relationship (`parent_user_id` index in `children` referencing `users(id)`). A single parent account can register and manage an unlimited number of sibling profiles under different school years and curriculum tracks.

### Multiple Guardians (Open Decision — DISC-032)
The existing database schema does not feature a many-to-many join entity (e.g., `guardian_children`) for joint parental custody or multi-guardian logins. In accordance with project instructions, **this architecture was not silently redesigned**. The limitation is formally recorded as an open client decision (DISC-032) in Section 14.

---

## 6. Database Entities & Schema Utilization

Phase 6 operates entirely within the approved Phase 3 baseline migrations. **Zero new database migrations were required.**

### 1. `users` Table
```sql
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `firebase_uid` VARCHAR(128) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `display_name` VARCHAR(150) NOT NULL,
    `role` ENUM('STUDENT_PARENT', 'TUTOR', 'MANAGER') NOT NULL,
    `status` ENUM('ACTIVE', 'PENDING', 'SUSPENDED', 'DELETED') NOT NULL DEFAULT 'PENDING',
    `email_verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. `student_profiles` Table
```sql
CREATE TABLE `student_profiles` (
    `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `phone` VARCHAR(40) NULL,
    `postcode` VARCHAR(20) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_student_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. `children` Table
```sql
CREATE TABLE `children` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_user_id` BIGINT UNSIGNED NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NULL,
    `date_of_birth` DATE NULL,
    `school_year` VARCHAR(50) NULL,
    `curriculum` VARCHAR(100) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_children_parent` (`parent_user_id`),
    CONSTRAINT `fk_children_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 7. Service Architecture: `StudentParentService`

All business logic, input validation, transaction management, and authorization checks are consolidated in `App\Services\StudentParentService`:

| Method | Parameters | Authorization / Guards | Description |
|---|---|---|---|
| `registerStudentParent()` | `array $data, ?UserContext $creator` | Blocks `MANAGER` self-assignment; sets role `STUDENT_PARENT`, status `ACTIVE` | Registers new student/parent and initializes profile |
| `getProfile()` | `int $userId, ?UserContext $currentUser` | Authenticated + Active; Role `STUDENT_PARENT` + Owner or `MANAGER` | Retrieves student/parent profile |
| `updateProfile()` | `int $userId, array $data, ?UserContext $currentUser` | Authenticated + Active; Role `STUDENT_PARENT` + Owner or `MANAGER` | Updates phone, postcode, and display name |
| `createChild()` | `int $parentUserId, array $data, ?UserContext $currentUser` | Authenticated + Active; Role `STUDENT_PARENT` + Owner or `MANAGER` | Creates a new dependent child record |
| `getChildren()` | `int $parentUserId, ?UserContext $currentUser, bool $onlyActive` | Authenticated + Active; Role `STUDENT_PARENT` + Owner or `MANAGER` | Lists all children belonging to parent |
| `getChild()` | `int $childId, ?UserContext $currentUser` | Authenticated + Active; Role `STUDENT_PARENT` + Child Owner or `MANAGER` | Retrieves a specific child profile |
| `updateChild()` | `int $childId, array $data, ?UserContext $currentUser` | Authenticated + Active; Role `STUDENT_PARENT` + Child Owner or `MANAGER` | Updates child educational details |
| `deleteChild()` | `int $childId, ?UserContext $currentUser, bool $softDelete` | Authenticated + Active; Role `STUDENT_PARENT` + Child Owner or `MANAGER` | Soft-deletes child profile (`active = 0`) |

---

## 8. HTTP Routes & API Endpoints

### Browser Web Routes
* `GET /student-profile.php`: Student & Parent Profile Management view.
* `POST /student-profile.php`: State-changing profile update handler (protected by CSRF token).
* `GET /parent-children.php`: Children & Dependents Dashboard view.
* `POST /parent-children.php`: State-changing child creation and removal handler (protected by CSRF token).

### REST API Endpoints
* `GET /api/student/profile.php`: Retrieves authenticated student/parent profile.
* `PUT /api/student/profile.php`: Updates authenticated student/parent profile.
* `GET /api/parent/children.php`: Lists children for current parent, or retrieves specific child via `?id=123`.
* `POST /api/parent/children.php`: Creates a child profile for the authenticated parent.
* `PUT /api/parent/children.php`: Updates a child profile owned by the authenticated parent.
* `DELETE /api/parent/children.php`: Soft-deletes a child profile owned by the authenticated parent.

---

## 9. Security Controls & Defensive Design

1. **SQL Injection Neutralization**: 100% of database interactions utilize PDO prepared statements with parameterized inputs. Zero string interpolation is permitted.
2. **Cross-Site Scripting (XSS) Prevention**: All view output is escaped via `App\Support\View::e()` and `e()` using `ENT_QUOTES | ENT_SUBSTITUTE` UTF-8 sanitization.
3. **Cross-Site Request Forgery (CSRF) Protection**: Browser form submissions require a valid, cryptographically generated CSRF token validated via `App\Support\Csrf::validateToken()`.
4. **Insecure Direct Object Reference (IDOR) Protection**: Child identifiers and user identifiers are never accepted without server-side verification against the authenticated actor's ID.
5. **Decoupled Identity Authority**: Client-supplied role and privilege escalations are discarded server-side.
6. **Audit Ledger Logging**: Sensitive state-changing actions are logged to `audit_logs` (`USER_REGISTERED_STUDENT`, `STUDENT_PROFILE_UPDATED`, `CHILD_CREATED`, `CHILD_UPDATED`, `CHILD_DELETED`). Passwords, JWTs, and credentials are automatically redacted.

---

## 10. Accessibility & Responsive Verification

Automated accessibility checks covering selected WCAG 2.2 AA-related requirements passed cleanly:
* **Skip Link**: `<a href="#main-content" class="skip-link">Skip to main content</a>` present and visible upon keyboard focus.
* **Semantic Landmarks**: `<main id="main-content">`, `<header role="banner">`, `<footer role="contentinfo">`, and `<nav aria-label="Breadcrumb">` implemented.
* **Heading Hierarchy**: Exactly one `<h1>` per page with strictly descending heading levels (`<h2>`, `<h3>`).
* **Explicit Form Labeling**: All inputs have explicitly bound `<label for="...">` elements with descriptive hint text (`aria-describedby`).
* **Visible Keyboard Focus**: 3px high-contrast outline focus states defined in `app.css`.
* **Responsive Layouts**: Fluid CSS Grid and Flexbox layouts accommodating desktop (1200px) down to mobile viewport widths (320px) without horizontal scrolling.

---

## 11. Test Traceability Matrix

The Phase 6 test suite (`tests/Phase6StudentParentTest.php`) executes 45 comprehensive test assertions:

| Section | Test Count | Scope | Result |
|---|---|---|---|
| **A. Authentication** | 3 | Unauthenticated blocked (401), Student/Parent permitted, Inactive blocked (403) | 3/3 PASS |
| **B. Role Authority** | 4 | Manager self-assignment blocked, Role mutation blocked, Tutor blocked, Manager oversight allowed | 4/4 PASS |
| **C. Student Profile** | 6 | Read own, update own, IDOR read blocked, IDOR update blocked, empty name rejected, overlong phone rejected | 6/6 PASS |
| **D. Parent Profile** | 2 | Parent B read own, Parent B update own | 2/2 PASS |
| **E. Child Management** | 12 | Create child, empty name blocked, future DOB blocked, invalid date blocked, IDOR create blocked, read child, update child, IDOR read blocked, IDOR update blocked, 404 for non-existent, soft-delete, omitted from active | 12/12 PASS |
| **F. Multiple Children** | 3 | Parent B registers multiple children, lists all owned siblings, Parent A cannot list Parent B children | 3/3 PASS |
| **G. Security & Validation**| 7 | SQLi neutralized, users table intact, XSS sanitization, View::e escaping, CSRF valid, CSRF forged blocked, audit log recorded | 7/7 PASS |
| **H. Web Routes & API** | 8 | student-profile 200 OK, parent-children 200 OK, semantic landmarks, form label associations, accessibility statement, API profile 401, API children 401, API method 405 | 8/8 PASS |
| **TOTAL** | **45** | **Comprehensive Phase 6 Functional & Security Coverage** | **45/45 PASS (100%)** |

---

## 12. Strict Phase Boundaries & Prohibited Functionality

In accordance with Section 25 of the project instructions, Phase 6 strictly observed all phase boundaries:
* **NO Booking Engine (Phase 7)**: No booking requests, status transitions, confirmed sessions, reschedule proposals, or lesson records were created.
* **NO Transactional Email Lifecycle (Phase 8)**: No welcome emails, confirmation notifications, or email dispatch queues were created.
* **NO Manager Admin Portal (Phase 9)**: No manager user management or platform administration interfaces were built.
* **NO Payment Gateways or Checkouts**: No payment methods, deposits, escrow, or card handling were implemented.
* **NO Tutor Directory Search**: No tutor ranking, search filters, or public matching directories were built.

---

## 13. Open Client Decisions Register

The following open client decisions are explicitly preserved as undecided business requirements:

1. **DISC-020 — Enhanced DBS Certificate Storage & Retention**: Policy for retaining physical DBS certificates vs redacting uploaded files after verification remains undecided.
2. **DISC-030 — Tutor Directory Publishing Toggle**: Whether approved tutors must explicitly toggle a public directory visibility setting before being displayed remains open.
3. **DISC-023 — Recurring Weekly Availability**: Whether tutors define recurring weekly schedules or discrete single-occurrence slots remains undecided.
4. **DEC-13 — Cancellation Notice Window**: The minimum cancellation notice period (e.g., 24h vs 48h) remains open.
5. **DEC-16 — Newsletter Double Opt-In**: Whether newsletter subscriptions require email verification before status becomes ACTIVE remains open.
6. **DISC-032 — Multi-Guardian Child Relationship Model**: Whether children can be linked to multiple guardians (e.g., joint parental custody) or remain strictly linked to a single registering parent (`parent_user_id`) remains an open business decision. The existing 1-to-many schema was preserved without unauthorized modifications.
