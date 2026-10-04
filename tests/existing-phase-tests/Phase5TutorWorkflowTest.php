<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\DbsService;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\OverlapException;
use App\Services\Exceptions\ValidationException;
use App\Services\TutorService;
use App\Support\Timezone;

class Phase5TutorWorkflowTest
{
    private PDO $pdo;
    private TutorService $tutorService;
    private DbsService $dbsService;
    private AvailabilityService $availabilityService;
    private AuditService $auditService;

    private int $passed = 0;
    private int $failed = 0;

    // Fixtures
    private ?UserContext $managerUser = null;
    private ?UserContext $studentUser = null;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->auditService = new AuditService($this->pdo);
        $this->tutorService = new TutorService($this->pdo, null, $this->auditService);
        $this->dbsService = new DbsService($this->pdo, null, $this->auditService);
        $this->availabilityService = new AvailabilityService($this->pdo, null, $this->auditService, $this->tutorService);

        $this->setupFixtures();
    }

    private function setupFixtures(): void
    {
        // 1. Ensure Manager fixture exists
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE role = 'MANAGER' AND status = 'ACTIVE' LIMIT 1");
        $stmt->execute();
        $mgrRow = $stmt->fetch();
        if ($mgrRow) {
            $this->managerUser = UserContext::fromDatabaseRow($mgrRow);
        } else {
            $uid = 'mgr_test_' . bin2hex(random_bytes(6));
            $this->pdo->prepare("
                INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
                VALUES (?, 'mgr@apptutors.co.uk', 'Manager Admin', 'MANAGER', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ")->execute([$uid]);
            $mgrId = (int)$this->pdo->lastInsertId();
            $this->managerUser = new UserContext($mgrId, $uid, 'mgr@apptutors.co.uk', 'Manager Admin', 'MANAGER', 'ACTIVE', true);
        }

        // 2. Ensure Student/Parent fixture exists
        $uid = 'stu_test_' . bin2hex(random_bytes(6));
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Test Student Parent', 'STUDENT_PARENT', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uid, $uid . '@example.com']);
        $stuId = (int)$this->pdo->lastInsertId();
        $this->studentUser = new UserContext($stuId, $uid, $uid . '@example.com', 'Test Student Parent', 'STUDENT_PARENT', 'ACTIVE', true);
    }

    public function runAll(): void
    {
        echo "=======================================================\n";
        echo " UK TUTORING PLATFORM — PHASE 5 TUTOR WORKFLOW TESTS\n";
        echo "=======================================================\n\n";

        $this->testTutorRegistration();
        $this->testTutorProfile();
        $this->testTutorApprovalLifecycle();
        $this->testDbsSafeguardingWorkflow();
        $this->testAvailabilityAndOverlapPrevention();
        $this->testTimezoneAndDstHandling();
        $this->testSecurityAndWebBoundaries();

        echo "=======================================================\n";
        $total = $this->passed + $this->failed;
        echo " TEST SUMMARY: {$this->passed}/{$total} PASSED (" . ($total > 0 ? round(($this->passed / $total) * 100) : 0) . "%)\n";
        if ($this->failed === 0) {
            echo " STATUS: ALL PHASE 5 TUTOR WORKFLOW CHECKS PASSED!\n";
        } else {
            echo " STATUS: {$this->failed} CHECKS FAILED!\n";
        }
        echo "=======================================================\n";
    }

    // -------------------------------------------------------------
    // 1. TUTOR REGISTRATION
    // -------------------------------------------------------------
    private function testTutorRegistration(): void
    {
        echo "--- 1. Tutor Registration Workflow ---\n";

        // 1.1 Valid Tutor Registration
        $uid = 'fb_tutor_' . bin2hex(random_bytes(6));
        $email = $uid . '@test.apptutors.co.uk';
        try {
            $result = $this->tutorService->registerTutor([
                'firebase_uid' => $uid,
                'email' => $email,
                'display_name' => 'Dr John Watson',
                'headline' => 'GCSE & A-Level Physics Expert',
                'bio' => 'Experienced UK educator with 10 years classroom experience.',
                'qualifications' => 'BSc Physics (Imperial), PGCE (Oxon)',
                'hourly_rate' => 45.00,
                'subjects' => ['Physics', 'Mathematics'],
                'curriculum' => ['GCSE', 'A-Level'],
            ]);

            $this->assert(
                $result['email'] === $email && $result['approval_status'] === 'PENDING' && $result['status'] === 'PENDING',
                'Registration: Valid tutor registered with PENDING role & status',
                "Tutor ID: {$result['id']}, Role: {$result['role']}, Status: {$result['status']}, Approval: {$result['approval_status']}"
            );

            $this->assert(
                $result['is_bookable'] === false,
                'Registration: New tutor starts strictly non-bookable',
                'is_bookable is false as required by safeguarding gate'
            );
        } catch (Throwable $e) {
            $this->recordFailure('Registration: Valid tutor registered', $e->getMessage());
        }

        // 1.2 Invalid input rejection
        try {
            $this->tutorService->registerTutor([
                'firebase_uid' => '',
                'email' => 'invalid-email',
                'display_name' => '',
            ]);
            $this->recordFailure('Registration: Malformed input rejection', 'Expected ValidationException not thrown');
        } catch (ValidationException $e) {
            $this->recordPass('Registration: Malformed input rejected server-side', "Caught ValidationException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Registration: Malformed input rejection', 'Unexpected exception: ' . $e->getMessage());
        }

        // 1.3 Client-supplied role cannot create MANAGER authority
        $uidMgrHack = 'fb_tutor_hack_' . bin2hex(random_bytes(6));
        try {
            $hackResult = $this->tutorService->registerTutor([
                'firebase_uid' => $uidMgrHack,
                'email' => $uidMgrHack . '@test.apptutors.co.uk',
                'display_name' => 'Hacker Attempt',
                'role' => 'MANAGER',
                'status' => 'ACTIVE',
                'approval_status' => 'APPROVED',
                'is_manager' => true,
            ]);

            $this->assert(
                $hackResult['role'] === 'TUTOR' && $hackResult['status'] === 'PENDING' && $hackResult['approval_status'] === 'PENDING',
                'Registration: Client-supplied MANAGER role discarded by MySQL authority',
                "MySQL assigned role: {$hackResult['role']}, status: {$hackResult['status']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Registration: Client role hack prevention', $e->getMessage());
        }

        // 1.4 Duplicate Firebase UID handling
        try {
            $this->tutorService->registerTutor([
                'firebase_uid' => $uid,
                'email' => 'another_' . $email,
                'display_name' => 'Duplicate UID',
            ]);
            $this->recordFailure('Registration: Duplicate UID rejection', 'Expected duplicate rejection not thrown');
        } catch (ValidationException $e) {
            $this->recordPass('Registration: Duplicate Firebase UID rejected (409)', "Code: {$e->getErrorCode()}");
        } catch (Throwable $e) {
            $this->recordFailure('Registration: Duplicate UID rejection', 'Unexpected exception: ' . $e->getMessage());
        }

        // 1.5 Duplicate Email handling
        try {
            $this->tutorService->registerTutor([
                'firebase_uid' => 'diff_' . $uid,
                'email' => $email,
                'display_name' => 'Duplicate Email',
            ]);
            $this->recordFailure('Registration: Duplicate Email rejection', 'Expected duplicate email rejection not thrown');
        } catch (ValidationException $e) {
            $this->recordPass('Registration: Duplicate Email rejected (409)', "Code: {$e->getErrorCode()}");
        } catch (Throwable $e) {
            $this->recordFailure('Registration: Duplicate Email rejection', 'Unexpected exception: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 2. TUTOR PROFILE & IDOR PROTECTION
    // -------------------------------------------------------------
    private function testTutorProfile(): void
    {
        echo "\n--- 2. Tutor Profile & IDOR Ownership Controls ---\n";

        // Create Tutor A
        $tutorA = $this->createTestTutor('Tutor A', true);
        // Create Tutor B
        $tutorB = $this->createTestTutor('Tutor B', true);

        // 2.1 Tutor A can view own profile
        try {
            $profileA = $this->tutorService->getProfile($tutorA->id, $tutorA);
            $this->assert(
                $profileA['id'] === $tutorA->id,
                'Profile: Tutor A can view own profile',
                "Profile user ID matches: {$profileA['id']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Profile: Tutor A view own profile', $e->getMessage());
        }

        // 2.2 Tutor A can update own profile
        try {
            $updated = $this->tutorService->updateProfile($tutorA->id, [
                'headline' => 'Updated Headline Specialist',
                'bio' => 'Updated bio details for Dr Watson',
                'subjects' => ['Further Maths', 'Physics'],
            ], $tutorA);

            $this->assert(
                $updated['headline'] === 'Updated Headline Specialist' && in_array('Further Maths', $updated['subjects'] ?? [], true),
                'Profile: Tutor A can update own profile fields',
                "Updated headline: {$updated['headline']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Profile: Tutor A update own profile', $e->getMessage());
        }

        // 2.3 Tutor A cannot modify Tutor B's profile (IDOR)
        try {
            $this->tutorService->updateProfile($tutorB->id, [
                'headline' => 'Malicious IDOR overwrite',
            ], $tutorA);
            $this->recordFailure('Profile: IDOR Protection', 'Tutor A was able to update Tutor B profile!');
        } catch (ForbiddenException $e) {
            $this->recordPass('Profile: IDOR Protection (Tutor A denied modifying Tutor B)', "Caught ForbiddenException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Profile: IDOR Protection', 'Unexpected exception: ' . $e->getMessage());
        }

        // 2.4 Inactive Tutor Denied Profile Updates
        $inactiveTutor = $this->createTestTutor('Inactive Tutor', false); // status = PENDING
        try {
            $this->tutorService->updateProfile($inactiveTutor->id, [
                'headline' => 'Trying to update while inactive',
            ], $inactiveTutor);
            $this->recordFailure('Profile: Inactive account update rejection', 'Inactive tutor was allowed to update profile');
        } catch (ForbiddenException $e) {
            $this->recordPass('Profile: Inactive account denied updates (403)', "Caught ForbiddenException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Profile: Inactive account update rejection', 'Unexpected exception: ' . $e->getMessage());
        }

        // 2.5 Manager can view and administer any tutor profile
        try {
            $profileByMgr = $this->tutorService->getProfile($tutorB->id, $this->managerUser);
            $this->assert(
                $profileByMgr['id'] === $tutorB->id,
                'Profile: Manager authority allows viewing tutor profile',
                "Manager viewed Tutor B ID: {$profileByMgr['id']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Profile: Manager view tutor profile', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 3. TUTOR APPROVAL LIFECYCLE & BOOKABILITY GATE
    // -------------------------------------------------------------
    private function testTutorApprovalLifecycle(): void
    {
        echo "\n--- 3. Tutor Approval Lifecycle & Safeguarding Gate ---\n";

        $candidate = $this->createTestTutor('Candidate Tutor', false); // PENDING

        // 3.1 Tutor cannot approve self
        try {
            $this->tutorService->managerApproveTutor($candidate->id, $candidate);
            $this->recordFailure('Approval: Tutor self-approval rejection', 'Tutor was able to approve self!');
        } catch (ForbiddenException $e) {
            $this->recordPass('Approval: Tutor cannot approve self (403)', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Approval: Tutor self-approval rejection', 'Unexpected: ' . $e->getMessage());
        }

        // 3.2 Tutor cannot change own approval_status via updateProfile
        try {
            $res = $this->tutorService->updateProfile($candidate->id, [
                'approval_status' => 'APPROVED',
                'status' => 'ACTIVE',
            ], $candidate);
            // Even if method threw inactive exception, let's verify DB approval_status
            $stmt = $this->pdo->prepare('SELECT approval_status FROM tutor_profiles WHERE user_id = ?');
            $stmt->execute([$candidate->id]);
            $row = $stmt->fetch();
            $this->assert(
                $row['approval_status'] === 'PENDING',
                'Approval: Tutor cannot manipulate approval_status via update payload',
                "approval_status remains {$row['approval_status']}"
            );
        } catch (ForbiddenException $e) {
            $this->recordPass('Approval: Tutor cannot manipulate approval_status via update payload', "Access denied correctly: {$e->getMessage()}");
        }

        // 3.3 Unauthorized user (Student/Parent) cannot approve tutor
        try {
            $this->tutorService->managerApproveTutor($candidate->id, $this->studentUser);
            $this->recordFailure('Approval: Student cannot approve tutor', 'Student was allowed to approve tutor!');
        } catch (ForbiddenException $e) {
            $this->recordPass('Approval: Student/Parent denied approval action (403)', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Approval: Student approval rejection', 'Unexpected: ' . $e->getMessage());
        }

        // 3.4 Manager can approve pending tutor
        try {
            $approved = $this->tutorService->managerApproveTutor($candidate->id, $this->managerUser);
            $this->assert(
                $approved['approval_status'] === 'APPROVED' && $approved['status'] === 'ACTIVE' && $approved['approved_by'] === $this->managerUser->id,
                'Approval: Manager approves pending tutor to APPROVED/ACTIVE',
                "Approval status: {$approved['approval_status']}, Approved at: {$approved['approved_at']}"
            );

            // Audit log check
            $stmtAudit = $this->pdo->prepare("SELECT * FROM audit_logs WHERE action = 'MANAGER_APPROVE_TUTOR' AND entity_id = ? ORDER BY id DESC LIMIT 1");
            $stmtAudit->execute([(string)$candidate->id]);
            $auditRow = $stmtAudit->fetch();
            $this->assert(
                !empty($auditRow) && (int)$auditRow['actor_user_id'] === $this->managerUser->id,
                'Approval: Manager approval action recorded in immutable audit log',
                "Audit log ID: {$auditRow['id']}, Action: {$auditRow['action']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Approval: Manager approve tutor', $e->getMessage());
        }

        // 3.5 Manager can suspend approved tutor
        try {
            $suspended = $this->tutorService->managerSuspendTutor($candidate->id, 'Safeguarding credential audit required', $this->managerUser);
            $this->assert(
                $suspended['approval_status'] === 'SUSPENDED' && $suspended['status'] === 'SUSPENDED',
                'Approval: Manager suspends approved tutor to SUSPENDED',
                "Approval status: {$suspended['approval_status']}, User status: {$suspended['status']}"
            );

            // Verify tutor is now NOT bookable
            $this->assert(
                $this->tutorService->isBookable($candidate->id) === false,
                'Approval: Suspended tutor immediately fails bookability gate',
                'isBookable returned false'
            );
        } catch (Throwable $e) {
            $this->recordFailure('Approval: Manager suspend tutor', $e->getMessage());
        }

        // 3.6 Manager can reinstate suspended tutor
        try {
            $reinstated = $this->tutorService->managerReinstateTutor($candidate->id, $this->managerUser);
            $this->assert(
                $reinstated['approval_status'] === 'APPROVED' && $reinstated['status'] === 'ACTIVE',
                'Approval: Manager reinstates suspended tutor back to APPROVED',
                "Approval status: {$reinstated['approval_status']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Approval: Manager reinstate tutor', $e->getMessage());
        }

        // 3.7 Manager rejection workflow
        $rejectedCandidate = $this->createTestTutor('Rejected Candidate', false);
        try {
            $rejected = $this->tutorService->managerRejectTutor($rejectedCandidate->id, 'Incomplete subject qualifications', $this->managerUser);
            $this->assert(
                $rejected['approval_status'] === 'REJECTED' && $rejected['status'] === 'SUSPENDED',
                'Approval: Manager rejects candidate to REJECTED/SUSPENDED',
                "Approval status: {$rejected['approval_status']}, User status: {$rejected['status']}"
            );
            $this->assert(
                $this->tutorService->isBookable($rejectedCandidate->id) === false,
                'Approval: Rejected tutor is strictly non-bookable',
                'isBookable returned false'
            );
        } catch (Throwable $e) {
            $this->recordFailure('Approval: Manager reject tutor', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 4. DBS WORKFLOW & TECHNICAL BOUNDARY
    // -------------------------------------------------------------
    private function testDbsSafeguardingWorkflow(): void
    {
        echo "\n--- 4. DBS Safeguarding Workflow & Storage Controls ---\n";

        $dbsTutor = $this->createTestTutor('DBS Test Tutor', true);

        // 4.1 Valid DBS Certificate Submission (Metadata without document)
        try {
            $sub = $this->dbsService->submitDbs($dbsTutor->id, '001594837261', null, $dbsTutor);
            $this->assert(
                $sub['dbs_status'] === 'SUBMITTED' && $sub['dbs_certificate_number'] === '001594837261',
                'DBS: Tutor can submit DBS certificate number',
                "Status: {$sub['dbs_status']}, Cert: {$sub['dbs_certificate_number']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('DBS: Submit DBS certificate number', $e->getMessage());
        }

        // 4.2 Unauthorized tutor cannot submit DBS for another tutor (IDOR)
        $intruder = $this->createTestTutor('Intruder Tutor', true);
        try {
            $this->dbsService->submitDbs($dbsTutor->id, '009999999999', null, $intruder);
            $this->recordFailure('DBS: IDOR Protection', 'Intruder was able to submit DBS for another tutor');
        } catch (ForbiddenException $e) {
            $this->recordPass('DBS: IDOR Protection on DBS submission (403)', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('DBS: IDOR Protection', 'Unexpected: ' . $e->getMessage());
        }

        // 4.3 Invalid upload rejection (Oversized file)
        $oversizedFile = [
            'name' => 'huge_dbs.pdf',
            'type' => 'application/pdf',
            'tmp_name' => __DIR__ . '/test_large.tmp',
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * 1024 * 1024, // 6MB > 5MB limit
        ];
        try {
            $this->dbsService->submitDbs($dbsTutor->id, '001594837261', $oversizedFile, $dbsTutor);
            $this->recordFailure('DBS: Oversized file rejection', 'Oversized file was accepted');
        } catch (ValidationException $e) {
            $this->recordPass('DBS: File upload exceeding 5MB strictly rejected', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('DBS: Oversized file rejection', 'Unexpected: ' . $e->getMessage());
        }

        // 4.4 Executable upload rejection (.php, .exe, .sh)
        $executableFile = [
            'name' => 'malicious.php',
            'type' => 'application/x-php',
            'tmp_name' => __DIR__ . '/test_php.tmp',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];
        file_put_contents($executableFile['tmp_name'], '<?php phpinfo(); ?>');
        try {
            $this->dbsService->submitDbs($dbsTutor->id, '001594837261', $executableFile, $dbsTutor);
            $this->recordFailure('DBS: Executable upload rejection', 'PHP script upload was accepted!');
        } catch (ValidationException $e) {
            $this->recordPass('DBS: Executable file upload rejected by MIME and extension guard', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('DBS: Executable upload rejection', 'Unexpected: ' . $e->getMessage());
        } finally {
            if (file_exists($executableFile['tmp_name'])) {
                unlink($executableFile['tmp_name']);
            }
        }

        // 4.5 Manager DBS Verification Action
        try {
            $verified = $this->dbsService->verifyDbs($dbsTutor->id, $this->managerUser);
            $this->assert(
                $verified['dbs_status'] === 'VERIFIED',
                'DBS: Manager can verify DBS certificate',
                "DBS Status: {$verified['dbs_status']}"
            );

            // Check audit log
            $stmtAudit = $this->pdo->prepare("SELECT * FROM audit_logs WHERE action = 'MANAGER_VERIFY_DBS' AND entity_id = ? ORDER BY id DESC LIMIT 1");
            $stmtAudit->execute([(string)$dbsTutor->id]);
            $auditRow = $stmtAudit->fetch();
            $this->assert(
                !empty($auditRow) && (int)$auditRow['actor_user_id'] === $this->managerUser->id,
                'DBS: Manager DBS verification recorded in audit log',
                "Audit log action: {$auditRow['action']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('DBS: Manager verify DBS', $e->getMessage());
        }

        // 4.6 Manager DBS Rejection Action
        $tutorDbsReject = $this->createTestTutor('DBS Reject Tutor', true);
        $this->dbsService->submitDbs($tutorDbsReject->id, '009876543210', null, $tutorDbsReject);
        try {
            $rejectedDbs = $this->dbsService->rejectDbs($tutorDbsReject->id, 'Certificate date exceeds acceptable window', $this->managerUser);
            $this->assert(
                $rejectedDbs['dbs_status'] === 'REJECTED',
                'DBS: Manager can reject unverified DBS credentials',
                "DBS Status: {$rejectedDbs['dbs_status']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('DBS: Manager reject DBS', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 5. AVAILABILITY MANAGEMENT & OVERLAP PREVENTATION
    // -------------------------------------------------------------
    private function testAvailabilityAndOverlapPrevention(): void
    {
        echo "\n--- 5. Availability Management & Overlap Prevention ---\n";

        // Create an approved and bookable tutor
        $availTutor = $this->createTestTutor('Avail Tutor', true);
        $this->tutorService->managerApproveTutor($availTutor->id, $this->managerUser);
        $this->dbsService->verifyDbs($availTutor->id, $this->managerUser);

        // Ensure tutor is fully bookable
        $this->assert(
            $this->tutorService->isBookable($availTutor->id) === true,
            'Availability: Tutor is approved and DBS verified (bookable)',
            'Bookability gate passed'
        );

        // 5.1 Create Valid Availability Slot (e.g. 10:00 to 11:00 on future date)
        $slot1 = null;
        try {
            $slot1 = $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 10:00:00',
                '2026-11-10 11:00:00',
                $availTutor
            );

            $this->assert(
                $slot1['starts_at_london_iso'] === '2026-11-10 10:00:00' && $slot1['ends_at_london_iso'] === '2026-11-10 11:00:00' && $slot1['status'] === 'PUBLISHED',
                'Availability: Create valid discrete availability slot',
                "Slot ID: {$slot1['id']}, Status: {$slot1['status']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Create valid slot', $e->getMessage());
        }

        // 5.2 Reject Invalid Zero or Negative Duration
        try {
            $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 12:00:00',
                '2026-11-10 11:00:00', // Ends before it starts
                $availTutor
            );
            $this->recordFailure('Availability: Negative duration rejection', 'Negative duration was accepted');
        } catch (ValidationException $e) {
            $this->recordPass('Availability: Negative duration rejected (ends_at <= starts_at)', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Negative duration rejection', 'Unexpected: ' . $e->getMessage());
        }

        // 5.3 Overlap Prevention: Existing is 10:00–11:00
        // Test Overlap Case A: 10:30–11:30 (Late overlap)
        try {
            $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 10:30:00',
                '2026-11-10 11:30:00',
                $availTutor
            );
            $this->recordFailure('Availability: Overlap Case A (10:30–11:30)', 'Overlapping slot was accepted');
        } catch (OverlapException $e) {
            $this->recordPass('Availability: Overlap Case A (10:30–11:30) rejected', "Caught OverlapException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Overlap Case A', 'Unexpected: ' . $e->getMessage());
        }

        // Test Overlap Case B: 09:30–10:30 (Early overlap)
        try {
            $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 09:30:00',
                '2026-11-10 10:30:00',
                $availTutor
            );
            $this->recordFailure('Availability: Overlap Case B (09:30–10:30)', 'Overlapping slot was accepted');
        } catch (OverlapException $e) {
            $this->recordPass('Availability: Overlap Case B (09:30–10:30) rejected', "Caught OverlapException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Overlap Case B', 'Unexpected: ' . $e->getMessage());
        }

        // Test Overlap Case C: 09:00–12:00 (Enclosing overlap)
        try {
            $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 09:00:00',
                '2026-11-10 12:00:00',
                $availTutor
            );
            $this->recordFailure('Availability: Overlap Case C (09:00–12:00)', 'Overlapping slot was accepted');
        } catch (OverlapException $e) {
            $this->recordPass('Availability: Overlap Case C (09:00–12:00) rejected', "Caught OverlapException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Overlap Case C', 'Unexpected: ' . $e->getMessage());
        }

        // Test Overlap Case D: 10:15–10:45 (Interior subset overlap)
        try {
            $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 10:15:00',
                '2026-11-10 10:45:00',
                $availTutor
            );
            $this->recordFailure('Availability: Overlap Case D (10:15–10:45)', 'Overlapping slot was accepted');
        } catch (OverlapException $e) {
            $this->recordPass('Availability: Overlap Case D (10:15–10:45) rejected', "Caught OverlapException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Overlap Case D', 'Unexpected: ' . $e->getMessage());
        }

        // 5.4 Allow Adjacent Slots:
        // Existing is 10:00–11:00
        // Adjacent Prior: 09:00–10:00
        try {
            $adjacentPrior = $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 09:00:00',
                '2026-11-10 10:00:00',
                $availTutor
            );
            $this->assert(
                $adjacentPrior['starts_at_london_iso'] === '2026-11-10 09:00:00' && $adjacentPrior['ends_at_london_iso'] === '2026-11-10 10:00:00',
                'Availability: Allow preceding adjacent slot (09:00–10:00)',
                "Adjacent slot created: ID {$adjacentPrior['id']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Allow preceding adjacent slot', $e->getMessage());
        }

        // Adjacent Subsequent: 11:00–12:00
        try {
            $adjacentSubsequent = $this->availabilityService->createSlot(
                $availTutor->id,
                '2026-11-10 11:00:00',
                '2026-11-10 12:00:00',
                $availTutor
            );
            $this->assert(
                $adjacentSubsequent['starts_at_london_iso'] === '2026-11-10 11:00:00' && $adjacentSubsequent['ends_at_london_iso'] === '2026-11-10 12:00:00',
                'Availability: Allow subsequent adjacent slot (11:00–12:00)',
                "Adjacent slot created: ID {$adjacentSubsequent['id']}"
            );
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Allow subsequent adjacent slot', $e->getMessage());
        }

        // 5.5 Update Own Slot
        try {
            if ($slot1) {
                $updatedSlot = $this->availabilityService->updateSlot(
                    $slot1['id'],
                    '2026-11-10 14:00:00',
                    '2026-11-10 15:00:00',
                    $availTutor
                );
                $this->assert(
                    $updatedSlot['starts_at_london_iso'] === '2026-11-10 14:00:00' && $updatedSlot['ends_at_london_iso'] === '2026-11-10 15:00:00',
                    'Availability: Tutor can update own availability slot',
                    "New time: {$updatedSlot['starts_at_london']} - {$updatedSlot['ends_at_london']}"
                );
            }
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Update own slot', $e->getMessage());
        }

        // 5.6 Delete Own Slot
        try {
            if ($slot1) {
                $delResult = $this->availabilityService->deleteSlot($slot1['id'], $availTutor);
                $this->assert(
                    $delResult === true,
                    'Availability: Tutor can remove own availability slot',
                    "Slot {$slot1['id']} removed"
                );
            }
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Delete own slot', $e->getMessage());
        }

        // 5.7 Tutor A cannot delete or modify Tutor B's slot (IDOR)
        $tutorOther = $this->createTestTutor('Tutor Other', true);
        $this->tutorService->managerApproveTutor($tutorOther->id, $this->managerUser);
        $this->dbsService->verifyDbs($tutorOther->id, $this->managerUser);
        $slotOther = $this->availabilityService->createSlot($tutorOther->id, '2026-11-15 10:00:00', '2026-11-15 11:00:00', $tutorOther);

        try {
            $this->availabilityService->deleteSlot($slotOther['id'], $availTutor);
            $this->recordFailure('Availability: IDOR Protection on slots', 'Tutor A was able to delete Tutor B slot');
        } catch (ForbiddenException $e) {
            $this->recordPass('Availability: IDOR Protection on slots (Tutor A denied modifying Tutor B slot)', "Caught: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: IDOR Protection on slots', 'Unexpected: ' . $e->getMessage());
        }

        // 5.8 Unapproved or Inactive Tutor cannot manage availability
        $unapprovedTutor = $this->createTestTutor('Unapproved Tutor', false);
        try {
            $this->availabilityService->createSlot($unapprovedTutor->id, '2026-11-20 10:00:00', '2026-11-20 11:00:00', $unapprovedTutor);
            $this->recordFailure('Availability: Unapproved tutor publishing blocked', 'Unapproved tutor created availability!');
        } catch (BookabilityException $e) {
            $this->recordPass('Availability: Unapproved tutor publishing strictly blocked by Bookability gate', "Caught BookabilityException: {$e->getMessage()}");
        } catch (ForbiddenException $e) {
            $this->recordPass('Availability: Inactive tutor blocked by authorization guard', "Caught ForbiddenException: {$e->getMessage()}");
        } catch (Throwable $e) {
            $this->recordFailure('Availability: Unapproved tutor publishing blocked', 'Unexpected: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 6. TIMEZONE, BST/GMT TRANSITIONS & UTC STORAGE
    // -------------------------------------------------------------
    private function testTimezoneAndDstHandling(): void
    {
        echo "\n--- 6. Timezone (Europe/London), BST/GMT & UTC Storage ---\n";

        // BST (British Summer Time) — July is UTC+1
        $summerLondon = '2026-07-15 14:00:00';
        $summerUtc = Timezone::londonToUtc($summerLondon);
        $this->assert(
            $summerUtc === '2026-07-15 13:00:00',
            'Timezone: BST summer time 14:00 London converts to 13:00 UTC (UTC+1)',
            "London: {$summerLondon} -> UTC: {$summerUtc}"
        );

        $summerBackToLondon = Timezone::utcToLondon($summerUtc, 'Y-m-d H:i:s');
        $this->assert(
            $summerBackToLondon === $summerLondon,
            'Timezone: BST UTC back to London round-trips identically',
            "UTC: {$summerUtc} -> London: {$summerBackToLondon}"
        );

        // GMT (Greenwich Mean Time) — January is UTC+0
        $winterLondon = '2026-01-15 14:00:00';
        $winterUtc = Timezone::londonToUtc($winterLondon);
        $this->assert(
            $winterUtc === '2026-01-15 14:00:00',
            'Timezone: GMT winter time 14:00 London converts to 14:00 UTC (UTC+0)',
            "London: {$winterLondon} -> UTC: {$winterUtc}"
        );

        $winterBackToLondon = Timezone::utcToLondon($winterUtc, 'Y-m-d H:i:s');
        $this->assert(
            $winterBackToLondon === $winterLondon,
            'Timezone: GMT UTC back to London round-trips identically',
            "UTC: {$winterUtc} -> London: {$winterBackToLondon}"
        );

        // Verify Database MySQL Session Timezone is strictly UTC (+00:00)
        $stmtTz = $this->pdo->query("SELECT @@session.time_zone as tz");
        $tzRow = $stmtTz->fetch();
        $this->assert(
            $tzRow['tz'] === '+00:00',
            'Database: PDO session timezone is strictly UTC (+00:00)',
            "Session timezone: {$tzRow['tz']}"
        );
    }

    // -------------------------------------------------------------
    // 7. SECURITY, BOUNDARIES & PUBLIC FILE PROTECTION
    // -------------------------------------------------------------
    private function testSecurityAndWebBoundaries(): void
    {
        echo "\n--- 7. Security, Boundaries & Storage Web Access ---\n";

        // 7.1 Verify private storage is outside public web root
        $privateDbsDir = file_exists(dirname(__DIR__) . '/storage/private/dbs') ? dirname(__DIR__) . '/storage/private/dbs' : dirname(__DIR__, 2) . '/storage/private/dbs';
        $this->assert(
            is_dir($privateDbsDir),
            'Storage: Private DBS directory exists outside web root',
            "Path: {$privateDbsDir}"
        );

        // 7.2 Attempting direct web access to storage directory returns 404 / 403
        $webUrl = 'http://127.0.0.1/storage/private/dbs/.gitkeep';
        $headers = @get_headers($webUrl);
        $status = $headers ? (int)substr($headers[0], 9, 3) : 0;
        $this->assert(
            $status === 403 || $status === 404,
            'Security: Direct web access to private storage files blocked (HTTP 403/404)',
            "HTTP Response code: {$status}"
        );

        // 7.3 Web Pages Load Correctly via HTTP (Apache)
        $pages = [
            'http://127.0.0.1/tutor-profile.php',
            'http://127.0.0.1/tutor-availability.php',
            'http://127.0.0.1/manager-tutors.php',
        ];

        foreach ($pages as $p) {
            $h = @get_headers($p);
            $st = $h ? (int)substr($h[0], 9, 3) : 0;
            $this->assert(
                $st === 200,
                "Web Route: {$p} returns 200 OK",
                "HTTP Status: {$st}"
            );
        }
    }

    // -------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------
    private function createTestTutor(string $name, bool $active = true): UserContext
    {
        $uid = 'test_tut_' . bin2hex(random_bytes(6));
        $email = $uid . '@example.co.uk';
        $status = $active ? 'ACTIVE' : 'PENDING';

        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, ?, 'TUTOR', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uid, $email, $name, $status]);

        $userId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("
            INSERT INTO tutor_profiles (user_id, headline, bio, qualifications, dbs_status, approval_status, created_at, updated_at)
            VALUES (?, 'Expert Tutor', 'Bio details', 'BSc Hons', 'NOT_SUBMITTED', 'PENDING', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$userId]);

        return new UserContext($userId, $uid, $email, $name, 'TUTOR', $status, true);
    }

    private function assert(bool $condition, string $name, string $detail = ''): void
    {
        if ($condition) {
            $this->recordPass($name, $detail);
        } else {
            $this->recordFailure($name, $detail);
        }
    }

    private function recordPass(string $name, string $detail = ''): void
    {
        $this->passed++;
        printf("[ PASS ] %-60s\n", $name);
        if (!empty($detail)) {
            echo "         Detail: {$detail}\n";
        }
    }

    private function recordFailure(string $name, string $detail = ''): void
    {
        $this->failed++;
        printf("[ FAIL ] %-60s\n", $name);
        if (!empty($detail)) {
            echo "         Detail: {$detail}\n";
        }
    }
}

// CLI Execution
$test = new Phase5TutorWorkflowTest();
$test->runAll();
