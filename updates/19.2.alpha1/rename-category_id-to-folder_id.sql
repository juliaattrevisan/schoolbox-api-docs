# Rename column name
ALTER TABLE `event_folder`
    CHANGE COLUMN `category_id` `folder_id` INT(11) UNSIGNED;

# Update foreign keys
ALTER TABLE `event_folder`
    DROP FOREIGN KEY `fk_event_category_folder_id`;
ALTER TABLE `event_folder`
    ADD CONSTRAINT `fk_event_folder_folder_id`
        FOREIGN KEY (`folder_id`)
            REFERENCES `folder` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;

ALTER TABLE `event_folder`
    DROP FOREIGN KEY `fk_event_category_event_id`;
ALTER TABLE `event_folder`
    ADD CONSTRAINT `fk_event_folder_event_id`
        FOREIGN KEY (`event_id`)
            REFERENCES `event` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;

# Update indexes
ALTER TABLE `event_folder`
    DROP INDEX `event_category_id` ,
    ADD UNIQUE INDEX `event_folder_id` (`event_id` ASC, `folder_id` ASC);

ALTER TABLE `event_folder`
    DROP INDEX `category_id` ,
    ADD INDEX `folder_id` (`folder_id` ASC);
