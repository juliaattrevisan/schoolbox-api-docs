CREATE TABLE `sis_event` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `detail` TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `location` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `code` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `day` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `period` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `start_at` DATETIME NOT NULL,
  `finish_at` DATETIME NOT NULL,
  `type` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `classification` ENUM('event', 'timetable') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'event',
  `guid` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `source` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  INDEX `code` (`code` ASC),
  INDEX `source` (`source` ASC),
  PRIMARY KEY (`id`))
  COLLATE=utf8mb4_unicode_ci
  ;

CREATE TABLE `sis_event_user_overrides` (
  `event_id` INT NOT NULL,
  `external_id` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
  `action` ENUM('clear', 'add', 'subtract') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT 'add',
  `sequence` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  INDEX `fk_sis_event_user_overrides_idx` (`event_id` ASC),
    CONSTRAINT `fk_sis_event_user_overrides`
      FOREIGN KEY (`event_id`)
      REFERENCES `sis_event` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION
  )
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sis_event_roletype` (
  `event_id` INT NOT NULL,
  `roletype` ENUM('staff', 'student', 'parent') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  INDEX `fk_sis_event_roletype_idx` (`event_id` ASC),
    CONSTRAINT `fk_sis_event_roletype`
      FOREIGN KEY (`event_id`)
      REFERENCES `sis_event` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION
  )
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sis_event_campus` (
  `event_id` INT NOT NULL,
  `campus_name` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  INDEX `fk_sis_event_campus_idx` (`event_id` ASC),
  CONSTRAINT `fk_sis_event_campus`
      FOREIGN KEY (`event_id`)
      REFERENCES `sis_event` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION
  )
  COLLATE=utf8mb4_unicode_ci;
