# JWT private and public keys.
# Not sure if config is the appropriate place for these.
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
    'gotenberg_host',
    'http://localhost:3000',
    'Gotenberg host',
    'The URL for the Gotenberg PDF translation service',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'true'
);
