/* Have a relation between a submission box and a task
 * This allows us to prevent dupe submission boxes from being created
 */
CREATE TABLE `submission_box_edumate_task`( `submission_box_id` INT(11), `task_id` INT(11) );
ALTER TABLE `submission_box_edumate_task` ADD COLUMN `category_code` VARCHAR(255) NULL AFTER `task_id`;