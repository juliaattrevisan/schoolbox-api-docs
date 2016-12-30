ALTER TABLE `curriculum_node`
  CHANGE COLUMN `reference` `reference` VARCHAR(2048) NOT NULL ,
  CHANGE COLUMN `description` `description` TEXT NULL,
  ADD KEY `idx_curriculum_node_code` (`code`);