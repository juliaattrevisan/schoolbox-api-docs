/*
SQLyog Enterprise - MySQL GUI v8.01 
MySQL - 5.0.67-0ubuntu6 : Database - portaldemo
*********************************************************************
*/


/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


/*Table structure for table `allowedFileExtension` */

DROP TABLE IF EXISTS `allowedFileExtension`;

CREATE TABLE `allowedFileExtension` (
  `fileTypeID` int(11) default NULL,
  `extension` char(6) default NULL,
  KEY `fileTypeID` (`fileTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `allowedFileTypes` */

DROP TABLE IF EXISTS `allowedFileTypes`;

CREATE TABLE `allowedFileTypes` (
  `fileTypeID` int(11) NOT NULL auto_increment,
  `description` varchar(150) NOT NULL default '',
  `mime` varchar(100) NOT NULL default '',
  `iconFile` varchar(80) NOT NULL default 'application-x-none.png',
  `inline` enum('true','false') NOT NULL default 'true',
  `enable` enum('true','false') NOT NULL default 'true',
  PRIMARY KEY  (`fileTypeID`),
  UNIQUE KEY `mime` (`mime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess` */

DROP TABLE IF EXISTS `assess`;

CREATE TABLE `assess` (
  `assess_id` int(11) NOT NULL auto_increment,
  `category_id` int(11) default NULL,
  `user_id` int(11) NOT NULL,
  `type` int(11) default '11',
  `title` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `duration` int(11) NOT NULL default '0' COMMENT 'in minutes',
  `allow_resit` int(1) NOT NULL default '0',
  `created_at` datetime default NULL,
  `deleted_at` datetime default NULL,
  PRIMARY KEY  (`assess_id`),
  UNIQUE KEY `id` (`assess_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess_answer` */

DROP TABLE IF EXISTS `assess_answer`;

CREATE TABLE `assess_answer` (
  `answer_id` int(11) NOT NULL auto_increment,
  `question_id` int(11) NOT NULL,
  `sequence` int(11) default NULL,
  `answer` text,
  `deleted_at` datetime default NULL,
  PRIMARY KEY  (`answer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess_attempt` */

DROP TABLE IF EXISTS `assess_attempt`;

CREATE TABLE `assess_attempt` (
  `attempt_id` int(11) NOT NULL auto_increment,
  `assess_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `reviewed_at` datetime default NULL,
  PRIMARY KEY  (`attempt_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess_luQuestionType` */

DROP TABLE IF EXISTS `assess_luQuestionType`;

CREATE TABLE `assess_luQuestionType` (
  `type_id` int(8) NOT NULL,
  `filename` varchar(255) default NULL,
  `description` varchar(255) default NULL,
  `moodle` varchar(255) default NULL,
  `automated_marking` enum('true','false') NOT NULL,
  PRIMARY KEY  (`type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess_question` */

DROP TABLE IF EXISTS `assess_question`;

CREATE TABLE `assess_question` (
  `question_id` int(11) NOT NULL auto_increment,
  `section_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `marks` int(11) default NULL,
  `question` text NOT NULL,
  `deleted_at` datetime default NULL,
  PRIMARY KEY  (`question_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess_response` */

DROP TABLE IF EXISTS `assess_response`;

CREATE TABLE `assess_response` (
  `response_id` int(11) NOT NULL auto_increment,
  `attempt_id` int(11) NOT NULL,
  `section_id` int(11) default NULL,
  `question_id` int(11) NOT NULL,
  `response` text,
  `comment` text NOT NULL,
  `mark` float default '0',
  `automarked_already` enum('true','false') default 'false',
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`response_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `assess_section` */

DROP TABLE IF EXISTS `assess_section`;

CREATE TABLE `assess_section` (
  `section_id` int(11) NOT NULL auto_increment,
  `assess_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL default '0',
  `title` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `random` int(11) NOT NULL default '0',
  `pool` int(11) NOT NULL default '0',
  `deleted_at` datetime default NULL,
  PRIMARY KEY  (`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `blog` */

DROP TABLE IF EXISTS `blog`;

CREATE TABLE `blog` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `cid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime default NULL,
  `heading` varchar(50) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `blogComment` */

DROP TABLE IF EXISTS `blogComment`;

CREATE TABLE `blogComment` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `bid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime default NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `bookmarks` */

DROP TABLE IF EXISTS `bookmarks`;

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL auto_increment,
  `owner` int(11) NOT NULL default '0',
  `url` varchar(255) NOT NULL default '',
  `description` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `bookmarks` */

/*Table structure for table `cache` */

DROP TABLE IF EXISTS `cache`;

CREATE TABLE `cache` (
  `cache_id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) unsigned NOT NULL default '0',
  `session_id` varchar(40) default NULL,
  `cache_key` varchar(128) NOT NULL default '',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `data` longtext NOT NULL,
  PRIMARY KEY  (`cache_id`),
  KEY `user_id` (`user_id`),
  KEY `cache_key` (`cache_key`),
  KEY `session_id` (`session_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `category` */

DROP TABLE IF EXISTS `category`;

CREATE TABLE `category` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `parent` int(5) NOT NULL default '0',
  `template` tinyint(1) unsigned NOT NULL default '0',
  `teacherid` int(4) NOT NULL default '0',
  `code` varchar(20) default NULL,
  PRIMARY KEY  (`id`),
  KEY `parent` (`parent`),
  KEY `alt_code` (`code`),
  FULLTEXT KEY `fulltext` (`name`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Table structure for table `categoryCode` */

DROP TABLE IF EXISTS `categoryCode`;

CREATE TABLE `categoryCode` (
  `categoryID` int(11) default NULL,
  `code` varchar(20) default NULL,
  UNIQUE KEY `categoryIDcode` (`categoryID`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `cmsLog` */

DROP TABLE IF EXISTS `cmsLog`;

CREATE TABLE `cmsLog` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `object` enum('folder','file','link','myfile','myfolder') NOT NULL default 'folder',
  `objectId` int(10) unsigned NOT NULL default '0',
  `action` enum('create','modify','delete') NOT NULL default 'create',
  `user` int(10) unsigned NOT NULL default '0',
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `dropbox` */

DROP TABLE IF EXISTS `dropbox`;

CREATE TABLE `dropbox` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `owner` int(4) unsigned NOT NULL default '0',
  `description` varchar(255) default NULL,
  `realname` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `file_size` bigint(20) default NULL,
  `mime_type` varchar(40) default NULL,
  `deleted` tinyint(2) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `category` (`category`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolioTemplateLayout` */

DROP TABLE IF EXISTS `eportfolioTemplateLayout`;

CREATE TABLE `eportfolioTemplateLayout` (
  `templateID` int(11) default NULL,
  `componentId` int(11) default NULL,
  `column` smallint(6) default NULL,
  `position` smallint(6) default NULL,
  UNIQUE KEY `table` (`templateID`,`componentId`,`column`,`position`),
  KEY `homepageID` (`templateID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_blog` */

DROP TABLE IF EXISTS `eportfolio_blog`;

CREATE TABLE `eportfolio_blog` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime default NULL,
  `heading` varchar(50) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_blogComment` */

DROP TABLE IF EXISTS `eportfolio_blogComment`;

CREATE TABLE `eportfolio_blogComment` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `bid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime default NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_comp` */

DROP TABLE IF EXISTS `eportfolio_comp`;

CREATE TABLE `eportfolio_comp` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `size` tinyint(3) unsigned NOT NULL default '0',
  `method` varchar(18) default NULL,
  `name` varchar(20) default NULL,
  `description` mediumtext,
  `filename` varchar(22) default NULL,
  `config` mediumtext NOT NULL,
  `invisible` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Table structure for table `eportfolio_comp_location` */

DROP TABLE IF EXISTS `eportfolio_comp_location`;

CREATE TABLE `eportfolio_comp_location` (
  `uid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(1) unsigned NOT NULL default '0',
  `column` tinyint(3) unsigned NOT NULL default '1',
  `component` int(11) unsigned NOT NULL default '0',
  `config` mediumtext NOT NULL,
  KEY `cid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_config` */

DROP TABLE IF EXISTS `eportfolio_config`;

CREATE TABLE `eportfolio_config` (
  `uid` int(6) unsigned NOT NULL default '0',
  `template` tinyint(1) unsigned NOT NULL default '0',
  `left_head_font` char(7) default NULL,
  `left_head_bg` char(7) default NULL,
  `left_font` char(7) default NULL,
  `left_bg_primary` char(7) default NULL,
  `left_bg_secondary` char(7) default NULL,
  `left_link` char(7) default NULL,
  `left_hover` char(7) default NULL,
  `right_head_font` char(7) default NULL,
  `right_head_bg` char(7) default NULL,
  `right_font` char(7) default NULL,
  `right_bg_primary` char(6) default NULL,
  `right_bg_secondary` char(7) default NULL,
  `right_link` char(7) default NULL,
  `right_hover` char(7) default NULL,
  PRIMARY KEY  (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_link` */

DROP TABLE IF EXISTS `eportfolio_link`;

CREATE TABLE `eportfolio_link` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `owner` int(5) unsigned NOT NULL default '0',
  `url` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime default NULL,
  `description` varchar(255) default NULL,
  `comment` mediumtext,
  `hidden` tinyint(1) NOT NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `fulltext` (`url`,`description`,`comment`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_log` */

DROP TABLE IF EXISTS `eportfolio_log`;

CREATE TABLE `eportfolio_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `uid` int(6) NOT NULL default '0',
  `hitcount` int(10) unsigned default '0',
  PRIMARY KEY  (`user`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_privateNotes` */

DROP TABLE IF EXISTS `eportfolio_privateNotes`;

CREATE TABLE `eportfolio_privateNotes` (
  `uid` int(8) default NULL,
  `privateNotes` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `eportfolio_textBox` */

DROP TABLE IF EXISTS `eportfolio_textBox`;

CREATE TABLE `eportfolio_textBox` (
  `textBoxID` int(11) NOT NULL auto_increment,
  `userID` int(11) default NULL,
  `heading` varchar(100) default NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`textBoxID`),
  KEY `categoryID` (`userID`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

/*Table structure for table `error` */

DROP TABLE IF EXISTS `error`;

CREATE TABLE `error` (
  `id` int(3) unsigned NOT NULL default '0',
  `message` mediumtext,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `event` */

DROP TABLE IF EXISTS `event`;

CREATE TABLE `event` (
  `id` int(6) NOT NULL auto_increment,
  `owner` int(6) NOT NULL default '0',
  `access` tinyint(1) NOT NULL default '0',
  `sid` int(6) default NULL,
  `priority` smallint(1) NOT NULL default '0',
  `type` int(10) unsigned default NULL,
  `note` varchar(250) NOT NULL default '',
  `date` date NOT NULL default '0000-00-00',
  `startTime` time default NULL,
  `endTime` time default NULL,
  PRIMARY KEY  (`id`),
  KEY `access` (`access`,`sid`),
  KEY `owner` (`owner`),
  KEY `date` (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `event` */

/*Table structure for table `eventCampus` */

DROP TABLE IF EXISTS `eventCampus`;

CREATE TABLE `eventCampus` (
  `eventID` int(11) NOT NULL auto_increment,
  `campusID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`campusID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eventCampus` */

/*Table structure for table `eventGroup` */

DROP TABLE IF EXISTS `eventGroup`;

CREATE TABLE `eventGroup` (
  `eventID` int(11) NOT NULL auto_increment,
  `groupID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`groupID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eventGroup` */

/*Table structure for table `file` */

DROP TABLE IF EXISTS `file`;

CREATE TABLE `file` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `owner` int(4) unsigned NOT NULL default '0',
  `realname` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime default NULL,
  `description` varchar(255) default NULL,
  `comment` mediumtext,
  `type` tinyint(4) unsigned NOT NULL default '0',
  `file_size` bigint(20) default NULL,
  `mime_type` varchar(40) default NULL,
  `deleted` tinyint(2) unsigned NOT NULL default '0',
  `hidden` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `category` (`category`),
  FULLTEXT KEY `fulltext` (`realname`,`description`,`comment`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `file_type` */

DROP TABLE IF EXISTS `file_type`;

CREATE TABLE `file_type` (
  `id` int(4) NOT NULL auto_increment,
  `name` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `forum` */

DROP TABLE IF EXISTS `forum`;

CREATE TABLE `forum` (
  `id` int(6) unsigned NOT NULL auto_increment,
  `owner` int(6) unsigned NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `heading` varchar(250) NOT NULL default '',
  `message` mediumtext,
  `parent` int(6) NOT NULL default '0',
  `cid` int(6) NOT NULL default '0',
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `thread` int(6) unsigned NOT NULL default '0',
  `last_post_id` int(11) default NULL,
  `last_post_title` varchar(200) default NULL,
  `last_post_time` datetime default NULL,
  `views` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `parent` (`parent`),
  KEY `category` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `forumPost` */

DROP TABLE IF EXISTS `forumPost`;

CREATE TABLE `forumPost` (
  `postID` int(11) NOT NULL auto_increment,
  `threadID` int(11) default NULL,
  `userID` int(11) default NULL,
  `date` datetime default NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL default 'false',
  `modified` datetime default NULL,
  `censored` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`postID`),
  KEY `threadID` (`threadID`),
  KEY `userID` (`userID`),
  FULLTEXT KEY `fulltext` (`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `forumThread` */

DROP TABLE IF EXISTS `forumThread`;

CREATE TABLE `forumThread` (
  `threadID` int(11) NOT NULL auto_increment,
  `categoryID` int(11) default NULL,
  `userID` int(11) default NULL,
  `title` varchar(250) default NULL,
  `date` datetime default NULL,
  `deleted` enum('true','false') NOT NULL default 'false',
  `locked` enum('true','false') NOT NULL default 'false',
  `views` int(11) NOT NULL,
  `last_post_id` int(11) default NULL,
  `last_post_date` datetime default NULL,
  `last_post_user_id` int(11) default NULL,
  `last_post_user` varchar(250) default NULL,
  `tempID` int(11) default NULL,
  PRIMARY KEY  (`threadID`),
  KEY `categoryID` (`categoryID`),
  KEY `userID` (`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `groups` */

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `name` varchar(20) NOT NULL default '',
  `staff` smallint(6) default NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `homepageTemplate` */

DROP TABLE IF EXISTS `homepageTemplate`;

CREATE TABLE `homepageTemplate` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `layout` smallint(6) default '1',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



/*Table structure for table `homepageTemplateLayout` */

DROP TABLE IF EXISTS `homepageTemplateLayout`;

CREATE TABLE `homepageTemplateLayout` (
  `templateID` int(11) default NULL,
  `componentId` int(11) default NULL,
  `column` smallint(6) default NULL,
  `position` smallint(6) default NULL,
  UNIQUE KEY `table` (`templateID`,`componentId`,`column`,`position`),
  KEY `homepageID` (`templateID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `homepage_comp` */

DROP TABLE IF EXISTS `homepage_comp`;

CREATE TABLE `homepage_comp` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `size` tinyint(3) unsigned NOT NULL default '0',
  `method` varchar(18) default NULL,
  `name` varchar(20) default NULL,
  `description` mediumtext,
  `filename` varchar(22) default NULL,
  `config` mediumtext NOT NULL,
  `invisible` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;


/*Table structure for table `homepage_comp_location` */

DROP TABLE IF EXISTS `homepage_comp_location`;

CREATE TABLE `homepage_comp_location` (
  `cid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(1) unsigned NOT NULL default '0',
  `column` tinyint(3) unsigned NOT NULL default '1',
  `component` int(11) unsigned NOT NULL default '0',
  `config` mediumtext NOT NULL,
  KEY `cid` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `homepage_config` */

DROP TABLE IF EXISTS `homepage_config`;

CREATE TABLE `homepage_config` (
  `cid` int(6) unsigned NOT NULL default '0',
  `template` tinyint(1) unsigned NOT NULL default '0',
  `left_head_font` char(7) default NULL,
  `left_head_bg` char(7) default NULL,
  `left_font` char(7) default NULL,
  `left_bg_primary` char(7) default NULL,
  `left_bg_secondary` char(7) default NULL,
  `left_link` char(7) default NULL,
  `left_hover` char(7) default NULL,
  `right_head_font` char(7) default NULL,
  `right_head_bg` char(7) default NULL,
  `right_font` char(7) default NULL,
  `right_bg_primary` char(6) default NULL,
  `right_bg_secondary` char(7) default NULL,
  `right_link` char(7) default NULL,
  `right_hover` char(7) default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `homepage_log` */

DROP TABLE IF EXISTS `homepage_log`;

CREATE TABLE `homepage_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `cid` int(6) NOT NULL default '0',
  `hitcount` int(10) unsigned default '0',
  PRIMARY KEY  (`user`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `house` */

DROP TABLE IF EXISTS `house`;

CREATE TABLE `house` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `house` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;


/*Table structure for table `link` */

DROP TABLE IF EXISTS `link`;

CREATE TABLE `link` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `owner` int(5) unsigned NOT NULL default '0',
  `url` varchar(255) NOT NULL default '',
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime default NULL,
  `description` varchar(255) default NULL,
  `comment` mediumtext,
  `hidden` tinyint(1) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `category` (`category`),
  FULLTEXT KEY `fulltext` (`url`,`description`,`comment`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


/*Table structure for table `luCampus` */

DROP TABLE IF EXISTS `luCampus`;

CREATE TABLE `luCampus` (
  `name` varchar(255) default NULL,
  `id` tinyint(4) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `luEventType` */

DROP TABLE IF EXISTS `luEventType`;

CREATE TABLE `luEventType` (
  `eventTypeID` int(10) unsigned NOT NULL auto_increment,
  `eventType` varchar(100) default NULL,
  `eventColour` varchar(10) default NULL,
  PRIMARY KEY  (`eventTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `luServices` */

DROP TABLE IF EXISTS `luServices`;

CREATE TABLE `luServices` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `luTitle` */

DROP TABLE IF EXISTS `luTitle`;

CREATE TABLE `luTitle` (
  `titleID` int(11) NOT NULL auto_increment,
  `titleName` varchar(20) default NULL,
  PRIMARY KEY  (`titleID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Table structure for table `messages` */

DROP TABLE IF EXISTS `messages`;

CREATE TABLE `messages` (
  `message_id` int(11) unsigned NOT NULL auto_increment,
  `user_id` int(11) unsigned NOT NULL default '0',
  `del` tinyint(1) NOT NULL default '0',
  `cache_key` varchar(128) NOT NULL default '',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `idx` int(11) unsigned NOT NULL default '0',
  `uid` int(11) unsigned NOT NULL default '0',
  `subject` varchar(255) NOT NULL default '',
  `from` varchar(255) NOT NULL default '',
  `to` varchar(255) NOT NULL default '',
  `cc` varchar(255) NOT NULL default '',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `size` int(11) unsigned NOT NULL default '0',
  `headers` mediumtext NOT NULL,
  `structure` mediumtext,
  PRIMARY KEY  (`message_id`),
  UNIQUE KEY `uniqueness` (`user_id`,`cache_key`,`uid`),
  KEY `user_id` (`user_id`),
  KEY `idx` (`idx`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `myFile` */

DROP TABLE IF EXISTS `myFile`;

CREATE TABLE `myFile` (
  `id` int(11) NOT NULL auto_increment,
  `parent` int(11) NOT NULL default '0',
  `owner` int(11) NOT NULL default '0',
  `realname` tinytext NOT NULL,
  `submitted` datetime NOT NULL default '0000-00-00 00:00:00',
  `description` longtext NOT NULL,
  `file_size` int(11) NOT NULL default '0',
  `mime_type` tinytext NOT NULL,
  `deleted` int(11) NOT NULL default '0',
  `published` datetime default NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `myFolder` */

DROP TABLE IF EXISTS `myFolder`;

CREATE TABLE `myFolder` (
  `id` int(11) NOT NULL auto_increment,
  `owner` int(11) NOT NULL default '0',
  `parent` int(11) NOT NULL default '0',
  `name` mediumtext NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `newsCampus` */

DROP TABLE IF EXISTS `newsCampus`;

CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL default '0',
  `campus` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `newsGroup` */

DROP TABLE IF EXISTS `newsGroup`;

CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL default '0',
  `gid` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `newsTopic` */

DROP TABLE IF EXISTS `newsTopic`;

CREATE TABLE `newsTopic` (
  `newsTopicID` int(11) NOT NULL auto_increment,
  `name` varchar(150) default NULL,
  PRIMARY KEY  (`newsTopicID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `news_items` */

DROP TABLE IF EXISTS `news_items`;

CREATE TABLE `news_items` (
  `item_id` int(10) unsigned NOT NULL auto_increment,
  `item_topic_id` smallint(5) unsigned default '0',
  `item_heading` varchar(50) NOT NULL default '',
  `item_datetime_posted` datetime NOT NULL default '0000-00-00 00:00:00',
  `item_datetime_expires` datetime NOT NULL default '0000-00-00 00:00:00',
  `item_datetime_modified` datetime default NULL,
  `item_mainpage` tinyint(3) unsigned NOT NULL default '0',
  `item_body` mediumtext NOT NULL,
  `item_deleted` tinyint(3) unsigned NOT NULL default '0',
  `item_owner` int(6) unsigned NOT NULL default '0',
  `item_campus` int(4) default '0',
  `item_group` int(4) unsigned default NULL,
  PRIMARY KEY  (`item_id`),
  KEY `access` (`item_topic_id`,`item_campus`),
  FULLTEXT KEY `fulltext` (`item_heading`,`item_body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `perms` */

DROP TABLE IF EXISTS `perms`;

CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL default '0',
  `gid` int(4) NOT NULL default '0',
  `type` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `poll` */

DROP TABLE IF EXISTS `poll`;

CREATE TABLE `poll` (
  `pollID` int(11) NOT NULL auto_increment,
  `categoryID` int(11) default NULL,
  `title` varchar(120) default NULL,
  `question` varchar(250) default NULL,
  `option_0` varchar(120) default NULL,
  `option_1` varchar(120) default NULL,
  `option_2` varchar(120) default NULL,
  `option_3` varchar(120) default NULL,
  `option_4` varchar(120) default NULL,
  `option_5` varchar(120) default NULL,
  `option_6` varchar(120) default NULL,
  `option_7` varchar(120) default NULL,
  `option_8` varchar(120) default NULL,
  `option_9` varchar(120) default NULL,
  `result_0` int(11) NOT NULL,
  `result_1` int(11) NOT NULL,
  `result_2` int(11) NOT NULL,
  `result_3` int(11) NOT NULL,
  `result_4` int(11) NOT NULL,
  `result_5` int(11) NOT NULL,
  `result_6` int(11) NOT NULL,
  `result_7` int(11) NOT NULL,
  `result_8` int(11) NOT NULL,
  `result_9` int(11) NOT NULL,
  `frequency` enum('once','hourly','daily') NOT NULL default 'once',
  `created` datetime default NULL,
  `close` datetime default NULL,
  PRIMARY KEY  (`pollID`),
  KEY `categoryID` (`categoryID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `poll` */

/*Table structure for table `pollLog` */

DROP TABLE IF EXISTS `pollLog`;

CREATE TABLE `pollLog` (
  `pollID` int(11) default NULL,
  `userID` int(11) default NULL,
  `created` datetime default NULL,
  KEY `pollID` (`pollID`,`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `pollLog` */


/*Table structure for table `revision` */

DROP TABLE IF EXISTS `revision`;

CREATE TABLE `revision` (
  `revisionID` int(11) NOT NULL auto_increment,
  `wikiID` int(11) default NULL,
  `heading` varchar(200) default NULL,
  `body` mediumtext,
  `userID` int(11) default NULL,
  `created` datetime default NULL,
  PRIMARY KEY  (`revisionID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `revision` */

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `sideMenuCampus` */

DROP TABLE IF EXISTS `sideMenuCampus`;

CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL default '0',
  `campus` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `sideMenuGroup` */

DROP TABLE IF EXISTS `sideMenuGroup`;

CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL default '0',
  `gid` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `subject_news` */

DROP TABLE IF EXISTS `subject_news`;

CREATE TABLE `subject_news` (
  `id` int(6) NOT NULL auto_increment,
  `heading` varchar(50) default NULL,
  `datetime_posted` datetime NOT NULL default '0000-00-00 00:00:00',
  `datetime_expires` datetime NOT NULL default '0000-00-00 00:00:00',
  `body` mediumtext NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `code` int(6) unsigned NOT NULL default '0',
  `owner` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `category` (`code`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `submission_box` */

DROP TABLE IF EXISTS `submission_box`;

CREATE TABLE `submission_box` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `category` int(5) unsigned NOT NULL default '0',
  `type` int(11) default NULL,
  `owner` int(4) unsigned NOT NULL default '0',
  `open_date` datetime default NULL,
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



/*Table structure for table `submission_log` */

DROP TABLE IF EXISTS `submission_log`;

CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `page` tinyint(4) NOT NULL default '0',
  `sid` int(6) NOT NULL default '0',
  PRIMARY KEY  (`user`,`page`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


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
  `submitted` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `superusers` */

DROP TABLE IF EXISTS `superusers`;

CREATE TABLE `superusers` (
  `id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `textBox` */

DROP TABLE IF EXISTS `textBox`;

CREATE TABLE `textBox` (
  `textBoxID` int(11) NOT NULL auto_increment,
  `categoryID` int(11) default NULL,
  `heading` varchar(100) default NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`textBoxID`),
  KEY `categoryID` (`categoryID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `uhomepage` */

DROP TABLE IF EXISTS `uhomepage`;

CREATE TABLE `uhomepage` (
  `owner` int(11) NOT NULL default '0',
  `education` mediumtext,
  `personal` mediumtext,
  `school` mediumtext,
  PRIMARY KEY  (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


/*Table structure for table `user` */

DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `username` varchar(60) NOT NULL default '',
  `password` varchar(80) default NULL,
  `group_id` int(4) unsigned NOT NULL default '0',
  `title` varchar(10) default NULL,
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
  `hash` varchar(40) default NULL,
  `key` varchar(40) default NULL,
  `eportfolio_enabled` int(8) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `synergy` (`synergy_id`),
  KEY `username` (`username`),
  KEY `groupind` (`group_id`),
  FULLTEXT KEY `fulltext` (`username`,`title`,`firstname`,`lastname`,`alt_email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `userAccess` */

DROP TABLE IF EXISTS `userAccess`;

CREATE TABLE `userAccess` (
  `uid` int(11) NOT NULL default '0',
  `service` int(11) NOT NULL default '0',
  `sid` int(11) NOT NULL default '0',
  `type` smallint(6) default NULL,
  PRIMARY KEY  (`service`,`sid`,`uid`),
  KEY `uid` (`uid`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `userCampus` */

DROP TABLE IF EXISTS `userCampus`;

CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL default '0',
  `campus` tinyint(3) unsigned NOT NULL default '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `userGroups` */

DROP TABLE IF EXISTS `userGroups`;

CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL default '0',
  `type` tinyint(2) default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `welcomeComponent` */

DROP TABLE IF EXISTS `welcomeComponent`;

CREATE TABLE `welcomeComponent` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `filename` varchar(100) default '0',
  `function` varchar(100) default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

/*Table structure for table `welcomeLayout` */

DROP TABLE IF EXISTS `welcomeLayout`;

CREATE TABLE `welcomeLayout` (
  `gid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(3) unsigned NOT NULL default '0',
  `componentID` int(10) unsigned default '0',
  PRIMARY KEY  (`gid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

/*Table structure for table `wiki` */

DROP TABLE IF EXISTS `wiki`;

CREATE TABLE `wiki` (
  `wikiID` int(11) NOT NULL auto_increment,
  `categoryID` int(11) default NULL,
  `latestRevision` int(11) NOT NULL,
  `created` datetime default NULL,
  `deleted` enum('true','false') NOT NULL default 'false',
  `locked` enum('true','false') NOT NULL default 'false',
  PRIMARY KEY  (`wikiID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `wikiOpens` */

DROP TABLE IF EXISTS `wikiOpens`;

CREATE TABLE `wikiOpens` (
  `openID` int(8) NOT NULL auto_increment,
  `wikiID` int(8) default NULL,
  `userID` int(8) default NULL,
  `lastopened` datetime default NULL,
  PRIMARY KEY  (`openID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `wikiRevision` */

DROP TABLE IF EXISTS `wikiRevision`;

CREATE TABLE `wikiRevision` (
  `revisionID` int(11) NOT NULL auto_increment,
  `wikiID` int(11) default NULL,
  `heading` varchar(200) default NULL,
  `body` mediumtext,
  `userID` int(11) default NULL,
  `created` datetime default NULL,
  PRIMARY KEY  (`revisionID`),
  KEY `wikiID` (`wikiID`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
