INSERT INTO `config`(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'oauth_debug_mode',
    '0',
    'Enable oAuth Debug Logging',
    'If enabled: enable debug-level logging for oAuth logins.  (These logs will be available only to Schoolbox support.)',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'boolean'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT max(sequence) + 1 FROM `config` c WHERE module = 'oauth')
);
