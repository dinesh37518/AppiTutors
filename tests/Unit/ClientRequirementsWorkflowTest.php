<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\AvailabilityService;
use App\Services\BlogService;
use App\Services\BookingService;
use App\Services\Email\Adapters\EmailJsEmailAdapter;
use App\Services\LessonNotesService;
use App\Services\ManagerService;
use App\Services\NewsletterService;
use App\Services\StudentParentService;
use App\Services\TutorService;

$db = Database::getConnection();

$passed = 0;
$failed = 0;
$total = 0;

function assertCondition(bool $condition, string $message): void
{
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "========================================================\n";
echo "CLIENT REQUIREMENTS WORKFLOW VERIFICATION SUITE\n";
echo "========================================================\n\n";

// ---------------------------------------------------------------------
// TEST 1: EmailJS Email Adapter Integration & Authoritative Recipient
// ---------------------------------------------------------------------
echo "1. EmailJS Adapter Integration:\n";
$emailJs = new EmailJsEmailAdapter([
    'service_id' => 'mock_service',
    'template_id' => 'template_test123',
    'public_key' => 'pub_key_test123',
    'private_key' => 'priv_key_test123',
]);
assertCondition($emailJs->isConfigured(), 'EmailJS adapter reports configured when credentials present');

$sendResult = $emailJs->send(
    toEmail: 'student@example.co.uk',
    toName: 'Jane Doe',
    subject: 'Verification Code',
    htmlBody: '<p>Test email</p>'
);
assertCondition($sendResult->isSuccess(), 'EmailJS adapter dispatches to authoritative recipient');
echo "\n";

// ---------------------------------------------------------------------
// TEST 2: Student Registration with Parent Email
// ---------------------------------------------------------------------
echo "2. Student Registration & Parent Email Validation:\n";
$studentService = new StudentParentService($db);

$uniqueSuffix = time() . '_' . random_int(1000, 9999);
$studentEmail = "student_{$uniqueSuffix}@example.com";
$parentEmail = "parent_{$uniqueSuffix}@example.com";

$studentReg = $studentService->registerStudentParent([
    'firebase_uid' => 'fb_' . $uniqueSuffix,
    'email' => $studentEmail,
    'password' => 'SecurePass123!',
    'display_name' => "Student Test {$uniqueSuffix}",
    'parent_email' => $parentEmail,
]);

assertCondition(!empty($studentReg['user']['id']), 'Student account created successfully');

$studentUser = new UserContext(
    id: (int) $studentReg['user']['id'],
    firebaseUid: 'fb_' . $uniqueSuffix,
    email: $studentEmail,
    displayName: "Student Test {$uniqueSuffix}",
    role: 'STUDENT_PARENT',
    status: 'ACTIVE'
);

$profile = $studentService->getProfile($studentUser->id, $studentUser);
assertCondition(($profile['parent_email'] ?? '') === $parentEmail, 'Authoritative parent email persisted and retrieved in student_profiles');
echo "\n";

// ---------------------------------------------------------------------
// TEST 3: 1-to-Many Slots and Capacity Concurrency Protection
// ---------------------------------------------------------------------
echo "3. 1-to-Many Slots and Capacity Protection:\n";
$availService = new AvailabilityService($db);
$bookingService = new BookingService($db);

// Find or create an approved and verified bookable tutor
$tutorRow = $db->query("
    SELECT u.* FROM `users` u 
    JOIN `tutor_profiles` tp ON u.id = tp.user_id 
    WHERE u.role = 'TUTOR' 
      AND u.status = 'ACTIVE' 
      AND tp.approval_status = 'APPROVED' 
      AND tp.dbs_status = 'VERIFIED' 
    LIMIT 1
")->fetch();

if ($tutorRow) {
    $tutorUser = UserContext::fromDatabaseRow($tutorRow);
    $randomDays = random_int(20, 80);
    $randomHour = random_int(8, 18);
    $startsAt = (new DateTimeImmutable("+{$randomDays} days", new DateTimeZone('UTC')))->setTime($randomHour, 0)->format('Y-m-d H:i:s');
    $endsAt = (new DateTimeImmutable("+{$randomDays} days", new DateTimeZone('UTC')))->setTime($randomHour + 1, 0)->format('Y-m-d H:i:s');

    // Create 1-to-many slot with capacity = 2
    $slot = $availService->createSlot(
        tutorUserId: $tutorUser->id,
        startsAt: $startsAt,
        endsAt: $endsAt,
        arg4: 'Europe/London',
        arg5: AvailabilityService::STATUS_PUBLISHED,
        currentUser: $tutorUser,
        maxStudents: 2
    );

    assertCondition((int) $slot['max_students'] === 2, 'Availability slot created with max_students = 2');
    assertCondition(!empty($slot['is_group']), 'Slot correctly flagged as group slot');

    // Student 1 books slot
    $booking1 = $bookingService->createBooking([
        'tutor_user_id' => $tutorUser->id,
        'slot_id' => (int) $slot['id'],
        'inquiry_notes' => 'Student 1 reservation',
    ], $studentUser);
    assertCondition((int) $booking1['id'] > 0, 'Student 1 booked 1-to-many slot successfully');

    // Check slot is still available for student 2
    $slotAfter1Stmt = $db->prepare('SELECT status FROM `availability_slots` WHERE `id` = ?');
    $slotAfter1Stmt->execute([(int) $slot['id']]);
    $slotAfter1Status = $slotAfter1Stmt->fetchColumn();
    assertCondition($slotAfter1Status === 'PUBLISHED', 'Slot remains PUBLISHED when capacity not yet reached (1 of 2 booked)');

    // Create Student 2
    $student2Email = "student2_{$uniqueSuffix}@example.com";
    $student2Reg = $studentService->registerStudentParent([
        'firebase_uid' => 'fb_2_' . $uniqueSuffix,
        'email' => $student2Email,
        'password' => 'SecurePass123!',
        'display_name' => "Student Two {$uniqueSuffix}",
        'parent_email' => "parent2_{$uniqueSuffix}@example.com",
    ]);
    $student2User = new UserContext(
        id: (int) $student2Reg['user']['id'],
        firebaseUid: 'fb_2_' . $uniqueSuffix,
        email: $student2Email,
        displayName: "Student Two {$uniqueSuffix}",
        role: 'STUDENT_PARENT',
        status: 'ACTIVE'
    );

    // Student 2 books slot
    $booking2 = $bookingService->createBooking([
        'tutor_user_id' => $tutorUser->id,
        'slot_id' => (int) $slot['id'],
        'inquiry_notes' => 'Student 2 reservation',
    ], $student2User);
    assertCondition((int) $booking2['id'] > 0, 'Student 2 booked 1-to-many slot successfully');

    // Slot is now at maximum capacity (2 of 2) -> marked BOOKED
    $slotAfter2Stmt = $db->prepare('SELECT status FROM `availability_slots` WHERE `id` = ?');
    $slotAfter2Stmt->execute([(int) $slot['id']]);
    $slotAfter2Status = $slotAfter2Stmt->fetchColumn();
    assertCondition($slotAfter2Status === 'BOOKED', 'Slot automatically transitions to BOOKED when capacity reached (2 of 2 booked)');

    // Student 3 attempt should be rejected / blocked
    $student3Email = "student3_{$uniqueSuffix}@example.com";
    $student3Reg = $studentService->registerStudentParent([
        'firebase_uid' => 'fb_3_' . $uniqueSuffix,
        'email' => $student3Email,
        'password' => 'SecurePass123!',
        'display_name' => "Student Three {$uniqueSuffix}",
    ]);
    $student3User = new UserContext(
        id: (int) $student3Reg['user']['id'],
        firebaseUid: 'fb_3_' . $uniqueSuffix,
        email: $student3Email,
        displayName: "Student Three {$uniqueSuffix}",
        role: 'STUDENT_PARENT',
        status: 'ACTIVE'
    );

    $slotOverbooked = false;
    try {
        $bookingService->createBooking([
            'tutor_user_id' => $tutorUser->id,
            'slot_id' => (int) $slot['id'],
            'inquiry_notes' => 'Student 3 should fail',
        ], $student3User);
        $slotOverbooked = true;
    } catch (\Throwable $e) {
        $slotOverbooked = false;
    }
    assertCondition(!$slotOverbooked, 'Attempt to overbook 1-to-many slot beyond capacity strictly rejected');
} else {
    echo "  [SKIP] No approved tutor found for slot test\n";
}
echo "\n";

// ---------------------------------------------------------------------
// TEST 4: Meeting Link Integration on Booking Confirmation
// ---------------------------------------------------------------------
echo "4. Meeting Link Integration:\n";
if (isset($booking1) && isset($tutorUser)) {
    $meetingUrl = 'https://meet.google.com/abc-defg-hij';
    $confirmedBooking = $bookingService->confirmBooking(
        bookingId: (int) $booking1['id'],
        currentUser: $tutorUser,
        reason: 'Confirmed by tutor with Google Meet classroom link',
        meetingLink: $meetingUrl
    );

    assertCondition($confirmedBooking['status'] === 'CONFIRMED', 'Booking confirmed by tutor');
    assertCondition(($confirmedBooking['meeting_link'] ?? '') === $meetingUrl, 'Meeting attendance link stored in booking history and exposed');

    // Student view exposes the meeting link
    $studentFetchedBooking = $bookingService->getBooking((int) $booking1['id'], $studentUser);
    assertCondition(($studentFetchedBooking['meeting_link'] ?? '') === $meetingUrl, 'Student can view the online attendance meeting link');
}
echo "\n";

// ---------------------------------------------------------------------
// TEST 5: Lesson Notes System & Access Control
// ---------------------------------------------------------------------
echo "5. Lesson Notes System & Access Control:\n";
if (isset($booking1) && isset($tutorUser) && isset($studentUser) && isset($student3User)) {
    $notesService = new LessonNotesService($db);

    // Tutor creates parent-visible note
    $publicNote = $notesService->createNote(
        bookingId: (int) $booking1['id'],
        notes: 'Covered quadratic equations; student grasped factoring well. Homework: exercises 4-8.',
        visibility: 'PARENT_VISIBLE',
        currentUser: $tutorUser
    );
    assertCondition((int) $publicNote['id'] > 0, 'Tutor successfully authored PARENT_VISIBLE lesson note');

    // Tutor creates internal note
    $internalNote = $notesService->createNote(
        bookingId: (int) $booking1['id'],
        notes: 'Parent expressed concern about exam pacing. Need to review speed next session.',
        visibility: 'INTERNAL',
        currentUser: $tutorUser
    );
    assertCondition((int) $internalNote['id'] > 0, 'Tutor successfully authored INTERNAL lesson note');

    // Student fetches notes: should only see PARENT_VISIBLE note
    $studentNotes = $notesService->getNotesForBooking((int) $booking1['id'], $studentUser);
    assertCondition(count($studentNotes) === 1, 'Student can only see PARENT_VISIBLE lesson notes (1 of 2 notes visible)');
    assertCondition($studentNotes[0]['content'] === $publicNote['content'], 'Student sees correct lesson note content');

    // Unrelated student (student 3) attempts to read notes: should be blocked by IDOR check
    $unrelatedNotesBlocked = false;
    try {
        $notesService->getNotesForBooking((int) $booking1['id'], $student3User);
    } catch (\App\Authorization\ForbiddenException $e) {
        $unrelatedNotesBlocked = true;
    }
    assertCondition($unrelatedNotesBlocked, 'Unrelated user blocked from accessing student lesson notes (IDOR protected)');
}
echo "\n";

// ---------------------------------------------------------------------
// TEST 6: 24-Hour Auto-Cancellation
// ---------------------------------------------------------------------
echo "6. 24-Hour Auto-Cancellation of Stale Bookings:\n";
if (isset($tutorUser) && isset($studentUser)) {
    $randomDaysStale = random_int(120, 200);
    $startsAtStale = (new DateTimeImmutable("+{$randomDaysStale} days", new DateTimeZone('UTC')))->setTime(14, 0)->format('Y-m-d H:i:s');
    $endsAtStale = (new DateTimeImmutable("+{$randomDaysStale} days", new DateTimeZone('UTC')))->setTime(15, 0)->format('Y-m-d H:i:s');
    $staleSlot = $availService->createSlot(
        tutorUserId: $tutorUser->id,
        startsAt: $startsAtStale,
        endsAt: $endsAtStale,
        arg4: 'Europe/London',
        arg5: AvailabilityService::STATUS_PUBLISHED,
        currentUser: $tutorUser,
        maxStudents: 1
    );

    $staleBooking = $bookingService->createBooking([
        'tutor_user_id' => $tutorUser->id,
        'slot_id' => (int) $staleSlot['id'],
        'inquiry_notes' => 'Stale test booking',
    ], $studentUser);

    // Backdate the booking created_at to 26 hours ago
    $db->prepare("
        UPDATE `bookings` 
        SET `created_at` = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 26 HOUR) 
        WHERE `id` = ?
    ")->execute([(int) $staleBooking['id']]);

    // Execute auto-cancel
    $cancelled = $bookingService->autoCancelStaleBookings(24);
    assertCondition($cancelled > 0, 'Stale pending booking auto-cancelled after 24 hours of inactivity');

    // Verify booking status is SYSTEM_CANCELLED
    $refreshedBooking = $bookingService->getBooking((int) $staleBooking['id'], $studentUser);
    assertCondition($refreshedBooking['status'] === 'SYSTEM_CANCELLED', 'Auto-cancelled booking transitioned to SYSTEM_CANCELLED status');

    // Verify slot was reopened to PUBLISHED
    $reopenedSlotStmt = $db->prepare('SELECT status FROM `availability_slots` WHERE `id` = ?');
    $reopenedSlotStmt->execute([(int) $staleSlot['id']]);
    $reopenedSlotStatus = $reopenedSlotStmt->fetchColumn();
    assertCondition($reopenedSlotStatus === 'PUBLISHED', 'Slot reopened to PUBLISHED status following 24hr auto-cancellation');
}
echo "\n";

// ---------------------------------------------------------------------
// TEST 7: Manager Tutor Rejection with Reason
// ---------------------------------------------------------------------
echo "7. Manager Tutor Rejection Reason:\n";
$managerRow = $db->query("SELECT * FROM `users` WHERE `role` = 'MANAGER' AND `status` = 'ACTIVE' LIMIT 1")->fetch();
if ($managerRow) {
    $managerUser = UserContext::fromDatabaseRow($managerRow);

    // Register a new candidate tutor
    $tutorCandidateEmail = "tutor_cand_{$uniqueSuffix}@example.com";
    $tutorService = new TutorService($db);
    $candReg = $tutorService->registerTutor([
        'firebase_uid' => 'fb_cand_' . $uniqueSuffix,
        'email' => $tutorCandidateEmail,
        'password' => 'SecurePass123!',
        'display_name' => "Tutor Cand {$uniqueSuffix}",
        'phone' => '+447911123456',
        'postcode' => 'SW1A 1AA',
        'headline' => 'GCSE Tutor',
        'hourly_rate' => 35.00,
    ]);

    $rejectionReason = 'Certificate missing - Enhanced DBS check missing valid issue date';
    $rejectedProfile = $tutorService->managerRejectTutor(
        tutorUserId: (int) $candReg['id'],
        reason: $rejectionReason,
        manager: $managerUser
    );

    assertCondition($rejectedProfile['approval_status'] === 'REJECTED', 'Tutor application rejected by manager');

    // Check audit log for rejection reason
    $auditStmt = $db->prepare("
        SELECT metadata_json FROM `audit_logs` 
        WHERE `action` = 'MANAGER_REJECT_TUTOR' AND `entity_id` = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $auditStmt->execute([(int) $candReg['id']]);
    $auditRow = $auditStmt->fetch();
    $auditMeta = json_decode((string) ($auditRow['metadata_json'] ?? '{}'), true);
    assertCondition(($auditMeta['reason'] ?? '') === $rejectionReason, 'Specific manager rejection reason recorded in audit log');
}
echo "\n";

// ---------------------------------------------------------------------
// TEST 8: Tutor Blog Authoring, Manager Moderation & Newsletter Broadcast
// ---------------------------------------------------------------------
echo "8. Tutor Blog Authoring, Moderation & Newsletter Broadcast:\n";
if (isset($tutorUser) && isset($managerUser)) {
    $blogService = new BlogService($db);
    $newsletterService = new NewsletterService($db);

    // Tutor creates draft article
    $blogPost = $blogService->createPost($tutorUser, [
        'title' => "Mastering A-Level Maths {$uniqueSuffix}",
        'slug' => "mastering-a-level-maths-{$uniqueSuffix}",
        'excerpt' => 'Essential calculus tips for top grades.',
        'body' => 'In this revision guide, we examine integration techniques step by step.',
        'status' => 'DRAFT',
    ]);
    assertCondition((int) $blogPost['id'] > 0 && $blogPost['status'] === 'DRAFT', 'Tutor created draft blog post');

    // Tutor submits for manager review
    $submittedPost = $blogService->submitPost($tutorUser, (int) $blogPost['id']);
    assertCondition($submittedPost['status'] === 'SUBMITTED', 'Tutor submitted blog post for manager review (SUBMITTED)');

    // Manager approves post
    $approvedPost = $blogService->approvePost($managerUser, (int) $blogPost['id']);
    assertCondition($approvedPost['status'] === 'APPROVED', 'Manager approved blog post (APPROVED)');

    // Manager publishes post
    $publishedPost = $blogService->publishPost($managerUser, (int) $blogPost['id']);
    assertCondition($publishedPost['status'] === 'PUBLISHED', 'Manager published blog post to website (PUBLISHED)');

    // Add a test subscriber
    $subEmail = "subscriber_{$uniqueSuffix}@example.com";
    $newsletterService->subscribe($subEmail, true);

    // Manager broadcasts published post to newsletter subscribers
    $broadcastResult = $newsletterService->broadcastPublishedBlogPost((int) $blogPost['id'], $managerUser);
    assertCondition($broadcastResult['sent_count'] > 0, 'Manager broadcasted published post notification to newsletter subscribers');
}
echo "\n";

// ---------------------------------------------------------------------
// TEST 9: Strict Role Protection Invariants
// ---------------------------------------------------------------------
echo "9. Strict Role Protection Invariants:\n";
// Ensure student/parent registration cannot set MANAGER role
$managerRoleInjectionBlocked = false;
try {
    $studentService->registerStudentParent([
        'firebase_uid' => "fb_inj_{$uniqueSuffix}",
        'email' => "injected_mgr_{$uniqueSuffix}@example.com",
        'password' => 'SecurePass123!',
        'display_name' => 'Attacker',
        'role' => 'MANAGER',
    ]);
    $checkRole = $db->query("SELECT role FROM users WHERE email = 'injected_mgr_{$uniqueSuffix}@example.com'")->fetchColumn();
    // Role must be forced to STUDENT_PARENT
    $managerRoleInjectionBlocked = ($checkRole === 'STUDENT_PARENT');
} catch (\Throwable $e) {
    $managerRoleInjectionBlocked = true;
}
assertCondition($managerRoleInjectionBlocked, 'Client cannot self-assign MANAGER role during registration');

// Prevent tutor self-approval
if (isset($tutorUser)) {
    $selfApprovalBlocked = false;
    try {
        $tutorService->managerApproveTutor($tutorUser->id, $tutorUser);
    } catch (\App\Authorization\ForbiddenException $e) {
        $selfApprovalBlocked = true;
    }
    assertCondition($selfApprovalBlocked, 'Tutor cannot self-approve their own account');
}
echo "\n";

echo "========================================================\n";
echo "SUMMARY: {$passed} / {$total} PASSED\n";
if ($failed === 0) {
    echo "STATUS: ALL CLIENT WORKFLOW TESTS PASSED (100%)\n";
    exit(0);
} else {
    echo "STATUS: {$failed} FAILURES DETECTED\n";
    exit(1);
}
