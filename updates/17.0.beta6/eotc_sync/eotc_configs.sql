-- Config for whether student attendees are mandatory
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('student_attendees_required', '1');

-- Add Permission Slip Required toggle to every metadata
ALTER TABLE `eotc_metadata` ADD COLUMN `permission_slip_required` INT(11) DEFAULT 0 NOT NULL AFTER `comment`;

-- Add Roles
CREATE TABLE `eotc_event_role` (
  `event_id` INT NOT NULL,
  `role_id` INT NOT NULL
);

ALTER TABLE `eotc_event_role` ADD UNIQUE INDEX `event_id_role_id` (`event_id`, `role_id`);
