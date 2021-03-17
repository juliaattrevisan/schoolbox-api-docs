-- The oAuth configs are not strictly related to Cloudwork anymore.
-- Keep their names the same though.

UPDATE config
SET title       = 'oAuth Identity Provider URL',
    description = 'The URL of your identity provider. For Cloudwork, this will likely be in the form "https://yourschool-id.cloudworkengine.net/".',
    sequence    = 1
WHERE name = 'cloudwork_oauth_identity_provider';

UPDATE config
SET title       = 'oAuth Client ID',
    description = 'The client ID for your Schoolbox oAuth App.',
    sequence    = 2
WHERE name = 'cloudwork_oauth_client_id';

UPDATE config
SET title       = 'oAuth Client Secret',
    description = 'The client secret for your Schoolbox oAuth App.',
    sequence    = 3
WHERE name = 'cloudwork_oauth_client_secret';

