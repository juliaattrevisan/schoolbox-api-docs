-- Curriculum node table
-- Will replace both strand and description
CREATE TABLE `curriculum_node` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_node_parent_id` int(11) DEFAULT NULL,
  `curriculum_learning_level_id` int(11) DEFAULT NULL,
  `curriculum_learning_area_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(64) NOT NULL,
  `reference` VARCHAR(500) NOT NULL,
  `order` tinyint NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_node_ibfk_1` (`curriculum_learning_area_id`),
  KEY `curriculum_node_ibfk_2` (`curriculum_node_parent_id`),
  KEY `curriculum_node_ibfk_3` (`curriculum_learning_level_id`),
  CONSTRAINT `curriculum_node_ibfk_1`
  FOREIGN KEY (`curriculum_learning_area_id`)
  REFERENCES `curriculum_learning_area` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `curriculum_node_ibfk_2`
  FOREIGN KEY (`curriculum_node_parent_id`)
  REFERENCES `curriculum_node` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `curriculum_node_ibfk_3`
  FOREIGN KEY (`curriculum_learning_level_id`)
  REFERENCES `curriculum_learning_level` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `curriculum_node`
CHANGE `name` `name` VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
ADD UNIQUE INDEX (`curriculum_node_parent_id`, `name`);
