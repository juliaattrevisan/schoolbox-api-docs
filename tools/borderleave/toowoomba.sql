ALTER TABLE `leave_request` ADD COLUMN `host_mobile` VARCHAR(255) NULL AFTER `host_phone`;
ALTER TABLE `leave_request` ADD COLUMN `host_address` TEXT NULL AFTER `host_mobile`;
ALTER TABLE `leave_request` ADD COLUMN `collector_name` VARCHAR(255) NULL AFTER `host_address`;
ALTER TABLE `leave_request` ADD COLUMN `returner_name` VARCHAR(255) NULL AFTER `collector_name`;
ALTER TABLE `leave_request` ADD COLUMN `reason` TEXT NULL AFTER `returner_name`;

INSERT INTO `lu_leave_request_travel_type` (`name`) VALUES ('Host\'s car - TACAPS Boarding Office');
INSERT INTO `lu_leave_request_travel_type` (`name`) VALUES ('Host\'s car - School Activity or Sporting Venue');
INSERT INTO `lu_leave_request_travel_type` (`name`) VALUES ('Other');

ALTER TABLE `lu_leave_request_travel_type` ADD COLUMN `sequence` INT(11) NULL AFTER `name`;
UPDATE `lu_leave_request_travel_type` SET `sequence` = '1' WHERE `id` = '6'; 
UPDATE `lu_leave_request_travel_type` SET `sequence` = '2' WHERE `id` = '7'; 
UPDATE `lu_leave_request_travel_type` SET `sequence` = '3' WHERE `id` = '2'; 
UPDATE `lu_leave_request_travel_type` SET `sequence` = '4' WHERE `id` = '3'; 
UPDATE `lu_leave_request_travel_type` SET `sequence` = '5' WHERE `id` = '4'; 
UPDATE `lu_leave_request_travel_type` SET `sequence` = '6' WHERE `id` = '5'; 
UPDATE `lu_leave_request_travel_type` SET `sequence` = '7' WHERE `id` = '8'; 