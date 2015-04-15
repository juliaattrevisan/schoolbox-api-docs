-- Basic relation between curriculum nodes and boxes
CREATE TABLE `curriculum_node_for_submission_box` (
    `curriculum_node_id` INT NOT NULL,
    `submission_box_id` INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- and its indices
ALTER TABLE `curriculum_node_for_submission_box`
    ADD INDEX `curriculum_node_for_submission_box_curriculum_node_id` (`curriculum_node_id`);
ALTER TABLE `curriculum_node_for_submission_box`
    ADD INDEX `curriculum_node_for_submission_box_submission_box_id` (`submission_box_id`);
ALTER TABLE `curriculum_node_for_submission_box`
    ADD UNIQUE INDEX `curriculum_node_for_submission_box_unique` (`curriculum_node_id`, `submission_box_id`);
ALTER TABLE `curriculum_node_for_submission_box`
    ADD FOREIGN KEY (`curriculum_node_id`)
    REFERENCES `curriculum_node` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE;
-- submission_box is also FailISAM -_-
# ALTER TABLE `curriculum_node_for_submission_box`
#     ADD FOREIGN KEY (`submission_box_id`)
#     REFERENCES `submission_box` (`id`)
#         ON UPDATE CASCADE ON DELETE CASCADE;
