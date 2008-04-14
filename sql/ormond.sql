/*
SQLyog Community Edition- MySQL GUI v5.2 Beta 1
Host - 4.1.16 : Database - ormond
*********************************************************************
Server version : 4.1.16
*/

SET NAMES utf8;

SET SQL_MODE='';

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

/*Table structure for table `allowedFileExtension` */

DROP TABLE IF EXISTS `allowedFileExtension`;

CREATE TABLE `allowedFileExtension` (
  `fileTypeID` int(11) default NULL,
  `extension` char(6) default NULL,
  KEY `fileTypeID` (`fileTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `allowedFileExtension` */

insert into `allowedFileExtension`(`fileTypeID`,`extension`) values (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtf'),(4,'rtx'),(5,'css'),(6,'jpg'),(6,'jpeg'),(6,'jpe'),(7,'pjpg'),(7,'jpg'),(7,'jpeg'),(7,'jpe'),(8,'png'),(9,'bmp'),(10,'pdf'),(11,'doc'),(12,'ppt'),(12,'pps'),(13,'xls'),(13,'csv'),(14,'zip'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(17,'ogg'),(18,'gsp'),(19,'swf'),(19,'cab'),(20,'ins'),(20,'isf'),(21,'ins'),(21,'isf'),(21,'ogg'),(21,'re3'),(21,'mswmm'),(21,'msw'),(22,'wav'),(23,'mp3'),(23,'mp2'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'mid'),(26,'midi'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(29,'wmv');

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

/*Data for the table `allowedFileTypes` */

insert into `allowedFileTypes`(`fileTypeID`,`description`,`mime`,`iconFile`,`inline`,`enable`) values (1,'GIF Image','image/gif','image-gif.png','true','true'),(2,'HTML Document','text/html','text-html.png','true','true'),(3,'Plain Text','text/plain','textfile.png','true','true'),(4,'Rich Text','text/richtext','application-rtf.png','true','true'),(5,'CSS','text/css','textfile.png','true','true'),(6,'JPEG Image','image/jpeg','image-jpeg.png','true','true'),(7,'Progressing JPEG Image','image/pjpeg','image-jpeg.png','true','true'),(8,'PNG Image','image/png','image-png.png','true','true'),(9,'Bitmap Image','image/bmp','image-bmp.png','true','true'),(10,'PDF Document','application/pdf','application-pdf.png','true','true'),(11,'Word Document','application/msword','application-msword.png','true','true'),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','application-vnd.ms-powerpoint.png','true','true'),(13,'Excel Spreadsheet','application/vnd.ms-excel','application-vnd.ms-excel.png','true','true'),(14,'ZIP Archive','application/x-zip-compressed','compressed.png','false','true'),(15,'Postscript','application/postscript','application-postscript.png','true','true'),(16,'Photoshop Image','application/x-photoshop','image-psd.png','false','true'),(17,'OGG Audio','application/ogg','audio-x-wav.png','true','true');
insert into `allowedFileTypes`(`fileTypeID`,`description`,`mime`,`iconFile`,`inline`,`enable`) values (18,'Geometer\'s Sketchpad','application/x-gsp','application-x-none.png','false','true'),(19,'Shockwave Flash','application/x-shockwave-flash','application-x-none.png','true','true'),(20,'Inspiration','application/x-Inspiration','application-x-none.png','false','true'),(21,'Binary Data (Unknown)','application/octet-stream','application-x-none.png','false','true'),(22,'WAV Audio','audio/wav','audio-x-wav.png','false','true'),(23,'MPEG Audio','audio/mpeg','audio-mpeg.png','false','true'),(24,'Real Audio Stream','audio/x-pn-realaudio','application-vnd.rn-realmedia.png','false','true'),(25,'Real Audio','audio/x-realaudio','audio-x-pn-realaudio.png','false','true'),(26,'MIDI Audio','audio/mid','audio-x-wav.png','false','true'),(27,'MPEG Video','video/mpeg','video-mpeg.png','false','true'),(28,'Quicktime Video','video/quicktime','video-quicktime.png','false','true'),(29,'WMV Video','video/x-ms-wmv','video-x-msvideo.png','true','true');

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

/*Data for the table `bookmarks` */

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

/*Data for the table `category` */

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

/*Data for the table `cmsLog` */

/*Table structure for table `enquiryDestination` */

DROP TABLE IF EXISTS `enquiryDestination`;

CREATE TABLE `enquiryDestination` (
  `campusID` int(11) NOT NULL default '0',
  `enquiryTypeID` int(11) NOT NULL default '0',
  `email` varchar(80) default NULL,
  PRIMARY KEY  (`campusID`,`enquiryTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `enquiryDestination` */

/*Table structure for table `enquiryType` */

DROP TABLE IF EXISTS `enquiryType`;

CREATE TABLE `enquiryType` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(40) default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `enquiryType` */

insert into `enquiryType`(`id`,`name`) values (1,'Portal Feedback'),(2,'General Feedback'),(3,'Technical Support');

/*Table structure for table `error` */

DROP TABLE IF EXISTS `error`;

CREATE TABLE `error` (
  `id` int(3) unsigned NOT NULL default '0',
  `message` mediumtext,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `error` */

insert into `error`(`id`,`message`) values (1,'You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.'),(2,'Not enough information was submitted to the form.'),(3,'Please enter either a file or a url, not both.'),(4,'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the portal.<br><br>Please contact your System Administrator if problems persist.'),(5,'You do not have permission to download this file.'),(6,'You do not have write permission to this category.'),(7,'Mail cannot be sent at this time. Please try again later.'),(8,'You do not have permission to delete this resource.'),(9,'Processing failed for the following reason: %MESSAGE%'),(10,'The size of this file exceeds the allowed limit.');
insert into `error`(`id`,`message`) values (11,'%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Portal.<br>If you would like to be able to add files of this type to the Portal please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Portal.'),(12,'The category that you are trying to delete is not empty. Please delete all its contents, then try again.'),(13,'The category that you are trying to view does not exist. Click here to return to the <a href=\"/browse.php\">Top Level</a>'),(14,'You do not have permission to view this category.'),(15,'The date that you have submitted is invalid. Please check it, then try again.'),(16,'There was a problem validating your account: <br><b>%MESSAGE%</b>'),(17,'The submission box that you are trying to access does not exist or you do not have permission to view it.'),(18,'The subject selected does not exist.'),(19,'You do not have permission to view this page.'),(20,'You do not have permission to create a category in this location.');
insert into `error`(`id`,`message`) values (21,'A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%'),(22,'The database is unavailable at this time. Please try again later.'),(23,'You do not have permission to modify this news.'),(24,'You are not enrolled in this subject.'),(25,'You do not have permission to return this work to this student.'),(26,'The work you are trying to return either does not exist or has already been returned.'),(27,'The work you are trying to submit has already been submitted.'),(28,'You do not have permission to post in this category.'),(29,'You do not have permission to modify or delete this post.'),(30,'This user cannot be the group administrator.'),(31,'No user was found that matches your request.'),(32,'The absentee system cannot be accessed on the weekend.'),(33,'That period does not exist today.'),(34,'You do not have permission to modify this event.'),(35,'You do not have permission to add an event of this context.'),(36,''),(37,'You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.');
insert into `error`(`id`,`message`) values (38,'This is already in the database. Please modify, then try again.'),(39,'No teams exist. You must add teams before trying to add events.'),(40,'Attendances for Grimwade have not yet been implemented. Please wait until further notice.'),(41,'That file does not exist.'),(42,'No email account information was found. Please either use an external mail client to send email, or provide the portal with an email address.'),(43,'The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.'),(44,'You are trying to view or delete work that either does not exist or does not belong to you.'),(45,'Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".'),(46,'You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.');
insert into `error`(`id`,`message`) values (101,'The resource you are trying to access does not exist or you do not have permission to view it.'),(102,'A booking already exists during the time you have selected.  Please press back and select a time that is free.'),(103,'The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.');

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

/*Data for the table `event` */

/*Table structure for table `eventCampus` */

DROP TABLE IF EXISTS `eventCampus`;

CREATE TABLE `eventCampus` (
  `eventID` int(11) NOT NULL auto_increment,
  `campusID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`campusID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `eventCampus` */

/*Table structure for table `eventGroup` */

DROP TABLE IF EXISTS `eventGroup`;

CREATE TABLE `eventGroup` (
  `eventID` int(11) NOT NULL auto_increment,
  `groupID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`groupID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `eventGroup` */

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

/*Data for the table `eventMonthly` */

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

/*Data for the table `eventWeekly` */

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

/*Data for the table `file` */

/*Table structure for table `file_type` */

DROP TABLE IF EXISTS `file_type`;

CREATE TABLE `file_type` (
  `id` int(4) NOT NULL auto_increment,
  `name` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `file_type` */

insert into `file_type`(`id`,`name`) values (1,'Notes'),(2,'Assignment');

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

/*Data for the table `forum` */

/*Table structure for table `groups` */

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `name` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `groups` */

insert into `groups`(`id`,`name`) values (1,'Administration'),(2,'Tutors'),(3,'Students');

/*Table structure for table `homepage_colours` */

DROP TABLE IF EXISTS `homepage_colours`;

CREATE TABLE `homepage_colours` (
  `id` smallint(3) unsigned NOT NULL auto_increment,
  `name` char(15) NOT NULL default '0',
  `hex` char(6) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `homepage_colours` */

insert into `homepage_colours`(`id`,`name`,`hex`) values (1,'Dark Blue','003399'),(2,'Light Blue','0066CC'),(3,'Grey','CCCCCC'),(4,'Black','000000'),(5,'White','FFFFFF'),(7,'Scarlet','CC0000'),(9,'Old Gold','CC9900'),(11,'Red','FF0000'),(12,'Dark Blue','000099'),(13,'Green','008000'),(14,'Yellow','FFFF00');

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

/*Data for the table `homepage_comp` */

insert into `homepage_comp`(`id`,`size`,`method`,`name`,`filename`) values (1,0,'','Nothing',NULL),(2,1,'outputLinkList','Links','links_n.htm'),(3,1,'outputFileList','Files','files_n.htm'),(4,2,'outputMembers','Group Members','members_s.htm'),(5,2,'outputForumList','Small Forum','forum_s.htm'),(6,2,'outputLinkList','Small Links','links_s.htm'),(7,2,'outputFileList','Small Files','files_s.htm'),(8,1,'outputNews','News','news_n.htm'),(9,2,'initSubject','Class Management','class_manage.htm'),(10,2,'folderManage','Folder Management','folder_manage.htm'),(11,2,'outputAssignList','Small Assignment','assign_s.htm'),(12,2,'outputHomeworkList','Small Homework','homework_s.htm'),(13,2,'outputManagers','Group Managers','staff_s.htm'),(14,2,'outputImage','Site Icon','image_s.htm'),(15,1,'outputImage','Site Promo Image','image_n.htm'),(16,1,'outputAssignList','Assignments','assign_n.htm'),(17,1,'outputHomeworkList','Homework','homework_n.htm'),(18,2,'outputNoteList','Small Notes','notes_s.htm'),(19,1,'outputNoteList','Notes','notes_n.htm'),(20,2,'outputFolderList','Folders','folders_s.htm'),(21,1,'outputEventList','Calendar','cal_n.htm'),(22,2,'outputEventList','Small Calendar','cal_s.htm'),(23,1,'outputImageGallery','Image Gallery','gallery.htm');

/*Table structure for table `homepage_comp_location` */

DROP TABLE IF EXISTS `homepage_comp_location`;

CREATE TABLE `homepage_comp_location` (
  `cid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(1) unsigned NOT NULL default '0',
  `component` tinyint(3) unsigned NOT NULL default '0',
  `size` tinyint(3) unsigned NOT NULL default '7',
  PRIMARY KEY  (`cid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `homepage_comp_location` */

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

/*Data for the table `homepage_config` */

/*Table structure for table `homepage_log` */

DROP TABLE IF EXISTS `homepage_log`;

CREATE TABLE `homepage_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `cid` int(6) NOT NULL default '0',
  `hitcount` int(10) unsigned default '0',
  PRIMARY KEY  (`user`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `homepage_log` */

/*Table structure for table `homepages` */

DROP TABLE IF EXISTS `homepages`;

CREATE TABLE `homepages` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `filename` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `homepages` */

insert into `homepages`(`id`,`name`,`filename`) values (1,'Class','subject.htm'),(2,'Group','group.htm'),(3,'Group with Forum','groupforum.htm'),(4,'Faculty','faculty.htm'),(5,'Class with Forum','subforum.htm');

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `login_log` */

/*Table structure for table `luCampus` */

DROP TABLE IF EXISTS `luCampus`;

CREATE TABLE `luCampus` (
  `name` varchar(255) default NULL,
  `id` tinyint(4) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `luCampus` */

insert into `luCampus`(`name`,`id`) values ('Main',1);

/*Table structure for table `luResourceLocation` */

DROP TABLE IF EXISTS `luResourceLocation`;

CREATE TABLE `luResourceLocation` (
  `id` mediumint(3) unsigned NOT NULL auto_increment,
  `name` varchar(50) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `luResourceLocation` */

insert into `luResourceLocation`(`id`,`name`) values (1,'Senior Library'),(2,'Senior School');

/*Table structure for table `luResourceType` */

DROP TABLE IF EXISTS `luResourceType`;

CREATE TABLE `luResourceType` (
  `id` mediumint(3) unsigned NOT NULL auto_increment,
  `name` varchar(50) NOT NULL default '0',
  `type` enum('room','equipment') default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `luResourceType` */

insert into `luResourceType`(`id`,`name`,`type`) values (1,'Classroom','room'),(2,'AV Equipment','equipment'),(3,'IT Equipment','equipment'),(4,'Property Equipment','equipment'),(5,'Meeting Room','room'),(6,'Assembly Area','room');

/*Table structure for table `luServices` */

DROP TABLE IF EXISTS `luServices`;

CREATE TABLE `luServices` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `luServices` */

insert into `luServices`(`id`,`name`) values (1,'Homepage');

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

/*Data for the table `news_items` */

/*Table structure for table `newsCampus` */

DROP TABLE IF EXISTS `newsCampus`;

CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL default '0',
  `campus` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `newsCampus` */

/*Table structure for table `newsGroup` */

DROP TABLE IF EXISTS `newsGroup`;

CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL default '0',
  `gid` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `newsGroup` */

/*Table structure for table `perms` */

DROP TABLE IF EXISTS `perms`;

CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL default '0',
  `gid` int(4) NOT NULL default '0',
  `type` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `perms` */

/*Table structure for table `ph_ext` */

DROP TABLE IF EXISTS `ph_ext`;

CREATE TABLE `ph_ext` (
  `owner` int(11) NOT NULL default '0',
  `extension` mediumint(9) default NULL,
  PRIMARY KEY  (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `ph_ext` */

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

/*Data for the table `resource` */

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

/*Data for the table `resourceBooking` */

/*Table structure for table `roomResource` */

DROP TABLE IF EXISTS `roomResource`;

CREATE TABLE `roomResource` (
  `room` int(6) unsigned NOT NULL default '0',
  `resource` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY  (`room`,`resource`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `roomResource` */

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

/*Data for the table `sideMenu` */

insert into `sideMenu`(`id`,`parent`,`txt`,`url`,`target`,`sequence`) values (1,0,'Home','/home.php','',0),(2,0,'Student Services','#','',5),(3,0,'Staff Services','#','',1),(4,0,'Faculties','#','',2),(7,0,'Houses','#','',4),(8,0,'Sport','#','',3),(9,1,'Main','/home.php','',5),(10,1,'News','/news.php','',6),(12,1,'Webmail','/mail.php','',9),(13,1,'Calendar','/calendar/viewCalendar.php','',3),(14,1,'Browse','/browse.php','',2),(15,1,'Submissions','/submission/listSubmission.php','',8),(16,1,'Returned Work','/submission/returnedWork.php','',7),(93,9,'Edit My Links','/editLinks.php','',0),(94,9,'Manage My Groups','/group/joinGroup.php','',2),(95,9,'Logout','/logout.php','',1),(96,10,'View News','/news.php','',1),(97,10,'Add News','/news/modnews.php','',0),(98,12,'View Mail','/mail.php','',2),(99,12,'Send Mail','/mail/send.php','',1),(100,12,'Address Book','/search/userLookup.php','',0),(101,14,'Add Category','/cms/modFolder.php?action=add','',0),(102,14,'Add File','/cms/modResource.php?type=file','',1),(103,14,'Add Multiple Files','/cms/modResource.php?type=multfile','',3),(104,14,'Add Link','/cms/modResource.php?type=url','',2),(105,14,'Add Multiple Links','/addResource.php?type=multurl','',4),(106,14,'Delete Category','/cms/modFolder.php?action=del','',5),(207,0,'Logout','/logout.php','',6),(208,1,'Admin','/admin/admin.php','',0),(209,208,'Add User','/admin/modUser.php?adduser=1','',1);
insert into `sideMenu`(`id`,`parent`,`txt`,`url`,`target`,`sequence`) values (211,208,'Modify User','/admin/modUser.php','',2),(212,208,'Modify Side Menu','/admin/modSideMenu.php','',0),(213,1,'Due Work','/submission/dueWork.php','',4),(214,1,'Book Resource','/modules/resource/viewResourceList.php','',1);

/*Table structure for table `sideMenuCampus` */

DROP TABLE IF EXISTS `sideMenuCampus`;

CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL default '0',
  `campus` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `sideMenuCampus` */

/*Table structure for table `sideMenuGroup` */

DROP TABLE IF EXISTS `sideMenuGroup`;

CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL default '0',
  `gid` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `sideMenuGroup` */

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

/*Data for the table `student_submission` */

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

/*Data for the table `subject_news` */

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

/*Data for the table `submission_box` */

/*Table structure for table `submission_log` */

DROP TABLE IF EXISTS `submission_log`;

CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `user` int(6) NOT NULL default '0',
  `page` tinyint(4) NOT NULL default '0',
  `sid` int(6) NOT NULL default '0',
  PRIMARY KEY  (`user`,`page`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
  PRIMARY KEY  (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `submission_return` */

/*Table structure for table `superusers` */

DROP TABLE IF EXISTS `superusers`;

CREATE TABLE `superusers` (
  `id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `superusers` */

insert into `superusers`(`id`) values (1);

/*Table structure for table `uhomepage` */

DROP TABLE IF EXISTS `uhomepage`;

CREATE TABLE `uhomepage` (
  `owner` int(11) NOT NULL default '0',
  `education` text,
  `personal` text,
  `school` text,
  PRIMARY KEY  (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `uhomepage` */

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

/*Data for the table `user` */

insert into `user`(`id`,`username`,`password`,`group_id`,`firstname`,`lastname`,`email_pass`,`synergy_id`,`campus`,`year`,`house`,`local_email`,`alt_email`,`mail_handler`) values (1,'jleckie','*7968DF3FE2AF92916A9D4F5BE24010B7707AC68F',1,'James','Leckie','',4,1,NULL,NULL,0,'jleckie@lektec.com.au',0);

/*Table structure for table `userAccess` */

DROP TABLE IF EXISTS `userAccess`;

CREATE TABLE `userAccess` (
  `uid` int(11) NOT NULL default '0',
  `service` int(11) NOT NULL default '0',
  `sid` int(11) NOT NULL default '0',
  `type` smallint(6) default NULL,
  PRIMARY KEY  (`service`,`sid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `userAccess` */

/*Table structure for table `userCampus` */

DROP TABLE IF EXISTS `userCampus`;

CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL default '0',
  `campus` tinyint(3) unsigned NOT NULL default '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `userCampus` */

insert into `userCampus`(`uid`,`campus`,`defaultCampus`) values (1,1,0);

/*Table structure for table `userGroups` */

DROP TABLE IF EXISTS `userGroups`;

CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL default '0',
  `type` tinyint(2) default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `userGroups` */

/*Table structure for table `welcomeComponent` */

DROP TABLE IF EXISTS `welcomeComponent`;

CREATE TABLE `welcomeComponent` (
  `id` int(3) unsigned NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `filename` varchar(100) default '0',
  `function` varchar(100) default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

/*Data for the table `welcomeComponent` */

insert into `welcomeComponent`(`id`,`name`,`filename`,`function`) values (1,'News','news.htm','outputNews'),(2,'Weather','weather.htm','outputWeather'),(3,'Calendar','calendar.htm','outputCalendar'),(4,'Whats New','changedfolder.htm','outputWhatNew'),(5,'My Students','studentSubjects.htm','outputChildSubjects');

/*Table structure for table `welcomeLayout` */

DROP TABLE IF EXISTS `welcomeLayout`;

CREATE TABLE `welcomeLayout` (
  `gid` int(6) unsigned NOT NULL default '0',
  `position` tinyint(3) unsigned NOT NULL default '0',
  `componentID` int(10) unsigned default '0',
  PRIMARY KEY  (`gid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 ROW_FORMAT=FIXED;

/*Data for the table `welcomeLayout` */

insert into `welcomeLayout`(`gid`,`position`,`componentID`) values (1,1,1),(1,2,3),(1,3,NULL),(1,4,NULL),(1,6,4),(1,7,NULL),(1,8,NULL),(2,1,1),(2,2,3),(2,3,NULL),(2,4,NULL),(2,6,4),(2,7,NULL),(2,8,NULL),(3,1,1),(3,2,3),(3,3,NULL),(3,4,NULL),(3,6,4),(3,7,NULL),(3,8,NULL),(4,1,1),(4,2,3),(4,3,NULL),(4,4,NULL),(4,5,2),(4,6,4),(4,7,5);

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
