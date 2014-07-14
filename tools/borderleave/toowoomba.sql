ALTER TABLE `leave_request` ADD COLUMN `host_mobile` VARCHAR(255) NULL AFTER `host_phone`;
ALTER TABLE `leave_request` ADD COLUMN `host_address` TEXT NULL AFTER `host_mobile`;
ALTER TABLE `leave_request` ADD COLUMN `collector_name` VARCHAR(255) NULL AFTER `host_address`;
ALTER TABLE `leave_request` ADD COLUMN `returner_name` VARCHAR(255) NULL AFTER `collector_name`;
ALTER TABLE `leave_request` ADD COLUMN `reason` TEXT NULL AFTER `returner_name`;

INSERT INTO `lu_leave_request_travel_type` (`name`) VALUES ('Host\'s car - TACAPS Boarding Office');
INSERT INTO `lu_leave_request_travel_type` (`name`) VALUES ('Host\'s car - School Activity or Sporting Venue');
INSERT INTO `lu_leave_request_travel_type` (`name`) VALUES ('Other');