/**
 * Set rubric_criterion.rubric_group_id to non-nullable.
 *
 * If this raises an error, then there are some rubric_criterion rows without a rubric_group.
 * This is an error, and must be reported.
 */
ALTER TABLE rubric_criterion
    DROP FOREIGN KEY `rubric_criterion_rubric_group`;
ALTER TABLE rubric_criterion
    DROP INDEX `rubric_criterion_rubric_group_idx` ;
ALTER TABLE rubric_criterion CHANGE COLUMN rubric_group_id rubric_group_id INT(11) UNSIGNED NOT NULL;
ALTER TABLE rubric_criterion
    ADD INDEX `rubric_criterion_rubric_group_idx` (`rubric_group_id` ASC),
    ADD CONSTRAINT `rubric_criterion_rubric_group`
        FOREIGN KEY (`rubric_group_id`)
        REFERENCES `rubric_group` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
