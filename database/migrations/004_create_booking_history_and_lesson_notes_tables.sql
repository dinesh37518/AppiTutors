-- Migration 004: Create booking_status_history and lesson_notes tables

CREATE TABLE IF NOT EXISTS `booking_status_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `changed_by_user_id` BIGINT UNSIGNED NULL,
    `old_status` VARCHAR(40) NULL,
    `new_status` VARCHAR(40) NOT NULL,
    `reason` TEXT NULL,
    `metadata_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bsh_booking_created` (`booking_id`, `created_at`),
    CONSTRAINT `fk_bsh_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bsh_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lesson_notes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `tutor_user_id` BIGINT UNSIGNED NOT NULL,
    `notes` TEXT NOT NULL,
    `visibility` ENUM('INTERNAL', 'PARENT_VISIBLE') NOT NULL DEFAULT 'INTERNAL',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_notes_booking` (`booking_id`),
    CONSTRAINT `fk_notes_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_notes_tutor` FOREIGN KEY (`tutor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
