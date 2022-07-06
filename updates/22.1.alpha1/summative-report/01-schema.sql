-- base table for summative report

CREATE TABLE `summative_report` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `introduction` LONGTEXT NULL,
  `logo_hash` VARCHAR(100) NULL,
  `options` LONGTEXT NULL,
  `updated_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

-- remove old reportexport settings
-- these are now saved in each summative report's settings

DELETE FROM `config`
WHERE `name` IN (
    'report_school_logo',
    'report_export_introduction',
    'report_title'
);
