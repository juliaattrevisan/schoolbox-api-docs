/*
SQLyog Community Edition- MySQL GUI v5.2 Beta 1
Host - 4.1.16 : Database - portal
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

insert into `bookmarks`(`id`,`owner`,`url`,`description`) values (2,1,'http://www.yahoo.com','yahoo'),(3,1,'http://www.google.com.au','google'),(4,2,'http://www.google.com','Google'),(6,3,'http://www.wenona.nsw.edu.au','Wenona'),(7,21,'http://www.sitescene.com.au','Sitescene'),(9,21,'http://www.sitescene.com.au','Sitescene'),(12,21,'http://www.sitescene.com.au','Sitescene'),(17,1,'http://www.sitescene.com.au','Sitescene');

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

insert into `category`(`id`,`name`,`parent`,`template`,`teacherid`,`code`) values (1,'First Test Folder',0,0,0,NULL),(2,'Open for all to write',0,0,0,NULL),(3,'Test Homepage',1,1,0,NULL),(4,'Test Group',1,2,0,NULL),(5,'Test Class',2,1,0,'23&AC'),(7,'Test Image Gallery',1,2,0,NULL),(8,'test',7,0,0,NULL),(11,'Test Group 2',1,2,0,NULL),(12,'Test Class',1,1,0,'10ENG4'),(13,'Test Class',1,1,0,NULL),(15,'cat',11,1,0,NULL),(16,'cat',11,0,0,NULL),(17,'Test Group',5,0,0,NULL),(18,'Test Class 2',2,1,0,NULL),(19,'test',4,1,0,NULL),(29,'test2345',2,0,0,NULL),(30,'tesdtgs',2,0,0,NULL),(31,'test me no content',2,1,0,NULL),(32,'bulletin pages',0,0,0,NULL),(33,'From the Headmaster - (Main)',32,2,0,NULL),(34,'The Chaplain Writes - (Main)',32,2,0,NULL);

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

insert into `cmsLog`(`id`,`object`,`objectId`,`action`,`user`,`time`) values (1,'folder',32,'create',23,'2006-05-21 15:14:39'),(2,'folder',33,'create',23,'2006-05-21 15:15:13'),(3,'folder',34,'create',23,'2006-05-21 15:15:36'),(4,'folder',35,'create',23,'2006-05-21 15:16:00'),(5,'link',8,'',23,'2006-05-21 17:34:45'),(6,'link',9,'',23,'2006-05-21 17:35:04'),(7,'link',10,'',23,'2006-05-21 17:35:21'),(8,'folder',12,'modify',23,'2006-06-13 17:17:05'),(9,'folder',15,'modify',23,'2006-06-30 15:46:59'),(10,'folder',12,'modify',23,'2006-07-04 14:40:53'),(11,'file',47,'',1,'2006-10-17 22:33:05'),(12,'file',48,'',1,'2006-10-17 22:36:32'),(13,'file',1,'delete',1,'2006-10-20 15:57:11'),(14,'file',3,'delete',1,'2006-10-20 15:57:13'),(15,'file',6,'delete',1,'2006-10-20 15:57:15'),(16,'file',8,'delete',1,'2006-10-20 15:57:19'),(17,'file',10,'delete',1,'2006-10-20 15:57:22'),(18,'file',49,'',1,'2006-10-20 15:57:34'),(19,'file',50,'',1,'2006-10-20 15:59:31');

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

insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (1,1,1,0,4,'test','2004-01-04','00:00:00','00:00:00'),(2,1,1,0,4,'test personal','2004-01-13','00:00:00','00:00:00'),(3,1,2,4,4,'test group event','2004-01-13','10:30:00','00:00:00'),(4,1,1,0,4,'another personal test','2004-01-13','11:30:00','00:00:00'),(5,5,1,0,2,'meeting','2004-09-13','17:00:00','18:00:00'),(6,5,1,0,4,'day off','2004-09-12','00:00:00','00:00:00'),(7,5,2,18,4,'meeting','2004-09-21','00:00:00','00:00:00'),(8,1,1,0,4,'test 2','2005-04-04','00:00:00','00:00:00'),(9,1,3,NULL,4,'Comment','0000-00-00','00:00:00','00:00:00'),(10,1,3,NULL,4,'Anzac Day holiday','2005-04-25','00:00:00','00:00:00'),(11,1,3,NULL,4,'Boarders return','2005-04-26','18:00:00','20:00:00'),(12,1,3,NULL,4,'Term 2 commences House','2005-04-27','00:00:00','00:00:00'),(13,1,3,NULL,4,'HOH Meeting','2005-04-27','00:00:00','00:00:00'),(14,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-04-27','07:00:00','08:30:00'),(15,1,3,NULL,4,'House Assembly - Assembly Bells day for Senior School  & Junior School','2005-04-27','00:00:00','00:00:00'),(16,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-04-27','00:00:00','00:00:00'),(17,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-04-27','00:00:00','00:00:00'),(18,1,3,NULL,4,'Board of Governors AGM - Music Centre','2005-04-27','18:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (19,1,3,NULL,4,'House Assembly','2005-04-27','00:00:00','00:00:00'),(20,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-04-28','07:00:00','08:30:00'),(21,1,3,NULL,4,'HOH Meeting','2005-04-28','16:30:00','17:30:00'),(22,1,3,NULL,4,'Senior School Athletics Carnival @ Sydney Academy of Sport, Narrabeen','2005-04-28','00:00:00','00:00:00'),(23,1,3,NULL,4,'Performing Art rehearsal - p6 Hall','2005-04-28','00:00:00','00:00:00'),(24,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-04-28','15:30:00','16:30:00'),(25,1,3,NULL,4,'Junior School Photo Session - Day 1','2005-04-29','00:00:00','00:00:00'),(26,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-04-29','07:00:00','08:30:00'),(27,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-04-29','13:15:00','00:00:00'),(28,1,3,NULL,4,'Junior School Tigramunna Performance - Hall','2005-04-29','00:00:00','00:00:00'),(29,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-04-29','00:00:00','00:00:00'),(30,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-04-29','13:45:00','14:15:00'),(31,1,3,NULL,4,'Junior School Clubs - Hall','2005-04-29','14:15:00','15:15:00'),(32,1,3,NULL,4,'Drama production rehearsal - Hall','2005-04-29','15:30:00','17:30:00'),(33,1,3,NULL,4,'Anzac Assembly','2005-04-29','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (34,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-04-29','15:30:00','16:30:00'),(35,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-04-29','16:30:00','17:30:00'),(36,1,3,NULL,4,'ISDA Debating Round 6 @ Riverview','2005-04-29','00:00:00','00:00:00'),(37,1,3,NULL,4,'NSNA Netball - Round 3','2005-04-30','00:00:00','00:00:00'),(38,1,3,NULL,4,'Hockey Eastern Districts - Round 1','2005-04-30','00:00:00','00:00:00'),(39,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 1','2005-04-30','00:00:00','00:00:00'),(40,1,3,NULL,4,'WOGU 1960 Reunion Lunch at school','2005-04-30','00:00:00','00:00:00'),(41,1,3,NULL,4,'St Davids Day','2005-04-30','00:00:00','00:00:00'),(42,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-02','07:00:00','08:30:00'),(43,1,3,NULL,4,'Y12 Assessment Period 2-9/5','2005-05-02','00:00:00','00:00:00'),(44,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-02','00:00:00','00:00:00'),(45,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-02','00:00:00','00:00:00'),(46,1,3,NULL,4,'\"\"\"The Hill\"\" Debating\"','2005-05-02','00:00:00','00:00:00'),(47,1,3,NULL,4,'WOGU Meeting -Ralston 2','2005-05-02','19:30:00','00:00:00'),(48,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-03','07:00:00','08:30:00'),(49,1,3,NULL,4,'Y12 Assessment Period 9/5','2005-05-03','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (50,1,3,NULL,4,'Senior School Tutor Time','2005-05-03','00:00:00','00:00:00'),(51,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-03','00:00:00','00:00:00'),(52,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-03','00:00:00','00:00:00'),(53,1,3,NULL,4,'Subject Co-ords & Staff Meeting','2005-05-03','15:30:00','16:30:00'),(54,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-04','07:00:00','08:30:00'),(55,1,3,NULL,4,'Y12 Assessment Period 9/5','2005-05-04','00:00:00','00:00:00'),(56,1,3,NULL,4,'Subject Co-ords Meeting','2005-05-04','15:30:00','16:30:00'),(57,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-04','00:00:00','00:00:00'),(58,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-04','00:00:00','00:00:00'),(59,1,3,NULL,4,'Y11 Drama incursion Ps O & 1','2005-05-04','00:00:00','00:00:00'),(60,1,3,NULL,4,'Lote Departmental Day 1','2005-05-04','00:00:00','00:00:00'),(61,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-05','07:00:00','08:30:00'),(62,1,3,NULL,4,'Y12 Assessment Period 9/5','2005-05-05','00:00:00','00:00:00'),(63,1,3,NULL,4,'Y11 Half Yearly Exams 5-13/5','2005-05-05','00:00:00','00:00:00'),(64,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-05-05','00:00:00','00:00:00'),(65,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-05','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (66,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-05','00:00:00','00:00:00'),(67,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-05-05','15:30:00','16:30:00'),(68,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-06','07:00:00','08:30:00'),(69,1,3,NULL,4,'Y12 Assessment Period to 9/5','2005-05-06','00:00:00','00:00:00'),(70,1,3,NULL,4,'Y11 Half Yearly Exams 13/5','2005-05-06','00:00:00','00:00:00'),(71,1,3,NULL,4,'NSW All Schools\' Swim & Diving Champs','2005-05-06','00:00:00','00:00:00'),(72,1,3,NULL,4,'Infants\' Mother Day Lunch','2005-05-06','00:00:00','00:00:00'),(73,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-05-06','13:15:00','00:00:00'),(74,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-06','00:00:00','00:00:00'),(75,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-05-06','13:45:00','14:15:00'),(76,1,3,NULL,4,'Junior School Clubs - Hall','2005-05-06','14:15:00','15:15:00'),(77,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-05-06','00:00:00','00:00:00'),(78,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-05-06','15:30:00','16:30:00'),(79,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-05-06','16:30:00','17:30:00'),(80,1,3,NULL,4,'Drama production rehearsal - Hall','2005-05-06','15:30:00','17:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (81,1,3,NULL,4,'IGSSA Friday Night Athletics 1 @ Homebush','2005-05-06','00:00:00','00:00:00'),(82,1,3,NULL,4,'ISDA Debating Round 7 Wenona (hosts) v St Augustine\'s','2005-05-06','00:00:00','00:00:00'),(83,1,3,NULL,4,'Hall Bump in for WPA - Trivia Night (Hall Unavailable after Drama Rehearsal)','2005-05-06','00:00:00','00:00:00'),(84,1,3,NULL,4,'NSNA Netball - Round 4','2005-05-07','00:00:00','00:00:00'),(85,1,3,NULL,4,'Hockey Eastern Districts - Round 2','2005-05-07','00:00:00','00:00:00'),(86,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 2','2005-05-07','00:00:00','00:00:00'),(87,1,3,NULL,4,'ACER Scholarship Examinations','2005-05-07','09:00:00','12:00:00'),(88,1,3,NULL,4,'GPLAs 1/2 & 4/5','2005-05-07','00:00:00','00:00:00'),(89,1,3,NULL,4,'WPA Trivia Night (Hall unavailable all Day)','2005-05-07','00:00:00','00:00:00'),(90,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-09','07:00:00','08:30:00'),(91,1,3,NULL,4,'Y12 Assessment period finishes today','2005-05-09','00:00:00','00:00:00'),(92,1,3,NULL,4,'Y11 Half yearly exams to 13/5','2005-05-09','00:00:00','00:00:00'),(93,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-09','00:00:00','00:00:00'),(94,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-09','00:00:00','00:00:00'),(95,1,3,NULL,4,'\"\"\"The Hill\"\" Debating\"','2005-05-09','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (96,1,3,NULL,4,'Drama Departmental Day 1','2005-05-09','00:00:00','00:00:00'),(97,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-10','07:00:00','08:30:00'),(98,1,3,NULL,4,'Y11 Half yearly exams to 13/5','2005-05-10','00:00:00','00:00:00'),(99,1,3,NULL,4,'Y7 Snap Testing - Little Gym','2005-05-10','00:00:00','00:00:00'),(100,1,3,NULL,4,'Senior School Tutor Time','2005-05-10','00:00:00','00:00:00'),(101,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-10','00:00:00','00:00:00'),(102,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-10','00:00:00','00:00:00'),(103,1,3,NULL,4,'HOH & Tutors Meeting','2005-05-10','15:30:00','16:30:00'),(104,1,3,NULL,4,'HOH Meeting','2005-05-11','00:00:00','00:00:00'),(105,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-11','07:00:00','08:30:00'),(106,1,3,NULL,4,'Y11 Half yearly exams to 13/5','2005-05-11','00:00:00','00:00:00'),(107,1,3,NULL,4,'Tour morning','2005-05-11','09:30:00','11:00:00'),(108,1,3,NULL,4,'GPLA 4/5','2005-05-11','09:30:00','10:00:00'),(109,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-11','00:00:00','00:00:00'),(110,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-11','00:00:00','00:00:00'),(111,1,3,NULL,4,'Education Meeting - Front Room Messiter','2005-05-11','16:45:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (112,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-12','07:00:00','08:30:00'),(113,1,3,NULL,4,'Y11 Half yearly exams to 13/5','2005-05-12','00:00:00','00:00:00'),(114,1,3,NULL,4,'WPA Golf Day','2005-05-12','00:00:00','00:00:00'),(115,1,3,NULL,4,'HOH Meeting','2005-05-12','16:30:00','17:50:00'),(116,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-05-12','00:00:00','00:00:00'),(117,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-12','00:00:00','00:00:00'),(118,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-12','00:00:00','00:00:00'),(119,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-05-12','15:30:00','16:30:00'),(120,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-13','07:00:00','08:30:00'),(121,1,3,NULL,4,'Y11 Half yearly exams finish today','2005-05-13','00:00:00','00:00:00'),(122,1,3,NULL,4,'NSWCIS Hockey Trials','2005-05-13','00:00:00','00:00:00'),(123,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-05-13','00:00:00','00:00:00'),(124,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-13','13:15:00','00:00:00'),(125,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-05-13','13:45:00','14:15:00'),(126,1,3,NULL,4,'Junior School Clubs - Hall','2005-05-13','14:15:00','15:15:00'),(127,1,3,NULL,4,'Drama production rehearsal - Hall','2005-05-13','15:30:00','17:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (128,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-05-13','15:30:00','16:30:00'),(129,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-05-13','16:30:00','17:30:00'),(130,1,3,NULL,4,'IGSSA Friday Night Athletics 2 @ Homebush','2005-05-13','00:00:00','00:00:00'),(131,1,3,NULL,4,'NSNA Netball - Round 5','2005-05-14','00:00:00','00:00:00'),(132,1,3,NULL,4,'Hockey Eastern Districts - Round 3','2005-05-14','00:00:00','00:00:00'),(133,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 3','2005-05-14','00:00:00','00:00:00'),(134,1,3,NULL,4,'IGSSA X-country @ Frensham','2005-05-14','00:00:00','00:00:00'),(135,1,3,NULL,4,'Geography excursion to Colleambly 15-17/5','2005-05-15','00:00:00','00:00:00'),(136,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-16','07:00:00','08:30:00'),(137,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-16','00:00:00','00:00:00'),(138,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-16','00:00:00','00:00:00'),(139,1,3,NULL,4,'Combined Flute and Vocal Concert - Hall','2005-05-16','17:50:00','00:00:00'),(140,1,3,NULL,4,'\"\"\"The Hill\"\" Debating\"','2005-05-16','00:00:00','00:00:00'),(141,1,3,NULL,4,'Geography excursion to Colleambly 15-17/5','2005-05-16','00:00:00','00:00:00'),(142,1,3,NULL,4,'Yr 12 Reports to Co-ords','2005-05-16','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (143,1,3,NULL,4,'Pastoral Care in Period 1 - No lesson','2005-05-17','00:00:00','00:00:00'),(144,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-17','07:00:00','08:30:00'),(145,1,3,NULL,4,'Senior School Tutor Time','2005-05-17','00:00:00','00:00:00'),(146,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-17','00:00:00','00:00:00'),(147,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-17','00:00:00','00:00:00'),(148,1,3,NULL,4,'Senior School Academic Staff Meeting','2005-05-17','15:30:00','17:00:00'),(149,1,3,NULL,4,'Lower North Shore Tertiary Information Evening @ Wenona -  Big & Little Gyms','2005-05-17','17:00:00','21:00:00'),(150,1,3,NULL,4,'Geography excursion to Colleambly 15-17/5','2005-05-17','00:00:00','00:00:00'),(151,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-18','07:00:00','08:30:00'),(152,1,3,NULL,4,'Finance Committee Meeting - Principal\'s office','2005-05-18','07:45:00','00:00:00'),(153,1,3,NULL,4,'Y 10 Careers Testing (compulsory) Little Gym','2005-05-18','00:00:00','00:00:00'),(154,1,3,NULL,4,'Subject Co-ords meeting','2005-05-18','16:30:00','17:30:00'),(155,1,3,NULL,4,'JS Athletics Carnival@ Narrabeen','2005-05-18','00:00:00','00:00:00'),(156,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-18','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (157,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-18','00:00:00','00:00:00'),(158,1,3,NULL,4,'WPA Comm Meeting - GPLA 4/5','2005-05-18','19:30:00','00:00:00'),(159,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-19','07:00:00','08:30:00'),(160,1,3,NULL,4,'YS 9 & 10 half yearly exams 19-25/5','2005-05-19','00:00:00','00:00:00'),(161,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-05-19','00:00:00','00:00:00'),(162,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-19','00:00:00','00:00:00'),(163,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-19','00:00:00','00:00:00'),(164,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-05-19','15:30:00','16:30:00'),(165,1,3,NULL,4,'Foundation AGM & Board Meeting - Ralston 2','2005-05-19','18:00:00','00:00:00'),(166,1,3,NULL,4,'Yr 12 Reports to Tutors','2005-05-19','00:00:00','00:00:00'),(167,1,3,NULL,4,'Yr 12 Aca Pts to Year Co-ord from Subject Co-ords','2005-05-19','00:00:00','00:00:00'),(168,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-20','07:00:00','08:30:00'),(169,1,3,NULL,4,'YS 9 & 10 Half Yearly Exams 25/5','2005-05-20','00:00:00','00:00:00'),(170,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-05-20','13:15:00','00:00:00'),(171,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-20','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (172,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-05-20','13:45:00','14:15:00'),(173,1,3,NULL,4,'Junior School Clubs - Hall','2005-05-20','14:15:00','15:15:00'),(174,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-05-20','15:30:00','16:30:00'),(175,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-05-20','16:30:00','17:30:00'),(176,1,3,NULL,4,'Drama production rehearsal - Hall','2005-05-20','15:30:00','17:30:00'),(177,1,3,NULL,4,'IGSSA Friday Night Athletics 3 @ Homebush','2005-05-20','00:00:00','00:00:00'),(178,1,3,NULL,4,'ISDA Semi Final 1','2005-05-20','00:00:00','00:00:00'),(179,1,3,NULL,4,'NSNA Netball - Round 6','2005-05-21','00:00:00','00:00:00'),(180,1,3,NULL,4,'Hockey Eastern Districts - Round 4','2005-05-21','00:00:00','00:00:00'),(181,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 4','2005-05-21','00:00:00','00:00:00'),(182,1,3,NULL,4,'Tentative Geography Excursion to Colleambly 22-24/5','2005-05-21','00:00:00','00:00:00'),(183,1,3,NULL,4,'Drama production rehearsal - Hall','2005-05-22','13:00:00','17:00:00'),(184,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-23','07:00:00','08:30:00'),(185,1,3,NULL,4,'Yrs 7 & 8 Half Yearly Exams 23-25/5','2005-05-23','00:00:00','00:00:00'),(186,1,3,NULL,4,'AHISA Meeting (MH off campus all day)','2005-05-23','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (187,1,3,NULL,4,'Yr 9 & 10 Half Yearly Exams to 25/5','2005-05-23','00:00:00','00:00:00'),(188,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-23','00:00:00','00:00:00'),(189,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-23','00:00:00','00:00:00'),(190,1,3,NULL,4,'\"\"\"The Hill\"\" Debating\"','2005-05-23','00:00:00','00:00:00'),(191,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-24','07:00:00','08:30:00'),(192,1,3,NULL,4,'Yrs 7 - 10 Half Yearly Exams to 25/5','2005-05-24','00:00:00','00:00:00'),(193,1,3,NULL,4,'Senior School Tutor Time','2005-05-24','00:00:00','00:00:00'),(194,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-24','00:00:00','00:00:00'),(195,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-24','00:00:00','00:00:00'),(196,1,3,NULL,4,'Subject Co-ords & Staff meeting','2005-05-24','15:30:00','16:30:00'),(197,1,3,NULL,4,'OH & S Meeting - Conference Room (Date to move)','2005-05-24','15:30:00','00:00:00'),(198,1,3,NULL,4,'Y11 Essay Skills Course in Esc','2005-05-24','00:00:00','00:00:00'),(199,1,3,NULL,4,'Y12 Breakfast (HSY/MJG)','2005-05-24','00:00:00','00:00:00'),(200,1,3,NULL,4,'Y12 Reports to HOH','2005-05-24','00:00:00','00:00:00'),(201,1,3,NULL,4,'Y12 Report Writing in Tutor Time','2005-05-24','00:00:00','00:00:00'),(202,1,3,NULL,4,'HOH Meeting','2005-05-25','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (203,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-25','07:00:00','08:30:00'),(204,1,3,NULL,4,'Ys 7 - 10 Half Yearly Exams Finish Today','2005-05-25','00:00:00','00:00:00'),(205,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-25','00:00:00','00:00:00'),(206,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-25','00:00:00','00:00:00'),(207,1,3,NULL,4,'PDHPE Departmental Day 1','2005-05-25','00:00:00','00:00:00'),(208,1,3,NULL,4,'Yr 11 Reports to Subject Co-ordinators','2005-05-25','00:00:00','00:00:00'),(209,1,3,NULL,4,'Yr 11 Aca Reports to Year Co-ordinators','2005-05-25','00:00:00','00:00:00'),(210,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-26','07:00:00','08:30:00'),(211,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-05-26','00:00:00','00:00:00'),(212,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-26','00:00:00','00:00:00'),(213,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-26','00:00:00','00:00:00'),(214,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-05-26','15:30:00','16:30:00'),(215,1,3,NULL,4,'Board of Governors AGM - Music Centre','2005-05-26','18:00:00','00:00:00'),(216,1,3,NULL,4,'HOH Meeting','2005-05-26','16:30:00','17:30:00'),(217,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-27','07:00:00','08:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (218,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-05-27','13:15:00','00:00:00'),(219,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-27','00:00:00','00:00:00'),(220,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-05-27','13:45:00','14:15:00'),(221,1,3,NULL,4,'Junior School Clubs - Hall','2005-05-27','14:15:00','15:15:00'),(222,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-05-27','15:30:00','16:30:00'),(223,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-05-27','16:30:00','17:30:00'),(224,1,3,NULL,4,'Drama production rehearsal - Hall','2005-05-27','15:30:00','17:30:00'),(225,1,3,NULL,4,'IGSSA Friday Night Athletics 4 @ Homebush','2005-05-27','00:00:00','00:00:00'),(226,1,3,NULL,4,'ISDA Semi Final 2','2005-05-27','00:00:00','00:00:00'),(227,1,3,NULL,4,'Yr 12 Reports to MH','2005-05-27','00:00:00','00:00:00'),(228,1,3,NULL,4,'NSNA Netball - Round 7','2005-05-28','00:00:00','00:00:00'),(229,1,3,NULL,4,'Hockey Eastern Districts - Round 5','2005-05-28','00:00:00','00:00:00'),(230,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 5','2005-05-28','00:00:00','00:00:00'),(231,1,3,NULL,4,'Drama Production Rehearsal - Hall','2005-05-29','10:30:00','17:00:00'),(232,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-30','07:00:00','08:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (233,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-30','00:00:00','00:00:00'),(234,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-30','00:00:00','00:00:00'),(235,1,3,NULL,4,'\"\"\"The Hill\"\" Debating\"','2005-05-30','00:00:00','00:00:00'),(236,1,3,NULL,4,'Yr 11 Reports to Tutors','2005-05-30','00:00:00','00:00:00'),(237,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-05-31','07:00:00','08:30:00'),(238,1,3,NULL,4,'Yr 12 Drama Excursion (Evening)','2005-05-31','00:00:00','00:00:00'),(239,1,3,NULL,4,'Senior School Tutor Time','2005-05-31','00:00:00','00:00:00'),(240,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-05-31','00:00:00','00:00:00'),(241,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-05-31','00:00:00','00:00:00'),(242,1,3,NULL,4,'HOH & Tutors Meeting','2005-05-31','15:30:00','16:30:00'),(243,1,3,NULL,4,'Yr 11 Essay Skills Course in Esc','2005-05-31','00:00:00','00:00:00'),(244,1,3,NULL,4,'Yr 11 Report Writing in Tutor Time','2005-05-31','00:00:00','00:00:00'),(245,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-01','07:00:00','08:30:00'),(246,1,3,NULL,4,'Junior School Specialist\'s (incl Peripatetics) Reports to KA','2005-06-01','00:00:00','00:00:00'),(247,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-01','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (248,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-01','00:00:00','00:00:00'),(249,1,3,NULL,4,'Subject Co-ords Meeting','2005-06-01','16:30:00','17:30:00'),(250,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-02','07:00:00','08:30:00'),(251,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-06-02','00:00:00','00:00:00'),(252,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-02','00:00:00','00:00:00'),(253,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-02','00:00:00','00:00:00'),(254,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-06-02','15:30:00','16:30:00'),(255,1,3,NULL,4,'Yr 11 Reports to HOH','2005-06-02','00:00:00','00:00:00'),(256,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-03','07:00:00','08:30:00'),(257,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-06-03','13:15:00','00:00:00'),(258,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-03','00:00:00','00:00:00'),(259,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-03','00:00:00','00:00:00'),(260,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-06-03','13:45:00','14:15:00'),(261,1,3,NULL,4,'Junior School Clubs - Hall','2005-06-03','14:15:00','15:15:00'),(262,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-06-03','15:30:00','16:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (263,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-06-03','16:30:00','17:30:00'),(264,1,3,NULL,4,'Drama production rehearsal - Hall','2005-06-03','15:30:00','17:30:00'),(265,1,3,NULL,4,'IGSSA Friday Night Athletics 5 @ Homebush','2005-06-03','00:00:00','00:00:00'),(266,1,3,NULL,4,'ISDA Semi-Final 3','2005-06-03','00:00:00','00:00:00'),(267,1,3,NULL,4,'Yr 12 Geography Excursion - Kurnell','2005-06-03','07:45:00','15:15:00'),(268,1,3,NULL,4,'Science Departmental Day 1','2005-06-03','00:00:00','00:00:00'),(269,1,3,NULL,4,'Yr 7 - 10 Reports to Subject Co-ordinators','2005-06-03','00:00:00','00:00:00'),(270,1,3,NULL,4,'NSNA Netball - Round 8','2005-06-04','00:00:00','00:00:00'),(271,1,3,NULL,4,'Hockey Eastern Districts - Round 6','2005-06-04','00:00:00','00:00:00'),(272,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 6','2005-06-04','00:00:00','00:00:00'),(273,1,3,NULL,4,'Drama production rehearsal - Hall','2005-06-05','13:00:00','17:00:00'),(274,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-06','07:00:00','08:30:00'),(275,1,3,NULL,4,'Junior School Class Teacher reports to DB for editing & printing','2005-06-06','00:00:00','00:00:00'),(276,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-06','00:00:00','00:00:00'),(277,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-06','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (278,1,3,NULL,4,'\"\"\"The Hill\"\" Debating\"','2005-06-06','00:00:00','00:00:00'),(279,1,3,NULL,4,'K-6 Primary Conference (AIS) all GPLAs - Small Gym','2005-06-06','00:00:00','00:00:00'),(280,1,3,NULL,4,'Year 8 concert practice in music class time','2005-06-06','00:00:00','00:00:00'),(281,1,3,NULL,4,'Yr 11 Reports to MH','2005-06-06','00:00:00','00:00:00'),(282,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-07','07:00:00','08:30:00'),(283,1,3,NULL,4,'Year 8 concert practice in music class time','2005-06-07','00:00:00','00:00:00'),(284,1,3,NULL,4,'Senior School Tutor Time','2005-06-07','00:00:00','00:00:00'),(285,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-07','00:00:00','00:00:00'),(286,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-07','00:00:00','00:00:00'),(287,1,3,NULL,4,'Senior School Academic Staff Meeting','2005-06-07','15:30:00','17:00:00'),(288,1,3,NULL,4,'Compliance Meeting - Principal\'s office','2005-06-07','17:30:00','00:00:00'),(289,1,3,NULL,4,'School Leader Voting','2005-06-07','00:00:00','00:00:00'),(290,1,3,NULL,4,'K-6 Primary Conference (AIS) all GPLAs - Small Gym','2005-06-07','00:00:00','00:00:00'),(291,1,3,NULL,4,'Y11 Essay Skills Course in Esc','2005-06-07','00:00:00','00:00:00'),(292,1,3,NULL,4,'Yr 7-10 Reports to Tutors','2005-06-07','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (293,1,3,NULL,4,'HOH Meeting','2005-06-08','00:00:00','00:00:00'),(294,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-08','07:00:00','08:30:00'),(295,1,3,NULL,4,'Junior School Ensemble rehearsal - Hall','2005-06-08','10:00:00','15:00:00'),(296,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-08','00:00:00','00:00:00'),(297,1,3,NULL,4,'Yr 10 Science Show P5, GPLAs 1 & 2','2005-06-08','00:00:00','00:00:00'),(298,1,3,NULL,4,'Year 8 Concert - Hall','2005-06-08','18:00:00','00:00:00'),(299,1,3,NULL,4,'HOH Meeting','2005-06-09','16:30:00','17:30:00'),(300,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-09','07:00:00','08:30:00'),(301,1,3,NULL,4,'Senior School Tutor Time','2005-06-09','00:00:00','00:00:00'),(302,1,3,NULL,4,'Junior School Ensemble rehearsal - Hall','2005-06-09','10:00:00','15:00:00'),(303,1,3,NULL,4,'Junior School Ensemble Concert - Hall','2005-06-09','17:30:00','00:00:00'),(304,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-06-09','15:30:00','16:30:00'),(305,1,3,NULL,4,'Yr 7 - 10 Report Writing in Tutor Time','2005-06-09','00:00:00','00:00:00'),(306,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-10','07:00:00','08:30:00'),(307,1,3,NULL,4,'NSWCIS Cross Country @ Eastern Creek','2005-06-10','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (308,1,3,NULL,4,'ACER Scholarship Interviews with Principal','2005-06-10','00:00:00','00:00:00'),(309,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-06-10','13:15:00','00:00:00'),(310,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-10','00:00:00','00:00:00'),(311,1,3,NULL,4,'Junior School Clubs - Junior Gym/Music Centre','2005-06-10','13:45:00','14:15:00'),(312,1,3,NULL,4,'Junior School Clubs - Hall','2005-06-10','14:15:00','15:15:00'),(313,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-06-10','15:30:00','16:30:00'),(314,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-06-10','16:30:00','17:30:00'),(315,1,3,NULL,4,'Drama Production','2005-06-10','12:00:00','19:30:00'),(316,1,3,NULL,4,'Yr 10 Prelim Subject Choice Meeting - P5 Hall','2005-06-10','00:00:00','00:00:00'),(317,1,3,NULL,4,'No NSNA Games','2005-06-11','00:00:00','00:00:00'),(318,1,3,NULL,4,'EXEAT Weekend','2005-06-11','00:00:00','00:00:00'),(319,1,3,NULL,4,'Drama production rehearsal - Hall','2005-06-12','13:00:00','17:00:00'),(320,1,3,NULL,4,'Queen\'s Birthday','2005-06-13','00:00:00','00:00:00'),(321,1,3,NULL,4,'Drama Production Rehearsal - Hall or IT','2005-06-13','14:00:00','18:00:00'),(322,1,3,NULL,4,'Pastoral Care in Period 4 - No lesson','2005-06-14','00:00:00','00:00:00'),(323,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-14','07:00:00','08:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (324,1,3,NULL,4,'Hall not available today if Drama Production in Hall','2005-06-14','00:00:00','00:00:00'),(325,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime?','2005-06-14','00:00:00','00:00:00'),(326,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall?','2005-06-14','00:00:00','00:00:00'),(327,1,3,NULL,4,'Drama Production Rehearsal - Hall or IT','2005-06-14','15:30:00','21:00:00'),(328,1,3,NULL,4,'Senior School Tutor Time','2005-06-14','00:00:00','00:00:00'),(329,1,3,NULL,4,'Subject Co-ords & Staff Meeting','2005-06-14','15:30:00','16:30:00'),(330,1,3,NULL,4,'Yr 7 - 10 Reports to HOH','2005-06-14','00:00:00','00:00:00'),(331,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-15','07:00:00','08:30:00'),(332,1,3,NULL,4,'Hall not available today if Drama Production in Hall','2005-06-15','00:00:00','00:00:00'),(333,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime?','2005-06-15','00:00:00','00:00:00'),(334,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall?','2005-06-15','00:00:00','00:00:00'),(335,1,3,NULL,4,'Subject Co-ords & Staff Meeting','2005-06-15','16:30:00','17:30:00'),(336,1,3,NULL,4,'Drama Production Rehearsal - Hall or IT','2005-06-15','15:30:00','20:00:00'),(337,1,3,NULL,4,'Finance Committee Meeting - Principal\'s office','2005-06-15','07:45:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (338,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-16','07:00:00','08:30:00'),(339,1,3,NULL,4,'Year 10 Careers Interviews (16-17/6)','2005-06-16','00:00:00','00:00:00'),(340,1,3,NULL,4,'JSHAA Gymnastics@Homebush','2005-06-16','00:00:00','00:00:00'),(341,1,3,NULL,4,'Tour Information Morning','2005-06-16','09:30:00','11:30:00'),(342,1,3,NULL,4,'GPLA 4/5','2005-06-16','09:30:00','10:00:00'),(343,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-06-16','00:00:00','00:00:00'),(344,1,3,NULL,4,'Hall not available today if Drama Production in Hall','2005-06-16','00:00:00','00:00:00'),(345,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime?','2005-06-16','00:00:00','00:00:00'),(346,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall?','2005-06-16','00:00:00','00:00:00'),(347,1,3,NULL,4,'Senior School Drama Production - Hall or Independent Theatre','2005-06-16','19:00:00','00:00:00'),(348,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-06-16','15:30:00','16:30:00'),(349,1,3,NULL,4,'VA Departmental Day 1','2005-06-16','00:00:00','00:00:00'),(350,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-17','07:00:00','08:30:00'),(351,1,3,NULL,4,'Year 10 Careers Interviews (16-17/6)','2005-06-17','00:00:00','00:00:00'),(352,1,3,NULL,4,'All Junior School reports corrected & completed','2005-06-17','00:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (353,1,3,NULL,4,'IGSSA Athletics Championships @ Homebush Y7 spectators','2005-06-17','00:00:00','00:00:00'),(354,1,3,NULL,4,'Junior School Assembly - Junior Gym','2005-06-17','13:15:00','00:00:00'),(355,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-17','00:00:00','00:00:00'),(356,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-17','00:00:00','00:00:00'),(357,1,3,NULL,4,'Junior School Clubs: Jun Gym/Music Centre','2005-06-17','13:45:00','14:15:00'),(358,1,3,NULL,4,'Junior School Clubs: Hall','2005-06-17','14:15:00','15:15:00'),(359,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-06-17','15:30:00','16:30:00'),(360,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-06-17','16:30:00','17:30:00'),(361,1,3,NULL,4,'ISDA Grand Final','2005-06-17','00:00:00','00:00:00'),(362,1,3,NULL,4,'Senior School Drama Production - Hall or Independent Theatre','2005-06-17','19:00:00','00:00:00'),(363,1,3,NULL,4,'Yr 7-10 Reports to MH','2005-06-17','00:00:00','00:00:00'),(364,1,3,NULL,4,'NSNA Netball -  Round 9','2005-06-18','00:00:00','00:00:00'),(365,1,3,NULL,4,'Hockey Eastern Districts - Round 7','2005-06-18','00:00:00','00:00:00'),(366,1,3,NULL,4,'JSHAA Hockey & Minkey - Round 7','2005-06-18','00:00:00','00:00:00'),(367,1,3,NULL,4,'Leadership Weekend @ Eleanora Heights','2005-06-18','13:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (368,1,3,NULL,4,'Leadership Weekend @ Eleanora Heights','2005-06-19','13:30:00','00:00:00'),(369,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-20','07:00:00','08:30:00'),(370,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-20','00:00:00','00:00:00'),(371,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-20','00:00:00','00:00:00'),(372,1,3,NULL,4,'Yr 10 Prelim Subject Choice forms: Tutors to MHA','2005-06-20','00:00:00','00:00:00'),(373,1,3,NULL,4,'Senior School Ensemble Concert: Independent Theatre','2005-06-20','19:00:00','00:00:00'),(374,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-21','07:00:00','08:30:00'),(375,1,3,NULL,4,'Senior School Tutor Time','2005-06-21','00:00:00','00:00:00'),(376,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-21','00:00:00','00:00:00'),(377,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-21','00:00:00','00:00:00'),(378,1,3,NULL,4,'HOH & Tutors Meeting','2005-06-21','15:30:00','16:30:00'),(379,1,3,NULL,4,'Board of Governors AGM - Music Centre','2005-06-21','18:00:00','00:00:00'),(380,1,3,NULL,4,'School Leaders\' Induction rehearsal @ St.Thomas','2005-06-21','14:00:00','15:30:00'),(381,1,3,NULL,4,'HOH Meeting','2005-06-22','00:00:00','00:00:00'),(382,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-22','07:00:00','08:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (383,1,3,NULL,4,'School Leaders\' Induction @ St.Thomas\' (Morning Tea in GPLA 4/5)','2005-06-22','09:00:00','10:30:00'),(384,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-22','00:00:00','00:00:00'),(385,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-22','00:00:00','00:00:00'),(386,1,3,NULL,4,'Junior School reports to Principal','2005-06-22','00:00:00','00:00:00'),(387,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-23','07:00:00','08:30:00'),(388,1,3,NULL,4,'Senior School Assembly - Big Gym','2005-06-23','00:00:00','00:00:00'),(389,1,3,NULL,4,'Performing Arts rehearsal - Hall Lunchtime','2005-06-23','00:00:00','00:00:00'),(390,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-23','00:00:00','00:00:00'),(391,1,3,NULL,4,'Junior Jazz Group - Little Gym','2005-06-23','15:30:00','16:30:00'),(392,1,3,NULL,4,'Performing Arts rehearsal - Hall','2005-06-24','07:00:00','08:30:00'),(393,1,3,NULL,4,'Foundation Day (Big Gym) all Day','2005-06-24','00:00:00','00:00:00'),(394,1,3,NULL,4,'Junior School Assembly - Junior Gym?','2005-06-24','13:15:00','00:00:00'),(395,1,3,NULL,4,'Junior School Clubs: Jun Gym/Music Centre','2005-06-24','13:45:00','14:15:00'),(396,1,3,NULL,4,'Junior School Clubs: Hall','2005-06-24','14:15:00','15:15:00'),(397,1,3,NULL,4,'Term 2 finishes','2005-06-24','13:00:00','00:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (398,1,3,NULL,4,'Performing Arts rehearsal - p6 Hall','2005-06-24','00:00:00','00:00:00'),(399,1,3,NULL,4,'Intermediate Jazz Eisteddfod - Little Gym','2005-06-24','15:30:00','16:30:00'),(400,1,3,NULL,4,'Senior Jazz Eisteddfod - Little Gym','2005-06-24','16:30:00','17:30:00'),(401,1,3,NULL,4,'NSNA Netball - Round 10','2005-06-25','00:00:00','00:00:00'),(402,1,3,NULL,4,'Silver D of E Practice & Test Expedition - Hawkesbury River 26/6-2/7','2005-06-26','00:00:00','00:00:00'),(403,1,1,0,2,'a meeting','2005-04-11','15:00:00','16:00:00'),(404,1,1,0,1,'another meeting','2005-04-11','13:00:00','14:00:00'),(405,1,1,0,4,'test','2005-06-05','00:00:00','00:00:00'),(406,1,1,0,3,'new event','2005-06-08','00:00:00','00:00:00'),(407,1,1,0,4,'more events','2005-06-06','00:00:00','00:00:00'),(408,1,1,0,1,'my new event','2005-06-06','02:00:00','03:00:00'),(409,1,1,0,4,'blah','2005-06-07','00:00:00','00:00:00'),(410,21,1,0,4,'ghnghjghj fj gj','2005-06-05','11:00:00','12:00:00'),(411,21,1,0,1,'hjmjhkhgjkhjk','2005-07-14','11:11:00','00:00:00'),(412,21,1,0,4,'hjkljkljkl','2005-06-06','00:00:00','00:00:00'),(413,21,1,0,1,'This sais sd   sdkfj sdf dsf sdklf df sa fsdf dsf','2005-06-07','03:00:00','07:00:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (414,21,1,0,2,'Just another to be edds fsd fsakd fsdf sdf sd   sdafdsj f fh sdk kjsdfhsdhfkjs adj  sadjfhjksdahfk jk fhsjfhaskjf h dsja hfkjsd fhsjd hsdkfjhdsf sdf','2005-06-08','05:00:00','12:00:00'),(415,1,1,0,4,'my new event','2005-06-23','00:00:00','00:00:00'),(416,1,1,0,4,'my new event','2005-06-21','00:00:00','00:00:00'),(417,1,1,0,4,'my new event','2005-06-24','00:00:00','00:00:00'),(418,1,1,0,4,'sdasd','2005-07-19','00:00:00','00:00:00'),(419,1,1,0,4,'dsfsf','2005-07-19','02:32:00','02:56:00'),(420,1,1,0,4,'ersdf','2005-07-20','10:00:00','11:00:00'),(421,1,1,0,4,'fdgfdgfd','2005-07-20','03:30:00','04:00:00'),(422,1,1,0,4,'dfsdfs','2005-07-20','17:23:00','18:00:00'),(423,1,1,0,4,'sdfsd','2005-07-20','05:00:00','06:00:00'),(424,1,2,4,4,'dssfsd','2005-07-20','01:00:00','05:12:00'),(425,1,2,4,4,'sdasd','2005-07-20','00:00:00','00:00:00'),(426,1,2,4,4,'sadasd','2005-07-04','00:00:00','04:00:00'),(427,1,1,0,4,'this is my new event','2005-07-25','10:00:00','12:00:00'),(428,1,1,0,4,'sadasd','2005-07-27','14:00:00','00:00:00'),(429,1,1,0,4,'asdsadsa','2005-07-27','15:00:00','00:00:00'),(430,1,1,0,4,'asdsad','2005-07-27','00:00:00',NULL),(431,1,1,0,4,'dfsd','2005-07-27','01:00:00','04:00:00'),(432,1,1,0,4,'new1','2005-07-27','00:00:00','00:00:00'),(433,1,1,0,4,'sdasd','2005-07-27',NULL,NULL),(434,1,2,4,4,'testtest','2005-09-12',NULL,NULL),(435,1,1,0,4,'test','2005-10-17',NULL,NULL),(438,23,3,NULL,4,'dfgdfg','2006-06-11','05:55:00','05:55:00'),(439,23,3,NULL,4,'hgdyt','2006-06-11',NULL,NULL),(440,23,3,NULL,4,'kdfgldkdlfk','2006-06-11',NULL,NULL),(441,23,3,NULL,4,'hgfjhgfjhfgjhgf','2006-06-15',NULL,NULL);
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (442,23,3,NULL,4,'jhfjhgfjhgf','2006-06-15',NULL,NULL),(496,23,3,NULL,4,'This is a Calendar Note1','2006-06-11','15:30:00','18:30:00'),(497,23,3,NULL,4,'This is a Calendar Note1','2006-06-11','15:30:00','18:30:00'),(498,23,3,NULL,4,'This is a Calendar Note2','2006-06-11','15:30:00','18:30:00'),(499,23,3,NULL,4,'This is a Calendar Note3','2006-06-11','15:30:00','18:30:00'),(500,23,3,NULL,4,'This is a Calendar Note4','2006-06-11','15:30:00','18:30:00'),(501,23,3,NULL,4,'This is a Calendar Note5','2006-06-11','15:30:00','18:30:00'),(502,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(503,23,3,NULL,4,'This is a Calendar Note7','2006-06-11','15:30:00','18:30:00'),(504,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(505,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(506,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(507,23,3,NULL,4,'This is a Calendar Note7','2006-06-11','15:30:00','18:30:00'),(508,23,3,NULL,4,'This is a Calendar Note1','2006-06-11','15:30:00','18:30:00'),(509,23,3,NULL,4,'This is a Calendar Note2','2006-06-11','15:30:00','18:30:00'),(510,23,3,NULL,4,'This is a Calendar Note3','2006-06-11','15:30:00','18:30:00'),(511,23,3,NULL,4,'This is a Calendar Note4','2006-06-11','15:30:00','18:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (512,23,3,NULL,4,'This is a Calendar Note5','2006-06-11','15:30:00','18:30:00'),(513,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(514,23,3,NULL,4,'This is a Calendar Note7','2006-06-11','15:30:00','18:30:00'),(515,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(516,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(517,23,3,NULL,4,'This is a Calendar Note6','2006-06-11','15:30:00','18:30:00'),(518,23,3,NULL,4,'This is a Calendar Note7','2006-06-11','15:30:00','18:30:00'),(519,0,0,NULL,0,'','2006-06-11',NULL,NULL),(520,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(521,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00'),(522,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00'),(523,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(524,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(525,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00'),(526,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00'),(527,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(528,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(529,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (530,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00'),(531,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(532,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(533,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(534,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00'),(535,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(536,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(537,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(538,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00'),(539,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00'),(540,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00'),(541,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(542,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00'),(543,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00'),(544,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(545,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(546,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (547,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00'),(548,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(549,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(550,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00'),(551,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00'),(552,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(553,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(554,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(555,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00'),(556,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(557,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(558,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(559,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00'),(560,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00'),(561,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00'),(562,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(563,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (564,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00'),(565,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(566,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(567,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00'),(568,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00'),(569,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(570,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(571,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00'),(572,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00'),(573,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(574,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(575,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(576,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00'),(577,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(578,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(579,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(580,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (581,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00'),(582,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00'),(583,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(584,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00'),(585,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00'),(586,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(587,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(588,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00'),(589,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00'),(590,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(591,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(592,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00'),(593,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00'),(594,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(595,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(596,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(597,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (598,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(599,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(600,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(601,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00'),(602,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00'),(603,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00'),(604,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(605,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00'),(606,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00'),(607,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(608,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(609,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00'),(610,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00'),(611,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(612,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(613,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00'),(614,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (615,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(616,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(617,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(618,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00'),(619,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(620,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(621,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(622,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00'),(623,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00'),(624,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00'),(625,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(626,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00'),(627,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00'),(628,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(629,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(630,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00'),(631,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (632,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(633,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(634,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00'),(635,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00'),(636,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(637,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(638,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(639,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00'),(640,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(641,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(642,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(643,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00'),(644,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00'),(645,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00'),(646,23,3,NULL,4,'This is a Calendar Note1','2006-06-16','15:30:00','18:30:00'),(647,23,3,NULL,4,'This is a Calendar Note2','2006-06-16','16:30:00','19:30:00'),(648,23,3,NULL,4,'This is a Calendar Note3','2006-06-16','17:30:00','20:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (649,23,3,NULL,4,'This is a Calendar Note4','2006-06-16','18:30:00','21:30:00'),(650,23,3,NULL,4,'This is a Calendar Note5','2006-06-16','19:30:00','22:30:00'),(651,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','20:30:00','23:30:00'),(652,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','21:30:00','00:30:00'),(653,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','22:30:00','01:30:00'),(654,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','23:30:00','02:30:00'),(655,23,3,NULL,4,'This is a Calendar Note6','2006-06-16','00:30:00','03:30:00'),(656,23,3,NULL,4,'This is a Calendar Note7','2006-06-16','01:30:00','04:30:00'),(657,23,3,NULL,4,'This is a Calendar Note8','2006-06-16','02:30:00','05:30:00'),(658,23,3,NULL,4,'This is a Calendar Note9','2006-06-16','03:30:00','06:30:00'),(659,23,3,NULL,4,'This is a Calendar Note10','2006-06-16','04:30:00','07:30:00'),(660,23,3,NULL,4,'This is a Calendar Note11','2006-06-16','05:30:00','08:30:00'),(661,23,3,NULL,4,'This is a Calendar Note12','2006-06-16','06:30:00','09:30:00'),(662,23,3,NULL,4,'This is a Calendar Note13','2006-06-16','07:30:00','10:30:00'),(663,23,3,NULL,4,'This is a Calendar Note14','2006-06-16','08:30:00','11:30:00'),(664,23,3,NULL,4,'This is a Calendar Note15','2006-06-16','09:30:00','12:30:00'),(665,23,3,NULL,4,'This is a Calendar Note16','2006-06-16','10:30:00','13:30:00');
insert into `event`(`id`,`owner`,`access`,`sid`,`priority`,`note`,`date`,`startTime`,`endTime`) values (666,23,3,NULL,4,'This is a Calendar Note17','2006-06-16','11:30:00','14:30:00');

/*Table structure for table `eventCampus` */

DROP TABLE IF EXISTS `eventCampus`;

CREATE TABLE `eventCampus` (
  `eventID` int(11) NOT NULL auto_increment,
  `campusID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`campusID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `eventCampus` */

insert into `eventCampus`(`eventID`,`campusID`) values (9,0),(10,0),(11,0),(12,0),(13,0),(14,0),(15,0),(16,1),(16,2),(17,1),(17,2),(18,1),(18,2),(19,1),(19,2),(20,1),(20,2),(21,1),(21,2),(22,1),(22,2),(23,1),(23,2),(24,1),(24,2),(25,1),(25,2),(26,1),(26,2),(27,1),(27,2),(28,1),(28,2),(29,1),(29,2),(30,1),(30,2),(31,1),(31,2),(32,1),(32,2),(33,1),(33,2),(34,1),(34,2),(35,1),(35,2),(36,1),(36,2),(37,1),(37,2),(38,1),(38,2),(39,1),(39,2),(40,1),(40,2),(41,1),(41,2),(42,1),(42,2),(43,1),(43,2),(44,1),(44,2),(45,1),(45,2),(46,1),(46,2),(47,1),(47,2),(48,1),(48,2),(49,1),(49,2),(50,1),(50,2),(51,1),(51,2),(52,1),(52,2),(53,1),(53,2),(54,1),(54,2),(55,1),(55,2),(56,1),(56,2),(57,1),(57,2),(58,1),(58,2),(59,1),(59,2),(60,1),(60,2),(61,1),(61,2),(62,1),(62,2),(63,1),(63,2),(64,1),(64,2),(65,1),(65,2),(66,1),(66,2),(67,1),(67,2),(68,1),(68,2),(69,1),(69,2),(70,1),(70,2),(71,1),(71,2),(72,1),(72,2),(73,1),(73,2),(74,1),(74,2),(75,1),(75,2),(76,1),(76,2),(77,1),(77,2),(78,1),(78,2),(79,1),(79,2),(80,1),(80,2),(81,1),(81,2),(82,1),(82,2),(83,1),(83,2),(84,1),(84,2),(85,1),(85,2),(86,1),(86,2),(87,1),(87,2),(88,1),(88,2),(89,1),(89,2),(90,1),(90,2),(91,1),(91,2),(92,1),(92,2),(93,1),(93,2),(94,1),(94,2),(95,1),(95,2),(96,1),(96,2),(97,1);
insert into `eventCampus`(`eventID`,`campusID`) values (97,2),(98,1),(98,2),(99,1),(99,2),(100,1),(100,2),(101,1),(101,2),(102,1),(102,2),(103,1),(103,2),(104,1),(104,2),(105,1),(105,2),(106,1),(106,2),(107,1),(107,2),(108,1),(108,2),(109,1),(109,2),(110,1),(110,2),(111,1),(111,2),(112,1),(112,2),(113,1),(113,2),(114,1),(114,2),(115,1),(115,2),(116,1),(116,2),(117,1),(117,2),(118,1),(118,2),(119,1),(119,2),(120,1),(120,2),(121,1),(121,2),(122,1),(122,2),(123,1),(123,2),(124,1),(124,2),(125,1),(125,2),(126,1),(126,2),(127,1),(127,2),(128,1),(128,2),(129,1),(129,2),(130,1),(130,2),(131,1),(131,2),(132,1),(132,2),(133,1),(133,2),(134,1),(134,2),(135,1),(135,2),(136,1),(136,2),(137,1),(137,2),(138,1),(138,2),(139,1),(139,2),(140,1),(140,2),(141,1),(141,2),(142,1),(142,2),(143,1),(143,2),(144,1),(144,2),(145,1),(145,2),(146,1),(146,2),(147,1),(147,2),(148,1),(148,2),(149,1),(149,2),(150,1),(150,2),(151,1),(151,2),(152,1),(152,2),(153,1),(153,2),(154,1),(154,2),(155,1),(155,2),(156,1),(156,2),(157,1),(157,2),(158,1),(158,2),(159,1),(159,2),(160,1),(160,2),(161,1),(161,2),(162,1),(162,2),(163,1),(163,2),(164,1),(164,2),(165,1),(165,2),(166,1),(166,2),(167,1),(167,2),(168,1),(168,2),(169,1),(169,2),(170,1);
insert into `eventCampus`(`eventID`,`campusID`) values (170,2),(171,1),(171,2),(172,1),(172,2),(173,1),(173,2),(174,1),(174,2),(175,1),(175,2),(176,1),(176,2),(177,1),(177,2),(178,1),(178,2),(179,1),(179,2),(180,1),(180,2),(181,1),(181,2),(182,1),(182,2),(183,1),(183,2),(184,1),(184,2),(185,1),(185,2),(186,1),(186,2),(187,1),(187,2),(188,1),(188,2),(189,1),(189,2),(190,1),(190,2),(191,1),(191,2),(192,1),(192,2),(193,1),(193,2),(194,1),(194,2),(195,1),(195,2),(196,1),(196,2),(197,1),(197,2),(198,1),(198,2),(199,1),(199,2),(200,1),(200,2),(201,1),(201,2),(202,1),(202,2),(203,1),(203,2),(204,1),(204,2),(205,1),(205,2),(206,1),(206,2),(207,1),(207,2),(208,1),(208,2),(209,1),(209,2),(210,1),(210,2),(211,1),(211,2),(212,1),(212,2),(213,1),(213,2),(214,1),(214,2),(215,1),(215,2),(216,1),(216,2),(217,1),(217,2),(218,1),(218,2),(219,1),(219,2),(220,1),(220,2),(221,1),(221,2),(222,1),(222,2),(223,1),(223,2),(224,1),(224,2),(225,1),(225,2),(226,1),(226,2),(227,1),(227,2),(228,1),(228,2),(229,1),(229,2),(230,1),(230,2),(231,1),(231,2),(232,1),(232,2),(233,1),(233,2),(234,1),(234,2),(235,1),(235,2),(236,1),(236,2),(237,1),(237,2),(238,1),(238,2),(239,1),(239,2),(240,1),(240,2),(241,1),(241,2),(242,1),(242,2),(243,1);
insert into `eventCampus`(`eventID`,`campusID`) values (243,2),(244,1),(244,2),(245,1),(245,2),(246,1),(246,2),(247,1),(247,2),(248,1),(248,2),(249,1),(249,2),(250,1),(250,2),(251,1),(251,2),(252,1),(252,2),(253,1),(253,2),(254,1),(254,2),(255,1),(255,2),(256,1),(256,2),(257,1),(257,2),(258,1),(258,2),(259,1),(259,2),(260,1),(260,2),(261,1),(261,2),(262,1),(262,2),(263,1),(263,2),(264,1),(264,2),(265,1),(265,2),(266,1),(266,2),(267,1),(267,2),(268,1),(268,2),(269,1),(269,2),(270,1),(270,2),(271,1),(271,2),(272,1),(272,2),(273,1),(273,2),(274,1),(274,2),(275,1),(275,2),(276,1),(276,2),(277,1),(277,2),(278,1),(278,2),(279,1),(279,2),(280,1),(280,2),(281,1),(281,2),(282,1),(282,2),(283,1),(283,2),(284,1),(284,2),(285,1),(285,2),(286,1),(286,2),(287,1),(287,2),(288,1),(288,2),(289,1),(289,2),(290,1),(290,2),(291,1),(291,2),(292,1),(292,2),(293,1),(293,2),(294,1),(294,2),(295,1),(295,2),(296,1),(296,2),(297,1),(297,2),(298,1),(298,2),(299,1),(299,2),(300,1),(300,2),(301,1),(301,2),(302,1),(302,2),(303,1),(303,2),(304,1),(304,2),(305,1),(305,2),(306,1),(306,2),(307,1),(307,2),(308,1),(308,2),(309,1),(309,2),(310,1),(310,2),(311,1),(311,2),(312,1),(312,2),(313,1),(313,2),(314,1),(314,2),(315,1),(315,2),(316,1);
insert into `eventCampus`(`eventID`,`campusID`) values (316,2),(317,1),(317,2),(318,1),(318,2),(319,1),(319,2),(320,1),(320,2),(321,1),(321,2),(322,1),(322,2),(323,1),(323,2),(324,1),(324,2),(325,1),(325,2),(326,1),(326,2),(327,1),(327,2),(328,1),(328,2),(329,1),(329,2),(330,1),(330,2),(331,1),(331,2),(332,1),(332,2),(333,1),(333,2),(334,1),(334,2),(335,1),(335,2),(336,1),(336,2),(337,1),(337,2),(338,1),(338,2),(339,1),(339,2),(340,1),(340,2),(341,1),(341,2),(342,1),(342,2),(343,1),(343,2),(344,1),(344,2),(345,1),(345,2),(346,1),(346,2),(347,1),(347,2),(348,1),(348,2),(349,1),(349,2),(350,1),(350,2),(351,1),(351,2),(352,1),(352,2),(353,1),(353,2),(354,1),(354,2),(355,1),(355,2),(356,1),(356,2),(357,1),(357,2),(358,1),(358,2),(359,1),(359,2),(360,1),(360,2),(361,1),(361,2),(362,1),(362,2),(363,1),(363,2),(364,1),(364,2),(365,1),(365,2),(366,1),(366,2),(367,1),(367,2),(368,1),(368,2),(369,1),(369,2),(370,1),(370,2),(371,1),(371,2),(372,1),(372,2),(373,1),(373,2),(374,1),(374,2),(375,1),(375,2),(376,1),(376,2),(377,1),(377,2),(378,1),(378,2),(379,1),(379,2),(380,1),(380,2),(381,1),(381,2),(382,1),(382,2),(383,1),(383,2),(384,1),(384,2),(385,1),(385,2),(386,1),(386,2),(387,1),(387,2),(388,1),(388,2),(389,1);
insert into `eventCampus`(`eventID`,`campusID`) values (389,2),(390,1),(390,2),(391,1),(391,2),(392,1),(392,2),(393,1),(393,2),(394,1),(394,2),(395,1),(395,2),(396,1),(396,2),(397,1),(397,2),(398,1),(398,2),(399,1),(399,2),(400,1),(400,2),(401,1),(401,2),(402,1),(402,2),(438,1),(438,2),(439,1),(439,2),(440,1),(440,2),(441,1),(441,2),(442,1),(442,2),(443,1),(444,1),(445,1),(446,1),(447,1),(448,1),(449,1),(452,1),(453,1),(454,1),(456,1),(457,1),(458,1),(458,2),(459,1),(460,2),(462,1),(462,2),(463,1),(465,1),(466,1),(467,1),(468,1),(469,1),(470,1),(471,1),(497,1),(498,1),(499,1),(500,1),(501,1),(502,1),(503,1),(504,1),(505,1),(506,1),(507,1),(508,1),(508,2),(509,1),(509,2),(510,1),(510,2),(511,1),(511,2),(512,1),(512,2),(513,1),(513,2),(514,1),(514,2),(515,1),(515,2),(516,1),(516,2),(517,1),(517,2),(518,1),(518,2),(520,1),(521,1),(522,1),(523,1),(524,1),(525,1),(526,1),(527,1),(528,1),(529,1),(530,1),(531,1),(532,1),(533,1),(534,1),(535,1),(536,1),(537,1),(538,1),(539,1),(540,1),(541,1),(542,1),(543,1),(544,1),(545,1),(546,1),(547,1),(548,1),(549,1),(550,1),(551,1),(552,1),(553,1),(554,1),(555,1),(556,1),(557,1),(558,1),(559,1),(560,1),(561,1),(562,1),(563,1),(564,1),(565,1),(566,1),(567,1),(568,1),(569,1);
insert into `eventCampus`(`eventID`,`campusID`) values (570,1),(571,1),(572,1),(573,1),(574,1),(575,1),(576,1),(577,1),(578,1),(579,1),(580,1),(581,1),(582,1),(583,1),(584,1),(585,1),(586,1),(587,1),(588,1),(589,1),(590,1),(591,1),(592,1),(593,1),(594,1),(595,1),(596,1),(597,1),(598,1),(599,1),(600,1),(601,1),(602,1),(603,1),(604,1),(605,1),(606,1),(607,1),(608,1),(609,1),(610,1),(611,1),(612,1),(613,1),(614,1),(615,1),(616,1),(617,1),(618,1),(619,1),(620,1),(621,1),(622,1),(623,1),(624,1),(625,1),(626,1),(627,1),(628,1),(629,1),(630,1),(631,1),(632,1),(633,1),(634,1),(635,1),(636,1),(637,1),(638,1),(639,1),(640,1),(641,1),(642,1),(643,1),(644,1),(645,1),(646,1),(647,1),(648,1),(649,1),(650,1),(651,1),(652,1),(653,1),(654,1),(655,1),(656,1),(657,1),(658,1),(659,1),(660,1),(661,1),(662,1),(663,1),(664,1),(665,1),(666,1),(667,1),(668,1),(669,1),(670,1),(671,1),(672,2),(673,2),(674,1),(675,1);

/*Table structure for table `eventGroup` */

DROP TABLE IF EXISTS `eventGroup`;

CREATE TABLE `eventGroup` (
  `eventID` int(11) NOT NULL auto_increment,
  `groupID` int(11) NOT NULL default '0',
  PRIMARY KEY  (`eventID`,`groupID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `eventGroup` */

insert into `eventGroup`(`eventID`,`groupID`) values (9,1),(9,2),(9,3),(10,1),(10,2),(10,3),(11,1),(11,2),(11,3),(12,1),(12,2),(12,3),(13,1),(13,2),(13,3),(14,1),(14,2),(14,3),(15,1),(15,2),(15,3),(16,1),(16,2),(16,3),(17,1),(17,2),(17,3),(18,1),(18,2),(18,3),(19,1),(19,2),(19,3),(20,1),(20,2),(20,3),(21,1),(21,2),(21,3),(22,1),(22,2),(22,3),(23,1),(23,2),(23,3),(24,1),(24,2),(24,3),(25,1),(25,2),(25,3),(26,1),(26,2),(26,3),(27,1),(27,2),(27,3),(28,1),(28,2),(28,3),(29,1),(29,2),(29,3),(30,1),(30,2),(30,3),(31,1),(31,2),(31,3),(32,1),(32,2),(32,3),(33,1),(33,2),(33,3),(34,1),(34,2),(34,3),(35,1),(35,2),(35,3),(36,1),(36,2),(36,3),(37,1),(37,2),(37,3),(38,1),(38,2),(38,3),(39,1),(39,2),(39,3),(40,1),(40,2),(40,3),(41,1),(41,2),(41,3),(42,1),(42,2),(42,3),(43,1),(43,2),(43,3),(44,1),(44,2),(44,3),(45,1),(45,2),(45,3),(46,1),(46,2),(46,3),(47,1),(47,2),(47,3),(48,1),(48,2),(48,3),(49,1),(49,2),(49,3),(50,1),(50,2),(50,3),(51,1),(51,2),(51,3),(52,1),(52,2),(52,3),(53,1),(53,2),(53,3),(54,1),(54,2),(54,3),(55,1),(55,2),(55,3),(56,1),(56,2),(56,3),(57,1),(57,2),(57,3),(58,1),(58,2),(58,3),(59,1),(59,2),(59,3),(60,1),(60,2),(60,3),(61,1),(61,2),(61,3),(62,1),(62,2),(62,3),(63,1),(63,2),(63,3),(64,1),(64,2),(64,3),(65,1),(65,2);
insert into `eventGroup`(`eventID`,`groupID`) values (65,3),(66,1),(66,2),(66,3),(67,1),(67,2),(67,3),(68,1),(68,2),(68,3),(69,1),(69,2),(69,3),(70,1),(70,2),(70,3),(71,1),(71,2),(71,3),(72,1),(72,2),(72,3),(73,1),(73,2),(73,3),(74,1),(74,2),(74,3),(75,1),(75,2),(75,3),(76,1),(76,2),(76,3),(77,1),(77,2),(77,3),(78,1),(78,2),(78,3),(79,1),(79,2),(79,3),(80,1),(80,2),(80,3),(81,1),(81,2),(81,3),(82,1),(82,2),(82,3),(83,1),(83,2),(83,3),(84,1),(84,2),(84,3),(85,1),(85,2),(85,3),(86,1),(86,2),(86,3),(87,1),(87,2),(87,3),(88,1),(88,2),(88,3),(89,1),(89,2),(89,3),(90,1),(90,2),(90,3),(91,1),(91,2),(91,3),(92,1),(92,2),(92,3),(93,1),(93,2),(93,3),(94,1),(94,2),(94,3),(95,1),(95,2),(95,3),(96,1),(96,2),(96,3),(97,1),(97,2),(97,3),(98,1),(98,2),(98,3),(99,1),(99,2),(99,3),(100,1),(100,2),(100,3),(101,1),(101,2),(101,3),(102,1),(102,2),(102,3),(103,1),(103,2),(103,3),(104,1),(104,2),(104,3),(105,1),(105,2),(105,3),(106,1),(106,2),(106,3),(107,1),(107,2),(107,3),(108,1),(108,2),(108,3),(109,1),(109,2),(109,3),(110,1),(110,2),(110,3),(111,1),(111,2),(111,3),(112,1),(112,2),(112,3),(113,1),(113,2),(113,3),(114,1),(114,2),(114,3),(115,1),(115,2),(115,3),(116,1),(116,2),(116,3),(117,1),(117,2),(117,3),(118,1),(118,2),(118,3);
insert into `eventGroup`(`eventID`,`groupID`) values (119,1),(119,2),(119,3),(120,1),(120,2),(120,3),(121,1),(121,2),(121,3),(122,1),(122,2),(122,3),(123,1),(123,2),(123,3),(124,1),(124,2),(124,3),(125,1),(125,2),(125,3),(126,1),(126,2),(126,3),(127,1),(127,2),(127,3),(128,1),(128,2),(128,3),(129,1),(129,2),(129,3),(130,1),(130,2),(130,3),(131,1),(131,2),(131,3),(132,1),(132,2),(132,3),(133,1),(133,2),(133,3),(134,1),(134,2),(134,3),(135,1),(135,2),(135,3),(136,1),(136,2),(136,3),(137,1),(137,2),(137,3),(138,1),(138,2),(138,3),(139,1),(139,2),(139,3),(140,1),(140,2),(140,3),(141,1),(141,2),(141,3),(142,1),(142,2),(142,3),(143,1),(143,2),(143,3),(144,1),(144,2),(144,3),(145,1),(145,2),(145,3),(146,1),(146,2),(146,3),(147,1),(147,2),(147,3),(148,1),(148,2),(148,3),(149,1),(149,2),(149,3),(150,1),(150,2),(150,3),(151,1),(151,2),(151,3),(152,1),(152,2),(152,3),(153,1),(153,2),(153,3),(154,1),(154,2),(154,3),(155,1),(155,2),(155,3),(156,1),(156,2),(156,3),(157,1),(157,2),(157,3),(158,1),(158,2),(158,3),(159,1),(159,2),(159,3),(160,1),(160,2),(160,3),(161,1),(161,2),(161,3),(162,1),(162,2),(162,3),(163,1),(163,2),(163,3),(164,1),(164,2),(164,3),(165,1),(165,2),(165,3),(166,1),(166,2),(166,3),(167,1),(167,2);
insert into `eventGroup`(`eventID`,`groupID`) values (167,3),(168,1),(168,2),(168,3),(169,1),(169,2),(169,3),(170,1),(170,2),(170,3),(171,1),(171,2),(171,3),(172,1),(172,2),(172,3),(173,1),(173,2),(173,3),(174,1),(174,2),(174,3),(175,1),(175,2),(175,3),(176,1),(176,2),(176,3),(177,1),(177,2),(177,3),(178,1),(178,2),(178,3),(179,1),(179,2),(179,3),(180,1),(180,2),(180,3),(181,1),(181,2),(181,3),(182,1),(182,2),(182,3),(183,1),(183,2),(183,3),(184,1),(184,2),(184,3),(185,1),(185,2),(185,3),(186,1),(186,2),(186,3),(187,1),(187,2),(187,3),(188,1),(188,2),(188,3),(189,1),(189,2),(189,3),(190,1),(190,2),(190,3),(191,1),(191,2),(191,3),(192,1),(192,2),(192,3),(193,1),(193,2),(193,3),(194,1),(194,2),(194,3),(195,1),(195,2),(195,3),(196,1),(196,2),(196,3),(197,1),(197,2),(197,3),(198,1),(198,2),(198,3),(199,1),(199,2),(199,3),(200,1),(200,2),(200,3),(201,1),(201,2),(201,3),(202,1),(202,2),(202,3),(203,1),(203,2),(203,3),(204,1),(204,2),(204,3),(205,1),(205,2),(205,3),(206,1),(206,2),(206,3),(207,1),(207,2),(207,3),(208,1),(208,2),(208,3),(209,1),(209,2),(209,3),(210,1),(210,2),(210,3),(211,1),(211,2),(211,3),(212,1),(212,2),(212,3),(213,1),(213,2),(213,3),(214,1),(214,2),(214,3),(215,1),(215,2),(215,3),(216,1);
insert into `eventGroup`(`eventID`,`groupID`) values (216,2),(216,3),(217,1),(217,2),(217,3),(218,1),(218,2),(218,3),(219,1),(219,2),(219,3),(220,1),(220,2),(220,3),(221,1),(221,2),(221,3),(222,1),(222,2),(222,3),(223,1),(223,2),(223,3),(224,1),(224,2),(224,3),(225,1),(225,2),(225,3),(226,1),(226,2),(226,3),(227,1),(227,2),(227,3),(228,1),(228,2),(228,3),(229,1),(229,2),(229,3),(230,1),(230,2),(230,3),(231,1),(231,2),(231,3),(232,1),(232,2),(232,3),(233,1),(233,2),(233,3),(234,1),(234,2),(234,3),(235,1),(235,2),(235,3),(236,1),(236,2),(236,3),(237,1),(237,2),(237,3),(238,1),(238,2),(238,3),(239,1),(239,2),(239,3),(240,1),(240,2),(240,3),(241,1),(241,2),(241,3),(242,1),(242,2),(242,3),(243,1),(243,2),(243,3),(244,1),(244,2),(244,3),(245,1),(245,2),(245,3),(246,1),(246,2),(246,3),(247,1),(247,2),(247,3),(248,1),(248,2),(248,3),(249,1),(249,2),(249,3),(250,1),(250,2),(250,3),(251,1),(251,2),(251,3),(252,1),(252,2),(252,3),(253,1),(253,2),(253,3),(254,1),(254,2),(254,3),(255,1),(255,2),(255,3),(256,1),(256,2),(256,3),(257,1),(257,2),(257,3),(258,1),(258,2),(258,3),(259,1),(259,2),(259,3),(260,1),(260,2),(260,3),(261,1),(261,2),(261,3),(262,1),(262,2),(262,3),(263,1),(263,2),(263,3),(264,1),(264,2),(264,3);
insert into `eventGroup`(`eventID`,`groupID`) values (265,1),(265,2),(265,3),(266,1),(266,2),(266,3),(267,1),(267,2),(267,3),(268,1),(268,2),(268,3),(269,1),(269,2),(269,3),(270,1),(270,2),(270,3),(271,1),(271,2),(271,3),(272,1),(272,2),(272,3),(273,1),(273,2),(273,3),(274,1),(274,2),(274,3),(275,1),(275,2),(275,3),(276,1),(276,2),(276,3),(277,1),(277,2),(277,3),(278,1),(278,2),(278,3),(279,1),(279,2),(279,3),(280,1),(280,2),(280,3),(281,1),(281,2),(281,3),(282,1),(282,2),(282,3),(283,1),(283,2),(283,3),(284,1),(284,2),(284,3),(285,1),(285,2),(285,3),(286,1),(286,2),(286,3),(287,1),(287,2),(287,3),(288,1),(288,2),(288,3),(289,1),(289,2),(289,3),(290,1),(290,2),(290,3),(291,1),(291,2),(291,3),(292,1),(292,2),(292,3),(293,1),(293,2),(293,3),(294,1),(294,2),(294,3),(295,1),(295,2),(295,3),(296,1),(296,2),(296,3),(297,1),(297,2),(297,3),(298,1),(298,2),(298,3),(299,1),(299,2),(299,3),(300,1),(300,2),(300,3),(301,1),(301,2),(301,3),(302,1),(302,2),(302,3),(303,1),(303,2),(303,3),(304,1),(304,2),(304,3),(305,1),(305,2),(305,3),(306,1),(306,2),(306,3),(307,1),(307,2),(307,3),(308,1),(308,2),(308,3),(309,1),(309,2),(309,3),(310,1),(310,2),(310,3),(311,1),(311,2),(311,3),(312,1),(312,2),(312,3),(313,1),(313,2);
insert into `eventGroup`(`eventID`,`groupID`) values (313,3),(314,1),(314,2),(314,3),(315,1),(315,2),(315,3),(316,1),(316,2),(316,3),(317,1),(317,2),(317,3),(318,1),(318,2),(318,3),(319,1),(319,2),(319,3),(320,1),(320,2),(320,3),(321,1),(321,2),(321,3),(322,1),(322,2),(322,3),(323,1),(323,2),(323,3),(324,1),(324,2),(324,3),(325,1),(325,2),(325,3),(326,1),(326,2),(326,3),(327,1),(327,2),(327,3),(328,1),(328,2),(328,3),(329,1),(329,2),(329,3),(330,1),(330,2),(330,3),(331,1),(331,2),(331,3),(332,1),(332,2),(332,3),(333,1),(333,2),(333,3),(334,1),(334,2),(334,3),(335,1),(335,2),(335,3),(336,1),(336,2),(336,3),(337,1),(337,2),(337,3),(338,1),(338,2),(338,3),(339,1),(339,2),(339,3),(340,1),(340,2),(340,3),(341,1),(341,2),(341,3),(342,1),(342,2),(342,3),(343,1),(343,2),(343,3),(344,1),(344,2),(344,3),(345,1),(345,2),(345,3),(346,1),(346,2),(346,3),(347,1),(347,2),(347,3),(348,1),(348,2),(348,3),(349,1),(349,2),(349,3),(350,1),(350,2),(350,3),(351,1),(351,2),(351,3),(352,1),(352,2),(352,3),(353,1),(353,2),(353,3),(354,1),(354,2),(354,3),(355,1),(355,2),(355,3),(356,1),(356,2),(356,3),(357,1),(357,2),(357,3),(358,1),(358,2),(358,3),(359,1),(359,2),(359,3),(360,1),(360,2),(360,3),(361,1),(361,2),(361,3),(362,1);
insert into `eventGroup`(`eventID`,`groupID`) values (362,2),(362,3),(363,1),(363,2),(363,3),(364,1),(364,2),(364,3),(365,1),(365,2),(365,3),(366,1),(366,2),(366,3),(367,1),(367,2),(367,3),(368,1),(368,2),(368,3),(369,1),(369,2),(369,3),(370,1),(370,2),(370,3),(371,1),(371,2),(371,3),(372,1),(372,2),(372,3),(373,1),(373,2),(373,3),(374,1),(374,2),(374,3),(375,1),(375,2),(375,3),(376,1),(376,2),(376,3),(377,1),(377,2),(377,3),(378,1),(378,2),(378,3),(379,1),(379,2),(379,3),(380,1),(380,2),(380,3),(381,1),(381,2),(381,3),(382,1),(382,2),(382,3),(383,1),(383,2),(383,3),(384,1),(384,2),(384,3),(385,1),(385,2),(385,3),(386,1),(386,2),(386,3),(387,1),(387,2),(387,3),(388,1),(388,2),(388,3),(389,1),(389,2),(389,3),(390,1),(390,2),(390,3),(391,1),(391,2),(391,3),(392,1),(392,2),(392,3),(393,1),(393,2),(393,3),(394,1),(394,2),(394,3),(395,1),(395,2),(395,3),(396,1),(396,2),(396,3),(397,1),(397,2),(397,3),(398,1),(398,2),(398,3),(399,1),(399,2),(399,3),(400,1),(400,2),(400,3),(401,1),(401,2),(401,3),(402,1),(402,2),(402,3),(438,1),(438,2),(438,3),(439,1),(439,2),(439,3),(440,1),(440,2),(440,3),(441,1),(441,2),(441,3),(442,1),(442,2),(442,3),(443,1),(444,1),(444,2),(444,3),(445,1),(445,2),(445,3),(446,1),(446,2);
insert into `eventGroup`(`eventID`,`groupID`) values (446,3),(447,1),(447,2),(447,3),(454,1),(458,1),(458,2),(458,3),(460,3),(463,1),(463,2),(465,1),(465,3),(467,1),(468,2),(469,3),(470,3),(471,1),(471,2),(497,1),(497,2),(497,3),(498,1),(498,2),(498,3),(499,1),(499,2),(499,3),(500,1),(500,2),(500,3),(501,1),(501,2),(501,3),(502,1),(502,2),(502,3),(503,1),(503,2),(503,3),(504,1),(504,2),(504,3),(505,1),(505,2),(505,3),(506,1),(506,2),(506,3),(507,1),(507,2),(507,3),(508,1),(508,2),(508,3),(509,1),(509,2),(509,3),(510,1),(510,2),(510,3),(511,1),(511,2),(511,3),(512,1),(512,2),(512,3),(513,1),(513,2),(513,3),(514,1),(514,2),(514,3),(515,1),(515,2),(515,3),(516,1),(516,2),(516,3),(517,1),(517,2),(517,3),(518,1),(518,2),(518,3),(520,1),(520,2),(520,3),(521,1),(521,2),(521,3),(522,1),(522,2),(522,3),(523,1),(523,2),(523,3),(524,1),(524,2),(524,3),(525,1),(525,2),(525,3),(526,1),(526,2),(526,3),(527,1),(527,2),(527,3),(528,1),(528,2),(528,3),(529,1),(529,2),(529,3),(530,1),(530,2),(530,3),(531,1),(531,2),(531,3),(532,1),(532,2),(532,3),(533,1),(533,2),(533,3),(534,1),(534,2),(534,3),(535,1),(535,2),(535,3),(536,1),(536,2),(536,3),(537,1),(537,2),(537,3),(538,1),(538,2),(538,3),(539,1),(539,2),(539,3),(540,1);
insert into `eventGroup`(`eventID`,`groupID`) values (540,2),(540,3),(541,1),(541,2),(541,3),(542,1),(542,2),(542,3),(543,1),(543,2),(543,3),(544,1),(544,2),(544,3),(545,1),(545,2),(545,3),(546,1),(546,2),(546,3),(547,1),(547,2),(547,3),(548,1),(548,2),(548,3),(549,1),(549,2),(549,3),(550,1),(550,2),(550,3),(551,1),(551,2),(551,3),(552,1),(552,2),(552,3),(553,1),(553,2),(553,3),(554,1),(554,2),(554,3),(555,1),(555,2),(555,3),(556,1),(556,2),(556,3),(557,1),(557,2),(557,3),(558,1),(558,2),(558,3),(559,1),(559,2),(559,3),(560,1),(560,2),(560,3),(561,1),(561,2),(561,3),(583,1),(583,2),(583,3),(584,1),(584,2),(584,3),(585,1),(585,2),(585,3),(586,1),(586,2),(586,3),(587,1),(587,2),(587,3),(588,1),(588,2),(588,3),(589,1),(589,2),(589,3),(590,1),(590,2),(590,3),(591,1),(591,2),(591,3),(592,1),(592,2),(592,3),(593,1),(593,2),(593,3),(594,1),(594,2),(594,3),(595,1),(595,2),(595,3),(596,1),(596,2),(596,3),(597,1),(597,2),(597,3),(598,1),(598,2),(598,3),(599,1),(599,2),(599,3),(600,1),(600,2),(600,3),(601,1),(601,2),(601,3),(602,1),(602,2),(602,3),(603,1),(603,2),(603,3),(604,3),(605,3),(606,3),(607,3),(608,3),(609,3),(610,3),(611,3),(612,3),(613,3),(614,3),(615,3),(616,3),(617,3),(618,3),(619,3),(620,3),(621,3);
insert into `eventGroup`(`eventID`,`groupID`) values (622,3),(623,3),(624,3),(625,3),(626,3),(627,3),(628,3),(629,3),(630,3),(631,3),(632,3),(633,3),(634,3),(635,3),(636,3),(637,3),(638,3),(639,3),(640,3),(641,3),(642,3),(643,3),(644,3),(645,3),(646,2),(647,2),(648,2),(649,2),(650,2),(651,2),(652,2),(653,2),(654,2),(655,2),(656,2),(657,2),(658,2),(659,2),(660,2),(661,2),(662,2),(663,2),(664,2),(665,2),(666,2),(667,1),(668,2),(669,1),(670,2),(671,3),(672,1),(673,2),(674,3),(675,1);

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

insert into `file`(`id`,`category`,`owner`,`realname`,`submitted`,`description`,`comment`,`type`,`file_size`,`mime_type`,`deleted`) values (1,1,1,'','2003-12-30 02:12:55','Junk Data Txt File','Just some test text',0,NULL,'',1),(2,1,1,'','2003-12-30 02:13:20','ReadMe','',0,NULL,'',1),(3,1,1,'','2003-12-30 02:17:10','Bitmap Image','',0,NULL,'',1),(4,1,1,'','2003-12-30 12:56:10','Speed Cameras','Test',0,NULL,'',1),(5,1,1,'','2003-12-30 12:59:39','Testing speed cameras again','',0,NULL,'',1),(6,1,1,'','2003-12-30 13:01:36','test','',0,NULL,'',1),(7,1,1,'','2003-12-30 13:02:07','test','',0,NULL,'',1),(8,1,1,'','2003-12-30 13:16:48','another test','',0,NULL,'',1),(9,4,3,'New Text Document (2).txt','2004-01-22 00:52:38','Test Submission Box','Testing Submission Boxes',0,NULL,'text/plain',0),(10,1,1,'awaysys.zip','2004-02-10 15:06:41','test','',0,NULL,'application/x-zip-compressed',1),(11,1,1,'extendable service system.doc','2004-02-10 15:07:30','component design','',0,NULL,'application/msword',0),(12,3,1,'extendable service system.doc','2004-02-10 15:11:01','test','',1,NULL,'application/msword',0),(13,1,1,'camera_12091.jpg','2004-02-24 15:48:47','jpg camera','',0,NULL,'image/pjpeg',0),(14,7,1,'camera_12091.jpg','2004-02-24 23:34:12','speed cameras','',0,NULL,'image/pjpeg',0),(15,7,1,'alien.jpg','2004-02-24 23:34:51','alien','',0,NULL,'image/pjpeg',0),(16,7,1,'logo_mgs_right_001.gif','2004-02-24 23:35:06','mgs gif','',0,NULL,'image/gif',0),(17,7,1,'Sample.jpg','2004-02-24 23:35:25','xp sample','',0,NULL,'image/pjpeg',0);
insert into `file`(`id`,`category`,`owner`,`realname`,`submitted`,`description`,`comment`,`type`,`file_size`,`mime_type`,`deleted`) values (18,7,1,'rainbowlorrikets.jpg','2004-02-24 23:35:42','rainbow lorikeets','',0,NULL,'image/pjpeg',0),(19,7,1,'Sunset.jpg','2004-02-24 23:36:00','sunset','',0,NULL,'image/pjpeg',0),(20,1,1,'','2004-03-16 10:55:37','Hello <script>alert(\"hahahaha\");</script>','',0,NULL,'',1),(21,7,1,'nav-vce.gif','2004-04-12 17:35:01','vcal','',0,NULL,'image/gif',0),(22,7,1,'logo.gif','2004-04-12 17:35:20','test logo','',0,NULL,'image/gif',0),(23,7,1,'logo-001.gif','2004-04-12 17:39:19','another logo','',0,NULL,'image/gif',0),(24,7,1,'cloud_increasing.gif','2004-04-12 17:40:15','cloud','',0,NULL,'image/gif',0),(25,7,1,'possible_thunderstorm.gif','2004-04-12 17:40:24','thunder','',0,NULL,'image/gif',1),(26,7,1,'snow.gif','2004-04-12 17:40:42','snow','',0,NULL,'image/gif',1),(27,7,1,'104_0478.JPG','2004-04-12 17:45:04','wine','',0,NULL,'image/pjpeg',1),(28,7,1,'105_0586.JPG','2004-04-12 18:16:16','flood','',0,NULL,'image/pjpeg',0),(29,8,1,'105_0586.JPG','2004-04-12 19:15:36','flood','',0,NULL,'image/pjpeg',0),(30,7,1,'1sthousepano.jpg','2004-04-12 21:11:36','merimbula','',0,NULL,'image/pjpeg',0),(31,18,5,'BIG ASSIGNMENT.doc','2004-09-23 16:32:30','Impossible Assignment','',2,NULL,'application/msword',0),(32,18,5,'BIG ASSIGNMENT.doc','2004-09-23 17:07:17','Impossible Assignment','',2,NULL,'application/msword',0),(33,2,1,'CHANGELOG.txt','2005-03-07 01:09:21','test','test',0,NULL,'text/plain',0);
insert into `file`(`id`,`category`,`owner`,`realname`,`submitted`,`description`,`comment`,`type`,`file_size`,`mime_type`,`deleted`) values (34,2,1,'CHANGELOG.txt','2005-03-07 01:11:00','another 1','sdasdad',0,NULL,'text/plain',0),(35,1,1,'expired users.csv','2005-03-29 21:22:53','my csv','testing csv',0,NULL,'application/vnd.ms-excel',0),(36,4,1,'Water lilies.jpg','2005-04-07 01:16:34','Preview Image','This is my alt tag',0,NULL,'image/jpeg',0),(37,4,1,'','2005-06-14 19:45:59','new file','',0,0,'',0),(38,11,1,'','2005-06-14 19:47:00','another file','asd',0,0,'',0),(39,4,1,'','2005-06-14 19:47:16','my sub','sad',0,0,'',0),(40,4,1,'','2005-06-26 22:36:58','submission system','asds',0,0,'',0),(41,4,1,'','2005-06-26 23:34:50','another submission','sdsad',0,0,'',0),(42,4,1,'','2005-07-19 21:37:57','fghfgh','fghgdhgfhgfh',2,0,'',0),(43,4,1,'','2005-07-19 22:38:30','asdasd','adssad',2,0,'',0),(44,4,1,'','2005-07-20 01:03:08','sdas','asdsadas',2,0,'',0),(45,4,1,'','2005-07-27 01:13:40','test homework','this is some information about this homework task.  tried to add some info so this appears longer than usual.',3,0,'',0),(46,4,3,'','2005-10-17 07:20:34','test file','yest',0,0,'',0),(47,1,1,'radio_off.gif','2006-10-17 22:33:05','test gif','',0,336,'image/gif',0),(48,1,1,'arf-function.jpg','2006-10-17 22:36:32','jpg test','',0,9202,'image/jpeg',0),(49,1,1,'ian-webb.jpg','2006-10-20 15:57:34','ian','',0,16874,'image/jpeg',0);
insert into `file`(`id`,`category`,`owner`,`realname`,`submitted`,`description`,`comment`,`type`,`file_size`,`mime_type`,`deleted`) values (50,1,1,'Greener_on_the_other_side__by_Simple_pop.jpg','2006-10-20 15:59:31','back','',0,748603,'image/jpeg',0);

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

insert into `forum`(`id`,`owner`,`date`,`heading`,`message`,`parent`,`cid`,`deleted`,`thread`) values (1,1,'2004-01-23 12:04:09','test','test',0,4,0,1),(2,1,'2004-03-17 14:22:00','test','test',0,4,0,2),(3,1,'2004-03-17 14:39:40','Re: test','test123',2,4,0,2),(4,1,'2004-03-17 15:24:45','test1234','tset',0,4,0,4),(5,1,'2004-06-09 18:35:36','Re: test','hello more testing',2,4,0,2),(6,1,'2004-06-09 18:39:42','Re: Re: test','tsfsdfsd',3,4,0,2),(7,1,'2005-07-21 03:10:45','Re: Re: test','my test',6,4,0,2),(8,1,'2005-07-21 04:04:52','Re: test','more depth',7,4,0,2);

/*Table structure for table `groups` */

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL auto_increment,
  `name` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `groups` */

insert into `groups`(`id`,`name`) values (1,'Administration'),(2,'Teachers');

/*Table structure for table `homepage_colours` */

DROP TABLE IF EXISTS `homepage_colours`;

CREATE TABLE `homepage_colours` (
  `id` smallint(3) unsigned NOT NULL auto_increment,
  `name` char(15) NOT NULL default '0',
  `hex` char(6) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `homepage_colours` */

insert into `homepage_colours`(`id`,`name`,`hex`) values (1,'MGS Dark Blue','003399'),(2,'Light Blue','0066CC'),(3,'Grey','CCCCCC'),(4,'Black','000000'),(5,'White','FFFFFF'),(6,'Pale Yellow','FFFFDF'),(7,'Scarlet','CC0000'),(8,'Lime','00FF00'),(9,'Old Gold','CC9900'),(10,'Magenta','FF00FF'),(11,'Red','FF0000'),(12,'Dark Blue','000099'),(13,'Green','008000'),(14,'Yellow','FFFF00');

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

insert into `homepage_comp_location`(`cid`,`position`,`component`,`size`) values (3,1,8,0),(3,2,19,5),(3,3,2,5),(3,4,1,0),(3,5,1,0),(3,6,12,7),(3,7,11,7),(3,8,9,0),(3,9,10,0),(3,10,1,0),(4,1,8,0),(4,2,1,5),(4,3,3,5),(4,4,17,3),(4,5,2,0),(4,6,11,7),(4,7,22,7),(4,8,5,0),(4,9,20,0),(4,10,10,0),(5,1,8,0),(5,2,19,5),(5,3,2,5),(5,4,1,0),(5,5,1,0),(5,6,12,7),(5,7,11,7),(5,8,9,0),(5,9,10,0),(5,10,1,0),(7,1,8,0),(7,2,3,5),(7,3,2,5),(7,4,23,0),(7,5,1,0),(7,6,4,7),(7,7,10,7),(7,8,20,0),(7,9,1,0),(7,10,1,0),(11,1,8,0),(11,2,3,5),(11,3,2,5),(11,4,1,0),(11,5,1,0),(11,6,4,7),(11,7,10,7),(11,8,20,0),(11,9,4,0),(11,10,10,0),(12,1,8,0),(12,2,19,5),(12,3,2,5),(12,4,1,0),(12,5,1,0),(12,6,12,7),(12,7,11,7),(12,8,9,0),(12,9,10,0),(12,10,1,0),(13,1,8,0),(13,2,19,5),(13,3,2,5),(13,4,1,0),(13,5,1,0),(13,6,12,7),(13,7,11,7),(13,8,9,0),(13,9,10,0),(13,10,1,0),(15,1,8,0),(15,2,19,5),(15,3,2,5),(15,4,1,0),(15,5,1,0),(15,6,12,7),(15,7,11,7),(15,8,9,0),(15,9,10,0),(15,10,1,0),(18,1,8,0),(18,2,19,5),(18,3,2,5),(18,4,1,0),(18,5,1,0),(18,6,12,7),(18,7,11,7),(18,8,9,0),(18,9,10,0),(18,10,5,0),(19,1,8,0),(19,2,19,5),(19,3,2,5),(19,4,1,0),(19,5,1,0),(19,6,12,7),(19,7,11,7),(19,8,9,0),(19,9,10,0),(19,10,1,0),(31,1,8,0),(31,2,16,5),(31,3,19,5),(31,4,1,0),(31,5,1,0),(31,6,5,7),(31,7,14,7),(31,8,22,0),(31,9,12,0),(31,10,7,0),(33,1,8,0),(33,2,3,5),(33,3,2,5),(33,4,1,0),(33,5,1,0),(33,6,4,7),(33,7,10,7),(33,8,1,0),(33,9,1,0),(33,10,1,0),(34,1,8,0),(34,2,3,5),(34,3,2,5),(34,4,1,0);
insert into `homepage_comp_location`(`cid`,`position`,`component`,`size`) values (34,5,1,0),(34,6,4,7),(34,7,10,7),(34,8,1,0),(34,9,1,0),(34,10,1,0),(35,1,8,0),(35,2,3,5),(35,3,2,5),(35,4,1,0),(35,5,1,0),(35,6,4,7),(35,7,10,7),(35,8,1,0),(35,9,1,0);

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

insert into `homepage_config`(`cid`,`template`,`large_head_fontcolour`,`large_head_bgcolour`,`large_text_fontcolour`,`large_text_bgcolour`,`small_head_fontcolour`,`small_head_bgcolour`,`small_text_fontcolour`,`small_text_bgcolour`) values (3,1,5,1,4,5,5,2,4,6),(4,1,15,1,10,6,5,10,4,6),(5,1,5,1,4,5,5,2,4,6),(7,1,5,1,4,5,5,2,4,3),(11,1,5,1,4,5,5,2,4,3),(12,1,5,1,4,5,5,2,4,6),(13,1,5,1,4,5,5,2,4,6),(15,1,5,1,4,5,5,2,4,6),(18,1,5,1,4,5,5,2,4,6),(19,1,5,1,4,5,5,2,4,6),(31,1,12,9,4,5,5,10,6,7),(33,1,5,1,4,5,5,2,4,3),(34,1,5,1,4,5,5,2,4,3);

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

insert into `homepage_log`(`datetime`,`user`,`cid`,`hitcount`) values ('2004-02-10 15:11:01',1,3,3),('2006-12-14 12:48:01',1,4,539),('2004-01-23 12:02:19',1,5,0),('2006-10-17 22:32:19',1,7,7),('2005-06-14 20:23:57',1,11,2),('2006-08-06 16:04:36',1,13,0),('2006-08-06 16:04:33',1,19,1),('2005-07-20 00:18:05',1,31,23),('2006-07-07 17:18:37',1,34,1),('2006-06-16 17:04:25',1,35,1),('2004-01-05 17:08:22',2,5,9),('2005-10-17 07:21:03',3,4,27),('2004-01-23 15:28:30',3,5,2),('2005-03-30 19:46:18',4,4,2),('2004-09-08 15:28:46',5,3,4),('2004-09-08 13:35:01',5,4,1),('2004-09-08 15:32:17',5,5,14),('2004-09-08 13:37:11',5,7,1),('2005-06-08 02:43:44',5,11,6),('2004-09-08 13:45:22',5,14,0),('2004-09-23 16:33:47',5,18,7),('2005-06-09 05:29:18',21,3,0),('2006-05-21 15:01:57',23,5,1),('2006-06-27 17:25:15',23,7,2),('2006-06-30 15:49:28',23,11,13),('2006-07-04 15:00:58',23,12,21),('2006-06-27 17:25:00',23,13,2),('2006-06-30 15:47:05',23,15,0),('2006-05-21 16:40:42',23,33,6),('2006-05-21 16:42:16',23,34,5),('2006-05-21 17:43:16',23,35,9),('2006-08-18 13:59:51',24,4,1),('2006-08-18 13:59:10',24,7,4);

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

/*Table structure for table `house` */

DROP TABLE IF EXISTS `house`;

CREATE TABLE `house` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `house` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `house` */

insert into `house`(`id`,`house`) values (1,'Bromby'),(2,'Bruce'),(3,'Deakin'),(4,'Hone'),(5,'Miller'),(6,'Morris'),(7,'Perry'),(8,'Ross'),(9,'Rusden'),(10,'School'),(11,'Witherby'),(12,'Caffin'),(13,'Cain'),(14,'Cuming'),(15,'Creese'),(16,'Austin'),(17,'Grey-Smith'),(18,'Millear');

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

insert into `link`(`id`,`category`,`owner`,`url`,`submitted`,`description`,`comment`) values (1,1,1,'http://www.google.com.au','2003-12-30 02:15:12','Google Test','My link to google for your searching pleasure'),(2,5,2,'http://www.google.com.au','2004-01-02 17:05:19','google',''),(3,2,1,'http://www.cnet.com','2004-01-08 19:50:51','test',''),(4,4,1,'http://www.cnet.com','2004-01-23 14:13:02','Cnet Link','fgdfgdfgdgdf'),(5,4,1,'http://wenonaportal.wenona.nsw.edu.au/redir.php?url=http://intranet.wenona.nsw.edu.au/subjects/english/index.asp','2005-06-06 00:34:17','English',''),(6,4,3,'http://www.cnet.com','2005-10-17 07:20:47','cnet',''),(7,4,3,'http://www.google.com','2005-10-17 07:21:03','google','gosdfdsf'),(8,35,23,'http://www.google.com','2006-05-21 17:34:45','google',''),(9,33,23,'http://www.google.com','2006-05-21 17:35:04','headmaster link','');

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

insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (1,'jleckie','2003-12-30 01:29:02','10.1.0.2',1,NULL),(2,'jleckie','2003-12-30 01:51:02','10.1.0.2',1,NULL),(3,'jleckie','2003-12-30 02:17:48','10.1.0.2',1,NULL),(4,'cc','2003-12-30 02:20:22','10.1.0.2',1,NULL),(5,'jleckie','2003-12-30 11:49:39','10.1.0.2',1,NULL),(6,'jtreweek','2004-01-02 14:01:54','203.25.82.58',0,NULL),(7,'jleckie','2004-01-02 14:01:59','203.25.82.58',0,NULL),(8,'jleckie','2004-01-02 14:10:58','10.1.0.2',1,NULL),(9,'jleckie','2004-01-02 16:37:53','10.1.0.2',1,NULL),(10,'cc','2004-01-02 16:38:07','10.1.0.2',1,NULL),(11,'cc','2004-01-02 16:49:55','210.50.217.236',1,NULL),(12,'test','2004-01-03 16:30:57','10.1.0.2',0,NULL),(13,'testuser','2004-01-03 16:31:03','10.1.0.2',0,NULL),(14,'testuser','2004-01-03 16:31:11','10.1.0.2',1,NULL),(15,'jleckie','2004-01-04 21:52:09','10.1.0.2',1,NULL),(16,'cc','2004-01-04 21:52:24','10.1.0.2',1,NULL),(17,'cc','2004-01-04 22:32:16','10.1.0.2',1,NULL),(18,'cc','2004-01-04 22:33:33','10.1.0.2',1,NULL),(19,'cc','2004-01-04 22:42:01','10.1.0.2',1,NULL),(20,'jleckie','2004-01-04 22:42:36','10.1.0.2',1,NULL),(21,'cc','2004-01-05 17:08:03','203.25.82.58',1,NULL),(22,'cc','2004-01-05 17:13:04','203.25.82.58',1,NULL),(23,'cc','2004-01-07 20:11:57','210.50.36.240',1,NULL),(24,'cc','2004-01-07 20:15:01','210.50.36.240',1,NULL),(25,'jleckie','2004-01-07 23:40:31','10.1.0.2',0,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (26,'jleckie','2004-01-07 23:40:37','10.1.0.2',1,NULL),(27,'jleckie','2004-01-08 19:48:51','10.1.0.2',1,NULL),(28,'cc','2004-01-11 18:38:35','211.26.234.95',1,NULL),(29,'cc','2004-01-11 19:01:05','10.1.0.2',1,NULL),(30,'jleckie','2004-01-11 19:02:04','10.1.0.2',1,NULL),(31,'jleckie','2004-01-12 12:31:36','203.25.82.58',1,NULL),(32,'jleckie','2004-01-12 12:33:03','203.25.82.58',1,NULL),(33,'jleckie','2004-01-12 19:10:24','10.1.0.2',1,NULL),(34,'cc','2004-01-12 19:11:10','10.1.0.2',1,NULL),(35,'cc','2004-01-12 19:23:50','210.50.216.166',1,NULL),(36,'cc','2004-01-12 19:40:17','210.50.216.166',1,NULL),(37,'cc','2004-01-12 19:52:43','210.50.216.166',1,NULL),(38,'jleckie','2004-01-12 21:19:32','10.1.0.2',0,NULL),(39,'jleckie','2004-01-12 21:19:36','10.1.0.2',1,NULL),(40,'jleckie','2004-01-12 21:53:00','10.1.0.2',0,NULL),(41,'jleckie','2004-01-12 21:53:04','10.1.0.2',1,NULL),(42,'jleckie','2004-01-12 22:00:31','10.1.0.2',1,NULL),(43,'jleckie','2004-01-12 22:20:50','10.1.0.2',1,NULL),(44,'jleckie','2004-01-12 22:21:13','10.1.0.2',1,NULL),(45,'jleckie','2004-01-13 13:33:54','10.1.0.2',1,NULL),(46,'jleckie','2004-01-13 14:47:25','10.1.0.2',1,NULL),(47,'jleckie','2004-01-13 15:15:53','10.1.0.2',1,NULL),(48,'jleckie','2004-01-13 15:36:28','10.1.0.2',1,NULL),(49,'jleckie','2004-01-13 15:36:40','10.1.0.2',1,NULL),(50,'testuser','2004-01-13 15:37:45','10.1.0.2',1,NULL),(51,'jleckie','2004-01-13 15:38:39','10.1.0.2',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (52,'jleckie','2004-01-13 16:13:40','10.1.0.2',1,NULL),(53,'jleckie','2004-01-13 16:14:49','10.1.0.2',1,NULL),(54,'testuser','2004-01-13 16:17:23','10.1.0.2',1,NULL),(55,'testuser','2004-01-13 20:03:17','202.93.188.7',1,NULL),(56,'testuser','2004-01-14 09:17:08','202.93.163.1',1,NULL),(57,'jleckie','2004-01-15 20:54:33','10.1.0.2',1,NULL),(58,'jleckie','2004-01-16 12:15:50','10.1.0.2',1,NULL),(59,'jleckie','2004-01-16 12:35:09','10.1.0.2',1,NULL),(60,'cc','2004-01-16 21:31:17','210.50.248.146',1,NULL),(61,'jleckie','2004-01-22 00:38:34','10.1.0.2',1,NULL),(62,'testuser','2004-01-22 00:41:02','10.1.0.2',0,NULL),(63,'testuser','2004-01-22 00:41:06','10.1.0.2',1,NULL),(64,'testuser','2004-01-22 00:50:41','10.1.0.2',1,NULL),(65,'student','2004-01-22 00:52:57','10.1.0.2',1,NULL),(66,'jleckie','2004-01-22 00:53:18','10.1.0.2',1,NULL),(67,'student','2004-01-22 00:54:15','10.1.0.2',1,NULL),(68,'jleckie','2004-01-23 11:26:28','10.1.0.2',1,NULL),(69,'jleckie','2004-01-23 12:02:34','10.1.0.2',1,NULL),(70,'jleckie','2004-01-23 12:21:31','10.1.0.2',1,NULL),(71,'jleckie','2004-01-23 14:12:25','10.1.0.2',1,NULL),(72,'testuser','2004-01-23 15:28:10','202.6.138.42',1,NULL),(73,'testuser','2004-01-23 15:29:45','202.6.138.42',1,NULL),(74,'jleckie','2004-01-23 15:57:43','10.1.0.2',1,NULL),(75,'cc','2004-01-24 15:50:41','211.26.201.225',1,NULL),(76,'cc','2004-01-24 16:03:49','210.50.87.44',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (77,'testuser','2004-01-24 17:02:50','10.1.0.2',1,NULL),(78,'jleckie','2004-01-24 17:16:34','10.1.0.2',1,NULL),(79,'testuser','2004-01-26 12:39:20','210.18.215.2',1,NULL),(80,'student','2004-01-26 12:43:13','210.18.215.2',1,NULL),(81,'cc','2004-01-26 14:54:01','211.26.244.98',1,NULL),(82,'jleckie','2004-01-26 15:34:00','10.1.0.2',1,NULL),(83,'cc','2004-01-26 16:48:57','210.50.88.77',1,NULL),(84,'cc','2004-01-26 17:34:05','210.50.88.77',1,NULL),(85,'jleckie','2004-01-26 17:36:10','10.1.0.2',1,NULL),(86,'jleckie','2004-01-27 14:15:11','203.25.82.58',1,NULL),(87,'jleckie','2004-01-27 14:18:41','203.25.82.58',1,NULL),(88,'cc','2004-01-27 19:24:23','210.50.221.78',1,NULL),(89,'jleckie','2004-01-27 21:30:23','10.1.0.2',1,NULL),(90,'jleckie','2004-01-27 21:49:53','10.1.0.2',1,NULL),(91,'jleckie','2004-01-27 22:46:22','10.1.0.2',1,NULL),(92,'jleckie','2004-01-27 22:56:22','10.1.0.2',1,NULL),(93,'jleckie','2004-01-27 22:58:33','10.1.0.2',1,NULL),(94,'jleckie','2004-02-01 21:01:36','10.1.0.2',1,NULL),(95,'123cc','2004-02-01 21:05:38','203.134.131.102',0,NULL),(96,'cc','2004-02-01 21:05:41','203.134.131.102',1,NULL),(97,'testuser','2004-02-03 09:45:27','210.11.188.20',1,NULL),(98,'cc','2004-02-04 09:56:27','220.240.119.229',1,NULL),(99,'jleckie','2004-02-07 02:51:26','10.1.0.2',0,NULL),(100,'jleckie','2004-02-07 02:51:30','10.1.0.2',1,NULL),(101,'cc','2004-02-08 13:20:14','203.134.132.207',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (102,'jleckie','2004-02-10 14:51:22','10.1.0.2',1,NULL),(103,'jleckie','2004-02-24 13:50:07','10.1.0.2',1,NULL),(104,'testuser','2004-02-24 19:06:03','10.1.0.2',0,NULL),(105,'jleckie','2004-02-24 19:08:49','10.1.0.2',1,NULL),(106,'jleckie','2004-02-25 11:12:13','203.25.82.58',0,NULL),(107,'jleckie','2004-02-25 11:12:17','203.25.82.58',1,NULL),(108,'jleckie','2004-02-25 14:21:23','203.25.82.58',1,NULL),(109,'jleckie','2004-03-12 15:41:28','10.1.0.2',1,NULL),(110,'jleckie','2004-03-13 16:32:40','10.1.0.2',1,NULL),(111,'student','2004-03-13 17:17:11','10.1.0.2',0,NULL),(112,'student','2004-03-13 17:17:47','10.1.0.2',1,NULL),(113,'jleckie','2004-03-13 17:26:30','10.1.0.2',0,NULL),(114,'jleckie','2004-03-13 17:26:34','10.1.0.2',1,NULL),(115,'jleckie','2004-03-13 19:21:02','10.1.0.2',1,NULL),(116,'jleckie','2004-03-14 22:43:52','10.1.0.2',0,NULL),(117,'jleckie','2004-03-14 22:43:55','10.1.0.2',1,NULL),(118,'jleckie','2004-03-15 16:35:49','10.1.0.2',0,NULL),(119,'jleckie','2004-03-15 16:35:52','10.1.0.2',1,NULL),(120,'jleckie','2004-03-15 20:15:48','10.1.0.2',1,NULL),(121,'jleckie','2004-03-15 20:16:17','10.1.0.2',1,NULL),(122,'teacher','2004-03-16 01:00:50','10.1.0.2',0,NULL),(123,'teacher','2004-03-16 01:00:54','10.1.0.2',0,NULL),(124,'testuser','2004-03-16 01:01:28','10.1.0.2',1,NULL),(125,'jleckie','2004-03-16 10:48:27','203.25.82.58',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (126,'testuser','2004-03-17 10:50:47','202.6.138.35',1,NULL),(127,'jleckie','2004-03-17 13:20:26','10.1.0.2',1,NULL),(128,'jleckie','2004-03-17 13:58:24','10.1.0.2',1,NULL),(129,'jleckie','2004-03-17 14:20:31','10.1.0.2',1,NULL),(130,'jaleckie','2004-03-22 11:35:15','203.25.82.58',0,NULL),(131,'jleckie','2004-03-22 11:35:21','203.25.82.58',1,NULL),(132,'jleckie','2004-03-22 15:40:28','203.25.82.58',1,NULL),(133,'jleckie','2004-03-29 18:37:11','10.1.0.2',1,NULL),(134,'jleckie','2004-03-29 18:37:53','10.1.0.2',1,NULL),(135,'testuser','2004-03-29 18:38:04','10.1.0.2',1,NULL),(136,'jleckie','2004-04-12 16:46:50','10.1.0.2',1,NULL),(137,'jleckie','2004-04-12 17:59:51','10.1.0.2',1,NULL),(138,'jleckie','2004-04-12 18:14:03','10.1.0.2',1,NULL),(139,'jleckie','2004-04-12 18:17:46','10.1.0.2',1,NULL),(140,'jleckie','2004-04-12 19:05:12','10.1.0.2',1,NULL),(141,'jleckie','2004-04-12 19:06:44','10.1.0.2',1,NULL),(142,'jleckie','2004-04-12 19:08:21','10.1.0.2',1,NULL),(143,'jleckie','2004-04-12 19:10:22','10.1.0.2',1,NULL),(144,'jleckie','2004-04-12 19:14:13','10.1.0.2',1,NULL),(145,'jleckie','2004-04-12 19:16:29','10.1.0.2',1,NULL),(146,'testuser','2004-04-12 19:49:50','210.49.194.239',1,NULL),(147,'testuser','2004-04-12 19:51:31','210.49.194.239',1,NULL),(148,'testuser','2004-04-12 20:34:20','210.49.194.239',1,NULL),(149,'jleckie','2004-04-13 19:19:18','10.1.0.2',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (150,'jleckie','2004-04-13 19:20:22','10.1.0.2',1,NULL),(151,'jleckie','2004-04-13 19:24:54','10.1.0.2',1,NULL),(152,'jleckie','2004-04-13 19:31:44','10.1.0.2',1,NULL),(153,'jleckie','2004-04-13 19:51:13','10.1.0.2',1,NULL),(154,'jleckie','2004-04-13 19:51:29','10.1.0.2',1,NULL),(155,'jleckie','2004-04-13 19:55:30','10.1.0.2',1,NULL),(156,'jleckie','2004-04-13 19:58:06','10.1.0.2',1,NULL),(157,'jleckie','2004-04-13 19:59:08','10.1.0.2',1,NULL),(158,'jleckie','2004-04-13 22:18:40','10.1.0.2',1,NULL),(159,'jleckie','2004-04-20 16:27:50','10.1.0.2',1,NULL),(160,'jaleckie','2004-04-20 17:00:16','10.1.0.2',0,NULL),(161,'jleckie','2004-04-20 17:00:19','10.1.0.2',1,NULL),(162,'jleckie','2004-04-27 15:07:25','10.1.0.2',1,NULL),(163,'testuser','2004-05-04 23:18:21','10.1.0.2',0,NULL),(164,'test','2004-05-04 23:18:33','10.1.0.2',0,NULL),(165,'testuser','2004-05-04 23:19:44','10.1.0.2',1,NULL),(166,'jaleckie','2004-05-07 11:38:06','203.25.82.58',0,NULL),(167,'jleckie','2004-05-07 11:38:13','203.25.82.58',1,NULL),(168,'jleckie','2004-06-09 18:32:55','203.54.40.244',1,NULL),(169,'jleckie','2004-06-09 18:33:23','203.54.40.244',1,NULL),(170,'jleckie','2004-06-21 07:03:11','192.168.1.2',1,NULL),(171,'jleckie','2004-06-21 07:11:18','192.168.1.1',1,NULL),(172,'eleckie','2004-06-21 07:11:26','192.168.1.1',1,NULL),(173,'jleckie','2004-06-21 08:20:29','69.31.64.2',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (174,'eleckie','2004-06-28 11:42:54','144.134.155.61',1,NULL),(175,'jleckie','2004-06-28 16:09:49','192.168.1.1',1,NULL),(176,'testuser','2004-06-28 17:36:05','202.168.100.82',1,NULL),(177,'student','2004-06-28 17:39:05','202.168.100.82',1,NULL),(178,'jleckie','2004-06-30 12:47:30','192.168.1.2',1,NULL),(179,'jleckie','2004-06-30 15:29:36','192.168.1.2',1,NULL),(180,'jleckie','2004-07-21 10:49:43','192.168.1.2',1,NULL),(181,'jleckie','2004-08-04 12:15:58','138.217.60.208',1,NULL),(182,'eleckie','2004-08-08 14:08:30','144.134.155.116',1,NULL),(183,'jleckie','2004-08-08 15:18:46','138.217.60.208',1,NULL),(184,'eleckie','2004-09-08 11:15:45','203.54.22.221',0,NULL),(185,'test','2004-09-08 11:16:06','203.54.22.221',0,NULL),(186,'jleckie','2004-09-08 11:49:52','203.52.235.2',1,NULL),(187,'eleckie','2004-09-08 12:08:52','203.54.22.221',1,NULL),(188,'eleckie','2004-09-08 13:26:13','203.54.22.221',1,NULL),(189,'eleckie','2004-09-08 15:28:17','203.54.40.30',1,NULL),(190,'eleckie','2004-09-08 17:03:53','203.54.40.30',0,NULL),(191,'eleckie','2004-09-08 17:04:07','203.54.40.30',1,NULL),(192,'eleckie','2004-09-08 17:21:06','203.54.40.30',1,NULL),(193,'eleckie','2004-09-09 12:38:49','203.54.22.49',1,NULL),(194,'test','2004-09-13 15:06:00','203.54.24.192',0,NULL),(195,'testuser','2004-09-13 15:06:54','203.54.24.192',1,NULL),(196,'eleckie','2004-09-13 16:27:20','203.54.24.192',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (197,'eleckie','2004-09-13 17:01:47','203.54.24.192',1,NULL),(198,'eleckie','2004-09-23 16:01:27','144.134.155.218',1,NULL),(199,'eleckie','2004-09-23 17:05:20','144.134.155.218',1,NULL),(200,'eleckie','2004-09-23 17:48:56','144.134.155.218',1,NULL),(201,'student','2004-10-28 12:34:04','144.133.83.126',1,NULL),(202,'student','2004-10-28 12:36:15','144.138.194.141',1,NULL),(203,'jleckie','2004-10-28 15:50:41','144.133.83.126',1,NULL),(204,'jleckie','2004-10-28 16:59:09','144.133.83.126',1,NULL),(205,'jleckie','2004-11-03 15:48:24','144.133.83.126',1,NULL),(206,'jleckie','2004-11-03 16:32:40','144.133.83.126',1,NULL),(207,'jleckie','2004-11-03 16:37:58','144.133.83.126',1,NULL),(208,'jleckie','2004-11-03 19:39:54','144.133.83.126',1,NULL),(209,'jleckie','2004-11-03 21:51:30','144.133.83.126',1,NULL),(210,'jleckie','2004-11-04 01:15:21','144.133.83.126',1,NULL),(211,'jleckie','2004-11-04 12:01:05','203.25.82.58',1,NULL),(212,'jleckie','2004-11-15 19:27:49','144.133.83.126',1,NULL),(213,'jleckie','2004-11-15 23:46:42','144.133.83.126',1,NULL),(214,'jleckie','2004-11-16 01:41:05','144.133.83.126',1,NULL),(215,'jleckie','2004-11-16 13:54:36','203.25.82.58',0,NULL),(216,'jleckie','2004-11-16 13:54:42','203.25.82.58',1,NULL),(217,'jleckie','2004-11-25 11:27:08','144.133.83.126',1,NULL),(218,'jleckie','2004-11-25 14:04:45','203.25.82.58',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (219,'jleckie','2004-12-15 10:55:45','144.133.83.126',0,NULL),(220,'jleckie','2004-12-15 10:55:52','144.133.83.126',1,NULL),(221,'jleckie','2004-12-22 15:00:01','144.133.83.126',1,NULL),(222,'jleckie','2004-12-22 15:35:42','144.133.83.126',1,NULL),(223,'jleckie','2005-02-08 22:43:32','144.133.81.70',0,NULL),(224,'jleckie','2005-02-08 22:43:41','144.133.81.70',1,NULL),(225,'jaleckie','2005-02-09 02:10:39','144.133.81.70',0,NULL),(226,'jleckie','2005-02-09 02:10:46','144.133.81.70',1,NULL),(227,'jleckie','2005-02-09 05:02:42','144.133.81.70',1,NULL),(228,'jleckie','2005-02-09 05:43:53','144.133.81.70',1,NULL),(229,'jleckie','2005-02-09 07:36:05','144.133.81.70',1,NULL),(230,'jleckie','2005-02-09 08:16:26','144.133.81.70',1,NULL),(231,'jleckie','2005-02-09 21:32:08','144.133.81.70',1,NULL),(232,'jleckie','2005-02-09 23:32:10','144.133.81.70',1,NULL),(233,'jleckie','2005-02-10 00:40:38','144.133.81.70',1,NULL),(234,'jleckie','2005-02-10 01:19:16','144.133.81.70',1,NULL),(235,'jleckie','2005-02-10 03:47:52','144.133.81.70',1,NULL),(236,'jleckie','2005-02-10 04:34:58','144.133.81.70',1,NULL),(237,'jleckie','2005-02-10 05:11:02','144.133.81.70',1,NULL),(238,'jleckie','2005-02-10 18:36:40','144.133.81.70',1,NULL),(239,'nelo','2005-02-10 19:01:30','144.133.81.70',1,NULL),(240,'nelo','2005-02-10 19:02:43','144.133.81.70',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (241,'jleckie','2005-02-27 22:54:16','144.133.81.70',1,NULL),(242,'jleckie','2005-03-07 01:06:58','144.133.81.70',1,NULL),(243,'jleckie','2005-03-09 19:43:02','144.133.81.70',1,NULL),(244,'student','2005-03-09 20:06:14','144.133.81.70',1,NULL),(245,'jaleckie','2005-03-29 21:10:43','144.133.83.208',0,NULL),(246,'jaleckie','2005-03-29 21:10:47','144.133.83.208',0,NULL),(247,'jaleckie','2005-03-29 21:10:52','144.133.83.208',0,NULL),(248,'jleckie','2005-03-29 21:12:53','144.133.83.208',1,NULL),(249,'jleckie','2005-03-29 22:24:52','144.133.83.208',1,NULL),(250,'jaleckie','2005-03-29 23:27:59','144.133.83.208',0,NULL),(251,'jleckie','2005-03-29 23:28:05','144.133.83.208',1,NULL),(252,'jleckie','2005-03-29 23:33:07','144.133.83.208',1,NULL),(253,'jleckie','2005-03-29 23:33:25','144.133.83.208',0,NULL),(254,'jleckie','2005-03-29 23:33:31','144.133.83.208',1,NULL),(255,'jleckie','2005-03-30 02:09:14','144.133.83.208',1,NULL),(256,'testuser','2005-03-30 02:44:34','220.244.224.174',1,NULL),(257,'testuser','2005-03-30 18:42:44','203.30.216.1',1,NULL),(258,'testuser','2005-03-30 19:40:51','203.30.216.1',1,NULL),(259,'student','2005-03-30 19:42:39','203.30.216.1',1,NULL),(260,'jleckie','2005-04-07 01:02:53','144.133.83.208',1,NULL),(261,'jleckie','2005-04-07 01:22:54','144.133.83.208',1,NULL),(262,'jleckie','2005-04-07 01:28:17','144.133.83.208',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (263,'jleckie','2005-04-12 21:46:27','144.133.83.208',1,NULL),(264,'jleckie','2005-04-13 22:07:01','144.133.83.208',1,NULL),(265,'jleckie','2005-04-14 00:05:24','144.133.83.208',1,NULL),(266,'jleckie','2005-06-05 20:53:29','144.133.81.69',1,NULL),(267,'jleckie','2005-06-05 21:33:39','144.133.81.69',1,NULL),(268,'jleckie','2005-06-05 23:10:34','144.133.81.69',1,NULL),(269,'jleckie','2005-06-06 00:26:03','144.133.81.69',1,NULL),(270,'jleckie','2005-06-07 04:18:07','144.133.81.69',1,NULL),(271,'eleckie','2005-06-07 04:18:34','144.133.81.69',1,NULL),(272,'jleckie','2005-06-07 06:04:17','144.133.81.69',1,NULL),(273,'eleckie','2005-06-07 06:04:35','144.133.81.69',1,NULL),(274,'jleckie','2005-06-07 08:11:16','144.133.81.69',1,NULL),(275,'jleckie','2005-06-07 10:28:50','144.133.81.69',1,NULL),(276,'jleckie','2005-06-07 19:57:21','144.133.81.69',1,NULL),(277,'jleckie','2005-06-07 20:34:06','144.133.81.69',1,NULL),(278,'jleckie','2005-06-07 21:42:56','144.133.81.69',1,NULL),(279,'jleckie','2005-06-08 01:34:02','144.133.81.69',1,NULL),(280,'cc','2005-06-08 02:11:52','144.133.81.69',1,NULL),(281,'sean','2005-06-08 02:38:12','144.133.81.69',1,NULL),(282,'jleckie','2005-06-08 02:43:07','144.133.81.69',1,NULL),(283,'eleckie','2005-06-08 02:43:32','144.133.81.69',1,NULL),(284,'sean','2005-06-08 03:03:47','144.133.81.69',1,NULL),(285,'sean','2005-06-08 03:06:11','144.133.81.69',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (286,'jleckie','2005-06-08 19:51:40','144.133.81.69',1,NULL),(287,'jleckie','2005-06-08 20:40:54','144.133.81.69',1,NULL),(288,'jleckie','2005-06-08 21:58:27','144.133.81.69',1,NULL),(289,'sean','2005-06-08 22:54:07','144.133.81.69',1,NULL),(290,'sean','2005-06-08 23:26:34','144.133.81.69',1,NULL),(291,'sean','2005-06-09 00:38:39','144.133.81.69',1,NULL),(292,'sean','2005-06-09 00:39:01','144.133.81.69',1,NULL),(293,'jleckie','2005-06-09 01:09:41','144.133.81.69',1,NULL),(294,'jleckie','2005-06-09 01:44:50','144.133.81.69',1,NULL),(295,'jleckie','2005-06-09 01:56:37','144.133.81.69',1,NULL),(296,'jleckie','2005-06-09 02:39:19','144.133.81.69',1,NULL),(297,'jleckie','2005-06-09 03:14:53','144.133.81.69',1,NULL),(298,'sean','2005-06-09 03:32:16','144.133.81.69',1,NULL),(299,'jleckie','2005-06-09 04:09:57','144.133.81.69',1,NULL),(300,'jleckie','2005-06-09 04:43:53','144.133.81.69',1,NULL),(301,'sean','2005-06-09 05:22:32','144.133.81.69',1,NULL),(302,'jleckie','2005-06-09 06:01:58','144.133.81.69',1,NULL),(303,'jleckie','2005-06-09 06:07:20','144.133.81.69',1,NULL),(304,'jleckie','2005-06-09 06:47:12','144.133.81.69',1,NULL),(305,'jleckie','2005-06-09 06:47:22','144.133.81.69',1,NULL),(306,'jleckie','2005-06-09 09:23:20','144.133.81.69',1,NULL),(307,'jleckie','2005-06-09 20:47:27','144.133.81.69',1,NULL),(308,'jleckie','2005-06-10 00:38:33','144.133.81.69',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (309,'jleckie','2005-06-10 01:16:32','144.133.81.69',1,NULL),(310,'jleckie','2005-06-10 02:30:04','144.133.81.69',1,NULL),(311,'jleckie','2005-06-13 19:26:08','203.25.82.46',1,NULL),(312,'jleckie','2005-06-14 09:16:59','144.133.81.69',1,NULL),(313,'jleckie','2005-06-14 19:08:34','144.133.81.69',1,NULL),(314,'jleckie','2005-06-14 19:25:30','144.133.81.69',0,NULL),(315,'jleckie','2005-06-14 19:25:43','144.133.81.69',1,NULL),(316,'jleckie','2005-06-14 19:41:29','144.133.81.69',1,NULL),(317,'jleckie','2005-06-14 19:58:30','144.133.81.69',1,NULL),(318,'jleckie','2005-06-14 21:55:41','144.133.81.69',1,NULL),(319,'jleckie','2005-06-14 23:07:37','144.133.81.69',1,NULL),(320,'jleckie','2005-06-14 23:28:18','144.133.81.69',1,NULL),(321,'jleckie','2005-06-15 02:09:45','144.133.81.69',1,NULL),(322,'jleckie','2005-06-15 02:10:30','144.133.81.69',1,NULL),(323,'jleckie','2005-06-15 02:35:45','144.133.81.69',1,NULL),(324,'jleckie','2005-06-15 02:41:18','144.133.81.69',1,NULL),(325,'jleckie','2005-06-15 02:57:04','144.133.81.69',1,NULL),(326,'jleckie','2005-06-15 03:16:31','144.133.81.69',1,NULL),(327,'jleckie','2005-06-15 04:05:02','144.133.81.69',1,NULL),(328,'jleckie','2005-06-16 00:18:29','144.133.81.69',0,NULL),(329,'jleckie','2005-06-16 00:18:33','144.133.81.69',1,NULL),(330,'jleckie','2005-06-16 00:20:21','144.133.81.69',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (331,'jleckie','2005-06-16 00:38:44','144.133.81.69',1,NULL),(332,'jleckie','2005-06-16 01:54:43','144.133.81.69',1,NULL),(333,'jleckie','2005-06-16 01:58:31','144.133.81.69',1,NULL),(334,'jleckie','2005-06-16 02:39:40','144.133.81.69',1,NULL),(335,'jleckie','2005-06-16 02:42:19','144.133.81.69',1,NULL),(336,'jleckie','2005-06-16 02:52:27','144.133.81.69',1,NULL),(337,'jleckie','2005-06-16 03:00:15','144.133.81.69',1,NULL),(338,'jleckie','2005-06-16 03:40:42','144.133.81.69',1,NULL),(339,'jleckie','2005-06-16 03:49:13','144.133.81.69',1,NULL),(340,'jleckie','2005-06-16 21:33:27','144.133.81.69',1,NULL),(341,'jleckie','2005-06-16 21:41:53','144.133.81.69',1,NULL),(342,'jleckie','2005-06-16 21:55:20','144.133.81.69',1,NULL),(343,'jleckie','2005-06-16 22:22:11','144.133.81.69',1,NULL),(344,'jleckie','2005-06-16 22:37:23','144.133.81.69',1,NULL),(345,'jleckie','2005-06-16 23:20:42','144.133.81.69',1,NULL),(346,'jleckie','2005-06-16 23:23:14','144.133.81.69',1,NULL),(347,'jleckie','2005-06-16 23:25:28','144.133.81.69',1,NULL),(348,'jleckie','2005-06-16 23:25:41','144.133.81.69',1,NULL),(349,'jleckie','2005-06-16 23:45:45','144.133.81.69',1,NULL),(350,'jleckie','2005-06-16 23:59:35','144.133.81.69',1,NULL),(351,'jleckie','2005-06-17 00:07:08','144.133.81.69',1,NULL),(352,'jleckie','2005-06-17 01:10:43','144.133.81.69',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (353,'jleckie','2005-06-20 00:05:56','144.133.81.69',1,NULL),(354,'jleckie','2005-06-20 00:43:46','144.133.81.69',1,NULL),(355,'jleckie','2005-06-20 02:00:04','144.133.81.69',1,NULL),(356,'jleckie','2005-06-21 00:21:50','203.25.82.46',1,NULL),(357,'jleckie','2005-06-21 21:47:29','144.133.81.69',1,NULL),(358,'jleckie','2005-06-21 22:49:11','144.133.81.69',1,NULL),(359,'jleckie','2005-06-22 00:29:50','144.133.81.69',1,NULL),(360,'jleckie','2005-06-22 01:19:54','144.133.81.69',1,NULL),(361,'jleckie','2005-06-22 01:54:35','144.133.81.69',1,NULL),(362,'jleckie','2005-06-22 03:56:29','144.133.81.69',1,NULL),(363,'jleckie','2005-06-23 01:51:56','144.133.81.69',1,NULL),(364,'jleckie','2005-06-23 20:16:22','144.133.81.69',1,NULL),(365,'jleckie','2005-06-23 20:26:18','144.133.81.69',1,NULL),(366,'jleckie','2005-06-23 20:36:17','144.133.81.69',1,NULL),(367,'jleckie','2005-06-24 02:33:38','144.133.81.69',1,NULL),(368,'jleckie','2005-06-26 22:36:17','144.133.81.69',1,NULL),(369,'jleckie','2005-06-26 23:34:09','144.133.81.69',1,NULL),(370,'jleckie','2005-06-27 08:59:38','144.133.81.69',0,NULL),(371,'jleckie','2005-06-27 08:59:43','144.133.81.69',1,NULL),(372,'jleckie','2005-06-28 10:05:51','144.133.81.69',1,NULL),(373,'jleckie','2005-06-29 08:10:19','144.133.81.69',1,NULL),(374,'jleckie','2005-06-29 08:45:15','144.133.81.69',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (375,'jleckie','2005-06-29 23:57:51','203.25.82.58',1,NULL),(376,'jleckie','2005-06-30 00:00:45','203.25.82.58',1,NULL),(377,'jleckie','2005-07-04 21:19:23','203.25.82.46',1,NULL),(378,'jleckie','2005-07-04 22:33:32','203.25.82.46',1,NULL),(379,'jleckie','2005-07-10 05:45:42','138.217.35.146',1,NULL),(380,'jleckie','2005-07-14 01:59:59','203.25.82.46',1,NULL),(381,'jleckie','2005-07-17 22:15:40','138.217.35.146',1,NULL),(382,'files','2005-07-17 22:35:00','138.217.27.47',0,NULL),(383,'jleckie','2005-07-17 22:39:42','138.217.27.47',1,NULL),(384,'jleckie','2005-07-17 23:45:21','138.217.35.146',1,NULL),(385,'jleckie','2005-07-18 00:59:42','138.217.35.146',1,NULL),(386,'jleckie','2005-07-18 02:46:40','138.217.35.146',1,NULL),(387,'jleckie','2005-07-18 03:26:56','138.217.35.146',1,NULL),(388,'jleckie','2005-07-19 19:33:26','138.217.35.146',1,NULL),(389,'jleckie','2005-07-19 19:37:24','138.217.35.146',1,NULL),(390,'jleckie','2005-07-19 20:10:57','138.217.35.146',1,NULL),(391,'jleckie','2005-07-19 20:35:14','138.217.35.146',1,NULL),(392,'jleckie','2005-07-19 21:01:59','138.217.35.146',1,NULL),(393,'jleckie','2005-07-19 21:35:11','138.217.35.146',1,NULL),(394,'jleckie','2005-07-19 21:35:45','138.217.35.146',1,NULL),(395,'jleckie','2005-07-19 22:13:37','138.217.35.146',1,NULL),(396,'jleckie','2005-07-19 22:17:07','138.217.35.146',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (397,'jleckie','2005-07-19 22:22:26','138.217.35.146',1,NULL),(398,'jleckie','2005-07-19 22:28:58','138.217.35.146',1,NULL),(399,'jleckie','2005-07-19 23:10:17','138.217.35.146',1,NULL),(400,'jleckie','2005-07-19 23:45:18','138.217.35.146',0,NULL),(401,'jleckie','2005-07-19 23:45:28','138.217.35.146',1,NULL),(402,'jleckie','2005-07-20 00:02:05','138.217.35.146',1,NULL),(403,'jleckie','2005-07-20 00:08:51','138.217.35.146',1,NULL),(404,'jleckie','2005-07-20 00:54:36','138.217.35.146',1,NULL),(405,'jleckie','2005-07-20 01:22:06','138.217.35.146',1,NULL),(406,'jleckie','2005-07-20 02:25:33','138.217.35.146',1,NULL),(407,'jleckie','2005-07-20 02:26:16','138.217.35.146',1,NULL),(408,'jleckie','2005-07-20 03:18:38','138.217.35.146',1,NULL),(409,'jleckie','2005-07-20 22:25:56','138.217.35.146',1,NULL),(410,'jleckie','2005-07-20 23:13:05','138.217.35.146',1,NULL),(411,'jleckie','2005-07-21 01:40:51','138.217.35.146',1,NULL),(412,'jleckie','2005-07-21 01:41:44','138.217.27.47',1,NULL),(413,'jleckie','2005-07-21 03:46:29','138.217.35.146',1,NULL),(414,'jleckie','2005-07-21 04:48:38','138.217.35.146',1,NULL),(415,'jleckie','2005-07-24 20:44:02','138.217.35.146',1,NULL),(416,'jleckie','2005-07-26 21:21:21','138.217.35.146',1,NULL),(417,'jleckie','2005-07-26 23:35:42','138.217.35.146',1,NULL),(418,'jleckie','2005-07-27 00:35:07','138.217.35.146',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (419,'jleckie','2005-07-27 02:43:18','138.217.35.146',1,NULL),(420,'jleckie','2005-07-27 03:24:31','138.217.35.146',1,NULL),(421,'jleckie','2005-07-27 19:29:44','138.217.35.146',1,NULL),(422,'jleckie','2005-07-27 19:47:46','138.217.35.146',1,NULL),(423,'jleckie','2005-07-28 02:53:09','138.217.35.146',1,NULL),(424,'jleckie','2005-07-28 03:18:13','138.217.35.146',1,NULL),(425,'jleckie','2005-07-28 03:36:54','138.217.35.146',1,NULL),(426,'jleckie','2005-08-24 23:23:40','138.217.19.195',1,NULL),(427,'jleckie','2005-09-14 00:01:47','138.217.35.146',1,NULL),(428,'jleckie','2005-09-14 01:43:56','138.217.35.146',1,NULL),(429,'testuser','2005-09-14 01:48:06','138.217.35.146',1,NULL),(430,'jleckie','2005-09-14 04:30:13','138.217.35.146',1,NULL),(431,'testuser','2005-09-14 04:30:34','138.217.35.146',1,NULL),(432,'testuser','2005-09-14 20:03:18','138.217.35.146',1,NULL),(433,'testuser','2005-09-14 20:52:34','138.217.35.146',1,NULL),(434,'jleckie','2005-10-06 01:40:53','138.217.35.146',1,NULL),(435,'jaleckie','2005-10-09 00:43:15','131.172.42.15',0,NULL),(436,'jleckie','2005-10-09 00:43:32','131.172.42.15',1,NULL),(437,'jleckie','2005-10-17 01:56:46','138.217.35.146',1,NULL),(438,'jleckie','2005-10-17 02:58:34','138.217.35.146',1,NULL),(439,'jleckie','2005-10-17 05:31:06','138.217.35.146',1,NULL),(440,'jleckie','2005-10-17 06:24:59','138.217.35.146',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (441,'testuser','2005-10-17 06:54:36','138.217.35.146',1,NULL),(442,'jleckie','2005-10-17 06:56:00','138.217.35.146',1,NULL),(443,'jleckie','2005-10-17 07:14:11','138.217.35.146',1,NULL),(444,'testuser','2005-10-17 07:20:18','138.217.35.146',1,NULL),(445,'jaleckie','2005-12-07 23:55:08','138.217.35.146',0,NULL),(446,'jleckie','2005-12-07 23:55:16','138.217.35.146',1,NULL),(447,'jaleckie','2005-12-08 01:36:02','138.217.35.146',0,NULL),(448,'jleckie','2005-12-08 01:36:07','138.217.35.146',1,NULL),(449,'jleckie','2005-12-08 01:39:01','138.217.35.146',1,NULL),(450,'jaleckie','2005-12-09 00:35:17','211.28.255.114',0,NULL),(451,'jleckie','2005-12-09 00:35:22','211.28.255.114',1,NULL),(452,'jleckie','2005-12-09 00:35:41','211.28.255.114',1,NULL),(453,'jleckie','2006-02-12 21:10:35','138.217.35.146',1,NULL),(454,'jaleckie','2006-03-09 18:12:12','138.217.17.13',0,NULL),(455,'jleckie','2006-03-09 18:12:18','138.217.17.13',1,NULL),(456,'jaleckie','2006-03-22 23:20:57','138.217.17.13',0,NULL),(457,'jleckie','2006-03-22 23:21:02','138.217.17.13',1,NULL),(458,'jleckie','2006-05-07 21:26:47','150.101.154.169',1,NULL),(459,'jleckie','2006-05-21 14:27:40','192.168.1.69',0,NULL),(460,'lcawood','2006-05-21 14:36:50','192.168.1.72',0,NULL),(461,'lcawood','2006-05-21 14:36:57','192.168.1.72',0,NULL),(462,'jleckie','2006-05-21 14:37:02','192.168.1.69',0,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (463,'jleckie','2006-05-21 14:37:28','192.168.1.69',0,NULL),(464,'jleckie','2006-05-21 14:37:32','192.168.1.69',0,NULL),(465,'lcawood','2006-05-21 14:38:37','192.168.1.72',0,NULL),(466,'lcawood','2006-05-21 14:41:30','192.168.1.72',0,NULL),(467,'lcawood','2006-05-21 14:42:57','192.168.1.72',0,NULL),(468,'jleckie','2006-05-21 14:43:51','192.168.1.69',0,NULL),(469,'jleckie','2006-05-21 14:43:56','192.168.1.69',0,NULL),(470,'lcawood','2006-05-21 14:44:27','192.168.1.72',0,NULL),(471,'lcawood','2006-05-21 14:44:31','192.168.1.72',0,NULL),(472,'lwcawood','2006-05-21 14:44:38','192.168.1.72',0,NULL),(473,'jleckie','2006-05-21 14:45:01','192.168.1.69',1,NULL),(474,'lcawood','2006-05-21 14:45:37','192.168.1.72',0,NULL),(475,'lcawood','2006-05-21 14:45:41','192.168.1.72',0,NULL),(476,'lcawood','2006-05-21 14:46:20','192.168.1.72',1,NULL),(477,'lcawood','2006-05-21 14:52:37','192.168.1.72',1,NULL),(478,'lcawood','2006-05-21 14:53:48','192.168.1.72',1,NULL),(479,'lcawood','2006-05-21 14:53:50','192.168.1.72',1,NULL),(480,'lcawood','2006-05-21 14:53:52','192.168.1.72',1,NULL),(481,'lcawood','2006-05-21 14:53:52','192.168.1.72',1,NULL),(482,'lcawood','2006-05-21 14:53:53','192.168.1.72',1,NULL),(483,'lcawood','2006-05-21 14:53:53','192.168.1.72',1,NULL),(484,'lcawood','2006-05-21 14:53:53','192.168.1.72',1,NULL),(485,'lcawood','2006-05-21 14:53:55','192.168.1.72',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (486,'lcawood','2006-05-21 15:02:43','192.168.1.72',1,NULL),(487,'jaleckie','2006-05-21 16:35:27','192.168.1.67',0,NULL),(488,'jaleckie','2006-05-21 16:35:31','192.168.1.67',0,NULL),(489,'jleckie','2006-05-21 16:35:36','192.168.1.67',1,NULL),(490,'lcawood','2006-05-21 17:31:41','192.168.1.72',1,NULL),(491,'jaleckie','2006-05-21 18:55:56','192.168.1.69',0,NULL),(492,'jaleckie','2006-05-21 18:56:01','192.168.1.69',0,NULL),(493,'lcawood','2006-05-23 10:51:37','192.168.1.72',1,NULL),(494,'lcawood','2006-05-23 13:56:20','192.168.1.72',1,NULL),(495,'lcawood','2006-06-13 17:13:39','192.168.1.68',1,NULL),(496,'lcawood','2006-06-13 17:20:13','192.168.1.68',1,NULL),(497,'lcawood','2006-06-13 17:55:26','192.168.1.68',1,NULL),(498,'lcawood','2006-06-13 17:58:23','192.168.1.68',1,NULL),(499,'lcawood','2006-06-15 10:43:25','192.168.1.68',1,NULL),(500,'lcawood','2006-06-15 15:51:34','192.168.1.68',1,NULL),(501,'lcawood','2006-06-16 11:29:21','192.168.1.68',1,NULL),(502,'student','2006-06-16 11:39:13','192.168.1.68',0,NULL),(503,'lcawood','2006-06-16 11:39:28','192.168.1.68',1,NULL),(504,'studo','2006-06-16 11:39:32','192.168.1.68',1,NULL),(505,'lcawood','2006-06-16 11:40:26','192.168.1.68',1,NULL),(506,'teacher','2006-06-16 11:40:34','192.168.1.68',1,NULL),(507,'lcawood','2006-06-16 11:41:09','192.168.1.68',1,NULL),(508,'student','2006-06-16 11:42:56','192.168.1.68',0,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (509,'lcawood','2006-06-16 11:42:59','192.168.1.68',1,NULL),(510,'studo','2006-06-16 11:43:13','192.168.1.68',1,NULL),(511,'lcawood','2006-06-16 11:43:40','192.168.1.68',1,NULL),(512,'lcawood','2006-06-16 12:57:41','192.168.1.68',1,NULL),(513,'studo','2006-06-16 13:05:50','192.168.1.68',1,NULL),(514,'lcawood','2006-06-16 13:06:14','192.168.1.68',1,NULL),(515,'teacher','2006-06-16 13:06:28','192.168.1.68',1,NULL),(516,'lcawood','2006-06-16 13:09:21','192.168.1.68',1,NULL),(517,'teacher','2006-06-16 13:10:53','192.168.1.68',1,NULL),(518,'lcawood','2006-06-16 13:11:41','192.168.1.68',1,NULL),(519,'teacher','2006-06-16 13:11:51','192.168.1.68',1,NULL),(520,'lcawood','2006-06-16 14:22:15','192.168.1.68',1,NULL),(521,'teacher','2006-06-16 14:22:22','192.168.1.68',1,NULL),(522,'lcawood','2006-06-16 14:54:50','192.168.1.68',1,NULL),(523,'lcawood','2006-06-16 16:01:53','192.168.1.68',1,NULL),(524,'jleckie','2006-06-16 16:40:14','192.168.1.67',1,NULL),(525,'lcawood','2006-06-27 17:24:43','192.168.1.68',1,NULL),(526,'lcawood','2006-06-29 14:57:31','192.168.1.72',1,NULL),(527,'lcawood','2006-06-29 16:11:34','192.168.1.72',1,NULL),(528,'lcawood','2006-06-30 14:18:24','192.168.1.72',1,NULL),(529,'lcawood','2006-06-30 15:46:17','192.168.1.72',1,NULL),(530,'lcawood','2006-07-04 14:24:36','192.168.1.72',1,NULL),(531,'lcawood','2006-07-04 14:41:09','192.168.1.72',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (532,'lcawood','2006-07-04 14:59:56','192.168.1.72',1,NULL),(533,'lcawood','2006-07-04 15:38:11','192.168.1.72',1,NULL),(534,'jaleckie','2006-07-07 15:55:44','192.168.1.67',0,NULL),(535,'jaleckie','2006-07-07 15:55:52','192.168.1.67',0,NULL),(536,'lcawood','2006-07-07 15:56:39','192.168.1.72',1,NULL),(537,'lcawood','2006-07-12 12:37:34','192.168.1.68',1,NULL),(538,'lcawood','2006-07-12 13:36:59','192.168.1.68',1,NULL),(539,'lcawood','2006-07-12 15:28:54','192.168.1.68',1,NULL),(540,'lcawood','2006-07-14 13:50:15','192.168.1.68',1,NULL),(541,'lcawood','2006-07-14 16:50:10','192.168.1.68',1,NULL),(542,'jleckie','2006-07-19 12:44:19','192.168.1.65',1,NULL),(543,'jleckie','2006-07-28 14:49:56','192.168.1.64',1,NULL),(544,'jleckie','2006-08-06 16:00:21','192.168.1.64',1,NULL),(545,'jleckie','2006-08-18 13:52:54','192.168.1.66',1,NULL),(546,'teacher','2006-08-18 13:53:13','192.168.1.66',1,NULL),(547,'jleckie','2006-08-18 13:59:26','192.168.1.66',1,NULL),(548,'teacher','2006-08-18 13:59:49','192.168.1.66',1,NULL),(549,'jleckie','2006-10-17 17:31:49','192.168.1.65',1,NULL),(550,'jleckie','2006-10-17 20:57:01','192.168.1.65',1,NULL),(551,'jleckie','2006-10-17 22:09:19','192.168.1.65',1,NULL),(552,'jaleckie','2006-10-18 10:45:58','203.25.82.46',0,NULL),(553,'jleckie','2006-10-18 10:46:04','203.25.82.46',1,NULL),(554,'jleckie','2006-10-20 12:40:10','192.168.1.65',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (555,'jleckie','2006-10-20 15:36:31','192.168.1.65',1,NULL),(556,'jleckie','2006-10-23 17:23:30','203.17.189.169',1,NULL),(557,'jaleckie','2006-12-14 12:47:23','192.168.1.66',0,NULL),(558,'jleckie','2006-12-14 12:47:48','192.168.1.66',1,NULL),(559,'jaleckie','2007-01-21 17:01:30','192.168.1.66',0,NULL),(560,'jleckie','2007-01-21 17:01:35','192.168.1.66',1,NULL),(561,'teacher','2007-01-21 17:04:25','192.168.1.66',1,NULL),(562,'jleckie','2007-01-22 09:43:45','192.168.1.67',1,NULL),(563,'jleckie','2007-01-22 10:25:25','192.168.1.67',1,NULL),(564,'jleckie','2007-01-22 15:04:39','192.168.1.67',1,NULL),(565,'jleckie','2007-01-22 16:41:12','192.168.1.67',1,NULL),(566,'jleckie','2007-01-22 17:13:10','192.168.1.67',1,NULL),(567,'jleckie','2007-01-22 17:59:44','192.168.1.67',1,NULL),(568,'jleckie','2007-01-22 18:36:35','138.217.33.76',1,NULL),(569,'jleckie','2007-01-22 20:04:59','138.217.33.76',1,NULL),(570,'jleckie','2007-01-23 10:43:58','192.168.1.67',1,NULL),(571,'jleckie','2007-01-23 11:52:01','192.168.1.67',1,NULL),(572,'jleckie','2007-01-23 12:15:59','192.168.1.66',1,NULL),(573,'jleckie','2007-01-23 12:27:53','192.168.1.67',1,NULL),(574,'jleckie','2007-01-23 12:29:44','192.168.1.67',1,NULL),(575,'jleckie','2007-01-23 13:08:01','192.168.1.66',1,NULL),(576,'jleckie','2007-01-23 15:12:38','192.168.1.67',1,NULL),(577,'jleckie','2007-01-23 16:12:01','192.168.1.67',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (578,'jleckie','2007-01-23 16:56:02','192.168.1.67',1,NULL),(579,'jleckie','2007-01-23 17:11:36','192.168.1.66',1,NULL),(580,'jleckie','2007-01-23 18:00:28','192.168.1.67',1,NULL),(581,'jleckie','2007-01-23 18:05:21','192.168.1.66',1,NULL),(582,'jleckie','2007-01-23 18:06:47','192.168.1.66',1,NULL),(583,'jleckie','2007-01-23 18:32:14','192.168.1.67',1,NULL),(584,'jleckie','2007-01-24 11:35:05','192.168.1.67',1,NULL),(585,'jleckie','2007-01-24 11:52:44','203.25.82.46',1,NULL),(586,'jleckie','2007-01-24 11:54:43','203.25.82.46',1,NULL),(587,'jleckie','2007-01-24 13:11:05','203.25.82.46',1,NULL),(588,'jleckie','2007-01-24 15:08:54','192.168.1.67',1,NULL),(589,'jleckie','2007-01-24 17:55:31','203.25.82.46',1,NULL),(590,'jleckie','2007-01-24 18:04:07','192.168.1.67',1,NULL),(591,'jleckie','2007-01-25 12:30:11','192.168.1.66',0,NULL),(592,'jleicke','2007-01-25 12:30:20','192.168.1.66',0,NULL),(593,'jleckie','2007-01-25 12:30:34','192.168.1.66',1,NULL),(594,'jleckie','2007-01-25 12:59:20','192.168.1.67',1,NULL),(595,'jleckie','2007-01-25 13:01:18','192.168.1.67',1,NULL),(596,'jleckie','2007-01-25 13:54:06','192.168.1.67',1,NULL),(597,'jleckie','2007-01-25 15:11:47','192.168.1.67',1,NULL),(598,'jleckie','2007-01-25 16:04:48','150.101.162.63',1,NULL),(599,'jleckie','2007-01-25 16:35:27','192.168.1.66',1,NULL),(600,'jleckie','2007-01-25 17:51:07','192.168.1.66',1,NULL);
insert into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (601,'jleckie','2007-01-25 17:55:22','192.168.1.67',1,NULL),(602,'jleckie','2007-01-25 18:24:00','192.168.1.67',1,NULL),(603,'jleckie','2007-01-25 18:31:31','192.168.1.67',1,NULL),(604,'jleckie','2007-01-25 20:31:46','192.168.1.67',1,NULL);

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

insert into `news_items`(`item_id`,`item_topic_id`,`item_heading`,`item_datetime_posted`,`item_datetime_expires`,`item_mainpage`,`item_body`,`item_link1`,`item_link2`,`item_link3`,`item_link4`,`item_link5`,`item_deleted`,`item_owner`,`item_campus`,`item_group`) values (1,2,'test','2004-01-08 19:58:10','2004-01-09 19:58:10',0,'test','','','','','',0,1,1,NULL),(2,2,'Test News','2004-01-15 20:56:11','2004-01-16 20:56:11',0,'tedt','','','','','',0,1,1,NULL),(3,2,'Welcome to the Portal Test Site','2004-01-24 17:12:35','2006-03-30 02:38:46',0,'\r\nThis site provides both a demonstration area and test site for new features.  Feel free to interact and post files.  Do remember that this is a development site and may contain bugs from time to time.\r\n\r\nThis site has the following features disabled as they are not applicable when offsite.<ul><li>Synergetic integration</li><li>Timetables</li><li>Webmail</li><li>Class lists</li><li>Subject Links</li><li>Attendances</li></ul>The following features are operational and I encourage all visitors to explore<ul><li>News</li><li>Calendar</li><li>Content Management System</li><li>Homepages</li><li>Group Management</li><li>Submission System</li></ul>If you have any enquires I can be contacted on the following address jleckie@lektec.com.au','','','','','',0,1,1,NULL);
insert into `news_items`(`item_id`,`item_topic_id`,`item_heading`,`item_datetime_posted`,`item_datetime_expires`,`item_mainpage`,`item_body`,`item_link1`,`item_link2`,`item_link3`,`item_link4`,`item_link5`,`item_deleted`,`item_owner`,`item_campus`,`item_group`) values (4,0,'test','2004-03-13 17:08:05','2004-03-14 17:08:05',0,'test','','','','','',0,1,0,NULL),(5,0,'test','2004-03-13 17:15:10','2004-03-14 17:15:10',0,'test','','','','','',1,1,0,NULL),(6,0,'admin only msg','2004-03-13 17:15:50','2004-03-14 17:15:50',0,'testing admin only','','','','','',1,1,0,NULL),(7,0,'teachers only','2004-03-13 17:16:03','2004-03-14 17:16:03',0,'teachers only','','','','','',0,1,0,NULL),(8,0,'students only','2004-03-13 17:16:44','2004-03-14 17:16:44',0,'students only','','','','','',0,1,0,NULL),(9,0,'everyone','2004-03-13 17:16:54','2004-03-14 17:16:54',0,'test','','','','','',1,1,0,NULL);
insert into `news_items`(`item_id`,`item_topic_id`,`item_heading`,`item_datetime_posted`,`item_datetime_expires`,`item_mainpage`,`item_body`,`item_link1`,`item_link2`,`item_link3`,`item_link4`,`item_link5`,`item_deleted`,`item_owner`,`item_campus`,`item_group`) values (10,0,'Welcome to the Portal Development Area','2004-03-13 20:05:57','2005-03-17 13:20:54',0,'This site provides both a demonstration area and test site for new features. Feel free to interact and post files. Do remember that this is a development site and may contain bugs from time to time. This site has the following features disabled as they are not applicable when offsite.<ul><li>Synergetic integration</li><li>Timetables</li><li>Webmail</li><li>Class lists</li><li>Subject Links</li><li>Attendances</li></ul>The following features are operational and I encourage all visitors to explore<ul><li>News</li><li>Calendar</li><li>Content Management System</li><li>Homepages</li><li>Group Management</li><li>Submission System</li></ul>If you have any enquires I can be contacted on the following address jleckie@lektec.com.au ','','','','','',0,1,0,NULL),(11,0,'sad','2005-06-09 07:34:17','2005-06-10 07:34:17',0,'asd\r\n','0','0','0','0','0',0,1,0,NULL),(12,0,'dfgdf','2005-06-09 07:39:28','2005-06-10 07:39:28',0,'dfgdfg\r\n','0','0','0','0','0',0,1,0,NULL),(13,0,'dfgdf','2005-06-09 07:39:48','2005-06-10 07:39:48',0,'dfgdfg\r\n','0','0','0','0','0',0,1,0,NULL),(14,0,'Some more news','2005-06-09 07:41:17','2005-06-23 07:41:17',0,'Some interesting stuff..<br /><br /><br />Blah blah<br /><br />More stuff...<br />....<br />Here we go<br />\r\n','0','0','0','0','0',0,1,0,NULL),(15,0,'test news','2005-07-26 21:24:31','2005-07-27 21:24:31',0,'my new news<br /> ','0','0','0','0','0',0,1,0,NULL);

/*Table structure for table `news_topics` */

DROP TABLE IF EXISTS `news_topics`;

CREATE TABLE `news_topics` (
  `topic_id` smallint(5) unsigned NOT NULL auto_increment,
  `topic_name` char(50) NOT NULL default '',
  PRIMARY KEY  (`topic_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `news_topics` */

insert into `news_topics`(`topic_id`,`topic_name`) values (1,'Staff Only');

/*Table structure for table `newsCampus` */

DROP TABLE IF EXISTS `newsCampus`;

CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL default '0',
  `campus` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `newsCampus` */

insert into `newsCampus`(`id`,`campus`) values (3,1),(7,1),(8,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1);

/*Table structure for table `newsGroup` */

DROP TABLE IF EXISTS `newsGroup`;

CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL default '0',
  `gid` int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `newsGroup` */

insert into `newsGroup`(`id`,`gid`) values (3,1),(3,2),(3,3),(4,1),(4,3),(7,2),(8,3),(10,1),(10,2),(10,3),(11,1),(12,1),(13,1),(14,1),(14,2),(14,3),(15,1),(15,2),(15,3),(16,1),(16,2);

/*Table structure for table `perms` */

DROP TABLE IF EXISTS `perms`;

CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL default '0',
  `gid` int(4) NOT NULL default '0',
  `type` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `perms` */

insert into `perms`(`cid`,`gid`,`type`) values (1,1,2),(1,2,1),(1,3,1),(2,1,2),(2,2,2),(2,3,2),(3,1,1),(3,2,1),(3,3,1),(4,1,1),(4,2,1),(4,3,1),(5,1,2),(5,2,2),(5,3,2),(7,1,2),(7,2,2),(7,3,2),(13,2,2),(13,3,1),(14,2,2),(14,3,1),(15,2,2),(15,3,1),(17,2,2),(17,3,1),(18,2,2),(18,3,1),(19,2,1),(19,3,1),(26,1,1),(26,2,1),(26,3,2),(27,1,1),(27,2,2),(27,3,2),(28,1,1),(28,2,2),(28,3,1),(29,1,2),(29,2,1),(29,3,1),(30,1,2),(30,2,1),(30,3,1),(31,1,1),(31,2,1);

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

insert into `resource`(`id`,`owner`,`perm`,`location`,`name`,`description`,`type`) values (1,1,1,3,'Q006','Computer lab with 10 computers available for use.\r\n<br />\r\n<br />\r\nAlso has central hub with power and network connectivity for entire class of laptop users.',1),(2,1,1,3,'Projector','This device can be used to display presentations and notes from either your laptop or VCR.  \r\n\r\nIf you need operating instructions please see this <a href=\"#\">document</a>.  Please ensure after use you return the remote control to the helpdesk.  ',2),(3,1,1,3,'Q005',NULL,1),(4,1,1,3,'Digital Camera',NULL,2),(5,1,1,1,'Video Recorder',NULL,2),(6,1,1,1,'BBQ','This BBQ can be wheeled to locations around the school.  It can be picked up from the property offices located below the cafeteria.  It must be returned cleaned and on time. ',4);

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

insert into `resourceBooking`(`id`,`created`,`rid`,`creator`,`start`,`end`,`note`,`start_time`,`end_time`,`day`,`week`,`date`,`month`,`year`,`recurrence`,`type`) values (1,'2005-02-10 03:29:05',2,1,'2004-11-16 10:00:00','2004-11-16 11:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(2,'2005-02-10 03:29:26',5,1,'2004-11-16 10:00:00','2004-11-16 11:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(3,'2005-02-10 03:29:26',5,1,'2004-11-17 13:34:00','2004-11-17 14:45:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(4,'2005-02-10 03:29:26',5,1,'2004-11-18 12:00:00','2004-11-18 14:30:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(5,'2005-02-10 03:29:26',5,1,'2004-11-17 00:12:00','2004-11-16 14:37:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(6,'2005-02-10 03:29:26',5,1,'2004-11-16 12:56:00','2004-11-16 15:34:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(7,'2005-02-10 03:29:26',6,1,'2004-11-25 14:00:00','2004-11-25 15:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(8,'2005-02-10 03:29:26',1,1,'2004-11-25 16:00:00','2004-11-25 18:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(9,'2005-02-10 03:29:26',1,1,'2004-11-26 16:00:00','2004-11-26 17:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(10,'2005-02-10 03:29:26',7,1,'2004-11-26 10:00:00','2004-11-26 14:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(11,'2005-02-10 03:29:26',7,1,'2004-11-27 17:00:00','2004-11-27 18:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(14,'2005-02-10 18:45:54',1,1,'2005-02-10 15:00:00','2005-02-10 16:30:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'request'),(15,'2005-02-10 18:43:33',1,1,'2005-02-10 13:00:00','2005-02-10 14:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(16,'2005-02-10 06:41:07',7,1,'2005-02-12 14:00:00','2005-02-12 15:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked');
insert into `resourceBooking`(`id`,`created`,`rid`,`creator`,`start`,`end`,`note`,`start_time`,`end_time`,`day`,`week`,`date`,`month`,`year`,`recurrence`,`type`) values (17,'2005-02-10 19:03:25',7,11,'2005-02-12 09:00:00','2005-02-12 11:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(18,'2005-02-10 19:12:28',7,11,'2005-02-10 10:00:00','2005-02-10 11:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'request'),(19,'2005-02-10 19:24:45',1,11,'2005-02-10 12:00:00','2005-02-10 14:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(20,'2005-02-10 19:25:05',1,11,'2005-02-10 14:00:00','2005-02-10 15:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(21,'2005-02-10 19:26:53',1,11,'2005-02-10 14:00:00','2005-02-10 15:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked'),(22,'2005-02-10 19:29:11',1,11,'2005-02-11 12:00:00','2005-02-11 14:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'locked');

/*Table structure for table `roomResource` */

DROP TABLE IF EXISTS `roomResource`;

CREATE TABLE `roomResource` (
  `room` int(6) unsigned NOT NULL default '0',
  `resource` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY  (`room`,`resource`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `roomResource` */

insert into `roomResource`(`room`,`resource`) values (1,2);

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

insert into `sideMenu`(`id`,`parent`,`txt`,`url`,`target`,`sequence`) values (1,0,'Home','/home.php','',0),(2,0,'Student Services','#','',5),(3,0,'Staff Services','#','',1),(4,0,'Faculties','#','',2),(7,0,'Houses','#','',4),(8,0,'Sport','#','',3),(9,1,'Main','/home.php','',5),(10,1,'News','/news.php','',6),(12,1,'Webmail','/mail.php','',9),(13,1,'Calendar','/calendar/viewCalendar.php','',3),(14,1,'Browse','/browse.php','',2),(15,1,'Submissions','/submission/listSubmission.php','',8),(16,1,'Returned Work','/submission/returnedWork.php','',7),(93,9,'Edit My Links','/cms/myLinks','',0),(94,9,'Manage My Groups','/group/joinGroup.php','',2),(95,9,'Logout','/logout.php','',1),(96,10,'View News','/news.php','',1),(97,10,'Add News','/news/modnews.php','',0),(98,12,'View Mail','/mail.php','',2),(99,12,'Send Mail','/mail/send.php','',1),(100,12,'Address Book','/search/userLookup.php','',0),(101,14,'Add Category','/cms/modFolder.php?action=add','',0),(102,14,'Add File','/cms/modResource.php?type=file','',1),(103,14,'Add Multiple Files','/cms/modResource.php?type=multfile','',3),(104,14,'Add Link','/cms/modResource.php?type=url','',2),(105,14,'Add Multiple Links','/addResource.php?type=multurl','',4),(106,14,'Delete Category','/cms/modFolder.php?action=del','',5),(207,0,'Logout','/logout.php','',6),(208,1,'Admin','/admin/admin.php','',0),(209,208,'Add User','/admin/modUser.php?adduser=1','',1);
insert into `sideMenu`(`id`,`parent`,`txt`,`url`,`target`,`sequence`) values (211,208,'Modify User','/admin/modUser.php','',2),(212,208,'Modify Side Menu','/admin/modSideMenu.php','',0),(213,1,'Due Work','/submission/dueWork.php','',4),(214,1,'Book Resource','/modules/resource/viewResourceList.php','',1),(215,101,'Seans Bigness','#','',1),(216,101,'Seans Bigness','#','',2),(217,101,'Seans Bigness','#','',3);

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

/*Data for the table `sidemenu` */

/*Table structure for table `sideMenuCampus` */

DROP TABLE IF EXISTS `sideMenuCampus`;

CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL default '0',
  `campus` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `sideMenuCampus` */

insert into `sideMenuCampus`(`id`,`campus`) values (1,1),(2,1),(3,1),(7,1),(8,1),(9,1),(10,1),(13,1),(14,1),(15,1),(16,1),(93,1),(94,1),(95,1),(96,1),(97,1),(101,1),(102,1),(103,1),(104,1),(105,1),(106,1),(207,1),(213,1);

/*Table structure for table `sideMenuGroup` */

DROP TABLE IF EXISTS `sideMenuGroup`;

CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL default '0',
  `gid` int(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `sideMenuGroup` */

insert into `sideMenuGroup`(`id`,`gid`) values (1,1),(1,2),(1,3),(2,1),(2,2),(2,3),(3,1),(3,2),(3,3),(7,1),(7,2),(7,3),(8,1),(8,2),(8,3),(9,1),(9,2),(9,3),(10,1),(10,2),(10,3),(13,1),(13,2),(13,3),(14,1),(14,2),(14,3),(15,1),(15,2),(16,3),(93,1),(93,2),(93,3),(94,1),(94,2),(94,3),(95,1),(95,2),(95,3),(96,1),(96,2),(96,3),(97,1),(97,2),(101,1),(101,2),(102,1),(102,2),(103,1),(103,2),(104,1),(104,2),(105,1),(105,2),(106,1),(106,2),(207,1),(207,2),(207,3),(213,3),(214,1),(214,2),(214,3),(215,1),(215,2),(215,3),(216,1),(216,2),(216,3),(217,1),(217,2);

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

insert into `subject_news`(`id`,`heading`,`datetime_posted`,`datetime_expires`,`body`,`deleted`,`code`,`owner`) values (1,'Test HTML editor','2004-03-15 17:08:04','2004-03-16 17:11:16','<p>Testing the new <font color=\"#ff0000\">HTML</font> editor. </p><p>It allows the following feature</p><ul><li>Lists</li><li>Colouring</li><li><u>Underlining</u></li><li><strong>Bold</strong></li><li><font style=\"BACKGROUND-COLOR: #66ff66\" color=\"#0099ff\">Colouring IN</font></li><li><a title=\"Cnet\" href=\"http://www.cnet.com/\" target=\"_blank\">Web links</a></li></ul><p align=\"right\">It has some justification</p><p align=\"center\">To both ends</p><p>Sides</p>',0,4,1),(2,'test','2004-03-29 18:37:21','2004-03-30 18:37:21','test',0,4,1),(3,'test','2004-03-29 18:38:11','2004-03-30 18:38:11','test',0,4,3),(4,'Sean was big and is now bigger','2005-06-17 01:46:12','2006-06-17 01:46:12','dsfsdfasdf asf sadf sdf hj sdflk sdkfj saklf sdfh oiwehiowehfoiwehf ewh fioweh fiowehfoihe oiehf oiewhf sapoef ewohfo iheafoihwea fohweaf\r\n',0,4,1);
insert into `subject_news`(`id`,`heading`,`datetime_posted`,`datetime_expires`,`body`,`deleted`,`code`,`owner`) values (5,'More news','2005-07-17 23:14:11','2005-08-14 23:14:26','An apology for publishing information previous informationr regarding seans bigness.  He really isnt that big.<br />',0,4,1),(6,'test','2005-10-17 06:55:46','2005-10-18 06:55:46','test',0,4,3),(7,'heading','2006-05-21 15:16:20','2006-05-26 15:16:20','<p>First Post</p><p>&nbsp;</p><p>&nbsp;</p><p>blah</p><p>&nbsp;</p>',0,35,23);
insert into `subject_news`(`id`,`heading`,`datetime_posted`,`datetime_expires`,`body`,`deleted`,`code`,`owner`) values (8,'headmaster','2006-05-21 15:17:15','2006-05-26 15:17:15',' <p> Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Vivamus elit risus, viverra vitae, consequat ac, varius ac, ligula. Praesent pharetra, mauris sit amet lacinia eleifend, nisi nulla volutpat arcu, quis fermentum ligula diam eu odio. Suspendisse consectetuer. Duis non sapien. Donec enim. Maecenas egestas cursus dolor. Nulla ut magna. Nunc euismod egestas neque. Vivamus tincidunt. Quisque fermentum mi. Cras eu ipsum. Nulla tincidunt venenatis nibh. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Duis pulvinar fermentum lectus. Aliquam aliquet vulputate augue. Aenean sed turpis in felis gravida dictum. Vestibulum diam. Maecenas quis lectus. </p>     <p>Mauris ultrices aliquam metus. Etiam vehicula, metus non facilisis lobortis, diam pede dapibus est, sit amet vehicula nunc massa sed quam. Nulla ipsum nibh, porttitor quis, consectetuer id, volutpat sit amet, libero. Aliquam ut leo. Duis non risus. Phasellus luctus. Mauris lectus. Morbi eu ante. Pellentesque dui pede, dictum vitae, fermentum eu, sagittis at, augue. Morbi eros. Cras tincidunt pulvinar tortor. In nonummy est eget elit. Pellentesque ligula. Aenean tempus risus id lectus. Aenean eleifend velit vitae diam. Integer consectetuer eleifend ante. Aenean rhoncus egestas enim. Fusce egestas lacinia neque. Sed nec tellus ac ante lobortis fermentum. Mauris sed mauris. </p>     <p>Donec sodales nonummy erat. Suspendisse potenti. Mauris nec massa quis erat eleifend bibendum. Etiam elit. Duis purus augue, sagittis placerat, condimentum id, bibendum pellentesque, nisl. Nulla a leo nec sapien ornare faucibus. Donec tristique, enim eu lobortis ultrices, tortor ligula euismod odio, in sagittis mauris risus vel nulla. Integer sollicitudin, eros vel fringilla pellentesque, felis mi mollis metus, et venenatis metus elit eu risus. Phasellus nibh ligula, dapibus vel, dapibus ut, nonummy id, mauris. Aliquam est turpis, hendrerit sed, lacinia ut, cursus sit amet, ligula. </p>     <p>Donec convallis ligula sed metus. Vestibulum ligula augue, ultrices non, rutrum quis, varius eget, elit. Vestibulum imperdiet purus et tellus. Sed lorem. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Mauris nec felis quis nibh tristique accumsan. Curabitur felis odio, consequat vel, dictum non, eleifend vel, lectus. Nullam eget mauris. Pellentesque sagittis cursus ligula. Donec in tortor interdum quam rutrum ultricies. </p>     <p>Nam dignissim nulla a dolor. Fusce volutpat est ullamcorper nisl. Fusce eleifend. Vestibulum elit. Vivamus sit amet nibh. Aliquam ut ante. Suspendisse fermentum, dolor non posuere iaculis, nibh massa facilisis massa, at faucibus nibh lacus et augue. Sed consectetuer vulputate diam. Vestibulum tempor, metus eget varius pharetra, tellus tellus vestibulum nibh, eget facilisis neque est vestibulum diam. Nam facilisis pellentesque nulla. Suspendisse hendrerit, urna nec ultricies venenatis, magna erat hendrerit nisl, at accumsan velit dolor sit amet erat. Nulla a nibh eget dui porta auctor. Praesent sed velit. Nulla vel elit. Ut diam. Nulla elit ante, vehicula et, adipiscing id, vestibulum ultricies, leo. Quisque at tellus tincidunt elit convallis luctus. Vivamus ut justo. </p> ',0,33,23),(9,'hmm','2006-05-21 15:19:29','2006-05-26 15:19:29',' <p>line 1</p> <p>line 2</p> <p>&nbsp;</p> <p>line 3</p> <p>&nbsp;</p> <p>line 4</p> <p>&nbsp;</p> ',0,34,23);
insert into `subject_news`(`id`,`heading`,`datetime_posted`,`datetime_expires`,`body`,`deleted`,`code`,`owner`) values (10,'Hello and welcome to the first bulletin post !!!!!','2006-05-21 16:27:53','2007-05-21 16:27:53',' <p> In lacus tortor, facilisis et, laoreet sit amet, elementum in, massa. Curabitur dolor odio, mollis vel, aliquam vel, dictum vel, dui. Mauris porttitor. Cras eget tortor. Aliquam mi dolor, eleifend sit amet, fringilla eu, porta in, pede. Nam dignissim, libero a molestie elementum, metus tellus laoreet dolor, in pellentesque mauris lacus sed diam. Fusce faucibus dolor et justo. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Proin varius justo eu odio. Nullam nulla quam, venenatis in, porttitor id, hendrerit sit amet, dolor. </p>   <p>Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Nullam viverra purus quis massa. Donec viverra vulputate ante. Vestibulum vestibulum augue et quam. Praesent ipsum diam, tincidunt eu, luctus non, scelerisque eget, diam. Suspendisse bibendum. Integer non dolor. Nullam placerat tincidunt felis. Maecenas facilisis posuere elit. Aenean venenatis, ante quis convallis mattis, est dui dapibus nisi, in vulputate lectus nulla eu enim. Sed elit dui, mollis eu, porta non, iaculis sed, felis. Nullam volutpat, massa in sollicitudin iaculis, nunc massa dictum velit, cursus interdum lectus risus ac quam. Phasellus lacinia laoreet neque. Donec varius, velit ut sollicitudin sodales, mauris metus cursus orci, id convallis mi leo id massa. Nam condimentum posuere mauris. In sit amet velit nec elit suscipit egestas. Phasellus nunc. Nulla neque nulla, ornare sit amet, aliquet vel, ultricies accumsan, neque. Nunc libero. </p>   <p>Donec faucibus enim non metus commodo molestie. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Vestibulum malesuada gravida erat. Duis libero eros, ornare sit amet, elementum eu, sodales dignissim, neque. Praesent consequat tincidunt ligula. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Mauris sodales, augue ac venenatis aliquet, mi leo sagittis nunc, eu vestibulum mauris felis id felis. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In elementum. Nulla vitae nisl vitae leo consectetuer tristique. In hac habitasse platea dictumst. Curabitur convallis vulputate mauris. Curabitur metus. Donec diam velit, pulvinar a, vestibulum in, eleifend ut, turpis. Nunc neque. Nullam facilisis porttitor dui. Cras euismod consectetuer arcu. Praesent aliquam, sapien ut suscipit commodo, ligula nisl elementum velit, eget ornare leo massa in mi. Quisque non nulla et metus vestibulum posuere. Duis pretium nunc a eros. </p>   <p>In consequat libero ac ante bibendum varius. Vivamus congue, erat et viverra lobortis, nisi nisi ultricies quam, non dignissim lectus urna quis augue. Cras nec justo non orci elementum aliquet. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Curabitur id lorem. Cras interdum elementum lectus. Maecenas at nisi a augue dignissim bibendum. Aliquam tincidunt, odio nec dictum sagittis, odio lorem tristique leo, ac viverra arcu erat eu elit. Donec tellus magna, vehicula at, porttitor scelerisque, malesuada eget, elit. Etiam sed augue. Nullam vel nulla tempor sem ultrices mattis. Maecenas tempus ipsum et mi. In lacus odio, lobortis nec, placerat in, lobortis et, ipsum. Donec elementum consectetuer risus. Aliquam rutrum, pede quis commodo convallis, libero libero facilisis urna, in pellentesque risus massa venenatis mi. Vivamus porta lorem non massa. </p>   <p>Maecenas vitae velit ac risus consectetuer malesuada. Aenean vitae justo ac urna fermentum porta. Morbi placerat imperdiet urna. Aliquam vehicula, est ac faucibus congue, nibh leo condimentum sapien, ut varius turpis velit quis sem. Nunc et pede et tortor porttitor volutpat. Nam ligula dui, adipiscing non, ultricies a, tempus nec, diam. Morbi libero. Mauris mollis ultricies felis. Aenean varius fringilla arcu. In neque nunc, tincidunt eu, mattis sit amet, dictum nec, diam. Maecenas malesuada nibh. Sed interdum est sit amet orci. Sed convallis auctor risus. Curabitur pretium eros sit amet lectus. Donec sit amet libero elementum metus feugiat pharetra. Fusce scelerisque felis sit amet justo. </p> ',0,35,23);

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

insert into `submission_box`(`id`,`category`,`owner`,`date`,`late_date`,`fid`,`deleted`,`description`,`created`) values (1,4,3,'2004-01-29 08:00:00','2004-01-29 08:00:00',9,0,'Test Submission Box','2004-01-22 00:52:43'),(2,18,5,'2004-09-23 16:00:00','2004-09-25 16:00:00',31,0,'Impossible Assignment','2004-09-23 16:32:52'),(3,4,1,'2005-06-28 19:00:00','2005-06-28 19:00:00',37,0,'new file','2005-06-14 19:46:42'),(4,11,1,'2005-06-17 19:00:00','2005-06-20 19:00:00',38,0,'another file','2005-06-14 19:47:04'),(5,4,1,'2005-06-27 19:00:00','2005-06-29 19:00:00',39,0,'my sub','2005-06-14 19:47:22'),(6,4,1,'2005-06-29 17:00:00','2005-11-30 17:00:00',41,0,'another submission','2005-06-26 23:35:16'),(7,4,1,'2005-07-27 02:00:00','2005-07-28 02:00:00',44,0,'sdas','2005-07-20 01:04:13');

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

insert into `submission_log`(`datetime`,`user`,`page`,`sid`) values ('2007-01-25 18:29:44',1,1,0),('2005-06-15 01:01:50',1,2,0),('2005-06-21 00:32:06',1,3,3),('2005-06-26 23:51:52',1,3,6),('2005-07-20 01:09:08',1,3,7),('2005-07-27 01:14:29',1,3,8),('2005-03-30 19:42:54',4,1,0),('2004-01-22 00:52:59',4,2,0);

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

insert into `superusers`(`id`) values (1),(2);

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

insert into `user`(`id`,`username`,`password`,`group_id`,`firstname`,`lastname`,`email_pass`,`synergy_id`,`campus`,`year`,`house`,`local_email`,`alt_email`,`mail_handler`) values (1,'jleckie','*7968DF3FE2AF92916A9D4F5BE24010B7707AC68F',1,'James','Leckie','',4,1,NULL,NULL,0,'jleckie@lektec.com.au',0),(2,'cc','*2C1E39E398F522D964A15F10E9240E150C4D54C4',1,'CC','Hua','cc123',0,1,NULL,NULL,0,'',0),(3,'testuser','*2C1E39E398F522D964A15F10E9240E150C4D54C4',1,'Test','User','test123',0,0,NULL,NULL,0,'',0),(4,'student','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'Student','Student','student',0,0,8,NULL,0,'',0),(5,'eleckie','*2C1E39E398F522D964A15F10E9240E150C4D54C4',1,'Emmie-clare','Leckie','temp123',0,0,13,NULL,1,'',0),(6,'helpdev','*2C1E39E398F522D964A15F10E9240E150C4D54C4',1,'Helpdesk','Development',NULL,45800,0,NULL,NULL,0,NULL,0),(7,'test1','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'firstname','',NULL,0,0,7,12,0,NULL,0),(8,'lulu','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'luai','luia',NULL,27126,0,7,1,0,NULL,0),(9,'jeter','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'peter','ASM',NULL,37733,0,8,2,0,NULL,0),(10,'Jinn','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'EuJinn','Wong',NULL,44109,0,9,3,0,NULL,0),(11,'nelo','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'Mike','Nelo',NULL,46919,0,10,4,0,NULL,0),(12,'jules','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'julian','nesci',NULL,47205,0,10,5,0,NULL,0),(13,'rocket','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'rocket','man',NULL,47618,0,10,6,0,NULL,0);
insert into `user`(`id`,`username`,`password`,`group_id`,`firstname`,`lastname`,`email_pass`,`synergy_id`,`campus`,`year`,`house`,`local_email`,`alt_email`,`mail_handler`) values (14,'moon','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'moonpie','face',NULL,49914,0,9,7,0,NULL,0),(15,'eat','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'cake','eater',NULL,51425,0,9,8,0,NULL,0),(16,'you','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'right','john',NULL,51427,0,11,9,0,NULL,0),(17,'rockin','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'underground','hiphop',NULL,53720,0,11,10,0,NULL,0),(18,'whos','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'rons','house',NULL,54378,0,12,11,0,NULL,0),(19,'tj','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'tj','panface',NULL,54398,0,12,12,0,NULL,0),(20,'ant','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'ant','therapist',NULL,54457,0,12,13,0,NULL,0),(21,'sean','*2C1E39E398F522D964A15F10E9240E150C4D54C4',3,'sean','sean',NULL,0,0,13,NULL,0,'0',0),(22,'mynewaccount','*2C1E39E398F522D964A15F10E9240E150C4D54C4',1,'test','testsa','test',0,0,0,NULL,0,'',0),(23,'lcawood','*CB4EBFAF0551F4892C4469B59A325B9008336F6D',1,'l','wc','indiana',0,0,NULL,NULL,0,'nobody@nowhere.com',0),(24,'teacher','*977F15BF49C046DA76BC81A80146AAB943F679F1',2,'teacher','teacher','teacher',0,0,0,NULL,0,'',0);

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

insert into `userAccess`(`uid`,`service`,`sid`,`type`) values (1,1,4,3),(2,1,4,1),(3,1,4,2),(4,1,4,1),(24,1,4,2),(1,1,11,0),(5,1,11,3),(1,1,17,2),(5,1,17,3),(5,1,18,3),(1,1,30,3),(1,1,34,4),(12,2,1,3);

/*Table structure for table `userCampus` */

DROP TABLE IF EXISTS `userCampus`;

CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL default '0',
  `campus` tinyint(3) unsigned NOT NULL default '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `userCampus` */

insert into `userCampus`(`uid`,`campus`,`defaultCampus`) values (1,1,0),(2,1,0),(3,1,0),(4,1,0),(5,1,0),(11,1,0),(21,1,0),(22,1,0),(23,1,0),(24,1,0);

/*Table structure for table `userGroups` */

DROP TABLE IF EXISTS `userGroups`;

CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL default '0',
  `type` tinyint(2) default NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Data for the table `userGroups` */

insert into `userGroups`(`cid`,`type`) values (4,2),(9,1),(10,1),(11,2),(14,1),(17,1),(18,1);

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

insert into `welcomeLayout`(`gid`,`position`,`componentID`) values (1,1,1),(1,2,3),(1,3,NULL),(1,4,NULL),(1,5,2),(1,6,4),(1,7,NULL),(1,8,NULL),(2,1,1),(2,2,3),(2,3,NULL),(2,4,NULL),(2,5,2),(2,6,4),(2,7,NULL),(2,8,NULL),(3,1,1),(3,2,3),(3,3,NULL),(3,4,NULL),(3,5,2),(3,6,4),(3,7,NULL),(3,8,NULL),(4,1,1),(4,2,3),(4,3,NULL),(4,4,NULL),(4,5,2),(4,6,4),(4,7,5);

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
