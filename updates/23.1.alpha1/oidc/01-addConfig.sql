INSERT INTO `config`(
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
    `sequence`
) VALUES (
    'oauth_enable_sso',
    '',
    'Enable Single Sign-On via oAuth Identity Provider',
    'If enabled: enable users to be redirected to the configured identity provider when signing into Schoolbox via their browser. (Otherwise, the identity provider will only be used by other apps to create a token which creates a Schoolbox session.)',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'boolean'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT max(sequence) + 1 FROM `config` c WHERE module = 'oauth')
), (
    'oauth_sso_ip_blacklist',
    '',
    'oAuth Single Sign-On IP blacklist',
    'A comma separated list of CIDR IP ranges: if matched, the oAuth provider will not be automatically invoked.',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'input'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT max(sequence) + 1 FROM `config` c WHERE module = 'oauth')
), (
    'oauth_sso_ip_whitelist',
    '',
    'oAuth Single Sign-On IP whitelist',
    'A comma separated list of CIDR IP ranges: if matched, oAuth provider authentication will be forced. This can be used to force internal clients to use the oAuth provider, leaving external clients to select what login system they prefer.',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'input'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT max(sequence) + 1 FROM `config` c WHERE module = 'oauth')
)
