-- For Enabling Microsoft Teams
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`)
VALUES (
    'teams_enabled',
    0,
    'Enable Microsoft Teams Meetings Integration',
    "If toggled to 'ON' users can create links from Microsoft Teams",
    (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
    null,
    (SELECT id FROM `lu_config_type` WHERE `type` = '3rd Party Services'),
    'true',
    'false',
    'true',
    'settings'
);

-- For setting the Microsoft Team's app url
-- In vagrants it's https://localhost:3000
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`)
VALUES (
    'teams_url',
    'https://msteams.schoolbox.cloud',
    'Microsoft Teams Meetings Integration App Url',
    "The url where Microsoft Teams App is installed",
    (SELECT id FROM `lu_config_field` WHERE `field` = 'input'),
    null,
    (SELECT id FROM `lu_config_type` WHERE `type` = '3rd Party Services'),
    'false',
    'true',
    'true',
    'settings'
);
