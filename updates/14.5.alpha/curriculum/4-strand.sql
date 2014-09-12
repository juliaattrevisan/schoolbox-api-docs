-- Strand table
-- An area of assessment within a learning area and learning level combination.
-- It may have sub-strands (see curriculum_strand_parent_id)
CREATE TABLE `curriculum_strand` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_strand_parent_id` int(11) DEFAULT NULL,
  `curriculum_learning_level_id` int(11) DEFAULT NULL,
  `curriculum_learning_area_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_strand_ibfk_1` (`curriculum_learning_area_id`),
  KEY `curriculum_strand_ibfk_2` (`curriculum_strand_parent_id`),
  KEY `curriculum_strand_ibfk_3` (`curriculum_learning_level_id`),
  CONSTRAINT `curriculum_strand_ibfk_1`
    FOREIGN KEY (`curriculum_learning_area_id`)
    REFERENCES `curriculum_learning_area` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `curriculum_strand_ibfk_2`
    FOREIGN KEY (`curriculum_strand_parent_id`)
    REFERENCES `curriculum_strand` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `curriculum_strand_ibfk_3`
    FOREIGN KEY (`curriculum_learning_level_id`)
    REFERENCES `curriculum_learning_level` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
