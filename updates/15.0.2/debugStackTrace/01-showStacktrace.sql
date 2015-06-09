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
    'debug_show_stack_trace',
    (IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'site_location' AND `c`.`value` = 'demo'), '1', '0')),
    'Show Stack Trace on Error',
    'If this is on, full stack traces will be shown on error. This can expose internal details of Schoolbox, so should be switched off unless testing.',
    3, -- checkbox
    NULL,
    14, -- debug
    'false',
    'true',
    'false'
);
