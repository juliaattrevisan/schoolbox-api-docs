-- Add "Incomplete" status
ALTER TABLE `sis_import_log`
    CHANGE COLUMN `status` `status` ENUM('running', 'complete', 'incomplete', 'error') NOT NULL ;
