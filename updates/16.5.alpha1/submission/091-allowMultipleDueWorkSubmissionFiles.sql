-- As God intended:
ALTER TABLE `student_submission` ENGINE=INNODB;
ALTER TABLE `storage` ENGINE=INNODB;

-- Add missing CRUD timestamps
ALTER TABLE `student_submission`
    ADD `created_at` DATETIME NOT NULL AFTER `uuid`,
    ADD `updated_at` DATETIME NOT NULL AFTER `created_at`,
    CHANGE COLUMN `submitted` `submitted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD `deleted_at` DATETIME NULL DEFAULT NULL AFTER `submitted_at`
;

-- Submission file table
CREATE TABLE `student_submission_file` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_submission_id` INT (5) UNSIGNED NOT NULL,
    `hash` varchar(40) NOT NULL,
    `realname` varchar(255) NOT NULL DEFAULT '',
    `editable_inline` BOOL DEFAULT FALSE,
    `uuid` varchar(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `student_submission_file_student_submission` (`student_submission_id` ASC),
    CONSTRAINT `fk_student_submission_file_student_submission`
    FOREIGN KEY (`student_submission_id`)
    REFERENCES `student_submission` (`id`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX `student_submission_file_hash` (`hash` ASC),
    CONSTRAINT `fk_student_submission_file_hash`
    FOREIGN KEY (`hash`)
    REFERENCES `storage` (`hash`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
    )
    ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- Populate data
-- Heuristic for whether a file is editable inline:
-- * name is "Online Assessment" or "online-assessment"
-- * type is "text/html"
-- Deleted if the parent Assessment is deleted
INSERT INTO `student_submission_file` (
    `student_submission_id`,
    `hash`,
    `realname`,
    `editable_inline`,
    `uuid`,
    `created_at`,
    `updated_at`,
    `deleted_at`
)
SELECT
    `ss`.`id`,
    `ss`.`hash`,
    `ss`.`realname`,
    (IF(`aft`.`mime` = 'text/html' AND `ss`.`realname` IN ('Online Submission', 'online-submission'), TRUE, FALSE)),
    `ss`.`uuid`,
    `ss`.`submitted_at`,
    `ss`.`submitted_at`,
    `a`.`deleted_at`
FROM `student_submission` `ss`
INNER JOIN `storage` `st` ON `ss`.`hash` = `st`.`hash`
LEFT JOIN `allowedFileExtension` `afe` ON `st`.`ext` = `afe`.`extension`
LEFT JOIN `allowedFileTypes` `aft` ON `afe`.`fileTypeID` =`aft`.`fileTypeID`
JOIN `submission_box` `sb` ON `ss`.`box_id` = `sb`.`id`
JOIN `assessment` `a` ON `sb`.`assessment_id` = `a`.`id`;

-- Rename column in preparation of migration
-- Plagscan will now need to link to the new 'student_submission_file' table
ALTER TABLE `plagscan_document`
    CHANGE COLUMN `file_id` `student_submission_file_id` INT (11) UNSIGNED NOT NULL;
