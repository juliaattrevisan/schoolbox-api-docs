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
    'ir_url',
    'https://manage.schoolbox.cloud',
    'Instance Registry URL',
    'The URL for Instance Registry server',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE type = 'resources'),
    'false',
    'true',
    'true'
);
