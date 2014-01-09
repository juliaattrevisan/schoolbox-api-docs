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
  'external_sync_relation_csv',
  '/home/alaress/relations.csv',
  'External Sync Relation CSV Location',
  'External Sync Relation CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
