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
    'redis_ephemeral_url',
    '',
    'Redis Ephemeral URL',
    'The URL for Ephemeral Redis server',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'resources'),
    'false',
    'true',
    'false'
);
