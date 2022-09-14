-- add created_at for group membership notification
ALTER TABLE `userAccess` ADD COLUMN `created_at` DATETIME;
UPDATE `userAccess` SET `created_at` = now();
