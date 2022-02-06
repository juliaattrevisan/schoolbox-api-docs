UPDATE config
SET options = 'url;Attempt to determine from oAuth Identity Provider URL:cloudwork;Cloudwork:azure;Azure:azureb2c;Azure B2C:adfs;ADFS:generic;Generic'
WHERE name = 'cloudwork_oauth_identity_provider_type';

