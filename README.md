# UK Tutoring Platform

A professional web application platform connecting UK students and parents with qualified, vetted tutors for Primary, GCSE, and A-Level curricula.

---

## 1. System Architecture

```text
Frontend
   ↓
Backend/API
   ↓
Firebase Authentication
   ↓
Firebase Admin SDK verification
   ↓
MySQL application database
```

### Core Architecture Principles:
* **Firebase Authentication**: Acts strictly as the identity/authentication provider issuing cryptographically signed Firebase ID tokens upon user login.
* **PHP Backend Verification**: The PHP application verifies incoming Firebase ID tokens using the Firebase Admin SDK (`Kreait\Firebase\Factory` / `FirebaseTokenVerifier`).
* **MySQL Source of Truth**: The MySQL database (`tutoring_platform_dev`) stores application user accounts, profiles, permissions, and roles (`STUDENT_PARENT`, `TUTOR`, `MANAGER`). MySQL is the sole application source of truth for authorization.
* **Zero Client Role Trust**: Client-side role claims are never trusted or accepted. All authorization decisions are enforced server-side against the authenticated MySQL `role_id`.

---

## 2. Project Directory Structure

```text
UK-Tutoring-Platform/
│
├── frontend/                     # User interface & presentation layer
│   ├── public/                   # Public web root (DocumentRoot)
│   │   ├── assets/               # CSS, JavaScript, images, fonts
│   │   └── index.php             # Main entry point & public routing
│   ├── views/                    # Reusable views and templates
│   │   ├── layouts/              # Main layout wrappers (header, footer, nav)
│   │   ├── components/           # Reusable UI components
│   │   ├── public/               # Public marketing and informative pages
│   │   ├── auth/                 # Sign-in and registration views
│   │   ├── student/              # Student and parent portal views
│   │   ├── tutor/                # Tutor workspace and profile views
│   │   └── manager/              # Manager administration views
│   └── README.md
│
├── backend/                      # Server-side application logic & APIs
│   ├── api/                      # REST API endpoints (health, auth, tutors, bookings, etc.)
│   ├── src/                      # PSR-4 namespaced application classes (`App\`)
│   │   ├── Auth/                 # Firebase token verification & UserContext
│   │   ├── Authorization/        # RBAC and resource ownership guards
│   │   ├── Database/             # PDO database connector & migration engine
│   │   ├── Logging/              # PSR-3 compliant logging with sensitive data redaction
│   │   ├── Services/             # Domain services (Booking, Tutor, Dbs, Email, etc.)
│   │   ├── Support/              # Utility helpers (CSRF, RateLimiter, View, etc.)
│   │   └── Validation/           # Server-side validation rules & exceptions
│   ├── config/                   # Application configuration files (app, database, mail)
│   ├── routes/                   # Route definitions (api.php, web.php)
│   ├── bootstrap/                # Application initialization (bootstrap.php, app.php)
│   └── README.md
│
├── database/                     # MySQL database management
│   ├── migrations/               # Sequential schema migrations (001 through 006)
│   ├── seeds/                    # Initial seed datasets (editorial blog, etc.)
│   ├── scripts/                  # CLI database migration and management scripts
│   └── README.md
│
├── firebase/                     # Firebase integration assets & documentation
│   ├── client/                   # Client-side Firebase configuration guide & SDK snippets
│   ├── admin/                    # Server-side Firebase Admin SDK documentation
│   ├── config/                   # Configuration templates & environment notes
│   └── README.md
│
├── storage/                      # Runtime generated & private assets (Outside Web Root)
│   ├── credentials/              # Private service-account credentials (Git-ignored)
│   ├── logs/                     # Application activity & error logs
│   ├── uploads/                  # Protected user file uploads
│   ├── cache/                    # Runtime application cache
│   └── private/                  # Encrypted DBS documents & rate limiting storage
│
├── tests/                        # Comprehensive automated test suite
│   ├── Unit/                     # Unit test specifications
│   ├── Integration/              # Component integration tests
│   ├── Security/                 # Authorization & security boundary tests
│   ├── UAT/                      # User acceptance & end-to-end scenario tests
│   └── existing-phase-tests/     # Canonical phase test suites (Phases 3 through 12)
│
├── docs/                         # Project architecture & phase completion reports
│
├── vendor/                       # Composer dependencies
├── .env                          # Local environment variables (strictly Git-ignored)
├── .env.example                  # Environment template with placeholders
├── .gitignore                    # Repository privacy & exclusion rules
├── composer.json                 # Composer manifest & PSR-4 autoloading definition
├── composer.lock                 # Locked dependency versions
└── README.md                     # Root project documentation
```

---

## 3. Technology Stack

* **Frontend**: HTML5, Vanilla CSS / Tailwind utilities, Vanilla JavaScript (ES6+), Google Fonts
* **Backend**: PHP 8.2+ with PDO MySQL extension
* **Database**: MySQL 8.4+ InnoDB with strict UTC time zone handling
* **Authentication**: Firebase Authentication (Client Web SDK) & Firebase Admin SDK (`kreait/firebase-php`)
* **Package Management**: Composer with PSR-4 autoloading (`App\` -> `backend/src/`)
* **Local Web Server**: Apache 2.4 (VirtualHost / Alias configuration pointing to `frontend/public`)

---

## 4. Security & Compliance Baseline

1. **Credentials Isolation**: All private credentials, including Firebase service-account JSON, reside strictly in `storage/credentials/` outside the web root and are excluded in `.gitignore`.
2. **Access Control**: Role-based access control (RBAC) and resource ownership validation (IDOR defense) are verified on every request.
3. **Database Concurrency**: Slot reservation and double-booking prevention rely on pessimistic row-level locking (`SELECT ... FOR UPDATE`).
4. **Safeguarding**: Tutors require Manager approval and verified Enhanced DBS checks before becoming bookable. Raw DBS documents are accessible exclusively to authorized Managers.
5. **Data Protection**: Engineering hooks support Data Subject Access Requests (DSAR) and account anonymization while preserving necessary safeguarding audit history.
