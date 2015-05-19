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
    'external_tass_app_code_parent_lounge',
    '',
    'TASS Parent Lounge App Code',
    'App code for TASS Parent Lounge (required for generating a SSO token)',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
), (
    'external_tass_app_code_tassweb',
    '',
    'TASSWeb App Code',
    'App code for TASSWeb (required for generating a SSO token)',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
), (
    'external_tass_app_code_teacherkiosk',
    '',
    'TASS Teacher Kiosk App Code',
    'App code for TASS Teacher Kiosk (required for generating a SSO token)',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
), (
    'external_tass_app_code_webbook',
    '',
    'TASS WebBook Code',
    'App code for TASS WebBook (required for generating a SSO token)',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
);
