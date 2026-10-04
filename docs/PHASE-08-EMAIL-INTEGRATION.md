# Phase 8: Transactional Email Integration Architecture

**Project**: UK Tutoring Platform  
**Document**: Phase 8 Implementation & Architecture Specification  
**Authoritative Baseline**: Production Master Project Document v2.0, Approved Phases 1–7  
**Status**: IMPLEMENTATION COMPLETE — AWAITING REVIEW  

---

## 1. Architectural Overview

Phase 8 implements the transactional email notification layer for the UK Tutoring Platform. In strict adherence to Section 3 of the Phase 8 specification and the Master Project Document v2.0, the transactional email provider remains an **open client decision**. 

To satisfy this requirement without locking the platform into a specific vendor or blocking business functionality:
1. A **provider-agnostic abstraction** (`App\Services\EmailService`) is implemented.
2. A pluggable **provider adapter interface** (`App\Services\Email\EmailProviderInterface`) decouples business services (`BookingService`, `TutorService`) from transport implementation details.
3. Concrete adapters are provided for local development and automated testing:
   - `ArrayEmailAdapter`: In-memory capture for automated testing and assertions without network I/O.
   - `LogEmailAdapter`: Masked, privacy-preserving local file logging (`storage/logs/app-YYYY-MM-DD.log`).
   - `NullEmailAdapter`: Silent sink for disabled or non-production test environments.
   - `SmtpEmailAdapter`: Pluggable SMTP transport ready for any standard SMTP provider or gateway.
4. Commercial provider selection (SendGrid, Mailgun, Amazon SES, Postmark, Resend, etc.) is preserved as an open client decision. Any vendor can be activated in the future by adding a concrete `EmailProviderInterface` adapter without modifying business services.

---

## 2. Directory Structure & Files Created

```
config/
└── mail.php                                # Mailer configuration with sensible provider-neutral defaults

src/
├── Services/
│   ├── EmailService.php                   # Core service contract for transactional email dispatch
│   ├── Email/
│   │   ├── EmailProviderInterface.php     # Transport adapter interface
│   │   ├── EmailResult.php                # Immutable value object for provider delivery responses
│   │   ├── DefaultEmailService.php        # Core orchestrator (validation, escaping, auditing)
│   │   └── Adapters/
│   │       ├── ArrayEmailAdapter.php      # In-memory adapter for test inspection
│   │       ├── LogEmailAdapter.php        # Masked local logging adapter
│   │       ├── NullEmailAdapter.php       # Silent no-op adapter
│   │       └── SmtpEmailAdapter.php       # Standard socket SMTP adapter
│   ├── BookingService.php                 # Injected EmailService for post-commit booking events
│   └── TutorService.php                   # Injected EmailService for post-commit tutor events
└── Views/
    └── emails/
        ├── layout.php                     # Master responsive HTML email layout with UK branding
        ├── booking_inquiry_received.php   # Notification to tutor upon new lesson request
        ├── booking_confirmed.php          # Notification to parent upon tutor acceptance
        ├── booking_rejected.php           # Notification to parent upon tutor rejection
        ├── booking_cancelled.php          # Notification to tutor upon parent cancellation
        ├── tutor_registered.php           # Welcome acknowledgement upon tutor registration
        ├── tutor_approved.php             # Notification to tutor upon manager approval & DBS verification
        └── tutor_rejected.php             # Safeguarding notification to candidate upon manager rejection
```

---

## 3. Configuration & Provider Neutrality

Configuration is loaded from `config/mail.php` utilizing environment variables from `.env`:

```php
return [
    'enabled' => filter_var(Env::get('EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'mailer' => Env::get('EMAIL_MAILER', 'log'), // Options: log, array, null, smtp
    'host' => Env::get('EMAIL_HOST', '127.0.0.1'),
    'port' => (int) Env::get('EMAIL_PORT', 587),
    'username' => Env::get('EMAIL_USERNAME', ''),
    'password' => Env::get('EMAIL_PASSWORD', ''),
    'encryption' => Env::get('EMAIL_ENCRYPTION', 'tls'),
    'from' => [
        'address' => Env::get('EMAIL_FROM_ADDRESS', 'noreply@tutoringplatform.co.uk'),
        'name' => Env::get('EMAIL_FROM_NAME', 'UK Tutoring Platform'),
    ],
];
```

### Secrets Safety & `.env.example`
- `.env.example` contains only generic placeholder values (`EMAIL_MAILER=log`, `EMAIL_HOST=smtp.example.com`, `EMAIL_USERNAME=placeholder-username`).
- Real API tokens, vendor API keys, and passwords are never committed to Git, documentation, or tests.
- When `EMAIL_MAILER=log` is configured in local development, no remote socket is opened, eliminating external network dependencies.

---

## 4. Supported Transactional Events

### A. Tutor Lifecycle Notifications
1. **`tutor_registered`**: Sent to the tutor candidate immediately after registration in `TutorService::registerTutor()`. Acknowledges application receipt and informs candidate of pending management and Enhanced DBS compliance review.
2. **`tutor_approved`**: Sent to the approved tutor in `TutorService::managerApproveTutor()`. Confirms active status, verified DBS credentials, and invites tutor to publish weekly availability slots.
3. **`tutor_rejected`**: Sent to the applicant in `TutorService::managerRejectTutor()`. Conveys review feedback professionally without exposing confidential management notes.

### B. Booking Lifecycle Notifications
1. **`booking_inquiry_received`**: Sent to the assigned tutor in `BookingService::createBooking()`. Contains lesson date/time in UK local format, student/child name, school year, and inquiry notes.
2. **`booking_confirmed`**: Sent to the student/parent in `BookingService::confirmBooking()`. Notifies parent that the lesson has been accepted and provides a link to their bookings dashboard.
3. **`booking_cancelled`**: Sent to the tutor in `BookingService::cancelBooking()`. Informs tutor that the student/parent has cancelled the session request.
4. **`booking_rejected`**: Sent to the student/parent in `BookingService::rejectBooking()`. Informs parent that the tutor is unable to accommodate the requested time.

*Note: The platform strictly respects the 7 approved Master Document booking states (`PENDING`, `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`, `COMPLETED`). No `RESCHEDULED` state exists.*

---

## 5. Critical Transaction Boundary Architecture

To prevent distributed failure modes and ensure database state consistency:
1. **Database Commit First**: All booking state changes (`SELECT ... FOR UPDATE`, row insertion, status history, slot state locks) commit strictly *before* email dispatch is attempted.
2. **Non-Blocking Email Dispatch**: Email operations are wrapped in safe `try/catch` blocks outside the database transaction.
3. **No Rollback on Provider Failure**: If an external email provider fails (e.g., socket timeout, HTTP 5xx, invalid credentials), the database transaction is **not** rolled back, the booking remains valid, and the slot remains properly locked.
4. **Concurrency Preserved**: Phase 7 InnoDB pessimistic row-locking (`SELECT ... FOR UPDATE`) remains completely unaffected.

```
Incoming Request
    │
    ▼
Database Transaction Starts
    ├─ SELECT ... FOR UPDATE (Lock slot / booking)
    ├─ Validate business rules & state transition
    ├─ UPDATE / INSERT entities
    ├─ INSERT status history record
    └─ COMMIT TRANSACTION (Locks released, state finalized)
    │
    ▼
Post-Commit Notification Boundary (Outside DB Transaction)
    ├─ Try EmailService::send(...)
    │     ├─ Sanitize & validate recipient
    │     ├─ Render templates (HTML + plain text)
    │     ├─ Provider dispatch (Array / Log / SMTP)
    │     └─ Record audit log entry (EMAIL_SENT / EMAIL_FAILED)
    └─ Catch Throwable: Log error, DO NOT disrupt client response
    │
    ▼
HTTP 200/201 Response Returned to User
```

---

## 6. Security Controls

1. **Email Header Injection Defense (OWASP)**:
   - Scans `toEmail`, `toName`, and `subject` for carriage return (`\r`) and newline (`\n`) characters.
   - Throws `ValidationException('Potential email header injection detected...', 'EMAIL_HEADER_INJECTION_DETECTED', 422)` immediately upon detection.
2. **Strict Recipient Validation**:
   - Validates recipient email syntax using RFC 822 / 5321 compliant `filter_var($email, FILTER_VALIDATE_EMAIL)`.
   - Rejects malformed addresses with HTTP 422 `INVALID_EMAIL_ADDRESS`.
3. **Template Path Traversal Protection**:
   - Template identifiers must match `^[a-zA-Z0-9_-]+$`.
   - Rejects directory traversal payloads (e.g., `../../../../etc/passwd`) with HTTP 422 `INVALID_TEMPLATE_NAME`.
4. **HTML Entity Escaping (OWASP XSS Prevention)**:
   - All user-controlled variables interpolated into templates are strictly escaped using `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
   - Neutralizes script tags, event handlers (`onmouseover`), and attribute-breaking quotes.
5. **PII Masking & Confidentiality**:
   - Sensitive DBS certificate numbers and internal passwords are never included in email templates.
   - Audit logs store SHA-256 hashed recipient identifiers (`recipient_hash`) rather than plaintext email addresses in audit metadata.
   - Log files mask recipient emails (e.g., `tu***@example.com`).

---

## 7. Audit Logging

Every email dispatch attempt is tracked in the immutable `audit_logs` table:
- **Success**: Recorded with action `EMAIL_SENT`, storing template name, provider name, message ID, and SHA-256 recipient hash.
- **Failure**: Recorded with action `EMAIL_FAILED`, storing template name, provider name, error message, and SHA-256 recipient hash.
- No real passwords, tokens, or raw provider credentials are ever persisted in audit logs.

---

## 8. Database Migration Status

`NO DATABASE MIGRATION REQUIRED`

The existing 12-table database schema (including `audit_logs` created in Phase 3/5) fully supports email event tracking and audit compliance without schema changes.

---

## 9. Verification & Automated Test Coverage

The implementation is verified via `tests/Phase8EmailTest.php` covering 33 dedicated tests across 9 functional categories:
1. Configuration & Secrets Safety (4 tests)
2. Email Abstraction & Orchestration (3 tests)
3. Security, Validation & Header Injection Defense (5 tests)
4. Template Rendering & HTML Escaping (2 tests)
5. Provider Adapters (4 tests)
6. Tutor Workflow Transactional Emails (3 tests)
7. Booking Workflow Transactional Emails (4 tests)
8. Transaction Boundary & Provider Failure Resilience (3 tests)
9. Audit Logging, Masked PII & Accessibility (5 tests)

**Phase 8 Test Result**: 33/33 PASSED (100%).  
**Total Platform Regression Result**: 224/224 PASSED (100%, 0 failures).
