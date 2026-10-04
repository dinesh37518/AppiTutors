# Phase 9: Manager Administration Dashboard Specification & Implementation Guide

**Project**: UK Tutoring Platform  
**Document**: Phase 9 Architecture & Administration Guide  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 9 — Manager Administration Dashboard  
**Status**: APPROVED & VERIFIED  

---

## 1. Executive Summary & Authoritative Purpose

Phase 9 implements the comprehensive, secure, and responsive **Manager Administration Dashboard** for the UK Tutoring Platform. The administration system provides operational oversight and governance across all foundational workflows established in Phases 1 through 8:

1. **Platform Operational KPIs**: Live platform aggregate metrics for tutors, bookings, DBS safeguarding compliance, availability capacity, and recent audit activity.
2. **Tutor Administration & Bookability Enforcement**: Full lifecycle management of tutors (approvals, rejections, suspensions, and reinstatements) with strict enforcement of the platform bookability gate (`users.status = 'ACTIVE' AND tutor_profiles.approval_status = 'APPROVED' AND tutor_profiles.dbs_status = 'VERIFIED'`).
3. **Student & Parent Governance**: Directory of registered guardians/students with family child dependent counts and booking history.
4. **Safeguarding & Child Directory**: Cross-platform visibility into registered children, curriculum levels, and guardian relationships.
5. **Booking Administration**: Centralized ledger of all platform bookings with status timeline tracking strictly conforming to the 7 Master Document states (with confirmed absence of any invalid `RESCHEDULED` state).
6. **Availability Overview**: Cross-tutor schedule inspection displaying both UTC storage timestamps and dynamic Europe/London (GMT/BST) times.
7. **Enhanced DBS Verification Queue**: Dedicated safeguarding queue enabling managers to inspect submissions, verify credentials, or reject non-compliant disclosures, strictly reusing the existing Phase 5 DBS model.
8. **Audit Trail & PII Redaction**: Audit records protected by server-side authorization and controlled application access, with automated masking of credentials, tokens, and sensitive metadata.
9. **Factual Operational Reports**: Objective compliance and distribution summaries without assuming any third-party payment gateway or commercial email provider.

---

## 2. Security Architecture & Role Authority

### 2.1 Server-Side Independent MySQL Role Enforcement
In accordance with OWASP Access Control standards and Phase 2 Architecture:
* **Sole Authority**: The user's role is strictly resolved from the authoritative MySQL `users` table via `UserContext`. Client-supplied roles, query parameters, or JWT custom claims are never trusted for privilege elevation.
* **Strict Role Guard**: Every manager endpoint and service method executes `Authorization::requireRole($user, [Authorization::ROLE_MANAGER])` and `Authorization::requireActiveStatus($user)`.
* **Zero Self-Assignment**: Registration endpoints for tutors and student/parents enforce rigid whitelists. Any client payload attempting to specify `role = 'MANAGER'` is discarded by MySQL defaults and server-side validation.
* **Error Semantics**: Unauthenticated requests receive HTTP 401 (`UNAUTHENTICATED`). Authenticated users possessing non-manager roles (`TUTOR`, `STUDENT_PARENT`) or inactive account statuses (`PENDING`, `SUSPENDED`, `DELETED`) receive HTTP 403 (`INSUFFICIENT_ROLE_PERMISSIONS` or `ACCOUNT_NOT_ACTIVE`).

### 2.2 Bookability Safeguarding Gate Integration & Reinstatement
The platform strictly maintains child safeguarding by enforcing that a tutor cannot be booked nor publish bookable slots unless they satisfy all three conditions:
$$\text{Bookable} \iff (\text{users.status} = \text{'ACTIVE'}) \land (\text{tutor\_profiles.approval\_status} = \text{'APPROVED'}) \land (\text{tutor\_profiles.dbs\_status} = \text{'VERIFIED'})$$

* **Manager Suspension**:
  1. `users.status` transitions to `SUSPENDED`.
  2. `tutor_profiles.approval_status` transitions to `SUSPENDED`.
  3. The tutor's `is_bookable` property immediately becomes `false`.
  4. The action is logged to `audit_logs` with the manager's user ID and audit reason.

* **Manager Reinstatement**:
  1. `users.status` transitions back to `ACTIVE`.
  2. `tutor_profiles.approval_status` transitions back to `APPROVED`.
  3. **Strict Gate Preservation**: Reinstatement alone does NOT confer bookability. Bookability continues to depend strictly on the complete three-part gate. If `dbs_status` is not `VERIFIED` or if any other condition is unmet, the tutor remains strictly non-bookable (`is_bookable = false`). Only when all three conditions (`ACTIVE`, `APPROVED`, `VERIFIED`) are present does `is_bookable` evaluate to `true`.
  4. The reinstatement event is recorded in `audit_logs`.

---

## 3. Core Service Layer Architecture (`App\Services\ManagerService`)

The `App\Services\ManagerService` class acts as the centralized orchestrator for managerial operations, delegating to specialized services (`TutorService`, `BookingService`, `DbsService`, `AvailabilityService`, `StudentParentService`, `AuditService`) where appropriate while executing high-performance direct SQL aggregations.

### Service Methods

| Method | Parameters | Description |
| :--- | :--- | :--- |
| `getDashboardKpis(?UserContext $m)` | Manager Context | Calculates live counts for tutors, bookings, DBS compliance, slots, and recent audit activity. |
| `listTutors(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Paginated list of registered tutors with bookability status, total slots, and booking counts. |
| `getTutorDetails(int $id, ?UserContext $m)` | Tutor User ID, Manager | Detailed profile for a tutor, including qualifications, subjects, recent bookings, and audit history. |
| `listStudents(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Paginated list of student/parent accounts with dependent children counts and contact details. |
| `getStudentDetails(int $id, ?UserContext $m)` | Student User ID, Manager | Full guardian profile, list of associated family children, and recent lesson booking inquiries. |
| `listChildren(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Platform-wide child dependent records with guardian names and curriculum stages. |
| `listBookings(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Global bookings ledger with tutor, student, child, and UTC/London timestamp details. |
| `getBookingDetails(int $id, ?UserContext $m)` | Booking ID, Manager | Detailed booking view with child information and status history trail. |
| `listAvailability(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Availability schedule across all tutors, linking booked slots to their respective Booking IDs. |
| `listDbsApplications(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Queue of tutor DBS submissions with masked certificate previews for verification. |
| `listAuditLogs(?UserContext $m, array $f, int $p, int $pp, string $s, string $d)` | Manager, Filters, Page, PerPage, SortBy, SortDir | Audit records protected by server-side authorization and controlled application access, with actor attribution and redacted metadata. |
| `getReports(?UserContext $m)` | Manager Context | Factual operational breakdowns for tutor approvals, safeguarding compliance, and bookings. |

---

## 4. API Endpoints Specification (`/api/manager/`)

All manager API endpoints require an active `Authorization: Bearer <token>` header resolving to a user with role `MANAGER` and status `ACTIVE`. All endpoints return standard JSON responses via `App\Support\Response`.

### 4.1 Endpoint Catalog

| Endpoint | Method | Purpose | Key Parameters |
| :--- | :--- | :--- | :--- |
| `/api/manager/dashboard.php` | `GET` | Retrieve live platform KPI metric cards | None |
| `/api/manager/tutors.php` | `GET` | List or view detailed tutor profiles | `id`, `approval_status`, `dbs_status`, `search`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/tutors.php` | `POST` | Execute tutor lifecycle action | `tutor_user_id`, `action` (`APPROVE`, `REJECT`, `SUSPEND`, `REINSTATE`), `reason` |
| `/api/manager/students.php` | `GET` | List or view student/parent accounts | `id`, `search`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/children.php` | `GET` | List platform child dependents | `school_year`, `search`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/bookings.php` | `GET` | List or view bookings and history | `id`, `status`, `tutor_id`, `student_id`, `search`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/availability.php` | `GET` | List availability slots across tutors | `tutor_id`, `status`, `from_date`, `to_date`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/dbs.php` | `GET` | List DBS verification queue | `dbs_status`, `search`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/dbs.php` | `POST` | Record DBS verification decision | `tutor_user_id`, `decision` (`VERIFY`, `REJECT`), `notes` |
| `/api/manager/audit.php` | `GET` | Query protected audit trail | `action`, `entity_type`, `actor_user_id`, `search`, `page`, `per_page`, `sort_by`, `sort_dir` |
| `/api/manager/reports.php` | `GET` | Retrieve operational summaries | None |

### 4.2 Standard Error Codes
* `401 Unauthorized`: `UNAUTHENTICATED` (missing or invalid Bearer token).
* `403 Forbidden`: `INSUFFICIENT_ROLE_PERMISSIONS` (non-manager user) or `ACCOUNT_NOT_ACTIVE` (suspended/pending manager).
* `404 Not Found`: `TUTOR_NOT_FOUND`, `STUDENT_NOT_FOUND`, `BOOKING_NOT_FOUND`.
* `405 Method Not Allowed`: `METHOD_NOT_ALLOWED`.
* `422 Unprocessable Entity`: `INVALID_PAGINATION`, `INVALID_SORT_FIELD`, `INVALID_SORT_DIRECTION`, `INVALID_ACTION`.

---

## 5. Web Views & User Interface Architecture

The Manager Administration interface uses the platform's Vanilla CSS token system (`public/assets/css/app.css`) and responsive layout components.

### 5.1 Shared Manager Navigation (`src/Views/manager-nav.php`)
A consistent sub-navigation bar is included across all managerial views, providing active route highlighting, role badge indication, and accessible links:
* **Dashboard**: `/manager-dashboard.php`
* **Tutors**: `/manager-tutors.php`
* **Students & Parents**: `/manager-students.php`
* **Bookings**: `/manager-bookings.php`
* **Availability**: `/manager-availability.php`
* **Audit Ledger**: `/manager-audit.php`
* **Reports**: `/manager-reports.php`

### 5.2 Public Web Routes Catalog
1. **`/manager-dashboard.php`**: Overview dashboard featuring 5 KPI summary metric cards (Tutors pipeline, Bookings breakdown, Enhanced DBS compliance, Availability capacity, Recent audit actions) and quick action shortcuts.
2. **`/manager-tutors.php`**: Tutor directory and approval desk. Displays bookability badges, subject specialisms, DBS statuses, and quick action modals for approval, suspension, and reinstatement.
3. **`/manager-students.php`**: Guardian registry with dependent child counts, contact details, and links to family profiles.
4. **`/manager-bookings.php`**: Master booking ledger with status badges, London lesson times, tutor/guardian names, and modal history views.
5. **`/manager-availability.php`**: Schedule overview presenting dual-timezone timestamps (London GMT/BST with explicit UTC storage times) and direct links to booked sessions.
6. **`/manager-audit.php`**: Security ledger displaying actor attribution, target entities, timestamps, and redacted metadata payloads.
7. **`/manager-reports.php`**: Visual breakdown cards detailing approval distributions, DBS compliance percentages, and booking lifecycle breakdowns.

---

## 6. Safeguarding, DBS & PII Protection

### 6.1 Reuse of Phase 5 DBS Model
Manager DBS verification actions (`VERIFY` and `REJECT`) strictly reuse the existing Phase 5 `DbsService::managerReviewDbs()` method:
* **No New Lifecycle**: Statuses remain strictly `NOT_SUBMITTED`, `SUBMITTED`, `VERIFIED`, `REJECTED`, and `EXPIRED` on `tutor_profiles.dbs_status`.
* **No Retention Policy Changes**: DBS documents remain in `storage/private/dbs/` with metadata marked `'retention_policy_status' => 'CLIENT_DECISION_OPEN'`.
* **No Automatic Deletion**: No automatic file deletion or purge routines have been introduced.
* **Open Decision Maintained**: The DBS retention/deletion policy remains explicitly open for client determination.

### 6.2 Audit Trail & PII Redaction
* **Protection Model**: Audit records are protected by server-side authorization and controlled application access. Cryptographic or database-level immutability is not claimed.
* **Storage Isolation**: DBS verification documents are stored strictly in `storage/private/dbs/`, entirely outside the web root (`public/`). Direct web access to document files returns HTTP 404/403.
* **PII & Certificate Masking**: Certificate numbers are masked in tabular views (e.g., `******7261`) to adhere to data minimization principles.
* **Audit Sanitization**: Audit logs automatically redact sensitive fields (`password`, `token`, `secret`, `private_key`, `api_key`, `credentials`) using `Logger::redactSensitiveData()` before presentation in managerial views.

---

## 7. Database Architecture & Schema Integrity

**NO DATABASE MIGRATION REQUIRED**: Phase 9 leverages the existing 12 tables established in Phases 3 through 6:
* `users`
* `tutor_profiles`
* `student_profiles`
* `children`
* `availability_slots`
* `bookings`
* `booking_status_history`
* `audit_logs`
* `blog_posts`
* `newsletter_subscribers`
* `contact_inquiries`
* `migrations`

All joins and queries operate natively without modifying tables, adding columns, or breaking backwards compatibility.

---

## 8. Accessibility & SEO Standards

All managerial views adhere to the platform accessibility baseline:
* **Skip Link**: `<a href="#main-content" class="skip-link">Skip to main content</a>` rendered as the very first focusable element.
* **Heading Hierarchy**: Exactly one `<h1>` per page representing the view title, followed by structured `<h2>` and `<h3>` elements.
* **Semantic Landmarks**: `<header>`, `<nav>`, `<main id="main-content">`, `<section>`, and `<footer>`.
* **Form & Modal Accessibility**: Explicit `id` and `for` associations on all filter inputs, search fields, and action modals.
* **High Contrast & Focus**: High-contrast status badges and 3px `:focus-visible` outlines.
* **Verification Note**: Selected automated accessibility checks related to WCAG 2.2 AA requirements passed across all manager administration views. Full legal, certification, or complete accessibility conformance is not claimed based on automated checks.

---

## 9. Preservation of Open Commercial Decisions

The following items remain open decisions and were not silently resolved:
1. **Payment Provider**: Deferred commercial scope; no commercial payment provider (Stripe, PayPal, etc.) has been selected or implemented.
2. **Commercial Email Provider**: Transactional emails utilize the neutral provider abstraction (`ArrayEmailAdapter`, `LogEmailAdapter`, `SmtpEmailAdapter`); no proprietary commercial vendor has been selected.
3. **Cancellation Policy**: Commercial cancellation terms remain open.
4. **Rescheduling Policy**: Complete rescheduling workflow and commercial terms remain open; no `RESCHEDULED` state exists.
5. **Delivery Mode**: In-person, online, or hybrid delivery mode rules remain open.
6. **Lesson-Note Visibility**: Student/guardian visibility permissions for lesson notes remain open.
7. **Recurring Availability**: Automated recurring slot generation remains open.
8. **Multi-Guardian Access**: Multiple parent accounts linked to a single child remain open.
9. **Newsletter Double Opt-In**: Newsletter double opt-in verification lifecycle remains open.
10. **DBS Retention / Deletion Policy**: Document retention period and automatic purging policies remain open.
11. **Tutor Publishing Toggle**: Independent self-service profile publishing toggle remains open.

---

## 10. Phase Boundary Confirmation

Strict phase boundaries are maintained. The following areas are NOT implemented and remain strictly out of Phase 9 scope:
* **Phase 10 (Blog / Newsletter Editorial Management)**: NOT STARTED.
* **Phase 11 (Security Hardening)**: NOT STARTED.
* **Phase 12 (QA & User Acceptance Testing)**: NOT STARTED.
* **Phase 13 (Production Deployment)**: NOT STARTED.
* **Phase 14 (Handover & Final Documentation)**: NOT STARTED.
* **Payment Processing**: NOT IMPLEMENTED.
* **Commercial Payment Provider**: NOT SELECTED.
* **Complete Rescheduling Workflow**: NOT IMPLEMENTED.
* **`RESCHEDULED` State**: NOT CREATED.
* **Lesson Notes**: NOT IMPLEMENTED.
* **Newsletter Expansion**: NOT IMPLEMENTED.
* **Blog Expansion / Moderation**: NOT IMPLEMENTED.
* **Production Deployment**: NOT STARTED.
* **Client Handover**: NOT STARTED.
