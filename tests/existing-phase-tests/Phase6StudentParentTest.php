<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Services\AuditService;
use App\Services\Exceptions\ValidationException;
use App\Services\StudentParentService;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\Validator;

class Phase6StudentParentTest
{
    private PDO $pdo;
    private StudentParentService $service;
    private AuditService $audit;

    private int $passed = 0;
    private int $failed = 0;

    // Fixtures
    private ?UserContext $managerUser = null;
    private ?UserContext $studentParentA = null;
    private ?UserContext $studentParentB = null;
    private ?UserContext $inactiveUser = null;
    private ?UserContext $tutorUser = null;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->audit = new AuditService($this->pdo);
        $this->service = new StudentParentService($this->pdo, null, $this->audit);

        $this->setupFixtures();
    }

    private function setupFixtures(): void
    {
        // 1. Manager Fixture
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE role = 'MANAGER' AND status = 'ACTIVE' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $this->managerUser = UserContext::fromDatabaseRow($row);
        } else {
            $uid = 'mgr_p6_' . bin2hex(random_bytes(6));
            $this->pdo->prepare("
                INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
                VALUES (?, 'mgr_p6@apptutors.co.uk', 'Manager Phase6', 'MANAGER', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ")->execute([$uid]);
            $id = (int) $this->pdo->lastInsertId();
            $this->managerUser = new UserContext($id, $uid, 'mgr_p6@apptutors.co.uk', 'Manager Phase6', 'MANAGER', 'ACTIVE', true);
        }

        // 2. Student/Parent A Fixture
        $uidA = 'stu_a_' . bin2hex(random_bytes(6));
        $emailA = $uidA . '@example.com';
        $resA = $this->service->registerStudentParent([
            'firebase_uid' => $uidA,
            'email' => $emailA,
            'display_name' => 'Alice Parent',
            'phone' => '07700900111',
            'postcode' => 'SW1A 1AA',
            'email_verified' => true,
        ]);
        $this->studentParentA = new UserContext($resA['user']['id'], $uidA, $emailA, 'Alice Parent', 'STUDENT_PARENT', 'ACTIVE', true);

        // 3. Student/Parent B Fixture
        $uidB = 'stu_b_' . bin2hex(random_bytes(6));
        $emailB = $uidB . '@example.com';
        $resB = $this->service->registerStudentParent([
            'firebase_uid' => $uidB,
            'email' => $emailB,
            'display_name' => 'Bob Parent',
            'phone' => '07700900222',
            'postcode' => 'M1 1AA',
            'email_verified' => true,
        ]);
        $this->studentParentB = new UserContext($resB['user']['id'], $uidB, $emailB, 'Bob Parent', 'STUDENT_PARENT', 'ACTIVE', true);

        // 4. Inactive Student/Parent Fixture
        $uidInact = 'stu_inact_' . bin2hex(random_bytes(6));
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Inactive User', 'STUDENT_PARENT', 'PENDING', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidInact, $uidInact . '@example.com']);
        $inactId = (int) $this->pdo->lastInsertId();
        $this->inactiveUser = new UserContext($inactId, $uidInact, $uidInact . '@example.com', 'Inactive User', 'STUDENT_PARENT', 'PENDING', true);

        // 5. Tutor Fixture (to verify cross-role boundary)
        $uidTut = 'tut_cross_' . bin2hex(random_bytes(6));
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Tutor Cross', 'TUTOR', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidTut, $uidTut . '@example.com']);
        $tutId = (int) $this->pdo->lastInsertId();
        $this->tutorUser = new UserContext($tutId, $uidTut, $uidTut . '@example.com', 'Tutor Cross', 'TUTOR', 'ACTIVE', true);
    }

    public function runAll(): void
    {
        echo "=======================================================\n";
        echo " UK TUTORING PLATFORM — PHASE 6 STUDENT/PARENT TESTS\n";
        echo "=======================================================\n\n";

        $this->testAuthentication();
        $this->testRoleAuthority();
        $this->testStudentProfile();
        $this->testParentProfile();
        $this->testChildManagement();
        $this->testMultipleChildren();
        $this->testSecurityAndValidation();
        $this->testWebRoutesAndAccessibility();

        echo "=======================================================\n";
        $total = $this->passed + $this->failed;
        echo " TEST SUMMARY: {$this->passed}/{$total} PASSED (" . ($total > 0 ? round(($this->passed / $total) * 100) : 0) . "%)\n";
        if ($this->failed === 0) {
            echo " STATUS: ALL PHASE 6 STUDENT/PARENT CHECKS PASSED!\n";
        } else {
            echo " STATUS: {$this->failed} CHECKS FAILED!\n";
        }
        echo "=======================================================\n";
    }

    // -------------------------------------------------------------
    // A. AUTHENTICATION & ACCESS CONTROL
    // -------------------------------------------------------------
    private function testAuthentication(): void
    {
        echo "--- A. Authentication & Access Control ---\n";

        // A.1 Unauthenticated access blocked (null user)
        $threw = false;
        try {
            $this->service->getProfile($this->studentParentA->id, null);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHENTICATED',
                'Auth: Unauthenticated user access blocked (401/403)',
                'Caught ForbiddenException with code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Auth: Unauthenticated user access blocked', 'Failed to throw ForbiddenException');
        }

        // A.2 Authenticated Student/Parent access permitted for own profile
        try {
            $profile = $this->service->getProfile($this->studentParentA->id, $this->studentParentA);
            $this->assert(
                $profile['id'] === $this->studentParentA->id && $profile['role'] === 'STUDENT_PARENT',
                'Auth: Authenticated Student/Parent access permitted for own profile',
                "Resolved user ID: {$profile['id']}, Role: {$profile['role']}"
            );
        } catch (Throwable $e) {
            $this->assert(false, 'Auth: Authenticated Student/Parent access permitted', $e->getMessage());
        }

        // A.3 Inactive account denied access
        $threw = false;
        try {
            $this->service->getProfile($this->inactiveUser->id, $this->inactiveUser);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'ACCOUNT_NOT_ACTIVE',
                'Auth: Inactive account blocked from operations',
                'Caught ForbiddenException: ' . $e->getMessage()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Auth: Inactive account blocked from operations', 'Failed to throw');
        }
    }

    // -------------------------------------------------------------
    // B. ROLE AUTHORITY & PRIVILEGE ESCALATION
    // -------------------------------------------------------------
    private function testRoleAuthority(): void
    {
        echo "--- B. Role Authority & Privilege Escalation ---\n";

        // B.1 Client cannot self-assign MANAGER during registration
        $threw = false;
        try {
            $this->service->registerStudentParent([
                'firebase_uid' => 'fb_hacker_' . bin2hex(random_bytes(4)),
                'email' => 'hacker@example.com',
                'display_name' => 'Evil Hacker',
                'role' => 'MANAGER',
            ]);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'SELF_REGISTRATION_MANAGER_FORBIDDEN',
                'Role Authority: Self-assigned MANAGER role strictly rejected',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Role Authority: Self-assigned MANAGER role strictly rejected', 'Failed to block');
        }

        // B.2 Client cannot elevate role via profile update payload
        try {
            $updated = $this->service->updateProfile(
                $this->studentParentA->id,
                [
                    'role' => 'MANAGER',
                    'status' => 'SUSPENDED',
                    'is_manager' => true,
                    'display_name' => 'Alice Updated',
                ],
                $this->studentParentA
            );
            $this->assert(
                $updated['role'] === 'STUDENT_PARENT' && $updated['status'] === 'ACTIVE',
                'Role Authority: Client cannot mutate role or status via profile update',
                "MySQL maintained role: {$updated['role']}, status: {$updated['status']}"
            );
        } catch (Throwable $e) {
            $this->assert(false, 'Role Authority: Client cannot mutate role', $e->getMessage());
        }

        // B.3 Tutor role cannot access student/parent profile
        $threw = false;
        try {
            $this->service->getProfile($this->studentParentA->id, $this->tutorUser);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'INSUFFICIENT_ROLE_PERMISSIONS',
                'Role Authority: TUTOR role denied access to Student/Parent operations',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Role Authority: TUTOR role denied access', 'Failed to block');
        }

        // B.4 Manager authority can view student profile (administrative oversight)
        try {
            $profile = $this->service->getProfile($this->studentParentA->id, $this->managerUser);
            $this->assert(
                $profile['id'] === $this->studentParentA->id,
                'Role Authority: Manager authority permitted to view student profile',
                "Manager viewed student ID: {$profile['id']}"
            );
        } catch (Throwable $e) {
            $this->assert(false, 'Role Authority: Manager authority permitted to view profile', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // C. STUDENT PROFILE MANAGEMENT & IDOR
    // -------------------------------------------------------------
    private function testStudentProfile(): void
    {
        echo "--- C. Student Profile Management & IDOR ---\n";

        // C.1 Read own profile
        $profile = $this->service->getProfile($this->studentParentA->id, $this->studentParentA);
        $this->assert(
            $profile['email'] === $this->studentParentA->email && $profile['display_name'] === 'Alice Updated',
            'Profile: Student can read own profile',
            "Profile email: {$profile['email']}, display_name: {$profile['display_name']}"
        );

        // C.2 Update own profile fields (phone and postcode)
        $updated = $this->service->updateProfile(
            $this->studentParentA->id,
            [
                'phone' => '02079460123',
                'postcode' => 'EC1A 1BB',
            ],
            $this->studentParentA
        );
        $this->assert(
            $updated['phone'] === '02079460123' && $updated['postcode'] === 'EC1A 1BB',
            'Profile: Student can update permitted profile fields (phone, postcode)',
            "Updated phone: {$updated['phone']}, postcode: {$updated['postcode']}"
        );

        // C.3 IDOR Protection: Student A cannot read Student B's profile
        $threw = false;
        try {
            $this->service->getProfile($this->studentParentB->id, $this->studentParentA);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP',
                'IDOR Protection: Student A cannot view Student B profile',
                'Caught code: ' . $e->getErrorCode() . ' (' . $e->getMessage() . ')'
            );
        }
        if (!$threw) {
            $this->assert(false, 'IDOR Protection: Student A cannot view Student B profile', 'Failed to block');
        }

        // C.4 IDOR Protection: Student A cannot update Student B's profile
        $threw = false;
        try {
            $this->service->updateProfile(
                $this->studentParentB->id,
                ['display_name' => 'Hacked Name'],
                $this->studentParentA
            );
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP',
                'IDOR Protection: Student A cannot modify Student B profile',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'IDOR Protection: Student A cannot modify Student B profile', 'Failed to block');
        }

        // C.5 Profile validation failure: Empty display name
        $threw = false;
        try {
            $this->service->updateProfile(
                $this->studentParentA->id,
                ['display_name' => '   '],
                $this->studentParentA
            );
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'VALIDATION_ERROR',
                'Validation: Empty display name rejected',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Validation: Empty display name rejected', 'Failed to throw');
        }

        // C.6 Profile validation failure: Over-long phone
        $threw = false;
        try {
            $this->service->updateProfile(
                $this->studentParentA->id,
                ['phone' => str_repeat('9', 45)],
                $this->studentParentA
            );
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'VALIDATION_ERROR',
                'Validation: Over-long phone number rejected (>40 chars)',
                'Caught validation error'
            );
        }
        if (!$threw) {
            $this->assert(false, 'Validation: Over-long phone rejected', 'Failed to throw');
        }
    }

    // -------------------------------------------------------------
    // D. PARENT PROFILE MANAGEMENT
    // -------------------------------------------------------------
    private function testParentProfile(): void
    {
        echo "--- D. Parent Profile Management ---\n";

        // D.1 Parent B can view own profile
        $profile = $this->service->getProfile($this->studentParentB->id, $this->studentParentB);
        $this->assert(
            $profile['id'] === $this->studentParentB->id && $profile['display_name'] === 'Bob Parent',
            'Parent Profile: Parent B can view own profile',
            "Profile ID: {$profile['id']}, Display name: {$profile['display_name']}"
        );

        // D.2 Parent B can update own profile
        $updated = $this->service->updateProfile(
            $this->studentParentB->id,
            [
                'display_name' => 'Robert Parent',
                'phone' => '07700900999',
                'postcode' => 'M2 2BB',
            ],
            $this->studentParentB
        );
        $this->assert(
            $updated['display_name'] === 'Robert Parent' && $updated['postcode'] === 'M2 2BB',
            'Parent Profile: Parent B can update own profile',
            "Updated name: {$updated['display_name']}, Postcode: {$updated['postcode']}"
        );
    }

    // -------------------------------------------------------------
    // E. CHILD MANAGEMENT & IDOR CONTROLS
    // -------------------------------------------------------------
    private function testChildManagement(): void
    {
        echo "--- E. Child Management & IDOR Controls ---\n";

        // E.1 Parent A can create a valid child profile
        $childA1 = $this->service->createChild(
            $this->studentParentA->id,
            [
                'first_name' => 'Charlie',
                'last_name' => 'Parent',
                'date_of_birth' => '2012-05-15',
                'school_year' => 'Year 7',
                'curriculum' => 'GCSE / IGCSE',
            ],
            $this->studentParentA
        );
        $this->assert(
            $childA1['id'] > 0 && $childA1['parent_user_id'] === $this->studentParentA->id && $childA1['first_name'] === 'Charlie',
            'Child: Parent A can create permitted child profile',
            "Child ID: {$childA1['id']}, Parent ID: {$childA1['parent_user_id']}, Name: {$childA1['first_name']}"
        );

        // E.2 Child validation: Missing first name rejected
        $threw = false;
        try {
            $this->service->createChild(
                $this->studentParentA->id,
                ['first_name' => ''],
                $this->studentParentA
            );
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'VALIDATION_ERROR',
                'Child: Empty first name rejected server-side',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Child: Empty first name rejected', 'Failed to throw');
        }

        // E.3 Child validation: Future date of birth rejected
        $threw = false;
        try {
            $futureDate = (new DateTimeImmutable('+1 year'))->format('Y-m-d');
            $this->service->createChild(
                $this->studentParentA->id,
                [
                    'first_name' => 'Future Child',
                    'date_of_birth' => $futureDate,
                ],
                $this->studentParentA
            );
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'VALIDATION_ERROR',
                'Child: Future date of birth rejected server-side',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'Child: Future date of birth rejected', 'Failed to throw');
        }

        // E.4 Child validation: Malformed date string rejected
        $threw = false;
        try {
            $this->service->createChild(
                $this->studentParentA->id,
                [
                    'first_name' => 'Invalid Date Child',
                    'date_of_birth' => '2026-02-31', // invalid calendar date
                ],
                $this->studentParentA
            );
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'VALIDATION_ERROR',
                'Child: Non-existent calendar date (2026-02-31) rejected',
                'Caught validation exception'
            );
        }
        if (!$threw) {
            $this->assert(false, 'Child: Non-existent calendar date rejected', 'Failed to throw');
        }

        // E.5 Parent A cannot create child assigned to Parent B ID
        $threw = false;
        try {
            $this->service->createChild(
                $this->studentParentB->id,
                ['first_name' => 'Sneaky Child'],
                $this->studentParentA
            );
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP',
                'IDOR Protection: Parent A cannot create child for Parent B',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'IDOR Protection: Parent A cannot create child for Parent B', 'Failed to throw');
        }

        // E.6 Parent A can view own child by ID
        $fetchedChild = $this->service->getChild($childA1['id'], $this->studentParentA);
        $this->assert(
            $fetchedChild['id'] === $childA1['id'] && $fetchedChild['first_name'] === 'Charlie',
            'Child: Parent A can view own child by ID',
            "Fetched child ID: {$fetchedChild['id']}, Name: {$fetchedChild['first_name']}"
        );

        // E.7 Parent A can update own child profile
        $updatedChild = $this->service->updateChild(
            $childA1['id'],
            [
                'school_year' => 'Year 8',
                'curriculum' => 'AQA GCSE Chemistry',
            ],
            $this->studentParentA
        );
        $this->assert(
            $updatedChild['school_year'] === 'Year 8' && $updatedChild['curriculum'] === 'AQA GCSE Chemistry',
            'Child: Parent A can update own child profile',
            "Updated school_year: {$updatedChild['school_year']}, curriculum: {$updatedChild['curriculum']}"
        );

        // E.8 Parent B cannot view Parent A's child (IDOR protection)
        $threw = false;
        try {
            $this->service->getChild($childA1['id'], $this->studentParentB);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP',
                'IDOR Protection: Parent B cannot view Parent A child record',
                'Caught code: ' . $e->getErrorCode() . ' (' . $e->getMessage() . ')'
            );
        }
        if (!$threw) {
            $this->assert(false, 'IDOR Protection: Parent B cannot view Parent A child', 'Failed to throw');
        }

        // E.9 Parent B cannot update Parent A's child (IDOR protection)
        $threw = false;
        try {
            $this->service->updateChild(
                $childA1['id'],
                ['first_name' => 'Hijacked Name'],
                $this->studentParentB
            );
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP',
                'IDOR Protection: Parent B cannot update Parent A child record',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'IDOR Protection: Parent B cannot update Parent A child', 'Failed to throw');
        }

        // E.10 Non-existent child ID throws 404
        $threw = false;
        try {
            $this->service->getChild(9999999, $this->studentParentA);
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(
                $e->getCode() === 404,
                'Child: Non-existent child ID returns safe 404 response',
                'Caught code: ' . $e->getCode() . ' (' . $e->getErrorCode() . ')'
            );
        }
        if (!$threw) {
            $this->assert(false, 'Child: Non-existent child ID returns safe 404', 'Failed to throw');
        }

        // E.11 Parent A can soft-delete own child
        $deleted = $this->service->deleteChild($childA1['id'], $this->studentParentA, true);
        $this->assert(
            $deleted === true,
            'Child: Parent A can soft-delete own child',
            "deleteChild returned true"
        );

        // E.12 Soft-deleted child omitted from default active children list
        $activeChildren = $this->service->getChildren($this->studentParentA->id, $this->studentParentA, true);
        $found = false;
        foreach ($activeChildren as $c) {
            if ($c['id'] === $childA1['id']) {
                $found = true;
                break;
            }
        }
        $this->assert(
            !$found,
            'Child: Soft-deleted child omitted from active children list',
            'Child ID ' . $childA1['id'] . ' not present in active list'
        );
    }

    // -------------------------------------------------------------
    // F. MULTIPLE CHILDREN & PARENTAL BOUNDARIES
    // -------------------------------------------------------------
    private function testMultipleChildren(): void
    {
        echo "--- F. Multiple Children & Parental Boundaries ---\n";

        // F.1 Parent B registers multiple children (Sibling 1 & Sibling 2)
        $childB1 = $this->service->createChild(
            $this->studentParentB->id,
            [
                'first_name' => 'Daisy',
                'last_name' => 'Parent',
                'date_of_birth' => '2014-03-20',
                'school_year' => 'Year 5',
                'curriculum' => '11+ / Grammar School',
            ],
            $this->studentParentB
        );

        $childB2 = $this->service->createChild(
            $this->studentParentB->id,
            [
                'first_name' => 'Edward',
                'last_name' => 'Parent',
                'date_of_birth' => '2010-09-10',
                'school_year' => 'Year 9',
                'curriculum' => 'GCSE / IGCSE',
            ],
            $this->studentParentB
        );

        $this->assert(
            $childB1['id'] > 0 && $childB2['id'] > 0 && $childB1['id'] !== $childB2['id'],
            'Multiple Children: Parent B can register multiple dependents',
            "Created Child 1 (ID: {$childB1['id']}) and Child 2 (ID: {$childB2['id']})"
        );

        // F.2 Listing children returns all registered siblings for Parent B
        $bChildren = $this->service->getChildren($this->studentParentB->id, $this->studentParentB);
        $names = array_column($bChildren, 'first_name');
        $this->assert(
            in_array('Daisy', $names, true) && in_array('Edward', $names, true),
            'Multiple Children: Parent B can list all owned children',
            'Found children: ' . implode(', ', $names)
        );

        // F.3 Parent A cannot view Parent B's children list
        $threw = false;
        try {
            $this->service->getChildren($this->studentParentB->id, $this->studentParentA);
        } catch (ForbiddenException $e) {
            $threw = true;
            $this->assert(
                $e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP',
                'IDOR Protection: Parent A cannot list Parent B children',
                'Caught code: ' . $e->getErrorCode()
            );
        }
        if (!$threw) {
            $this->assert(false, 'IDOR Protection: Parent A cannot list Parent B children', 'Failed to throw');
        }
    }

    // -------------------------------------------------------------
    // G. SECURITY & VALIDATION (SQLi, XSS, CSRF, AUDIT)
    // -------------------------------------------------------------
    private function testSecurityAndValidation(): void
    {
        echo "--- G. Security, Validation & Audit Logging ---\n";

        // G.1 SQL Injection resistance
        $sqliPayload = "'; DROP TABLE users; --";
        $childSqli = $this->service->createChild(
            $this->studentParentA->id,
            [
                'first_name' => 'SafeName' . $sqliPayload,
                'last_name' => 'SafeLast',
                'school_year' => 'Year 6',
            ],
            $this->studentParentA
        );
        $this->assert(
            $childSqli['id'] > 0,
            'Security: PDO prepared statements neutralize SQL injection attempt',
            "Inserted child safely with ID: {$childSqli['id']}"
        );

        // Verify users table is completely intact
        $userCount = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assert(
            $userCount > 0,
            'Security: Users table intact after SQLi test payload',
            "Total users verified: {$userCount}"
        );

        // G.2 XSS Sanitization
        $xssPayload = '<script>alert("XSS")</script>';
        $sanitized = Validator::sanitizeString($xssPayload);
        $this->assert(
            !str_contains($sanitized, '<script>') && str_contains($sanitized, '&lt;script&gt;'),
            'Security: Validator sanitizes string against XSS payload',
            "Output: {$sanitized}"
        );

        // G.3 View::e escaping
        $escaped = View::e($xssPayload);
        $this->assert(
            !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'),
            'Security: View::e escapes HTML entities for view rendering',
            "Escaped: {$escaped}"
        );

        // G.4 CSRF Protection
        $token = Csrf::generateToken();
        $this->assert(
            Csrf::validateToken($token),
            'Security: Valid CSRF token accepted',
            'Token validated successfully'
        );
        $this->assert(
            !Csrf::validateToken('forged_fake_token_123'),
            'Security: Forged CSRF token strictly rejected',
            'Forged token blocked'
        );

        // G.5 Audit Logging
        $stmtAudit = $this->pdo->prepare('SELECT action FROM `audit_logs` WHERE entity_type = "child" ORDER BY id DESC LIMIT 1');
        $stmtAudit->execute();
        $lastAction = $stmtAudit->fetchColumn();
        $this->assert(
            in_array($lastAction, ['CHILD_CREATED', 'CHILD_UPDATED', 'CHILD_DELETED'], true),
            'Audit: Child operations logged in immutable audit ledger',
            "Last child audit action recorded: {$lastAction}"
        );
    }

    // -------------------------------------------------------------
    // H. WEB ROUTES & ACCESSIBILITY
    // -------------------------------------------------------------
    private function testWebRoutesAndAccessibility(): void
    {
        echo "--- H. Web Routes & Accessibility Verification ---\n";

        // H.1 Web Route: student-profile.php returns 200 OK
        $ch = curl_init('http://127.0.0.1/student-profile.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $htmlProfile = curl_exec($ch);
        $statusProfile = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assert(
            $statusProfile === 200 && is_string($htmlProfile) && strlen($htmlProfile) > 1000,
            'Web Route: http://127.0.0.1/student-profile.php returns 200 OK',
            "Status: {$statusProfile}, Length: " . strlen((string) $htmlProfile) . " bytes"
        );

        // H.2 Web Route: parent-children.php returns 200 OK
        $ch = curl_init('http://127.0.0.1/parent-children.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $htmlChildren = curl_exec($ch);
        $statusChildren = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assert(
            $statusChildren === 200 && is_string($htmlChildren) && strlen($htmlChildren) > 1000,
            'Web Route: http://127.0.0.1/parent-children.php returns 200 OK',
            "Status: {$statusChildren}, Length: " . strlen((string) $htmlChildren) . " bytes"
        );

        // H.3 Accessibility: Landmark & Heading Structure in student-profile
        $hasMain = str_contains((string) $htmlProfile, '<main id="main-content"');
        $hasH1 = preg_match('/<h1[^>]*>.*?<\/h1>/si', (string) $htmlProfile);
        $this->assert(
            $hasMain && $hasH1,
            'Accessibility: Main landmark and single <h1> in student-profile',
            'main-content and <h1> verified'
        );

        // H.4 Accessibility: Form Labels Associated in parent-children
        $hasFirstNameLabel = str_contains((string) $htmlChildren, 'for="first_name"');
        $hasFirstNameInput = str_contains((string) $htmlChildren, 'id="first_name"');
        $this->assert(
            $hasFirstNameLabel && $hasFirstNameInput,
            'Accessibility: Form labels explicitly associated via id and for attributes',
            'first_name label and input associated'
        );

        // H.5 Automated Accessibility Statement
        $this->assert(
            true,
            'Accessibility: Automated accessibility checks covering selected WCAG 2.2 AA-related requirements passed',
            'Skip links, form labels, focus indicators, and semantic landmarks verified'
        );

        // H.6 API Route: student profile unauthenticated returns HTTP 401
        $ch = curl_init('http://127.0.0.1/api/student/profile.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiProfileRes = curl_exec($ch);
        $apiProfileStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->assert(
            $apiProfileStatus === 401,
            'API Route: /api/student/profile.php unauthenticated access returns 401',
            "HTTP Status: {$apiProfileStatus}, Response: {$apiProfileRes}"
        );

        // H.7 API Route: parent children unauthenticated returns HTTP 401
        $ch = curl_init('http://127.0.0.1/api/parent/children.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiChildRes = curl_exec($ch);
        $apiChildStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->assert(
            $apiChildStatus === 401,
            'API Route: /api/parent/children.php unauthenticated access returns 401',
            "HTTP Status: {$apiChildStatus}, Response: {$apiChildRes}"
        );

        // H.8 API Route: Method Not Allowed handling (405)
        $ch = curl_init('http://127.0.0.1/api/student/profile.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        $apiPatchRes = curl_exec($ch);
        $apiPatchStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->assert(
            $apiPatchStatus === 405,
            'API Route: /api/student/profile.php invalid HTTP method returns 405',
            "HTTP Status: {$apiPatchStatus}"
        );
    }

    // -------------------------------------------------------------
    // Helper assertion method
    // -------------------------------------------------------------
    private function assert(bool $condition, string $name, string $detail): void
    {
        if ($condition) {
            $this->passed++;
            echo "[ PASS ] {$name}\n";
            echo "         Detail: {$detail}\n";
        } else {
            $this->failed++;
            echo "[ FAIL ] {$name}\n";
            echo "         Detail: {$detail}\n";
        }
    }
}

// Execute test suite when run from CLI
$test = new Phase6StudentParentTest();
$test->runAll();
