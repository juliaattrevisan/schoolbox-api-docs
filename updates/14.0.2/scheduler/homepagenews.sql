/* Everyone                                                    min off,         max email,       default email */
INSERT INTO   `msg_route_perm` (`msg_source_id`, `role_id`,   `min_level`,     `max_level`,     `default_level`)
SELECT src.id `msg_source_id`,              g.id `role_id`, 0 `min_level`, 300 `max_level`, 200 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'news')
AND `key` = 'published'
ON DUPLICATE KEY UPDATE `min_level` = 0, `max_level` = 300, `default_level` = 200;