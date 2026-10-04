# PHASE 1 — DISCOVERY DOCUMENTATION
## UK Tutoring Platform — Production Discovery Specification
**Authoritative Baseline**: Production Master Project Document v2.0 | UK Tutoring Platform  
**Document ID**: `DOC-PHASE-01-DISCOVERY`  
**Status**: COMPLETED & PENDING CLIENT REVIEW  
**Date**: October 2026  

---

## 1. PROJECT OBJECTIVE

The primary objective of the UK Tutoring Platform is to deliver a secure, scalable, responsive, and robust web platform tailored specifically for the United Kingdom educational market. The platform connects parents and students with verified, qualified, and DBS-checked tutors for structured one-to-one tutoring across primary, secondary (GCSE), further education (A-Level), and higher curricula.

### Key Architectural Tenet
* **Identity Provider**: Firebase Authentication handles authentication credentials and token issuance (Email/Password & Google SSO).
* **Application Source of Truth**: MySQL 8+ maintains all application domain state, user profiles, relational permissions, booking state machines, audit trails, and business logic.
* **Zero Client Trust**: The browser/client is never trusted for authorization. All authorization decisions are enforced server-side in PHP 8.x using verified tokens and database roles.

---

## 2. PROJECT SCOPE

### 2.1 In-Scope Functionality
1. **Public Marketing Website**:
   * Fully responsive marketing pages: Home, About Us / DBS Safeguarding Credentials, Curriculum / Subjects Covered, Pricing Models, Testimonials / Success Stories, Contact Us, Blog, and Newsletter Subscription.
   * Target accessibility compliance: WCAG 2.2 AA.
2. **Identity & User Management**:
   * Firebase Authentication integration (Email/Password, Google OAuth).
   * Server-side token verification via Firebase Admin SDK.
   * User role provisioning and enforcement (`STUDENT_PARENT`, `TUTOR`, `MANAGER`).
   * Account profile management for all three user tiers.
3. **Tutor Workflow & Safeguarding**:
   * Tutor application and multi-stage onboarding workflow.
   * DBS credential submission and managerial review/verification.
   * State-controlled bookability (tutors remain strictly non-bookable and hidden from public discovery until manager approval).
4. **Availability & Scheduling**:
   * Concurrency-safe calendar availability management (Draft, Published, Blocked, Booked, Expired).
   * Real-time slot locking during booking creation.
   * Timezone handling: Storage in UTC timestamps (`+00:00`), dynamic display in UK local time (`Europe/London`) across GMT and British Summer Time (BST).
5. **Student/Parent Experience**:
   * Tutor directory with multi-parameter filtering (subjects, curricula, keywords, verified DBS status).
   * Child profile management (multiple children per parent account).
   * Booking creation with inquiry notes and immediate slot locking.
   * Booking history and management dashboard.
6. **Booking Engine & State Machine**:
   * Concurrency-controlled booking requests (`SELECT ... FOR UPDATE`).
   * Full lifecycle management: `PENDING`, `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`, `COMPLETED`.
   * Comprehensive audit and status history tracking (`booking_status_history`).
   * Tutor reschedule proposal and parent accept/decline workflow.
   * Lesson notes with configurable parent/internal visibility.
7. **Transactional Notifications**:
   * Multi-event transactional email notifications powered by an `EmailService` abstraction (no native `mail()`).
8. **Manager Administration**:
   * Administrative dashboard with key operational KPIs.
   * Tutor application review, suspension, and DBS verification queue.
   * User directory administration (Student/Parent, Tutor, Manager).
   * Booking intervention and status inspection.
   * Blog moderation and publishing workflow.
   * Newsletter subscriber suppression and export management.
   * Comprehensive system audit log viewer.
9. **Engineering Privacy & Security**:
   * UK GDPR and PECR technical engineering controls (data minimization, purpose consent, subject rights workflows, suppression lists).
   * Robust security hardening (HSTS, CSP, PDO prepared statements, XSS sanitization, CSRF mitigation, IDOR protection, upload validation).

### 2.2 Out-of-Scope Functionality (Locked for Initial Phase)
* **Real-time Video / Lesson Classroom**: The platform coordinates bookings; live lesson delivery is conducted via external conferencing tools (e.g., Zoom, Google Meet) or in person.
* **Integrated Payment Processing (Inquiry-First Scope)**: The core master document establishes booking requests as inquiry-based; full automated payment gateway processing (e.g., Stripe escrow) is an open client decision / Phase 2 candidate.
* **Custom Chat / In-App Messaging Engine**: Direct communication is handled via inquiry notes, booking comments, and transactional emails.
* **Automated Marketing Campaign Studio**: Newsletter scope covers subscriber capture, double opt-in, consent logging, suppression, and export; bulk newsletter authoring/sending is handled via external transactional/marketing ESPs.
* **Native Mobile Apps (iOS/Android)**: Mobile access is provided via a fully responsive, mobile-first web interface.

---

## 3. USER ROLES & ACCESS MATRIX

The platform enforces three distinct, immutable role boundaries:

```
[ Public Visitor ] (Anonymous)
        │
        ├── Registers as Student/Parent ──> [ STUDENT_PARENT ] (Active)
        │
        ├── Registers as Tutor ───────────> [ TUTOR ] (Pending Review -> Approved)
        │
        └── Provisioned Administratively ─> [ MANAGER ] (Full Administrative Rights)
```

| Capability | Public Visitor | Student / Parent | Tutor | Manager |
| :--- | :---: | :---: | :---: | :---: |
| **Public Pages & Blog** | View | View | View | View / Manage |
| **Tutor Directory Search** | View Approved | View Approved | View Approved | View All (incl. Unapproved) |
| **Manage Own Profile** | — | Yes | Yes | Yes |
| **Manage Children Profiles** | — | Yes (Own) | — | View (Support) |
| **Manage Availability** | — | — | Yes (Own) | Yes (All) |
| **Request / Create Booking** | — | Yes | — | Administrative |
| **Accept / Reject Booking** | — | — | Yes (Assigned) | Yes (All) |
| **Propose Reschedule** | — | — | Yes (Assigned) | Yes (All) |
| **Respond to Reschedule** | — | Yes (Assigned) | — | Yes (All) |
| **Cancel Booking** | — | Yes (Own) | Yes (Assigned) | Yes (All) |
| **View Booking History** | — | Yes (Own) | Yes (Own) | Yes (All) |
| **Author Lesson Notes** | — | — | Yes (Assigned) | View / Audit |
| **View Lesson Notes** | — | Parent-Visible Only | Own Authored | All (Policy Permitting) |
| **Draft Blog Articles** | — | — | Yes (Own Drafts) | Yes |
| **Publish Blog Articles** | — | — | — | Yes |
| **Newsletter Subscribe** | Yes | Yes | Yes | Manage / Export |
| **DBS Credential Review** | — | — | Submit Only | Verify / Reject |
| **User Administration** | — | — | — | Full Access |
| **System Audit Logs** | — | — | — | Full Access |

---

## 4. MAJOR USER JOURNEYS

### Journey 01: Public Visitor Experience
* **Actor**: Anonymous Public Visitor.
* **Starting Point**: Homepage (`/`).
* **Main Steps**:
  1. Visitor lands on the marketing homepage, navigates to About Us, DBS Safeguarding, Subjects, Pricing, or Testimonials.
  2. Accesses the public Tutor Directory (`/tutors.php`), enters search terms (e.g., "GCSE Maths"), selects curriculum filters.
  3. Clicks on an individual tutor card (`/tutor.php?id={id}`) to inspect qualifications, bio, verified safeguarding badge, and published calendar slots.
  4. Reads published educational articles on the Blog (`/blog.php`).
  5. Enters email into the footer newsletter form and accepts consent terms.
* **System Actions**: Serves cached/compiled responsive assets; queries database for active marketing content; applies strict query filters (`status='ACTIVE'`, `approval_status='APPROVED'`, `dbs_status='VERIFIED'`); validates and sanitizes newsletter email input; records timestamped consent.
* **Expected Result**: Fast, accessible, informative browsing experience without exposing private user or booking data.
* **Security & Auth**: No authentication required; rate-limiting applied to public search, contact, and newsletter endpoints; XSS protection on all displayed profile/content fields; strict Content Security Policy (CSP).

### Journey 02: Student / Parent Registration and Login
* **Actor**: Prospective Parent / Student.
* **Starting Point**: Registration Page (`/register.php`) or Login Page (`/login.php`).
* **Main Steps**:
  1. User enters Email and Password or clicks "Sign in with Google".
  2. Firebase client-side SDK creates authentication credential and issues Firebase ID token.
  3. Client application transmits Bearer ID token to backend `/api/auth.php`.
  4. Backend checks MySQL `users` table for matching `firebase_uid`.
  5. If user is new, backend creates `users` record (`role='STUDENT_PARENT'`, `status='ACTIVE'`) and initializes `student_profiles`.
  6. User is redirected to Parent Dashboard (`/parent/dashboard.php`).
* **System Actions**: Server-side token verification using Firebase Admin SDK; extracts verified email and UID; maps identity to relational MySQL user record; establishes secure session / authorized API context.
* **Expected Result**: Verified, authenticated session established; user lands on student/parent portal.
* **Security & Auth**: Client role input is strictly ignored; backend sets role to `STUDENT_PARENT`; tokens verified cryptographically against Google public keys; secure session cookies configured with `HttpOnly`, `Secure`, and `SameSite=Lax`.

### Journey 03: Tutor Registration Journey
* **Actor**: Prospective Tutor.
* **Starting Point**: Tutor Join Page (`/tutors/join.php` or `/register.php?type=tutor`).
* **Main Steps**:
  1. Candidate signs up via Firebase Authentication (Email/Password or Google SSO).
  2. Backend provisions `users` record with `role='TUTOR'` and initial `status='PENDING'`.
  3. Backend provisions `tutor_profiles` with `approval_status='PENDING'` and `dbs_status='NOT_SUBMITTED'`.
  4. Tutor is directed to multi-step Onboarding Wizard: enters professional headline, detailed bio, qualifications, subjects taught (JSON), and curricula supported (JSON).
  5. Tutor receives prominent portal notification: *"Your profile is under review by our safeguarding team. You cannot publish availability or receive bookings until verified."*
* **System Actions**: Validates input formats and text lengths; populates `tutor_profiles`; logs registration event in `audit_logs`; notifies platform manager queue.
* **Expected Result**: Tutor profile stored in pending state; tutor access restricted to profile completion screens.
* **Security & Auth**: Tutor is explicitly prevented from publishing availability or appearing in directory; API endpoints enforce `approval_status='APPROVED'` check.

### Journey 04: Tutor Approval & DBS Verification Workflow
* **Actor**: Tutor & Platform Manager.
* **Starting Point**: Tutor Dashboard (DBS Submission) & Manager Safeguarding Console (`/manager/dbs-review.php`).
* **Main Steps**:
  1. Tutor submits DBS verification metadata (certificate number, issue date, disclosure type, or secure document reference per client policy).
  2. Profile `dbs_status` transitions to `SUBMITTED`.
  3. Manager logs into Manager Dashboard, views pending tutor verification queue.
  4. Manager inspects credentials, verifies official DBS register / documentation, and selects "Approve & Verify" (or "Reject" / "Request More Info").
  5. Manager confirms action.
* **System Actions**:
  * Backend updates `tutor_profiles`: `dbs_status='VERIFIED'`, `approval_status='APPROVED'`, `approved_at=NOW()`, `approved_by=$managerId`.
  * Generates immutable entry in `audit_logs` detailing manager action, actor ID, and timestamp.
  * Dispatches transactional email to tutor via `EmailService` notifying them of approval.
* **Expected Result**: Tutor profile unlocked; tutor can now create published availability and is indexed in public directory.
* **Security & Auth**: Only authenticated `MANAGER` role can access approval APIs; audit trail records full verification metadata; sensitive DBS evidence is never exposed to public or student roles.

### Journey 05: Tutor Profile & Directory Management
* **Actor**: Approved Tutor & Searching Parent.
* **Starting Point**: Tutor Dashboard (`/tutor/profile.php`) & Public Directory (`/tutors.php`).
* **Main Steps**:
  1. Tutor updates bio, subjects, qualifications, or profile photo.
  2. System validates input against length and schema constraints and saves changes.
  3. Parent navigates to public directory, selects subjects (e.g., "KS3 English"), filters by availability.
  4. Search results display tutor profile card with "DBS Verified" badge.
* **System Actions**: Executes indexed search query; joins `users` and `tutor_profiles`; enforces `status='ACTIVE'`, `approval_status='APPROVED'`, and `dbs_status='VERIFIED'`.
* **Expected Result**: Real-time profile updates reflect accurately in directory; search results are fast and paginated.
* **Security & Auth**: Object ownership verification (`user_id = $currentUser['id']`); parameterized SQL queries prevent SQL injection; HTML output sanitized against XSS.

### Journey 06: Tutor Availability Management
* **Actor**: Approved Tutor.
* **Starting Point**: Tutor Calendar Console (`/tutor/availability.php`).
* **Main Steps**:
  1. Tutor views dynamic interactive calendar in UK local time (`Europe/London`).
  2. Selects date and time window (e.g., Thursday 16:00 - 17:00).
  3. Clicks "Publish Slot".
  4. Can also select unbooked published slots to "Block" or "Delete".
* **System Actions**:
  * Validates that slot start time is in the future and `ends_at > starts_at`.
  * Converts UK local time to UTC storage format (`starts_at_utc`, `ends_at_utc`).
  * Executes overlap check: ensures no existing slots for this tutor overlap this window.
  * Inserts record into `availability_slots` with `status='PUBLISHED'`.
* **Expected Result**: New slot displayed on tutor calendar and made visible to parents on tutor profile page.
* **Security & Auth**: Enforces tutor ownership (`tutor_user_id = $currentUser['id']`); prevents modification of slots with status `BOOKED`; atomic database transactions prevent race conditions.

### Journey 07: Student / Parent Tutor Discovery
* **Actor**: Parent / Student.
* **Starting Point**: Directory Page (`/tutors.php`).
* **Main Steps**:
  1. Parent enters search criteria (keyword, subject, curriculum stage).
  2. Browses filtered list of verified tutors.
  3. Clicks "View Profile" on selected tutor.
  4. Views complete credentials, subjects, bio, and upcoming available published slots.
  5. Selects desired time slot and clicks "Request Lesson".
* **System Actions**: Directory API validates query parameters; queries published availability; renders slots dynamically converted to UK local time.
* **Expected Result**: Parent seamlessly transitions from browsing to booking initiation.
* **Security & Auth**: Blocked, draft, and already booked slots are filtered out; only approved tutors are discoverable.

### Journey 08: Child / Student Profile Management
* **Actor**: Parent.
* **Starting Point**: Parent Dashboard -> Children (`/parent/children.php`).
* **Main Steps**:
  1. Parent clicks "Add Child".
  2. Enters first name, optional last name, date of birth, school year (e.g., "Year 10"), and curriculum focus (e.g., "GCSE Edexcel").
  3. Saves child record; can view, edit, or deactivate child profiles at any time.
* **System Actions**: Validates date formats and required fields; inserts record into `children` table with `parent_user_id = $currentUser['id']`.
* **Expected Result**: Child profiles available in dropdown selection during booking request.
* **Security & Auth**: IDOR prevention: Parent can only view/modify children where `parent_user_id` matches authenticated user; child data protected under UK GDPR child privacy considerations.

### Journey 09: Booking Request Creation
* **Actor**: Parent / Student.
* **Starting Point**: Tutor Booking Screen (`/booking.php?tutor_id={id}&slot_id={slot_id}`).
* **Main Steps**:
  1. Parent confirms selected tutor, date, and time slot.
  2. Selects child profile (or self, if student registration permitted).
  3. Enters inquiry notes (student's learning goals, areas of difficulty).
  4. Clicks "Confirm Booking Request".
* **System Actions**:
  * Initiates database transaction (`PDO::beginTransaction()`).
  * Issues `SELECT ... FROM availability_slots WHERE id = ? AND tutor_user_id = ? FOR UPDATE`.
  * Verifies slot status is strictly `PUBLISHED`.
  * Inserts new record into `bookings` (`status='PENDING'`).
  * Updates `availability_slots.status='BOOKED'`.
  * Records transition in `booking_status_history` (`new_status='PENDING'`).
  * Commits database transaction (`PDO::commit()`).
  * Triggers transactional notification email to tutor.
* **Expected Result**: Slot locked instantly; booking created in `PENDING` state; parent redirected to booking confirmation page; tutor receives email alert.
* **Security & Auth**: Row-level locking prevents concurrent double-booking; child ID ownership verified; rate limiting enforced on booking creation.

### Journey 10: Booking Acceptance / Rejection
* **Actor**: Assigned Tutor.
* **Starting Point**: Tutor Dashboard -> Booking Inquiries (`/tutor/bookings.php`).
* **Main Steps**:
  1. Tutor reviews pending booking request, student year, and inquiry notes.
  2. Tutor clicks "Accept Booking" OR "Reject Booking" (providing rejection reason).
  3. Submits decision.
* **System Actions**:
  * Starts database transaction with `SELECT ... FOR UPDATE` on `bookings`.
  * Verifies booking is in `PENDING` state and belongs to authenticated tutor.
  * If Accepted: updates `bookings.status='CONFIRMED'`; sets `confirmed_starts_at_utc`.
  * If Rejected: updates `bookings.status='REJECTED'`; releases `availability_slots.status='PUBLISHED'` (or per client policy).
  * Logs action in `booking_status_history` with actor ID and reason.
  * Commits transaction.
  * Sends transactional email notification to parent.
* **Expected Result**: Booking state updated; parent receives immediate notification of tutor decision.
* **Security & Auth**: Strict object ownership check (`tutor_user_id = $currentUser['id']`); state machine prevents invalid transitions.

### Journey 11: Rescheduling Workflow
* **Actor**: Tutor (Proposer) & Parent (Responder).
* **Starting Point**: Booking Detail Page (`/bookings/{id}`).
* **Main Steps**:
  1. Tutor needs to adjust lesson time; clicks "Propose Reschedule".
  2. Selects new available date/time window and provides explanation.
  3. Booking transitions to `RESCHEDULE_PROPOSED`. Parent receives email notification with proposal details.
  4. Parent opens dashboard, reviews proposed alternative time.
  5. Parent clicks "Accept Proposal" OR "Decline Proposal".
* **System Actions**:
  * Validates proposed time availability using row locks.
  * If Accepted: updates booking confirmed times to proposed times; updates old/new slots; transitions status to `CONFIRMED`.
  * If Declined: booking transitions to approved fallback state (`PENDING` or `RELEASED` per client decision).
  * Records all transitions in `booking_status_history`.
  * Dispatches notification emails to both parties.
* **Expected Result**: Schedule updated without booking collisions or orphaned slots.
* **Security & Auth**: Only assigned tutor/manager can propose; only assigned parent/manager can respond; concurrency locks prevent conflicting bookings.

### Journey 12: Cancellation Workflow
* **Actor**: Parent, Tutor, or Manager.
* **Starting Point**: Booking Detail Page (`/bookings/{id}`).
* **Main Steps**:
  1. User navigates to an upcoming `PENDING` or `CONFIRMED` booking.
  2. Clicks "Cancel Booking", enters mandatory cancellation reason.
  3. Confirms cancellation modal.
* **System Actions**:
  * Verifies user authorization (must be booking owner, tutor, or manager).
  * Checks booking state (cannot cancel `COMPLETED` or already `CANCELLED` bookings).
  * Updates `bookings.status='CANCELLED'`.
  * Releases associated `availability_slots` back to `PUBLISHED` (or `BLOCKED` if within late cancellation window).
  * Records transition in `booking_status_history` and `audit_logs`.
  * Dispatches cancellation notice email to counterpart.
* **Expected Result**: Booking marked cancelled; schedule slot cleared; both parties notified.
* **Security & Auth**: Ownership validation; state-machine validation prevents cancelling historical/completed sessions.

### Journey 13: Lesson Notes Workflow
* **Actor**: Assigned Tutor & Parent.
* **Starting Point**: Completed Lesson View (`/tutor/bookings/{id}/notes`).
* **Main Steps**:
  1. Following a confirmed lesson, tutor accesses lesson notes form.
  2. Enters summary of topics covered, student strengths, homework assigned, and next steps.
  3. Sets visibility: `INTERNAL` (tutor-only personal notes) or `PARENT_VISIBLE` (shared feedback).
  4. Clicks "Save Lesson Notes".
  5. Parent logs in, navigates to booking history, and reads shared lesson notes.
* **System Actions**: Validates notes content; inserts/updates `lesson_notes`; checks visibility flag when parent queries API.
* **Expected Result**: High-value academic feedback loop established between tutor and parent.
* **Security & Auth**: Strict tutor authorship check; parents are strictly prevented from querying `INTERNAL` notes via API; input sanitized against XSS.

### Journey 14: Manager Dashboard & Platform Administration
* **Actor**: Platform Manager.
* **Starting Point**: Manager Dashboard (`/manager/dashboard.php`).
* **Main Steps**:
  1. Manager logs in; dashboard displays live platform metrics (active tutors, pending approvals, total bookings, today's lessons, active inquiries).
  2. Manager reviews and approves/rejects tutor applications and DBS submissions.
  3. Searches and filters user accounts; suspends or reactivates accounts if necessary.
  4. Inspects disputed bookings, reviews complete audit logs and status histories.
  5. Reviews newsletter subscriber counts and moderation queues.
* **System Actions**: Executes server-side role check (`requireRole($user, ['MANAGER'])`); aggregates platform metrics; logs administrative actions in `audit_logs`.
* **Expected Result**: Full platform governance, safeguarding oversight, and operational control.
* **Security & Auth**: Every manager API endpoint enforces role authorization; all status modifications, impersonations (if any), and approvals logged with actor IP hash and timestamp.

### Journey 15: Blog Authoring & Moderation
* **Actor**: Tutor (Author) & Manager (Moderator).
* **Starting Point**: Tutor Blog Console (`/tutor/blog/`) & Manager Blog Queue (`/manager/blog/`).
* **Main Steps**:
  1. Approved tutor writes educational article (title, slug, excerpt, body).
  2. Saves as `DRAFT` or clicks "Submit for Review" (`SUBMITTED`).
  3. Manager views moderation queue, inspects content, quality, and safeguarding compliance.
  4. Manager approves and publishes article (`status='PUBLISHED'`, `published_at=NOW()`) or rejects with editorial feedback.
  5. Published post becomes visible on public `/blog.php` website.
* **System Actions**: Generates unique URL slug; sanitizes HTML/Markdown content server-side; records reviewer ID and review timestamp.
* **Expected Result**: Controlled content publication that enhances platform SEO and thought leadership without risking unsafe user content.
* **Security & Auth**: Tutors can only edit their own draft/rejected articles; server-side sanitization strips `<script>`, `<iframe>`, and malicious attributes; only managers can trigger `PUBLISHED` state.

### Journey 16: Newsletter Subscription Lifecycle
* **Actor**: Public Visitor / Parent.
* **Starting Point**: Public Newsletter Widget (`/newsletter.php`).
* **Main Steps**:
  1. Visitor enters email, ticks explicit marketing consent checkbox, and submits.
  2. If double opt-in is enabled by client policy: system sends confirmation email with unique token link; user clicks link to activate.
  3. Status becomes `ACTIVE`.
  4. Any email sent contains a functional, one-click unsubscribe link.
  5. User clicks unsubscribe link: status transitions to `UNSUBSCRIBED` / `SUPPRESSED`.
* **System Actions**:
  * Validates email format; hashes confirmation/unsubscribe tokens with SHA-256.
  * Records `consent_at` timestamp.
  * Updates subscriber status in `newsletter_subscribers`.
* **Expected Result**: Frictionless subscription and instant, compliant opt-out mechanism.
* **Security & Auth**: Rate limiting on submission; tokens stored as hashes; unsubscribe does not require authentication; subscriber email addresses protected against public harvesting.

---

## 5. AUTHENTICATION & AUTHORIZATION SPECIFICATION

### 5.1 Architecture & Identity Boundary
The platform architecture strictly separates identity verification from authorization:

```
[ Browser / Client ]
       │  (1) Authenticates with Firebase Client SDK (Email/Password or Google)
       ▼
[ Firebase Auth ] ──> Issues signed Firebase ID Token (JWT)
       │
       │  (2) Sends request with Bearer ID Token in Authorization header
       ▼
[ PHP Application / API ]
       │
       ├── (3) Verifies ID Token using Firebase Admin SDK (cryptographic signature)
       ├── (4) Extracts verified Firebase UID (sub claim)
       │
       ▼
[ MySQL 8+ Database ]
       │  (5) Queries users WHERE firebase_uid = ?
       │  (6) Retrieves application role (STUDENT_PARENT, TUTOR, MANAGER) & status
       ▼
[ Server-Side Authorization Check ]
       │  (7) requireRole($user, $allowedRoles)
       │  (8) Object ownership check ($record['owner_id'] === $user['id'])
       ▼
[ Business Execution / JSON Response ]
```

### 5.2 Mandatory Security Rules
1. **Never Trust Client-Supplied Roles**: The frontend is treated as untrusted. No role, permission, or status sent in request bodies or query parameters is accepted.
2. **Server-Side Token Verification**: Every protected endpoint validates the Firebase ID token signature, expiration, and issuer server-side via the Firebase Admin SDK.
3. **Database is Source of Truth**: User identity is mapped via `users.firebase_uid`. The relational `users.role` column alone governs access.
4. **Account Status Verification**: Accounts with status other than `ACTIVE` (e.g., `PENDING`, `SUSPENDED`, `DELETED`) are rejected with `403 Forbidden`.
5. **IDOR & Object Ownership Enforcement**: In addition to role checks, endpoints verify that the authenticated user owns the target record (e.g., tutor booking ID, parent child ID) before returning or modifying data.

---

## 6. BOOKING SPECIFICATION & STATE MACHINE

### 6.1 State Machine Lifecycle
The booking engine transitions strictly through approved states:

```
                  ┌───────────────┐
                  │    PENDING    │
                  └───────┬───────┘
          ┌───────────────┼───────────────┬────────────────┐
          ▼               ▼               ▼                ▼
   ┌─────────────┐ ┌─────────────┐ ┌─────────────┐  ┌─────────────┐
   │  CONFIRMED  │ │  REJECTED   │ │  CANCELLED  │  │SYS_CANCELLED│
   └──────┬──────┘ └─────────────┘ └─────────────┘  └─────────────┘
          │
          ├───────────────────────────────┐
          ▼                               ▼
   ┌─────────────┐                 ┌─────────────┐
   │  COMPLETED  │                 │  CANCELLED  │
   └─────────────┘                 └─────────────┘

   Reschedule Pathway (from PENDING):
   PENDING ──> RESCHEDULE_PROPOSED
                     │
                     ├── Parent Accepts ──> CONFIRMED (New Times)
                     └── Parent Declines ─> Fallback (PENDING or RELEASED)
```

### 6.2 Concurrency & Double-Booking Prevention
* **Atomic Transactions**: Booking creation and status updates execute inside isolated InnoDB database transactions (`PDO::beginTransaction()`).
* **Pessimistic Row Locking**: Slots are evaluated with `SELECT ... FROM availability_slots WHERE id = ? FOR UPDATE`.
* **State Verification**: If the slot is not strictly in `PUBLISHED` status, the transaction rolls back immediately and returns an unambiguous error (`BOOKING_SLOT_UNAVAILABLE`).
* **Audit History**: Every transition creates an immutable record in `booking_status_history` recording `old_status`, `new_status`, `changed_by_user_id`, `reason`, and timestamp.

---

## 7. TUTOR WORKFLOW & SAFEGUARDING

### 7.1 Lifecycle Progression
1. **Registered**: Account created via Firebase; database record set to `role='TUTOR'`, `status='PENDING'`.
2. **Profile Completed**: Professional bio, qualifications, subjects, and curricula entered.
3. **DBS Submitted**: Safeguarding details provided by tutor (`dbs_status='SUBMITTED'`).
4. **Under Manager Review**: Application appears in manager review queue.
5. **Manager Approved**: Manager verifies credentials and marks `approval_status='APPROVED'`.
6. **Bookable & Public**: Only at this point can the tutor publish calendar slots and appear in public search.

### 7.2 Managerial Safeguarding Controls
* Manager can reject an application at any point (`approval_status='REJECTED'`).
* Manager can suspend an active tutor immediately (`approval_status='SUSPENDED'`), which instantly removes the tutor from public search and cancels/blocks unpublished availability.
* Sensitive DBS records are viewable strictly by authorized managers; students and parents only see a verified badge.

---

## 8. DATA, PRIVACY & SECURITY CONSIDERATIONS

### 8.1 UK GDPR & PECR Engineering Baseline
* **Data Minimization**: Collect only necessary information required to arrange educational tutoring and maintain safeguarding.
* **Lawful Basis & Consent**: Separate explicit consent tracking for marketing communications with exact UTC timestamps.
* **Data Subject Rights Workflow**: Engineering hooks to support Subject Access Requests (SAR), data rectification, and erasure ("Right to be Forgotten") with managerial audit logging.
* **Suppression Lists**: Unsubscribed newsletter users are retained on an automated suppression list to prevent accidental re-solicitation.
* **Disclaimer**: Technical controls fulfill engineering specifications; client-approved legal documentation (Privacy Notice, Terms of Service, Cookie Policy) remains required.

### 8.2 DBS & Sensitive Data Handling
* Avoid storing full unencrypted scans of DBS certificates unless mandated by client operational policy.
* Verification metadata (certificate number hash, issue date, disclosure level, verified by) stored in access-restricted relational columns.
* If file uploads are enabled: files stored outside web root, randomized filenames, strict MIME verification, encrypted storage at rest.

### 8.3 Application Hardening
* **SQL Injection**: 100% PDO prepared statements; zero dynamic SQL string concatenation.
* **Cross-Site Scripting (XSS)**: Context-aware output encoding; HTML Purifier / sanitization on rich-text/markdown fields.
* **Cross-Site Request Forgery (CSRF)**: CSRF tokens required on state-changing session forms; SameSite cookies.
* **Security Headers**: HSTS, strict CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`.
* **Rate Limiting**: Applied to login verification, booking submissions, newsletter subscription, and contact forms.

---

## 9. OPEN CLIENT DECISIONS / REQUIREMENTS TO CONFIRM

The following table details every unresolved business, operational, and legal decision identified in Section 36 of the Master Project Document v2.0. These decisions must be confirmed by the client prior to final implementation:

| # | Item / Decision Area | Why It Matters | Master Document v2.0 Baseline Position | What Must Be Confirmed by Client |
| :-: | :--- | :--- | :--- | :--- |
| **01** | **Parent vs Direct Student Registration** | Governs age gating, terms of service, child safeguarding, and registration form fields. | Baseline accommodates `STUDENT_PARENT` role with a `children` table for parents managing dependents. | Can adult learners (18+) register directly without a parent entity, or is the platform strictly for parents/guardians? |
| **02** | **Multiple Children & Guardians** | Determines schema cardinality and permission boundaries for family units. | `children` table links to `parent_user_id`. One parent can have multiple children. | Can a single child profile be linked to multiple parent/guardian accounts (e.g., shared custody)? |
| **03** | **Lesson Delivery Mode** | Affects tutor profile filters, booking location fields, and safeguarding guidance. | Master document focuses on directory search and booking inquiries. | Are lessons conducted online only, in-person only, or can tutors offer both hybrid modes? |
| **04** | **Payment Model vs Inquiry-Only** | Fundamentally dictates financial architecture, PCI compliance, refund handling, and checkout workflows. | Baseline is an **inquiry-first booking workflow** without integrated payment processing. | Is the platform launching as inquiry-only (payments handled offline), or is an escrow/Stripe payment gateway required for Phase 1? |
| **05** | **Subjects & Curricula Taxonomy** | Governs search filters, database index structures, and tutor profile onboarding tags. | Uses flexible JSON storage (`subjects_json`, `curriculum_json`) in `tutor_profiles`. | What is the exhaustive, authoritative list of searchable UK subjects, exam boards (AQA, Edexcel, OCR), and levels (KS2, GCSE, A-Level)? |
| **06** | **Tutor Publishing Rules** | Dictates tutor profile lifecycle and onboarding friction. | Approved tutors can publish availability; approval is granted by managers. | Once a manager approves a tutor, are they immediately live and bookable, or does the tutor retain a manual "Publish Profile" toggle? |
| **07** | **DBS Evidence Collection** | Impacts safeguarding compliance, storage encryption, risk profile, and file storage architecture. | Collects `dbs_status` enum and verification metadata. | Does the platform store physical DBS certificate scans/PDFs, or does the manager verify certificates externally and record certificate numbers only? |
| **08** | **DBS Data Retention Period** | Legal requirement under UK GDPR and DBS safeguarding guidelines. | Recommends client-approved retention policy; advises against hard-coding arbitrary periods. | What is the exact retention period for DBS verification data following tutor offboarding (e.g., 6 months, 1 year, 7 years)? |
| **09** | **Reschedule Expiry & Slot Behavior** | Prevents deadlocked schedules when a proposed reschedule is pending response. | Proposes `RESCHEDULE_PROPOSED` status with parent acceptance/rejection. | How many hours/days before a proposed reschedule expires, and does a declined reschedule return the booking to `PENDING` or cancel it? |
| **10** | **Cancellation Notice & Policy** | Dictates booking state machine constraints and user terms. | Allows cancellation with status tracking and reason capture. | What is the minimum cancellation notice period (e.g., 24 hours), and can tutors cancel confirmed sessions unilaterally? |
| **11** | **Recurring Availability Slots** | Impacts slot generation algorithms and database performance. | Baseline specifies discrete start/end slots in `availability_slots`. | Are tutors required to create slots individually, or is an automated recurring weekly slot generator required? |
| **12** | **Lesson Notes Visibility** | Impacts academic privacy, safeguarding, and parent engagement. | Schema supports `visibility` ENUM (`INTERNAL`, `PARENT_VISIBLE`). | Should parents have default access to lesson progress notes, or should notes remain strictly internal unless explicitly shared? |
| **13** | **Newsletter Double Opt-In** | Direct PECR / UK GDPR marketing compliance requirement. | Schema includes `confirmation_token_hash` and `status='PENDING'`. | Does the client require a strict double opt-in confirmation email, or is single opt-in with timestamped consent sufficient? |
| **14** | **Newsletter Functional Scope** | Clarifies boundaries between local web app and external marketing tools. | Stores subscribers, consent, and suppression records in MySQL. | Is the platform simply collecting and exporting subscribers, or is an integrated email campaign broadcasting tool expected? |
| **15** | **Transactional Email Service Provider** | Determines API integration libraries, SDK dependencies, and DNS SPF/DKIM records. | Specifies an abstract `EmailService` interface supporting SMTP/API. | Which transactional email service will be provisioned (e.g., Mailgun, SendGrid, Postmark, AWS SES, or custom SMTP)? |
| **16** | **Payment Gateway Provider (if applicable)** | Affects future billing architecture and API contracts. | Deferred in v2.0 master specification. | If payments are added in a future phase, which gateway is preferred (Stripe, PayPal, GoCardless)? |
| **17** | **Cookies & Analytics Services** | Dictates Cookie Banner complexity and PECR consent classification. | Public pages require clear privacy controls and secure headers. | Which analytics or tracking tools will be deployed (e.g., Google Analytics 4, Plausible, Cookiebot, or essential-only)? |
| **18** | **Legal Documents & Operational Policies** | Critical for production go-live acceptance and regulatory compliance. | Engineering documents do not replace legal advice. | Who is providing the client-approved Terms of Service, Privacy Notice, Safeguarding Policy, and Cookie Policy text? |
| **19** | **Backup RPO & RTO Targets** | Establishes infrastructure requirements for automated backups and failover. | Recommends daily logical backups at minimum; PITR requires binary logging. | What are the client's agreed Recovery Point Objective (e.g., 24 hours) and Recovery Time Objective (e.g., 4 hours)? |

---

## 10. PRELIMINARY REQUIREMENT TRACEABILITY MATRIX

| Requirement ID | Requirement Summary | Master Doc v2.0 Source | Category | Status | Notes |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **DISC-001** | Production Stack Compliance | Section 0, 1 | Architecture | **CONFIRMED** | PHP 8.x, MySQL 8+, HTML5, Tailwind CSS, Vanilla JS. |
| **DISC-002** | Firebase Identity Provider | Section 1, 2, 8 | Authentication | **CONFIRMED** | Firebase handles authentication; issues JWT ID tokens. |
| **DISC-003** | Server-Side Token Verification | Section 2, 8 | Security | **CONFIRMED** | Firebase Admin SDK verifies Bearer tokens on protected APIs. |
| **DISC-004** | MySQL Application Authority | Section 1, 2, 5 | Database | **CONFIRMED** | MySQL is source of truth for users, roles, and business data. |
| **DISC-005** | Role Mapping via Database | Section 2, 8, 9 | Authorization | **CONFIRMED** | Firebase UID maps to `users.firebase_uid`; role set in MySQL. |
| **DISC-006** | Student / Parent Role | Section 3, 5 | Users | **CONFIRMED** | Role `STUDENT_PARENT` for searching, child profiles, and bookings. |
| **DISC-007** | Tutor Role | Section 3, 5 | Users | **CONFIRMED** | Role `TUTOR` for profiles, availability, and lesson notes. |
| **DISC-008** | Manager Role | Section 3, 5, 17 | Users | **CONFIRMED** | Role `MANAGER` for platform governance, approvals, and audits. |
| **DISC-009** | Object Ownership (IDOR) Checks | Section 9, 20 | Security | **CONFIRMED** | Endpoints verify record ownership; no cross-tenant leakage. |
| **DISC-010** | Untrusted Client Role Rejection | Section 2, 9 | Security | **CONFIRMED** | Client-supplied role parameters are strictly rejected. |
| **DISC-011** | Public Marketing Pages | Section 1, 28 | Public | **CONFIRMED** | Home, About, Subjects, Pricing, Testimonials, Contact, Blog. |
| **DISC-012** | WCAG 2.2 AA Accessibility Target | Section 22 | Accessibility | **CONFIRMED** | Keyboard navigation, focus states, contrast, aria announcements. |
| **DISC-013** | Mobile-First Responsive Design | Section 1, 22 | Frontend | **CONFIRMED** | Verified across phone, tablet, and desktop viewports. |
| **DISC-014** | Static Compiled Tailwind CSS | Section 0, 21 | Frontend | **CONFIRMED** | No runtime Tailwind CDN in production; compiled assets only. |
| **DISC-015** | Content Security Policy (CSP) | Section 21 | Security | **CONFIRMED** | Strict CSP allowing only self and trusted Firebase/Google domains. |
| **DISC-016** | Tutor Registration Workflow | Section 4.1, 5 | Tutor | **CONFIRMED** | Tutor registered in `PENDING` status; requires profile completion. |
| **DISC-017** | Tutor Bookability Gate | Section 2, 4.1 | Safeguarding | **CONFIRMED** | Tutors remain non-bookable and hidden until manager approved. |
| **DISC-018** | DBS Submission Workflow | Section 4.1, 24 | Safeguarding | **CONFIRMED** | Tutor submits DBS info; status tracked in `tutor_profiles`. |
| **DISC-019** | DBS Evidence Scope | Section 24, 36 | Safeguarding | **OPEN** | Decision required on certificate file upload vs number only. |
| **DISC-020** | DBS Data Retention Policy | Section 24, 36 | Privacy | **OPEN** | Client retention duration policy required before go-live. |
| **DISC-021** | Manager Tutor Approval Action | Section 4.1, 17 | Management | **CONFIRMED** | Manager explicitly approves, rejects, or suspends tutors. |
| **DISC-022** | Availability Slot Creation | Section 5, 15 | Availability | **CONFIRMED** | Tutors create discrete slots with start and end times. |
| **DISC-023** | UTC Storage Timezone | Section 5, 15 | Database | **CONFIRMED** | All timestamps stored in UTC (`+00:00`). |
| **DISC-024** | UK Local Time Display | Section 5, 15 | Localization | **CONFIRMED** | Frontend/display converts UTC to `Europe/London` (GMT/BST). |
| **DISC-025** | Availability Overlap Prevention | Section 15 | Availability | **CONFIRMED** | Database/service prevents overlapping slots for same tutor. |
| **DISC-026** | Recurring Availability Engine | Section 15, 36 | Availability | **OPEN** | Client decision required on whether recurring rules are needed. |
| **DISC-027** | Public Tutor Directory Search | Section 13, 14 | Discovery | **CONFIRMED** | Public search filtering by subject, curriculum, and keywords. |
| **DISC-028** | Directory Safeguarding Gate | Section 14 | Safeguarding | **CONFIRMED** | Directory strictly returns active, approved, and verified tutors. |
| **DISC-029** | Authoritative Subject Taxonomy | Section 14, 36 | Discovery | **OPEN** | Formal taxonomy of UK subjects and exam boards required. |
| **DISC-030** | Tutor Publishing Control | Section 36 | Tutor | **OPEN** | Immediate live publishing vs tutor-controlled visibility toggle. |
| **DISC-031** | Child Profile Management | Section 5 | Student | **CONFIRMED** | Parents manage multiple children linked to parent account. |
| **DISC-032** | Direct Adult Student Sign-up | Section 36 | Student | **OPEN** | Confirmation required on whether 18+ students register solo. |
| **DISC-033** | Multiple Guardian Linking | Section 36 | Student | **OPEN** | Confirmation required on whether children link to multiple parents. |
| **DISC-034** | Concurrency-Safe Booking Lock | Section 11 | Booking | **CONFIRMED** | `SELECT ... FOR UPDATE` prevents double-booking race conditions. |
| **DISC-035** | Booking Creation Transaction | Section 11 | Booking | **CONFIRMED** | Slot locked, booking inserted, and history logged in one commit. |
| **DISC-036** | Booking Status Lifecycle | Section 4.2, 5 | Booking | **CONFIRMED** | Full lifecycle: PENDING, CONFIRMED, REJECTED, RESCHEDULE_PROPOSED, CANCELLED, SYSTEM_CANCELLED, COMPLETED. |
| **DISC-037** | Booking Status History Audit | Section 5, 25 | Audit | **CONFIRMED** | `booking_status_history` logs all transitions with actor and reason. |
| **DISC-038** | Tutor Accept / Reject API | Section 12, 13 | Booking | **CONFIRMED** | Tutors accept/reject pending bookings; updates slot state. |
| **DISC-039** | Reschedule Proposal Flow | Section 4.2, 12 | Booking | **CONFIRMED** | Tutor proposes alternative slot; booking moves to review state. |
| **DISC-040** | Reschedule Expiry & Fallback | Section 4.2, 36 | Booking | **OPEN** | Proposal timeout hours and decline fallback rule to be confirmed. |
| **DISC-041** | Booking Cancellation Window | Section 36 | Booking | **OPEN** | Minimum notice hours for parent/tutor cancellations to be confirmed. |
| **DISC-042** | Lesson Delivery Mode Flag | Section 36 | Booking | **OPEN** | Online vs In-Person vs Hybrid flag requirements to be confirmed. |
| **DISC-043** | Lesson Notes Storage | Section 5 | Notes | **CONFIRMED** | Tutors record notes and feedback linked to booking ID. |
| **DISC-044** | Lesson Notes Visibility Policy | Section 5, 36 | Notes | **OPEN** | Confirmation required on parent visibility defaults. |
| **DISC-045** | Email Service Abstraction | Section 16 | Notification | **CONFIRMED** | `EmailService` interface replaces native PHP `mail()`. |
| **DISC-046** | Transactional Provider Choice | Section 16, 36 | Infrastructure | **OPEN** | Vendor selection required (Mailgun, SendGrid, Postmark, AWS SES). |
| **DISC-047** | Manager KPI Dashboard | Section 17 | Management | **CONFIRMED** | Real-time counts of tutors, pending reviews, bookings, and alerts. |
| **DISC-048** | Administrative User Governance | Section 17 | Management | **CONFIRMED** | Manager can inspect, activate, suspend, or manage any user. |
| **DISC-049** | Immutable Audit Logging | Section 5, 25 | Audit | **CONFIRMED** | `audit_logs` captures actor ID, action, entity, IP hash, user agent. |
| **DISC-050** | Blog Drafting & Moderation | Section 5, 18 | Blog | **CONFIRMED** | Tutors draft articles; managers review, edit, approve, and publish. |
| **DISC-051** | Server-Side Content Sanitization | Section 18, 20 | Security | **CONFIRMED** | HTML/Markdown sanitized before rendering to eliminate XSS. |
| **DISC-052** | Newsletter Subscriber Lifecycle | Section 5, 19 | Newsletter | **CONFIRMED** | Tracks email, consent timestamp, active status, suppression. |
| **DISC-053** | Newsletter Double Opt-In | Section 19, 36 | Compliance | **OPEN** | Double opt-in requirement to be confirmed by client legal counsel. |
| **DISC-054** | Newsletter Scope Boundary | Section 19, 36 | Scope | **OPEN** | Confirmation on subscriber storage vs broadcast composer tool. |
| **DISC-055** | Cookie Banner & Analytics | Section 20, 36 | Compliance | **OPEN** | Analytics service selection and cookie consent tool to be agreed. |
| **DISC-056** | Payment Gateway Integration | Section 36 | Payment | **OPEN** | Phase 1 launch confirmed inquiry-only; future provider open. |
| **DISC-057** | Approved Legal Documentation | Section 23, 36 | Legal | **OPEN** | Client-approved Privacy Notice, Terms, and Safeguarding required. |
| **DISC-058** | Backup Frequency & RPO/RTO | Section 33, 36 | Infrastructure | **OPEN** | Client RPO/RTO disaster recovery metrics to be confirmed. |

---

## 11. TECHNICAL ARCHITECTURE CONSTRAINTS CARRIED FORWARD TO PHASE 2

The technical architecture is locked and non-negotiable:

```
[ Web Browser ]
      │
      │  HTTPS (TLS 1.3, Strict-Transport-Security, Content-Security-Policy)
      ▼
[ Web Server (Apache / Nginx) ]
      │
      ▼
[ PHP 8.x Application Layer ]
      ├── /public          (Front controller & static assets)
      ├── /api             (Strict JSON REST endpoints)
      ├── /src             (Domain services, verifiers, validation)
      │     ├── Auth/FirebaseTokenVerifier.php
      │     ├── Auth/Authorization.php
      │     ├── Database/Database.php (PDO Connection Singleton)
      │     ├── Services/BookingService.php
      │     ├── Services/AvailabilityService.php
      │     ├── Services/EmailService.php
      │     ├── Services/BlogService.php
      │     └── Services/AuditService.php
      └── /config          (App, database, and Firebase configurations)
      │
      ├── [ Firebase Admin SDK ]  --> Validates signed ID tokens cryptographically
      ├── [ MySQL 8+ Database ]   --> InnoDB, utf8mb4, UTC storage, source of truth
      └── [ Transactional ESP ]  --> Transactional email delivery via SMTP/API
```

---

## 12. PHASE BOUNDARY COMPLIANCE

During this Phase 1 Discovery milestone, strict adherence to project boundaries has been maintained:
* **NO** application code or production features have been built.
* **NO** Firebase configuration or service account credentials have been created.
* **NO** production database schema or migration scripts have been executed.
* **NO** business logic or UI mockups have been invented outside the Master Document.
* **NO** unapproved client decisions have been assumed or hard-coded.

This discovery document stands as the definitive, agreed functional and technical baseline required before commencing **Phase 2 — Architecture**.
