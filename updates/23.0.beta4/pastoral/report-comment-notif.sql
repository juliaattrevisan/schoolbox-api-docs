-- add new message to msg_source
INSERT INTO `msg_source` (`namespace`,
                          `key`,
                          `role_type`,
                          `description`)
VALUES ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral_comment'),
        'addedToReport',
        'staff',
        'a new {:pastoralCare} comment has been created for a record in an existing report.');
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT (SELECT id
        FROM `msg_source`
        WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral_comment')
          AND `key` = 'addedToReport'
          AND `role_type` = 'staff'),
       `groups`.id,
       'MU,DI,IN,MO,EM',
       'IN'
FROM `groups`
WHERE `groups`.`group_type` = 'staff';
