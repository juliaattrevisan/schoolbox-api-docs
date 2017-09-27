-- A table which identifies a source from which an event was imported
CREATE TABLE `event_source`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_id` INT(6) UNSIGNED NOT NULL, `source_name` VARCHAR(255) NOT NULL, `edval_guid` VARCHAR(255) NOT NULL, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) ) ENGINE=INNODB CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Add foreign key
ALTER TABLE `event_source` CHANGE `event_id` `event_id` INT(6) UNSIGNED NOT NULL, ADD CONSTRAINT `fk_event_source_event_id_event_event_id` FOREIGN KEY (`event_id`) REFERENCES `event`(`id`) ON UPDATE NO ACTION ON DELETE NO ACTION;

-- Add unique index for (event_id, source, guid) to prevent dupe source records
ALTER TABLE `event_source` ADD UNIQUE INDEX `event_source_edval_guid` (`event_id`, `source_name`, `edval_guid`);
