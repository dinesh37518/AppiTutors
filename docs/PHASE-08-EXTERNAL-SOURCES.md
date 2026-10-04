# Phase 8: External Technical Sources Register

**Project**: UK Tutoring Platform  
**Document**: Phase 8 External Sources Log  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 8 — Transactional Email Integration  
**Status**: APPROVED BASELINE VERIFIED  

---

## 1. Compliance Rule

In strict compliance with project specifications, external sources are permitted **only** when technical implementation details require official verification. External sources may clarify technical implementation, but they **must not** introduce new business requirements, invent unapproved transactional providers, or alter architectural baselines.

Unapproved sources (random tutorials, Stack Overflow, competitor platforms, arbitrary GitHub repositories, or unofficial architecture blogs) are strictly prohibited.

---

## 2. Official Technical Sources Consulted

### Source 1: PHP Official Documentation — `filter_var` and `FILTER_VALIDATE_EMAIL`
* **Source Organization**: The PHP Group
* **Official URL**: [https://www.php.net/manual/en/filter.filters.validate.php](https://www.php.net/manual/en/filter.filters.validate.php)
* **Technical Topic Consulted**: RFC 822 / 5321 compliant email address syntax validation in PHP 8.x.
* **Why It Was Needed**: To ensure recipient email addresses are rigorously validated before attempting dispatch, rejecting malformed addresses before sending them to email provider adapters.
* **Implementation Decision Informed**:
  - Implemented strict email format validation in `App\Services\Email\EmailService::send()` using `filter_var($toEmail, FILTER_VALIDATE_EMAIL)`.
  - Rejects invalid recipients with a clean `ValidationException('Invalid recipient email address.', 'INVALID_EMAIL_ADDRESS', 422)`.

---

### Source 2: OWASP Official Guidance — Email Header Injection
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://owasp.org/www-community/attacks/Email_Header_Injection](https://owasp.org/www-community/attacks/Email_Header_Injection)
* **Technical Topic Consulted**: Carriage Return (`\r` / `%0D`) and Line Feed (`\n` / `%0A`) injection in email headers.
* **Why It Was Needed**: To protect all email dispatch endpoints and services against attackers attempting to inject arbitrary headers (`Bcc:`, `Cc:`, `To:`, or message body delimiters) via student names, inquiry subjects, or user-supplied strings.
* **Implementation Decision Informed**:
  - Implemented header validation guard `assertNoHeaderInjection()` in `EmailService`.
  - Scans all recipient emails, recipient names, and subject lines for `\r` and `\n` characters, rejecting any detected carriage returns or newlines with a security exception.

---

### Source 3: OWASP Official Guidance — Cross-Site Scripting (XSS) Prevention in HTML Emails
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
* **Technical Topic Consulted**: Context-aware HTML entity encoding for dynamic template variables.
* **Why It Was Needed**: Modern email clients render HTML markup. If user-controlled strings (e.g., student name, lesson inquiry notes, tutor headline) are interpolated into email templates without escaping, malicious markup could be rendered in the recipient's mail client.
* **Implementation Decision Informed**:
  - Implemented contextual escaping in email templates via `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
  - All dynamic data passed to email templates is escaped by default, preventing HTML injection into notification bodies.

---

### Source 4: PHP Official Documentation — Language Exceptions & Non-Blocking Error Handling
* **Source Organization**: The PHP Group
* **Official URL**: [https://www.php.net/manual/en/language.exceptions.php](https://www.php.net/manual/en/language.exceptions.php)
* **Technical Topic Consulted**: Exception hierarchies, try-catch structures, and non-blocking recovery patterns.
* **Why It Was Needed**: To guarantee that email delivery failures (e.g., remote provider outages, network timeouts, or invalid credentials) do not crash critical business transactions or cause already-committed booking state changes to roll back.
* **Implementation Decision Informed**:
  - Established a clear transaction boundary: database operations commit first (`$this->pdo->commit()`), releasing all row locks.
  - Email notification dispatch occurs *after* transaction commit, wrapped in non-blocking try-catch blocks that log failures via `Logger` and `AuditService` without interrupting user HTTP responses.

---

## 3. Topics Where No External Source Was Needed

The following technical areas were fully covered by existing internal project code, Phase 1–7 baselines, and architectural documentation:
1. **Provider Abstraction**: Implemented provider-agnostic adapter interface (`EmailProviderInterface`) following the Master Project Document v2.0 requirement to keep commercial provider selection deferred.
2. **Audit Logging**: Reused existing `App\Services\AuditService` immutable audit ledger writing to `audit_logs`.
3. **Database Transactions**: Reused existing Phase 3 and Phase 7 PDO transaction architecture.
4. **Timezone Handling**: Reused existing `App\Support\Timezone` and UTC database storage convention with London display formatting.
5. **View Rendering**: Reused existing view layout concepts adapted for email markup.
