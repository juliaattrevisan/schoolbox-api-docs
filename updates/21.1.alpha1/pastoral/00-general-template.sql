-- Store template html for default value of General Template
ALTER TABLE `lu_pastoral_type`
    ADD COLUMN `template` MEDIUMTEXT CHARACTER SET 'utf8mb4' NULL AFTER `name`;

ALTER TABLE `lu_pastoral_subtype`
    ADD COLUMN `template` MEDIUMTEXT CHARACTER SET 'utf8mb4' NULL AFTER `name`;
