/**
 * Assume that offset starts at 0, start_day starts at 1.
 * Sam thinks that the logic is different if start_day between Wed and Sat,
 * but that looks like a bug instead of what is intended.
 */
UPDATE `timetable_campus` SET `start_day` = `offset` + 1;
