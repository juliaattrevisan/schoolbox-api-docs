-- Clone all staff sstream/forum notifs for parents
INSERT IGNORE INTO msg_source (`namespace`, `key`, `role_type`, `description`)
    SELECT DISTINCT
        `n`.`id`,
        `s`.`key`,
        'parent',
        `s`.`description`

    FROM msg_source s
        INNER JOIN msg_namespace n ON s.namespace=n.id
    WHERE n.slug IN ('sstream', 'forum')
    AND s.role_type = 'staff';
