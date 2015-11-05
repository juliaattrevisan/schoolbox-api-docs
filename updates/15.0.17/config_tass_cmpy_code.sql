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
    'external_tass_company_code',
    '01',
    'External TASS Company Code',
    'The TASS company code that your Schoolbox installation uses. Usually, this will be "01".',
    1, -- input
    NULL,
    7, -- external DB
    'true',
    (IF (@isTass, 'false', 'true')),
    'false'
);
