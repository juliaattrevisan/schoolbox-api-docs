insert into config (
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
)
VALUES (
    'cloudwork_oauth_identity_provider',
    '',
    'Cloudwork oAuth Identity Provider URL',
    'The URL of your Cloudwork identity provider. Will likely be in the form "https://yourschool-id.cloudworkengine.net/".',
    1, -- input
    '',
    21, -- SAML? will probably change this
    'true',
    'false',
    'false'
), (
    'cloudwork_oauth_client_id',
    '',
    'Cloudwork oAuth Client ID',
    'The client ID for your Schoolbox oAuth App within Cloudwork',
    1, -- input
    '',
    21, -- SAML? will probably change this
    'true',
    'false',
    'false'
),  (
    'cloudwork_oauth_client_secret',
    '',
    'Cloudwork oAuth Client Secret',
    'The client secret for your Schoolbox oAuth App within Cloudwork',
    1, -- input
    '',
    21, -- SAML? will probably change this
    'true',
    'false',
    'false'
),  (
    'cloudwork_oauth_jwt_key_id',
    '',
    'Cloudwork oAuth JWT Key ID',
    'The ID of the key that is used to decode a JWT from Cloudwork. It is planned that this will auto-detect in the future.',
    1, -- input
    '',
    21, -- SAML? will probably change this
    'true',
    'false',
    'false'
);
