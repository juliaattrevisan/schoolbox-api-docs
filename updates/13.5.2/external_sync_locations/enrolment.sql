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
  'external_sync_enrolment_csv',
  '/home/alaress/enrolments.csv',
  'External Sync Enrolment CSV Location',
  'External Sync Enrolment CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
