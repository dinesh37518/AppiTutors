<?php

declare(strict_types=1);

namespace App\Auth;

class UserContext
{
    public function __construct(
        public readonly int $id,
        public readonly string $firebaseUid,
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $role,
        public readonly string $status,
        public readonly bool $emailVerified = false
    ) {}

    public static function fromDatabaseRow(array $row, bool $emailVerified = false): self
    {
        return new self(
            id: (int) $row['id'],
            firebaseUid: (string) $row['firebase_uid'],
            email: (string) $row['email'],
            displayName: (string) $row['display_name'],
            role: (string) $row['role'],
            status: (string) $row['status'],
            emailVerified: $emailVerified
        );
    }

    public function isManager(): bool
    {
        return $this->role === 'MANAGER';
    }

    public function isTutor(): bool
    {
        return $this->role === 'TUTOR';
    }

    public function isStudentParent(): bool
    {
        return $this->role === 'STUDENT_PARENT';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firebase_uid' => $this->firebaseUid,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'role' => $this->role,
            'status' => $this->status,
            'email_verified' => $this->emailVerified,
        ];
    }
}
