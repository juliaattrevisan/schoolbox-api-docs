-- Remember when imports ran

CREATE TABLE `clipboard_import_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `run_id` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `script` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `start_at` DATETIME NOT NULL,
  `finish_at` DATETIME DEFAULT NULL,
  `status` ENUM('running', 'complete', 'error') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `error_message` TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`))
    DEFAULT CHARACTER SET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

-- Record external ids (clipboard ids) of stuff that could be renamed

ALTER TABLE `sportsfixtures_sport`
    ADD COLUMN `external_id` VARCHAR(255) NULL DEFAULT NULL AFTER `url`;

ALTER TABLE `sportsfixtures_division`
    ADD COLUMN `external_id` VARCHAR(255) NULL DEFAULT NULL AFTER `url`;

ALTER TABLE `sportsfixtures_team`
    ADD COLUMN `external_id` VARCHAR(255) NULL DEFAULT NULL AFTER `url`;

ALTER TABLE `sportsfixtures_position`
    ADD COLUMN `external_id` VARCHAR(255) NULL DEFAULT NULL AFTER `name`;
