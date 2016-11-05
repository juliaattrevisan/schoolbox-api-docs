/* rename table */
RENAME TABLE `task_instance_completion` TO `task_completion`;

/* drop now-unnecessary column */
ALTER TABLE `task_completion`
  DROP COLUMN `task_instance_id`,
  DROP INDEX `task_instance_completion_task_instance_id`;

/* remove Quiz + DueWork task references */
DELETE FROM `task_completion` WHERE `task_id` IS NULL;

/* remove column nullability, add indices and foreign key */
ALTER TABLE `task_completion`
  CHANGE `task_id` `task_id` INT(11) NOT NULL,
  ADD INDEX (`task_id`),
  ADD FOREIGN KEY (`task_id`) REFERENCES `task`(`id`)
    ON UPDATE NO ACTION
    ON DELETE NO ACTION;

/* drop harvested table */
DROP TABLE `task_instance`;

/* Remove tasks that are quiz or due work */
DELETE FROM task where assess_id is not null OR box_id is not null

