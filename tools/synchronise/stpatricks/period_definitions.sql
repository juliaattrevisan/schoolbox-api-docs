/* These need to be added to the main definition somewhere */
ALTER TABLE `timetable_period` ADD UNIQUE INDEX (`day`, `sequence`);
ALTER TABLE `timetable_period` ADD UNIQUE INDEX (`title`, `day`);

	
/* WARNING: execution will delete any live changes made:
 * this is an idempotent script that should be run once a term or when there are timetable definition changes
 */

/* create the cycle */
TRUNCATE `timetable_cycle`;
INSERT INTO `timetable_cycle` (`title`, `cycle_length`, `day_length`, `regular`) VALUES ('10 Day Cycle', '10', '11', '1');

/* create the campus, setup a timetable from the first day of the current month to 3 months later (to cover a 10 week term) */
TRUNCATE `timetable_campus`;
INSERT INTO `timetable_campus` (`cycle_id`, `campus_id`, `start`, `finish`, `offset`) VALUES (
  '1', '1',
  CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') AS DATE),
  DATE_ADD(CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') AS DATE), INTERVAL 3 MONTH),
  '0'
);


/* Insert the base definition (includes Friday assembly) */
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Before School', '1', '0', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Tutor Period', '1', '1', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Period 1', '1', '2', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Period 2', '1', '3', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Recess', '1', '4', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Period 3', '1', '5', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Period 4', '1', '6', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'PC / Assembly', '1', '7', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Lunch', '1', '8', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Period 5', '1', '9', NULL, NULL, '1');
INSERT INTO `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) VALUES ('1', 'Period 6', '1', '10', NULL, NULL, '1');


/* replicate the rows and increment the day number */
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 2, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 3, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 4, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 5, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 6, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 7, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 8, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 9, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`) SELECT `cycle_id`, `title`, 10, `sequence`, `usable` FROM timetable_period WHERE `day` = 1;

/*
  It would be preferred if this were in a loop rather than repeated...oh well...
INSERT INTO timetable_period (`cycle_id`, `title`, `day`, `sequence`, `usable`)
SELECT                        `cycle_id`, `title`, i,     `sequence`, `usable`
FROM timetable_period
WHERE `day` = 1;
*/