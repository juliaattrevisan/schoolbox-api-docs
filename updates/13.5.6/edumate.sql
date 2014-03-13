/* Have a relation between a submission box and a task
 * This allows us to prevent dupe submission boxes from being created
 */
CREATE TABLE `submission_box_edumate_task`( `submission_box_id` INT(11), `task_id` INT(11) );
ALTER TABLE `submission_box_edumate_task` ADD COLUMN `category_code` VARCHAR(255) NULL AFTER `task_id`;
ALTER TABLE `submission_box_edumate_task` ADD UNIQUE INDEX `task_code` (`task_id`, `category_code`);
ALTER TABLE `submission_box_edumate_task` ADD COLUMN `updated_at` DATETIME NULL AFTER `category_code`;

/* Have a relation between a submisison return and a task
 * This allows us to prevent respawns of return records during regular importing
 */
CREATE TABLE `submission_return_edumate_task`( `submission_return_id` INT(11), `task_id` INT(11), `category_code` VARCHAR(255) );
ALTER TABLE `submission_return_edumate_task` ADD UNIQUE INDEX `return_task` (`task_id`, `category_code`);
ALTER TABLE `submission_return_edumate_task` ADD COLUMN `updated_at` DATETIME NULL AFTER `category_code`;