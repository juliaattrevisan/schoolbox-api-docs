INSERT INTO `config`
  (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
  'external_synergetic_timetable_accurate_days', '2', 'Synergetic Timetable Accurate Days Window',
  'The number of days in advance to retrieve date-specific timetable information for users, which is a more expensive operation than retrieving just by day number',
  '1', '', '7', 'true', 'false', 'true'
);
