-- Deprecate the Description column
ALTER TABLE `sis_class`
    CHANGE COLUMN `description` `_description` VARCHAR(256) NULL DEFAULT NULL;
