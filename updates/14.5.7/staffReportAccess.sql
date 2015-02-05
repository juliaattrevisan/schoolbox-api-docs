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
    'report_staff_access',
    '0',
    'Allow Parents Into Staff Directory',
    'If this setting is on, parents role types will have access into the staff directory.  Otherwise its only accessible to staff.',
    3, -- checkbox
    NULL,
    5, -- policy
    'true',
    'false',
    'false'
);
