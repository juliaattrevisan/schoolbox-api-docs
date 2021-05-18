INSERT INTO `lu_config_field` (`id`, `field`) VALUES ('17', 'multiSelect');

UPDATE `config` SET options='none;None:sb;Custom:hourly;Hourly:twiceDaily;Twice Daily (6am and 6pm):daily;Daily (9pm)' WHERE name= 'schooldata_import_frequency';


INSERT INTO config (name, value, title, description, field_id, options, editable, hidden, required, module, sequence)
VALUES (
   'schooldata_import_managed_hours',
   '',
   'Custom Import Hours',
   'The hours of the day the import will run on.',
   (SELECT id FROM lu_config_field WHERE field = 'multiSelect'),
   '00:01:02:03:04:05:06:07:08:09:10:11:12:13:14:15:16:17:18:19:20:21:22:23',
   'true',
   'false',
   'false',
   'schoolData',
   5
);
