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
  'Email',
  'true',
  'false',
  'false'
);