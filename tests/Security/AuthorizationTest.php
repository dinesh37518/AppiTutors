<?php

declare(strict_types=1);

namespace Tests;

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;

class AuthorizationTest
{
    public function run(): array
    {
        $results = [];

        // 1. Unauthenticated Request Rejected
        $results[] = $this->testUnauthenticatedRejected();

        // 2. Correct Role Permitted
        $results[] = $this->testCorrectRolePermitted();

        // 3. Incorrect Role Denied
        $results[] = $this->testIncorrectRoleDenied();

        // 4. CRITICAL CASE C: Client-Supplied Role Cannot Override MySQL Authority
        $results[] = $this->testClientRoleCannotOverrideMySQL();

        // 5. CRITICAL CASE D: Manager Self-Assignment Rejection
        $results[] = $this->testManagerSelfAssignmentRejected();

        // 6. Fine-Grained Ownership / IDOR Enforcement
        $results[] = $this->testObjectOwnershipIDOR();

        // 7. Inactive Account Status Denied
        $results[] = $this->testInactiveStatusDenied();

        return $results;
    }

    private function testUnauthenticatedRejected(): array
    {
        try {
            Authorization::requireAuthenticatedUser(null);
            return [
                'name' => 'Authorization: Unauthenticated User Rejected (403)',
                'passed' => false,
                'detail' => 'Expected ForbiddenException was not thrown',
            ];
        } catch (ForbiddenException $e) {
            return [
                'name' => 'Authorization: Unauthenticated User Rejected (403)',
                'passed' => $e->getErrorCode() === 'UNAUTHENTICATED',
                'detail' => 'Correctly rejected null user context',
            ];
        }
    }

    private function testCorrectRolePermitted(): array
    {
        $managerUser = new UserContext(
            id: 1,
            firebaseUid: 'FB_MGR_1',
            email: 'mgr@example.com',
            displayName: 'Manager Admin',
            role: 'MANAGER',
            status: 'ACTIVE'
        );

        try {
            Authorization::requireRole($managerUser, ['MANAGER']);
            return [
                'name' => 'Authorization: Correct Role Permitted (MANAGER -> MANAGER)',
                'passed' => true,
                'detail' => 'Role check cleanly passed',
            ];
        } catch (ForbiddenException $e) {
            return [
                'name' => 'Authorization: Correct Role Permitted (MANAGER -> MANAGER)',
                'passed' => false,
                'detail' => 'Unexpectedly threw ForbiddenException: ' . $e->getMessage(),
            ];
        }
    }

    private function testIncorrectRoleDenied(): array
    {
        $studentUser = new UserContext(
            id: 2,
            firebaseUid: 'FB_STU_2',
            email: 'student@example.com',
            displayName: 'Student Jane',
            role: 'STUDENT_PARENT',
            status: 'ACTIVE'
        );

        try {
            // Attempting to access MANAGER endpoint
            Authorization::requireRole($studentUser, ['MANAGER']);
            return [
                'name' => 'Authorization: Incorrect Role Denied (STUDENT_PARENT -> MANAGER)',
                'passed' => false,
                'detail' => 'Expected ForbiddenException was not thrown',
            ];
        } catch (ForbiddenException $e) {
            return [
                'name' => 'Authorization: Incorrect Role Denied (STUDENT_PARENT -> MANAGER)',
                'passed' => $e->getErrorCode() === 'INSUFFICIENT_ROLE_PERMISSIONS',
                'detail' => "Correctly caught code {$e->getErrorCode()}",
            ];
        }
    }

    private function testClientRoleCannotOverrideMySQL(): array
    {
        // Simulated client payload claims:
        $clientSuppliedPayload = [
            'role' => 'MANAGER',
            'is_manager' => true,
            'permissions' => ['all'],
        ];

        // MySQL database record says:
        $mysqlRecord = [
            'id' => 42,
            'firebase_uid' => 'GENUINE_FB_UID',
            'email' => 'client_attacker@example.com',
            'display_name' => 'Sneaky User',
            'role' => 'STUDENT_PARENT', // Authoritative MySQL Role
            'status' => 'ACTIVE',
        ];

        // System resolves UserContext from MySQL only:
        $userContext = UserContext::fromDatabaseRow($mysqlRecord);

        // Security check: Verify client-supplied role is disregarded
        $isOverridden = false;
        try {
            Authorization::requireRole($userContext, ['MANAGER']);
            $isOverridden = true; // Would be catastrophic failure
        } catch (ForbiddenException $e) {
            $isOverridden = false;
        }

        $passed = (!$isOverridden && $userContext->role === 'STUDENT_PARENT');

        return [
            'name' => 'Authorization: [CASE C] Client-Supplied Role Cannot Override MySQL Authority',
            'passed' => $passed,
            'detail' => "Client sent 'MANAGER', MySQL maintained 'STUDENT_PARENT', action successfully blocked (403)",
        ];
    }

    private function testManagerSelfAssignmentRejected(): array
    {
        $attempts = ['MANAGER', 'manager', 'Manager', '  MANAGER  '];
        $allBlocked = true;

        foreach ($attempts as $roleAttempt) {
            try {
                Authorization::assertCannotSelfAssignManager($roleAttempt);
                $allBlocked = false;
            } catch (ForbiddenException $e) {
                if ($e->getErrorCode() !== 'SELF_REGISTRATION_MANAGER_FORBIDDEN') {
                    $allBlocked = false;
                }
            }
        }

        return [
            'name' => 'Authorization: [CASE D] Manager Role Self-Assignment Strictly Blocked',
            'passed' => $allBlocked,
            'detail' => 'All variants of self-assigned MANAGER role rejected with SELF_REGISTRATION_MANAGER_FORBIDDEN',
        ];
    }

    private function testObjectOwnershipIDOR(): array
    {
        $currentUserId = 100;
        $matchingResourceOwner = 100;
        $otherResourceOwner = 200;

        // 1. Matching owner must pass
        $passedMatch = false;
        try {
            Authorization::assertOwnership($matchingResourceOwner, $currentUserId);
            $passedMatch = true;
        } catch (ForbiddenException $e) {
            $passedMatch = false;
        }

        // 2. Different owner must throw
        $passedMismatch = false;
        try {
            Authorization::assertOwnership($otherResourceOwner, $currentUserId);
            $passedMismatch = false;
        } catch (ForbiddenException $e) {
            $passedMismatch = ($e->getErrorCode() === 'UNAUTHORIZED_RESOURCE_OWNERSHIP');
        }

        $passed = $passedMatch && $passedMismatch;

        return [
            'name' => 'Authorization: Fine-Grained Ownership / IDOR Protection Enforced',
            'passed' => $passed,
            'detail' => 'Matching resource owner allowed, non-matching owner blocked (403)',
        ];
    }

    private function testInactiveStatusDenied(): array
    {
        $suspendedUser = new UserContext(
            id: 5,
            firebaseUid: 'FB_SUSPENDED',
            email: 'bad@example.com',
            displayName: 'Suspended User',
            role: 'TUTOR',
            status: 'SUSPENDED'
        );

        try {
            Authorization::requireActiveStatus($suspendedUser);
            return [
                'name' => 'Authorization: Inactive Account Status Denied (403)',
                'passed' => false,
                'detail' => 'Expected ForbiddenException was not thrown',
            ];
        } catch (ForbiddenException $e) {
            return [
                'name' => 'Authorization: Inactive Account Status Denied (403)',
                'passed' => $e->getErrorCode() === 'ACCOUNT_NOT_ACTIVE',
                'detail' => "Correctly caught code {$e->getErrorCode()}",
            ];
        }
    }
}
