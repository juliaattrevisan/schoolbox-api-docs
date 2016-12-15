CREATE TABLE IF NOT EXISTS `timetable_cycle` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(32) NOT NULL,
  `cycle_length` int(11) unsigned DEFAULT NULL,
  `day_length` int(10) unsigned DEFAULT NULL,
  `regular` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

SET foreign_key_checks = 0; ALTER TABLE timetable_cycle MODIFY title varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1;
SET foreign_key_checks = 0; ALTER TABLE timetable_period MODIFY title varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1;
SET foreign_key_checks = 0; ALTER TABLE user_assume_log MODIFY user varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1;
SET foreign_key_checks = 0; ALTER TABLE user_assume_log MODIFY assumed varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci; SET foreign_key_checks = 1;
