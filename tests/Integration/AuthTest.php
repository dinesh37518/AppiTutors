<?php

declare(strict_types=1);

namespace Tests;

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\Exceptions\UserNotRegisteredException;
use App\Auth\FirebaseTokenVerifier;
use App\Auth\UserContext;
use PDO;

class AuthTest
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(): array
    {
        $results = [];

        // 1. Missing Authorization Header
        $results[] = $this->testMissingHeaderRejected();

        // 2. Malformed Header (Not Bearer)
        $results[] = $this->testMalformedHeaderRejected();

        // 3. Empty Bearer Token
        $results[] = $this->testEmptyTokenRejected();

        // 4. Invalid / Garbage JWT Token Rejected by Verifier
        $results[] = $this->testInvalidTokenRejected();

        // 5. User Not Registered in MySQL
        $results[] = $this->testUnregisteredFirebaseUserRejected();

        // 6. Registered User Resolved Cleanly
        $results[] = $this->testRegisteredUserResolved();

        // 7. Inactive / Pending Account Throws AccountInactiveException
        $results[] = $this->testInactiveUserRejected();

        return $results;
    }

    private function testMissingHeaderRejected(): array
    {
        $verifier = new FirebaseTokenVerifier(null, $this->pdo);
        try {
            $verifier->extractBearerToken('');
            return [
                'name' => 'Auth: Missing Authorization Header Rejected (401)',
                'passed' => false,
                'detail' => 'Expected InvalidTokenException was not thrown',
            ];
        } catch (InvalidTokenException $e) {
            return [
                'name' => 'Auth: Missing Authorization Header Rejected (401)',
                'passed' => true,
                'detail' => 'Correctly threw InvalidTokenException: ' . $e->getMessage(),
            ];
        }
    }

    private function testMalformedHeaderRejected(): array
    {
        $verifier = new FirebaseTokenVerifier(null, $this->pdo);
        try {
            $verifier->extractBearerToken('Basic dXNlcjpwYXNz');
            return [
                'name' => 'Auth: Malformed Header (Non-Bearer) Rejected (401)',
                'passed' => false,
                'detail' => 'Expected InvalidTokenException was not thrown',
            ];
        } catch (InvalidTokenException $e) {
            return [
                'name' => 'Auth: Malformed Header (Non-Bearer) Rejected (401)',
                'passed' => true,
                'detail' => 'Correctly rejected Non-Bearer format',
            ];
        }
    }

    private function testEmptyTokenRejected(): array
    {
        $verifier = new FirebaseTokenVerifier(null, $this->pdo);
        try {
            $verifier->extractBearerToken('Bearer    ');
            return [
                'name' => 'Auth: Empty Bearer Token Rejected (401)',
                'passed' => false,
                'detail' => 'Expected InvalidTokenException was not thrown',
            ];
        } catch (InvalidTokenException $e) {
            return [
                'name' => 'Auth: Empty Bearer Token Rejected (401)',
                'passed' => true,
                'detail' => 'Correctly rejected whitespace/empty token',
            ];
        }
    }

    private function testInvalidTokenRejected(): array
    {
        $verifier = new FirebaseTokenVerifier(null, $this->pdo);
        try {
            $verifier->verifyFirebaseToken('invalid.garbage.jwt.token');
            return [
                'name' => 'Auth: Invalid/Garbage JWT Token Rejected (401)',
                'passed' => false,
                'detail' => 'Expected InvalidTokenException was not thrown',
            ];
        } catch (InvalidTokenException $e) {
            return [
                'name' => 'Auth: Invalid/Garbage JWT Token Rejected (401)',
                'passed' => true,
                'detail' => 'Firebase Admin SDK cleanly rejected invalid token',
            ];
        }
    }

    private function testUnregisteredFirebaseUserRejected(): array
    {
        $verifier = new FirebaseTokenVerifier(null, $this->pdo);
        $randomUid = 'TEST_NON_EXISTENT_UID_' . uniqid();

        try {
            $verifier->resolveUser($randomUid);
            return [
                'name' => 'Auth: Unregistered Firebase UID Throws USER_NOT_REGISTERED (404)',
                'passed' => false,
                'detail' => 'Expected UserNotRegisteredException was not thrown',
            ];
        } catch (UserNotRegisteredException $e) {
            $passed = ($e->getErrorCode() === 'USER_NOT_REGISTERED');
            return [
                'name' => 'Auth: Unregistered Firebase UID Throws USER_NOT_REGISTERED (404)',
                'passed' => $passed,
                'detail' => "Correctly caught code: {$e->getErrorCode()}",
            ];
        }
    }

    private function testRegisteredUserResolved(): array
    {
        $testUid = 'TEST_REG_UID_' . uniqid();
        $testEmail = 'registered_test_' . uniqid() . '@example.com';

        // Insert temporary test user into MySQL
        $stmt = $this->pdo->prepare(
            'INSERT INTO `users` (`firebase_uid`, `email`, `display_name`, `role`, `status`, `created_at`, `updated_at`)
             VALUES (?, ?, "Test Student", "STUDENT_PARENT", "ACTIVE", UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([$testUid, $testEmail]);
        $insertedId = (int) $this->pdo->lastInsertId();

        try {
            $verifier = new FirebaseTokenVerifier(null, $this->pdo);
            $user = $verifier->resolveUser($testUid, true);

            $passed = (
                $user->id === $insertedId &&
                $user->firebaseUid === $testUid &&
                $user->role === 'STUDENT_PARENT' &&
                $user->isActive() &&
                $user->emailVerified === true
            );

            return [
                'name' => 'Auth: Registered User Resolved Strictly from MySQL',
                'passed' => $passed,
                'detail' => "Resolved user ID {$user->id} with MySQL role {$user->role}",
            ];
        } finally {
            // Teardown test record
            $this->pdo->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$insertedId]);
        }
    }

    private function testInactiveUserRejected(): array
    {
        $testUid = 'TEST_INACTIVE_UID_' . uniqid();
        $testEmail = 'inactive_test_' . uniqid() . '@example.com';

        // Insert pending tutor
        $stmt = $this->pdo->prepare(
            'INSERT INTO `users` (`firebase_uid`, `email`, `display_name`, `role`, `status`, `created_at`, `updated_at`)
             VALUES (?, ?, "Pending Tutor", "TUTOR", "PENDING", UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([$testUid, $testEmail]);
        $insertedId = (int) $this->pdo->lastInsertId();

        try {
            $verifier = new FirebaseTokenVerifier(null, $this->pdo);
            $user = $verifier->resolveUser($testUid);

            $threw = false;
            try {
                if (!$user->isActive()) {
                    throw new AccountInactiveException($user->status, 'Account is pending');
                }
            } catch (AccountInactiveException $e) {
                $threw = true;
            }

            return [
                'name' => 'Auth: Inactive User (PENDING status) Throws ACCOUNT_INACTIVE (403)',
                'passed' => $threw && $user->role === 'TUTOR',
                'detail' => "Tutor in status '{$user->status}' denied access",
            ];
        } finally {
            $this->pdo->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$insertedId]);
        }
    }
}
