INSERT INTO `config` (
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
    'rubicon_api_token',
    '',
    'Rubicon API Token',
    'Rubicon API token. Required for querying Rubicon''s curriculum.',
    2, -- textarea
    '',
    10, -- 3rd party services
    'false',
    'true',
    'true'
);
