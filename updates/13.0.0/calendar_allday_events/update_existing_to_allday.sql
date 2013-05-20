-- Existing events: update any that start at 12am.
-- This must run after allday_events.sql, because that adds the allday event field.
UPDATE `event` SET `allday` = TRUE WHERE `startTime` = '00:00:00';