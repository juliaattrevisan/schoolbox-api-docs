INSERT INTO `msg_source` (
    `namespace`,
    `key`,
    `role_type`,
    `description`
) VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'assess'),
    'updated',
    'parent',
    'a quiz in your child\'s class is updated'
);
