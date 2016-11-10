-- Add new Columns
-- * student_submission_id - FK to specific Submission, or NULL if floating feedback
-- * created_at - renamed 'returned' field
-- * updated_at - copied from old 'returned' field
-- * published_at - copied from old 'returned' field

ALTER TABLE `submission_return`
  ADD `student_submission_id` INT(5) NULL DEFAULT NULL AFTER `owner`,
  CHANGE COLUMN `returned` `created_at` DATETIME NOT NULL AFTER `is_confirmation_of_submission`,
  ADD `published_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
  ADD `updated_at` DATETIME NOT NULL AFTER `published_at`
;

-- ReturnedFile file table
CREATE TABLE `submission_return_file` (
  `id`                         INT(11) NOT NULL AUTO_INCREMENT,
  `submission_return_id`       INT(11) NOT NULL,
  `hash`                       VARCHAR(40) NULL DEFAULT NULL,
  `realname`                   VARCHAR(255) NOT NULL DEFAULT '',
  `uuid`                       VARCHAR(255) DEFAULT NULL,
  `created_at`                 DATETIME NOT NULL,
  `updated_at`                 DATETIME NOT NULL,
  `deleted_at`                 DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_submission_return_file_hash`
  FOREIGN KEY (`hash`)
  REFERENCES `storage` (`hash`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
)
  ENGINE = InnoDB DEFAULT CHARSET=utf8;


-- Populate data
-- Unlike StudentSubmissions, textArea comments are saved in the 'comment' field, not as a text/html hash
-- Deleted if the SubmissionReturn they belong to is deleted (ie not checking the Assessment or DueWork tables)
INSERT INTO `submission_return_file` (
  `submission_return_id`,
  `hash`,
  `realname`,
  `uuid`,
  `created_at`,
  `updated_at`,
  `deleted_at`
)
  SELECT
    `sr`.`id`,
    `sr`.`hash`,
    (IF(`sr`.`filename` IS NOT NULL, `sr`.`filename`, `ssf`.`realname`)),
    `sr`.`uuid`,
    `sr`.`created_at`,
    `sr`.`created_at`,
    `sr`.`deleted_at`
  FROM `submission_return` `sr`
    LEFT JOIN `student_submission_file` `ssf` ON  `sr`.`uuid` = `ssf`.`uuid`
  WHERE `sr`.`hash` IS NOT NULL OR `sr`.`uuid` IS NOT NULL;
