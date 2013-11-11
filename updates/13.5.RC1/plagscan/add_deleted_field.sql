-- Plagscan attempts can now be soft-deleted.
ALTER TABLE `plagscan_document` ADD COLUMN `deleted` DATETIME NULL DEFAULT NULL AFTER `html` ;
