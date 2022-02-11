-- Add message sources for students to receive notifications about pastoral
-- records.
-- Don't add notifs for comments yet.
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
);
