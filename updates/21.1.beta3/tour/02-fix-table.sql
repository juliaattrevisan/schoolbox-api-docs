ALTER TABLE `tour_audience_role`
    DROP FOREIGN KEY `tour_audience_role_tour_uuid_fk`;
ALTER TABLE `tour_audience_role`
    CHANGE COLUMN `tour_uuid` `tour_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;
ALTER TABLE `tour_audience_role`
    ADD CONSTRAINT `tour_audience_role_tour_uuid_fk`
        FOREIGN KEY (`tour_uuid`)
            REFERENCES `tour` (`uuid`);
