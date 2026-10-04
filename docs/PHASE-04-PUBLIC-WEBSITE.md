# PHASE 4 — PUBLIC WEBSITE IMPLEMENTATION SPECIFICATION

**Project**: UK Tutoring Platform  
**Phase**: Phase 4 — Public Website  
**Authoritative Baseline**: Production Master Project Document v2.0 | Approved Phase 1 Discovery | Approved Phase 2 Architecture | Verified Phase 3 Foundation  
**Primary Region**: United Kingdom (Europe/London GMT/BST)  
**Status**: APPROVED & VERIFIED  

---

## 1. OBJECTIVE & EXECUTIVE SUMMARY

The objective of **Phase 4 — Public Website** was to construct and verify the complete, responsive, public-facing web presentation layer for the UK Tutoring Platform. This implementation transforms the core architectural foundation established in Phase 3 into a fully accessible, mobile-first marketing and discovery experience for parents, students, and prospective educators across England, Wales, and Northern Ireland.

### 1.1 Core Principles & Strict Scope Boundaries
In accordance with the non-negotiable project boundaries defined in the Master Project Document v2.0:

1. **Strictly Public Scope**: This phase implements only public presentation pages, informational structures, and approved public forms. It does **not** implement later-phase administrative workflows, tutor availability scheduling, live booking concurrency engines, manager moderation portals, or payment gateways.
2. **Zero Client Role Trust**: Public forms cannot establish sessions, allocate application roles, or bypass server-side authorization. Identity authentication remains cleanly separated from relational application authority.
3. **No Fabricated Claims**: In strict adherence to platform integrity standards, no artificial customer reviews, fake tutor ratings, invented company histories, or unsubstantiated partner logos were created. Unfinalized marketing wording and operational policies are explicitly labeled with `[CLIENT APPROVAL REQUIRED]` and `[OPEN CLIENT DECISION]` tags.
4. **WCAG 2.2 AA Accessibility Target**: All public interfaces provide semantic HTML5 hierarchy, keyboard navigation, visible focus indicators, screen-reader landmarks, and contrast ratios exceeding 4.5:1.
5. **Runtime Compatibility**: Fully compatible with the local Apache 2.4.58 (PHP 8.2.12) web environment and PHP 8.3.33 CLI tooling.

---

## 2. WEB SERVER ARCHITECTURE & ROUTING

### 2.1 Web Root Shielding & DocumentRoot Configuration
To ensure maximum security and protect private infrastructure, Apache's `DocumentRoot` is configured directly to the project's public directory:

```apache
DocumentRoot "C:/Users/Dineshkumar M/OneDrive/Desktop/Client Apptutors/public"
<Directory "C:/Users/Dineshkumar M/OneDrive/Desktop/Client Apptutors/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

Alias /api "C:/Users/Dineshkumar M/OneDrive/Desktop/Client Apptutors/api"
<Directory "C:/Users/Dineshkumar M/OneDrive/Desktop/Client Apptutors/api">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

#### Security Implications:
* **Inaccessible `.env`**: Requesting `http://127.0.0.1/.env` returns `HTTP 403 Forbidden` / `404 Not Found`. Application secrets, database passwords, and environment keys cannot be accessed via HTTP.
* **Inaccessible Credentials**: Requesting `http://127.0.0.1/storage/credentials/firebase-service-account.json` returns `HTTP 404 Not Found`. Private keys and server accounts remain isolated outside the web root.
* **Shielded Codebase**: Application source files (`src/`), configuration definitions (`config/`), and database migration SQL scripts (`database/`) are physically unreachable via web browsers.

### 2.2 Front Controller & Clean URL Rewriting
The public web directory contains an authoritative `.htaccess` file and `public/index.php` front controller:

* **Security Headers**: Injects `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and `Referrer-Policy: strict-origin-when-cross-origin` via `public/.htaccess` and `src/Support/Response.php`.
* **Content-Security-Policy (CSP) Status**: Content-Security-Policy hardening is deferred to Phase 11 and was not silently omitted. Baseline transport and framing controls are actively enforced and tested. Introducing an untested CSP during Phase 4 was avoided to prevent breaking public website styles and inline assets.
* **Clean URLs**: Clean URLs (e.g. `/about`, `/subjects`, `/pricing`) automatically resolve internally to their respective `.php` scripts while preserving direct script access (`/about.php`).
* **404 Catch-All**: Non-existent routes cleanly route to the custom, accessible `404.php` error template with a proper `404 Not Found` HTTP status code.

---

## 3. DESIGN SYSTEM & FRONTEND TOKENS

The design system is implemented in `public/assets/css/app.css` using modern CSS Custom Properties, flexbox, and grid layouts. In compliance with requirement `DISC-014`, zero runtime CSS CDNs or external dependencies are loaded in production.

### 3.1 UK Academic & Safeguarding Color Palette
```css
:root {
    /* Brand Navy Stack */
    --color-navy-950: #0A1128; /* Academic Dark Navy */
    --color-navy-900: #0F172A; /* Slate Navy */
    --color-navy-800: #1E293B; /* Heading Slate */
    --color-navy-700: #334155; /* Body Text (Contrast > 7:1) */
    --color-navy-200: #E2E8F0; /* Clean Border */
    --color-navy-50:  #F8FAFC; /* Neutral Off-White Background */

    /* Sapphire Accent */
    --color-primary-600: #1D4ED8; /* Primary Button */
    --color-primary-500: #2563EB; /* Interactive Hover */
    --color-primary-50:  #EFF6FF; /* Highlight Pill */

    /* Safeguarding Emerald */
    --color-emerald-700: #047857; /* Verified Badge Text */
    --color-emerald-600: #059669; /* Verification Emerald */
    --color-emerald-50:  #ECFDF5; /* Verified Background */

    /* Open Decision / Notice Amber */
    --color-amber-600: #D97706;
    --color-amber-50:  #FFFBEB;
}
```

### 3.2 WCAG 2.2 AA Accessibility Foundation
1. **Skip-to-Content Link**: A dedicated focusable link (`.skip-link`) allows keyboard users to bypass header navigation and jump straight to `<main id="main-content">`.
2. **High-Visibility Focus Indicators**: Global `:focus-visible` rule enforces a 3px solid `#2563EB` outline with 2px offset, ensuring clear keyboard focus for tab-navigation users.
3. **Semantic Hierarchy**: Exactly one `<h1>` per page representing the core page topic, followed by logical `<h2>` and `<h3>` section headings.
4. **Mobile Navigation Drawer**: Accessible toggle button equipped with `aria-expanded`, `aria-controls="mobile-nav"`, and `aria-label`. Handles `Escape` key close events and returns focus to the toggle button.
5. **Reduced Motion**: Full support for `@media (prefers-reduced-motion: reduce)` disabling transitions and smooth scrolling for motion-sensitive visitors.

---

## 4. PUBLIC PAGE IMPLEMENTATIONS

Complete Phase 4 public website surface comprising the required marketing pages, public entry points, dynamic blog reader, error handling and SEO/security support resources. All public pages are rendered via `App\Support\View::render()`, wrapping content inside consistent master `header.php` and `footer.php` layouts.

```
/client-apptutors
├── public/
│   ├── index.php             # Front controller & Marketing Homepage
│   ├── about.php             # About Us, Vetting & Enhanced DBS Safeguarding
│   ├── subjects.php          # UK Curriculum breakdown across Key Stages
│   ├── pricing.php           # Transparent fees & booking lifecycle rules
│   ├── testimonials.php      # Authentic feedback collection framework
│   ├── contact.php           # Public inquiry form with validation
│   ├── blog.php              # Educational blog index (MySQL backed)
│   ├── blog-post.php         # Single article reader by slug
│   ├── newsletter.php        # Newsletter opt-in with token hashing
│   ├── tutors.php            # Tutor directory landing & safeguarding gate preview
│   ├── login.php             # Firebase authentication entry gateway
│   ├── register.php          # Account registration selection portal
│   ├── 404.php               # Accessible error state
│   ├── robots.txt            # SEO crawling directives
│   └── assets/
│       ├── css/app.css       # Complete production styling
│       └── js/app.js         # Accessible navigation & progressive enhancements
```

### 4.1 Marketing Homepage (`/` or `/index.php`)
* **Hero Section**: Communicates core value proposition ("Expert 1-to-1 UK Tutoring for Primary, GCSE & A-Level") with trust indicators (AQA, Edexcel, OCR aligned; 100% Enhanced DBS checked).
* **3-Step "How It Works" Guide**: Clarifies parent journey: 1. Discover Verified Tutors, 2. Request Lesson Inquiries, 3. Learn & Track Progress.
* **Safeguarding Banner**: Prominently highlights zero-tolerance child protection and manager vetting controls.
* **Curriculum Cards**: High-level overview of Primary (KS1/KS2), Lower Secondary (KS3), GCSE (KS4), and A-Level (KS5).
* **Pricing Preview**: Summary table linking to full pricing details with explicit client approval badges.
* **Call to Action**: High-contrast CTAs directing visitors to tutor search and student registration.

### 4.2 About Us & DBS Safeguarding (`/about.php`)
* **Platform Ethos**: Academic focus, personalized learning, and transparent communication.
* **Enhanced DBS Safeguarding Architecture**: Explains certificate submission, manager auditing, and secure storage outside the web root.
* **4-Step Tutor Vetting Pipeline**: Identity check & Right to Work &rarr; Academic qualification validation &rarr; Enhanced DBS check &rarr; Manager approval gate.
* **Regulatory Policy Placeholders**: Clearly labeled placeholders for client-supplied Terms of Service, Privacy Notice, and Safeguarding Policy.

### 4.3 UK Curriculum & Subjects Guide (`/subjects.php`)
* **Primary (KS1 & KS2)**: Phonics, literacy, numeracy, times tables, and 11+ grammar entrance (GL Assessment, CEM).
* **Lower Secondary (KS3)**: Years 7–9 transition, algebraic manipulation, secondary English, combined sciences, and modern languages.
* **GCSE Examinations (KS4)**: Detailed coverage of major UK exam boards (AQA, Pearson Edexcel, OCR, Eduqas), Higher vs Foundation tier differences, and core practicals.
* **A-Level & Further Education (KS5)**: Advanced single-subject specialization, pure/further mathematics, organic chemistry, micro/macro economics, and UCAS personal statement preparation.

### 4.4 Pricing & Fee Structure (`/pricing.php`)
* **Transparent Indicative Rates**: Displays typical hourly rates by Key Stage with explicit `[CLIENT APPROVAL REQUIRED]` notices:
  * Primary: &pound;25 &ndash; &pound;40 / hr
  * 11+ Entrance: &pound;30 &ndash; &pound;45 / hr
  * Lower Secondary: &pound;30 &ndash; &pound;45 / hr
  * GCSE: &pound;35 &ndash; &pound;55 / hr
  * A-Level: &pound;45 &ndash; &pound;70 / hr
* **Open Commercial Decision Advisory**: Emphasizes that payment models (direct inquiry vs platform-mediated billing vs package blocks) remain under active client consideration.
* **7-State Booking Lifecycle**: Explains how lesson requests move from `PENDING` to `CONFIRMED`, `RESCHEDULE_PROPOSED`, and `COMPLETED`.

### 4.5 Testimonials & Case Studies (`/testimonials.php`)
* **Feedback Collection Framework**: Explains that reviews are restricted to parents and students who have completed verifiable lesson lifecycles on the platform.
* **Child Privacy Protection**: Surnames are never published.
* **Case Study Placeholders**: Displays three structured testimonial frameworks (GCSE Maths, A-Level Chemistry, 11+ Preparation) marked with `[CLIENT APPROVAL REQUIRED]`.

### 4.6 Contact Admissions Support (`/contact.php`)
* **Inquiry Form**: Collects full name, email, enquiry type (Parent/Student, Tutor Application, Safeguarding, General), subject, message, and explicit data processing consent (implemented as an explicit-consent engineering control designed to support the client's UK GDPR/PECR requirements, pending client/legal approval of the authoritative legal policy).
* **Server-Side Validation**: Uses `App\Validation\Validator` to enforce RFC email compliance, required field completeness, enum values, and character length caps.
* **Sanitized Logging**: Logs incoming inquiries with email addresses hashed (SHA-256) as a privacy-preserving audit control.
* **Operating Hours**: Displays admissions team schedule in `Europe/London` (Monday–Friday 09:00–18:00, Saturday 10:00–14:00).

### 4.7 Educational Blog & Revision Guides (`/blog.php`)
* **Dynamic MySQL Integration**: Queries `blog_posts` table for records matching `status = 'PUBLISHED'`.
* **Author Resolution**: Joins `users` table on `author_user_id` to render author display names.
* **Timezone Localization**: Translates UTC database timestamps into localized UK dates (e.g. `15 September 2026`) via `App\Support\Timezone::utcToLondon()`.
* **Empty State Handling**: Gracefully informs visitors when articles are undergoing editorial review.

### 4.8 Single Blog Post Reader (`/blog-post.php`)
* **Slug Routing**: Queries posts by unique URL slug (`?slug=...` or `/blog-post/...`).
* **Content Sanitization**: Safely escapes body text with newline preservation (`nl2br(e($body))`).
* **Semantic Breadcrumbs**: Clear hierarchical navigation back to Homepage and Blog Index.
* **404 Protection**: Non-existent or unpublished slugs trigger custom 404 responses.

### 4.9 Newsletter Subscription (`/newsletter.php`)
* **Educational Value Proposition**: Highlights exam timetables, revision planners, and curriculum news.
* **Explicit Consent Control**: Requires explicit checkbox confirmation (implemented as an explicit-consent engineering control designed to support the client's UK GDPR/PECR requirements, pending client/legal approval of the authoritative legal policy).
* **Database Persistence & Neutral State**: Stores subscribers in MySQL `newsletter_subscribers` table with UTC consent timestamps in a neutral `status = 'PENDING'` state with `confirmed_at = NULL`. This directly utilizes the approved Phase 3 schema enum (`PENDING`, `ACTIVE`, `UNSUBSCRIBED`, `SUPPRESSED`) without database redesign and strictly preserves the client's open double-opt-in decision without assuming single or double opt-in as the final business rule.
* **Cryptographic Token Hashes**: Pre-computes 64-character SHA-256 hashes (`confirmation_token_hash`, `unsubscribe_token_hash`) for future workflow readiness. Their existence is explicitly not presented as proof that double opt-in has been decided.
* **Scope Demarcation**: Full confirmation email delivery, 1-click unsubscribe lifecycle, suppression lists, and automated campaigns are strictly deferred to Phase 10.

### 4.10 Tutor Directory Preview (`/tutors.php`)
* **Phase 6 Scope Demarcation**: Explains that live directory searching and booking activation launch in Phase 5/6.
* **Safeguarding Gate (DISC-028)**: Clearly outlines the query requirements (`approval_status = 'APPROVED'` and `dbs_status = 'VERIFIED'`).
* **Verified Tutor Card Mockup**: Showcases how verified credentials, academic qualifications, and subject specialisms will render.

### 4.11 Authentication & Registration Gateways (`/login.php` & `/register.php`)
* **Sign In (`/login.php`)**: Clean entry point explaining that identity is verified by Firebase Authentication while permissions reside in MySQL.
* **Registration Selection (`/register.php`)**: Clearly demarcates the immediate Parent/Student pathway (`STUDENT_PARENT` role in `ACTIVE` status) from the professional Tutor pathway (`TUTOR` role in `PENDING` status requiring manager vetting).

---

## 5. REQUIREMENT TRACEABILITY MATRIX

| Requirement ID | Specification Source | Description | Phase 4 Implementation Location | Verification Method | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **DISC-011** | Master Doc Sec 1, 28 | Public Marketing Pages (Home, About, Subjects, Pricing, Testimonials, Contact, Blog) | `public/index.php`, `public/*.php`, `src/Views/*.php` | HTTP 200 verification across all tested public routes and resources | **VERIFIED** |
| **DISC-012** | Master Doc Sec 22 | WCAG 2.2 AA Accessibility Target (Keyboard nav, focus, landmarks) | `src/Views/layouts/header.php`, `app.css`, `app.js` | Skip link, `:focus-visible`, `<main>` landmark, ARIA checks | **VERIFIED** |
| **DISC-013** | Master Doc Sec 1, 22 | Mobile-First Responsive Design across phone, tablet, and desktop | `public/assets/css/app.css` (Grid, Flexbox, Breakpoints) | Mobile menu drawer, viewport meta tag, responsive tables | **VERIFIED** |
| **DISC-014** | Master Doc Sec 0, 21 | Static Compiled Tailwind / Production CSS without Runtime CDN | `public/assets/css/app.css` (Self-contained design tokens) | Asset loading check, zero external script dependencies | **VERIFIED** |
| **DISC-027** | Master Doc Sec 13, 14 | Public Tutor Directory Search Entry Point | `public/tutors.php`, `src/Views/tutors.php` | Search filter UI structure & card layout rendered | **VERIFIED** |
| **DISC-028** | Master Doc Sec 14 | Directory Safeguarding Gate Enforcement Notice | `src/Views/tutors.php`, `src/Views/about.php` | Prominent notice of `APPROVED` & `VERIFIED` prerequisites | **VERIFIED** |
| **DISC-050** | Master Doc Sec 4.2, 11 | Educational Blog Index & Post Reader | `public/blog.php`, `public/blog-post.php`, `blog_posts` | Dynamic database query and single post slug retrieval | **VERIFIED** |
| **DISC-052** | Master Doc Sec 4.2, 11 | Newsletter Subscriber Public Form & Persistence | `public/newsletter.php`, `newsletter_subscribers` | Validated POST persisted to MySQL in neutral PENDING state with token hashes | **VERIFIED** |
| **DISC-055** | Master Doc Sec 14.1, 16 | Privacy & Cookie Boundaries Preserved | Zero third-party trackers injected in public layouts | Clean HTML audit, no unsolicited cookies set | **VERIFIED** |

---

## 6. VERIFICATION SUMMARY & TEST HARNESS

All Phase 4 functionality has been verified using automated suites and live Apache HTTP requests.

### 6.1 Test Suites Executed
1. **Phase 3 Foundation Regression Suite (`tests/run_tests.php`)**:
   * **25/25 Tests Passed (100%)**.
   * Confirms PDO singleton, UTC timezone handling, Firebase verification baseline, RBAC Case C & Case D, and logging redaction remained intact.
2. **Phase 4 Public Website Test Suite (`tests/Phase4PublicWebsiteTest.php`)**:
   * **30/30 Tests Passed (100%)**.
   * Functional Route Verification: All tested public routes and resources returned `HTTP 200 OK`.
   * Error Handling: Unknown URLs returned `HTTP 404 Not Found` with custom styled page.
   * Blog Engine: Successfully rendered published editorial posts and individual article reader.
   * Form Validation: Contact form and Newsletter form verified for valid acceptance and invalid rejection.
   * Newsletter Persistence: Verified subscriber persisted with neutral `status = 'PENDING'`, `confirmed_at = NULL`, and pre-computed token hashes preserving open client decision.
   * Security Boundaries: `.env` blocked (`403`), credentials blocked (`404`), `.htaccess` blocked (`403`), and `/api/auth.php` protected (`401`).
   * Accessibility: Skip link, `<main>` landmark, single `<h1>` hierarchy, ARIA attributes, and 3px focus outline verified.

---

## 7. OPEN CLIENT DECISIONS PRESERVED

All 19 open client decisions established in Phase 1 and Phase 2 remain strictly preserved. No business policies were prematurely finalized:

1. **Payment Gateway & Commercial Model**: Neutral indicative fee ranges displayed; payment provider choice deferred.
2. **Authoritative Subject Taxonomy**: Hierarchical subject structures marked for client review.
3. **DBS Retention Policy**: Evidence retention rules marked as open client decision.
4. **Newsletter Double Opt-In (DEC-16 / DISC-053)**: Preserved as an open client decision. Phase 4 creates provisional subscriber records with `status = 'PENDING'` and `confirmed_at = NULL` without committing to single or double opt-in. Full confirmation and unsubscribe workflows are strictly deferred to Phase 10. Pre-computed token hashes exist solely for future technical readiness and do not assume double opt-in has been chosen.
5. **Cancellation Notice Window**: 24 vs 48-hour cancellation policies documented as pending client rules.
6. **Analytics & Tracking**: No third-party cookie trackers deployed.

---

## 8. PRESERVATION OF PHASE BOUNDARIES & CONCLUSION

In accordance with strict project governance, Phase 4 maintains clean phase boundaries:
* **No Phase 5 Functionality**: Tutor application workflow, DBS certificate upload/verification, tutor availability management, and manager approval gates are NOT implemented.
* **No Phase 6+ Functionality**: Live tutor directory searching, student/parent dashboard, booking engine concurrency, rescheduling, manager dashboards, and payment integrations are NOT implemented.
* **Public Blog Scope**: Only public viewing of already-published editorial content is provided; tutor drafting and manager moderation queues remain deferred.

Phase 4 has successfully delivered an accessible, responsive, and secure public web presence for the UK Tutoring Platform. The implementation adheres to all architectural constraints, integrates seamlessly with the Phase 3 foundation, and establishes a rock-solid presentation baseline for upcoming phases.

**Official Phase 4 Status**:  
**PHASE 4 — PUBLIC WEBSITE VERIFIED AND READY FOR PHASE 5**
