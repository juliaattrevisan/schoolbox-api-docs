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
  'external_sync_parent_csv',
  '/home/alaress/parents.csv',
  'External Sync Parent CSV Location',
  'External Sync Parent CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
