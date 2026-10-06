-- Rollback Script for Incremental Schema Enhancement
ALTER TABLE `availability_slots` DROP COLUMN `max_students`;
ALTER TABLE `student_profiles` DROP COLUMN `parent_email`;
