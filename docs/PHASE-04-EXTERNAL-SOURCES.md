# PHASE 4 — EXTERNAL SOURCES LOG

**Project**: UK Tutoring Platform  
**Phase**: Phase 4 — Public Website Implementation  
**Date**: October 2026  
**Document Version**: 1.0  

---

## 1. REQUIREMENT BASELINE DECLARATION

> **External sources required for Phase 4 requirements: NO.**

All business requirements, user journeys, educational curricula scope, safeguarding expectations, and architectural boundaries for the UK Tutoring Platform Public Website are strictly derived from the approved internal project specifications:

1. **Production Master Project Document v2.0 | UK Tutoring Platform**
2. **Approved Phase 1 — Discovery** (`docs/PHASE-01-DISCOVERY.md`)
3. **Approved Phase 2 — Architecture** (`docs/PHASE-02-ARCHITECTURE.md`)
4. **Verified Phase 3 — Foundation** (`docs/PHASE-03-FOUNDATION.md`)

No external competitor websites, commercial themes, community blog posts, Stack Overflow snippets, random GitHub repositories, or unapproved tutorials were utilized to formulate or modify project requirements.

---

## 2. OFFICIAL TECHNICAL REFERENCES CONSULTED

Where implementation details required technical syntax or API verification, only official, authoritative documentation was referenced:

| Technology | Official Source & URL | Technical Information Consulted | Impact on Implementation |
| :--- | :--- | :--- | :--- |
| **Apache HTTP Server 2.4** | Apache HTTP Server Project<br>`https://httpd.apache.org/docs/2.4/` | `mod_rewrite` directive syntax, `RewriteCond`, `RewriteRule`, directory security overrides (`AllowOverride All`, `Require all granted`), and hidden file blocking (`FilesMatch "^\."`). | Configured DocumentRoot to `public/`, created `public/.htaccess` with baseline security headers (nosniff, SAMEORIGIN, Referrer-Policy) and clean URL routing while keeping `.env` and `storage/` inaccessible. Content-Security-Policy hardening is deferred to Phase 11 and was not silently omitted. |
| **W3C WCAG 2.2** | W3C Web Accessibility Initiative<br>`https://www.w3.org/WAI/WCAG22/` | Success Criterion 2.4.7 (Focus Visible), 2.4.1 (Bypass Blocks / Skip Link), 1.4.3 (Contrast Minimum 4.5:1), and ARIA landmark specifications. | Implemented `.skip-link`, `<main id="main-content">`, ARIA navigation attributes (`aria-expanded`, `aria-controls`), and `:focus-visible` 3px outline styles in `app.css`. |
| **HTML5 Living Standard** | WHATWG HTML Standard<br>`https://html.spec.whatwg.org/` | Semantic sectioning elements (`<header>`, `<nav>`, `<main>`, `<article>`, `<aside>`, `<footer>`), form validation attributes (`required`, `maxlength`), and meta viewport constraints. | Structured the public views with semantic HTML5 hierarchy and strict single-`<h1>` rules per page. |
| **PHP 8.2 Core Documentation** | PHP Group Official Documentation<br>`https://www.php.net/docs.php` | `filter_var` with `FILTER_VALIDATE_EMAIL`, `htmlspecialchars` flags (`ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8), and PDO parameter binding semantics. | Implemented XSS escaping helpers, contact form validation, and newsletter token hashing. |
| **MySQL 8.4 LTS Documentation** | Oracle MySQL 8.4 Reference Manual<br>`https://dev.mysql.com/doc/refman/8.4/en/` | Indexed queries on `blog_posts` (`status`, `published_at`), `newsletter_subscribers` unique constraints, neutral `PENDING` status storage preserving the open double opt-in decision, and UTC `DATETIME` storage format. | Built blog index, blog single post reader, and provisional newsletter subscription persistence. |

---

## 3. UNUSED / EXCLUDED SOURCES

The following external sources were deliberately excluded to maintain strict compliance with project governance:

* **No CSS Framework CDN / External Tailwind CDN**: In compliance with requirement `DISC-014` and project guidelines, zero external CSS runtime scripts or CDNs were introduced. A dedicated, self-contained production stylesheet was crafted in `public/assets/css/app.css`.
* **No Client-Side JavaScript Frameworks**: No React, Vue, or Angular libraries were introduced. All UI components utilize standard semantic HTML5, Vanilla CSS, and lightweight progressive JavaScript in `public/assets/js/app.js`.
* **No Unverified Stock Photography or Media**: No third-party copyrighted photography was downloaded. All visual UI elements utilize native CSS styling, accessible Unicode symbols, and structured typography.
