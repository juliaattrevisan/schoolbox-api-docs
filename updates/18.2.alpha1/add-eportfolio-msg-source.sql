INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'),
    'blogCommented', 'staff', 'someone comments on your eportfolio blog');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'),
    'blogCommented', 'parent', 'someone comments on your eportfolio blog');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
     (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'),
     'blogPosted', 'staff', 'someone posts to your eportfolio blog');
INSERT INTO `msg_source` (`namespace`, `key`, `role_type`, `description`)
  VALUES (
     (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'),
     'blogPosted', 'parent', 'someone posts to your eportfolio blog');

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
  FROM msg_source src
  LEFT JOIN groups g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog')
  AND src.role_type = 'staff'
  AND src.`key` = 'blogCommented';

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
  FROM msg_source src
  LEFT JOIN groups g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog')
  AND src.role_type = 'staff'
  AND src.`key` = 'blogPosted';

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
  FROM msg_source src
  LEFT JOIN groups g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog')
  AND src.role_type = 'parent'
  AND src.`key` = 'blogCommented';

INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
  FROM msg_source src
  LEFT JOIN groups g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog')
  AND src.role_type = 'parent'
  AND src.`key` = 'blogPosted';
