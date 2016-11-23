-- add new column to link up
-- Rename 'completion_date' to 'created_at'
-- Add missing timestamps
ALTER TABLE `task_instance_completion`
  ADD COLUMN `task_id` INT(11) NULL AFTER `id`,
  CHANGE COLUMN `completion_date` `created_at` DATETIME NULL DEFAULT NULL AFTER `completion_status`,
  ADD COLUMN `updated_at` DATETIME NOT NULL,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL;

