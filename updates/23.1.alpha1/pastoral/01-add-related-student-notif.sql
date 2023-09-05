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

-- Fixing extra periods in the current messages
UPDATE `msg_source`
SET `description` = REGEXP_REPLACE(`description`, '\.$', '')
WHERE `description` LIKE '%.';
