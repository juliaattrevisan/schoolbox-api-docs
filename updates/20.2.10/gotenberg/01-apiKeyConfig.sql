INSERT INTO config(
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'gotenberg_api_key',
    '',
    'Gotenberg API key',
    'The API key for the Gotenberg PDF translation service',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'true'
);
