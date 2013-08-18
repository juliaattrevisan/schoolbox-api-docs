ALTER TABLE `config` CHANGE COLUMN `type` `type` ENUM(
  'Site',
  'Security',
  'Policy',
  'Paths',
  'Resources',
  'Email',
  'Welcome',
  'Localisation',
  'External DB',
  'Colours',
  'Board Leave',
  'Modules',
  '3rd Party Services',
  'Debugging'
) NULL DEFAULT NULL;

INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'debug_mode',
  '0',
  'Debug mode',
  'If this is on, a debugging toolbar is displayed on the bottom of each page.',
  'boolean',
  NULL,
  'Debugging',
  'true',
  'false',
  'false'
), (
  'debug_xhprof',
  '0',
  'Debug XHProf output',
  'Displays XHProf output on the debugging toolbar. Has no effect unless debug mode is on, and the XHProf PHP extension is installed.',
  'boolean',
  NULL,
  'Debugging',
  'true',
  'false',
  'false'
);