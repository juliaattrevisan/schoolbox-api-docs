-- Add schoolData category
ALTER TABLE `config`
    CHANGE COLUMN `module` `module` ENUM('settings', 'comms', 'custom', 'oauth', 'schoolData') COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT 'settings' ;

-- Import Frequency for cron task tools/synchronise/scheduler.php
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('schooldata_import_frequency', 'Import Frequency', 'Change how frequently data import occurs - hourly, twice daily, or daily', '7', 'none;None:hourly;Hourly:twiceDaily;Twice Daily (4am and 4pm):daily;Daily (1am)', '7', 'true', 'false', 'true', 'schoolData');
