CREATE TABLE `sis_event` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `detail` TEXT NULL,
  `code` VARCHAR(255) NULL,
  `day` VARCHAR(255) NULL,
  `period` VARCHAR(255) NULL,
  `start_at` DATETIME NOT NULL,
  `finish_at` DATETIME NOT NULL,
  `type` VARCHAR(45) NULL,
  `classification` ENUM('event', 'timetable') NOT NULL DEFAULT 'event',
  `guid` VARCHAR(255) NULL,
  `source` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`));


CREATE TABLE `sis_event_user_overrides` (
  `event_id` INT NOT NULL,
  `external_id` VARCHAR(255) NULL,
  `action` ENUM('clear', 'add', 'subtract') NULL DEFAULT 'add',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL);


CREATE TABLE `sis_event_roletype` (
  `event_id` INT NOT NULL,
  `roletype` ENUM('staff', 'student', 'parent') NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NOT NULL);

CREATE TABLE `sis_event_campus` (
  `event_id` INT NOT NULL,
  `campus_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL);

-- Alter the collation stuff to whatever it was supposed to be

ALTER TABLE `sis_event` COLLATE=utf8mb4_unicode_ci,
 CHANGE COLUMN `classification` `classification` enum('event','timetable') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
 CHANGE COLUMN `code` `code` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
 CHANGE COLUMN `day` `day` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
 CHANGE COLUMN `detail` `detail` text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
 CHANGE COLUMN `guid` `guid` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
 CHANGE COLUMN `period` `period` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
 CHANGE COLUMN `source` `source` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
 CHANGE COLUMN `title` `title` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
 CHANGE COLUMN `type` `type` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;

ALTER TABLE `sis_event_campus` COLLATE=utf8mb4_unicode_ci,
  CHANGE COLUMN `campus_name` `campus_name` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL;

ALTER TABLE `sis_event_user_overrides` COLLATE=utf8mb4_unicode_ci,
 CHANGE COLUMN `action` `action` enum('clear','add','subtract') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT 'add',
 CHANGE COLUMN `external_id` `external_id` varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;



