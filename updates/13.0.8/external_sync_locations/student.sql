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
  'external_sync_student_csv',
  '/home/alaress/students.csv',
  'External Sync Student CSV Location',
  'External Sync Student CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
