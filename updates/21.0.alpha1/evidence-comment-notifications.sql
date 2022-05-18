INSERT INTO `msg_namespace` (`slug`, `label`) VALUES
('evidence_comment', 'Learning Moments Comment');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
VALUES (
           (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence_comment'),
           'commented', 'staff', 'someone comments on a learning moment you have posted');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
VALUES (
           (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence_comment'),
           'commented', 'student', 'someone comments on your learning moment');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
VALUES (
           (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence_comment'),
           'commented', 'parent', 'someone comments on your child''s learning moment');

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
FROM msg_source src
         LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence_comment')
  AND src.role_type = 'staff'
  AND src.`key` = 'commented';

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
FROM msg_source src
         LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence_comment')
  AND src.role_type = 'student'
  AND src.`key` = 'commented';

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'MU'             AS `role_default`
FROM msg_source src
         LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence_comment')
  AND src.role_type = 'parent'
  AND src.`key` = 'commented';
