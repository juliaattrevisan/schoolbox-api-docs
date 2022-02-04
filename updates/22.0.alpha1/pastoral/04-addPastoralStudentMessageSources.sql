INSERT INTO msg_source (
    `namespace`,
    `key`,
    `role_type`,
    `description`
) VALUES (
    (SELECT id FROM msg_namespace WHERE slug = 'pastoral'),
    'created',
    'student',
    'a new {:pastoral} record has been created.'
), (
    (SELECT id FROM msg_namespace WHERE slug = 'pastoral'),
    'updated',
    'student',
    'an existing {:pastoral} record has been updated.'
), (
    (SELECT id FROM msg_namespace WHERE slug = 'pastoral_comment'),
    'created',
    'student',
    'a new {:pastoralCare} comment has been created.'
), (
    (SELECT id FROM msg_namespace WHERE slug = 'pastoral_comment'),
    'updated',
    'student',
    'an existing {:pastoralCare} comment has been updated.'
);
