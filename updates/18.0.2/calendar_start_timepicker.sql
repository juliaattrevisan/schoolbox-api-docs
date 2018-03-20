INSERT INTO `lu_config_field` (`field`) VALUES ('timepicker');

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
  'calendar_start_time',
  '06:00',
  'Calendar Start Time',
  'The default scroll position for all week and day calendar views.',
  15,
  NULL,
  6,
  'true',
  'false',
  'true'
);