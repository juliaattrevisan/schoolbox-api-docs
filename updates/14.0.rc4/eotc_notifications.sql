INSERT INTO `msg_namespace` (`id`,`slug`,`label`) VALUES
  (NULL, 'eotc', 'EOTC');

INSERT INTO `msg_source`
  (`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'submitted',     'staff', 'someone submits an EOTC'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'statuschanged', 'staff', 'a moderator changes the status of your EOTC'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'updated',       'staff', 'someone updates or coments on your EOTC');

/* Students and staff                                        min off,         max email,       default email */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`,            0 `min_level`, 300 `max_level`, 300 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc')
AND role_type = 'staff'
ON DUPLICATE KEY
  UPDATE `msg_source_id` = `msg_source_id`;