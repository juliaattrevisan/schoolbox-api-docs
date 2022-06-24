
CREATE TABLE `sis_event_user` (
  `event_id` INT NOT NULL,
  `external_id` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `external_id` (`external_id` ASC)
  )
  COLLATE=utf8mb4_unicode_ci;
