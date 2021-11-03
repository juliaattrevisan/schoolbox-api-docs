ALTER TABLE `lu_pastoral_type`
    ADD COLUMN `color` VARCHAR(32) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci'  NULL AFTER `template`,
    ADD COLUMN `icon` VARCHAR(100) CHARACTER SET 'utf8mb4' NULL AFTER `color`;

ALTER TABLE `lu_pastoral_subtype`
    ADD COLUMN `color` VARCHAR(32) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci'  NULL AFTER `template`,
    ADD COLUMN `icon` VARCHAR(100) CHARACTER SET 'utf8mb4' NULL AFTER `color`;
