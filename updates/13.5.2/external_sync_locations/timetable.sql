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
  'external_sync_timetable_csv',
  '/home/alaress/timetable.csv',
  'External Sync Timetable CSV Location',
  'External Sync Timetable CSV Location',
  1,
  NULL,
  7,
  'true',
  'false',
  'false'
);
