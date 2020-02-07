-- Add a config option which allows staff to create tags
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES
(
    'pastoral_create_tag_enable',
    1,
    'Allow staff to create pastoral tags',
    'When enabled, staff members can create pastoral tags on the fly while creating or updating pastoral records.',
    (SELECT id FROM lu_config_field WHERE `field` = 'boolean'),
    null,
    (SELECT id FROM lu_config_type WHERE `type` = 'Pastoral Care'),
    true,
    false,
    false,
    'settings',
    null
);
