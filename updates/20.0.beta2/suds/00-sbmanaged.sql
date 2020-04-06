-- Add new option to Frequency
UPDATE `config` SET `options`='none;None:sb;Schoolbox Managed:hourly;Hourly:twiceDaily;Twice Daily (4am and 4pm):daily;Daily (1am)'
WHERE `name`='schooldata_import_frequency';
