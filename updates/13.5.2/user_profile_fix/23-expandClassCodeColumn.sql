-- Was 16 chars, now 64 to accomodate some codes from NHS.
ALTER TABLE `class` CHANGE COLUMN `code` `code` VARCHAR(64) NOT NULL;
