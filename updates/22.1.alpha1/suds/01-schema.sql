
CREATE TABLE `sis_event_user` (
  `event_id` INT NOT NULL,
  `external_id` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `external_id` (`external_id` ASC)
  )
  COLLATE=utf8mb4_unicode_ci;

-- Deconflict with Class User Overrides,
-- which is the purpose of this table (despite its naming)

ALTER TABLE `sis_event_user_overrides`
RENAME TO `sis_class_user_overrides` ;

-- Rename index

ALTER TABLE `sis_class_user_overrides` RENAME INDEX `fk_sis_event_user_overrides_idx` TO `fk_sis_class_user_overrides_idx`;

-- Rename fk

ALTER TABLE `sis_class_user_overrides`
DROP FOREIGN KEY `fk_sis_event_user_overrides`;
ALTER TABLE `sis_class_user_overrides`
ADD CONSTRAINT `fk_sis_class_user_overrides`
  FOREIGN KEY (`event_id`)
  REFERENCES `sis_event` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- Add preferred_name
ALTER TABLE `sis_user_profile`
ADD COLUMN `preferred_firstname` VARCHAR(255) NULL DEFAULT NULL AFTER `firstname`;
