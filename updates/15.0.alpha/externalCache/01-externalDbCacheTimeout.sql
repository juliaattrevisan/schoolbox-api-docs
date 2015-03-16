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
    'external_cache_timeout',
    '300', -- 5 minutes
    'External Cache Timeout',
    'The amount of time (in seconds) that external DB call results should be cached for.',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    'false',
    'false'
);
