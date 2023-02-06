INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'gotenberg_api_version',
    '7',
    'Gotenberg API version',
    'The version of the Gotenberg PDF translation service',
    (SELECT id FROM lu_config_field WHERE field = 'number'),
    '',
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'true',
    'settings',
    null
);
