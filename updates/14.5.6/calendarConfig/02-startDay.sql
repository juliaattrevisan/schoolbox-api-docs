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
    'calendar_start_day',
    '0',
    'Calendar Start Day',
    'The day the calendar display starts on - usually Sunday or Monday.',
    7, -- select
    '0;Sunday:1;Monday:2;Tuesday:3;Wednesday:4;Thursday:5;Friday:6;Saturday',
    6, -- localization
    'true',
    'false',
    'false'
);
