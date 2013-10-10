-- task instances can now be created independently of a base task.
ALTER TABLE `task_instance` CHANGE COLUMN `task_id` `task_id` INT(11) NULL;
