ALTER TABLE `category` ADD COLUMN `deleted_at` DATETIME NULL AFTER `parent`;
ALTER TABLE `link` ADD COLUMN `deleted_at` DATETIME NULL AFTER `hidden`;