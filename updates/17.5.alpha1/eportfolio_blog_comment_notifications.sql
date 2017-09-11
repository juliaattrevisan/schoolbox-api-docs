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
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'posted', 'staff',   'your student posts to their eportfolio blog'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'posted', 'parent',  'your child posts to their eportfolio blog');

-- Receive 'Eportfolio Blog' 'commented' messages when:
-- * student: someone comments on a blog on your eportfolio page
-- * staff:   someone comments on a eportfolio post you authored
-- (Intentionally does not distinguish for students between posts they authored, and authored by staff)
INSERT INTO `msg_source`
(`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'commented', 'student', 'someone comments on a blog on your Eportfolio'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'commented', 'staff',   'someone comments on a eportfolio blog post you authored');

-- Receive 'Eportfolio Blog' 'followed' messages when:
-- * staff/student/parent: someone comments after you on a eportfolio blog post.
INSERT INTO `msg_source`
(`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'followed', 'student', 'someone comments after you on a eportfolio blog post'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'followed', 'staff',   'someone comments after you on a eportfolio blog post'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eportfolio_blog'), 'followed', 'parent',  'someone comments after you on a eportfolio blog post');
