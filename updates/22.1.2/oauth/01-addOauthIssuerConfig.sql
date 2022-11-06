# Add config
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
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    null,
    'true',
    'false',
    'false',
    'oauth',
    2
);

# and reorder existing
UPDATE config SET sequence=1 WHERE name='cloudwork_oauth_identity_provider';
UPDATE config SET sequence=3 WHERE name='cloudwork_oauth_client_id';
UPDATE config SET sequence=4 WHERE name='cloudwork_oauth_client_secret';
UPDATE config SET sequence=5 WHERE name='cloudwork_oauth_identifying_claims';
