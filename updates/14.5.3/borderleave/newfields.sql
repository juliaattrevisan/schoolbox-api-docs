ALTER TABLE `leave_request` ADD COLUMN `host_mobile` VARCHAR(255) NULL AFTER `host_phone`;
ALTER TABLE `leave_request` ADD COLUMN `host_address` TEXT NULL AFTER `host_mobile`;
ALTER TABLE `leave_request` ADD COLUMN `collector_name` VARCHAR(255) NULL AFTER `host_address`;
ALTER TABLE `leave_request` ADD COLUMN `returner_name` VARCHAR(255) NULL AFTER `collector_name`;
ALTER TABLE `leave_request` ADD COLUMN `reason` TEXT NULL AFTER `returner_name`;

/* Sequence for Travel Types. They can be manually set by us, to control
 * the order of elements in the dropdown (optional)
 */
ALTER TABLE `lu_leave_request_travel_type` ADD COLUMN `sequence` INT(11) NULL AFTER `name`;