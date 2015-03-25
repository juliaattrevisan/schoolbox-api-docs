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
    'external_markbook',
    '0', -- off by default
    'Enable Markbook Syncronisation',
    'Enables markbook data to be automatically written back into the Synergetic markbook.',
    3, -- checkbox
    NULL,
    7, -- external DB
    'true',
    'false',
    'false'
);
