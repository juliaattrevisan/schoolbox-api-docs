INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'comms_comments_enabled',
    '1',
    'Enable comments on news articles',
    'If enabled, users may comment on news articles',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    NULL,
    'false',
    'true',
    'false'
);
