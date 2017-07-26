-- Config for whether student attendees are mandatory
INSERT INTO `eotc_config` (`id`, `name`, `value`) VALUES ('7', 'student_attendees_required', '1');

-- Add Permission Slip Required toggle to every metadata
ALTER TABLE `eotc_metadata` ADD COLUMN `permission_slip_required` INT(11) DEFAULT 0 NOT NULL AFTER `comment`;