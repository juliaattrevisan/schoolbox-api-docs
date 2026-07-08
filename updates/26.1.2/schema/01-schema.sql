-- Fix column charset/collations too
-- These were found in teraspcs

ALTER TABLE `ib_group_subjects` COLLATE=utf8mb4_unicode_ci,
CHANGE COLUMN `level` `level` enum('HL','SL','EE','TK','MY') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;

ALTER TABLE `lu_ib_groups` COLLATE=utf8mb4_unicode_ci,
CHANGE COLUMN `name` `name` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL;

ALTER TABLE `lu_ib_subjects` COLLATE=utf8mb4_unicode_ci,
CHANGE COLUMN `name` `name` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
CHANGE COLUMN `transcript_name` `transcript_name` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;

ALTER TABLE `lu_qualification` COLLATE=utf8mb4_unicode_ci,
CHANGE COLUMN `name` `name` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL;

-- for surety

ALTER TABLE `user_qualification` COLLATE=utf8mb4_unicode_ci;


