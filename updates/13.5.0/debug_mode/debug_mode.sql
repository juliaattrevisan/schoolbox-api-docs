-- ALTER TABLE `config` CHANGE COLUMN `type` `type` ENUM(
--   'Site',
--   'Security',
--   'Policy',
--   'Paths',
--   'Resources',
--   'Email',
--   'Welcome',
--   'Localisation',
--   'External DB',
--   'Colours',
--   'Board Leave',
--   'Modules',
--   '3rd Party Services',
--   'Debugging'
-- ) NULL DEFAULT NULL;

INSERT INTO `lu_config_type` (`type`) VALUES ('Debugging');

INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field_id`,
  `options`,
  `type_id`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'debug_mode',
  '0',
  'Debug mode',
  'If this is on, a debugging toolbar is displayed on the bottom of each page.',
  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'boolean' LIMIT 1),
  NULL,
  (SELECT `id` FROM `lu_config_type` WHERE `type` = 'Debugging' LIMIT 1),
  'true',
  'false',
  'false'
), (
  'debug_xhprof',
  '0',
  'Debug XHProf output',
  'Displays XHProf output on the debugging toolbar. Has no effect unless debug mode is on, and the XHProf PHP extension is installed.',
  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'boolean' LIMIT 1),
  NULL,
  (SELECT `id` FROM `lu_config_type` WHERE `type` = 'Debugging' LIMIT 1),
  'true',
  'false',
  'false'
);