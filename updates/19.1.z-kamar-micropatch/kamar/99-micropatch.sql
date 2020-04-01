-- Micropatch-specific: hardcode some values
UPDATE `config` SET `value`='kamar' WHERE `name`='schooldata_import_data_source';
UPDATE `config` SET `value`='hourly' WHERE `name`='schooldata_import_frequency';

-- Add new option to Frequency
UPDATE `config` SET `options`='none;None:sb;Schoolbox Managed:hourly;Hourly:twiceDaily;Twice Daily (4am and 4pm):daily;Daily (1am)'
WHERE `name`='schooldata_import_frequency';
