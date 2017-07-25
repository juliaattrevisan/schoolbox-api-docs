-- Allow EOTC to shove events into Synergetic
INSERT INTO `eotc_config` (`id`, `name`, `value`) VALUES ('6', 'external_db_sync', '0');

-- Add Permission Slip Required toggle to every metadata
ALTER TABLE `eotc_metadata` ADD COLUMN `permission_slip` INT(11) DEFAULT 0 NOT NULL AFTER `comment`;