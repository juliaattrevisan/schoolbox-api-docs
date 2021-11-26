INSERT INTO `config`
(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES ('pastoral_action_enable',
        0,
        'Enable Pastoral Action',
        'If toggled ON, action will be available to be added to pastoral records.',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        NULL,
        (SELECT id FROM lu_config_type WHERE type = 'Pastoral Care'),
        'true',
        'false',
        'true',
        'settings',
        (SELECT max(sequence) + 1
         FROM config c
         WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Pastoral Care')));
