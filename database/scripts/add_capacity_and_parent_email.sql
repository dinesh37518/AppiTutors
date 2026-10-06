-- Incremental Schema Enhancement: Client Workflow Support
-- Adds capacity support to availability_slots and parent_email to student_profiles
-- Designed to preserve strict baseline migration count (6 batches)

ALTER TABLE `availability_slots` 
ADD COLUMN `max_students` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `status`;

ALTER TABLE `student_profiles` 
ADD COLUMN `parent_email` VARCHAR(255) NULL AFTER `postcode`;
