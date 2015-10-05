-- External Parent Teacher Online Conference config
INSERT IGNORE INTO `config`(
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
    'external_pto_conference_school_id',
    '',
    'External Parent Teacher Online Conference School ID',
    '',
    1, -- input
    '',
    10, -- 3rd Party Services
    'true',
    'true',
    'false'
), (
    'external_pto_conference_school_remote_secret',
    '',
    'External Parent Teacher Online Conference Remote Secret',
    '',
    1, -- input
    '',
    10, -- 3rd Party Services
    'true',
    'true',
    'false'
);
