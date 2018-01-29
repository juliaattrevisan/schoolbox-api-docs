INSERT INTO `tour_status`
(`current_step`, `user_id`, `status`)
SELECT `first_step_uuid`, `user`.`id`, 'hard_set'
FROM `tour`, `user`
LEFT JOIN `login_log` ON `login_log`.`username` = `user`.`username` AND `login_log`.`login` = 1
GROUP BY `login_log`.`username`
HAVING COUNT(`login_log`.`username`) >= 3;