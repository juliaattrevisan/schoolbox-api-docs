-- Remember when SOCS imports ran

CREATE TABLE `socs_import_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `run_id` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `script` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `start_at` DATETIME NOT NULL,
  `finish_at` DATETIME DEFAULT NULL,
  `status` ENUM('running', 'complete', 'error') CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `error_message` TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

ALTER TABLE `socs_import_log`
    ADD INDEX `finish_at_idx` (`finish_at` ASC, `status` ASC);
