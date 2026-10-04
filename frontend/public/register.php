<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\FirebaseTokenVerifier;
use App\Database\Database;
use App\Services\StudentParentService;
use App\Services\TutorService;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\Validator;
use Throwable;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = Database::getConnection();
$errorMessage = '';
$accountType = $_GET['type'] ?? 'student';

// If already authenticated and visiting register via GET, redirect to role portal
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'STUDENT_PARENT';
    if ($role === 'MANAGER') {
        header('Location: /manager-dashboard.php');
        exit;
    } elseif ($role === 'TUTOR') {
        header('Location: /tutor-profile.php');
        exit;
    } else {
        header('Location: /student-profile.php');
        exit;
    }
}

// Handle Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'Security validation failed (CSRF token expired). Please refresh and try again.';
    } else {
        $accountType = trim((string) ($_POST['account_type'] ?? 'student'));
        $displayName = Validator::sanitizeString((string) ($_POST['display_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (empty($displayName)) {
            $errorMessage = 'Please provide your full name.';
        } elseif (empty($email) || !Validator::validateEmail($email)) {
            $errorMessage = 'Please provide a valid UK email address.';
        } elseif (strlen($password) < 6) {
            $errorMessage = 'Password must be at least 6 characters in length.';
        } else {
            try {
                $verifier = new FirebaseTokenVerifier(null, $pdo);
                $firebaseAuth = $verifier->getFirebaseAuth();
                $firebaseUid = null;

                // 1. Create Identity in Firebase Auth
                try {
                    $fbUser = $firebaseAuth->createUser([
                        'email' => $email,
                        'password' => $password,
                        'displayName' => $displayName,
                        'emailVerified' => false,
                    ]);
                    $firebaseUid = $fbUser->uid;
                } catch (Throwable $fbEx) {
                    // Check if already registered in Firebase
                    try {
                        $existingFbUser = $firebaseAuth->getUserByEmail($email);
                        $firebaseUid = $existingFbUser->uid;
                    } catch (Throwable) {
                        $errorMessage = 'Unable to create account in Firebase: ' . $fbEx->getMessage();
                    }
                }

                if (!empty($firebaseUid)) {
                    // 2. Check if user already exists in MySQL
                    $stmtCheck = $pdo->prepare('SELECT id, role FROM `users` WHERE `firebase_uid` = ? OR `email` = ? LIMIT 1');
                    $stmtCheck->execute([$firebaseUid, $email]);
                    $existingMysqlUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingMysqlUser) {
                        $errorMessage = 'An account with this email address already exists. Please sign in instead.';
                    } else {
                        // 3. Register based on Account Type
                        if ($accountType === 'tutor') {
                            $tutorService = new TutorService($pdo);
                            $tutorReg = $tutorService->registerTutor([
                                'firebase_uid' => $firebaseUid,
                                'email' => $email,
                                'display_name' => $displayName,
                                'role' => 'TUTOR',
                                'headline' => !empty($_POST['headline']) ? Validator::sanitizeString($_POST['headline']) : 'UK Qualified Tutor',
                                'hourly_rate' => !empty($_POST['hourly_rate']) ? (float) $_POST['hourly_rate'] : 45.00,
                            ]);
                            $newUserId = (int) $tutorReg['id'];
                            $newRole = 'TUTOR';
                            $redirectUrl = '/tutor-profile.php?welcome=1';
                        } else {
                            $studentService = new StudentParentService($pdo);
                            $studentReg = $studentService->registerStudentParent([
                                'firebase_uid' => $firebaseUid,
                                'email' => $email,
                                'display_name' => $displayName,
                                'role' => 'STUDENT_PARENT',
                                'phone' => !empty($_POST['phone']) ? Validator::sanitizeString($_POST['phone']) : null,
                                'postcode' => !empty($_POST['postcode']) ? strtoupper(Validator::sanitizeString($_POST['postcode'])) : null,
                            ]);
                            $newUserId = (int) $studentReg['user']['id'];
                            $newRole = 'STUDENT_PARENT';
                            $redirectUrl = '/student-profile.php?welcome=1';
                        }

                        // 4. Establish Session
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['user_role'] = $newRole;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_name'] = $displayName;

                        header("Location: {$redirectUrl}");
                        exit;
                    }
                }
            } catch (Throwable $e) {
                $errorMessage = 'Registration error: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'register',
    [
        'csrfToken' => $csrfToken,
        'errorMessage' => $errorMessage,
        'accountType' => $accountType,
    ],
    'Create an Account — AppTutors UK',
    'Register as a parent or student, or apply to join our verified roster of professional UK educators.'
);
