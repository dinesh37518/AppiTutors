# Phase 7: Booking Engine Implementation — Completion Report

**Project**: UK Tutoring Platform  
**Document**: Phase 7 Completion Report  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 7 — Booking Engine Implementation  
**Status**: PHASE 7 — IMPLEMENTATION COMPLETE; AWAITING REVIEW  

---

## 1. Executive Summary

Phase 7 of the UK Tutoring Platform has been implemented and verified strictly against the **Production Master Project Document v2.0** and the approved baselines from Phases 1 through 6. 

The **Booking Engine** provides a secure, concurrency-safe mechanism for authenticated Student and Parent users to book lesson sessions against verified, eligible tutors' published availability slots. Concurrency protection and race-condition prevention are strictly enforced at the database level using MySQL 8.4 InnoDB exclusive row-level locking (`SELECT ... FOR UPDATE`). Competing transactions simultaneously attempting to reserve the same slot are serialized: the first transaction reserves the slot, and any competing transaction is rejected with an HTTP `409 Conflict` response, guaranteeing **zero duplicate bookings**.

The booking lifecycle strictly adheres to the approved Master Document vocabulary (`PENDING`, `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`, `COMPLETED`), with **zero occurrences of any unapproved `RESCHEDULED` state**.

Every state transition records an entry in `booking_status_history` and is logged to the immutable `audit_logs` table within the same atomic transaction. Strict server-side object-level authorization (IDOR/BOLA protection) guarantees that parents cannot book using another parent's child, cannot view or alter other users' bookings, and tutors cannot manage bookings assigned to others.

The automated test suite executed **40/40 tests passing (100%)** for Phase 7, and the full regression test suite passed with **191/191 tests passing (100%) and 0 failures**.

---

## 2. Files Created

| File Path | Description |
| :--- | :--- |
| `src/Services/BookingService.php` | Core domain service managing booking creation, concurrency locking, state transitions, slot release, and ownership validation. |
| `api/bookings.php` | RESTful API endpoint supporting GET, POST, and PATCH/PUT actions with Firebase auth, MySQL role checks, and error formatting. |
| `src/Views/book-session.php` | Accessible booking view with tutor profile banner, localized slot picker, dependent selector, and inquiry notes textarea. |
| `public/book-session.php` | Public entry controller for booking session page. |
| `src/Views/student-bookings.php` | Student/Parent dashboard view for reviewing requested and confirmed lessons with cancellation action. |
| `public/student-bookings.php` | Public entry controller for student bookings dashboard. |
| `src/Views/tutor-bookings.php` | Tutor booking management view for accepting or rejecting pending lesson requests. |
| `public/tutor-bookings.php` | Public entry controller for tutor bookings dashboard. |
| `tests/Phase7BookingEngineTest.php` | Automated test suite comprising 40 test assertions covering security, concurrency, lifecycle, and accessibility. |
| `docs/PHASE-07-EXTERNAL-SOURCES.md` | Audit register documenting official MySQL 8.4, PHP, and OWASP documentation consulted for technical decisions. |
| `docs/PHASE-07-BOOKING-ENGINE.md` | Comprehensive architectural document detailing booking workflows, locking strategies, and lifecycle boundaries. |
| `docs/PHASE-07-COMPLETION-REPORT.md` | Official completion report verifying deliverables against requirements. |

---

## 3. Files Modified

| File Path | Reason Modified |
| :--- | :--- |
| `src/Views/layouts/footer.php` | Added direct navigation links to "My Bookings" and "Session Requests" for authenticated users. |

*Note: No previous phase code was altered; existing Phase 1–6 functionality was strictly preserved without regression.*

---

## 4. Database Changes

An exhaustive review of the existing database schema established that migrations `003_create_availability_and_bookings_tables.sql` and `004_create_booking_history_and_lesson_notes_tables.sql` already provided complete support for:
- `availability_slots`: (`id`, `tutor_user_id`, `starts_at_utc`, `ends_at_utc`, `status`)
- `bookings`: (`id`, `student_user_id`, `child_id`, `tutor_user_id`, `slot_id`, `status`, `inquiry_notes`, `proposed_starts_at_utc`, `proposed_ends_at_utc`, `confirmed_starts_at_utc`, `confirmed_ends_at_utc`, `created_at`, `updated_at`)
- `booking_status_history`: (`id`, `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`)
- `audit_logs`: (`id`, `action`, `entity_type`, `entity_id`, `actor_user_id`, `metadata`, `ip_address`, `user_agent`, `created_at`)

**Database Schema Migrations Required**: **ZERO (0)**.  
No schema additions or modifications were necessary. No existing tables or migration records were altered.

---

## 5. Booking Creation Workflow

The booking creation process follows a strict 9-step atomic sequence:
1. **User Authentication**: Validates Bearer Firebase ID token and resolves MySQL user account with role `STUDENT_PARENT` (or `MANAGER`) and status `ACTIVE`.
2. **Payload Validation**: Validates presence of `tutor_user_id` and `slot_id`. Trims and sanitizes optional `notes` (max 2000 chars).
3. **Transaction Initiation**: Begins PDO database transaction (`$this->pdo->beginTransaction()`).
4. **Row Lock Acquisition**: Executes `SELECT ... FROM availability_slots WHERE id = ? FOR UPDATE`.
5. **Eligibility & Slot Verification**:
   - Asserts slot belongs to target tutor.
   - Asserts slot is in the future (`starts_at_utc > UTC_TIMESTAMP()`).
   - Asserts slot status is strictly `PUBLISHED`.
   - Calls `TutorService::isBookable()` to enforce Safeguarding Gate (Tutor must be `ACTIVE`, `APPROVED`, with `VERIFIED` DBS).
6. **Child Ownership Verification**: If `child_id` is provided, verifies that `child.parent_user_id === current_user.id`.
7. **Booking Row Insertion**: Inserts new record into `bookings` in `PENDING` status.
8. **History & Slot Transition**:
   - Inserts record into `booking_status_history` (`old_status: NULL`, `new_status: PENDING`, `changed_by: current_user.id`).
   - Updates target slot status to `BOOKED`.
   - Writes `BOOKING_CREATED` event to `audit_logs`.
9. **Commit**: Commits transaction (`$this->pdo->commit()`), releasing the row lock and returning HTTP 201 Created.

---

## 6. Transaction & Locking Strategy

- **Engine**: MySQL 8.4 InnoDB.
- **Lock Type**: Exclusive row lock (`X` lock) acquired via `SELECT ... FOR UPDATE`.
- **Query Boundary**:
  ```sql
  SELECT id, tutor_user_id, starts_at_utc, ends_at_utc, status 
  FROM availability_slots 
  WHERE id = ? 
  FOR UPDATE;
  ```
- **Atomicity**: Any validation error, foreign key violation, or runtime exception triggers `$this->pdo->rollBack()`, ensuring no partial rows exist in `bookings` or `booking_status_history`, and the availability slot is not corrupted.

---

## 7. Real Concurrency Verification Evidence

The concurrency safety of `SELECT ... FOR UPDATE` was verified directly against the active MySQL 8.4.9 database using two completely independent PDO connections:
- **Scenario A (Physical InnoDB Row Locking)**: Connection 1 begins transaction and locks Slot 114 with `SELECT ... FOR UPDATE`. Connection 2 attempts to lock Slot 114 with a 1-second timeout. Connection 2 is physically blocked by InnoDB and times out with `SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded`.
- **Scenario B (Double-Booking Prevention)**: Connection 1 books Slot 115 and commits. Competing Connection 2 attempts to book Slot 115 and is rejected with HTTP 409 Conflict (`SLOT_UNAVAILABLE`). Exactly 1 booking is created; zero duplicate bookings exist in the database.
- **Scenario C (Rollback Atomicity)**: An in-flight booking transaction is rolled back. Verification confirms zero partial rows remain in `bookings` and the slot status remains `PUBLISHED`.
- **Execution Log**:
  ```text
  [ PASS ] Concurrency: MySQL 8.4 InnoDB SELECT ... FOR UPDATE physically blocks competing transaction
           Detail: Competing connection was blocked and caught expected lock wait timeout (1205)
  [ PASS ] Concurrency: First transaction successfully acquired lock and reserved slot
           Detail: Booking ID: 43, Slot ID: 115
  [ PASS ] Concurrency: Competing transaction safely rejected with HTTP 409 Conflict
           Detail: Caught exception: SLOT_UNAVAILABLE (Availability slot is no longer available for booking (status: BOOKED).)
  [ PASS ] Concurrency: Exactly 1 booking created; ZERO duplicate bookings in database
           Detail: Total bookings recorded for Slot 115: 1
  [ PASS ] Concurrency: Availability slot final state is BOOKED
           Detail: Slot ID 115 final status: BOOKED
  [ PASS ] Concurrency: Rollback leaves no partial booking and preserves PUBLISHED slot state
           Detail: Partial bookings remaining: 0, Slot status: PUBLISHED
  ```

---

## 8. Booking States & Transitions

### Approved Vocabulary
Only the approved 7 states are recognized:
1. `PENDING`
2. `CONFIRMED`
3. `REJECTED`
4. `RESCHEDULE_PROPOSED`
5. `CANCELLED`
6. `SYSTEM_CANCELLED`
7. `COMPLETED`

> **VERIFIED**: There is **NO `RESCHEDULED` state** in the codebase.

### Supported State Transitions in Phase 7
- `PENDING` → `CONFIRMED`: Assigned tutor confirms session. Recorded in `booking_status_history` and `audit_logs`.
- `PENDING` → `REJECTED`: Assigned tutor declines session. Associated availability slot is automatically restored to `PUBLISHED` if in the future.
- `PENDING` / `CONFIRMED` → `CANCELLED`: Owning student/parent, tutor, or manager cancels booking. Future slot is restored to `PUBLISHED`.
- Attempted invalid transitions (e.g. re-confirming already `CONFIRMED` booking) are rejected with `422 Unprocessable Entity` (`INVALID_STATE_TRANSITION`).

---

## 9. Lesson Notes Status

- **Status in Phase 7**: **Deferred**.
- **Important Distinction**: Inquiry notes (`inquiry_notes` column on `bookings`) are pre-booking messages submitted by students/parents and are fully implemented. Lesson notes (`lesson_notes` table) represent post-lesson academic feedback authored by tutors.
- **Codebase Findings**:
  - The `lesson_notes` table was created in migration `004` (Phase 3).
  - No `LessonNotesService`, `/api/lesson_notes.php`, or tutor authoring UI was created in Phase 7.
  - Lesson notes functionality remains deferred to a subsequent lesson delivery / tutor management phase.
  - The visibility policy (`INTERNAL` vs `PARENT_VISIBLE`) remains an open client decision and no unapproved visibility policy was hard-coded.

---

## 10. Lesson Delivery Mode Status

- **Status in Phase 7**: **Deferred / Open Client Decision**.
- **Codebase Findings**: Lesson delivery mode remains an open client decision and no unapproved delivery-mode business rule (such as forcing `ONLINE` or `IN_PERSON`) was hard-coded in Phase 7.

---

## 11. Rescheduling Boundary

- **Status in Phase 7**: **Baseline Lifecycle Preparation Only**.
- **Codebase Findings**:
  - The status enum `RESCHEDULE_PROPOSED` is recognized in `BookingService::VALID_STATUSES` and permitted transition maps.
  - Full rescheduling workflows (reschedule proposal creation, slot re-assignment, negotiation loop, expiry timer, acceptance/rejection) remain deferred to a dedicated rescheduling workflow phase / prompt.
  - Zero `RESCHEDULED` state exists.
  - No speculative timeout, expiry, slot-release on proposal, or auto-cancellation rules were invented.

---

## 12. Cancellation Status & Boundary

- **Status in Phase 7**: **Implemented Baseline Transitions**.
- **Codebase Findings**:
  - Supported transitions: `PENDING` → `CANCELLED` and `CONFIRMED` → `CANCELLED`.
  - Can be initiated by: Owning Student/Parent, Assigned Tutor, or Manager.
  - If the slot is in the future, it is automatically restored to `PUBLISHED`.
  - Status history and audit log are recorded atomically.
  - **No arbitrary cancellation notice periods** (such as 24h or 48h windows) were introduced.
  - **No cancellation fees or penalties** were introduced.
  - **No refund or payment rules** were introduced.
  - Cancellation policy remains an open client decision.

---

## 13. Student / Parent Ownership Controls

- Student/parent users are strictly bound to their own records.
- Querying bookings via `getBooking()` or `listBookings()` enforces `booking.student_user_id = current_user.id`.
- Parent A attempting to view or cancel Parent B's booking is blocked with HTTP 403 Forbidden (`UNAUTHORIZED_RESOURCE_OWNERSHIP`).

---

## 14. Child Ownership Controls

- Before creating a booking for a dependent, `BookingService` performs a database lookup on `children` using the supplied `child_id`.
- The child record's `parent_user_id` is strictly compared with the authenticated user's ID.
- Parent A attempting to book a session using Parent B's child is blocked with HTTP 403 Forbidden (`UNAUTHORIZED_RESOURCE_OWNERSHIP: You cannot create a booking for a child that does not belong to your account.`).
- Non-existent child IDs return a safe HTTP 404 Not Found without disclosing internal table details.

---

## 15. Tutor Eligibility Safeguards

Reused the established `TutorService::isBookable()` gate from Phase 5:
- Tutors in `PENDING` approval status cannot be booked (`403 TUTOR_NOT_BOOKABLE`).
- Tutors in `SUSPENDED` status cannot be booked (`403 TUTOR_NOT_BOOKABLE`).
- Tutors with unverified or missing DBS certificates cannot be booked.

---

## 16. Tutor Booking Workflow

- Tutors access `tutor-bookings.php` to view pending requests assigned specifically to them.
- Tutors can inspect lesson inquiry notes, student/child details, and slot time.
- Tutors can confirm or decline requests with single-click CSRF-protected actions.
- Tutor B cannot view, confirm, or reject bookings assigned to Tutor A (`403 UNAUTHORIZED_RESOURCE_OWNERSHIP`).

---

## 17. Manager Access

- Users with authoritative MySQL role `MANAGER` and status `ACTIVE` have supervisory access.
- Managers can view all platform bookings and perform administrative confirmations, rejections, or cancellations where required.

---

## 18. Security Controls Summary

| Security Domain | Control Implemented | Status |
| :--- | :--- | :--- |
| **SQL Injection** | 100% PDO prepared statements with bound parameters across all queries. | VERIFIED |
| **XSS** | Input sanitization on inquiry notes via `Validator::sanitizeString()` and view escaping via `View::e()`. | VERIFIED |
| **CSRF** | Form-based state changes protected by cryptographic session CSRF tokens (`Csrf::validateToken()`). | VERIFIED |
| **IDOR / BOLA** | Strict server-side verification of student, parent, child, and tutor resource ownership on every action. | VERIFIED |
| **Privilege Escalation** | Client-supplied roles and statuses discarded; authorization derived strictly from MySQL user records. | VERIFIED |
| **Data Protection** | No passwords, Firebase tokens, internal database errors, or private credentials logged or exposed. | VERIFIED |

---

## 19. Accessibility Checks

Automated accessibility checks covering selected WCAG 2.2 AA-related requirements passed across all booking views:
- Focusable skip link (`<a class="skip-link" href="#main-content">`) present.
- Landmark structure includes `<main id="main-content">`.
- Strict heading hierarchy with exactly one `<h1>` per view.
- Explicit form label association using `id` and `for` attributes.
- High-contrast 3px focus outline visible on interactive elements (`:focus-visible`).
- Accessible buttons and descriptive error messaging.

*Note: In accordance with project instructions, these automated tests verify selected WCAG 2.2 AA-related criteria and do not constitute a full legal accessibility audit.*

---

## 20. External Sources

All external sources consulted were recorded in `docs/PHASE-07-EXTERNAL-SOURCES.md`:
1. **MySQL 8.4 Reference Manual — Locking Reads (`SELECT ... FOR UPDATE`)**: Informed row locking and concurrency isolation.
2. **MySQL 8.4 Reference Manual — InnoDB Transaction Model & Isolation Levels**: Informed transaction boundaries and atomic rollback.
3. **PHP Official Documentation — PDO Transactions**: Informed exception handling and `inTransaction()` guards.
4. **OWASP API Security Top 10 — Broken Object Level Authorization (BOLA / IDOR)**: Informed ownership checks on bookings and children.

---

## 21. Phase 7 Test Results

- **Test Suite**: `tests/Phase7BookingEngineTest.php`
- **Total Tests Executed**: 40
- **Tests Passed**: 40 (100%)
- **Tests Failed**: 0

Breakdown:
- Authentication & Access Control: 2/2
- Role Authorization & RBAC: 2/2
- Booking Creation & Status History: 4/4
- Ownership & IDOR / BOLA: 5/5
- Tutor Eligibility Safeguards: 2/2
- Availability Slot Validation: 3/3
- Input Validation & Security: 4/4
- Booking State Transitions: 6/6
- Concurrency & Double-Booking Prevention: 5/5
- Web Routes & API Endpoints: 5/5
- Accessibility Verification: 2/2

---

## 22. Full Regression Results

Full regression testing across all phases was executed sequentially:

| Test Suite | Scope | Result | Status |
| :--- | :--- | :--- | :--- |
| `tests/run_tests.php` | Phase 3 — Foundation & Auth | 25 / 25 | **PASS** (100%) |
| `tests/Phase4PublicWebsiteTest.php` | Phase 4 — Public Website & CMS | 30 / 30 | **PASS** (100%) |
| `tests/Phase5TutorWorkflowTest.php` | Phase 5 — Tutor Profile & Availability | 51 / 51 | **PASS** (100%) |
| `tests/Phase6StudentParentTest.php` | Phase 6 — Student / Parent Workflow | 45 / 45 | **PASS** (100%) |
| `tests/Phase7BookingEngineTest.php` | Phase 7 — Booking Engine | 40 / 40 | **PASS** (100%) |
| **TOTAL REGRESSION SUITE** | **Complete Platform Baseline** | **191 / 191** | **PASS (100%), 0 FAILURES** |

---

## 23. Open Client Decisions Preserved

The neutral baseline has been preserved for all unresolved business decisions:
- **Payment Processing**: Deferred; no payment gateway, checkout, or fee collection introduced.
- **Cancellation Notice Periods**: No arbitrary time windows (e.g. 24h/48h cutoffs) or penalty fees were implemented.
- **Rescheduling Negotiation**: Full rescheduling workflow is deferred to later phases.
- **Lesson Delivery Mode**: Remained open; no `ONLINE` or `IN_PERSON` rules forced.
- **Lesson Notes Visibility Policy**: Remained open; full authoring and visibility deferred.
- **Recurring Availability**: Left as single discrete slots per Phase 5.
- **Multi-Guardian Access**: Maintained single parent account per child.
- **Newsletter Double Opt-In**: Maintained neutral `PENDING` subscription state from Phase 4.

---

## 24. Strict Phase Boundaries

In strict compliance with instructions:
- **Phase 8 (Email)**: No transactional email providers (SendGrid, Mailgun, SES), SMTP configs, queues, or notification triggers were implemented.
- **Phase 9 (Manager Admin)**: No complete manager booking dashboard was built.
- **Phase 10 (Blog/Newsletter)**: No editorial or subscription management changes were made.
- **Phase 11 (Security/Hardening)**: No speculative third-party rate limiting infrastructure was added.
- **Payment Gateway**: Zero payment code exists in the platform.

---

## 25. Known Limitations

1. **Email Notifications**: Confirmation and cancellation emails are deferred to Phase 8.
2. **Rescheduling UI**: Full multi-step reschedule negotiation UI is deferred.
3. **Lesson Notes Authoring**: Tutor post-lesson notes UI and API are deferred.
4. **Automated Accessibility Scope**: Automated tests verify selected WCAG 2.2 AA technical criteria; manual screen-reader testing remains for QA/UAT (Phase 12).

---

## 26. Phase 8 Integration Boundary

Phase 7 establishes clean internal transaction boundaries. When Phase 8 is implemented:
- Status transition methods (`createBooking()`, `confirmBooking()`, `rejectBooking()`, `cancelBooking()`) can dispatch asynchronous domain events (e.g. `BookingCreatedEvent`, `BookingConfirmedEvent`).
- Phase 8 will consume these events to render email templates and dispatch transactional emails without altering the core booking engine logic.

---

## 27. Final Status Declaration

**PHASE 7 — IMPLEMENTATION COMPLETE; AWAITING REVIEW.**
