-- Addition of class ID column and indices
ALTER TABLE `timetable_period` ADD COLUMN `class_id` INT(11) NULL AFTER `cycle_id`;
ALTER TABLE `timetable_period`
  ADD INDEX `timetable_period_class_id` (`class_id` ASC),
  ADD INDEX `timetable_period_cycle_id` (`cycle_id` ASC);

