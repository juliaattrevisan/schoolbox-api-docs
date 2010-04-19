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

/*Data for the table `allowedFileExtension` */

insert  into `allowedFileExtension`(`fileTypeID`,`extension`) values (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtf'),(4,'rtx'),(5,'css'),(6,'jpg'),(6,'jpeg'),(6,'jpe'),(7,'pjpg'),(7,'jpg'),(7,'jpeg'),(7,'jpe'),(8,'png'),(9,'bmp'),(10,'pdf'),(11,'doc'),(12,'ppt'),(12,'pps'),(13,'xls'),(13,'csv'),(14,'zip'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(17,'ogg'),(18,'gsp'),(19,'swf'),(19,'cab'),(20,'ins'),(20,'isf'),(21,'msw'),(21,'mswmm'),(21,'re3'),(21,'ogg'),(21,'isf'),(21,'ins'),(22,'wav'),(23,'mp3'),(23,'mp2'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'midi'),(26,'mid'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(29,'wmv'),(30,'avi'),(21,'wmv'),(3,'csv'),(31,'zip');

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

/*Data for the table `allowedFileTypes` */

insert  into `allowedFileTypes`(`fileTypeID`,`description`,`mime`,`iconFile`,`inline`,`enable`) values (1,'GIF Image','image/gif','image-gif.png','true','true'),(2,'HTML Document','text/html','text-html.png','true','true'),(3,'Plain Text','text/plain','textfile.png','true','true'),(4,'Rich Text','text/richtext','application-rtf.png','true','true'),(5,'CSS','text/css','textfile.png','true','true'),(6,'JPEG Image','image/jpeg','image-jpeg.png','true','true'),(7,'Progressing JPEG Image','image/pjpeg','image-jpeg.png','true','true'),(8,'PNG Image','image/png','image-png.png','true','true'),(9,'Bitmap Image','image/bmp','image-bmp.png','true','true'),(10,'PDF Document','application/pdf','application-pdf.png','true','true'),(11,'Word Document','application/msword','application-msword.png','true','true'),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','application-vnd.ms-powerpoint.png','true','true'),(13,'Excel Spreadsheet','application/vnd.ms-excel','application-vnd.ms-excel.png','true','true'),(14,'ZIP Archive','application/x-zip-compressed','compressed.png','false','true'),(15,'Postscript','application/postscript','application-postscript.png','true','true'),(16,'Photoshop Image','application/x-photoshop','image-psd.png','false','true'),(17,'OGG Audio','application/ogg','audio-x-wav.png','true','true'),(18,'Geometer\'s Sketchpad','application/x-gsp','application-x-none.png','false','true'),(19,'Shockwave Flash','application/x-shockwave-flash','application-x-none.png','true','true'),(20,'Inspiration','application/x-Inspiration','application-x-none.png','false','true'),(21,'Binary Data (Unknown)','application/octet-stream','application-x-none.png','false','true'),(22,'WAV Audio','audio/wav','audio-x-wav.png','false','true'),(23,'MPEG Audio','audio/mpeg','audio-mpeg.png','false','true'),(24,'Real Audio Stream','audio/x-pn-realaudio','application-vnd.rn-realmedia.png','false','true'),(25,'Real Audio','audio/x-realaudio','audio-x-pn-realaudio.png','false','true'),(26,'MIDI Audio','audio/mid','audio-midi.png','false','true'),(27,'MPEG Video','video/mpeg','video-mpeg.png','false','true'),(28,'Quicktime Video','video/quicktime','video-quicktime.png','false','true'),(29,'WMV Video','video/x-ms-wmv','video-x-msvideo.png','true','true'),(30,'Microsoft Video','video/x-msvideo','video-x-msvideo.png','false','true'),(31,'ZIP Archive','application/zip','compressed.png','true','true');

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
) ENGINE=MyISAM AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;

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
) ENGINE=MyISAM AUTO_INCREMENT=86 DEFAULT CHARSET=utf8;

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

/*Data for the table `cache` */

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

/*Data for the table `category` */

insert  into `category`(`id`,`name`,`parent`,`template`,`teacherid`,`code`) values (1,'Classes',0,1,0,''),(2,'Sport',0,0,0,'');

/*Table structure for table `categoryCode` */

DROP TABLE IF EXISTS `categoryCode`;

CREATE TABLE `categoryCode` (
  `categoryID` int(11) default NULL,
  `code` varchar(20) default NULL,
  UNIQUE KEY `categoryIDcode` (`categoryID`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `categoryCode` */

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

/*Data for the table `dropbox` */


/*Table structure for table `error` */

DROP TABLE IF EXISTS `error`;

CREATE TABLE `error` (
  `id` int(3) unsigned NOT NULL default '0',
  `message` mediumtext,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `error` */

insert  into `error`(`id`,`message`) values (1,'You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.'),(2,'Not enough information was submitted to the form.'),(3,'Please enter either a file or a url, not both.'),(4,'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the portal.<br><br>Please contact your System Administrator if problems persist.'),(5,'You do not have permission to download this file.'),(6,'You do not have write permission to this category.'),(7,'Mail cannot be sent at this time. Please try again later.'),(8,'You do not have permission to delete this resource.'),(9,'Processing failed for the following reason: %MESSAGE%'),(10,'The size of this file exceeds the allowed limit.'),(11,'%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Portal.<br>If you would like to be able to add files of this type to the Portal please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Portal.'),(12,'The category that you are trying to delete is not empty. Please delete all its contents, then try again.'),(13,'The category that you are trying to view does not exist.'),(14,'You do not have permission to view this category.'),(15,'The date that you have submitted is invalid. Please check it, then try again.'),(16,'There was a problem validating your account: <br><b>%MESSAGE%</b>'),(17,'The submission box that you are trying to access does not exist or you do not have permission to view it.'),(18,'The subject selected does not exist.'),(19,'You do not have permission to view this page.'),(20,'You do not have permission to create a category in this location.'),(21,'A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%'),(22,'The database is unavailable at this time. Please try again later.'),(23,'You do not have permission to modify this news.'),(24,'You are not enrolled in this subject.'),(25,'You do not have permission to return this work to this student.'),(26,'The work you are trying to return either does not exist or has already been returned.'),(27,'The work you are trying to submit has already been submitted.'),(28,'You do not have permission to post in this category.'),(29,'You do not have permission to modify or delete this post.'),(30,'This user cannot be the group administrator.'),(31,'No user was found that matches your request.'),(32,'The absentee system cannot be accessed on the weekend.'),(33,'That period does not exist today.'),(34,'You do not have permission to modify this event.'),(35,'You do not have permission to add an event of this context.'),(36,'You are attempting to submit work to a submission box that has not yet opened.'),(37,'You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.'),(38,'This is already in the database. Please modify, then try again.'),(39,'No teams exist. You must add teams before trying to add events.'),(40,'Attendances for Grimwade have not yet been implemented. Please wait until further notice.'),(41,'That file does not exist.'),(42,'No email account information was found. Please either use an external mail client to send email, or provide the portal with an email address.'),(43,'The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.'),(44,'You are trying to view or delete work that either does not exist or does not belong to you.'),(45,'Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".'),(46,'You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.'),(101,'The resource you are trying to access does not exist or you do not have permission to view it.'),(102,'A booking already exists during the time you have selected.  Please press back and select a time that is free.'),(103,'The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.'),(104,'You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.'),(47,'You do not have permission to modify this user\'s file.'),(48,'You do not have permission to delete this user\'s file.'),(49,'The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.'),(105,'<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>');

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

/*Data for the table `file` */

insert  into `file`(`id`,`category`,`owner`,`realname`,`submitted`,`modified`,`description`,`comment`,`type`,`file_size`,`mime_type`,`deleted`,`hidden`) values (1,1,1,'product-attributes.txt','2009-05-20 18:22:12','2009-05-20 18:22:12','test','',0,3318,'text/plain',0,0),(2,1,1,'product-attributes.txt','2009-05-20 18:23:02','2009-05-20 18:23:02','test','',0,3318,'text/plain',0,0),(3,1,1,'Developing the feed system.doc','2009-05-20 18:27:42','2009-05-20 18:27:42','Homework','',0,27648,'application/msword',0,0),(4,1,1,'xmlStockLookupDocumentation.pdf','2009-05-20 18:33:46','2009-05-20 18:33:46','Assignment','',0,57521,'application/pdf',0,0);

/*Table structure for table `file_type` */

DROP TABLE IF EXISTS `file_type`;

CREATE TABLE `file_type` (
  `id` int(4) NOT NULL auto_increment,
  `name` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `file_type` */

insert  into `file_type`(`id`,`name`) values (1,'Assessment Task'),(2,'Assignment'),(3,'Homework'),(6,'Research Project'),(5,'Essay'),(7,'Class Work'),(8,'Presentation'),(9,'Oral'),(10,'Extension');

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
) ENGINE=MyISAM AUTO_INCREMENT=52 DEFAULT CHARSET=utf8;

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
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;

/*Table structure for table `groups` */

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `name` varchar(20) NOT NULL default '',
  `staff` smallint(6) default NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `groups` */

insert  into `groups`(`id`,`name`,`staff`) values (1,'Administration',1),(2,'Teachers',1),(3,'Students',0),(4,'Parents',0),(5,'Junior Students',0);

/*Table structure for table `homepageTemplate` */

DROP TABLE IF EXISTS `homepageTemplate`;

CREATE TABLE `homepageTemplate` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `layout` smallint(6) default '1',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `homepageTemplate` */

insert  into `homepageTemplate`(`id`,`name`,`layout`) values (1,'Class',1),(2,'Collab Group',1),(4,'Faculty',1),(3,'Extra-Curricular',1),(5,'Committee',1),(6,'House',1);

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

/*Data for the table `homepageTemplateLayout` */

insert  into `homepageTemplateLayout`(`templateID`,`componentId`,`column`,`position`) values (1,2,2,3),(1,3,1,3),(1,8,1,1),(1,9,2,5),(1,10,2,6),(1,25,2,2),(1,28,1,2),(1,30,2,1),(1,35,2,4),(2,3,1,3),(2,4,2,1),(2,5,2,3),(2,8,1,1),(2,10,2,5),(2,21,2,2),(2,34,1,2),(2,35,2,4),(3,2,2,5),(3,3,1,3),(3,4,2,2),(3,5,2,3),(3,8,1,2),(3,10,2,6),(3,21,2,1),(3,23,1,1),(3,33,2,4),(3,35,1,4),(4,2,2,3),(4,3,1,3),(4,8,1,2),(4,10,2,4),(4,13,2,1),(4,15,1,1),(4,20,2,2),(5,3,1,3),(5,4,2,2),(5,5,2,3),(5,8,1,1),(5,10,2,5),(5,21,2,2),(5,34,1,2),(5,35,2,4),(6,3,1,3),(6,4,2,1),(6,5,2,3),(6,8,1,1),(6,10,2,5),(6,21,2,2),(6,34,1,2),(6,35,2,4);

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

/*Data for the table `homepage_comp` */

insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`filename`,`config`,`invisible`) values (8,1,'outputNews','News','Provides a text area for you to post information, news, blogs and introductions. ','news_n.htm','a:1:{s:4:\"size\";i:0;}','false'),(2,1,'outputLinkList','Links','Provide links to interesting, related and helpful resource on the portal or the WWW.','links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false'),(3,1,'outputFileList','Files','List general files that have been uploaded to your homepage. The large version shows both titles and comments.','files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}','false'),(4,2,'outputMembers','Group Members','Show the group members associated with your homepage.  This also provides an email all group members link.','members_s.htm','','false'),(5,2,'outputForumList','Forum Hot Topics','Show recent discussion topics going on in your homepages forum.','forum_s.htm','a:2:{s:4:"sort";s:4:"Date";s:4:"size";i:0;}','false'),(6,2,'outputLinkList','Small Links','Provide links to interesting, related and helpful resource on the portal or the WWW.','links_s.htm','a:1:{s:4:\"size\";i:0;}','true'),(7,2,'outputFileList','Small Files','List general files that have been uploaded to your homepage. The small version only shows file titles.','files_s.htm','a:1:{s:4:\"size\";i:0;}','true'),(9,2,'initSubject','Class Management','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.','class_manage.htm','','false'),(10,2,'folderManage','Folder Management','Provides links to browse the files, manage group membership and view homepage statistics.','folder_manage.htm','','false'),(11,2,'outputAssignList','Small Assignment','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.','assign_s.htm','a:1:{s:4:\"size\";i:0;}','true'),(12,2,'outputHomeworkList','Small Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.','homework_s.htm','a:1:{s:4:\"size\";i:0;}','true'),(13,2,'outputManagers','Group Managers','Displays a list of group members with write access to the homepage.  Useful in larger groups to show manages of the group.','staff_s.htm','','false'),(14,1,'outputImage','Image Simple','Just displays an image on your page.','image_s.htm','','false'),(15,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location','image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false'),(16,1,'outputAssignList','Assignments','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.','assign_n.htm','a:1:{s:4:\"size\";i:0;}','false'),(17,1,'outputHomeworkList','Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.','homework_n.htm','a:1:{s:4:\"size\";i:0;}','false'),(20,2,'outputFolderList','Folders','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.','folders_s.htm','a:1:{s:4:\"size\";i:0;}','false'),(21,1,'outputEventList','Calendar','Display upcoming events that are in the calendar for this group.','cal_n.htm','a:1:{s:4:\"size\";i:0;}','false'),(22,2,'outputEventList','Small Calendar','Display upcoming events that are in the calendar for this group.','cal_s.htm','a:1:{s:4:\"size\";i:0;}','true'),(23,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ','gallery.htm','a:1:{s:4:\"size\";i:0;}','false'),(24,1,'outputRandImage','Random Image','Display a random image selected from the images on this homepage.','image_s.htm','a:1:{s:5:\"pixel\";i:400;}','false'),(25,2,'outputDropBox','File Drop Box','Provides an area for teachers to recieve work via the portal without the need for a submission box.','dropbox.htm','','false'),(28,1,'outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.','duework_n.htm','a:1:{s:4:\"size\";i:0;}','false'),(27,2,'outputRSS','Small RSS Feed','Display external RSS site headlines and brief description. The RSS feed can be from any site that provides a feed.','rss_s.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','true'),(26,1,'outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.','rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','false'),(30,2,'outputTeachers','Class Teachers','Show a list of teachers responsible for this subject if it has a synergetic subject code.','teacher_s.htm','','false'),(29,2,'outputDueWorkList','Small Due Work','Shows a list of all due work and its due date.','duework_s.htm','a:1:{s:4:\"size\";i:0;}','true'),(31,1,'outputCalendarList','Campus Calendar','Show a calendar for the entire campus on your homepage.','cal_n.htm','','false'),(32,0,'outputDuties','Duties',NULL,'duties.htm','','false'),(33,2,'outputPoll','Poll','Create a poll to determine group members opinion on a given subject.','poll.htm','a:1:{s:6:\"pollID\";i:0;}','false'),(34,1,'outputWiki','Wiki','Provides an area for group members to work together on joint documents.','wiki.htm','a:1:{s:4:\"size\";i:0;}','false'),(35,1,'outputFeed','Homepage Feed','Provides a running commentary on recent changes to the homepage','feed.htm','a:1:{s:4:\"size\";i:0;}','false'),(36,1,'outputTextBox','Text Box','Use this space to write introductory message or information relating to the content that is displayed','textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false'),(37,1,'outputAudio','Audio','Lists audio files and allows them to be played.','audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false'),(38,1,'outputPodcast','Podcast','Subscribe to a podcast ','podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false');

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

/*Data for the table `homepage_comp_location` */

insert  into `homepage_comp_location`(`cid`,`position`,`column`,`component`,`config`) values (1,1,1,8,'a:1:{s:4:\"size\";i:0;}'),(1,2,1,28,'a:1:{s:4:\"size\";i:0;}'),(1,3,1,3,'a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}'),(1,1,2,30,''),(1,2,2,25,''),(1,3,2,2,'a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}'),(1,4,2,35,'a:1:{s:4:\"size\";i:0;}'),(1,5,2,9,''),(1,6,2,10,'');

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

/*Data for the table `homepage_config` */

insert  into `homepage_config`(`cid`,`template`,`left_head_font`,`left_head_bg`,`left_font`,`left_bg_primary`,`left_bg_secondary`,`left_link`,`left_hover`,`right_head_font`,`right_head_bg`,`right_font`,`right_bg_primary`,`right_bg_secondary`,`right_link`,`right_hover`) values (1,1,'000000','dbe7f2','000000','FFFFFF','f5f5f5','0033CC','0066CC','000000','dbe7f2','000000','FFFFFF','f5f5f5','0033CC','0066CC');

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

/*Data for the table `house` */

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

/*Data for the table `link` */

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

/*Data for the table `luCampus` */

insert  into `luCampus`(`name`,`id`) values ('Main',1),('Other',2);

/*Table structure for table `luEventType` */

DROP TABLE IF EXISTS `luEventType`;

CREATE TABLE `luEventType` (
  `eventTypeID` int(10) unsigned NOT NULL auto_increment,
  `eventType` varchar(100) default NULL,
  `eventColour` varchar(10) default NULL,
  PRIMARY KEY  (`eventTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert  into `luEventType`(`eventTypeID`,`eventType`,`eventColour`) values (1,'Event','000000'),(2,'Excursion','0033FF'),(3,'Report','FF9900'),(4,'Exam','FF0000'),(5,'Meeting','009933');

/*Table structure for table `luServices` */

DROP TABLE IF EXISTS `luServices`;

CREATE TABLE `luServices` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `luServices` */

insert  into `luServices`(`id`,`name`) values (1,'Homepage'),(2,'Calendar'),(3,'News'),(4,'Tutorial'),(5,'CMS');

/*Table structure for table `luTitle` */

DROP TABLE IF EXISTS `luTitle`;

CREATE TABLE `luTitle` (
  `titleID` int(11) NOT NULL auto_increment,
  `titleName` varchar(20) default NULL,
  PRIMARY KEY  (`titleID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Data for the table `luTitle` */

insert  into `luTitle`(`titleID`,`titleName`) values (2,'Mr'),(3,'Mrs'),(4,'Ms'),(9,'Prof'),(8,'Hon Dr'),(7,'Hon'),(6,'Dr'),(5,'Miss'),(10,'A/Prof'),(1,NULL);

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

/*Data for the table `messages` */

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
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `myFile` */

insert  into `myFile`(`id`,`parent`,`owner`,`realname`,`submitted`,`description`,`file_size`,`mime_type`,`deleted`) values (1,29,1,'product-attributes.txt','2009-05-20 18:25:20','Test',3318,'text/plain',0);

/*Table structure for table `myFolder` */

DROP TABLE IF EXISTS `myFolder`;

CREATE TABLE `myFolder` (
  `id` int(11) NOT NULL auto_increment,
  `owner` int(11) NOT NULL default '0',
  `parent` int(11) NOT NULL default '0',
  `name` mediumtext NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `myFolder` */

insert  into `myFolder`(`id`,`owner`,`parent`,`name`) values (29,1,0,'Random');

/*Table structure for table `newsCampus` */

DROP TABLE IF EXISTS `newsCampus`;

CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL default '0',
  `campus` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `newsCampus` */

insert  into `newsCampus`(`id`,`campus`) values (88,1);

/*Table structure for table `newsGroup` */

DROP TABLE IF EXISTS `newsGroup`;

CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL default '0',
  `gid` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `newsGroup` */

insert  into `newsGroup`(`id`,`gid`) values (88,1),(88,2),(88,3),(88,4),(88,5);

/*Table structure for table `newsTopic` */

DROP TABLE IF EXISTS `newsTopic`;

CREATE TABLE `newsTopic` (
  `newsTopicID` int(11) NOT NULL auto_increment,
  `name` varchar(150) default NULL,
  PRIMARY KEY  (`newsTopicID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `newsTopic` */

insert  into `newsTopic`(`newsTopicID`,`name`) values (1,'General'),(2,'Music'),(3,'Sport'),(4,'Academic');

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
  KEY `access` (`item_topic_id`,`item_campus`),
  FULLTEXT KEY `fulltext` (`item_heading`,`item_body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `news_items` */

insert  into `news_items`(`item_id`,`item_topic_id`,`item_heading`,`item_datetime_posted`,`item_datetime_expires`,`item_datetime_modified`,`item_mainpage`,`item_body`,`item_link1`,`item_link2`,`item_link3`,`item_link4`,`item_link5`,`item_deleted`,`item_owner`,`item_campus`,`item_group`) values (88,1,'Welcome to the OurSchool Demonstration Site','2009-05-20 18:50:35','2010-05-20 18:50:35','2009-05-20 18:51:20',0,'<p>Welcome to the OurSchool Demonstration.&nbsp; This area is designed to present the interface elements and basic features of OurSchool.</p><p>The key areas of interest is management of homepages and resources.&nbsp; To get to your homepage, just click the link under \'My Groups\'.&nbsp; From here you can manage content, layouts and add additional functionality.</p><p>If you have any questions please contact <a href=\"mailto:james.leckie@alaress.com.au\">James Leckie</a> at Alaress.&nbsp; </p><p>&nbsp;</p>',NULL,NULL,NULL,NULL,NULL,0,1,0,NULL);

/*Table structure for table `perms` */

DROP TABLE IF EXISTS `perms`;

CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL default '0',
  `gid` int(4) NOT NULL default '0',
  `type` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `perms` */

insert  into `perms`(`cid`,`gid`,`type`) values (136,1,2),(136,2,1),(136,3,1),(136,4,1),(136,5,1),(137,1,2),(137,2,1),(137,3,1),(137,4,1),(137,5,1),(1,1,2),(1,2,1),(1,3,1),(1,4,1),(1,5,1);

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

/*Data for the table `sideMenu` */

insert  into `sideMenu`(`id`,`parent`,`txt`,`url`,`target`,`sequence`) values (1,0,'Home','/home.php','',0),(9,1,'Main','/home.php','',4),(93,9,'Edit My Links','/editLinks.php','',0),(94,9,'Manage My Groups','/group/join','',1),(10,1,'News','/news','',5),(96,10,'View News','/news','',1),(97,10,'Add News','/news/modify','',0),(12,1,'Webmail','/mail.php','',7),(98,12,'View Mail','/mail.php','',2),(99,12,'Send Mail','/mail/send.php','',1),(100,12,'Address Book','/search/user','',0),(13,1,'Calendar','/calendar/week','',2),(14,1,'Resources','/resources','',1),(103,14,'File to Multiple Folders','/cms/file/multicat','',0),(105,14,'Link to Multiple Folders','/cms/link/multicat','',1),(229,1,'Change Password','/account/modify','',8),(15,1,'Submissions','/submission/listSubmission.php','',6),(219,1,'User Directory','/search/user','',9),(2,0,'Student Services','#','',5),(3,0,'Staff Services','#','',1),(4,0,'Faculties','#','',2),(7,0,'Houses','#','',4),(8,0,'Sport','#','',3),(207,0,'Logout','/logout.php','',7),(208,1,'Admin','/admin/admin.php','',0),(209,208,'Add User','/admin/modUser.php?adduser=1','',0),(211,208,'Modify User','/admin/modUser.php','',2),(213,1,'Due Work','/submission/dueWork.php','',3),(212,208,'Modify Side Menu','/admin/modSideMenu.php','',1),(230,15,'Create Submission Box','/submission/createSubmission.php','',0),(218,208,'Modify File Types','/admin/modFileType.php','',3),(221,220,'Statistics','/modules/tutorial/report/statistics.php','',2),(222,220,'Mark Attendance','/modules/tutorial/attendance/list.php','',1),(224,220,'Reports','#','',0),(225,224,'Uncompleted Attendance','/modules/tutorial/report/attendance.php','',0),(226,224,'Hours Performed','/modules/tutorial/report/hours.php','',1),(227,224,'Student Attendance','/modules/tutorial/report/student.php','',2),(228,0,'Manage Groups','/group/join','',6);

/*Table structure for table `sideMenuCampus` */

DROP TABLE IF EXISTS `sideMenuCampus`;

CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL default '0',
  `campus` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `sideMenuCampus` */

insert  into `sideMenuCampus`(`id`,`campus`) values (1,1),(2,1),(3,1),(7,1),(8,1),(9,1),(10,1),(13,1),(14,1),(15,1),(93,1),(94,1),(96,1),(97,1),(103,1),(105,1),(207,1),(213,1),(221,1),(222,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(221,2),(222,2),(224,2),(225,2),(226,2),(227,2),(228,2),(229,2),(230,2);

/*Table structure for table `sideMenuGroup` */

DROP TABLE IF EXISTS `sideMenuGroup`;

CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL default '0',
  `gid` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `sideMenuGroup` */

insert  into `sideMenuGroup`(`id`,`gid`) values (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(2,3),(2,4),(2,5),(3,1),(3,2),(3,3),(3,5),(4,1),(4,2),(4,3),(4,4),(4,5),(7,1),(7,2),(7,3),(7,4),(7,5),(8,1),(8,2),(8,3),(8,5),(9,1),(9,2),(9,3),(9,4),(9,5),(10,1),(10,2),(10,3),(10,4),(10,5),(13,1),(13,2),(13,3),(13,4),(13,5),(14,1),(14,2),(14,3),(14,5),(15,1),(15,2),(93,1),(93,2),(93,3),(93,4),(93,5),(94,1),(94,2),(94,3),(94,4),(94,5),(96,1),(96,2),(96,3),(96,4),(96,5),(97,1),(97,2),(103,1),(103,2),(105,1),(105,2),(207,1),(207,2),(207,3),(207,4),(207,5),(213,3),(213,4),(213,5),(221,1),(221,2),(222,1),(222,2),(224,1),(225,1),(226,1),(227,1),(228,1),(228,2),(228,3),(228,4),(228,5),(229,1),(229,2),(229,3),(229,4),(229,5),(230,1),(230,2);

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

/*Data for the table `subject_news` */

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

/*Data for the table `submission_box` */


/*Table structure for table `submission_log` */

DROP TABLE IF EXISTS `submission_log`;

CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `page` tinyint(4) NOT NULL default '0',
  `sid` int(6) NOT NULL default '0',
  PRIMARY KEY  (`user`,`page`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `submission_log` */

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

/*Data for the table `submission_return` */

/*Table structure for table `superusers` */

DROP TABLE IF EXISTS `superusers`;

CREATE TABLE `superusers` (
  `id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `superusers` */

insert  into `superusers`(`id`) values (1);

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

/*Data for the table `textBox` */

/*Table structure for table `uhomepage` */

DROP TABLE IF EXISTS `uhomepage`;

CREATE TABLE `uhomepage` (
  `owner` int(11) NOT NULL default '0',
  `education` mediumtext,
  `personal` mediumtext,
  `school` mediumtext,
  PRIMARY KEY  (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `uhomepage` */

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
  PRIMARY KEY  (`id`),
  KEY `synergy` (`synergy_id`),
  KEY `username` (`username`),
  KEY `groupind` (`group_id`),
  FULLTEXT KEY `fulltext` (`username`,`title`,`firstname`,`lastname`,`alt_email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `user` */

insert  into `user`(`id`,`username`,`password`,`group_id`,`title`,`firstname`,`lastname`,`email_pass`,`synergy_id`,`campus`,`year`,`house`,`local_email`,`alt_email`,`mail_handler`,`hash`) values (1,'jleckie','*7968DF3FE2AF92916A9D4F5BE24010B7707AC68F',1,NULL,'James','Leckie',NULL,0,0,NULL,NULL,0,NULL,0,NULL));

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

/*Data for the table `userAccess` */

insert  into `userAccess`(`uid`,`service`,`sid`,`type`) values (1,1,1,1);

/*Table structure for table `userCampus` */

DROP TABLE IF EXISTS `userCampus`;

CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL default '0',
  `campus` tinyint(3) unsigned NOT NULL default '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `userCampus` */

insert  into `userCampus`(`uid`,`campus`,`defaultCampus`) values (2,1,0),(3,1,0);

/*Table structure for table `userGroups` */

DROP TABLE IF EXISTS `userGroups`;

CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL default '0',
  `type` tinyint(2) default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `userGroups` */

/*Table structure for table `welcomeComponent` */

DROP TABLE IF EXISTS `welcomeComponent`;

CREATE TABLE `welcomeComponent` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `filename` varchar(100) default '0',
  `function` varchar(100) default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

/*Data for the table `welcomeComponent` */

insert  into `welcomeComponent`(`id`,`name`,`filename`,`function`) values (1,'News','news.htm','outputNews'),(2,'Weather','weather.htm','outputWeather'),(3,'Calendar','calendar.htm','outputCalendar'),(4,'Whats New','changedfolder.htm','outputWhatNew'),(5,'My Students','studentSubjects.htm','outputChildSubjects'),(6,'Timetable','','outputTimetable'),(7,'Helpdesk','helpdesk.htm','outputHelpdesk'),(8,'Parent Helpdesk','parentHelpdesk.htm','outputParentHelpdesk'),(9,'Wenona Intranet Login','wenonaIntranet.htm','outputWenonaIntranet'),(10,'RSS Feed','rss.htm','outputRSS'),(11,'Tutorials','tutorial.html','outputTutorial');

/*Table structure for table `welcomeLayout` */

DROP TABLE IF EXISTS `welcomeLayout`;

CREATE TABLE `welcomeLayout` (
  `gid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(3) unsigned NOT NULL default '0',
  `componentID` int(10) unsigned default '0',
  PRIMARY KEY  (`gid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

/*Data for the table `welcomeLayout` */

insert  into `welcomeLayout`(`gid`,`position`,`componentID`) values (1,1,1),(1,2,3),(1,3,10),(1,4,NULL),(1,5,2),(1,6,4),(1,7,NULL),(1,8,NULL),(2,1,1),(2,2,3),(2,3,NULL),(2,4,NULL),(2,5,2),(2,6,4),(2,7,NULL),(2,8,NULL),(3,1,1),(3,2,3),(3,3,NULL),(3,4,NULL),(3,5,2),(3,6,4),(3,7,NULL),(3,8,NULL),(4,1,1),(4,2,3),(4,3,8),(4,4,NULL),(4,5,2),(4,6,4),(4,7,5),(4,8,0),(1,0,6),(2,0,6),(3,0,6),(4,0,6),(5,0,6),(5,8,NULL),(5,7,NULL),(5,6,4),(5,5,2),(5,4,NULL),(5,3,NULL),(5,2,3),(5,1,1);

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

/*Data for the table `wiki` */

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
) ENGINE=MyISAM AUTO_INCREMENT=85 DEFAULT CHARSET=utf8;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
