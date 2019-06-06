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
        'Enable/Disable comments module globally',
        'If enabled, comments module will be available',
        '3',
        'true:false',
        '5',
        'true',
        'false',
        'false',
        'settings');
