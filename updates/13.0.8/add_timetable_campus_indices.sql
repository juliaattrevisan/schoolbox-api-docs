ALTER TABLE `timetable_campus`
  ADD INDEX `timetable_campus_campus_id` (`campus_id` ASC),
  ADD INDEX `timetable_campus_cycle_id`  (`cycle_id`  ASC);