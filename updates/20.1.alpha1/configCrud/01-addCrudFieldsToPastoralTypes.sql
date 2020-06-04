ALTER TABLE `lu_pastoral_type`
    ADD COLUMN `sequence`   INT(11) UNSIGNED NOT NULL AFTER `external_code`,
    ADD COLUMN `updated_by` INT(11) UNSIGNED NULL AFTER `sequence`,
    ADD COLUMN `created_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `updated_by`,
    ADD COLUMN `updated_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `created_at`;

ALTER TABLE `lu_pastoral_type`
    ADD CONSTRAINT `fk_lu_pastoral_type_user_id`
        FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
            ON DELETE NO ACTION ON UPDATE NO ACTION;

# backfill sequence with id to mimic its current ordering
UPDATE `lu_pastoral_type` SET `sequence` = `id`;

# backfill updated_by with Alaress user id
UPDATE `lu_pastoral_type` SET `updated_by` = (SELECT `user`.`id` FROM `user` WHERE username LIKE 'alaress');

# Prevent updated_by being nullable
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `lu_pastoral_type` MODIFY COLUMN `updated_by` INT(11) UNSIGNED NOT NULL;
SET FOREIGN_KEY_CHECKS = 1;
