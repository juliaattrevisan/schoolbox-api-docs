-- You currently can't open paperly on iOS devices due to limitations with cookies in iframes.
-- As a temporary workaround (SA-19282), we are adding a configuration option to allow schools to choose to open paperly in a new tab instead of an iframe.
-- By default, iframes will be used

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'paperly_open_in_new_tab',
    '0',
    'Open Paperly in a New Tab instead of Inline',
    'Enable this to open Paperly in a new tab instead of inline. Useful if users have loading issues with the inline option',
    null,
    (SELECT id FROM lu_config_field WHERE `field` = 'boolean'),
    (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT c.sequence+1 FROM config c WHERE c.name = 'paperly_agent_external_db_name_override' AND c.module = 'settings' LIMIT 1)
);
