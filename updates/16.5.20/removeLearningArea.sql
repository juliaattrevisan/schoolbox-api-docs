ALTER TABLE `curriculum_node`
  DROP FOREIGN KEY `curriculum_node_ibfk_1`;
ALTER TABLE `curriculum_node`
  CHANGE COLUMN `curriculum_learning_area_id` `curriculum_learning_area_id` INT(11) NULL ;