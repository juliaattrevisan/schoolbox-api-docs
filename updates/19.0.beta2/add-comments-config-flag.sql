INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`)
        VALUES (
        'comments_module',
        '0',
        'Allow comments module globally',
        'If enabled, users will be able to post new comments or moderate if they have the rights',
        '3',
        'true:false',
        '5',
        'true',
        'false',
        'false',
        'settings');
