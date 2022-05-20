# Create a hole in config
UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'Policy'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='timetable_export_end' LIMIT 1);

-- Add a new config option
INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('timetable_view',
        'table',
        'Timetable View',
        'Choose your preferred layout for the timetable. If you have varying periods times per day, we recommend the calendar view as it is more flexible.',
        'table;Table:calendar;Calendar',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        (SELECT id FROM lu_config_type WHERE type = 'Policy'),
        'true',
        'false',
        'true',
        'settings',
        41
    );
