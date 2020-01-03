UPDATE `userGroups` SET `type` = 1 WHERE `type` IS NULL;
ALTER TABLE `userGroups` CHANGE COLUMN `type` `type` TINYINT(2) NOT NULL;
