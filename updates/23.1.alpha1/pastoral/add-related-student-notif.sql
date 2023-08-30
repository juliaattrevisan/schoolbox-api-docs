-- add new messages to msg_source
INSERT INTO `msg_source` (`namespace`,
                          `key`,
                          `role_type`,
                          `description`)
VALUES ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral'),
        'createdRelated',
        'staff',
        'a student you teach has a {:pastoral} record created'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral'),
        'updatedRelated',
        'staff',
        'a student you teach has a {:pastoral} record updated'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral_comment'),
        'createdRelated',
        'staff',
        'a student you teach has a {:pastoral} record comment created'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral_comment'),
        'updatedRelated',
        'staff',
        'a student you teach has a {:pastoral} record comment updated')
;

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT `msg_source`.`id`,
       `groups`.`id`,
       'MU,DI,IN,MO,EM',
       'IN'
FROM `msg_source`
         JOIN `groups` ON `msg_source`.`role_type` = `groups`.`group_type`
         JOIN `msg_namespace` ON `msg_source`.`namespace` = `msg_namespace`.`id`
WHERE `msg_namespace`.`slug` IN ('pastoral', 'pastoral_comment')
  AND `key` IN ('createdRelated', 'updatedRelated')
  AND `msg_source`.`role_type` = 'staff';

-- Fixing extra periods in the current messages
UPDATE `msg_source`
SET `description` = REGEXP_REPLACE(`description`, '\.$', '')
WHERE `description` LIKE '%.';
