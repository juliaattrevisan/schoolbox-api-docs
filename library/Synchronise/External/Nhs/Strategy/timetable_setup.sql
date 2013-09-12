-- Add terms for this year
INSERT INTO `timetable_cycle`
(`id`, `title`, `cycle_length`, `day_length`, `regular`)
VALUES
(1, 'Term 1 2013', 10, 5, 1),
(2, 'Term 2 2013', 10, 5, 1),
(3, 'Term 3 2013', 10, 5, 1),
(4, 'Term 4 2013', 10, 5, 1);

-- Add start and end dates for campuses
INSERT INTO `timetable_campus`
(`cycle_id`, `campus_id`, `start`, `reset`, `finish`, `offset`)
VALUES
(1, 1, '2013-01-30', null, '2013-03-28', 2),
(2, 1, '2013-04-15', null, '2013-06-28', 0),
(3, 1, '2013-07-15', null, '2013-09-20', 0),
(4, 1, '2013-10-07', null, '2013-12-20', 0);

-- Add timetable periods.
-- We will need to know their actual period start and end time
-- (UPDATE `timetable_period` SET `start` = ?, `finish` = ? WHERE sequence = ?)
INSERT INTO `timetable_period`
(`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`)
VALUES
(3, 'Period 1', 1, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 1, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 1, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 1, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 1, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 2, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 2, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 2, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 2, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 2, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 3, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 3, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 3, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 3, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 3, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 4, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 4, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 4, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 4, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 4, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 5, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 5, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 5, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 5, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 5, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 6, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 6, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 6, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 6, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 6, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 7, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 7, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 7, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 7, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 7, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 8, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 8, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 8, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 8, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 8, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 9, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 9, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 9, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 9, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 9, 5, '13:00:00', '14:00:00', true),
(3, 'Period 1', 10, 1, '09:00:00', '10:00:00', true),
(3, 'Period 2', 10, 2, '10:00:00', '11:00:00', true),
(3, 'Period 3', 10, 3, '11:00:00', '12:00:00', true),
(3, 'Period 4', 10, 4, '12:00:00', '13:00:00', true),
(3, 'Period 5', 10, 5, '13:00:00', '14:00:00', true);