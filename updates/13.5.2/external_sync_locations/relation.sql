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
  'external_sync_relation_csv',
  '/home/alaress/relations.csv',
  'External Sync Relation CSV Location',
  'External Sync Relation CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
