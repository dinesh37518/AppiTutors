# Phase 12 — QA / UAT Verification Report
**UK Tutoring Platform (Local Windows Implementation + QA/UAT Pass)**

**Document Reference:** `docs/PHASE-12-QA-UAT-REPORT.md`  
**Execution Date:** 4 October 2026  
**Environment:** Local Windows 11 / Apache 2.4.58 / PHP 8.2.12 / MySQL 8.4.9 (Strict Mode, InnoDB, UTC)  
**Primary Requirements Baseline:** Master Project Document v2.0 — UK Tutoring Platform  
**Secondary Requirements Baseline:** Approved Phase 1–11 Implementation Reports and Source Code  
**Final Status:** **PASS** (100% Cumulative Regression: 500 / 500 Passed)

---

## 1. Executive Summary

Phase 12 represents the comprehensive Quality Assurance (QA) and User Acceptance Testing (UAT) verification pass for the UK Tutoring Platform. The objective of this phase is strictly verification of the integrated platform across all implemented subsystems—not feature development, architecture refactoring, or infrastructure provisioning.

All verification was conducted strictly on the **Local Windows Implementation + Local QA/UAT scope**. No production infrastructure, DNS records, production SSL certificates, commercial email services, commercial payment gateways, or production cloud deployments have been started or provisioned.

### Cumulative Automated Results
* **Phase 3 (Foundation):** 25 / 25 PASSED (100%)
* **Phase 4 (Public Website):** 30 / 30 PASSED (100%)
* **Phase 5 (Tutor Workflow):** 51 / 51 PASSED (100%)
* **Phase 6 (Student / Parent):** 45 / 45 PASSED (100%)
* **Phase 7 (Booking Engine):** 40 / 40 PASSED (100%)
* **Phase 8 (Email Integration):** 33 / 33 PASSED (100%)
* **Phase 9 (Manager Administration):** 39 / 39 PASSED (100%)
* **Phase 10 (Blog & Newsletter):** 83 / 83 PASSED (100%)
* **Phase 11 (Security & Privacy):** 78 / 78 PASSED (100%)
* **Phase 12 (QA / UAT Full Integration):** 76 / 76 PASSED (100%)
* **Cumulative Platform Total:** **500 / 500 PASSED (100.0%)**

---

## 2. Test Execution Details by Domain (DOM-01 through DOM-20)

### DOM-01: Public Website
* **Routes Verified:** `/` (Home), `/about.php`, `/subjects.php`, `/pricing.php`, `/testimonials.php`, `/contact.php`, `/blog.php`, `/newsletter.php`, `/unsubscribe.php`, `/tutors.php`, `/login.php`, `/register.php`.
* **Asset Loading:** CSS stylesheet `/assets/css/app.css` (23,871 bytes) returns HTTP 200 without 404 dependencies.
* **404 Routing:** Non-existent routes return styled HTTP 404 response handler without stack traces.
* **Result:** **PASS** (14/14 checks).

### DOM-02: Authentication & Authorization Boundary
* **Unauthenticated Requests:** Protected endpoints without Bearer token return `401 Unauthorized`.
* **Malformed Tokens:** Malformed JWT Bearer tokens return `401 Unauthorized`.
* **Server-side Role Authority:** MySQL remains the sole authorization authority. Attempts by clients to self-assign `MANAGER` role fail with `403 Forbidden` (`assertCannotSelfAssignManager`).
* **Account Status Boundary:** Suspended user accounts are rejected with `403 Forbidden` (`requireActiveStatus`).
* **Result:** **PASS** (4/4 checks).

### DOM-03: Student / Parent Workflows (Scenario A)
* **Single-Parent Child Relationship:** Parent creates child record (`Oliver Jenkins`, ID 161) and retrieves registered children list.
* **Tutor Directory & Profile Viewing:** Student views approved, verified tutor profile (`Dr Jane Smith`).
* **Booking Creation:** Student books published availability slot; initial status is `PENDING`.
* **Booking History:** Student views their own booking history.
* **Horizontal IDOR Attack:** Parent B attempting to retrieve Parent A child record is rejected with `403 Forbidden`.
* **Result:** **PASS** (6/6 checks).

### DOM-04: Tutor Workflows (Scenario B)
* **Approval Lifecycle Gate:** Unapproved candidate tutor is strictly not bookable (`isBookable` returns `false`).
* **Availability Creation Boundary:** Candidate tutor blocked from creating slots (`BookabilityException`, 403).
* **DBS Submission:** Candidate tutor submits DBS certificate number; status transitions to `SUBMITTED`.
* **Three-Part Bookability Rule:** Manager verifies DBS and approves tutor; tutor becomes bookable only when `ACTIVE + APPROVED + VERIFIED`.
* **Slot Publication:** Approved and verified tutor publishes slot successfully (`PUBLISHED`).
* **Overlap Prevention:** Overlapping availability slot rejected with `422 ValidationException`.
* **Negative Duration:** Slots where `ends_at <= starts_at` are rejected with `422 ValidationException`.
* **Result:** **PASS** (7/7 checks).

### DOM-05: Manager Workflows (Scenario C)
* **Dashboard Metrics:** Real-time KPI aggregation loads successfully (`tutors`, `bookings`, `pending approvals`).
* **Tutor Management:** Administrative tutor list with approval and DBS metadata retrieved.
* **Result:** **PASS** (2/2 checks).

### DOM-06: Availability Engine
* **UTC Storage & London Presentation:** Slots stored in UTC and rendered in Europe/London.
* **Slot Integrity:** Overlaps strictly prevented across all tutor slots.
* **Result:** **PASS** (Verified in Scenario B).

### DOM-07: Booking Engine Lifecycle
* **7 Master Booking States:** Exactly seven allowed states: `PENDING`, `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`, `COMPLETED`.
* **State Integrity:** Unapproved state `RESCHEDULED` does NOT exist in code or database schema.
* **Result:** **PASS** (1/1 check).

### DOM-08: Booking Concurrency & Double-Booking (Scenario E)
* **Physical Row Lock:** Competing transaction 1 acquires exclusive `SELECT ... FOR UPDATE` lock on availability slot.
* **Lock Wait Timeout:** Competing transaction 2 is physically blocked by MySQL InnoDB row-level lock (SQLSTATE 1205 timeout).
* **Integrity Guarantee:** Exactly 1 booking committed; exactly 0 duplicate bookings created for slot.
* **Result:** **PASS** (3/3 checks).

### DOM-09: Email Notification Engine
* **Provider Agnostic:** `EmailService` dispatches transactional email using in-memory `ArrayEmailAdapter` without network dependencies.
* **Dual-Format Rendering:** Both HTML and plain-text fallbacks generated without XSS leakage.
* **Transaction Decoupling:** Database transactions commit prior to email dispatch; email failures do not rollback committed database state.
* **Result:** **PASS** (3/3 checks).

### DOM-10: Blog Editorial Lifecycle
* **Authoring:** Tutor creates post in `DRAFT` status.
* **Submission:** Tutor submits post for review -> status transitions to `SUBMITTED`.
* **Self-Approval Prevention:** Tutor cannot approve own post (`403 Forbidden`).
* **Manager Moderation:** Manager approves post (`APPROVED`) and publishes article (`PUBLISHED`).
* **Archival:** Manager transitions article to `ARCHIVED`.
* **Public Visibility Boundary:** Only `PUBLISHED` posts are exposed to public routes; unpublished posts return 404/403.
* **Result:** **PASS** (5/5 checks).

### DOM-11: Newsletter Management & Privacy
* **Consent Verification:** Subscription without explicit consent rejected with `422 ValidationException`.
* **Double Opt-in Neutrality:** New subscriber persisted in neutral `PENDING` status (`confirmed_at = NULL`).
* **Bypass Prevention:** Manager cannot administratively transition `PENDING` to `ACTIVE` (blocked with `DOUBLE_OPT_IN_OPEN_DECISION`, 422).
* **Suppression (PECR Compliance):** Subscriber transitioned to `SUPPRESSED`; subsequent re-subscription attempts remain `SUPPRESSED`.
* **Unsubscribe Token Handling:** Public token unsubscribe operates without leaking raw tokens or credentials.
* **Result:** **PASS** (5/5 checks).

### DOM-12: DBS Security Boundaries
* **Manager Document Access:** Manager can view/download stored raw DBS PDF documents.
* **Tutor Access Boundary:** Owning tutor is strictly blocked from retrieving raw sensitive DBS documents (`403 Forbidden`).
* **Student/Parent Access Boundary:** Student/Parent users are strictly blocked from retrieving raw DBS documents (`403 Forbidden`).
* **Public Boundary:** Unauthenticated/public users have zero access to DBS storage.
* **Result:** **PASS** (3/3 checks).

### DOM-13: Authorization & IDOR Defenses (Scenario D)
* **Vertical Escalation:** Self-assignment of `MANAGER` role blocked (`403 Forbidden`).
* **Horizontal IDOR:** Student B cannot cancel Student A booking (`403 Forbidden`).
* **Child Record IDOR:** Parent B cannot view Parent A child record (`403 Forbidden`).
* **Result:** **PASS** (3/3 checks).

### DOM-14: Security Hardening & Headers
* **SQL Injection:** SQL injection payloads treated as literal parameters via PDO prepared statements (`count = 0`).
* **XSS Defense:** HTML entities escaped via `View::e()` (`ENT_QUOTES | ENT_SUBSTITUTE`).
* **Protected Files:** Direct HTTP requests to `.env` and `composer.json` return `403 Forbidden`.
* **Defense Headers:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and strict CSP emitted with `object-src 'none'` and without `'unsafe-eval'`.
* **Result:** **PASS** (5/5 checks).

### DOM-15: Privacy & Data Protection
* **DSAR Export:** Data Subject Access Request export package generated including user account, profile, bookings, and children.
* **Neutral Policy Note:** Verifies exact approved neutral policy wording:
  > “Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.”
* **Erasure Deferral:** Account erasure deferred when active/confirmed bookings exist pending policy review.
* **Account Minimization:** Clean eligible accounts have personal data scrubbed (`erased_{id}_{hash}@anonymized.invalid`, name scrubbed, status `DELETED`).
* **Result:** **PASS** (5/5 checks).

### DOM-16: Error Handling & Fault Isolation
* **Safe Error Messages:** Public error handlers return user-friendly messages without exposing database passwords, stack traces, or environment variables.
* **Audit Logging:** System actions logged with sanitized identifiers.
* **Result:** **PASS** (Verified across tests).

### DOM-17: Accessibility Compliance (WCAG 2.2 AA)
* **Skip Link:** Accessible `<a href="#main-content" class="skip-link">` present as the first interactive element (SC 2.4.1).
* **Main Landmark:** `<main id="main-content">` landmark present (SC 1.3.1).
* **Heading Hierarchy:** Strictly single `<h1>` heading per page (SC 1.3.1).
* **Form Association:** Form controls explicitly associated with matching `<label for="contact-name">` and `<input id="contact-name">` attributes (SC 3.3.2).
* **Focus Indicators:** Standardized 3px high-contrast focus indicators styled via `:focus-visible` in `app.css` (SC 2.4.7).
* **Result:** **PASS** (5/5 checks).

### DOM-18: Responsive & Cross-Device Behavior
* **Desktop Viewport (1280x800):** Multi-column grid, persistent horizontal navigation, expanded hero and feature sections.
* **Tablet Viewport (768x1024):** Responsive grid collapse, interactive hamburger navigation toggle verified.
* **Mobile Viewport (375x812):** Single-column layout, touch-friendly touch targets (min 44px), zero horizontal overflow, mobile drawer navigation verified.
* **Result:** **PASS** (Visual browser screenshots captured and verified).

### DOM-19: Database Integrity & Constraints
* **Timezone Consistency:** Session timezone strictly configured to UTC (`+00:00`).
* **Unique Constraints:** Duplicate emails rejected by MySQL unique key constraint (`SQLSTATE 23000`).
* **Transaction Rollback:** Aborted transactions leave zero orphaned records.
* **Result:** **PASS** (3/3 checks).

### DOM-20: Regression Testing
* **Phase 3–11 Regression:** All 424 existing baseline regression assertions executed and passed.
* **Zero Weakened Assertions:** No assertions were removed, weakened, or bypassed.
* **Result:** **PASS** (424 / 424 passed).

---

## 3. End-to-End UAT Scenario Matrix

| Scenario | Title | Primary Actors | Verified Steps | Outcome |
| :--- | :--- | :--- | :--- | :--- |
| **Scenario A** | Student / Parent Complete Journey | Student, Parent, Tutor | Parent registers child, browses approved tutors, books published slot (PENDING), checks history, resists horizontal IDOR attack. | **PASS** |
| **Scenario B** | Tutor Complete Journey | Tutor, Manager | Candidate tutor is unbookable; slot creation blocked; DBS submitted; manager approves tutor + verifies DBS; availability slot published; overlaps rejected. | **PASS** |
| **Scenario C** | Manager Admin Complete Journey | Manager, Tutor, Student | Dashboard KPIs load; tutor administrative list with DBS status viewed; raw DBS PDF retrieved; tutor and student blocked from raw DBS files. | **PASS** |
| **Scenario D** | Security Attacker Simulation | Malicious Actor | Vertical privilege escalation blocked; horizontal booking IDOR blocked; SQL injection safely bound; XSS escaped; `.env` protected; CSP headers emitted. | **PASS** |
| **Scenario E** | Concurrency & Double-Booking | Two Concurrent Clients | Competing transaction blocked by physical InnoDB row lock (`SELECT ... FOR UPDATE`); exactly 1 booking created; 0 duplicate bookings. | **PASS** |

---

## 4. Defect Log & Resolutions

| Defect ID | Classification | Affected Component | Description | Resolution / Status |
| :--- | :--- | :--- | :--- | :--- |
| **DEF-P12-001** | Test Defect | `tests/Phase12QaUatTest.php` | Test accessed `$tutorsList['data']` instead of established `$tutorsList['items']` return key. | Fixed in test assertion. **RESOLVED**. |
| **DEF-P12-002** | Test Defect | `tests/Phase12QaUatTest.php` | Test called `Authorization::assertCannotSelfAssignManager` with array parameter instead of string role. | Fixed in test assertion. **RESOLVED**. |
| **DEF-P12-003** | Test Defect | `tests/Phase12QaUatTest.php` | Test called non-existent `SecurityHeaders::getHeaders()` instead of static `SecurityHeaders::apply()`. | Fixed in test assertion. **RESOLVED**. |
| **DEF-P12-004** | Test Defect | `tests/Phase12QaUatTest.php` | Test called non-existent `Database::createConnection()` instead of `Database::getConnection($cfg)`. | Fixed in test assertion. **RESOLVED**. |
| **DEF-P12-005** | Test Defect | `tests/Phase12QaUatTest.php` | Test called `DefaultEmailService::send()` with omitted recipient name argument. | Fixed argument list to match signature. **RESOLVED**. |
| **DEF-P12-006** | Test Defect | `tests/Phase12QaUatTest.php` | Test called `BlogService::submitForReview()` instead of established `BlogService::submitPost()`. | Fixed method call to `submitPost()`. **RESOLVED**. |
| **DEF-P12-007** | Test Defect | `tests/Phase12QaUatTest.php` | Test called non-existent `NewsletterService::updateStatus()` instead of `updateSubscriberStatus()`. | Fixed method call and passed subscriber ID. **RESOLVED**. |
| **DEF-P12-008** | Test Defect | `tests/Phase12QaUatTest.php` | Contact form accessibility check looked for `for="name"` instead of established `for="contact-name"`. | Fixed selector to `contact-name`. **RESOLVED**. |

*Total Implementation Defects Discovered:* **0**  
*Total Requirement Defects Discovered:* **0**  
*Total Unresolved Defects:* **0**

---

## 5. Confirmation of Open Client Decisions

All eleven (11) open client decisions identified in Master Project Document v2.0 remain explicitly **OPEN and NEUTRAL**:

1. **Double Opt-In Workflow:** Subscriptions created in neutral `PENDING` status; manager activation bypass blocked.
2. **Email Provider Selection:** Managed via provider-agnostic `EmailService` abstraction; no commercial provider configured.
3. **Payment Gateway Provider:** Zero payment vendor integrations implemented; neutral data models maintained.
4. **Cancellation Policy:** Technical capability to cancel provided; commercial fees/deadlines not hard-coded.
5. **Rescheduling Policy:** `RESCHEDULE_PROPOSED` transition supported; `RESCHEDULED` state strictly omitted.
6. **Delivery Mode (Online vs In-Person):** Delivery mode flags remain neutral.
7. **Lesson Note Visibility:** Teacher/parent notes visibility boundaries remain unconstrained by premature assumptions.
8. **Recurring Availability:** Recurring slots omitted; only discrete slot creation implemented.
9. **Multi-Guardian Access:** Single-parent child relationship implemented; multi-guardian access deferred.
10. **DBS Retention Schedule:** Physical DBS certificate retention remains explicitly marked `CLIENT_DECISION_OPEN`.
11. **Tutor Publishing Self-Toggle:** Bookability strictly governed by server-side approval (`ACTIVE + APPROVED + VERIFIED`).

---

## 6. Phase Boundary & Completion Statement

* **Phase 12 QA / UAT Status:** **PASS**
* **Phase 13 (Deployment):** **NOT STARTED** (Zero production infrastructure, zero DNS, zero commercial hosting).
* **Phase 14 (Handover):** **NOT STARTED** (Zero client handover activities executed).

**PHASE 12 — QA/UAT COMPLETE; LOCAL IMPLEMENTATION AND QA SCOPE COMPLETE.**
