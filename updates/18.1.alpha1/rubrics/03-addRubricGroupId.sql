/**
 * Add a relation to rubric groups in criteria.
 * This will be populated in the next update script.
 */
ALTER TABLE `rubric_criterion`
    ADD COLUMN `rubric_group_id` int(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `rubric_criterion_rubric_group_idx` (`rubric_group_id` ASC),
    ADD CONSTRAINT `rubric_criterion_rubric_group`
        FOREIGN KEY (`rubric_group_id`)
        REFERENCES `rubric_group` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
