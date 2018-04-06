INSERT INTO `tour_status`
(`tour_uuid`, `user_id`, `status`,`created_at`)
SELECT `uuid`, `user`.`id`, 'disabled', NOW()
FROM `tour`, `user`
LEFT JOIN `login_log` ON `login_log`.`username` = `user`.`username` AND `login_log`.`login` = 1

WHERE NOT EXISTS (SELECT * FROM `tour_status` ts where `ts`.`user_id` = `user`.`id` and `ts`.`tour_uuid` = `tour`.`uuid`)

GROUP BY tour.uuid, `login_log`.`username`
HAVING COUNT(`login_log`.`username`) >= 3;
