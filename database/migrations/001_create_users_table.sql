-- Migration 001: Create users table
-- Authoritative application identity and RBAC role mapping

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `firebase_uid` VARCHAR(128) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `display_name` VARCHAR(150) NOT NULL,
    `role` ENUM('STUDENT_PARENT', 'TUTOR', 'MANAGER') NOT NULL,
    `status` ENUM('ACTIVE', 'PENDING', 'SUSPENDED', 'DELETED') NOT NULL DEFAULT 'PENDING',
    `email_verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
