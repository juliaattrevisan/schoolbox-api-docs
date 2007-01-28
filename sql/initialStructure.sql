/*
SQLyog Community Edition- MySQL GUI v5.2 Beta 1
Host - 4.1.16 : Database - portal
*********************************************************************
Server version : 4.1.16
*/

SET NAMES utf8;

SET SQL_MODE='';

create database if not exists `portal`;

USE `portal`;

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

/*Table structure for table `allowedFileExtension` */

DROP TABLE IF EXISTS `allowedFileExtension`;

CREATE TABLE `allowedFileExtension` (
  `fileTypeID` int(11) default NULL,
  `extension` char(6) default NULL,
  KEY `fileTypeID` (`fileTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `allowedFileTypes` */

DROP TABLE IF EXISTS `allowedFileTypes`;

CREATE TABLE `allowedFileTypes` (
  `fileTypeID` int(11) NOT NULL auto_increment,
  `description` varchar(150) default NULL,
  `mime` varchar(100) NOT NULL default '',
  `iconFile` varchar(80) NOT NULL default 'application-x-none.png',
  `inline` enum('true','false') NOT NULL default 'true',
  `enable` enum('true','false') default 'true',
  PRIMARY KEY  (`fileTypeID`),
  UNIQUE KEY `mime` (`mime`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `bookmarks` */

DROP TABLE IF EXISTS `bookmarks`;

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL auto_increment,
  `owner` int(11) NOT NULL default '0',
  `url` varchar(255) NOT NULL default '',
  `description` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `category` */

DROP TABLE IF EXISTS `category`;

CREATE TABLE `category` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `parent` int(5) NOT NULL default '0',
  `template` tinyint(1) unsigned NOT NULL default '0',
  `teacherid` int(4) NOT NULL default '0',
  `code` varchar(8) default NULL,
  PRIMARY KEY  (`id`),
  KEY `parent` (`parent`),
  KEY `alt_code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `cmsLog` */

DROP TABLE IF EXISTS `cmsLog`;

CREATE TABLE `cmsLog` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `object` enum('folder','file','link') NOT NULL default 'folder',
  `objectId` int(10) unsigned NOT NULL default '0',
  `action` enum('create','modify','delete') NOT NULL default 'create',
  `user` int(10) unsigned NOT NULL default '0',
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `enquiryDestination` */

DROP TABLE IF EXISTS `enquiryDestination`;

CREATE TABLE `enquiryDestination` (
  `campusID` int(11) NOT NULL default '0',
  `enquiryTypeID` int(11) NOT NULL default '0',
  `email` varchar(80) default NULL,
  PRIMARY KEY  (`campusID`,`enquiryTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `enquiryType` */

DROP TABLE IF EXISTS `enquiryType`;

CREATE TABLE `enquiryType` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(40) default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `error` */

DROP TABLE IF EXISTS `error`;

CREATE TABLE `error` (
  `id` int(3) unsigned NOT NULL default '0',
  `message` mediumtext,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `event` */

DROP TABLE IF EXISTS `event`;

CREATE TABLE `event` (
  `id` int(6) NOT NULL auto_increment,
  `owner` int(6) NOT NULL default '0',
  `access` tinyint(1) NOT NULL default '0',
  `sid` int(6) default NULL,
  `priority` smallint(1) NOT NULL default '0',
  `note` varchar(250) NOT NULL default '',
  `date` date NOT NULL default '0000-00-00',
  `startTime` time default NULL,
  `endTime` time default NULL,
  PRIMARY KEY  (`id`),
  KEY `access` (`access`,`sid`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `eventCampus` */

DROP TABLE IF EXISTS `eventCampus`;

CREATE TABLE `eventCampus` (
  `eventID` int(11) NOT NULL auto_increment,
  `campusID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`campusID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `eventGroup` */

DROP TABLE IF EXISTS `eventGroup`;

CREATE TABLE `eventGroup` (
  `eventID` int(11) NOT NULL auto_increment,
  `groupID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`groupID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `eventMonthly` */

DROP TABLE IF EXISTS `eventMonthly`;

CREATE TABLE `eventMonthly` (
  `id` int(6) NOT NULL auto_increment,
  `owner` int(6) default NULL,
  `access` tinyint(1) default NULL,
  `sid` int(6) default NULL,
  `date` smallint(2) default NULL,
  `dayOcc` tinyint(1) default NULL,
  `day` tinyint(1) default NULL,
  `priority` tinyint(1) default NULL,
  `startTime` time default NULL,
  `endTime` time default NULL,
  `startDuration` date default NULL,
  `endDuration` date default NULL,
  `note` varchar(250) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `access` (`access`,`sid`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `eventWeekly` */

DROP TABLE IF EXISTS `eventWeekly`;

CREATE TABLE `eventWeekly` (
  `id` int(6) NOT NULL auto_increment,
  `owner` int(6) NOT NULL default '0',
  `access` tinyint(1) NOT NULL default '0',
  `sid` int(6) default NULL,
  `day` tinyint(1) NOT NULL default '0',
  `priority` tinyint(1) NOT NULL default '0',
  `startTime` time default NULL,
  `endTime` time default NULL,
  `startDuration` date default NULL,
  `endDuration` date default NULL,
  `note` varchar(250) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `owner` (`owner`),
  KEY `access` (`access`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `file` */

DROP TABLE IF EXISTS `file`;

CREATE TABLE `file` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `owner` int(4) unsigned NOT NULL default '0',
  `realname` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `description` varchar(255) default NULL,
  `comment` text,
  `type` tinyint(4) unsigned NOT NULL default '0',
  `file_size` bigint(20) default NULL,
  `mime_type` varchar(40) default NULL,
  `deleted` tinyint(2) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `category` (`category`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `file_type` */

DROP TABLE IF EXISTS `file_type`;

CREATE TABLE `file_type` (
  `id` int(4) NOT NULL auto_increment,
  `name` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `forum` */

DROP TABLE IF EXISTS `forum`;

CREATE TABLE `forum` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `owner` int(6) unsigned NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `heading` varchar(250) NOT NULL default '',
  `message` text,
  `parent` int(6) NOT NULL default '0',
  `cid` int(6) NOT NULL default '0',
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `thread` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `parent` (`parent`),
  KEY `category` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `groups` */

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `name` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `homepage_colours` */

DROP TABLE IF EXISTS `homepage_colours`;

CREATE TABLE `homepage_colours` (
  `id` smallint(3) unsigned NOT NULL auto_increment,
  `name` char(15) NOT NULL default '0',
  `hex` char(6) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `homepage_comp` */

DROP TABLE IF EXISTS `homepage_comp`;

CREATE TABLE `homepage_comp` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `size` tinyint(3) unsigned NOT NULL default '0',
  `method` varchar(18) default NULL,
  `name` varchar(20) default NULL,
  `filename` varchar(22) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `homepage_comp_location` */

DROP TABLE IF EXISTS `homepage_comp_location`;

CREATE TABLE `homepage_comp_location` (
  `cid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(1) unsigned NOT NULL default '0',
  `component` tinyint(3) unsigned NOT NULL default '0',
  `size` tinyint(3) unsigned NOT NULL default '7',
  PRIMARY KEY  (`cid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `homepage_config` */

DROP TABLE IF EXISTS `homepage_config`;

CREATE TABLE `homepage_config` (
  `cid` int(6) unsigned NOT NULL default '0',
  `template` tinyint(1) unsigned NOT NULL default '0',
  `large_head_fontcolour` tinyint(3) unsigned default NULL,
  `large_head_bgcolour` tinyint(3) unsigned default NULL,
  `large_text_fontcolour` tinyint(3) unsigned default NULL,
  `large_text_bgcolour` tinyint(3) unsigned default NULL,
  `small_head_fontcolour` tinyint(3) unsigned default NULL,
  `small_head_bgcolour` tinyint(3) unsigned default NULL,
  `small_text_fontcolour` tinyint(3) unsigned default NULL,
  `small_text_bgcolour` tinyint(3) unsigned default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `homepage_log` */

DROP TABLE IF EXISTS `homepage_log`;

CREATE TABLE `homepage_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `cid` int(6) NOT NULL default '0',
  `hitcount` int(10) unsigned default '0',
  PRIMARY KEY  (`user`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `homepages` */

DROP TABLE IF EXISTS `homepages`;

CREATE TABLE `homepages` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `filename` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `house` */

DROP TABLE IF EXISTS `house`;

CREATE TABLE `house` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `house` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `link` */

DROP TABLE IF EXISTS `link`;

CREATE TABLE `link` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `owner` int(5) unsigned NOT NULL default '0',
  `url` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `description` varchar(255) default NULL,
  `comment` text,
  PRIMARY KEY  (`id`),
  KEY `category` (`category`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `login_log` */

DROP TABLE IF EXISTS `login_log`;

CREATE TABLE `login_log` (
  `id` int(6) NOT NULL auto_increment,
  `username` varchar(50) NOT NULL default '',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `ip` varchar(15) NOT NULL default '',
  `login` tinyint(1) unsigned default NULL,
  `logout` tinyint(1) unsigned default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `luCampus` */

DROP TABLE IF EXISTS `luCampus`;

CREATE TABLE `luCampus` (
  `name` varchar(255) default NULL,
  `id` tinyint(4) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `luResourceLocation` */

DROP TABLE IF EXISTS `luResourceLocation`;

CREATE TABLE `luResourceLocation` (
  `id` mediumint(3) unsigned NOT NULL auto_increment,
  `name` varchar(50) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `luResourceType` */

DROP TABLE IF EXISTS `luResourceType`;

CREATE TABLE `luResourceType` (
  `id` mediumint(3) unsigned NOT NULL auto_increment,
  `name` varchar(50) NOT NULL default '0',
  `type` enum('room','equipment') default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `luServices` */

DROP TABLE IF EXISTS `luServices`;

CREATE TABLE `luServices` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `news_items` */

DROP TABLE IF EXISTS `news_items`;

CREATE TABLE `news_items` (
  `item_id` int(10) unsigned NOT NULL auto_increment,
  `item_topic_id` smallint(5) unsigned default '0',
  `item_heading` varchar(50) NOT NULL default '',
  `item_datetime_posted` datetime NOT NULL default '0000-00-00 00:00:00',
  `item_datetime_expires` datetime NOT NULL default '0000-00-00 00:00:00',
  `item_mainpage` tinyint(3) unsigned NOT NULL default '0',
  `item_body` text NOT NULL,
  `item_link1` varchar(255) default NULL,
  `item_link2` varchar(255) default NULL,
  `item_link3` varchar(255) default NULL,
  `item_link4` varchar(255) default NULL,
  `item_link5` varchar(255) default NULL,
  `item_deleted` tinyint(3) unsigned NOT NULL default '0',
  `item_owner` int(6) unsigned NOT NULL default '0',
  `item_campus` int(4) default '0',
  `item_group` int(4) unsigned default NULL,
  PRIMARY KEY  (`item_id`),
  KEY `access` (`item_topic_id`,`item_campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `news_topics` */

DROP TABLE IF EXISTS `news_topics`;

CREATE TABLE `news_topics` (
  `topic_id` smallint(5) unsigned NOT NULL auto_increment,
  `topic_name` char(50) NOT NULL default '',
  PRIMARY KEY  (`topic_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `newsCampus` */

DROP TABLE IF EXISTS `newsCampus`;

CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL default '0',
  `campus` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `newsGroup` */

DROP TABLE IF EXISTS `newsGroup`;

CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL default '0',
  `gid` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `perms` */

DROP TABLE IF EXISTS `perms`;

CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL default '0',
  `gid` int(4) NOT NULL default '0',
  `type` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `ph_ext` */

DROP TABLE IF EXISTS `ph_ext`;

CREATE TABLE `ph_ext` (
  `owner` int(11) NOT NULL default '0',
  `extension` mediumint(9) default NULL,
  PRIMARY KEY  (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `resource` */

DROP TABLE IF EXISTS `resource`;

CREATE TABLE `resource` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `owner` int(6) unsigned NOT NULL default '0',
  `perm` tinyint(1) unsigned NOT NULL default '0',
  `location` mediumint(3) unsigned default '0',
  `name` varchar(50) default '0',
  `description` blob,
  `type` mediumint(3) unsigned default '0',
  PRIMARY KEY  (`id`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `resourceBooking` */

DROP TABLE IF EXISTS `resourceBooking`;

CREATE TABLE `resourceBooking` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `created` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `rid` int(6) unsigned NOT NULL default '0',
  `creator` int(6) unsigned NOT NULL default '0',
  `start` datetime default NULL,
  `end` datetime default NULL,
  `note` varchar(250) default NULL,
  `start_time` time default NULL,
  `end_time` time default NULL,
  `day` tinyint(3) unsigned default NULL,
  `week` tinyint(3) unsigned default NULL,
  `date` tinyint(3) unsigned default NULL,
  `month` tinyint(3) unsigned default NULL,
  `year` mediumint(8) unsigned default NULL,
  `recurrence` enum('week','fortnight','month') default NULL,
  `type` enum('request','locked') default NULL,
  PRIMARY KEY  (`id`),
  KEY `rid` (`rid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `roomResource` */

DROP TABLE IF EXISTS `roomResource`;

CREATE TABLE `roomResource` (
  `room` int(6) unsigned NOT NULL default '0',
  `resource` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY  (`room`,`resource`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `sideMenu` */

DROP TABLE IF EXISTS `sideMenu`;

CREATE TABLE `sideMenu` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `parent` int(3) unsigned NOT NULL default '0',
  `txt` varchar(150) NOT NULL default '0',
  `url` varchar(200) default '0',
  `target` varchar(10) default '0',
  `sequence` tinyint(3) unsigned default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `sidemenu` */

DROP TABLE IF EXISTS `sidemenu`;

CREATE TABLE `sidemenu` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `parent` int(3) unsigned NOT NULL default '0',
  `txt` varchar(150) NOT NULL default '0',
  `url` varchar(200) default '0',
  `target` varchar(10) default '0',
  `sequence` tinyint(3) unsigned default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `sideMenuCampus` */

DROP TABLE IF EXISTS `sideMenuCampus`;

CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL default '0',
  `campus` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `sideMenuGroup` */

DROP TABLE IF EXISTS `sideMenuGroup`;

CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL default '0',
  `gid` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `student_submission` */

DROP TABLE IF EXISTS `student_submission`;

CREATE TABLE `student_submission` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `owner` int(4) unsigned NOT NULL default '0',
  `realname` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `marked` tinyint(1) unsigned NOT NULL default '0',
  `late` tinyint(1) unsigned NOT NULL default '0',
  `box_id` int(4) unsigned NOT NULL default '0',
  `mime_type` varchar(20) default NULL,
  PRIMARY KEY  (`id`),
  KEY `box` (`box_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `subject_news` */

DROP TABLE IF EXISTS `subject_news`;

CREATE TABLE `subject_news` (
  `id` int(6) NOT NULL auto_increment,
  `heading` varchar(50) default NULL,
  `datetime_posted` datetime NOT NULL default '0000-00-00 00:00:00',
  `datetime_expires` datetime NOT NULL default '0000-00-00 00:00:00',
  `body` text NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `code` int(6) unsigned NOT NULL default '0',
  `owner` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `category` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `submission_box` */

DROP TABLE IF EXISTS `submission_box`;

CREATE TABLE `submission_box` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `owner` int(4) unsigned NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `late_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `fid` int(5) unsigned default '0',
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `description` varchar(32) default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `file` (`fid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `submission_log` */

DROP TABLE IF EXISTS `submission_log`;

CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `page` tinyint(4) NOT NULL default '0',
  `sid` int(6) NOT NULL default '0',
  PRIMARY KEY  (`user`,`page`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `submission_return` */

DROP TABLE IF EXISTS `submission_return`;

CREATE TABLE `submission_return` (
  `id` int(11) NOT NULL auto_increment,
  `box_id` int(11) NOT NULL default '0',
  `owner` int(11) NOT NULL default '0',
  `comment` varchar(255) default NULL,
  `mark` varchar(15) default NULL,
  `filename` varchar(80) default NULL,
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `returned` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `superusers` */

DROP TABLE IF EXISTS `superusers`;

CREATE TABLE `superusers` (
  `id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `uhomepage` */

DROP TABLE IF EXISTS `uhomepage`;

CREATE TABLE `uhomepage` (
  `owner` int(11) NOT NULL default '0',
  `education` text,
  `personal` text,
  `school` text,
  PRIMARY KEY  (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `user` */

DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `username` varchar(60) NOT NULL default '',
  `password` varchar(80) default NULL,
  `group_id` int(4) unsigned NOT NULL default '0',
  `firstname` varchar(30) NOT NULL default '',
  `lastname` varchar(30) NOT NULL default '',
  `email_pass` varchar(40) default NULL,
  `synergy_id` int(6) unsigned NOT NULL default '0',
  `campus` tinyint(4) unsigned NOT NULL default '0',
  `year` tinyint(2) unsigned default NULL,
  `house` int(11) default NULL,
  `local_email` tinyint(3) unsigned NOT NULL default '0',
  `alt_email` varchar(60) default NULL,
  `mail_handler` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `synergy` (`synergy_id`),
  KEY `username` (`username`),
  KEY `groupind` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `userAccess` */

DROP TABLE IF EXISTS `userAccess`;

CREATE TABLE `userAccess` (
  `uid` int(11) NOT NULL default '0',
  `service` int(11) NOT NULL default '0',
  `sid` int(11) NOT NULL default '0',
  `type` smallint(6) default NULL,
  PRIMARY KEY  (`service`,`sid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `userCampus` */

DROP TABLE IF EXISTS `userCampus`;

CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL default '0',
  `campus` tinyint(3) unsigned NOT NULL default '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `userGroups` */

DROP TABLE IF EXISTS `userGroups`;

CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL default '0',
  `type` tinyint(2) default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `welcomeComponent` */

DROP TABLE IF EXISTS `welcomeComponent`;

CREATE TABLE `welcomeComponent` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `filename` varchar(100) default '0',
  `function` varchar(100) default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

/*Table structure for table `welcomeLayout` */

DROP TABLE IF EXISTS `welcomeLayout`;

CREATE TABLE `welcomeLayout` (
  `gid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(3) unsigned NOT NULL default '0',
  `componentID` int(10) unsigned default '0',
  PRIMARY KEY  (`gid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
