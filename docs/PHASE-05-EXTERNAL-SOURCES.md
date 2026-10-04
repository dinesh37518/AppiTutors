# PHASE 5 — EXTERNAL SOURCES LOG

**Project**: UK Tutoring Platform  
**Phase**: Phase 5 — Tutor Profile, Approval & Availability Implementation  
**Date**: October 2026  
**Document Version**: 1.0  

---

## 1. REQUIREMENT BASELINE DECLARATION

> **External sources required for Phase 5 business requirements: NO.**

All business logic, tutor onboarding workflows, managerial governance hierarchies, safeguarding gates, availability rules, and overlap prevention semantics for the UK Tutoring Platform are strictly derived from the approved internal project specifications:

1. **Production Master Project Document v2.0 | UK Tutoring Platform**
2. **Approved Phase 1 — Discovery** (`docs/PHASE-01-DISCOVERY.md`)
3. **Approved Phase 2 — Architecture** (`docs/PHASE-02-ARCHITECTURE.md`)
4. **Verified Phase 3 — Foundation** (`docs/PHASE-03-FOUNDATION.md`)
5. **Approved Phase 4 — Public Website** (`docs/PHASE-04-PUBLIC-WEBSITE.md`)
6. **Phase 4 Completion Report** (`docs/PHASE-04-COMPLETION-REPORT.md`)

No external competitor websites, community blog posts, Stack Overflow threads, unverified third-party libraries, or unapproved tutorials were used as requirements sources. The Master Document remains the sole authoritative baseline for platform requirements.

---

## 2. OFFICIAL TECHNICAL REFERENCES CONSULTED

Where implementation required technical verification of runtime behaviors, API contracts, concurrency locking semantics, or cryptographic standards, only official, authoritative documentation was referenced:

| Technology / Authority | Official Source & URL | Technical Information Consulted | Impact on Implementation |
| :--- | :--- | :--- | :--- |
| **Official Firebase Authentication Documentation** | Google Firebase Documentation<br>`https://firebase.google.com/docs/auth/admin/verify-id-tokens` | Firebase ID token verification semantics, clock skew tolerance, signature verification via Google Public Keys, and distinction between client authentication and database authorization. | Ensured Firebase establishes identity only (`firebase_uid`), while MySQL strictly establishes role authority (`role = 'TUTOR'`) and state (`status = 'PENDING'`). Verified that client-supplied roles in registration requests are completely discarded. Firebase authentication boundaries and token-verification integration were verified within the local implementation/test environment; production Firebase credentials and cloud deployment-environment verification remain deployment/UAT activities. |
| **Official PHP Documentation** | The PHP Group Reference Manual<br>`https://www.php.net/manual/en/` | `DateTimeImmutable` timezone conversion (`DateTimeZone('Europe/London')` to `DateTimeZone('UTC')`), `finfo_file` MIME inspection, `random_bytes`, `hash_equals` timing-safe comparison, and PDO transaction management. | Implemented `Timezone::londonToUtc` / `utcToLondon` handling GMT/BST transitions, timing-safe session CSRF tokens in `App\Support\Csrf`, and strict server-side upload MIME checks (`application/pdf`, `image/png`, `image/jpeg`). |
| **Official MySQL 8.4 LTS Documentation** | Oracle MySQL 8.4 Reference Manual<br>`https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html` | InnoDB locking reads (`SELECT ... FOR UPDATE`), transaction isolation levels, row-level locking, and overlap detection predicate logic: `(starts_at_utc < candidate_end) AND (ends_at_utc > candidate_start)`. | Implemented atomic, transaction-isolated availability overlap prevention in `AvailabilityService::assertNoOverlap` preventing race conditions under concurrent requests. |
| **Official OWASP Security Guidelines** | OWASP Foundation<br>`https://cheatsheetseries.owasp.org/` | OWASP File Upload Cheat Sheet, Insecure Direct Object Reference (IDOR) Prevention Cheat Sheet, and Access Control Cheat Sheet. | Ensured DBS uploads are stored strictly outside the public web root (`storage/private/dbs/`) with generated cryptographic random names, MIME and extension verification, fine-grained ownership enforcement (`Authorization::assertOwnership`), and audit logging. |

---

## 3. UNUSED / EXCLUDED SOURCES

The following external sources were deliberately excluded to maintain strict compliance with project governance:

* **No Unofficial Tutorials or Stack Overflow Snippets**: All code was designed directly against official language, database, and framework documentation.
* **No Unapproved Third-Party Booking Engines or Calendars**: No full-calendar or proprietary scheduling libraries were added. Availability slots are cleanly managed through bespoke, tested server-side services.
* **No Speculative Legal Certifications**: No third-party privacy badges or unverified legal statements were adopted; safeguarding and DBS controls are accurately described using technical engineering-control language pending client-approved legal policies.

---

## 4. EXTERNAL SOURCE VERIFICATION SIGN-OFF

* **Requirement Baseline**: Production Master Project Document v2.0
* **Technical Compliance**: 100% compliant with allowed official sources
* **Audit Trail**: All technical decisions traceable to official documentation
