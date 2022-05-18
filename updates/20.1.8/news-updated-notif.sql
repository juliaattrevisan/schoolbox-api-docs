INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news'),
    'updated', 'staff', 'somebody updates news');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news'),
    'updated', 'student', 'somebody updates news');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news'),
    'updated', 'parent', 'somebody updates news');
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'MU'             AS `role_default`
  FROM msg_source src
  LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news')
  AND src.role_type = 'staff'
  AND src.`key` = 'updated';
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'MU'             AS `role_default`
  FROM msg_source src
  LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news')
  AND src.role_type = 'student'
  AND src.`key` = 'updated';
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'MU'          AS `role_default`
  FROM msg_source src
  LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news')
  AND src.role_type = 'parent'
  AND src.`key` = 'updated';
