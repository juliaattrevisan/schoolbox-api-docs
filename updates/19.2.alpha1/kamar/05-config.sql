-- Add schoolData category
ALTER TABLE `config`
    CHANGE COLUMN `module` `module` ENUM('settings', 'comms', 'custom', 'oauth', 'schoolData') COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT 'settings' ;

-- Import Frequency for cron task tools/synchronise/scheduler.php
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('schooldata_import_frequency', 'Import Frequency', 'Change how frequently data import occurs - hourly, twice daily, or daily', (SELECT id FROM lu_config_field WHERE field = 'select'), 'none;None:hourly;Hourly:twiceDaily;Twice Daily (4am and 4pm):daily;Daily (1am)', null, 'true', 'false', 'true', 'schoolData');

-- Move batchxml options into SchoolData module
UPDATE `config` SET `module`='schoolData', `type_id`=null WHERE `name`='external_ups_provider_batchxml_host';
UPDATE `config` SET `module`='schoolData', `type_id`=null WHERE `name`='external_ups_provider_batchxml_key';

-- Data Source options, a freeform input field for now
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('schooldata_import_data_source', 'Data Source', 'Choose which importer to use when importing data from API endpoints', (SELECT id FROM lu_config_field WHERE field = 'select'), 'csv;CSV Files:isams;iSAMS:kamar;Kamar:sentral;Sentral', null, 'true', 'false', 'false', 'schoolData');
