ALTER TABLE `file`
    ADD COLUMN `modified_by` INT(4) UNSIGNED NULL DEFAULT NULL AFTER `description`;
