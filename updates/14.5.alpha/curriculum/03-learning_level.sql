-- Learning level table
-- Loosely maps to a year level.
CREATE TABLE `curriculum_learning_level` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_source_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_source_id` (`curriculum_source_id`),
  CONSTRAINT `curriculum_year_level_ibfk_1`
  FOREIGN KEY (`curriculum_source_id`)
  REFERENCES `curriculum_source` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
