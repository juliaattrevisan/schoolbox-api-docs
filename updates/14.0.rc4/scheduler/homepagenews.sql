INSERT INTO msg_source
  (`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT id FROM msg_namespace WHERE slug = 'news'), 'published', 'student', 'someone posts homepage news'),
  ((SELECT id FROM msg_namespace WHERE slug = 'news'), 'published', 'staff',   'someone posts homepage news'),
  ((SELECT id FROM msg_namespace WHERE slug = 'news'), 'published', 'parent',  'someone posts homepage news');

/* Everyone                                                    min off,         max email,       default email */
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 200 `max_level`, 300 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news')
AND `key` = 'published';
