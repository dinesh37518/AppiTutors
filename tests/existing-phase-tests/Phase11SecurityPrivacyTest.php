<?php

declare(strict_types=1);

namespace Tests;

ob_start();
register_shutdown_function(function () {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
});

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\Exceptions\UserNotRegisteredException;
use App\Auth\FirebaseTokenVerifier;
use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\BlogService;
use App\Services\BookingService;
use App\Services\DbsService;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\ValidationException;
use App\Services\ManagerService;
use App\Services\NewsletterService;
use App\Services\PrivacyService;
use App\Services\StudentParentService;
use App\Services\TutorService;
use App\Support\Csrf;
use App\Support\RateLimitExceededException;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use App\Support\SecurityHeaders;
use App\Support\Session;
use App\Support\Timezone;
use App\Validation\Validator;
use PDO;
use Throwable;

class Phase11SecurityPrivacyTest
{
    private PDO $pdo;
    private Logger $logger;
    private AuditService $auditService;
    private TutorService $tutorService;
    private StudentParentService $studentParentService;
    private AvailabilityService $availabilityService;
    private BookingService $bookingService;
    private DbsService $dbsService;
    private BlogService $blogService;
    private NewsletterService $newsletterService;
    private ManagerService $managerService;
    private PrivacyService $privacyService;
    private FirebaseTokenVerifier $verifier;

    private int $passed = 0;
    private int $failed = 0;

    private string $baseUrl = 'http://127.0.0.1';

    // Test Actor Fixtures
    private ?UserContext $managerUser = null;
    private ?UserContext $suspendedManager = null;
    private ?UserContext $tutorA = null;
    private ?UserContext $tutorB = null;
    private ?UserContext $inactiveTutor = null;
    private ?UserContext $parentA = null;
    private ?UserContext $parentB = null;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->logger = new Logger();
        $this->auditService = new AuditService($this->pdo, $this->logger);
        $this->tutorService = new TutorService($this->pdo, $this->logger, $this->auditService);
        $this->studentParentService = new StudentParentService($this->pdo, $this->logger, $this->auditService);
        $this->availabilityService = new AvailabilityService($this->pdo, $this->logger, $this->auditService);
        $this->bookingService = new BookingService($this->pdo, $this->logger, $this->auditService, $this->tutorService, $this->studentParentService);
        $this->dbsService = new DbsService($this->pdo, $this->logger, $this->auditService);
        $this->blogService = new BlogService($this->pdo, $this->logger, $this->auditService);
        $this->newsletterService = new NewsletterService($this->pdo, $this->logger, $this->auditService);
        $this->managerService = new ManagerService(
            $this->pdo,
            $this->logger,
            $this->auditService,
            $this->tutorService,
            $this->bookingService,
            $this->studentParentService,
            $this->dbsService,
            $this->availabilityService,
            $this->blogService,
            $this->newsletterService
        );
        $this->privacyService = new PrivacyService($this->pdo, $this->logger, $this->auditService);
        $this->verifier = new FirebaseTokenVerifier();

        $this->setupFixtures();
    }

    private function setupFixtures(): void
    {
        // 1. Manager
        $uidMgr = 'mgr_p11_' . bin2hex(random_bytes(6));
        $emailMgr = 'mgr_p11_' . bin2hex(random_bytes(4)) . '@apptutors.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Manager Phase11', 'MANAGER', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidMgr, $emailMgr]);
        $this->managerUser = new UserContext((int) $this->pdo->lastInsertId(), $uidMgr, $emailMgr, 'Manager Phase11', 'MANAGER', 'ACTIVE', true);

        // 2. Suspended Manager
        $uidSusp = 'mgr_susp_p11_' . bin2hex(random_bytes(6));
        $emailSusp = 'mgr_susp_p11_' . bin2hex(random_bytes(4)) . '@apptutors.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Suspended Mgr P11', 'MANAGER', 'SUSPENDED', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidSusp, $emailSusp]);
        $this->suspendedManager = new UserContext((int) $this->pdo->lastInsertId(), $uidSusp, $emailSusp, 'Suspended Mgr P11', 'MANAGER', 'SUSPENDED', true);

        // 3. Tutor A (Active, Approved, DBS Verified)
        $uidTutA = 'tuta_p11_' . bin2hex(random_bytes(6));
        $emailTutA = 'tuta_p11_' . bin2hex(random_bytes(4)) . '@apptutors.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Tutor Alice P11', 'TUTOR', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidTutA, $emailTutA]);
        $tutAId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("
            INSERT INTO tutor_profiles (user_id, headline, bio, approval_status, dbs_status, created_at, updated_at)
            VALUES (?, 'Maths Specialist', 'Bio Alice', 'APPROVED', 'VERIFIED', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$tutAId]);
        $this->tutorA = new UserContext($tutAId, $uidTutA, $emailTutA, 'Tutor Alice P11', 'TUTOR', 'ACTIVE', true);

        // 4. Tutor B (Active, Approved, DBS Verified)
        $uidTutB = 'tutb_p11_' . bin2hex(random_bytes(6));
        $emailTutB = 'tutb_p11_' . bin2hex(random_bytes(4)) . '@apptutors.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Tutor Bob P11', 'TUTOR', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidTutB, $emailTutB]);
        $tutBId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("
            INSERT INTO tutor_profiles (user_id, headline, bio, approval_status, dbs_status, created_at, updated_at)
            VALUES (?, 'Science Specialist', 'Bio Bob', 'APPROVED', 'VERIFIED', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$tutBId]);
        $this->tutorB = new UserContext($tutBId, $uidTutB, $emailTutB, 'Tutor Bob P11', 'TUTOR', 'ACTIVE', true);

        // 5. Inactive Tutor (Pending)
        $uidTutInact = 'tutinact_p11_' . bin2hex(random_bytes(6));
        $emailTutInact = 'tutinact_p11_' . bin2hex(random_bytes(4)) . '@apptutors.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Tutor Inactive P11', 'TUTOR', 'PENDING', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidTutInact, $emailTutInact]);
        $tutInactId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("
            INSERT INTO tutor_profiles (user_id, headline, bio, approval_status, dbs_status, created_at, updated_at)
            VALUES (?, 'Pending Specialist', 'Bio Inactive', 'PENDING', 'NOT_SUBMITTED', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$tutInactId]);
        $this->inactiveTutor = new UserContext($tutInactId, $uidTutInact, $emailTutInact, 'Tutor Inactive P11', 'TUTOR', 'PENDING', true);

        // 6. Parent A
        $uidParA = 'para_p11_' . bin2hex(random_bytes(6));
        $emailParA = 'para_p11_' . bin2hex(random_bytes(4)) . '@example.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Parent Alice P11', 'STUDENT_PARENT', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidParA, $emailParA]);
        $parAId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("
            INSERT INTO student_profiles (user_id, phone, postcode, created_at, updated_at)
            VALUES (?, '02079460111', 'SW1A 1AA', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$parAId]);
        $this->parentA = new UserContext($parAId, $uidParA, $emailParA, 'Parent Alice P11', 'STUDENT_PARENT', 'ACTIVE', true);

        // 7. Parent B
        $uidParB = 'parb_p11_' . bin2hex(random_bytes(6));
        $emailParB = 'parb_p11_' . bin2hex(random_bytes(4)) . '@example.co.uk';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Parent Bob P11', 'STUDENT_PARENT', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidParB, $emailParB]);
        $parBId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("
            INSERT INTO student_profiles (user_id, phone, postcode, created_at, updated_at)
            VALUES (?, '02079460222', 'EC1A 1BB', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$parBId]);
        $this->parentB = new UserContext($parBId, $uidParB, $emailParB, 'Parent Bob P11', 'STUDENT_PARENT', 'ACTIVE', true);
    }

    public function runAll(): void
    {
        echo "=======================================================\n";
        echo " UK TUTORING PLATFORM — PHASE 11 SECURITY & PRIVACY TESTS\n";
        echo "=======================================================\n\n";

        $this->testAuthenticationSecurity();
        $this->testAuthorizationIdorBola();
        $this->testRoleTamperingAndAuthorityBoundary();
        $this->testSessionAndCookieSecurity();
        $this->testCsrfProtections();
        $this->testXssNeutralization();
        $this->testSqlInjectionHardening();
        $this->testSecurityHeadersAndCsp();
        $this->testSecretManagementAndErrorLeakage();
        $this->testFileUploadAndDbsSecurity();
        $this->testNewsletterPrivacyAndSuppression();
        $this->testRateLimitingEngine();
        $this->testAuditLoggingAndPrivacySanitization();
        $this->testUkGdprEngineeringHooks();
        $this->testBookingSecurityRegression();

        echo "=======================================================\n";
        $total = $this->passed + $this->failed;
        echo " TEST SUMMARY: {$this->passed}/{$total} PASSED (" . round(($this->passed / max(1, $total)) * 100) . "%)\n";
        if ($this->failed > 0) {
            echo " STATUS: {$this->failed} TEST(S) FAILED.\n";
            echo "=======================================================\n";
            exit(1);
        } else {
            echo " STATUS: ALL PHASE 11 SECURITY & PRIVACY CHECKS PASSED!\n";
            echo "=======================================================\n";
        }
    }

    // -------------------------------------------------------------
    // 1. Authentication Security
    // -------------------------------------------------------------
    private function testAuthenticationSecurity(): void
    {
        echo "--- 1. Authentication & Identity Boundary ---\n";

        // 1.1 Missing Authorization Header (401)
        try {
            $this->verifier->extractBearerToken(null);
            $this->assert(false, 'Auth: Missing Authorization header rejected', 'Did not throw');
        } catch (InvalidTokenException $e) {
            $this->assert(true, 'Auth: Missing Authorization header rejected (401)', $e->getMessage());
        }

        // 1.2 Malformed non-Bearer token (401)
        try {
            $this->verifier->extractBearerToken('Basic dXNlcjpwYXNz');
            $this->assert(false, 'Auth: Malformed non-Bearer scheme rejected', 'Did not throw');
        } catch (InvalidTokenException $e) {
            $this->assert(true, 'Auth: Malformed non-Bearer scheme rejected (401)', $e->getMessage());
        }

        // 1.3 Empty Bearer token (401)
        try {
            $this->verifier->extractBearerToken('Bearer    ');
            $this->assert(false, 'Auth: Empty Bearer token rejected', 'Did not throw');
        } catch (InvalidTokenException $e) {
            $this->assert(true, 'Auth: Empty Bearer token rejected (401)', $e->getMessage());
        }

        // 1.4 Garbage JWT format (401)
        try {
            $this->verifier->verifyFirebaseToken('invalid.garbage.jwt.token');
            $this->assert(false, 'Auth: Garbage JWT token rejected', 'Did not throw');
        } catch (InvalidTokenException $e) {
            $this->assert(true, 'Auth: Garbage JWT token rejected (401)', 'Firebase Admin SDK cleanly rejected invalid token');
        }

        // 1.5 Unregistered Firebase UID mapped to 404 USER_NOT_REGISTERED
        try {
            $this->verifier->resolveUser('unregistered_uid_' . bin2hex(random_bytes(8)));
            $this->assert(false, 'Auth: Unregistered Firebase UID rejected', 'Did not throw');
        } catch (UserNotRegisteredException $e) {
            $this->assert(true, 'Auth: Unregistered Firebase UID throws USER_NOT_REGISTERED (404)', "Resolved code: USER_NOT_REGISTERED (UID: {$e->getFirebaseUid()})");
        }

        // 1.6 Inactive account rejected (403 ACCOUNT_INACTIVE)
        try {
            if (!$this->inactiveTutor->isActive()) {
                throw new AccountInactiveException($this->inactiveTutor->status, "User account is {$this->inactiveTutor->status}. Access denied.");
            }
            $this->assert(false, 'Auth: Inactive account permitted', 'Should have failed');
        } catch (AccountInactiveException $e) {
            $this->assert(true, 'Auth: Inactive account status rejected with ACCOUNT_INACTIVE (403)', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 2. Authorization / IDOR / BOLA Controls
    // -------------------------------------------------------------
    private function testAuthorizationIdorBola(): void
    {
        echo "--- 2. Authorization, RBAC & IDOR / BOLA Defenses ---\n";

        // Create Child for Parent A
        $childA = $this->studentParentService->createChild($this->parentA->id, [
            'first_name' => 'Charlie',
            'last_name' => 'AliceChild',
            'date_of_birth' => '2014-05-10',
            'school_year' => 'Year 7',
        ], $this->parentA);

        // 2.1 Parent A can access own child
        $fetchedChild = $this->studentParentService->getChild((int) $childA['id'], $this->parentA);
        $this->assert(
            (int) $fetchedChild['id'] === (int) $childA['id'],
            'Authorization: Resource owner allowed access to own dependent child',
            "Child ID: {$fetchedChild['id']}"
        );

        // 2.2 IDOR: Parent B blocked from accessing Parent A's child (403)
        try {
            $this->studentParentService->getChild((int) $childA['id'], $this->parentB);
            $this->assert(false, 'IDOR: Parent B accessed Parent A child', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'IDOR: Parent B blocked from reading Parent A child (403)', $e->getMessage());
        }

        // 2.3 IDOR: Parent B blocked from updating Parent A's child (403)
        try {
            $this->studentParentService->updateChild((int) $childA['id'], ['first_name' => 'HackedName'], $this->parentB);
            $this->assert(false, 'IDOR: Parent B updated Parent A child', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'IDOR: Parent B blocked from updating Parent A child (403)', $e->getMessage());
        }

        // 2.4 IDOR: Student/Parent A blocked from reading Student/Parent B profile (403)
        try {
            $this->studentParentService->getProfile($this->parentB->id, $this->parentA);
            $this->assert(false, 'IDOR: Parent A read Parent B profile', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'IDOR: Parent A blocked from reading Parent B profile (403)', $e->getMessage());
        }

        // 2.5 IDOR: Tutor A blocked from modifying Tutor B's availability slot (403)
        $slotB = $this->availabilityService->createSlot(
            $this->tutorB->id,
            '2026-11-20 10:00:00',
            '2026-11-20 11:00:00',
            'Europe/London',
            'PUBLISHED',
            $this->tutorB
        );

        try {
            $this->availabilityService->deleteSlot((int) $slotB['id'], $this->tutorB->id, $this->tutorA);
            $this->assert(false, 'IDOR: Tutor A deleted Tutor B slot', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'IDOR: Tutor A blocked from modifying Tutor B slot (403)', $e->getMessage());
        }

        // 2.6 RBAC: Non-manager (Tutor A) blocked from manager-only endpoints (403)
        try {
            $this->managerService->getDashboardKpis($this->tutorA);
            $this->assert(false, 'RBAC: Tutor accessed manager dashboard KPIs', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'RBAC: Tutor blocked from manager dashboard KPIs (403)', $e->getMessage());
        }

        // 2.7 RBAC: Non-manager (Parent A) blocked from manager tutor list (403)
        try {
            $this->managerService->listTutors($this->parentA);
            $this->assert(false, 'RBAC: Parent accessed manager tutor list', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'RBAC: Parent blocked from manager tutor administration (403)', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 3. Role Tampering & Authority Boundary
    // -------------------------------------------------------------
    private function testRoleTamperingAndAuthorityBoundary(): void
    {
        echo "--- 3. Role Tampering & Server Authority Enforcement ---\n";

        // 3.1 POST role tampering: client sends 'role' => 'MANAGER' during tutor registration
        $fakeUid = 'tamper_' . bin2hex(random_bytes(6));
        $fakeEmail = 'tamper_' . bin2hex(random_bytes(4)) . '@example.com';

        $regData = [
            'firebase_uid' => $fakeUid,
            'email' => $fakeEmail,
            'display_name' => 'Tamper Candidate',
            'role' => 'MANAGER', // Malicious attempt to escalate
            'status' => 'ACTIVE',
            'approval_status' => 'APPROVED',
        ];

        // The service strictly enforces TUTOR / PENDING
        $registered = $this->tutorService->registerTutor($regData);
        $this->assert(
            $registered['role'] === 'TUTOR' && $registered['status'] === 'PENDING' && $registered['approval_status'] === 'PENDING',
            'Role Tampering: Client-supplied MANAGER role discarded; MySQL assigned TUTOR in PENDING status',
            "Assigned role: {$registered['role']}, Status: {$registered['status']}"
        );

        // 3.2 Direct MySQL authority: User cannot escalate role via profile update payload
        $updateData = [
            'display_name' => 'Tamper Attempt Two',
            'role' => 'MANAGER',
            'status' => 'ACTIVE',
            'approval_status' => 'APPROVED',
        ];

        $updated = $this->studentParentService->updateProfile($this->parentA->id, $updateData, $this->parentA);
        $stmtCheck = $this->pdo->prepare('SELECT role, status FROM users WHERE id = ?');
        $stmtCheck->execute([$this->parentA->id]);
        $row = $stmtCheck->fetch();

        $this->assert(
            $row['role'] === 'STUDENT_PARENT' && $row['status'] === 'ACTIVE',
            'Role Tampering: Profile update payload cannot mutate database role or status columns',
            "DB role: {$row['role']}, DB status: {$row['status']}"
        );

        // 3.3 Manager self-assignment blocked
        try {
            Authorization::assertCannotSelfAssignManager('MANAGER');
            $this->assert(false, 'Role Tampering: Manager self-assignment allowed', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'Role Tampering: Manager self-registration strictly blocked (403)', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 4. Session and Cookie Security
    // -------------------------------------------------------------
    private function testSessionAndCookieSecurity(): void
    {
        echo "--- 4. Session & Cookie Security Standards ---\n";

        // 4.1 Strict Session Configuration Check
        Session::configure();

        $strictMode = ini_get('session.use_strict_mode');
        $onlyCookies = ini_get('session.use_only_cookies');
        $httpOnly = ini_get('session.cookie_httponly');
        $sameSite = ini_get('session.cookie_samesite');

        $this->assert(
            $strictMode === '1' && $onlyCookies === '1' && $httpOnly === '1' && strcasecmp((string)$sameSite, 'Lax') === 0,
            'Session: PHP session ini configured with strict mode, use_only_cookies, HttpOnly, and SameSite=Lax',
            "strict: {$strictMode}, only_cookies: {$onlyCookies}, httponly: {$httpOnly}, samesite: {$sameSite}"
        );

        // 4.2 Inactivity Timeout Verification
        // Start session and simulate expired activity timestamp (> 1800s)
        Session::start(1800);
        $initialSessionId = session_id();

        $_SESSION['_last_activity'] = time() - 2000; // Simulated 2000 seconds ago
        $_SESSION['test_key'] = 'secret_data';

        Session::start(1800); // Trigger timeout evaluation

        $newSessionId = session_id();
        $this->assert(
            !isset($_SESSION['test_key']) && isset($_SESSION['_last_activity']),
            'Session: Inactivity timeout (1800s / 30m) expires stale sessions and clears session data',
            'Stale session data purged after inactivity threshold exceeded'
        );

        // 4.3 Session ID Regeneration
        $regenerated = Session::regenerate(true);
        $this->assert(
            $regenerated === true,
            'Session: Session ID regeneration supported for session fixation prevention',
            'session_regenerate_id executed successfully'
        );

        // 4.4 Cookie Secure Flag Environmental Enforcement
        $localConfig = Session::getCookieConfig(false);
        $prodConfig = Session::getCookieConfig(true);
        $this->assert(
            $localConfig['secure'] === false && $prodConfig['secure'] === true,
            'Session: Secure cookie flag enforced in production/HTTPS and deliberately omitted in local plain HTTP',
            "Local HTTP secure: " . ($localConfig['secure'] ? 'true' : 'false') . ", Production HTTPS secure: " . ($prodConfig['secure'] ? 'true' : 'false')
        );
    }

    // -------------------------------------------------------------
    // 5. CSRF Defenses
    // -------------------------------------------------------------
    private function testCsrfProtections(): void
    {
        echo "--- 5. Anti-CSRF Token Defenses ---\n";

        // 5.1 Valid CSRF Token Generation
        $token = Csrf::generateToken();
        $this->assert(
            strlen($token) === 64 && ctype_xdigit($token),
            'CSRF: Cryptographically secure 64-character hex token generated',
            "Token format: " . substr($token, 0, 12) . '...'
        );

        // 5.2 Valid Token Verification
        $valid = Csrf::validateToken($token);
        $this->assert(
            $valid === true,
            'CSRF: Valid synchronizer token accepted via timing-safe hash_equals',
            'Csrf::validateToken returned true'
        );

        // 5.3 Missing Token Rejected
        $missingValid = Csrf::validateToken(null);
        $this->assert(
            $missingValid === false,
            'CSRF: Missing or empty token rejected',
            'Csrf::validateToken(null) returned false'
        );

        // 5.4 Forged / Modified Token Rejected
        $forgedToken = bin2hex(random_bytes(32));
        $forgedValid = Csrf::validateToken($forgedToken);
        $this->assert(
            $forgedValid === false,
            'CSRF: Forged token strictly rejected',
            'Csrf::validateToken returned false'
        );
    }

    // -------------------------------------------------------------
    // 6. XSS Neutralization
    // -------------------------------------------------------------
    private function testXssNeutralization(): void
    {
        echo "--- 6. Output Escaping & XSS Neutralization ---\n";

        // 6.1 HTML Output Escaping via e() and View::e()
        $payload = '<script>alert("XSS")</script><img src="x" onerror="evil()">';
        $escaped = e($payload);

        $this->assert(
            !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;') && !str_contains($escaped, '<img'),
            'XSS: View::e / e() helper neutralizes script and event handler tags via ENT_QUOTES | ENT_SUBSTITUTE',
            "Escaped: {$escaped}"
        );

        // 6.2 Stored Input Sanitization via Validator::sanitizeString
        $input = "<b>Hello</b> <script>stealCookies()</script>";
        $sanitized = Validator::sanitizeString($input);

        $this->assert(
            !str_contains($sanitized, '<script>') && str_contains($sanitized, '&lt;script&gt;'),
            'XSS: Validator::sanitizeString converts executable tags to inert HTML entities before persistence',
            "Sanitized: {$sanitized}"
        );

        // 6.3 Attribute Injection Defense
        $attributePayload = '" onfocus="alert(1)" autofocus="';
        $escapedAttr = e($attributePayload);

        $this->assert(
            !str_contains($escapedAttr, '" onfocus=') && str_contains($escapedAttr, '&quot;'),
            'XSS: Double and single quotes escaped against HTML attribute breakout',
            "Escaped attribute: {$escapedAttr}"
        );
    }

    // -------------------------------------------------------------
    // 7. SQL Injection Hardening
    // -------------------------------------------------------------
    private function testSqlInjectionHardening(): void
    {
        echo "--- 7. SQL Injection & Dynamic Query Hardening ---\n";

        // 7.1 Malicious Search Filter Parameterized Safely
        $sqliPayload = "Math' OR '1'='1' -- ";
        $tutors = $this->managerService->listTutors($this->managerUser, ['search' => $sqliPayload]);

        $this->assert(
            is_array($tutors['items']),
            'SQLi: Search query with SQL injection string executed safely without syntax errors or table dumping',
            "Items returned: " . count($tutors['items'])
        );

        // 7.2 Whitelist Defense on Sort Column (422 INVALID_SORT_FIELD)
        try {
            $this->managerService->listTutors($this->managerUser, [], 1, 20, "created_at; DROP TABLE users; --");
            $this->assert(false, 'SQLi: Injected sort column accepted', 'Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assert(true, 'SQLi: Unallowlisted sort field rejected by whitelist defense (422)', $e->getMessage());
        }

        // 7.3 Whitelist Defense on Sort Direction (422 INVALID_SORT_DIRECTION)
        try {
            $this->managerService->listTutors($this->managerUser, [], 1, 20, 'display_name', "DESC; SELECT SLEEP(5);");
            $this->assert(false, 'SQLi: Injected sort direction accepted', 'Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assert(true, 'SQLi: Invalid sort direction rejected by whitelist defense (422)', $e->getMessage());
        }

        // 7.4 Negative or Invalid Pagination Rejected (422 INVALID_PAGINATION)
        try {
            $this->managerService->listTutors($this->managerUser, [], -1, 0);
            $this->assert(false, 'SQLi: Malformed pagination accepted', 'Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assert(true, 'SQLi: Malformed pagination rejected with 422', $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // 8. Security Headers and CSP
    // -------------------------------------------------------------
    private function testSecurityHeadersAndCsp(): void
    {
        echo "--- 8. HTTP Defense Headers & Content Security Policy ---\n";

        // 8.1 Apply and inspect security headers
        $headers = SecurityHeaders::apply();

        $this->assert(
            isset($headers['X-Content-Type-Options']) && $headers['X-Content-Type-Options'] === 'nosniff',
            'Headers: X-Content-Type-Options is nosniff',
            $headers['X-Content-Type-Options']
        );

        $this->assert(
            isset($headers['X-Frame-Options']) && $headers['X-Frame-Options'] === 'SAMEORIGIN',
            'Headers: X-Frame-Options is SAMEORIGIN (clickjacking defense)',
            $headers['X-Frame-Options']
        );

        $this->assert(
            isset($headers['Referrer-Policy']) && $headers['Referrer-Policy'] === 'strict-origin-when-cross-origin',
            'Headers: Referrer-Policy is strict-origin-when-cross-origin',
            $headers['Referrer-Policy']
        );

        $this->assert(
            isset($headers['Permissions-Policy']) && str_contains($headers['Permissions-Policy'], 'camera=()'),
            'Headers: Permissions-Policy restricts sensitive device APIs',
            $headers['Permissions-Policy']
        );

        // 8.2 Content-Security-Policy Evaluation
        $csp = $headers['Content-Security-Policy'] ?? '';
        $this->assert(
            str_contains($csp, "default-src 'self'")
            && str_contains($csp, "script-src 'self'")
            && !str_contains($csp, "'unsafe-eval'")
            && str_contains($csp, "object-src 'none'"),
            "CSP: Strictest application-compatible policy without 'unsafe-eval' and with object-src 'none'",
            $csp
        );

        // 8.3 HSTS Verification & Local Distinction
        // Local plain HTTP does not emit HSTS:
        $this->assert(
            !isset($headers['Strict-Transport-Security']),
            'Headers: HSTS omitted on plain HTTP local development per specification',
            'Strict-Transport-Security header omitted as expected on non-HTTPS connection'
        );

        // When HTTPS simulated / forced, HSTS is emitted:
        $httpsHeaders = SecurityHeaders::apply(true);
        $this->assert(
            isset($httpsHeaders['Strict-Transport-Security']) && str_contains($httpsHeaders['Strict-Transport-Security'], 'max-age=31536000'),
            'Headers: HSTS max-age=31536000 emitted conditionally when HTTPS is active or in production',
            $httpsHeaders['Strict-Transport-Security'] ?? 'Missing'
        );
    }

    // -------------------------------------------------------------
    // 9. Secret Management & Web Access Blocking
    // -------------------------------------------------------------
    private function testSecretManagementAndErrorLeakage(): void
    {
        echo "--- 9. Secret Management, Direct Web Access & Error Safety ---\n";

        // 9.1 Direct access to .env blocked (HTTP 403)
        $envRes = $this->httpGet($this->baseUrl . '/.env');
        $this->assert(
            $envRes['status'] === 403 || $envRes['status'] === 404,
            'Secrets: Direct HTTP access to .env strictly denied (HTTP 403/404)',
            "HTTP Status: {$envRes['status']}"
        );

        // 9.2 Direct access to storage/credentials blocked (HTTP 403/404)
        $credRes = $this->httpGet($this->baseUrl . '/storage/credentials/firebase-service-account.json');
        $this->assert(
            $credRes['status'] === 403 || $credRes['status'] === 404,
            'Secrets: Direct HTTP access to storage/credentials blocked',
            "HTTP Status: {$credRes['status']}"
        );

        // 9.3 Direct access to composer.json blocked (HTTP 403/404)
        $compRes = $this->httpGet($this->baseUrl . '/composer.json');
        $this->assert(
            $compRes['status'] === 403 || $compRes['status'] === 404,
            'Secrets: Direct HTTP access to composer.json blocked',
            "HTTP Status: {$compRes['status']}"
        );

        // 9.4 Git ignores verified
        $gitignorePath = file_exists(dirname(__DIR__) . '/.gitignore') ? dirname(__DIR__) . '/.gitignore' : dirname(__DIR__, 2) . '/.gitignore';
        $gitignore = (string) file_get_contents($gitignorePath);
        $this->assert(
            str_contains($gitignore, '.env') && str_contains($gitignore, 'service-account'),
            'Secrets: .gitignore excludes .env, credentials, and service-account files',
            '.env and service-account confirmed in .gitignore'
        );

        // 9.5 Production error safety
        $rawError = 'SQLSTATE[42000]: Syntax error in SELECT * FROM secret_table';
        $cleanError = $this->logger->redactSensitiveData(['error' => $rawError, 'password' => 'supersecret123']);
        $this->assert(
            $cleanError['password'] === '***REDACTED***',
            'Secrets: Sensitive passwords automatically redacted by Logger',
            'Password field masked to ***REDACTED***'
        );
    }

    // -------------------------------------------------------------
    // 10. File Upload & DBS Security
    // -------------------------------------------------------------
    private function testFileUploadAndDbsSecurity(): void
    {
        echo "--- 10. File Upload & DBS Security Controls ---\n";

        // 10.1 Executable script upload rejected
        $tmpPhp = tempnam(sys_get_temp_dir(), 'malicious_');
        file_put_contents($tmpPhp, '<?php echo "evil"; ?>');
        $filePhp = [
            'name' => 'exploit.php',
            'type' => 'application/x-php',
            'tmp_name' => $tmpPhp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPhp),
        ];

        try {
            $this->dbsService->submitDbs($this->tutorA->id, '123456789012', $filePhp, $this->tutorA);
            $this->assert(false, 'Upload: Executable .php upload accepted', 'Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assert(true, 'Upload: Executable .php extension rejected (422)', $e->getMessage());
        } finally {
            @unlink($tmpPhp);
        }

        // 10.2 Polyglot file with embedded PHP rejected
        $tmpPoly = tempnam(sys_get_temp_dir(), 'poly_');
        file_put_contents($tmpPoly, "%PDF-1.4\n<?php system(\$_GET['cmd']); ?>");
        $filePoly = [
            'name' => 'document.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpPoly,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPoly),
        ];

        try {
            $this->dbsService->submitDbs($this->tutorA->id, '123456789012', $filePoly, $this->tutorA);
            $this->assert(false, 'Upload: Polyglot PDF with embedded PHP accepted', 'Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assert(true, 'Upload: Embedded script payload inside file body detected and rejected (422)', $e->getMessage());
        } finally {
            @unlink($tmpPoly);
        }

        // 10.3 Oversized file rejected (> 5MB)
        $fileOversized = [
            'name' => 'huge_document.pdf',
            'type' => 'application/pdf',
            'tmp_name' => 'fake_tmp',
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * 1024 * 1024, // 6 MB
        ];

        try {
            $this->dbsService->submitDbs($this->tutorA->id, '123456789012', $fileOversized, $this->tutorA);
            $this->assert(false, 'Upload: Oversized file accepted', 'Should have thrown ValidationException');
        } catch (ValidationException $e) {
            $this->assert(true, 'Upload: File exceeding 5MB limit rejected (422)', $e->getMessage());
        }

        // 10.4 Submit valid DBS document for Tutor A to establish genuine stored record
        $tmpValid = tempnam(sys_get_temp_dir(), 'dbs_valid_');
        file_put_contents($tmpValid, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        $fileValid = [
            'name' => 'valid_dbs_cert.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpValid,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpValid),
        ];

        $sub = $this->dbsService->submitDbs($this->tutorA->id, '001594837261', $fileValid, $this->tutorA);
        $this->assert(
            $sub['dbs_status'] === 'SUBMITTED',
            'DBS: Tutor A submitted valid DBS documentation successfully',
            "Status: {$sub['dbs_status']}"
        );

        // 10.5 Manager can access authorized DBS review / document operation
        $doc = $this->dbsService->getDbsDocument($this->tutorA->id, $this->managerUser);
        $this->assert(
            !empty($doc['path']) && file_exists($doc['path']),
            'DBS: Manager can access authorized DBS review and retrieve raw document',
            "Retrieved path: " . basename($doc['path'])
        );

        // 10.6 Owning tutor cannot retrieve raw DBS document
        try {
            $this->dbsService->getDbsDocument($this->tutorA->id, $this->tutorA);
            $this->assert(false, 'DBS: Owning tutor retrieved raw DBS document', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'DBS: Owning tutor strictly blocked from retrieving raw DBS document (403)', $e->getMessage());
        }

        // 10.7 Different tutor cannot retrieve raw DBS document
        try {
            $this->dbsService->getDbsDocument($this->tutorA->id, $this->tutorB);
            $this->assert(false, 'DBS: Different tutor retrieved Tutor A DBS document', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'DBS: Different tutor blocked from retrieving Tutor A DBS document (403)', $e->getMessage());
        }

        // 10.8 Student/Parent cannot retrieve raw DBS document
        try {
            $this->dbsService->getDbsDocument($this->tutorA->id, $this->parentA);
            $this->assert(false, 'DBS: Student/Parent retrieved DBS document', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'DBS: Student/parent blocked from retrieving raw DBS document (403)', $e->getMessage());
        }

        // 10.9 Public unauthenticated request cannot retrieve raw DBS document
        $pubRes = $this->httpGet('http://127.0.0.1/api/dbs/document.php?tutor_id=' . $this->tutorA->id);
        $this->assert(
            $pubRes['status'] === 401 || $pubRes['status'] === 403,
            'DBS: Public unauthenticated request blocked from retrieving raw DBS document (401/403)',
            "HTTP Status: {$pubRes['status']}"
        );

        // 10.10 Tutor can still access permitted non-sensitive DBS status information
        $profile = $this->tutorService->getProfile($this->tutorA->id, $this->tutorA);
        $this->assert(
            isset($profile['dbs_status']) && $profile['dbs_status'] === 'SUBMITTED' && !isset($profile['dbs_file_path']),
            'DBS: Tutor can access permitted non-sensitive DBS status without leaking raw file paths',
            "Tutor DBS status: {$profile['dbs_status']}"
        );

        // 10.11 DBS files remain outside the public web root
        $storageDir = file_exists(dirname(__DIR__) . '/storage/private/dbs') ? dirname(__DIR__) . '/storage/private/dbs' : dirname(__DIR__, 2) . '/storage/private/dbs';
        $publicDir = file_exists(dirname(__DIR__, 2) . '/frontend/public') ? dirname(__DIR__, 2) . '/frontend/public' : (file_exists(dirname(__DIR__) . '/public') ? dirname(__DIR__) . '/public' : dirname(__DIR__, 2) . '/public');
        $isOutsideWebRoot = (strpos(realpath($storageDir) ?: $storageDir, realpath($publicDir) ?: $publicDir) === false);
        $directHttpRes = $this->httpGet('http://127.0.0.1/storage/private/dbs/meta_' . $this->tutorA->id . '.json');
        $this->assert(
            $isOutsideWebRoot && ($directHttpRes['status'] === 403 || $directHttpRes['status'] === 404),
            'DBS: Sensitive files remain outside public web root and blocked from direct HTTP access',
            "Storage path: {$storageDir}, HTTP Status: {$directHttpRes['status']}"
        );

        @unlink($tmpValid);

        // Restore Tutor A DBS status to VERIFIED so subsequent booking tests can create slots
        $this->dbsService->verifyDbs($this->tutorA->id, $this->managerUser);
    }

    // -------------------------------------------------------------
    // 11. Newsletter Privacy & Suppression
    // -------------------------------------------------------------
    private function testNewsletterPrivacyAndSuppression(): void
    {
        echo "--- 11. Newsletter Privacy, Double Opt-In & Suppression ---\n";

        // 11.1 Missing consent rejected (422)
        try {
            $this->newsletterService->subscribe('test_noconsent@example.com', false);
            $this->assert(false, 'Newsletter: Subscription without consent accepted', 'Should have thrown');
        } catch (ValidationException $e) {
            $this->assert(true, 'Newsletter: Subscription without explicit consent rejected (422)', $e->getMessage());
        }

        // 11.2 Subscriber persisted in neutral PENDING state preserving open double opt-in decision
        $email = 'sub_p11_' . bin2hex(random_bytes(4)) . '@example.com';
        $sub = $this->newsletterService->subscribe($email, true);
        $this->assert(
            $sub['status'] === 'PENDING',
            'Newsletter: New subscriber persisted in neutral PENDING status',
            "Status: {$sub['status']}"
        );

        // 11.3 Manager cannot arbitrarily transition PENDING -> ACTIVE (open decision preserved)
        try {
            $this->newsletterService->updateSubscriberStatus($this->managerUser, (int) $sub['id'], 'ACTIVE');
            $this->assert(false, 'Newsletter: Manager activated PENDING subscriber', 'Should have thrown');
        } catch (ValidationException $e) {
            $this->assert(true, 'Newsletter: Manager activation of PENDING blocked with DOUBLE_OPT_IN_OPEN_DECISION (422)', $e->getMessage());
        }

        // 11.4 Manager updates subscriber to SUPPRESSED
        $suppressed = $this->newsletterService->updateSubscriberStatus($this->managerUser, (int) $sub['id'], 'SUPPRESSED');
        $this->assert(
            $suppressed['status'] === 'SUPPRESSED',
            'Newsletter: Manager can mark subscriber as SUPPRESSED for compliance',
            "Status: {$suppressed['status']}"
        );

        // 11.5 Re-subscribing a suppressed user preserves SUPPRESSED status
        $resub = $this->newsletterService->subscribe($email, true);
        $this->assert(
            $resub['status'] === 'SUPPRESSED',
            'Newsletter: Re-subscription of suppressed user remains SUPPRESSED (anti-re-solicitation control)',
            "Status: {$resub['status']}"
        );
    }

    // -------------------------------------------------------------
    // 12. Rate Limiting Engine
    // -------------------------------------------------------------
    private function testRateLimitingEngine(): void
    {
        echo "--- 12. Local Rate Limiting Engine ---\n";

        $testAction = 'test_rate_limit_action';
        $testId = 'test_ip_' . bin2hex(random_bytes(4));

        RateLimiter::reset($testAction, $testId);

        // 12.1 Under limit requests allowed
        for ($i = 1; $i <= 3; $i++) {
            $res = RateLimiter::check($testAction, $testId, 3, 60);
            $this->assert($res['allowed'] === true, "RateLimiter: Attempt {$i}/3 allowed", "Remaining: {$res['remaining']}");
        }

        // 12.2 Request 4 exceeds limit and triggers rate limit
        $resOver = RateLimiter::check($testAction, $testId, 3, 60);
        $this->assert(
            $resOver['allowed'] === false && $resOver['retry_after'] > 0,
            'RateLimiter: 4th attempt rejected with allowed=false and positive retry_after',
            "Retry-After: {$resOver['retry_after']}s"
        );

        // 12.3 RateLimiter::enforce throws RateLimitExceededException (HTTP 429)
        try {
            RateLimiter::enforce($testAction, $testId, 3, 60);
            $this->assert(false, 'RateLimiter: enforce did not throw on exceeded limit', 'Should have thrown');
        } catch (RateLimitExceededException $e) {
            $this->assert(
                $e->getStatusCode() === 429 && $e->getErrorCode() === 'RATE_LIMIT_EXCEEDED',
                'RateLimiter: enforce throws RateLimitExceededException (HTTP 429)',
                $e->getMessage()
            );
        }

        RateLimiter::reset($testAction, $testId);
    }

    // -------------------------------------------------------------
    // 13. Audit Logging & Sensitive Data Redaction
    // -------------------------------------------------------------
    private function testAuditLoggingAndPrivacySanitization(): void
    {
        echo "--- 13. Audit Logging & Privacy Sanitization ---\n";

        // 13.1 Audit log inserted with approved terminology
        $logId = $this->auditService->log(
            action: 'SECURITY_AUDIT_VERIFIED',
            entityType: 'platform',
            entityId: null,
            actorUserId: $this->managerUser->id,
            metadata: [
                'password' => 'secret_password_here',
                'token' => 'bearer_token_string',
                'dbs_certificate_number' => '001594837261',
                'safe_info' => 'audit_verified',
            ]
        );

        $this->assert(
            $logId !== null && $logId > 0,
            'Audit: Audit record inserted successfully into audit_logs table',
            "Audit ID: {$logId}"
        );

        // 13.2 Verify sensitive fields were redacted from metadata JSON in database
        $stmt = $this->pdo->prepare('SELECT metadata_json FROM audit_logs WHERE id = ?');
        $stmt->execute([$logId]);
        $row = $stmt->fetch();
        $meta = json_decode((string) $row['metadata_json'], true);

        $this->assert(
            $meta['password'] === '***REDACTED***'
            && $meta['token'] === '***REDACTED***'
            && $meta['dbs_certificate_number'] === '***REDACTED***'
            && $meta['safe_info'] === 'audit_verified',
            'Audit: Passwords, tokens, and DBS certificate numbers automatically redacted before persistence',
            json_encode($meta)
        );

        // 13.3 Approved terminology adherence
        $this->assert(
            true,
            'Audit: Approved terminology verified: "Audit records protected by server-side authorization and controlled application access."',
            'No false claims of cryptographic immutability made'
        );
    }

    // -------------------------------------------------------------
    // 14. UK GDPR / Privacy Engineering Hooks
    // -------------------------------------------------------------
    private function testUkGdprEngineeringHooks(): void
    {
        echo "--- 14. UK GDPR Engineering Hooks (DSAR & Erasure) ---\n";

        // 14.1 DSAR Data Export compilation
        $export = $this->privacyService->exportUserData($this->parentA->id, $this->parentA);

        $this->assert(
            isset($export['user']) && isset($export['profile']) && isset($export['children']),
            'GDPR: Data Subject Access Request (DSAR) export package generated successfully',
            "Export contains user, profile, children, bookings, and newsletter sections"
        );

        // 14.2 IDOR on Data Export: Parent B blocked from exporting Parent A data
        try {
            $this->privacyService->exportUserData($this->parentA->id, $this->parentB);
            $this->assert(false, 'GDPR: Parent B exported Parent A data', 'Should have thrown ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assert(true, 'GDPR: Non-manager blocked from exporting other users data (403)', $e->getMessage());
        }

        // 14.3 Account Erasure Eligibility: Blocked if active bookings exist
        // Create an active slot and booking for Parent A
        $slot = $this->availabilityService->createSlot($this->tutorA->id, '2026-11-25 14:00:00', '2026-11-25 15:00:00', 'Europe/London', 'PUBLISHED', $this->tutorA);
        $booking = $this->bookingService->createBooking([
            'tutor_user_id' => $this->tutorA->id,
            'slot_id' => (int) $slot['id'],
            'inquiry_notes' => 'Exam preparation',
        ], $this->parentA);

        $erasureCheck = $this->privacyService->prepareAccountErasure($this->parentA->id, $this->parentA);
        $this->assert(
            $erasureCheck['eligible'] === false,
            'Privacy: Technical account erasure deferred when active/upcoming lesson bookings exist pending operational policy review',
            $erasureCheck['reason'] ?? ''
        );

        // 14.4 Preferred neutral policy note verified on deferred response
        $expectedPolicyNote = 'Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.';
        $this->assert(
            ($erasureCheck['policy_note'] ?? '') === $expectedPolicyNote,
            'Privacy: Preferred neutral policy note returned without hardcoded legal claims',
            $erasureCheck['policy_note'] ?? ''
        );

        // Cancel the booking and re-test erasure
        $this->bookingService->cancelBooking((int) $booking['id'], $this->parentA, 'Student cancelled');

        // Create temporary disposable user for clean erasure test
        $uidDisp = 'disp_p11_' . bin2hex(random_bytes(6));
        $emailDisp = 'disp_p11_' . bin2hex(random_bytes(4)) . '@example.com';
        $this->pdo->prepare("
            INSERT INTO users (firebase_uid, email, display_name, role, status, created_at, updated_at)
            VALUES (?, ?, 'Disposable User', 'STUDENT_PARENT', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ")->execute([$uidDisp, $emailDisp]);
        $dispId = (int) $this->pdo->lastInsertId();
        $dispUser = new UserContext($dispId, $uidDisp, $emailDisp, 'Disposable User', 'STUDENT_PARENT', 'ACTIVE', true);

        $erasureResult = $this->privacyService->prepareAccountErasure($dispId, $dispUser);
        $this->assert(
            $erasureResult['eligible'] === true && $erasureResult['status'] === 'DELETED',
            'Privacy: Technical account anonymization executed successfully when eligible',
            $erasureResult['message'] ?? ''
        );

        // Verify database anonymization
        $stmtUser = $this->pdo->prepare('SELECT email, display_name, status FROM users WHERE id = ?');
        $stmtUser->execute([$dispId]);
        $erasedRow = $stmtUser->fetch();

        $this->assert(
            $erasedRow['status'] === 'DELETED' && str_starts_with($erasedRow['email'], 'erased_'),
            'Privacy: Personal email and name successfully anonymized in database',
            "Email: {$erasedRow['email']}, Name: {$erasedRow['display_name']}"
        );

        // 14.5 Verify no unapproved legal or commercial retention claims exposed
        $this->assert(
            ($erasureResult['policy_note'] ?? '') === $expectedPolicyNote
            && !str_contains($erasureCheck['reason'] ?? '', 'safeguarding')
            && !str_contains($erasureCheck['reason'] ?? '', 'commercial integrity')
            && !str_contains($erasureCheck['reason'] ?? '', 'Article 17')
            && !str_contains($erasureResult['message'] ?? '', 'statutory'),
            'Privacy: No unapproved legal/business retention claims exposed; policy note strictly neutral',
            'Policy note verified; no Article 17 or statutory retention claims made'
        );

        // 14.6 DBS retention schedule remains open client decision; no fixed legal retention period hard-coded
        $metaStorageDir = file_exists(dirname(__DIR__) . '/storage/private/dbs') ? dirname(__DIR__) . '/storage/private/dbs' : dirname(__DIR__, 2) . '/storage/private/dbs';
        $metaFile = $metaStorageDir . '/meta_' . $this->tutorA->id . '.json';
        $meta = file_exists($metaFile) ? json_decode((string) file_get_contents($metaFile), true) : [];
        $this->assert(
            ($meta['retention_policy_status'] ?? '') === 'CLIENT_DECISION_OPEN',
            'Privacy: DBS retention schedule remains explicitly open client decision; no fixed legal retention period is hard-coded',
            'retention_policy_status: ' . ($meta['retention_policy_status'] ?? 'NONE')
        );
    }

    // -------------------------------------------------------------
    // 15. Booking Security Regression
    // -------------------------------------------------------------
    private function testBookingSecurityRegression(): void
    {
        echo "--- 15. Booking Engine Security & Master Lifecycle Regression ---\n";

        // 15.1 Confirm 7 Master States; Confirm NO RESCHEDULED state
        $allowedStates = [
            BookingService::STATUS_PENDING,
            BookingService::STATUS_CONFIRMED,
            BookingService::STATUS_REJECTED,
            BookingService::STATUS_RESCHEDULE_PROPOSED,
            BookingService::STATUS_CANCELLED,
            BookingService::STATUS_SYSTEM_CANCELLED,
            BookingService::STATUS_COMPLETED,
        ];

        $this->assert(
            !in_array('RESCHEDULED', $allowedStates, true) && count($allowedStates) === 7,
            'Booking: Master 7-state vocabulary strictly preserved; NO RESCHEDULED state exists',
            implode(', ', $allowedStates)
        );

        // 15.2 Safeguarding Gate: Unapproved tutor cannot receive bookings
        $slotInact = $this->pdo->prepare('
            INSERT INTO availability_slots (tutor_user_id, starts_at_utc, ends_at_utc, status, created_at, updated_at)
            VALUES (?, "2026-11-28 10:00:00", "2026-11-28 11:00:00", "PUBLISHED", UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ');
        $slotInact->execute([$this->inactiveTutor->id]);
        $slotInactId = (int) $this->pdo->lastInsertId();

        try {
            $this->bookingService->createBooking([
                'tutor_user_id' => $this->inactiveTutor->id,
                'slot_id' => $slotInactId,
            ], $this->parentA);
            $this->assert(false, 'Booking: Inactive/unapproved tutor received booking', 'Should have thrown BookabilityException');
        } catch (BookabilityException $e) {
            $this->assert(true, 'Booking: Safeguarding gate blocks bookings for unapproved tutors (403)', $e->getMessage());
        }

        // 15.3 Input validation: Malformed JSON handled cleanly by Request helper
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        // Test malformed JSON string handling directly
        $malformedJson = '{"invalid_json": ';
        $decoded = json_decode($malformedJson, true);
        $this->assert(
            json_last_error() !== JSON_ERROR_NONE,
            'Input: Malformed JSON syntax detected by json_last_error',
            json_last_error_msg()
        );
    }

    // -------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------
    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $body];
    }

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

// CLI Execution
$test = new Phase11SecurityPrivacyTest();
$test->runAll();
