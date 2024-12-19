
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
    'otfs_s3_host',
    null,
    'The s3 endpoint for other s3 providers',
    'The s3 endpoint for other s3 providers',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    null,
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'true',
    'false',
    'settings',
    null
);
