-- There's an existing config flag for enabling/disabling the comments.
-- Was introduced in https://schoolbox.atlassian.net/browse/SA-8095
-- But just to be sure, let's just try to insert it if config flag doesn't exist yet.
-- Otherwise, let's just update the row to make it visible in the news settings.

INSERT INTO config (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `editable`,
    `hidden`,
    `required`,
    `module`
) VALUES (
    'comms_comments_enabled',
    '1',
    'Allow comments',
    'If enabled, users will be able to turn on comments on news items',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    null,
    'true',
    'false',
    'false',
    'comms'
) ON DUPLICATE KEY UPDATE
            `title` = 'Allow comments',
            `value` = '1',
            `description` = 'If enabled, users will be able to turn on comments on news items',
            `field_id` = (SELECT id FROM lu_config_field WHERE field = 'boolean'),
            `options` = null,
            `editable` = 'true',
            `hidden` = 'false',
            `required` = 'false',
            `module` = 'comms'
