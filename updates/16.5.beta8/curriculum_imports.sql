ALTER TABLE `curriculum_node`
  CHANGE COLUMN `reference` `reference` VARCHAR(2048) NOT NULL ,
  CHANGE COLUMN `description` `description` TEXT NULL,
  ADD KEY `idx_curriculum_node_code` (`code`);

ALTER TABLE `curriculum_learning_level`
  CHANGE COLUMN `description` `description` TEXT NULL ;

ALTER TABLE `curriculum_learning_area`
  CHANGE COLUMN `description` `description` TEXT NULL ;