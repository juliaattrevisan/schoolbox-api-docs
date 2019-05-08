INSERT INTO `schoolbox`.`config` (
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
        '3',
        'true:false',
        '5',
        'true',
        'false',
        'false',
        'settings');
