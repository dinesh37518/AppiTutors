-- Migration 003: Create availability_slots and bookings tables

CREATE TABLE IF NOT EXISTS `availability_slots` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tutor_user_id` BIGINT UNSIGNED NOT NULL,
    `starts_at_utc` DATETIME NOT NULL,
    `ends_at_utc` DATETIME NOT NULL,
    `status` ENUM('DRAFT', 'PUBLISHED', 'BLOCKED', 'BOOKED', 'EXPIRED') NOT NULL DEFAULT 'DRAFT',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slots_tutor_start` (`tutor_user_id`, `starts_at_utc`),
    INDEX `idx_slots_status` (`status`),
    CONSTRAINT `fk_slots_tutor` FOREIGN KEY (`tutor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_slot_times` CHECK (`ends_at_utc` > `starts_at_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_user_id` BIGINT UNSIGNED NOT NULL,
    `child_id` BIGINT UNSIGNED NULL,
    `tutor_user_id` BIGINT UNSIGNED NOT NULL,
    `slot_id` BIGINT UNSIGNED NULL,
    `status` ENUM('PENDING', 'CONFIRMED', 'REJECTED', 'RESCHEDULE_PROPOSED', 'CANCELLED', 'SYSTEM_CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'PENDING',
    `inquiry_notes` TEXT NULL,
    `proposed_starts_at_utc` DATETIME NULL,
    `proposed_ends_at_utc` DATETIME NULL,
    `confirmed_starts_at_utc` DATETIME NULL,
    `confirmed_ends_at_utc` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_bookings_tutor_status` (`tutor_user_id`, `status`),
    INDEX `idx_bookings_student_status` (`student_user_id`, `status`),
    CONSTRAINT `fk_bookings_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bookings_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bookings_tutor` FOREIGN KEY (`tutor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bookings_slot` FOREIGN KEY (`slot_id`) REFERENCES `availability_slots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
