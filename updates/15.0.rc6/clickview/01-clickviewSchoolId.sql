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
    'clickview_school_id',
    '',
    'Clickview School ID',
    'The School ID that Clickview has provided you with',
    1, -- input
    '',
    10, -- 3rd party services
    'true',
    'false',
    'false'
);
