-- Sources
INSERT INTO `msg_source` (
    `namespace`,
    `key`,
    `role_type`,
    `description`
  )
  VALUES (
  -- for wiki
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki' LIMIT 1),
    'revised',
    'staff',
    'somebody revises a wiki'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki' LIMIT 1),
    'revised',
    'student',
    'somebody revises a wiki'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki' LIMIT 1),
    'revised',
    'parent',
    'somebody revises a wiki'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki' LIMIT 1),
    'created',
    'staff',
    'somebody creates a wiki'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki' LIMIT 1),
    'created',
    'student',
    'somebody creates a wiki'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki' LIMIT 1),
    'created',
    'parent',
    'somebody creates a wiki'
  ), (
  -- for blog
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog' LIMIT 1),
    'commented',
    'parent',
    'somebody comments on a blog post you have written'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog' LIMIT 1),
    'followed',
    'parent',
    'somebody comments after you on a blog post'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog' LIMIT 1),
    'posted',
    'student',
    'somebody creates a blog post'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog' LIMIT 1),
    'posted',
    'staff',
    'somebody creates a blog post'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog' LIMIT 1),
    'posted',
    'parent',
    'somebody creates a blog post'
  ), (
  -- for duework
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'duework' LIMIT 1),
    'resubmitted',
    'staff',
    'a student resubmits work'
  ), (
    (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'duework' LIMIT 1),
    'deleted',
    'student',
    'a teacher removes your submitted work'
  )
ON DUPLICATE KEY UPDATE `id` = `id`;


-- Wiki > * > *
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 300 `max_level`, 200 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'wiki')
ON DUPLICATE KEY UPDATE `min_level` = 0, `max_level` = 300, `default_level` = 200;


-- Blog > Parent > *
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 300 `max_level`, 0 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog')
AND g.group_type = 'parent'
ON DUPLICATE KEY UPDATE `min_level` = 0, `max_level` = 300, `default_level` = 0;


-- Blog > Staff|Student > posted
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 300 `max_level`, 200 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'blog')
AND src.key = 'posted'
AND g.group_type IN ('staff', 'student')
ON DUPLICATE KEY UPDATE `min_level` = 0, `max_level` = 300, `default_level` = 200;


-- Submission > Staff > Resubmitted
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'duework')
AND src.key = 'resubmitted'
AND g.group_type IN ('staff')
ON DUPLICATE KEY UPDATE `min_level` = 0, `max_level` = 300, `default_level` = 250;


-- Submission > Student > Deleted
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'duework')
AND src.key = 'deleted'
AND g.group_type IN ('student')
ON DUPLICATE KEY UPDATE `min_level` = 0, `max_level` = 300, `default_level` = 250;


