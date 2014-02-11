-- Restore day index with 'cycle_id' key added to the unique key.
ALTER TABLE `timetable_period` ADD UNIQUE INDEX `day` (`day` ASC, `sequence` ASC, `cycle_id` ASC);
