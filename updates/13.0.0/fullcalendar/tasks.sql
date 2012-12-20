ALTER TABLE `event` ADD COLUMN `completed` TINYINT DEFAULT 0 NOT NULL COMMENT 'For marking Tasks as complete or not.' AFTER `publish_date`;
