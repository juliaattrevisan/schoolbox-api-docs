INSERT IGNORE INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
    'external_synergetic_allowed_file_types',
    '',
    'Synergetic Filetypes to filter subjects by',
    'Choose filetypes that should appear in Schoolbox. Classes matching these filetypes will appear. Leave this field empty to disable filtering.',
    (
        SELECT id FROM lu_config_field
        WHERE field = 'input'
    ),
    '',
    (
        SELECT id FROM lu_config_type
        WHERE type = 'External DB'
    ),
    'true',
    'false',
    'false',
    'settings'
);
