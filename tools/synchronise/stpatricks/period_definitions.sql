/* These need to be added to the main definition somewhere */
ALTER TABLE `timetable_period` ADD UNIQUE INDEX (`day`, `sequence`);
ALTER TABLE `timetable_period` ADD UNIQUE INDEX (`title`, `day`);


/* WARNING: execution will delete any live changes made, this is an idempotent script that should be run once a term or
 * when there are timetable definition changes
 */

/* create the cycle */
TRUNCATE `timetable_cycle`;
insert into `timetable_cycle` (`title`, `cycle_length`, `day_length`, `regular`) values ('10 Day Cycle','10','11','1');

/* create the campus, setup a timetable from the first day of the current month to 3 months later (to cover a 10 week term) */
TRUNCATE `timetable_campus`;
insert into `timetable_campus` (`cycle_id`, `campus_id`, `start`, `finish`, `offset`) values (
  '1', '1',
  CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') as DATE),
  DATE_ADD(CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') as DATE), '3 MONTHS'),
  '1'
);


/* Insert the base definition (includes Friday assembly) */
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Before School','5','0',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Tutor Period','5','1',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Period 1','5','2',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Period 2','5','3',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Recess','5','4',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Period 3','5','5',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Period 4','5','6',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','PC / Assembly','5','7',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Lunch','5','8',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Period 5','5','9',NULL,NULL,'1');
insert into `timetable_period` (`cycle_id`, `title`, `day`, `sequence`, `start`, `finish`, `usable`) values('1','Period 6','5','10',NULL,NULL,'1');


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