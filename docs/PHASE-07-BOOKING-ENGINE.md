# Phase 7: Booking Engine Technical Architecture & Implementation Guide

**Project**: UK Tutoring Platform  
**Document**: Phase 7 Booking Engine Technical Architecture  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 7 — Booking Engine Implementation  
**Status**: COMPLETE — AWAITING REVIEW  

---

## 1. Executive Summary & Objective

Phase 7 delivers the complete, concurrency-safe **Booking Engine** for the UK Tutoring Platform. The booking engine enables authenticated Student and Parent accounts to discover eligible tutors, browse published availability slots, and request structured lesson sessions for themselves or their verified dependents (children).

The implementation strictly maintains:
1. **Zero Duplicate Bookings**: Guaranteed concurrency protection via MySQL 8.4 InnoDB row-level locking (`SELECT ... FOR UPDATE`). When multiple clients attempt to reserve the same slot concurrently across distinct database connections, exactly one succeeds and all competing requests are rejected with a clean `409 Conflict` response.
2. **Master Document Lifecycle Conformance**: Uses strictly the approved 7 booking states (`PENDING`, `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`, `COMPLETED`). There is explicitly **NO `RESCHEDULED` state**.
3. **Comprehensive Audit & Status History**: Every lifecycle transition writes to `booking_status_history` and `audit_logs` within the same atomic database transaction.
4. **Strict Server-Side Authorization**: Complete IDOR/BOLA protection ensuring students/parents can only access their own bookings and children, and tutors can only view and manage bookings assigned to them.
5. **Safeguarding & Bookability Gate**: Reuses the approved Phase 5 `TutorService::isBookable()` gate, preventing bookings against unapproved, suspended, or unverified-DBS tutors.

---

## 2. Non-Negotiable Security & Authority Flow

The booking engine enforces the non-negotiable server-side authority chain:

```text
Browser Client (HTTPS)
   ↓
PHP Web / API Controller (/api/bookings.php)
   ↓
Firebase Admin SDK (ID Token Verification)
   ↓
Resolved Firebase UID
   ↓
MySQL `users.firebase_uid` Resolution
   ↓
MySQL Authoritative Role (`STUDENT_PARENT`, `TUTOR`, `MANAGER`) & Status (`ACTIVE`)
   ↓
BookingService Server-Side Authorization Checks
   ↓
MySQL 8.4 InnoDB Atomic Transaction + Row Lock (`SELECT ... FOR UPDATE`)
```

At no point does the application trust client-supplied roles, account statuses, tutor approval flags, booking statuses, or slot ownership IDs.

---

## 3. Booking Creation Architecture & Workflow

### 3.1 Booking Flow Diagram

```text
Student / Parent
      ↓
Select Bookable Tutor & Published Availability Slot
      ↓
Select Booking Recipient (Self OR Owned Child)
      ↓
Enter Inquiry Notes (Optional, max 2000 chars)
      ↓
Submit Booking Request (POST /api/bookings.php or /book-session.php)
      ↓
START TRANSACTION (PDO)
      ↓
LOCK availability_slots row:
SELECT * FROM availability_slots WHERE id = ? FOR UPDATE
      ↓
Validate:
1. Slot exists and is in the future (starts_at_utc > UTC_TIMESTAMP())
2. Slot belongs to the requested tutor
3. Tutor satisfies Safeguarding Gate (ACTIVE, APPROVED, DBS VERIFIED)
4. Slot status is strictly 'PUBLISHED'
5. If child_id provided: child exists and child.parent_user_id == current_user.id
6. Inquiry notes sanitized and length-checked
      ↓
Insert `bookings` row:
status = 'PENDING'
      ↓
Insert `booking_status_history` row:
old_status = NULL, new_status = 'PENDING', changed_by_user_id = current_user.id
      ↓
Update `availability_slots`:
status = 'BOOKED'
      ↓
Insert `audit_logs` row:
action = 'BOOKING_CREATED'
      ↓
COMMIT TRANSACTION
      ↓
Return HTTP 201 Created (Booking Details + History)
```

If any check fails, or if an unexpected exception occurs, the transaction rolls back immediately (`PDO::rollBack()`), releasing all row locks without persisting partial records.

---

## 4. Concurrency Safety & Double-Booking Prevention

### 4.1 InnoDB Row Locking (`SELECT ... FOR UPDATE`)
To prevent race conditions and double-booking, `BookingService::createBooking()` executes an exclusive row-level locking read on the target availability slot:

```sql
SELECT id, tutor_user_id, starts_at_utc, ends_at_utc, status 
FROM availability_slots 
WHERE id = ? 
FOR UPDATE;
```

### 4.2 Multi-Connection Race Scenario & Verification
When tested using two completely independent PDO connections to MySQL 8.4:
1. **Connection 1 (Parent A)**: Issues `SELECT ... FOR UPDATE` on Slot 101. MySQL InnoDB grants an exclusive `X` lock on the index record for Slot 101.
2. **Connection 2 (Parent B)**: Simultaneously attempts to acquire a lock on Slot 101. MySQL InnoDB physically suspends Connection 2 in lock-wait until Connection 1 commits, or until `innodb_lock_wait_timeout` expires (verified: throws SQLSTATE 1205 Lock wait timeout exceeded).
3. **Connection 1 Validation & Execution**: Connection 1 reads Slot 101 with `status = 'PUBLISHED'`. Validation passes. Connection 1 creates the booking row, logs history, updates Slot 101 to `status = 'BOOKED'`, and commits.
4. **Connection 1 Commits**: Slot 101 row lock is released.
5. **Connection 2 Evaluates Committed State**: Connection 2 reads the committed state: `status = 'BOOKED'`.
6. **Connection 2 Validation Fails**: `BookingService` detects that the slot is no longer `PUBLISHED` and throws:
   ```php
   throw new ValidationException(
       'Availability slot is no longer available for booking (status: ' . $slot['status'] . ').',
       'SLOT_UNAVAILABLE',
       409
   );
   ```
7. **Connection 2 Rolls Back**: Zero duplicate bookings are inserted. Competing client receives HTTP 409 Conflict.
8. **Rollback Atomicity**: Any rolled-back booking transaction leaves zero partial records in `bookings` and `booking_status_history`, and preserves the availability slot in its untouched `PUBLISHED` state.

---

## 5. Booking States & Approved Transitions

### 5.1 Approved Master Vocabulary
In strict alignment with Master Project Document v2.0, the booking lifecycle consists of exactly 7 states:

| Booking State | Description | Permitted Next States |
| :--- | :--- | :--- |
| `PENDING` | Initial state upon booking creation by student/parent. | `CONFIRMED`, `REJECTED`, `CANCELLED`, `SYSTEM_CANCELLED` |
| `CONFIRMED` | Accepted by assigned tutor (or manager). | `RESCHEDULE_PROPOSED`, `CANCELLED`, `COMPLETED` |
| `REJECTED` | Declined by assigned tutor. Slot released to `PUBLISHED` if in future. | *Terminal state* |
| `RESCHEDULE_PROPOSED` | A time modification has been requested. | `CONFIRMED`, `REJECTED`, `CANCELLED` |
| `CANCELLED` | Cancelled by student/parent, tutor, or manager before lesson start. | *Terminal state* |
| `SYSTEM_CANCELLED` | Cancelled automatically by system rules (e.g. tutor suspension). | *Terminal state* |
| `COMPLETED` | Successfully delivered lesson session. | *Terminal state* |

> **CRITICAL RULE**: There is **NO `RESCHEDULED` state**. Any state modification or transition outside this approved list is strictly prohibited.

### 5.2 Phase 7 Implemented Transitions
1. **`PENDING` → `CONFIRMED`**:
   - Initiated by: Assigned Tutor (or Manager).
   - Validates: Current booking status is `PENDING`.
   - Effect: Booking updated to `CONFIRMED`. `booking_status_history` record logged. `audit_logs` record logged (`BOOKING_CONFIRMED`).
2. **`PENDING` → `REJECTED`**:
   - Initiated by: Assigned Tutor (or Manager).
   - Validates: Current booking status is `PENDING`.
   - Effect: Booking updated to `REJECTED`. If slot is in the future, slot status is returned to `PUBLISHED`. History and audit logged (`BOOKING_REJECTED`).
3. **`PENDING` / `CONFIRMED` → `CANCELLED`**:
   - Initiated by: Owning Student/Parent, Assigned Tutor, or Manager.
   - Validates: Booking is in `PENDING` or `CONFIRMED` status.
   - Effect: Booking updated to `CANCELLED`. If slot is in the future, slot status is returned to `PUBLISHED`. History and audit logged (`BOOKING_CANCELLED`).
   - Policy: No arbitrary cancellation notice periods (e.g. 24h/48h cutoffs) or penalty fees are hard-coded; cancellation rules remain open client decisions.

---

## 6. Inquiry Notes vs. Lesson Notes

An important architectural distinction is maintained between inquiry notes and lesson notes:

1. **Inquiry Notes (`inquiry_notes` on `bookings`)**:
   - **Scope**: Pre-booking communication submitted by the Student/Parent when requesting a lesson.
   - **Status in Phase 7**: **Implemented**.
   - **Fields**: Stored in `bookings.inquiry_notes`.
   - **Validation**: Sanitized via `Validator::sanitizeString()`, strictly capped at 2000 characters, protected against XSS.
   - **Visibility**: Visible to the owning student/parent, assigned tutor, and platform managers.

2. **Lesson Notes (`lesson_notes` domain entity)**:
   - **Scope**: Post-lesson academic evaluation and pedagogical feedback authored by tutors following session completion.
   - **Status in Phase 7**: **Deferred**.
   - **Database Entity**: The underlying database table `lesson_notes` (`booking_id`, `tutor_user_id`, `notes`, `visibility`) was established in Phase 3 migration `004_create_booking_history_and_lesson_notes_tables.sql`.
   - **Service / API / UI**: No `LessonNotesService`, `/api/lesson_notes.php`, or tutor authoring UI has been introduced in Phase 7.
   - **Visibility Policy**: The client has not formally decided the final access rules governing `INTERNAL` vs `PARENT_VISIBLE` notes (e.g. whether parents see full vs redacted academic feedback). Therefore, lesson notes functionality remains deferred to a subsequent lesson delivery / tutor management phase, and no unapproved visibility policy was invented.

---

## 7. Lesson Delivery Mode

- **Status in Phase 7**: **Deferred / Open Client Decision**.
- The Master Project Document identifies lesson delivery mode (e.g. `ONLINE`, `IN_PERSON`, or hybrid) as an unresolved client decision.
- **Rule Enforced**: Lesson delivery mode remains an open client decision and no unapproved delivery-mode business rule was hard-coded in Phase 7.

---

## 8. Rescheduling Boundary

- **Status in Phase 7**: **Baseline Lifecycle Preparation Only**.
- The Master Document defines `RESCHEDULE_PROPOSED` as a valid booking lifecycle status.
- In Phase 7:
  - `RESCHEDULE_PROPOSED` is included in `BookingService::VALID_STATUSES` and permitted transition maps.
  - Full rescheduling workflows (reschedule proposal creation, slot re-assignment, negotiation loop, expiry timer, acceptance/rejection) remain deferred to a dedicated rescheduling workflow phase / prompt.
  - No speculative timeout, expiry, slot-release on proposal, or auto-cancellation rules were invented.

---

## 9. Ownership & IDOR / BOLA Controls

### 9.1 Student / Parent Ownership
- **Creation Ownership**: A student/parent may only create a booking where `student_user_id` matches their own authenticated database ID.
- **Child Ownership Enforcement**: If a `child_id` is supplied, `BookingService` queries the `children` table and strictly validates:
  ```php
  if ((int) $child['parent_user_id'] !== $currentUser->id) {
      throw new ForbiddenException(
          'You cannot create a booking for a child that does not belong to your account.',
          'UNAUTHORIZED_RESOURCE_OWNERSHIP',
          403
      );
  }
  ```
- **Read & Mutation Ownership**: Students/parents can only query bookings where `booking.student_user_id = current_user.id`. Any attempt by Parent A to view or cancel Parent B's booking throws a `403 Forbidden` (`UNAUTHORIZED_RESOURCE_OWNERSHIP`).

### 9.2 Tutor Ownership
- Tutors can only query bookings where `booking.tutor_user_id = current_user.id`.
- Tutors can only accept (`confirmBooking()`) or reject (`rejectBooking()`) requests where they are the assigned tutor. Tutor B attempting to confirm Tutor A's booking is blocked with `403 Forbidden`.

### 9.3 Manager Authority
- Users with authoritative MySQL role `MANAGER` and status `ACTIVE` may list, view, confirm, reject, or cancel bookings across all tutors and students for administrative supervision.

---

## 10. Tutor Eligibility Safeguards

Before creating any booking, `BookingService::createBooking()` invokes `TutorService::isBookable($tutorUserId)`. A tutor is eligible to receive bookings **only if**:
1. User account exists with status `ACTIVE`.
2. Tutor profile has `approval_status = 'APPROVED'`.
3. Tutor profile has `dbs_status = 'VERIFIED'` (Enhanced DBS clearance confirmed by a Manager).

Attempting to book a tutor who is `PENDING`, `SUSPENDED`, or `REJECTED` throws `ValidationException('Tutor is currently not eligible for booking...', 'TUTOR_NOT_BOOKABLE', 403)`.

---

## 11. Database Schema & Migration Assessment

Inspection of migration files `003_create_availability_and_bookings_tables.sql` and `004_create_booking_history_and_lesson_notes_tables.sql` confirmed that the existing database tables completely satisfy Phase 7 requirements:
- `availability_slots`: (`id`, `tutor_user_id`, `starts_at_utc`, `ends_at_utc`, `status`)
- `bookings`: (`id`, `student_user_id`, `child_id`, `tutor_user_id`, `slot_id`, `status`, `inquiry_notes`, `proposed_starts_at_utc`, `proposed_ends_at_utc`, `confirmed_starts_at_utc`, `confirmed_ends_at_utc`, `created_at`, `updated_at`)
- `booking_status_history`: (`id`, `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`)
- `audit_logs`: (`id`, `action`, `entity_type`, `entity_id`, `actor_user_id`, `metadata`, `ip_address`, `user_agent`, `created_at`)

**Result**: Zero new database migrations were required. No destructive schema operations were executed.

---

## 12. API Specifications

### `POST /api/bookings.php`
- **Purpose**: Create a new booking request.
- **Auth**: Bearer Firebase ID Token (Role: `STUDENT_PARENT` or `MANAGER`).
- **Request Body (JSON)**:
  ```json
  {
    "tutor_user_id": 200,
    "slot_id": 45,
    "child_id": 21,
    "notes": "Preparation for upcoming GCSE Mock exams in November."
  }
  ```
- **Responses**:
  - `201 Created`: Booking created in `PENDING` status.
  - `401 Unauthorized`: Missing or invalid Firebase authentication.
  - `403 Forbidden`: Role not permitted, account inactive, or child not owned.
  - `404 Not Found`: Child ID or tutor does not exist.
  - `409 Conflict`: Slot is not published or has already been booked.
  - `422 Unprocessable Entity`: Validation failure (past date, mismatch, notes too long).

### `GET /api/bookings.php`
- **Purpose**: List bookings for the authenticated user.
- **Parameters**: `?status=PENDING` (optional filter).
- **Responses**:
  - `200 OK`: Array of booking objects including child and tutor/student details.
  - `401 Unauthorized`: Unauthenticated.

### `GET /api/bookings.php?id={booking_id}`
- **Purpose**: Retrieve details and status history of a specific booking.
- **Responses**:
  - `200 OK`: Booking record + status history array.
  - `403 Forbidden`: IDOR protection (not owner, assigned tutor, or manager).
  - `404 Not Found`: Booking ID does not exist.

### `PATCH / PUT /api/bookings.php?id={booking_id}`
- **Purpose**: Transition booking status (`CONFIRMED`, `REJECTED`, `CANCELLED`).
- **Request Body (JSON)**:
  ```json
  {
    "action": "CONFIRM",
    "reason": "Looking forward to our lesson."
  }
  ```
- **Responses**:
  - `200 OK`: Updated booking record.
  - `403 Forbidden`: Not assigned tutor (or manager).
  - `422 Unprocessable Entity`: Invalid state transition.

---

## 13. Web User Interface & Accessibility

Three fully responsive web views provide accessible user interaction:
1. `public/book-session.php` (`src/Views/book-session.php`):
   - Dynamic tutor banner and subject display.
   - Interactive slot selection showing UK localized time (`Europe/London`) alongside UTC.
   - Dependent selection dropdown populated with parent's owned children (or "Book for Myself").
   - Inquiry notes textarea with live character counter (max 2000 chars).
   - Clear validation error alert styling and CSRF token protection.
2. `public/student-bookings.php` (`src/Views/student-bookings.php`):
   - Status-badged list of all requested, confirmed, and completed lessons.
   - Associated child or student name clearly identified.
   - Direct cancellation button with reason dialog.
3. `public/tutor-bookings.php` (`src/Views/tutor-bookings.php`):
   - Actionable list of pending lesson requests awaiting tutor confirmation.
   - Detailed inquiry notes modal/callout.
   - One-click "Confirm Booking" and "Decline Request" actions with CSRF protection.

### Accessibility Conformance (WCAG 2.2 AA Selected Checks)
- Semantic `<main id="main-content">` landmarks.
- Exactly one `<h1>` heading per view.
- Explicit label association via `for` and `id` attributes.
- High-contrast visual focus indicators (`:focus-visible`).
- Accessible buttons and descriptive link text.

---

## 14. Concurrency Verification Evidence

Verified directly against local MySQL 8.4.9 with two distinct PDO client connections:
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

## 15. Strict Phase Boundaries & Deferred Capabilities

The following capabilities are explicitly deferred to subsequent phases or open client decisions:
1. **Phase 8 (Email)**: No transactional email providers, SMTP clients, or automated booking notification emails were introduced. Internal events/hooks remain clean for Phase 8 integration.
2. **Phase 9 (Manager Admin)**: No comprehensive booking administration dashboard or manager batch tools were built.
3. **Phase 10 (Blog/Newsletter)**: No editorial newsletter or blog modifications were introduced.
4. **Rescheduling Negotiation Workflow**: Full reschedule negotiation workflows, timeout limits, and slot re-assignment remain deferred.
5. **Cancellation Notice Periods**: Cancellation notice limits (e.g. 24h/48h rules) and refund policies remain open client decisions.
6. **Payment Processing**: No Stripe, PayPal, card handling, or payout mechanisms exist in the platform.
7. **Lesson Notes Authoring & Visibility**: Deferred to a subsequent lesson delivery / tutor management phase.
