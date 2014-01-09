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
  'external_sync_student_csv',
  '/home/alaress/students.csv',
  'External Sync Student CSV Location',
  'External Sync Student CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
