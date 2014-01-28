-- Foreign key for travel type.
ALTER TABLE `leave_request`
    ADD COLUMN `travel_type_id` INT NULL DEFAULT NULL,
    ADD INDEX `leave_request_travel_type` (`travel_type_id`)
;
