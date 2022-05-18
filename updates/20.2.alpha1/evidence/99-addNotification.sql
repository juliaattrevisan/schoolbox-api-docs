INSERT INTO `msg_namespace` (`slug`, `label`)
  VALUES ('evidence', 'Learning Moments');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence'),
    'posted', 'staff', 'a student posted a learning moment');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence'),
    'posted', 'student', 'a teacher posted a learning moment for you');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence'),
    'posted', 'parent', 'a learning moment is posted for your child');
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN'          AS `role_default`
  FROM msg_source src
  LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence')
  AND src.role_type = 'staff'
  AND src.`key` = 'posted';
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN'          AS `role_default`
  FROM msg_source src
  LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence')
  AND src.role_type = 'student'
  AND src.`key` = 'posted';
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN'          AS `role_default`
  FROM msg_source src
  LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'evidence')
  AND src.role_type = 'parent'
  AND src.`key` = 'posted';
