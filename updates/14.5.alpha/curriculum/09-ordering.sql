-- Learning levels and areas now have an order column.
ALTER TABLE `curriculum_learning_level` ADD COLUMN `order` TINYINT NOT NULL AFTER `reference`;
ALTER TABLE `curriculum_learning_area` ADD COLUMN `order` TINYINT NOT NULL AFTER `reference`;
