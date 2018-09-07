/**
 * Set rubric_descriptor_mark.rubric_group_mark_id to non-nullable.
 *
 * If this raises an error, then there are some rubric_descriptor_mark rows
 * without a rubric_group_mark.
 * This is an error, and must be reported.
 */
ALTER TABLE `rubric_descriptor_mark`
    DROP FOREIGN KEY `fk_rubric_descriptor_mark_rubric_group_mark_id`;
ALTER TABLE `rubric_descriptor_mark`
    CHANGE COLUMN `rubric_group_mark_id` `rubric_group_mark_id` INT(11) UNSIGNED NOT NULL ;
ALTER TABLE `rubric_descriptor_mark`
    ADD CONSTRAINT `fk_rubric_descriptor_mark_rubric_group_mark_id`
    FOREIGN KEY (`rubric_group_mark_id`)
    REFERENCES `rubric_group_mark` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
