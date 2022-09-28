UPDATE `curriculum_node` SET `description` = '' WHERE `description` IS NULL;
ALTER TABLE `curriculum_node` MODIFY COLUMN `description` text NOT NULL COLLATE utf8mb4_unicode_ci DEFAULT '';
