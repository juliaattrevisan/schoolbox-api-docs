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
    'lti_debug_mode',
    '0',
    'Enable LTI Debug Logging',
    'If enabled: enable debug-level logging for LTI integrations.  (These logs will be available only to Schoolbox support.)',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'boolean'),
    null,
    'true',
    'false',
    'false',
    'lti',
    (SELECT max(sequence) + 1 FROM `config` c WHERE module = 'lti')
 );
