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
    'calendar_collapse_events',
    '1',
    'Collapse events on calendar?',
    'If this setting is on, events on the calendar month view will be collapsed to fit the screen, and hidden behind a "View More" link. If this is off, all events will display at once.',
    3, -- checkbox
    NULL,
    6, -- localization
    'true',
    'false',
    'false'
);
