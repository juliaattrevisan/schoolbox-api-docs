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
  'external_sync_staff_csv',
  '/home/alaress/staff.csv',
  'External Sync Staff CSV Location',
  'External Sync Staff CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
