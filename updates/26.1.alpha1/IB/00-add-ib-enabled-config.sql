INSERT INTO config (
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
)
VALUES (
    'ib_enabled',
    'International Baccalaureate Enabled',
    'false',
    'Controls whether International Baccalaureate admin and profile surfaces are available.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    'false',
    'true',
    'false',
    'settings',
    0
)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `field_id` = VALUES(`field_id`),
    `type_id` = VALUES(`type_id`),
    `editable` = VALUES(`editable`),
    `hidden` = VALUES(`hidden`),
    `required` = VALUES(`required`),
    `module` = VALUES(`module`);
