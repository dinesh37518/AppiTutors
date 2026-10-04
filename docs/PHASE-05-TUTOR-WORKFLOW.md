# PHASE 5 — TUTOR PROFILE, APPROVAL & AVAILABILITY SPECIFICATION

**Project**: UK Tutoring Platform  
**Phase**: Phase 5 — Tutor Profile, Approval & Availability  
**Authoritative Baseline**: Production Master Project Document v2.0 | Approved Phase 1 Discovery | Approved Phase 2 Architecture | Verified Phase 3 Foundation | Approved Phase 4 Public Website  
**Primary Region**: United Kingdom (`Europe/London` GMT/BST)  
**Status**: APPROVED & VERIFIED (51/51 Tests Passing)  

---

## 1. OBJECTIVE & ARCHITECTURAL FOUNDATION

The objective of **Phase 5** is to implement the authoritative educator onboarding pipeline, managerial safeguarding approval controls, discrete teaching availability schedule, and transaction-isolated overlap prevention engine for the UK Tutoring Platform.

### 1.1 Non-Negotiable Core Principles

1. **Identity vs Authority Separation**:
   * **Firebase Authentication** establishes identity only (`firebase_uid`).
   * **MySQL Database** strictly establishes application role (`role = 'TUTOR'`), account status (`status = 'PENDING'`), profile content, and safeguarding approval authority.
   * Client-supplied roles and authority overrides are unconditionally discarded by the backend. A browser or client request can **never** create, assign, or elevate to `MANAGER` authority.
2. **Safeguarding Bookability Gate**:
   * Architectural requirement: An unapproved tutor must not be bookable.
   * Implemented Phase 5 technical gate: A tutor must satisfy `users.status = 'ACTIVE'`, `tutor_profiles.approval_status = 'APPROVED'`, and `tutor_profiles.dbs_status = 'VERIFIED'` to be bookable. Prospective or unverified tutors remain non-bookable and excluded from public directory queries.
3. **Database Schema Integrity**:
   * Database schema changes were limited to the approved Phase 5 migration scope. The existing Phase 3 foundation schema fully accommodated all Phase 5 domain entities, so no additional or unapproved schema migrations were introduced.
4. **Timezone Principle**:
   * All timestamps are stored in UTC (`+00:00`) in MySQL.
   * All user-facing lesson schedules and slot inputs operate in `Europe/London` (Greenwich Mean Time / British Summer Time).
5. **Phase Boundaries**:
   * Phase 5 implements only tutor onboarding, profile administration, managerial status controls, and discrete availability management.
   * Phase 6 (student/parent directory search, public profile pages), Phase 7 (booking transaction engine, lesson rescheduling, concurrency engine), Phase 8 (automated email notifications), and payment gateway or payout processing (deferred/open commercial scope) remain strictly untouched.

---

## 2. TUTOR REGISTRATION WORKFLOW

### 2.1 Registration Pathway & Role Authority

Tutor registration is orchestrated via `App\Services\TutorService::registerTutor()`:

1. **Token & Identity Verification**: The client authenticates with Firebase, obtaining a cryptographically signed Firebase ID token containing the user's `firebase_uid` and verified email.
2. **Authority Stripping**: Any client-supplied authority parameters (`role`, `status`, `approval_status`, `dbs_status`, `is_manager`, `is_admin`, `approved_at`, `approved_by`) are explicitly stripped from the input payload.
3. **Duplicate Prevention**: The system enforces uniqueness across both `users.firebase_uid` and `users.email`. Attempted duplicate registrations return `HTTP 409 Conflict` with `USER_ALREADY_EXISTS`.
4. **Atomic Relational Persistence**: In a single database transaction, the system provisions:
   * `users` record: `role = 'TUTOR'`, `status = 'PENDING'`, `email_verified = false`.
   * `tutor_profiles` record: `approval_status = 'PENDING'`, `dbs_status = 'NOT_SUBMITTED'`, with sanitized `headline`, `bio`, `qualifications`, `subjects_json`, and `curriculum_json`.
5. **Initial Non-Bookable State**: The registered educator starts with `is_bookable = false`.

---

## 3. TUTOR PROFILE MANAGEMENT & IDOR DEFENSE

### 3.1 Profile Operations
Tutor profile management is governed by `App\Services\TutorService`:

* **`getProfile(int $tutorUserId, ?UserContext $currentUser)`**:
  * If the requester is a third-party (unauthenticated or non-manager), the profile is accessible only if the tutor satisfies the full bookability gate (`isBookable() === true`). Prospective, rejected, or suspended profiles are shielded (`HTTP 403 Forbidden`).
  * If the requester is the tutor themselves (`assertOwnership`) or a platform `MANAGER`, full profile data, safeguarding statuses, and approval details are returned.
* **`updateProfile(int $tutorUserId, array $data, UserContext $currentUser)`**:
  * Tutors may update their own `headline`, `bio`, `qualifications`, `subjects`, and `curriculum`.
  * Display name updates sync to `users.display_name`.
  * Sensitive lifecycle fields (`role`, `status`, `approval_status`, `dbs_status`, `approved_at`, `approved_by`) are strictly ignored if submitted in the payload.

### 3.2 Insecure Direct Object Reference (IDOR) & Inactive Protection
* **IDOR Prevention**: `Authorization::assertOwnership($tutorUserId, $currentUser->id)` ensures Tutor A cannot inspect or modify Tutor B's profile.
* **Inactive Account Guard**: `Authorization::requireActiveStatus($currentUser)` blocks suspended or inactive accounts from modifying profiles (`HTTP 403 Forbidden`).
* **Output Sanitization**: All view outputs are escaped with `App\Support\View::e()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) preventing Cross-Site Scripting (XSS).

---

## 4. TUTOR LIFECYCLE & MANAGERIAL APPROVAL CONTROLS

### 4.1 Approved Business Lifecycle & Scope Boundaries

The approved overarching tutor lifecycle model spans multiple project phases:

```
[ REGISTER ]
     │
     ▼
[ EMAIL_VERIFIED ]
     │
     ▼
[ PROFILE_COMPLETE ]
     │
     ▼
[ DBS_SUBMITTED ]
     │
     ▼
[ PENDING_REVIEW ] ──(Manager Rejection)──► [ REJECTED ]
     │
(Manager Approval)
     │
     ▼
[ MANAGER_APPROVED ] ◄──(Manager Reinstatement)──┐
     │                                            │
     ▼                                            │
[ BOOKABLE / LIVE ] ──(Manager Suspension)──► [ SUSPENDED ]
```

### 4.2 Implemented Phase 5 Functionality vs Later-Phase Integrations

To maintain strict architectural governance, implemented Phase 5 functionality is clearly distinguished from later-phase integration points:

* **Implemented in Phase 5**:
  * Initial registration into `role = 'TUTOR'`, `status = 'PENDING'`, and `approval_status = 'PENDING'`.
  * Profile completion and self-management by active educators.
  * Technical DBS metadata and document submission (`DBS_SUBMITTED`).
  * Managerial review queue displaying pending applications.
  * Managerial governance transitions:
    * `managerApproveTutor`: `PENDING` $\to$ `APPROVED` (sets user to `ACTIVE`).
    * `managerRejectTutor`: `PENDING` $\to$ `REJECTED` (sets user to `SUSPENDED`).
    * `managerSuspendTutor`: `APPROVED` $\to$ `SUSPENDED` (sets user to `SUSPENDED`, blocks published slots).
    * `managerReinstateTutor`: `SUSPENDED` $\to$ `APPROVED` (restores user to `ACTIVE`).
* **Deferred to Later Phases (NOT implemented in Phase 5)**:
  * Public directory search, subject filtering, and parent-facing profile browsing belong strictly to **Phase 6**.
  * Lesson booking creation, inquiry requests, acceptance/confirmation, rescheduling, and cancellation belong strictly to **Phase 7**.
  * Automated email notification lifecycle — deferred to Phase 8.
  * Payment gateway or payout processing — deferred/open commercial scope; no payment implementation was introduced in Phase 5.

### 4.3 Safeguarding Defense Rules
1. **Self-Approval Strictly Forbidden**: Tutors cannot invoke approval endpoints on their own profile (`SELF_APPROVAL_FORBIDDEN` / `403 Forbidden`).
2. **Client-Supplied Status Discarded**: Tutors cannot alter their own approval status via profile update APIs.
3. **Suspension Cascading**: Suspending a tutor automatically updates `users.status = 'SUSPENDED'` and transitions existing `PUBLISHED` availability slots to `BLOCKED`.

---

## 5. BOOKABILITY / SAFEGUARDING PUBLICATION GATE

### 5.1 Architectural Requirement vs Implemented Phase 5 Technical Predicate

* **Architectural Requirement**: An unapproved tutor must not be publicly bookable.
* **Implemented Phase 5 Safeguarding Predicate**: Implemented in `TutorService::isBookable(int $tutorUserId)` as a conjunction of three database-verified conditions:

$$\text{isBookable} \iff (\text{users.status} = \text{'ACTIVE'}) \land (\text{tutor\_profiles.approval\_status} = \text{'APPROVED'}) \land (\text{tutor\_profiles.dbs\_status} = \text{'VERIFIED'})$$

The `ACTIVE + APPROVED + VERIFIED` rule is the authoritative Phase 5 technical gate. If any condition fails, `isBookable()` evaluates to `false`.

### 5.2 Enforced Gate Restrictions
* **Unapproved Tutors Cannot Publish Slots**: Attempting to publish availability throws `BookabilityException` (`HTTP 403 Forbidden`).
* **Directory Shielding**: Prospective, rejected, or suspended tutors are excluded from third-party profile queries and public discovery.
* **Open Client Decision (DISC-030)**: Whether approved tutors have an additional manual "Publish/Unpublish Profile" toggle remains an open client decision. Phase 5 enforces the mandatory safeguarding gate without pre-empting the client's decision on discretionary profile visibility toggles.

---

## 6. DBS SAFEGUARDING WORKFLOW BOUNDARY

The Disclosure and Barring Service (DBS) workflow provides technical safeguarding verification while preserving open client policy decisions.

### 6.1 State Handling
* `NOT_SUBMITTED`: Default state upon initial tutor registration.
* `SUBMITTED`: Certificate details (and optional scan) provided by tutor.
* `VERIFIED`: Verified by an authorized platform Manager.
* `REJECTED`: Manager review determined certificate invalid or non-compliant.
* `EXPIRED`: Reserved for credential expiration workflow.

### 6.2 Secure Document Upload Engineering Controls
When a tutor submits physical evidence:
1. **Storage Isolation**: Files are stored strictly in `storage/private/dbs/`, entirely outside the public web server `DocumentRoot`.
2. **File Size Limit**: Strictly enforced between 1 byte and 5 MB (5,242,880 bytes).
3. **MIME & Extension Whitelist**: Verified via `finfo_file` and file extension matching:
   * PDF (`application/pdf`)
   * PNG (`image/png`)
   * JPEG (`image/jpeg`)
   * All executable extensions (`.php`, `.phtml`, `.exe`, `.sh`, `.js`, etc.) and polyglot files are strictly rejected.
4. **Cryptographic Renaming**: Files are renamed with `bin2hex(random_bytes(16))` plus legitimate extension. Original client filenames are never used on the filesystem.
5. **Authorized Streaming**: Documents are served only through `api/dbs/document.php` which validates session/token ownership or managerial authority before streaming binary contents. Direct URL access to `storage/private/dbs/` returns `HTTP 404/403`.
6. **Open Client Decision (DISC-020)**: The physical document retention period remains an open client policy decision. All submissions and metadata record `retention_policy_status = 'CLIENT_DECISION_OPEN'`. No retention policy has been invented or finalized.

---

## 7. AVAILABILITY MODEL & OVERLAP PREVENTION

### 7.1 Timezone & UTC Storage
* **MySQL Storage**: `starts_at_utc` and `ends_at_utc` columns store standard UTC `DATETIME`.
* **UK Local Input/Display**: Times are accepted and displayed in `Europe/London` using `App\Support\Timezone`.
* **Seasonal Transitions**: Automatically accounts for British Summer Time (BST, UTC+1) and Greenwich Mean Time (GMT, UTC+0).

### 7.2 Overlap Prevention Algorithm
To prevent scheduling conflicts, `AvailabilityService::assertNoOverlap` evaluates whether candidate time window $[S_{new}, E_{new})$ intersects with any existing slot $[S_{i}, E_{i})$ for the tutor:

$$\text{Collision} \iff (S_{i} < E_{new}) \land (E_{i} > S_{new})$$

#### Test Cases:
* **Existing Slot**: 10:00–11:00
* **Late Overlap** (10:30–11:30): $10:00 < 11:30$ AND $11:00 > 10:30 \implies \mathbf{REJECTED}$
* **Early Overlap** (09:30–10:30): $10:00 < 10:30$ AND $11:00 > 09:30 \implies \mathbf{REJECTED}$
* **Enclosing Overlap** (09:00–12:00): $10:00 < 12:00$ AND $11:00 > 09:00 \implies \mathbf{REJECTED}$
* **Interior Overlap** (10:15–10:45): $10:00 < 10:45$ AND $11:00 > 10:15 \implies \mathbf{REJECTED}$
* **Adjacent Prior** (09:00–10:00): $11:00 > 09:00$ BUT $10:00 < 10:00$ is False $\implies \mathbf{ALLOWED}$
* **Adjacent Subsequent** (11:00–12:00): $10:00 < 12:00$ BUT $11:00 > 11:00$ is False $\implies \mathbf{ALLOWED}$

### 7.3 Concurrency Protection
To prevent race conditions where two simultaneous browser requests create overlapping slots, overlap validation executes within an atomic transaction utilizing InnoDB row-level locking:

```sql
SELECT id, starts_at_utc, ends_at_utc 
FROM availability_slots 
WHERE tutor_user_id = ? 
  AND status IN ('DRAFT', 'PUBLISHED', 'BOOKED')
  AND (starts_at_utc < ?) 
  AND (ends_at_utc > ?)
FOR UPDATE;
```

---

## 8. IMMUTABLE AUDIT LOGGING

All sensitive managerial actions and profile state transitions are logged via `App\Services\AuditService`:

* `MANAGER_APPROVE_TUTOR`: Logs approval timestamp, approving manager ID, and status transition.
* `MANAGER_REJECT_TUTOR`: Logs rejection reason and timestamp.
* `MANAGER_SUSPEND_TUTOR`: Logs suspension reason and affected tutor ID.
* `MANAGER_REINSTATE_TUTOR`: Logs reinstatement event.
* `MANAGER_VERIFY_DBS`: Logs DBS credential verification.
* `MANAGER_REJECT_DBS`: Logs DBS credential rejection.
* `TUTOR_DBS_SUBMITTED`: Logs submission metadata (file size, MIME) without leaking certificate numbers or file payloads.
* `AVAILABILITY_SLOT_CREATED`, `AVAILABILITY_SLOT_UPDATED`, `AVAILABILITY_SLOT_DELETED`: Logs scheduling modifications.

---

## 9. USER INTERFACES & ACCESSIBILITY

Three responsive, accessible web interfaces were developed adhering to the UK Academic design system:

1. **`public/tutor-profile.php` (`src/Views/tutor-profile.php`)**:
   * Tutor profile details form (headline, bio, qualifications, subjects).
   * Safeguarding & Bookability Gate Banner displaying current approval & DBS verification status.
   * Enhanced DBS certificate and document submission portal.
2. **`public/tutor-availability.php` (`src/Views/tutor-availability.php`)**:
   * Interactive schedule calendar with local London time inputs.
   * Safeguarding publishing gate restrictions notification.
   * Table of configured slots with London time, UTC time, BST indicator, and removal controls.
3. **`public/manager-tutors.php` (`src/Views/manager-tutors.php`)**:
   * Managerial review queue with status filter tabs (`ALL`, `PENDING`, `APPROVED`, `SUSPENDED`, `REJECTED`).
   * One-click governance actions (Approve, Reject, Suspend, Reinstate).
   * DBS credential verification and private document inspection viewer.
   * CSRF protection across all form submissions via `App\Support\Csrf`.

### 9.1 Accessibility Scope & Engineering Controls
Automated accessibility checks covering selected WCAG 2.2 AA-related requirements passed (skip links, single h1 hierarchy, keyboard focus-visible indicators, form labels, ARIA landmarks). These automated tests verify specific technical engineering controls and do not establish complete legal or WCAG 2.2 AA conformance.

---

## 10. TRACEABILITY MATRIX

| Master Document Requirement | Phase 5 Component | Verification Status |
| :--- | :--- | :--- |
| **Tutor Registration Identity Separation** | `TutorService::registerTutor()` | **VERIFIED** (Passes unit & integration tests) |
| **Client Role Rejection (No Manager Hack)** | `TutorService::registerTutor()` & `Authorization` | **VERIFIED** (Client roles discarded) |
| **Tutor Profile Management & Ownership** | `TutorService::updateProfile()` | **VERIFIED** (IDOR strictly denied) |
| **Tutor Approval Lifecycle** | `TutorService::managerApproveTutor/Reject/Suspend` | **VERIFIED** (Full lifecycle passing) |
| **Safeguarding Bookability Gate** | `TutorService::isBookable()` | **VERIFIED** (ACTIVE + APPROVED + VERIFIED enforced) |
| **DBS Storage Outside Web Root** | `DbsService::submitDbs()` / `storage/private/dbs/` | **VERIFIED** (HTTP 404/403 enforced; DISC-020 OPEN) |
| **Discrete Availability Management** | `AvailabilityService::createSlot/update/delete` | **VERIFIED** (CRUD operations verified) |
| **Overlap Prevention Algorithm** | `AvailabilityService::assertNoOverlap` | **VERIFIED** (Collisions rejected, adjacents allowed) |
| **Europe/London & UTC Handling** | `App\Support\Timezone` | **VERIFIED** (BST and GMT transitions verified) |
| **Immutable Manager Audit Logging** | `AuditService` | **VERIFIED** (Audit rows verified in DB) |
| **Anti-CSRF Form Defense** | `App\Support\Csrf` | **VERIFIED** (Timing-safe token verification) |
