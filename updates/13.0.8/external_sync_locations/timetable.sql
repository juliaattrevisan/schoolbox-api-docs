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
  'external_sync_timetable_csv',
  '/home/alaress/timetable.csv',
  'External Sync Timetable CSV Location',
  'External Sync Timetable CSV Location',
  'input',
  NULL,
  'External DB',
  'true',
  'false',
  'false'
);
