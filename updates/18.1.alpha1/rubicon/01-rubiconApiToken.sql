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
    'rubicon_atlas_api_token',
    '',
    'Rubicon Atlas API Token',
    'Rubicon Atlas API token. Required for retrieving curriculum plans stored in a Rubicon Atlas instance.',
    2, -- textarea
    '',
    10, -- 3rd party services
    'true',
    'false',
    'false'
);
