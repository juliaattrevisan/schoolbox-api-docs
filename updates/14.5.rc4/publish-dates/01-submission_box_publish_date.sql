-- Add a publish date to submission boxes
ALTER TABLE `submission_box` ADD COLUMN `publish_date` DATETIME NULL AFTER `task_instance_id`;
