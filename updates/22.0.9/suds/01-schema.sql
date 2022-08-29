-- This index seems to serve no real purpose
-- other than to prevent duplicate period names
-- but having duplicate period names has no consequence?

ALTER TABLE `sis_timetable_period`
DROP INDEX `title` ;
