-- Restore title index with 'cycle_id' key added to the unique key.
ALTER TABLE `timetable_period` ADD UNIQUE INDEX `title` (`title` ASC, `day` ASC, `cycle_id` ASC);
