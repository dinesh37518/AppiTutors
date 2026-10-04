<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\AuditService;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use App\Support\Timezone;
use App\Validation\Validator;

$logger = new Logger();
$audit = new AuditService();

try {
    RateLimiter::enforce('register_tutor', null, 30, 60);

    if (Request::getMethod() !== 'POST') {
        Response::error('Method not allowed. Use POST.', 'METHOD_NOT_ALLOWED', 405);
    }

    // 1. Verify Firebase Bearer Token
    $verifier = new FirebaseTokenVerifier();
    $claims = $verifier->verifyFirebaseToken($verifier->extractBearerToken());
    $firebaseUid = $claims['uid'];
    $email = $claims['email'] ?? '';

    // 2. Parse request body
    $body = Request::getJsonBody();

    // CRITICAL: Discard any client-supplied authorization fields
    unset($body['role'], $body['status'], $body['approval_status'], $body['is_manager'], $body['is_admin']);

    $displayName = Validator::sanitizeString($body['display_name'] ?? '');
    if (empty($displayName)) {
        Response::error('Display name is required.', 'VALIDATION_ERROR', 422, ['display_name' => 'Required']);
    }

    $headline = isset($body['headline']) ? Validator::sanitizeString($body['headline']) : null;
    $bio = isset($body['bio']) ? Validator::sanitizeString($body['bio']) : null;
    $qualifications = isset($body['qualifications']) ? Validator::sanitizeString($body['qualifications']) : null;

    $subjectsJson = isset($body['subjects']) && is_array($body['subjects'])
        ? json_encode(array_values(array_map('strval', $body['subjects'])))
        : null;

    $curriculumJson = isset($body['curriculum']) && is_array($body['curriculum'])
        ? json_encode(array_values(array_map('strval', $body['curriculum'])))
        : null;

    $pdo = Database::getConnection();

    // 3. Check if user already registered in MySQL
    $stmtCheck = $pdo->prepare('SELECT id, role FROM `users` WHERE `firebase_uid` = ? OR `email` = ? LIMIT 1');
    $stmtCheck->execute([$firebaseUid, $email]);
    if ($stmtCheck->fetch()) {
        Response::error('User account already registered.', 'USER_ALREADY_EXISTS', 409);
    }

    // 4. Atomic Registration Transaction (Role is strictly TUTOR with PENDING status)
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare(
        'INSERT INTO `users` (`firebase_uid`, `email`, `display_name`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`)
         VALUES (?, ?, ?, "TUTOR", "PENDING", ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $verifiedAt = $claims['email_verified'] ? Timezone::nowUtc() : null;
    $stmtUser->execute([$firebaseUid, $email, $displayName, $verifiedAt]);
    $userId = (int) $pdo->lastInsertId();

    $stmtProfile = $pdo->prepare(
        'INSERT INTO `tutor_profiles` 
         (`user_id`, `headline`, `bio`, `subjects_json`, `curriculum_json`, `qualifications`, `dbs_status`, `approval_status`, `created_at`, `updated_at`)
         VALUES (?, ?, ?, ?, ?, ?, "NOT_SUBMITTED", "PENDING", UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $stmtProfile->execute([$userId, $headline, $bio, $subjectsJson, $curriculumJson, $qualifications]);

    $pdo->commit();

    // 5. Audit Logging
    $logger->info("Tutor registered in PENDING status: {$email} (User ID: {$userId})");
    $audit->log(
        action: 'USER_REGISTERED_TUTOR',
        entityType: 'user',
        entityId: $userId,
        actorUserId: $userId,
        metadata: ['role' => 'TUTOR', 'status' => 'PENDING', 'approval_status' => 'PENDING']
    );

    Response::success([
        'user' => [
            'id' => $userId,
            'firebase_uid' => $firebaseUid,
            'email' => $email,
            'display_name' => $displayName,
            'role' => 'TUTOR',
            'status' => 'PENDING',
            'approval_status' => 'PENDING',
        ],
    ], 201);

} catch (InvalidTokenException $e) {
    Response::error($e->getMessage(), 'INVALID_TOKEN', 401);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $logger->error('Tutor registration error: ' . $e->getMessage());
    Response::error('Registration could not be completed.', 'REGISTRATION_FAILED', 500);
}
