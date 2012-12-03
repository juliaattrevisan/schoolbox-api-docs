/* This script generates the tables required when the "external" database type is set to Schoolbox.
   At time of writing, only Donvale and StPats require these tables to be in the database, as they are the only two
   using CSV imports for data syncing.
*/

CREATE TABLE `class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `code` varchar(16) NOT NULL,
  `description` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `class_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `class_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `timetable_campus` (
  `cycle_id` int(10) unsigned NOT NULL,
  `campus_id` int(11) NOT NULL,
  `start` date NOT NULL,
  `reset` date DEFAULT NULL,
  `finish` date NOT NULL,
  `offset` int(11) DEFAULT NULL,
  PRIMARY KEY (`cycle_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `timetable_cycle` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(32) NOT NULL,
  `cycle_length` int(11) unsigned DEFAULT NULL,
  `day_length` int(10) unsigned DEFAULT NULL,
  `regular` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `timetable_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `title` varchar(32) DEFAULT NULL,
  `day` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `start` time DEFAULT NULL,
  `finish` time DEFAULT NULL,
  `usable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day` (`day`,`sequence`),
  UNIQUE KEY `title` (`title`,`day`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `user_profile` (
  `user_id` int(11) NOT NULL,
  `title` varchar(16) DEFAULT NULL,
  `given_name` varchar(128) DEFAULT NULL,
  `preferred_name` varchar(32) DEFAULT NULL,
  `last_name` varchar(64) DEFAULT NULL,
  `birth_date` datetime DEFAULT NULL,
  `gender` int(11) DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `user_relation` (
  `user_id` int(11) NOT NULL,
  `relation_id` int(11) NOT NULL,
  `relation_type` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`relation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;