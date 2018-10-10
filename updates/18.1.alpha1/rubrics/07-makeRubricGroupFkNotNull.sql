-- First, don't even try to import rubric criteria which don't have a group
DELETE FROM rubric_criterion WHERE rubric_group_id IS NULL;

-- Then, set rubric_criterion.rubric_group_id to non-nullable
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
