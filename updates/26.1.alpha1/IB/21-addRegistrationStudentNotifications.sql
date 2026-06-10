-- Add notifications namespace for IB registration
INSERT IGNORE INTO msg_namespace (`slug`, `label`)
VALUES ('ib_registration', 'IB registration');

-- Add notification for unconfirmed IB registration
INSERT IGNORE INTO msg_source (`namespace`, `key`, `role_type`, `description`)
VALUES (
    (SELECT id FROM msg_namespace WHERE slug = 'ib_registration' LIMIT 1),
    'unconfirmed',
    'student',
    'an IB registration is ready to review'
);

-- Add notification permissions
INSERT IGNORE INTO msg_route_perm (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT (
        SELECT id
        FROM msg_source
        WHERE namespace = (SELECT id FROM msg_namespace WHERE slug = 'ib_registration')
          AND `key` = 'unconfirmed'
          AND role_type = 'student'
    ),
    `groups`.id,
    'MU,DI,IN,MO,EM',
    'IN'
FROM `groups`
WHERE `groups`.`group_type` = 'student';
