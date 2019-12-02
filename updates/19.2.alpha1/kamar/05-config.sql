-- Add schoolData category
ALTER TABLE `config`
    CHANGE COLUMN `module` `module` ENUM('settings', 'comms', 'custom', 'oauth', 'schoolData') COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT 'settings' ;

-- Import Frequency for cron task tools/synchronise/scheduler.php
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('schooldata_import_frequency', 'Import Frequency', 'Change how frequently data import occurs - hourly, twice daily, or daily', (SELECT id FROM lu_config_field WHERE field = 'selectKey'), 'none;None:hourly;Hourly:twiceDaily;Twice Daily (4am and 4pm):daily;Daily (1am)', null, 'true', 'false', 'true', 'schoolData');

-- Move batchxml options into SchoolData module
UPDATE `config` SET `module`='schoolData', `type_id`=null WHERE `name`='external_ups_provider_batchxml_host';
UPDATE `config` SET `module`='schoolData', `type_id`=null WHERE `name`='external_ups_provider_batchxml_key';

-- Data Source options, a freeform input field for now
INSERT INTO `config` (`name`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('schooldata_import_data_source', 'Data Source', 'Choose which importer to use when importing data from API endpoints', (SELECT id FROM lu_config_field WHERE field = 'selectKey'), 'csv;CSV Files:isams;iSAMS:kamar;Kamar:sentral;Sentral', null, 'true', 'false', 'false', 'schoolData');

-- Sequence config options
-- Because I need them to appear in a particular order...
ALTER TABLE `config`
    ADD COLUMN `sequence` INT(11) NULL AFTER `module`;

-- ...and this is the order
UPDATE `config` SET `sequence`='1' WHERE `name`='schooldata_import_data_source';
UPDATE `config` SET `sequence`='2' WHERE `name`='external_ups_provider_batchxml_host';
UPDATE `config` SET `sequence`='3' WHERE `name`='external_ups_provider_batchxml_key';
UPDATE `config` SET `sequence`='4' WHERE `name`='schooldata_import_frequency';

-- Rename external_ups_provider_batchxml configs
UPDATE `config` SET `name`='schooldata_import_batchxml_host' WHERE `name`='external_ups_provider_batchxml_host';
UPDATE `config` SET `name`='schooldata_import_batchxml_key' WHERE `name`='external_ups_provider_batchxml_key';

UPDATE `config` SET `title`='School Data Endpoint Host' WHERE `name`='schooldata_import_batchxml_host';
UPDATE `config` SET `title`='School Data Endpoint Key' WHERE `name`='schooldata_import_batchxml_key';
