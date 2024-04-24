SELECT @inputField := id FROM lu_config_field WHERE field = 'input';

# Slot these new configs in after cloudwork_oauth_client_secret
SELECT @inputSequence := sequence FROM config WHERE name='cloudwork_oauth_client_secret';
UPDATE config SET sequence = sequence + 2 WHERE module='oauth' AND sequence > @inputSequence;

# And insert
INSERT INTO config (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    sequence
) VALUES (
    'oauth_response_type',
    'code id_token',
    'oAuth Identity Provider Response Type',
    'The response_type value to request from the oAuth identity provider. Should usually be "code id_token" to request an authorization code and an identity token.',
    @inputField,
    null,
    'true',
    'false',
    'false',
    'oauth',
    @inputSequence + 1
), (
    'oauth_scopes',
    'openid email profile',
    'oAuth Identity Provider Scopes',
    'The scopes to request from the oAuth identity provider. Should usually be "openid email profile" to request an identity token, email address and profile data, but may also include scopes which contain custom claims from your identity provider; these scopes must provide the claims in "oAuth Identifying Claims".',
    @inputField,
    null,
    'true',
    'false',
    'false',
    'oauth',
    @inputSequence + 2
);
