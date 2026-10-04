<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Auth\UserContext;
use App\Support\Response;

class Authorization
{
    public const ROLE_STUDENT_PARENT = 'STUDENT_PARENT';
    public const ROLE_TUTOR = 'TUTOR';
    public const ROLE_MANAGER = 'MANAGER';

    public const ALLOWED_ROLES = [
        self::ROLE_STUDENT_PARENT,
        self::ROLE_TUTOR,
        self::ROLE_MANAGER,
    ];

    /**
     * Enforce that a user is authenticated.
     *
     * @param UserContext|null $user
     * @throws ForbiddenException
     */
    public static function requireAuthenticatedUser(?UserContext $user): void
    {
        if ($user === null) {
            throw new ForbiddenException('Authentication required.', 'UNAUTHENTICATED');
        }
    }

    /**
     * Enforce that the authenticated user possesses one of the allowed application roles.
     *
     * @param UserContext $user
     * @param array<string> $allowedRoles
     * @throws ForbiddenException
     */
    public static function requireRole(UserContext $user, array $allowedRoles): void
    {
        if (!in_array($user->role, $allowedRoles, true)) {
            throw new ForbiddenException(
                "Access denied. User role '{$user->role}' is not permitted to perform this action.",
                'INSUFFICIENT_ROLE_PERMISSIONS'
            );
        }
    }

    /**
     * Enforce fine-grained resource ownership to prevent IDOR vulnerabilities.
     *
     * @param int $resourceOwnerId
     * @param int $currentUserId
     * @param string $message
     * @throws ForbiddenException
     */
    public static function assertOwnership(int $resourceOwnerId, int $currentUserId, string $message = 'Access denied to this resource.'): void
    {
        if ($resourceOwnerId !== $currentUserId) {
            throw new ForbiddenException($message, 'UNAUTHORIZED_RESOURCE_OWNERSHIP');
        }
    }

    /**
     * Enforce that a user account has ACTIVE status.
     *
     * @param UserContext $user
     * @throws ForbiddenException
     */
    public static function requireActiveStatus(UserContext $user): void
    {
        if (!$user->isActive()) {
            throw new ForbiddenException(
                "Access denied. User account is currently {$user->status}.",
                'ACCOUNT_NOT_ACTIVE'
            );
        }
    }

    /**
     * Critical Security Guard: Ensure MANAGER role can never be self-assigned during registration.
     *
     * @param string $requestedRole
     * @throws ForbiddenException
     */
    public static function assertCannotSelfAssignManager(string $requestedRole): void
    {
        if (strtoupper(trim($requestedRole)) === self::ROLE_MANAGER) {
            throw new ForbiddenException(
                'Manager accounts cannot be self-registered. Provisioning requires administrative authorization.',
                'SELF_REGISTRATION_MANAGER_FORBIDDEN'
            );
        }
    }
}
