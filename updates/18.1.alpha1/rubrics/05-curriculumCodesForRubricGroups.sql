CREATE TABLE `curriculum_node_for_rubric_group` (
    `curriculum_node_id` int(11) NOT NULL,
    `rubric_group_id` int(11) NOT NULL,
    UNIQUE KEY `curriculum_node_for_rubric_group_unique` (`curriculum_node_id`,`rubric_group_id`),
    KEY `curriculum_node_for_rubric_group_node_id` (`curriculum_node_id`),
    KEY `curriculum_node_for_rubric_group_rubric_group_id` (`rubric_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
