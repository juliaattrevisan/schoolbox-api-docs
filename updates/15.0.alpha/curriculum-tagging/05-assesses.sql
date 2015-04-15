-- Basic relation between curriculum nodes and assessments
CREATE TABLE `curriculum_node_for_assess` (
    `curriculum_node_id` INT NOT NULL,
    `assess_id` INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- and its indices
ALTER TABLE `curriculum_node_for_assess`
    ADD INDEX `curriculum_node_for_assess_curriculum_node_id` (`curriculum_node_id`);
ALTER TABLE `curriculum_node_for_assess`
    ADD INDEX `curriculum_node_for_assess_assess_id` (`assess_id`);
ALTER TABLE `curriculum_node_for_assess`
    ADD UNIQUE INDEX `curriculum_node_for_assess_unique` (`curriculum_node_id`, `assess_id`);
ALTER TABLE `curriculum_node_for_assess`
    ADD FOREIGN KEY (`curriculum_node_id`)
    REFERENCES `curriculum_node` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE;
-- assess is also FailISAM -_-
# ALTER TABLE `curriculum_node_for_assess`
#     ADD FOREIGN KEY (`assess_id`)
#     REFERENCES `assess` (`assess_id`)
#         ON UPDATE CASCADE ON DELETE CASCADE;
