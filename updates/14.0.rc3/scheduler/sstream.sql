INSERT INTO `msg_namespace`(`id`,`slug`,`label`) VALUES
  (NULL, 'sstream', 'Social Stream');

INSERT INTO `msg_source` (`id`,`namespace`,`key`,`role_type`,`description`) VALUES
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'sstream'), 'posted',  'student', 'someone posts in a Social Stream'),
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'sstream'), 'posted',  'staff',   'someone posts in a Social Stream'),
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'sstream'), 'replied', 'student', 'someone replies to a post in a Social Stream'),
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'sstream'), 'replied', 'staff',   'someone replies to a post in a Social Stream');

/* Students and staff                                     min digest,      max email,       default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`,     `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`,            200 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'sstream')
AND role_type IN ('student', 'staff')
