-- Reorder these columns
-- Extensions contain some similarities that should appear in a predictable place
ALTER TABLE `sis_user_profile_parent`
    CHANGE COLUMN `username` `username` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `sis_user_profile_id`;

ALTER TABLE `sis_user_profile_parent`
    CHANGE COLUMN `email` `email` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `username`;

ALTER TABLE `sis_user_profile_student`
    CHANGE COLUMN `username` `username` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `sis_user_profile_id`;

ALTER TABLE `sis_user_profile_student`
    CHANGE COLUMN `email` `email` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `username`;

ALTER TABLE `sis_user_profile_student`
    CHANGE COLUMN `campus_name` `campus_name` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `gender`,
    CHANGE COLUMN `mobile` `mobile` VARCHAR(45) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `campus_name`;

ALTER TABLE `sis_user_profile_staff`
    CHANGE COLUMN `campus_name` `campus_name` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `email`,
    CHANGE COLUMN `phone` `phone` VARCHAR(45) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `campus_name`,
    CHANGE COLUMN `building` `building` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `department`;

ALTER TABLE `sis_user_profile_student`
    CHANGE COLUMN `campus_name` `campus_name` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `emergency_contact_phone_alt_3`,
    CHANGE COLUMN `house_name` `house_name` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL AFTER `campus_name`;
