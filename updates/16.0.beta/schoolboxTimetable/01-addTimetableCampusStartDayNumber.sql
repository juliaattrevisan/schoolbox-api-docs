ALTER TABLE `timetable_campus`
ADD COLUMN `start_day` INT DEFAULT 1  NOT NULL AFTER `offset`;
