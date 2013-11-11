-- Add task instance IDs to submission box and assess, so that they can be
-- related back to tasks?
-- Prob not required as it's a 1-1 relation, though these tables have a task ID?
ALTER TABLE `submission_box`
  ADD COLUMN `task_instance_id` INT(11) NULL DEFAULT NULL AFTER `task_id`,
  ADD INDEX `submission_box_task_instance_id` (`task_instance_id` ASC)
;
ALTER TABLE `assess`
  ADD COLUMN `task_instance_id` INT(11) NULL DEFAULT NULL AFTER `task_id`,
  ADD INDEX `assess_task_instance_id` (`task_instance_id` ASC)
;
