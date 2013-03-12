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
  'file_default_to_hidden',
  IF ((SELECT `c2`.`value` FROM `config` AS `c2` WHERE `c2`.`name` = 'site_location') IN ('balcombe'), '1', '0'),
  'File uploads default to hidden',
  'If this is checked, then file uploads will default to hidden - this can be changed on a per-file basis.',
  'boolean',
  NULL,
  'Resources',
  'true',
  'false',
  'false'
);