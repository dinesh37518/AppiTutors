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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = Database::getConnection();
$errorMessage = '';
$accountType = $_GET['type'] ?? 'student';

// If already authenticated and visiting register via GET, redirect to role portal UNLESS applying as tutor
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'STUDENT_PARENT';
    if ($role === 'MANAGER') {
        header('Location: /manager-dashboard.php');
        exit;
    } elseif ($role === 'TUTOR') {
        header('Location: /tutor-profile.php');
        exit;
    } else {
        // If logged in as student/parent and explicitly applying to become a tutor, allow access to educator application!
        if ($accountType !== 'tutor') {
            header('Location: /student-profile.php');
            exit;
        }
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
                    // Extract tutor-specific attributes if applying as tutor
                    if ($accountType === 'tutor') {
                        $headline = !empty($_POST['headline']) ? Validator::sanitizeString((string)$_POST['headline']) : 'UK Qualified Educator';
                        $qualifications = !empty($_POST['qualifications']) ? Validator::sanitizeString((string)$_POST['qualifications']) : '';
                        $bio = !empty($_POST['bio']) ? Validator::sanitizeString((string)$_POST['bio']) : 'Dedicated UK educator committed to pupil academic success.';
                        $dbsCertificateNumber = !empty($_POST['dbs_certificate_number']) ? trim((string)$_POST['dbs_certificate_number']) : '';
                        $dbsIssueDate = !empty($_POST['dbs_issue_date']) ? trim((string)$_POST['dbs_issue_date']) : date('Y-m-d');

                        if (empty($qualifications)) {
                            throw new \RuntimeException('Please provide your academic qualifications and teaching credentials.');
                        }
                        if (empty($dbsCertificateNumber)) {
                            throw new \RuntimeException('Please provide your Enhanced DBS certificate number for safeguarding verification.');
                        }

                        $subjects = [];
                        if (!empty($_POST['subjects']) && is_array($_POST['subjects'])) {
                            foreach ($_POST['subjects'] as $s) {
                                $clean = Validator::sanitizeString((string)$s);
                                if ($clean !== '') {
                                    $subjects[] = $clean;
                                }
                            }
                        }
                        if (!empty($_POST['custom_subjects'])) {
                            $custom = explode(',', (string)$_POST['custom_subjects']);
                            foreach ($custom as $s) {
                                $clean = Validator::sanitizeString(trim($s));
                                if ($clean !== '' && !in_array($clean, $subjects, true)) {
                                    $subjects[] = $clean;
                                }
                            }
                        }
                        if (empty($subjects)) {
                            $subjects = ['Mathematics', 'Science'];
                        }

                        $curriculum = ['Primary', '11+', 'KS3', 'GCSE', 'A-Level'];
                    }

                    // 2. Check if user already exists in MySQL
                    $stmtCheck = $pdo->prepare('SELECT id, role, status FROM `users` WHERE `firebase_uid` = ? OR `email` = ? LIMIT 1');
                    $stmtCheck->execute([$firebaseUid, $email]);
                    $existingMysqlUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existingMysqlUser) {
                        if ($accountType === 'tutor') {
                            $tutorUserId = (int) $existingMysqlUser['id'];

                            // Grant immediate ACTIVE status and TUTOR role
                            $stmtUp = $pdo->prepare("UPDATE `users` SET `role` = 'TUTOR', `status` = 'ACTIVE', `display_name` = :name, `updated_at` = UTC_TIMESTAMP() WHERE `id` = :id");
                            $stmtUp->execute([':name' => $displayName, ':id' => $tutorUserId]);

                            // Insert or update tutor profile with qualifications and verified status
                            $stmtProf = $pdo->prepare("
                                INSERT INTO `tutor_profiles` 
                                (`user_id`, `headline`, `bio`, `subjects_json`, `curriculum_json`, `qualifications`, `dbs_status`, `approval_status`, `approved_at`, `created_at`, `updated_at`)
                                VALUES (:id, :headline, :bio, :subjects, :curriculum, :qualifications, 'VERIFIED', 'APPROVED', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
                                ON DUPLICATE KEY UPDATE 
                                    `headline` = VALUES(`headline`),
                                    `bio` = VALUES(`bio`),
                                    `subjects_json` = VALUES(`subjects_json`),
                                    `curriculum_json` = VALUES(`curriculum_json`),
                                    `qualifications` = VALUES(`qualifications`),
                                    `dbs_status` = 'VERIFIED',
                                    `approval_status` = 'APPROVED',
                                    `approved_at` = UTC_TIMESTAMP(),
                                    `updated_at` = UTC_TIMESTAMP()
                            ");
                            $stmtProf->execute([
                                ':id' => $tutorUserId,
                                ':headline' => $headline,
                                ':bio' => $bio,
                                ':subjects' => json_encode($subjects, JSON_UNESCAPED_SLASHES),
                                ':curriculum' => json_encode($curriculum, JSON_UNESCAPED_SLASHES),
                                ':qualifications' => $qualifications,
                            ]);

                            $newUserId = $tutorUserId;
                            $newRole = 'TUTOR';
                            $redirectUrl = '/tutor-profile.php?welcome=1';
                        } else {
                            $errorMessage = 'An account with this email address already exists. Please sign in instead.';
                        }
                    } else {
                        // 3. Register based on Account Type
                        if ($accountType === 'tutor') {
                            // Insert into users with ACTIVE status to grant immediate tutor access
                            $stmtUser = $pdo->prepare("
                                INSERT INTO `users` 
                                (`firebase_uid`, `email`, `display_name`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`)
                                VALUES (?, ?, ?, 'TUTOR', 'ACTIVE', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
                            ");
                            $stmtUser->execute([$firebaseUid, $email, $displayName]);
                            $tutorUserId = (int) $pdo->lastInsertId();

                            // Insert tutor profile with qualifications and verified DBS
                            $stmtProf = $pdo->prepare("
                                INSERT INTO `tutor_profiles` 
                                (`user_id`, `headline`, `bio`, `subjects_json`, `curriculum_json`, `qualifications`, `dbs_status`, `approval_status`, `approved_at`, `created_at`, `updated_at`)
                                VALUES (?, ?, ?, ?, ?, ?, 'VERIFIED', 'APPROVED', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
                            ");
                            $stmtProf->execute([
                                $tutorUserId,
                                $headline,
                                $bio,
                                json_encode($subjects, JSON_UNESCAPED_SLASHES),
                                json_encode($curriculum, JSON_UNESCAPED_SLASHES),
                                $qualifications,
                            ]);

                            $newUserId = $tutorUserId;
                            $newRole = 'TUTOR';
                            $redirectUrl = '/tutor-profile.php?welcome=1';
                        } else {
                            $parentEmail = null;
                            if (!empty($_POST['parent_email'])) {
                                $parentEmail = strtolower(trim((string) $_POST['parent_email']));
                                if (!Validator::validateEmail($parentEmail)) {
                                    throw new \RuntimeException('Please provide a valid parent email address.');
                                }
                            }

                            $studentService = new StudentParentService($pdo);
                            $studentReg = $studentService->registerStudentParent([
                                'firebase_uid' => $firebaseUid,
                                'email' => $email,
                                'display_name' => $displayName,
                                'role' => 'STUDENT_PARENT',
                                'phone' => !empty($_POST['phone']) ? Validator::sanitizeString($_POST['phone']) : null,
                                'postcode' => !empty($_POST['postcode']) ? strtoupper(Validator::sanitizeString($_POST['postcode'])) : null,
                                'parent_email' => $parentEmail,
                            ]);
                            $newUserId = (int) $studentReg['user']['id'];
                            $newRole = 'STUDENT_PARENT';
                            $redirectUrl = '/student-profile.php?welcome=1';
                        }
                    }

                    // Process certificate document upload & metadata if tutor
                    if ($accountType === 'tutor' && !empty($newUserId)) {
                        $uploadedFile = $_FILES['dbs_file'] ?? null;
                        $storedFilename = null;
                        $originalFilename = null;
                        $mimeType = null;
                        $fileSize = 0;

                        $dbsDir = dirname(__DIR__, 2) . '/storage/private/dbs';
                        if (!is_dir($dbsDir)) {
                            @mkdir($dbsDir, 0750, true);
                        }

                        if ($uploadedFile !== null && !empty($uploadedFile['tmp_name']) && is_uploaded_file($uploadedFile['tmp_name'])) {
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $detectedMime = $finfo->file($uploadedFile['tmp_name']);
                            $allowedMimes = ['application/pdf', 'image/png', 'image/jpeg'];
                            if (in_array($detectedMime, $allowedMimes, true) && $uploadedFile['size'] <= 5242880) {
                                $ext = match ($detectedMime) {
                                    'application/pdf' => 'pdf',
                                    'image/png' => 'png',
                                    'image/jpeg' => 'jpg',
                                    default => 'bin',
                                };
                                $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                                move_uploaded_file($uploadedFile['tmp_name'], $dbsDir . '/' . $storedFilename);
                                $originalFilename = basename($uploadedFile['name']);
                                $mimeType = $detectedMime;
                                $fileSize = (int) $uploadedFile['size'];
                            }
                        }

                        $docRecord = [
                            'tutor_user_id' => $newUserId,
                            'certificate_number' => $dbsCertificateNumber,
                            'issue_date' => $dbsIssueDate,
                            'stored_filename' => $storedFilename,
                            'original_filename' => $originalFilename,
                            'mime_type' => $mimeType,
                            'file_size' => $fileSize,
                            'submitted_at_utc' => gmdate('Y-m-d H:i:s'),
                            'submitted_by_user_id' => $newUserId,
                            'retention_policy_status' => 'CLIENT_DECISION_OPEN',
                        ];
                        file_put_contents(
                            $dbsDir . "/meta_{$newUserId}.json",
                            json_encode($docRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        );
                    }

                        // Dispatch transactional registration/verification email to the authoritative registered email
                        try {
                            $emailService = new \App\Services\Email\DefaultEmailService();
                            if ($accountType === 'tutor') {
                                $emailService->send(
                                    $email,
                                    $displayName,
                                    'Your AppTutors UK Tutor Application Received',
                                    'tutor_registered',
                                    [
                                        'recipient_name' => $displayName,
                                        'app_url' => \App\Support\Env::get('APP_URL', 'http://localhost'),
                                    ]
                                );
                            } else {
                                $emailService->send(
                                    $email,
                                    $displayName,
                                    'Verify Your AppTutors UK Account',
                                    'account_verification',
                                    [
                                        'recipient_name' => $displayName,
                                        'recipient_email' => $email,
                                        'account_role' => ($accountType === 'parent' ? 'Parent' : 'Student'),
                                        'parent_email' => $parentEmail,
                                        'app_url' => \App\Support\Env::get('APP_URL', 'http://localhost'),
                                        'verification_url' => \App\Support\Env::get('APP_URL', 'http://localhost') . '/login.php?verified=1',
                                    ]
                                );
                                if (!empty($parentEmail)) {
                                    $emailService->send(
                                        $parentEmail,
                                        'Parent/Guardian of ' . $displayName,
                                        'AppTutors UK — Student Account Linked: ' . $displayName,
                                        'account_verification',
                                        [
                                            'recipient_name' => 'Parent / Guardian',
                                            'recipient_email' => $parentEmail,
                                            'account_role' => 'Linked Parent for ' . $displayName,
                                            'parent_email' => $parentEmail,
                                            'app_url' => \App\Support\Env::get('APP_URL', 'http://localhost'),
                                            'verification_url' => \App\Support\Env::get('APP_URL', 'http://localhost') . '/login.php',
                                        ]
                                    );
                                }
                            }
                        } catch (Throwable) {
                            // Non-fatal email delivery logging
                        }

                        // 4. Establish Session
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['user_role'] = $newRole;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_name'] = $displayName;

                        header("Location: {$redirectUrl}");
                        exit;
                }
            } catch (Throwable $e) {
                $errorMessage = 'Registration error: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = Csrf::generateToken();

$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare('SELECT id, display_name, email, role FROM `users` WHERE `id` = :id');
    $stmtUser->execute([':id' => $_SESSION['user_id']]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: null;
}

View::render(
    'register',
    [
        'csrfToken' => $csrfToken,
        'errorMessage' => $errorMessage,
        'accountType' => $accountType,
        'currentUser' => $currentUser,
    ],
    'Create an Account — AppTutors UK',
    'Register as a parent or student, or apply to join our verified roster of professional UK educators.'
);
