ALTER TABLE `rubric_descriptor_mark`
    -- Temporary null
    ADD COLUMN `rubric_group_mark_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `return_id`,
    ADD INDEX `rubric_descriptor_mark_rubric_group_mark_id` (rubric_group_mark_id ASC),
    ADD CONSTRAINT `fk_rubric_descriptor_mark_rubric_group_mark_id`
    FOREIGN KEY (`rubric_group_mark_id`)
    REFERENCES `rubric_group_mark` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
