-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
SET c.sequence = c.sequence + 1
WHERE c.module = 'schoolData'
AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='schooldata_import_frequency' LIMIT 1);

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'schooldata_import_dailyorg_time',
    '08:30',
    'Daily Org Import Time',
    'Decide at what time the timetable-only import runs. This occurs on a daily basis, each day of the week.',
    '',
    (SELECT id FROM lu_config_field WHERE field = 'timepicker'),
    null,
    'true',
    'false',
    'false',
    'schoolData',
    (SELECT c.sequence+1 FROM config c WHERE c.name = 'schooldata_import_frequency' AND c.module = 'schoolData' LIMIT 1)
);