-- Is this a TASS school?
SELECT @isTass := COUNT(*)
FROM `config`
WHERE `name` = 'external_type' AND `value` = 'tass'
LIMIT 1;

INSERT INTO `config`(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`
)
VALUES (
    'external_tass_sso_server',
    '',
    'External TASS SSO Server',
    'SSO Server for TASSWeb services. (Enter your TASSWeb URL in "External Management Portal".)',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
), (
    'external_tass_sso_token_key',
    '',
    'External TASS SSO Token Key',
    'SSO Token Key for TASSWeb services',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
);
