-- Set up message source
INSERT IGNORE INTO msg_source (`namespace`, `key`, `role_type`, `description`)
VALUES
    (
        (SELECT id FROM msg_namespace WHERE slug = 'dialog_agent' LIMIT 1),
        'locked',
        'staff',
        'a response to a {:dialogAgent} has been locked'
    );

-- Default settings for Locked trigger
INSERT IGNORE INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT (SELECT id
        FROM `msg_source`
        WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'dialog_agent')
          AND `key` = 'locked'
          AND `role_type` = 'staff'),
       `groups`.id,
       'MU,DI,IN,MO,EM',
       'IN'
FROM `groups`
WHERE `groups`.`group_type` = 'staff';
