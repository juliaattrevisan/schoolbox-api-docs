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
  'external_sync_parent_csv',
  '/home/alaress/parents.csv',
  'External Sync Parent CSV Location',
  'External Sync Parent CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
