ALTER TABLE `newsTopic`
    ADD COLUMN `updated_by` INT(11) UNSIGNED NULL AFTER `sequence`,
    ADD COLUMN `created_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `updated_by`,
    ADD COLUMN `updated_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `created_at`,
    ADD COLUMN `deleted_at` DATETIME         NULL AFTER `updated_at`;

ALTER TABLE `newsTopic`
    ADD CONSTRAINT `fk_newsTopic_id`
        FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
            ON DELETE NO ACTION ON UPDATE NO ACTION;

# backfill updated_by with Alaress user id
UPDATE `newsTopic`
SET `updated_by` = (SELECT `user`.`id` FROM `user` WHERE username LIKE 'alaress');
