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
  'external_sync_staff_csv',
  '/home/alaress/staff.csv',
  'External Sync Staff CSV Location',
  'External Sync Staff CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
