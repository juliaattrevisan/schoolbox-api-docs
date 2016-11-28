-- add new column to link up
-- Add soft-delete column
ALTER TABLE `task_instance_completion`
  ADD COLUMN `task_id` INT(11) NULL AFTER `id`,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL;

