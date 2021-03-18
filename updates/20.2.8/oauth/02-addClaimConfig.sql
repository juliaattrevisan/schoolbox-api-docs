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
    sequence,
    module
) VALUES (
    'cloudwork_oauth_identifying_claims',
    'preferred_username',
    'oAuth Identifying Claims',
    'A comma-separated list of claims within the JWT provided by the identity provider which may identify a user; one of these claim''s values must match the username of a Schoolbox user. Common values are "preferred_username", "upn" and "unique_name".',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    NULL,
    'true',
    'false',
    'false',
    4,
    'oauth'
);
