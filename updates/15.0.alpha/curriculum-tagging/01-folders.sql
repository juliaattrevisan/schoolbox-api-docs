-- Basic relation between curriculum nodes and folders
CREATE TABLE `curriculum_node_for_category` (
    `curriculum_node_id` INT NOT NULL,
    `category_id` INT(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- and its indices
ALTER TABLE `curriculum_node_for_category`
    ADD INDEX `curriculum_node_for_category_curriculum_node_id` (`curriculum_node_id`);
ALTER TABLE `curriculum_node_for_category`
    ADD INDEX `curriculum_node_for_category_category_id` (`category_id`);
ALTER TABLE `curriculum_node_for_category`
    ADD UNIQUE INDEX `curriculum_node_for_category_unique` (`curriculum_node_id`, `category_id`);
ALTER TABLE `curriculum_node_for_category`
    ADD FOREIGN KEY (`curriculum_node_id`)
    REFERENCES `curriculum_node` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE;
-- category is MyISAM -_-
# ALTER TABLE `curriculum_node_for_category`
#     ADD FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;
