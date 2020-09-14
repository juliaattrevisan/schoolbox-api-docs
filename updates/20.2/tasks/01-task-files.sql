-- Tasks can now be associated with multiple files

CREATE TABLE `task_file` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `storage_id` VARCHAR(100) NULL DEFAULT NULL,
    `task_id` INT(10) UNSIGNED NOT NULL,
    `realname` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id`));

-- Set up indexes

ALTER TABLE `task_file`
    ADD INDEX `task_id` (`task_id` ASC),
    ADD INDEX `storage_id` (`storage_id` ASC),
    ADD UNIQUE INDEX `storage_id_task_id` (`storage_id` ASC, `task_id` ASC);
