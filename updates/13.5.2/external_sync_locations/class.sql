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
  'external_sync_class_csv',
  '/home/alaress/classes.csv',
  'External Sync Class CSV Location',
  'External Sync Class CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
