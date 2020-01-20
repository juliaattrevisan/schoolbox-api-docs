ALTER TABLE `luResidentialHouse`
    ADD COLUMN `sequence`   INT(11) UNSIGNED NOT NULL AFTER `name`,
    ADD COLUMN `updated_by` INT(11) UNSIGNED NULL AFTER `sequence`,
    ADD COLUMN `created_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `updated_by`,
    ADD COLUMN `updated_at` DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `created_at`,
    ADD COLUMN `deleted_at` DATETIME         NULL AFTER `updated_at`;

ALTER TABLE `luResidentialHouse`
    ADD CONSTRAINT `fk_luResidentialHouse_user_id`
        FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
            ON DELETE NO ACTION ON UPDATE NO ACTION;

# backfill sequence with id to mimic its current ordering
UPDATE `luResidentialHouse`
SET `luResidentialHouse`.sequence = `luResidentialHouse`.id;
