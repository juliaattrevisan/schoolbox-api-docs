CREATE TABLE `video_queue`( `video_queue_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `file_id` INT UNSIGNED NOT NULL, `type` ENUM('sbfs','storage') NOT NULL, `status` ENUM('pending','processing','completed','failed') NOT NULL, `message` TEXT NULL, `duration` INT NULL, `createdOn` DATETIME NOT NULL, `modifiedOn` DATETIME NOT NULL, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;
ALTER TABLE `video_queue` ADD INDEX `file_id` (`file_id`);

ALTER TABLE `file` DROP COLUMN `type`;