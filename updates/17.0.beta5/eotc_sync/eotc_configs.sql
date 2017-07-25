-- Add Permission Slip Required toggle to every metadata
ALTER TABLE `eotc_metadata` ADD COLUMN `permission_slip_required` INT(11) DEFAULT 0 NOT NULL AFTER `comment`;