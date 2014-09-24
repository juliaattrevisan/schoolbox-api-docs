ALTER TABLE `category` ADD COLUMN `deleted_at` DATETIME NULL AFTER `parent`;
ALTER TABLE `categoryCode` ADD COLUMN `deleted_at` DATETIME NULL AFTER `code`;
ALTER TABLE `link` ADD COLUMN `deleted_at` DATETIME NULL AFTER `hidden`;