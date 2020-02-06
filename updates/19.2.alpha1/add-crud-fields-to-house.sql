ALTER TABLE `house`
    ADD COLUMN `sequence`   INT(11) UNSIGNED NOT NULL AFTER `house`,
    ADD COLUMN `updated_by` INT(11) UNSIGNED NULL AFTER `sequence`,
    ADD COLUMN `created_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `updated_by`,
    ADD COLUMN `updated_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `created_at`,
    ADD COLUMN `deleted_at` DATETIME         NULL AFTER `updated_at`;

ALTER TABLE `house`
    ADD CONSTRAINT `fk_house_user_id`
        FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
            ON DELETE NO ACTION ON UPDATE NO ACTION;

# backfill sequence with id to mimic its current ordering
UPDATE `house`
SET `house`.sequence = `house`.id;

# backfill updated_by with Alaress user id
UPDATE `house`
SET `updated_by` = (SELECT `user`.`id` FROM `user` WHERE username LIKE 'alaress');

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `house`
    MODIFY COLUMN `updated_by` INT(11) UNSIGNED NOT NULL;
SET FOREIGN_KEY_CHECKS = 1;
