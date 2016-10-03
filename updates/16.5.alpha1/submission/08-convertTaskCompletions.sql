/* add new column to link up */
ALTER TABLE `task_instance_completion`
  ADD COLUMN `task_id` INT(11) NULL AFTER `id`;

/* inject linked data */
UPDATE task_instance_completion c
SET task_id = (
    SELECT t.id
    FROM task_instance i
    LEFT JOIN task t ON i.tasK_id = t.id
    WHERE c.task_instance_id = i.id
);

/* rename table */
RENAME TABLE `task_instance_completion` TO `task_completion`;

/* drop now-unnecessary column */
ALTER TABLE `task_completion`
  DROP COLUMN `task_instance_id`,
  DROP INDEX `task_instance_completion_task_instance_id`;

/* remove column nullability, add indices and foreign key */
ALTER TABLE `task_completion`
  CHANGE `task_id` `task_id` INT(11) NOT NULL,
  ADD INDEX (`task_id`),
  ADD FOREIGN KEY (`task_id`) REFERENCES `task`(`id`)
    ON UPDATE NO ACTION
    ON DELETE NO ACTION;
