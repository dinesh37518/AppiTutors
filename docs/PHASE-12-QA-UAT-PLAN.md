# Phase 12 — QA & User Acceptance Testing (QA/UAT) Plan

**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 12 — QA & User Acceptance Testing (Final Active Implementation Phase)  
**Execution Environment**: Local Windows environment (`http://127.0.0.1:80`, Apache 2.4, PHP 8.2, MySQL 8.4 LTS)  
**Scope**: Local implementation verification and QA/UAT only. No deployment (Phase 13) or handover (Phase 14).

---

## 1. QA/UAT Objectives & Verification Strategy

The primary objective of Phase 12 is to verify that the completed UK Tutoring Platform functions reliably, securely, and cohesively as an integrated system across all 20 functional, architectural, and security domains defined in the Master Project Document v2.0.

Phase 12 combines:
1. **Automated End-to-End & Integration Testing**: Programmatic verification of all core application APIs, domain services, database transactions, concurrency locking, and security boundaries.
2. **End-to-End User Journeys (Scenarios A through E)**: Step-by-step validation of realistic user workflows for Students, Tutors, Managers, and simulated Malicious Actors.
3. **Accessibility & Responsive Verification**: Technical validation against W3C WCAG 2.2 AA criteria and responsive viewport validation across Desktop, Tablet, and Mobile layouts.
4. **Cumulative Regression Validation**: Execution and verification of all existing test suites for Phases 3 through 11 (baseline: 424/424 passed).

---

## 2. The 20 QA/UAT Testing Domains

| Domain ID | Domain Description | Test Methodology | Key Verification Criteria |
| :--- | :--- | :--- | :--- |
| **DOM-01** | **Public Website** | HTTP Client + DOM Verification | All 12 public views load HTTP 200; navigation links; contact form validation; static assets. |
| **DOM-02** | **Authentication** | API Token Boundary Tests | 401 on missing/invalid/expired token; 404 on unmapped UID; 403 on inactive account; server-side authority. |
| **DOM-03** | **Student / Parent** | E2E Scenario A | Registration, profile CRUD, single-parent child management, directory search, booking inquiry, history. |
| **DOM-04** | **Tutor Workflows** | E2E Scenario B | Registration starts PENDING; bookability gate (`ACTIVE + APPROVED + VERIFIED`); profile; discrete slots. |
| **DOM-05** | **Manager Admin** | E2E Scenario C | Dashboard metrics, tutor review, DBS verification/rejection, student admin, booking oversight, audit. |
| **DOM-06** | **Availability Engine** | Service & API Tests | Discrete slots; UTC storage & London display; negative duration rejection; 4 overlap collision cases. |
| **DOM-07** | **Booking Engine** | State Machine Tests | Exactly 7 master states; `RESCHEDULED` strictly rejected; valid/invalid state transitions; history logging. |
| **DOM-08** | **Concurrency** | E2E Scenario E | Concurrent transactions against identical slot via `SELECT ... FOR UPDATE`; 1 succeeds, 1 gets 409. |
| **DOM-09** | **Email Notifications**| Mock/Log Adapter Tests | Transactional dispatch; HTML + plain-text fallback; DB commit decoupled from email failure; masking. |
| **DOM-10** | **Blog Lifecycle** | Workflow Tests | `DRAFT -> SUBMITTED -> APPROVED -> PUBLISHED`; rejection to `DRAFT`; archiving; slug routing. |
| **DOM-11** | **Newsletter** | API & Privacy Tests | Consent validation; neutral `PENDING`; double opt-in open; token unsubscribe; `SUPPRESSED` anti-solicitation. |
| **DOM-12** | **DBS Confidentiality**| Security Matrix Tests | Raw files **MANAGER ONLY**; owning tutor/student/public blocked (403/401); open retention (`DISC-020`). |
| **DOM-13** | **Authorization / IDOR**| Cross-Tenant Attacks | Horizontal/vertical IDOR prevention across profiles, children, bookings, availability, blog posts. |
| **DOM-14** | **Security Controls** | Attack Simulation | PDO prepared statements (SQLi), entity escaping (XSS), anti-CSRF synchronizer tokens, CSP, rate limiting. |
| **DOM-15** | **Privacy Mechanisms**| Service Tests | DSAR machine-readable JSON export; technical account anonymization; booking deferral; neutral policy wording. |
| **DOM-16** | **Error Handling** | Fault Injection Tests | Malformed JSON (400), invalid IDs (422/404), route 404, unhandled exceptions return safe JSON/HTML. |
| **DOM-17** | **Accessibility** | WCAG 2.2 AA Checks | Semantic HTML, skip-to-content link, single `<h1>`, form labels (`for`/`id`), `:focus-visible` outline. |
| **DOM-18** | **Responsive Layout** | Viewport Checks | Desktop (1280px), Tablet (768px), Mobile (375px); layout stability, menu toggles, no horizontal overflow. |
| **DOM-19** | **Database Integrity**| Schema & Constraint Tests| Foreign keys, cascade deactivation, unique constraints, transaction rollbacks, UTC session timezone. |
| **DOM-20** | **Automated Regression**| Test Suite Execution | Full execution of Phase 3 through Phase 11 suites; zero regressions from 424-test baseline. |

---

## 3. End-to-End UAT Scenarios (Scenarios A through E)

### Scenario A — Student / Parent End-to-End Journey
1. Browse public website (`/`, `/about.php`, `/subjects.php`, `/pricing.php`).
2. Register as a new Student/Parent (`/api/register/student.php`).
3. Complete profile setup with phone, address, and postcode (`/api/student/profile.php`).
4. Add child record specifying school year and curriculum (`/api/parent/children.php`).
5. Browse tutor directory (`/tutors.php` & `/api/tutors.php`).
6. View approved tutor profile and published availability slots.
7. Book a lesson slot with inquiry notes (`/api/bookings.php`).
8. Verify booking appears in student booking history with initial `PENDING` status.
9. Attempt unauthorized access to another student's profile/child records (verify HTTP 403 Forbidden).

### Scenario B — Tutor End-to-End Journey
1. Register as a new Tutor candidate (`/api/register/tutor.php`).
2. Verify account starts in `PENDING` status with `is_bookable = false`.
3. Complete tutor profile (headline, bio, subjects, qualifications).
4. Attempt to publish availability before manager approval (verify HTTP 403 Bookability gate).
5. Submit DBS certificate number and document attachment (`/api/dbs/upload.php`).
6. Await manager review and verification.
7. Upon verification, publish discrete 1-hour availability slots.
8. Receive student booking inquiry (`PENDING`).
9. Transition booking to `CONFIRMED`.
10. Attempt to view or modify another tutor's availability or bookings (verify HTTP 403 Forbidden).

### Scenario C — Manager Administration End-to-End Journey
1. Authenticate with Manager credentials.
2. View managerial administrative dashboard (`/manager-dashboard.php` & `/api/manager/dashboard.php`).
3. Inspect pending tutor queue; review profile details.
4. Access DBS review interface; inspect raw DBS document in secure storage (`/api/dbs/document.php`).
5. Approve tutor application (`MANAGER_APPROVE_TUTOR`) and verify DBS (`MANAGER_VERIFY_DBS`).
6. Inspect student directory and booking administrative queue.
7. Review submitted blog articles; approve valid post and reject post requiring revisions.
8. Inspect newsletter subscriber roster; suppress requested email.
9. Review administrative audit log entries confirming actor, action, timestamp, and IP hash.

### Scenario D — Security Attacker Simulation (Adversarial UAT)
1. **Vertical Privilege Escalation**: Send registration payload with `"role": "MANAGER"` or modify user role via API. (Must be discarded; server-side MySQL authority enforces assigned role).
2. **Horizontal IDOR Attack**: Student A attempts to update Student B's profile or child record. (Must return HTTP 403).
3. **Raw DBS Document Theft**: Non-manager (tutor, student, anonymous) attempts to download stored DBS document via direct HTTP or API parameter tampering. (Must return HTTP 403/401/404).
4. **SQL Injection Attack**: Inject SQL syntax (`' OR 1=1 --`, `UNION SELECT`) into search, filter, and pagination parameters. (Must be safely parameterized by PDO; zero syntax leakage).
5. **Cross-Site Scripting (XSS)**: Inject `<script>alert('XSS')</script>` into blog title, tutor bio, or contact form message. (Must be HTML-entity escaped on output by `View::e()`).
6. **Path Traversal / Web Root Leakage**: Direct HTTP requests to `/.env`, `/.git`, `/composer.json`, `/storage/private/dbs/`. (Must return HTTP 403/404).
7. **Rate Limit Breach**: Rapid fire 60 requests in 10 seconds to `/api/bookings.php`. (Must return HTTP 429 Too Many Requests with `Retry-After`).

### Scenario E — Booking Concurrency & Double-Booking Simulation
1. Target a single `PUBLISHED` availability slot belonging to an approved tutor.
2. Fire two concurrent booking transactions using distinct database connections representing two competing students.
3. Both transactions execute `SELECT ... FOR UPDATE` on the slot row.
4. Verify:
   - First transaction acquires the exclusive row-level lock, transitions slot to `BOOKED`, creates booking `PENDING`, and commits.
   - Second transaction either waits and detects slot is no longer `PUBLISHED`, throwing `SLOT_UNAVAILABLE` (HTTP 409 Conflict), or reaches lock wait timeout safely.
   - Exactly **1** booking record exists for the slot; **zero** duplicate bookings created.
   - Database remains completely consistent.

---

## 4. Defect Classification Scheme

In strict accordance with Section 26 of the Phase 12 prompt:
* **Class A (Requirement Defect)**: Conflict, ambiguity, or omission in the Master Project Document v2.0. Must be documented without unilaterally inventing business rules.
* **Class B (Implementation Defect)**: Existing code deviates from or fails to satisfy an established requirement in the Master Project Document or approved Phase 3–11 specifications. Must be fixed, verified, and regression-tested.
* **Class C (Test Defect)**: A test assertion is incorrect or asserts behavior not required by the specifications. Must be corrected to match authoritative specifications.
* **Class D (Environment Limitation)**: Local environment constraints (e.g. lack of live domain SSL for HSTS, localhost plain HTTP cookie rules, offline CLI mock Firebase) prevent production verification. Must be explicitly documented as accepted local limitations.
* **Class E (Open Client Decision)**: Behavior depends on an unresolved business decision from Section 36 of the Master Project Document v2.0. Must NOT be resolved by the developer; must remain neutral/deferred.

---

## 5. The 11 Open Client Decisions Boundary Control

The QA/UAT verification must explicitly confirm that none of the 11 client decisions have been unilaterally resolved:
1. **Double Opt-In Workflow**: Maintained in neutral `PENDING` state; manager activation bypass blocked (`DOUBLE_OPT_IN_OPEN_DECISION`).
2. **Email Provider**: Provider-agnostic adapter architecture maintained; zero commercial vendor credentials hardcoded.
3. **Payment Provider**: Payment processing deferred; no vendor selected or installed.
4. **Cancellation Policy**: Seven-state lifecycle preserved; refund/cancellation business rules remain open.
5. **Rescheduling Policy**: Vocabulary strictly limited to 7 states; no `RESCHEDULED` state introduced.
6. **Delivery Mode**: In-person vs online lesson delivery flags remain open.
7. **Lesson Note Visibility**: Student/parent visibility rules for tutor notes remain open.
8. **Recurring Availability**: Recurring schedule generation remains open; discrete slots only.
9. **Multi-Guardian Family Access**: Multi-guardian delegation remains open; single-parent relationship preserved.
10. **DBS Retention / Deletion Policy**: Documents stored securely outside web root; automated purge schedule deferred (`DISC-020`).
11. **Tutor Publishing Toggle**: Manual publishing toggle vs automatic approved status remains open.

---

## 6. Execution Deliverables
1. `tests/Phase12QaUatTest.php`: Complete automated integration test suite executing Scenarios A–E and the 20 testing domains.
2. `docs/PHASE-12-QA-UAT-REPORT.md`: Comprehensive execution log, viewport testing, accessibility findings, and defect classification.
3. `docs/PHASE-12-COMPLETION-REPORT.md`: Final completion report fulfilling the 13 required sections of Section 32.
