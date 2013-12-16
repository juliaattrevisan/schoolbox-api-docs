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
  'external_sync_enrolment_csv',
  '/home/alaress/enrolments.csv',
  'External Sync Enrolment CSV Location',
  'External Sync Enrolment CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
