-- Both Eportfolio Blog Posts and Comments will share this namespace
INSERT INTO msg_namespace (id, slug, label)
VALUES (NULL, 'eportfolio_blog', 'Eportfolio Blog');


-- Receive 'Eportfolio Blog' 'posted' messages when:
-- * student: someone else creates a blog post on your eportfolio page
-- * staff:   one of your students posts to their eportfolio blog
-- * parent:  your child posts to their eportfolio blog
INSERT INTO `msg_source`
(`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'posted', 'student', 'someone posts to your eportfolio blog'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'posted', 'staff',   'someone posts to your student\'s eportfolio blog'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'posted', 'parent',  'someone posts to your child\'s eportfolio blog');

-- Student (Instant + Email)
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'IN,EM'          AS `role_default`
  FROM msg_source src
  LEFT JOIN groups g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog')
  AND src.role_type = 'student'
  AND src.`key` = 'posted';

-- Staff (Mute)
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
  SELECT
    src.id           AS `msg_source_id`,
    g.id             AS `role_id`,
    'MU,DI,IN,MO,EM' AS `mask`,
    'MU'             AS `role_default`
  FROM msg_source src
  LEFT JOIN groups g ON src.`role_type` = g.`group_type`
  WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog')
  AND src.role_type = 'staff'
  AND src.`key` = 'posted';

-- Parent (Instant + Email)
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
  AND src.`key` = 'posted';


-- Receive 'Eportfolio Blog' 'commented' messages when:
-- * student: someone comments on a blog on your eportfolio page
-- * staff:   someone comments on a eportfolio post you authored
-- (Intentionally does not distinguish for students between posts they authored, and authored by staff)
INSERT INTO `msg_source`
(`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'commented', 'student', 'someone comments on your eportfolio blog'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'commented', 'staff',   'someone comments on an eportfolio blog post you authored');

-- Receive 'Eportfolio Blog' 'followed' messages when:
-- * staff/student/parent: someone comments after you on a eportfolio blog post.
INSERT INTO `msg_source`
(`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'followed', 'student', 'someone comments after you on an eportfolio blog post'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'followed', 'staff',   'someone comments after you on an eportfolio blog post'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'followed', 'parent',  'someone comments after you on an eportfolio blog post');
