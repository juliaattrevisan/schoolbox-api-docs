-- Add a new config option
INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('timetable_view',
        'table',
        'Timetable View',
        'Choose your preferred layout for the timetable. If you have different periods times per day, we recommend the calendar view as it is more flexible and will more accurately represent your timetable.',
        'table;Table:calendar;Calendar',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        (SELECT id FROM lu_config_type WHERE type = 'Policy'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
            SELECT id FROM lu_config_type WHERE type = 'Policy'
        ))
    );

