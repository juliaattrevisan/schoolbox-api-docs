/* add new column to link up */
ALTER TABLE `task_instance_completion`
  ADD COLUMN `task_id` INT(11) NULL AFTER `id`;
