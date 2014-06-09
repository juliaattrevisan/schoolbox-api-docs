INSERT INTO `msg_namespace`(`id`,`slug`,`label`) VALUES
  (NULL, 'report_card', 'Grades');

INSERT INTO `msg_source` (`id`,`namespace`,`key`,`role_type`,`description`) VALUES
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'report_card'), 'commented', 'student', 'someone comments on your grades'),
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'report_card'), 'commented', 'staff',   'someone comments on one of your students\' grades'),
  (NULL, (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'report_card'), 'commented', 'parent',  'someone comments on your child\'s grades');

/* Students and staff                                     min off,       max email,       default email */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`,            0 `min_level`, 300 `max_level`, 300 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'report_card');
