UPDATE `config` SET `options` = 'none;None:sb;Schoolbox Managed:hourly;Hourly:twiceDaily;Twice Daily (6am and 6pm):daily;Daily (9pm)'
WHERE (`name` = 'schooldata_import_frequency');
