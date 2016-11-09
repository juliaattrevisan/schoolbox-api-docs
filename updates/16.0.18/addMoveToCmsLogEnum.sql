ALTER TABLE `cmsLog`
CHANGE COLUMN `action` `action` ENUM('create', 'modify', 'delete', 'access', 'move');