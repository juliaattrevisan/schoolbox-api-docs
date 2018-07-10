/**
 * I neglected to add foreign keys to curriculum_node_for_rubric_criterion and
 * curriculum_node_for_rubric_group
 */
ALTER TABLE `curriculum_node_for_rubric_criterion`
    CHANGE COLUMN `rubric_criterion_id` `rubric_criterion_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `curriculum_node_for_rubric_criterion`
    ADD CONSTRAINT `fk_curriculum_node_for_rubric_criterion_rubric_criterion_id`
    FOREIGN KEY (`rubric_criterion_id`)
    REFERENCES `rubric_criterion` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;

ALTER TABLE `curriculum_node_for_rubric_criterion`
    ADD CONSTRAINT `fk_curriculum_node_for_rubric_criterion_curriculum_node_id`
    FOREIGN KEY (`curriculum_node_id`)
    REFERENCES `curriculum_node` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;

ALTER TABLE `curriculum_node_for_rubric_group`
    CHANGE COLUMN `rubric_group_id` `rubric_group_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `curriculum_node_for_rubric_group`
    ADD CONSTRAINT `fk_curriculum_node_for_rubric_group_rubric_group_id`
    FOREIGN KEY (`rubric_group_id`)
    REFERENCES `rubric_group` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;

ALTER TABLE `curriculum_node_for_rubric_group`
    ADD CONSTRAINT `fk_curriculum_node_for_rubric_group_curriculum_node_id`
    FOREIGN KEY (`curriculum_node_id`)
    REFERENCES `curriculum_node` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
