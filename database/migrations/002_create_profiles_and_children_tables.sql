-- Migration 002: Create tutor_profiles, student_profiles, and children tables

CREATE TABLE IF NOT EXISTS `tutor_profiles` (
    `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `headline` VARCHAR(255) NULL,
    `bio` TEXT NULL,
    `subjects_json` JSON NULL,
    `curriculum_json` JSON NULL,
    `qualifications` TEXT NULL,
    `dbs_status` ENUM('NOT_SUBMITTED', 'SUBMITTED', 'VERIFIED', 'REJECTED', 'EXPIRED') NOT NULL DEFAULT 'NOT_SUBMITTED',
    `approval_status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED') NOT NULL DEFAULT 'PENDING',
    `approved_at` DATETIME NULL,
    `approved_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tutors_approval_dbs` (`approval_status`, `dbs_status`),
    CONSTRAINT `fk_tutor_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tutor_profiles_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_profiles` (
    `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `phone` VARCHAR(40) NULL,
    `postcode` VARCHAR(20) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_student_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `children` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_user_id` BIGINT UNSIGNED NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NULL,
    `date_of_birth` DATE NULL,
    `school_year` VARCHAR(50) NULL,
    `curriculum` VARCHAR(100) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_children_parent` (`parent_user_id`),
    CONSTRAINT `fk_children_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
