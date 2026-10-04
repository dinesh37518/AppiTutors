<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\Exceptions\UserNotRegisteredException;
use App\Auth\FirebaseTokenVerifier;
use App\Logging\Logger;
use App\Services\AuditService;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use App\Support\Timezone;

$logger = new Logger();
$audit = new AuditService();

try {
    RateLimiter::enforce('auth', null, 60, 60);

    // 1. Enforce POST or GET method
    $method = Request::getMethod();
    if (!in_array($method, ['GET', 'POST'], true)) {
        Response::error('Method not allowed. Use GET or POST.', 'METHOD_NOT_ALLOWED', 405);
    }

    // 2. Instantiate Verifier (Zero Trust: Client input ignored)
    $verifier = new FirebaseTokenVerifier();

    // 3. Authenticate Bearer token & Resolve MySQL authority
    $user = $verifier->authenticateRequest();

    // 4. Record successful authentication event in security log & audit
    $logger->security("User authenticated: {$user->email} (UID: {$user->firebaseUid}, Role: {$user->role})");
    $audit->log(
        action: 'USER_SESSION_AUTHENTICATED',
        entityType: 'user',
        entityId: $user->id,
        actorUserId: $user->id,
        metadata: ['role' => $user->role, 'email_verified' => $user->emailVerified]
    );

    // 5. Return authoritative user context derived SOLELY from MySQL
    Response::success([
        'user' => $user->toArray(),
    ], 200, ['timestamp' => Timezone::nowUtc()]);

} catch (UserNotRegisteredException $e) {
    // Firebase identity verified, but no MySQL platform account exists
    $logger->security("Unregistered Firebase UID attempted access: {$e->getFirebaseUid()}");
    Response::error(
        $e->getMessage(),
        'USER_NOT_REGISTERED',
        404,
        ['firebase_uid' => $e->getFirebaseUid()]
    );
} catch (AccountInactiveException $e) {
    $logger->security("Inactive user account attempted access: {$e->getMessage()}");
    Response::error(
        $e->getMessage(),
        'ACCOUNT_INACTIVE',
        403,
        ['account_status' => $e->getAccountStatus()]
    );
} catch (InvalidTokenException $e) {
    $logger->security("Authentication rejected: {$e->getMessage()}");
    Response::error($e->getMessage(), 'INVALID_TOKEN', 401);
} catch (AuthenticationException $e) {
    $logger->security("Authentication error: {$e->getMessage()}");
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/auth.php: ' . $e->getMessage());
    Response::error('An unexpected authentication error occurred.', 'INTERNAL_AUTH_ERROR', 500);
}
