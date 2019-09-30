-- Support can configure the Spouse Prefix for TASS users
-- Default is 'S'
INSERT IGNORE INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
    'external_tass_spouse_prefix',
    'S',
    'TASS Spouse Prefix',
    'Prefix for a spouse external id',
    (
        SELECT id FROM lu_config_field
        WHERE field = 'input'
    ),
    null,
    (
        SELECT id FROM lu_config_type
        WHERE type = 'External DB'
    ),
    'false',
    'true',
    'true',
    'settings'
);
