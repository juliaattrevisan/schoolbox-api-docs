-- Remove title index - it will be readded with the 'cycle_id' key added back in.
ALTER TABLE `timetable_period` DROP INDEX `title`;
