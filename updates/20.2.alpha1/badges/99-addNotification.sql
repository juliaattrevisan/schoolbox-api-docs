-- Name space
INSERT INTO `msg_namespace` (`slug`, `label`)
VALUES ('badge', 'Badges');
-- Message source
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
VALUES ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge'),
        'awarded', 'staff', 'a student has been awarded an achievement'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge'),
        'awarded', 'student', 'you are awarded an achievement'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge'),
        'awarded', 'parent', 'your child has been awarded an achievement'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge'),
        'goalSet', 'staff', 'a student has a new goal'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge'),
        'goalSet', 'student', 'a goal is set for you'),
       ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge'),
        'goalSet', 'parent', 'your child has a new goal');
-- Set role defaults for awarded
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT src.id           AS `msg_source_id`,
       g.id             AS `role_id`,
       'MU,DI,IN,MO,EM' AS `mask`,
       'IN'          AS `role_default`
FROM msg_source src
         LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'badge')
  AND src.role_type IN ('staff', 'student', 'parent')
  AND src.`key` IN ('awarded', 'goalSet');
