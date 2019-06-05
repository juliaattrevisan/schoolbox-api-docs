insert into config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    editable,
    hidden,
    required,
    module
)
VALUES (
    'cloudwork_oauth_identity_provider',
    '',
    'Cloudwork oAuth Identity Provider URL',
    'The URL of your Cloudwork identity provider. Will likely be in the form "https://yourschool-id.cloudworkengine.net/".',
    1, -- input
    '',
    'true',
    'false',
    'false',
    'oauth'
), (
    'cloudwork_oauth_client_id',
    '',
    'Cloudwork oAuth Client ID',
    'The client ID for your Schoolbox oAuth App within Cloudwork',
    1, -- input
    '',
    'true',
    'false',
    'false',
    'oauth'
), (
    'cloudwork_oauth_client_secret',
    '',
    'Cloudwork oAuth Client Secret',
    'The client secret for your Schoolbox oAuth App within Cloudwork',
    1, -- input
    '',
    'true',
    'false',
    'false',
    'oauth'
);
