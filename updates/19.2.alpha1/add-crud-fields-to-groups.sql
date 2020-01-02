ALTER TABLE `groups`
    ADD COLUMN `sequence`   INT(11) UNSIGNED NOT NULL AFTER `digest_schedule`,
    ADD COLUMN `updated_by` INT(11) UNSIGNED NULL AFTER `sequence`,
    ADD COLUMN `created_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `updated_by`,
    ADD COLUMN `updated_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `created_at`;

ALTER TABLE `groups`
    ADD CONSTRAINT `fk_groups_user_id`
        FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
            ON DELETE NO ACTION ON UPDATE NO ACTION;

# backfill sequence with id to mimic its current ordering
UPDATE `groups`
SET `groups`.sequence = `groups`.id;

# backfill updated_by with Alaress user id
UPDATE `groups`
SET `updated_by` = (SELECT `user`.`id` FROM `user` WHERE username LIKE 'alaress');

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `groups`
    MODIFY COLUMN `updated_by` INT(11) UNSIGNED NOT NULL;
SET FOREIGN_KEY_CHECKS = 1;
