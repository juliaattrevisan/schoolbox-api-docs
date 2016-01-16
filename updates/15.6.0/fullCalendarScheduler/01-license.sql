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
    'fullcalendar_scheduler_license_key',
    '0220474696-fcs-1452833916',
    'FullCalendar Scheduler License Key',
    'FullCalendar Scheduler License Key',
    (SELECT `id` FROM lu_config_field WHERE field = 'input'),
    NULL,
    (SELECT `id` FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'false'
);
