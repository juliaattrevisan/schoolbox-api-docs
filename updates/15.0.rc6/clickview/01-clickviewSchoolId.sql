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
    'The School ID that Clickview has provided you with, this is only required if you use SSO',
    1, -- input
    '',
    10, -- 3rd party services
    'true',
    'false',
    'false'
);
