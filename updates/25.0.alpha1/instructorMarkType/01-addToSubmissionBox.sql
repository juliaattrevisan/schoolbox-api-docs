ALTER TABLE `submission_box`
    ADD `instructor_mark_type` INT(11) NOT NULL AFTER `mark_type`,
    ADD `instructor_max_mark` DECIMAL(14,11) DEFAULT NULL AFTER `max_mark`,
    ADD `submission_instructor_grade_group_id` INT(11) DEFAULT NULL AFTER `submission_grade_group_id`;

-- The instructor variants is the same for all existing things
UPDATE `submission_box` SET
    `instructor_mark_type` = `mark_type`,
    `instructor_max_mark` = `max_mark`,
    `submission_instructor_grade_group_id` = `submission_grade_group_id`;

-- Foreign keys and indexes (irrelevant for max_mark in the current table)
ALTER TABLE `submission_box`
    ADD INDEX `fk_submission_box_instructor_mark_type` (`instructor_mark_type`),
    ADD INDEX `fk_submission_box_submission_instructor_grade_group_id` (`submission_instructor_grade_group_id`);


ALTER TABLE `submission_box`
    ADD CONSTRAINT `fk_submission_box_instructor_mark_type`
        FOREIGN KEY (`instructor_mark_type`)
            REFERENCES `submission_mark_type` (`mark_type_id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    ADD CONSTRAINT `fk_submission_box_submission_instructor_grade_group_id`
        FOREIGN KEY (`submission_instructor_grade_group_id`)
            REFERENCES `submission_grade_group` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
