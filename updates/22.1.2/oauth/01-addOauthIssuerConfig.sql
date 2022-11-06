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
    'oauth_issuer',
    '',
    'oAuth Issuer',
    'The issuer for your Schoolbox oAuth App',
    (SELECT id FROM lu_config_field WHERE name = 'input'),
    '',
    null,
    'true',
    'false',
    'false',
    'oauth',
    1
);
