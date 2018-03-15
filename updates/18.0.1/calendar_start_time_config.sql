INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
  'calendar_start_time',
  '6am',
  'Calendar Start Time',
  'Select the default start time for events created via the Day and Week views',
  4,
  '12am:1am:2am:3am:4am:5am:6am:7am:8am:9am:10am:11am:12pm:1pm:2pm:3pm:4pm:5pm:6pm:7pm:8pm:9pm:10pm:11pm',
  6,
  'true',
  'false',
  'true'
);