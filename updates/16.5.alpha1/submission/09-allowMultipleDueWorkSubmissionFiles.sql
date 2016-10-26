-- As God intended:
ALTER TABLE `student_submission` ENGINE=INNODB;

-- Submission file table
CREATE TABLE `student_submission_file` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_submission_id` INT (5) UNSIGNED NOT NULL,
    `hash` varchar(40) NOT NULL,
    `realname` varchar(255) NOT NULL DEFAULT '',
    `editable_inline` BOOL DEFAULT FALSE,
    `uuid` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `student_submission_file_student_submission` (`student_submission_id` ASC),
    CONSTRAINT `fk_student_submission_file_student_submission`
    FOREIGN KEY (`student_submission_id`)
    REFERENCES `student_submission` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX `student_submission_file_hash` (`hash` ASC)
    -- storage is MyISAM
#     CONSTRAINT `fk_student_submission_file_hash`
#     FOREIGN KEY (`hash`)
#     REFERENCES `storage` (`hash`)
#         ON DELETE CASCADE
#         ON UPDATE CASCADE
    )
    ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- Populate data
-- Heuristic for whether a file is editable inline:
-- * name is "Online Assessment" or "online-assessment"
-- * type is "text/html"
INSERT INTO `student_submission_file` (
    `student_submission_id`,
    `hash`,
    `realname`,
    `editable_inline`,
    `uuid`
)
SELECT
    `ss`.`id`,
    `ss`.`hash`,
    `ss`.`realname`,
    (IF(`aft`.`mime` = 'text/html' AND `ss`.`realname` IN ('Online Submission', 'online-submission'), TRUE, FALSE)),
    `ss`.`uuid`
FROM `student_submission` `ss`
INNER JOIN `storage` `st` ON `ss`.`hash` = `st`.`hash`
LEFT JOIN `allowedFileExtension` `afe` ON `st`.`ext` = `afe`.`extension`
LEFT JOIN `allowedFileTypes` `aft` ON `afe`.`fileTypeID` =`aft`.`fileTypeID`;
