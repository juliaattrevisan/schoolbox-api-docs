-- Allow the identity provider type to be set directly.
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
    'cloudwork_oauth_identity_provider_type',
    'url',
    'oAuth Identity Provider Type',
    'The type of oAuth identity provider. Different providers may verify the JWT''s validity in a slightly different way.',
    7, -- select
    'url;Attempt to determine from oAuth Identity Provider URL:cloudwork;Cloudwork:azure;Azure:adfs;ADFS:generic;Generic',
    'true',
    'false',
    'false',
    'oauth'
);

-- Also re-sequence existing oAuth config options
UPDATE config SET sequence = 1 WHERE name = 'cloudwork_oauth_identity_provider_type';
UPDATE config SET sequence = 2 WHERE name = 'cloudwork_oauth_identity_provider';
UPDATE config SET sequence = 3 WHERE name = 'cloudwork_oauth_client_id';
UPDATE config SET sequence = 4 WHERE name = 'cloudwork_oauth_client_secret';
