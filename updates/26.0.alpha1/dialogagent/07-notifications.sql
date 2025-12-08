-- Set up message namespace
INSERT INTO msg_namespace (`slug`, `label`)
VALUES ('dialog_agent', '{:dialogAgent}');

-- Set up message source
INSERT INTO msg_source (`namespace`, `key`, `role_type`, `description`)
VALUES
    (
        (SELECT id FROM msg_namespace WHERE slug = 'dialog_agent' LIMIT 1),
        'reset',
        'staff',
        'a response to a {:dialogAgent} has been reset'
    ),
    (
        (SELECT id FROM msg_namespace WHERE slug = 'dialog_agent' LIMIT 1),
        'reopened',
        'staff',
        'a response to a {:dialogAgent} has been reopened'
    );

-- Default settings for Reset
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT (SELECT id
        FROM `msg_source`
        WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'dialog_agent')
          AND `key` = 'reset'
          AND `role_type` = 'staff'),
       `groups`.id,
       'MU,DI,IN,MO,EM',
       'IN'
FROM `groups`
WHERE `groups`.`group_type` = 'staff';

-- Default settings for Reopen
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT (SELECT id
        FROM `msg_source`
        WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'dialog_agent')
          AND `key` = 'reopened'
          AND `role_type` = 'staff'),
       `groups`.id,
       'MU,DI,IN,MO,EM',
       'IN'
FROM `groups`
WHERE `groups`.`group_type` = 'staff';
