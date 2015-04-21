ALTER TABLE `video_queue` ADD COLUMN `hash` VARCHAR(40) NULL AFTER `file_id`;
ALTER TABLE `video_queue` ADD INDEX `hash` (`hash`);