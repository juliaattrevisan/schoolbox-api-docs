-- Learning level table
-- Loosely maps to a year level.
CREATE TABLE `curriculum_learning_level` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_learning_area_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_learning_area_id` (`curriculum_learning_area_id`),
  CONSTRAINT `curriculum_year_level_ibfk_1`
    FOREIGN KEY (`curriculum_learning_area_id`)
    REFERENCES `curriculum_learning_area` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
