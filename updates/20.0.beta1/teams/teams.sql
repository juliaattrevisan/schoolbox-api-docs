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


