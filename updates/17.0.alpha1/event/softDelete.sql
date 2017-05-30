-- Default create/update to epoch
ALTER TABLE `event`
  ADD COLUMN `created_at` DATETIME NOT NULL AFTER `allday`,
  ADD COLUMN `updated_at` DATETIME NOT NULL AFTER `created_at`,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;
UPDATE event SET created_at = FROM_UNIXTIME(0), updated_at = FROM_UNIXTIME(0);

ALTER TABLE `eotc_event`
  ADD COLUMN `created_at` DATETIME NOT NULL AFTER `allday`,
  ADD COLUMN `updated_at` DATETIME NOT NULL AFTER `created_at`,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;
UPDATE eotc_event SET created_at = FROM_UNIXTIME(0), updated_at = FROM_UNIXTIME(0);

/* Remove deprecated fields */
ALTER TABLE `event`
  DROP COLUMN `endTime`,
  DROP COLUMN `endDate`,
  DROP COLUMN `startTime`,
  DROP COLUMN `startDate`,
  DROP COLUMN `priority`,
  DROP COLUMN `sid`,
  DROP INDEX `access` ,
  ADD INDEX `access` (`access` ASC);

ALTER TABLE `eotc_event`
  DROP COLUMN `endTime`,
  DROP COLUMN `endDate`,
  DROP COLUMN `startTime`,
  DROP COLUMN `startDate`;

