ALTER TABLE `resource`
    ADD COLUMN `room_code` VARCHAR(64) NULL,
    ADD INDEX `resource_room_code` (`room_code`)
;
