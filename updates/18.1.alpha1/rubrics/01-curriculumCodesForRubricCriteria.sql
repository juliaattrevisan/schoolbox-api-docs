CREATE TABLE `curriculum_node_for_rubric_criterion` (
    `curriculum_node_id` int(11) NOT NULL,
    `rubric_criterion_id` int(11) NOT NULL,
    UNIQUE KEY `curriculum_node_for_rubric_criterion_unique` (`curriculum_node_id`,`rubric_criterion_id`),
    KEY `curriculum_node_for_rubric_criterion_node_id` (`curriculum_node_id`),
    KEY `curriculum_node_for_rubric_criterion_rubric_criterion_id` (`rubric_criterion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
