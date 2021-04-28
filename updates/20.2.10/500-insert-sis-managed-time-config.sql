INSERT INTO `schoolbox`.`lu_config_field` (`id`, `field`) VALUES ('17', 'multiSelect');

INSERT INTO config (name, value, title, description, field_id, options, editable, hidden, required, module, sequence)
VALUES (
   'schooldata_import_managed_hours',
   '',
   'Schoolbox Managed hours',
   'Hours to run the import if "Import Frequency" = "Schoolbox Managed".',
   (SELECT id FROM lu_config_field WHERE field = 'multiSelect'),
   '00:01:02:03:04:05:06:07:08:09:10:11:12:13:14:15:16:17:18:19:20:21:22:23',
   'true',
   'false',
   'true',
   'schoolData',
   5
);
