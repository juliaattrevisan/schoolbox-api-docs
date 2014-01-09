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
  'external_sync_class_csv',
  '/home/alaress/classes.csv',
  'External Sync Class CSV Location',
  'External Sync Class CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
