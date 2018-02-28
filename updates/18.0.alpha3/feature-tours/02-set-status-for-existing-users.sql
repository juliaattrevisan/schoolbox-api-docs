INSERT INTO `tour_status`
(`tour_uuid`, `user_id`, `status`,`created_at`)
SELECT `uuid`, `user`.`id`, 'disabled', NOW()
FROM `tour`, `user`
LEFT JOIN `login_log` ON `login_log`.`username` = `user`.`username` AND `login_log`.`login` = 1
GROUP BY `login_log`.`username`
HAVING COUNT(`login_log`.`username`) >= 3;