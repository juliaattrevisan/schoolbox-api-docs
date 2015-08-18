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
    'external_tass_sso_parent_code',
    'TASS ID',
    'External TASS Parent SSO Code',
    'Sets whether to send a parent\'s username or TASS ID as the SSO code for authentication',
    4, -- select
    'TASS ID:Username',
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
);
