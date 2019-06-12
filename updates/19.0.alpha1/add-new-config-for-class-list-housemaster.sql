INSERT INTO config (
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
        'email_housemasters',
        '1',
        'Allow Email Housemasters from Class List',
        'If ticked, allows staff to email housemasters of a class with a single button',
        (SELECT lcf.id FROM lu_config_field lcf WHERE lcf.field = 'boolean'),
        'true:false',
        (SELECT lct.id FROM lu_config_type lct WHERE lct.type = 'Policy'),
        'true',
        'false',
        'false',
        'settings');
