-- Tasks can now be associated with multiple files

CREATE TABLE `task_file` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `storage_id` VARCHAR(100) CHARACTER SET 'ascii' NULL DEFAULT NULL,
    `task_id` INT(11) NOT NULL,
    `realname` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`));

-- Set up indexes

ALTER TABLE `task_file`
    ADD INDEX `task_id` (`task_id` ASC),
    ADD INDEX `storage_id` (`storage_id` ASC);

-- Set up foreign keys

ALTER TABLE `task_file`
    ADD CONSTRAINT `fk_task_file`
        FOREIGN KEY (`task_id`)
            REFERENCES `task` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;

ALTER TABLE `task_file`
    ADD CONSTRAINT `fk_task_file_storage`
        FOREIGN KEY (`storage_id`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;

-- Make backup copy of task.link into task._link

ALTER TABLE `task`
    ADD COLUMN `_link` VARCHAR(2048) NULL AFTER `link`;

UPDATE `task` SET _link = link;
