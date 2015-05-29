INSERT INTO `msg_source` (
    `namespace`,
    `key`,
    `role_type`,
    `description`
) VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'duework'),
    'updated',
    'parent',
    'a submission box in your child\'s class is updated'
);
