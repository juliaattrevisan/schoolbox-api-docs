INSERT INTO `lu_config_field` (`field`) VALUES ('timepicker');

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
  'calendar_start_time',
  '06:00',
  'Calendar Start Time',
  'Select the default start time for events created via the Day and Week views',
  15,
  NULL,
  6,
  'true',
  'false',
  'true'
);