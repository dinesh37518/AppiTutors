# Phase 7: External Technical Sources Register

**Project**: UK Tutoring Platform  
**Document**: Phase 7 External Sources Log  
**Authoritative Baseline**: Production Master Project Document v2.0  
**Phase**: Phase 7 — Booking Engine Implementation  
**Status**: APPROVED BASELINE VERIFIED  

---

## 1. Compliance Rule

In strict compliance with the project specifications, external sources are permitted **only** when technical implementation details require official verification. External sources may clarify technical implementation, but they **must not** introduce new business requirements or alter architectural baselines.

Unapproved sources (random tutorials, Stack Overflow, competitor platforms, arbitrary GitHub repositories, or unofficial architecture blogs) are strictly prohibited.

---

## 2. Official Technical Sources Consulted

### Source 1: MySQL 8.4 Reference Manual — Locking Reads (`SELECT ... FOR UPDATE`)
* **Source Organization**: Oracle Corporation / MySQL Documentation
* **Official URL**: [https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)
* **Technical Topic Consulted**: InnoDB locking reads, row-level exclusive locks (`FOR UPDATE`), lock acquisition order, and concurrency isolation.
* **Why It Was Needed**: To implement concurrency-safe slot reservations and prevent double-booking when multiple student/parent users attempt to book the identical availability slot at the same microsecond.
* **Implementation Decision Informed**:
  - Implemented `SELECT id, tutor_user_id, starts_at_utc, ends_at_utc, status FROM availability_slots WHERE id = ? FOR UPDATE` inside an active transaction in `App\Services\BookingService::createBooking()`.
  - Serializes concurrent transactions attempting to acquire the same slot: the first transaction holds the lock, verifies `status = 'PUBLISHED'`, sets `status = 'BOOKED'`, and commits; the second transaction waits, acquires the lock on the committed row, detects `status = 'BOOKED'`, and is safely rejected with HTTP 409 Conflict.

---

### Source 2: MySQL 8.4 Reference Manual — InnoDB Transaction Model & Isolation Levels
* **Source Organization**: Oracle Corporation / MySQL Documentation
* **Official URL**: [https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-model.html](https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-model.html)
* **Technical Topic Consulted**: ACID transaction guarantees, `REPEATABLE READ` default isolation level, and rollback behaviors under row-level locking.
* **Why It Was Needed**: To ensure that partial booking records or dangling availability slot states are never committed if validation fails or an exception occurs midway through booking execution.
* **Implementation Decision Informed**:
  - Enclosed the entire multi-step booking sequence (slot lock, tutor bookability verification, child ownership assertion, booking row insertion, status history row insertion, slot state update to `BOOKED`) within an atomic transaction.
  - Implemented strict rollback guards in `BookingService` to ensure any validation exception rolls back all uncommitted writes and immediately releases acquired row locks.

---

### Source 3: PHP Official Documentation — PDO Transactions & Prepared Statements
* **Source Organization**: The PHP Group
* **Official URL**: [https://www.php.net/manual/en/pdo.begintransaction.php](https://www.php.net/manual/en/pdo.begintransaction.php)
* **Technical Topic Consulted**: `PDO::beginTransaction()`, `PDO::commit()`, `PDO::rollBack()`, and active transaction status inspection (`PDO::inTransaction()`).
* **Why It Was Needed**: To safely handle PHP runtime exceptions, database timeouts, and constraint violations during booking lifecycle operations.
* **Implementation Decision Informed**:
  - Implemented `try { $this->pdo->beginTransaction(); ... $this->pdo->commit(); } catch (Throwable $e) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }` across `createBooking()`, `confirmBooking()`, `rejectBooking()`, and `cancelBooking()`.

---

### Source 4: OWASP API Security Top 10 — Broken Object Level Authorization (BOLA / IDOR)
* **Source Organization**: Open Worldwide Application Security Project (OWASP)
* **Official URL**: [https://owasp.org/API-Security/editions/2023/en/0xa1-broken-object-level-authorization/](https://owasp.org/API-Security/editions/2023/en/0xa1-broken-object-level-authorization/)
* **Technical Topic Consulted**: Object-level access control, horizontal privilege escalation, and relationship verification.
* **Why It Was Needed**: To verify that students/parents cannot view or mutate other users' bookings, cannot book other parents' children, and tutors cannot access or confirm bookings assigned to other tutors.
* **Implementation Decision Informed**:
  - In `BookingService::getBooking()`, enforce that `$currentUser->id === $booking['student_user_id']` (for students) or `$currentUser->id === $booking['tutor_user_id']` (for tutors) or `$currentUser->isManager()`.
  - In `BookingService::createBooking()`, when `child_id` is provided, query `children` table and strictly assert that `child.parent_user_id === $currentUser->id`.
  - In `BookingService::confirmBooking()` and `rejectBooking()`, strictly enforce that `booking.tutor_user_id === $currentUser->id` (or manager).

---

## 3. Topics Where No External Source Was Needed

The following technical areas were fully covered by existing internal project code, Phase 1–6 baselines, and architectural documentation:
1. **CSRF Protection**: Reused existing `App\Support\Csrf` token generation and verification architecture.
2. **Audit Logging**: Reused existing `App\Services\AuditService` immutable audit ledger writing to `audit_logs`.
3. **HTML Escaping & View Rendering**: Reused existing `App\Support\View::render` and global `e()` escaping helper.
4. **Tutor Bookability Gate**: Reused existing `App\Services\TutorService::isBookable()` verifying account `ACTIVE`, approval `APPROVED`, and DBS `VERIFIED`.
5. **Timezone Handling**: Reused existing `App\Support\Timezone` and UTC database storage convention with London display formatting.
