-- task instances now have a creating user attached.
ALTER TABLE `task_instance` ADD COLUMN `user_id` INT(11) NOT NULL AFTER `box_id`;
ALTER TABLE `task_instance` ADD INDEX  `task_instance_user_id` (`user_id` ASC);
