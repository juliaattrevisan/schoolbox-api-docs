-- task instance components now have a marking user attached.
ALTER TABLE `task_instance_completion` ADD COLUMN `marking_user_id` INT(11) NULL DEFAULT NULL AFTER `student_id`;
ALTER TABLE `task_instance_completion` ADD INDEX  `task_instance_completion_marking_user_id` (`marking_user_id` ASC);
