-- Add all-day support for events
ALTER TABLE `sis_event`
ADD COLUMN `allday` TINYINT(1) NOT NULL DEFAULT 0 AFTER `finish_at`;
