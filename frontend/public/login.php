<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\FirebaseTokenVerifier;
use App\Database\Database;
use App\Services\StudentParentService;
use App\Support\Csrf;
use App\Support\View;
use Throwable;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = Database::getConnection();
$errorMessage = '';
$successMessage = '';

if (isset($_GET['logged_out'])) {
    $successMessage = 'You have been successfully signed out.';
} elseif (isset($_GET['registered'])) {
    $successMessage = 'Your account has been created successfully! Please sign in below.';
}

// If already authenticated and visiting login via GET, redirect to role portal
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

// Process Sign-In Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'Security validation failed (CSRF token expired). Please refresh and try again.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $errorMessage = 'Please enter both your email address and password.';
        } else {
            try {
                $verifier = new FirebaseTokenVerifier(null, $pdo);
                $firebaseAuth = $verifier->getFirebaseAuth();
                $authenticatedUser = null;

                // 1. Attempt live Firebase Sign-In with Email and Password
                try {
                    $signInResult = $firebaseAuth->signInWithEmailAndPassword($email, $password);
                    $verifiedUid = $signInResult->firebaseUserId();

                    // Find corresponding user in MySQL
                    $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `firebase_uid` = ? OR `email` = ? LIMIT 1');
                    $stmt->execute([$verifiedUid, $email]);
                    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($userRow) {
                        // Ensure UID is synchronized in MySQL
                        if ($userRow['firebase_uid'] !== $verifiedUid) {
                            $pdo->prepare('UPDATE `users` SET `firebase_uid` = ?, `updated_at` = UTC_TIMESTAMP() WHERE `id` = ?')
                                ->execute([$verifiedUid, $userRow['id']]);
                            $userRow['firebase_uid'] = $verifiedUid;
                        }
                        $authenticatedUser = $userRow;
                    } else {
                        // Firebase user exists but no MySQL profile yet; auto-provision as STUDENT_PARENT
                        $studentService = new StudentParentService($pdo);
                        $regResult = $studentService->registerStudentParent([
                            'firebase_uid' => $verifiedUid,
                            'email' => $email,
                            'display_name' => explode('@', $email)[0],
                            'role' => 'STUDENT_PARENT',
                        ]);
                        $authenticatedUser = $regResult['user'];
                    }
                } catch (Throwable $fbEx) {
                    // 2. Fallback: If user exists in MySQL (e.g. seeded manager editorial@apptutors.co.uk)
                    // but not yet in the empty Firebase project, provision in Firebase with the password provided
                    $stmtCheck = $pdo->prepare('SELECT * FROM `users` WHERE `email` = ? LIMIT 1');
                    $stmtCheck->execute([$email]);
                    $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingUser) {
                        try {
                            $fbUser = $firebaseAuth->createUser([
                                'email' => $email,
                                'password' => $password,
                                'displayName' => $existingUser['display_name'],
                                'emailVerified' => true,
                            ]);
                            $pdo->prepare('UPDATE `users` SET `firebase_uid` = ?, `updated_at` = UTC_TIMESTAMP() WHERE `id` = ?')
                                ->execute([$fbUser->uid, $existingUser['id']]);
                            $existingUser['firebase_uid'] = $fbUser->uid;
                            $authenticatedUser = $existingUser;
                        } catch (Throwable $createEx) {
                            $errorMessage = 'Invalid email or password. Please check your credentials or create a new account.';
                        }
                    } else {
                        $errorMessage = 'Invalid email or password. Please verify your credentials or create an account.';
                    }
                }

                // 3. Establish Session and Redirect if authenticated
                if ($authenticatedUser !== null) {
                    if (in_array($authenticatedUser['status'], ['SUSPENDED', 'DELETED'], true)) {
                        $errorMessage = "Your account is currently {$authenticatedUser['status']}. Please contact platform admissions.";
                    } else {
                        $_SESSION['user_id'] = (int) $authenticatedUser['id'];
                        $_SESSION['user_role'] = $authenticatedUser['role'];
                        $_SESSION['user_email'] = $authenticatedUser['email'];
                        $_SESSION['user_name'] = $authenticatedUser['display_name'];

                        if ($authenticatedUser['role'] === 'MANAGER') {
                            header('Location: /manager-dashboard.php');
                            exit;
                        } elseif ($authenticatedUser['role'] === 'TUTOR') {
                            header('Location: /tutor-profile.php');
                            exit;
                        } else {
                            header('Location: /student-profile.php');
                            exit;
                        }
                    }
                }
            } catch (Throwable $e) {
                $errorMessage = 'An unexpected authentication error occurred: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'login',
    [
        'csrfToken' => $csrfToken,
        'errorMessage' => $errorMessage,
        'successMessage' => $successMessage,
    ],
    'Sign In to Dashboard — AppTutors UK',
    'Sign in to your parent, student, tutor, or manager account.'
);
