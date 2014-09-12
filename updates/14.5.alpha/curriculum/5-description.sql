-- Description table
-- A curriculum code within a strand.
CREATE TABLE `curriculum_description` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_description_parent_id` int(11) DEFAULT NULL,
  `curriculum_strand_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `code` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_strand_id` (`curriculum_strand_id`),
  KEY `curriculum_description_ibfk_2` (`curriculum_description_parent_id`),
  CONSTRAINT `curriculum_description_ibfk_1`
    FOREIGN KEY (`curriculum_strand_id`)
    REFERENCES `curriculum_strand` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `curriculum_description_ibfk_2`
    FOREIGN KEY (`curriculum_description_parent_id`)
    REFERENCES `curriculum_description` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
