/*
SQLyog Ultimate v8.82
MySQL - 5.1.41-3ubuntu12.10 : Database - schoolbox
*********************************************************************
*/


/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`schoolbox` /*!40100 DEFAULT CHARACTER SET latin1 */;


/*Table structure for table `allowedFileExtension` */

DROP TABLE IF EXISTS `allowedFileExtension`;

CREATE TABLE `allowedFileExtension` (
  `fileTypeID` int(11) DEFAULT NULL,
  `extension` char(6) DEFAULT NULL,
  KEY `fileTypeID` (`fileTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `allowedFileExtension` */

insert  into `allowedFileExtension`(`fileTypeID`,`extension`) values (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtx'),(4,'rtf'),(5,'css'),(6,'jpe'),(6,'jpeg'),(6,'jpg'),(7,'pjpg'),(7,'jpg'),(7,'jpeg'),(7,'jpe'),(8,'png'),(9,'bmp'),(10,'pdf'),(11,'docx'),(12,'ppt'),(12,'pps'),(13,'xls'),(13,'csv'),(14,'zip'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(17,'ogg'),(18,'gsp'),(19,'cab'),(19,'swf'),(20,'ins'),(20,'isf'),(21,'msw'),(21,'mswmm'),(21,'re3'),(21,'ogg'),(21,'isf'),(21,'ins'),(22,'wav'),(23,'mp2'),(23,'mp3'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'midi'),(26,'mid'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(29,'wmv'),(30,'avi'),(21,'wmv'),(3,'csv'),(31,'zip'),(11,'doc'),(36,'docx'),(37,'ppsx'),(0,'ppsx'),(38,'pptx'),(39,'xlsx'),(40,'xps'),(41,'avi'),(42,'tif'),(42,'tiff'),(0,'tif'),(0,'tiff'),(43,'pub'),(44,'mdb'),(45,'mp4'),(45,'mpv');

/*Table structure for table `allowedFileTypes` */

DROP TABLE IF EXISTS `allowedFileTypes`;

CREATE TABLE `allowedFileTypes` (
  `fileTypeID` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(150) NOT NULL DEFAULT '',
  `mime` varchar(100) NOT NULL DEFAULT '',
  `iconFile` varchar(80) NOT NULL DEFAULT 'application-x-none.png',
  `inline` enum('true','false') NOT NULL DEFAULT 'true',
  `enable` enum('true','false') NOT NULL DEFAULT 'true',
  PRIMARY KEY (`fileTypeID`),
  UNIQUE KEY `mime` (`mime`)
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8;

/*Data for the table `allowedFileTypes` */

insert  into `allowedFileTypes`(`fileTypeID`,`description`,`mime`,`iconFile`,`inline`,`enable`) values (1,'GIF Image','image/gif','image_gif.png','true','true'),(2,'HTML Document','text/html','html.png','true','true'),(3,'Plain Text','text/plain','txt.png','true','true'),(4,'Rich Text','text/richtext','document_alt1.png','true','true'),(5,'CSS','text/css','txt.png','true','true'),(6,'JPEG Image','image/jpeg','image_jpeg.png','true','true'),(7,'Progressing JPEG Image','image/pjpeg','image_jpeg.png','true','true'),(8,'PNG Image','image/png','image_png.png','true','true'),(9,'Bitmap Image','image/bmp','image_bmp.png','true','true'),(10,'PDF Document','application/pdf','pdf_alt.png','true','true'),(11,'Word Document','application/msword','msword_doc.png','true','true'),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','mspowerpoint_ppt.png','true','true'),(13,'Excel Spreadsheet','application/vnd.ms-excel','msexcel_xls.png','true','true'),(14,'ZIP Archive','application/x-zip-compressed','archive_zip.png','false','true'),(15,'Postscript','application/postscript','postscript_alt.png','true','true'),(16,'Photoshop Image','application/x-photoshop','image_psd.png','false','true'),(17,'OGG Audio','application/ogg','sound_ogg.png','true','true'),(18,'Geometer\'s Sketchpad','application/x-gsp','widget.png','false','true'),(19,'Shockwave Flash','application/x-shockwave-flash','flash_swf.png','true','true'),(20,'Inspiration','application/x-Inspiration','umbrellofile.png','false','true'),(21,'Binary Data (Unknown)','application/octet-stream','binary.png','false','true'),(22,'WAV Audio','audio/wav','sound_wav.png','false','true'),(23,'MPEG Audio','audio/mpeg','sound_mp3.png','false','true'),(24,'Real Audio Stream','audio/x-pn-realaudio','sound.png','false','true'),(25,'Real Audio','audio/x-realaudio','sound.png','false','true'),(26,'MIDI Audio','audio/mid','sound.png','false','true'),(27,'MPEG Video','video/mpeg','video_alt.png','false','true'),(28,'Quicktime Video','video/quicktime','quicktime.png','false','true'),(29,'WMV Video','video/x-ms-wmv','video_alt.png','true','true'),(30,'Microsoft Video','video/x-msvideo','video_alt.png','false','true'),(31,'ZIP Archive','application/zip','archive_zip.png','true','true'),(32,'Flash','video/x-flv','flash_swf.png','true','true'),(33,'Windows Media','video/x-ms-asf','video_alt.png','true','true'),(35,'Rich Text Format','text/rtf','document_alt2.png','false','true'),(34,'MPEG 4','video/x-m4v','video_alt.png','true','true'),(36,'Microsoft Office Document 2007','application/vnd.openxmlformats-officedocument.wordprocessingml.document','msword_doc.png','false','true'),(37,'Microsoft Office Slideshow 2007','application/vnd.openxmlformats-officedocument.presentationml.slideshow','mspowerpoint_ppt.png','true','true'),(38,'Microsoft Office Presentation 2007','application/vnd.openxmlformats-officedocument.presentationml.presentation','mspowerpoint_ppt.png','true','true'),(39,'Microsoft Office Spreadsheet 2007','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','msexcel_xls.png','false','true'),(40,'Microsoft XPS','application/vnd.ms-xpsdocument','widget_alt.png','false','true'),(41,'AVI Video','video/avi','video_alt.png','true','true'),(42,'Scanned Image','image/tiff','image_tiff.png','false','true'),(43,'Microsoft Publisher','application/vnd.ms-publisher','document.png','false','true'),(44,'Microsoft Access','application/msaccess','msaccess_mdb.png','false','true'),(45,'MP4 Video','video/mp4','video_alt.png','false','true');

/*Table structure for table `assess` */

DROP TABLE IF EXISTS `assess`;

CREATE TABLE `assess` (
  `assess_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `type` int(11) DEFAULT '11',
  `title` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT 'in minutes',
  `allow_resit` int(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`assess_id`),
  UNIQUE KEY `id` (`assess_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess` */

/*Table structure for table `assess_answer` */

DROP TABLE IF EXISTS `assess_answer`;

CREATE TABLE `assess_answer` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `sequence` int(11) DEFAULT NULL,
  `answer` text,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`answer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess_answer` */

/*Table structure for table `assess_attempt` */

DROP TABLE IF EXISTS `assess_attempt`;

CREATE TABLE `assess_attempt` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `assess_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`attempt_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess_attempt` */

/*Table structure for table `assess_luQuestionType` */

DROP TABLE IF EXISTS `assess_luQuestionType`;

CREATE TABLE `assess_luQuestionType` (
  `type_id` int(8) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `moodle` varchar(255) DEFAULT NULL,
  `automated_marking` enum('true','false') NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess_luQuestionType` */

insert  into `assess_luQuestionType`(`type_id`,`filename`,`description`,`moodle`,`automated_marking`) values (1,'short','Short Answer','shortanswer','false'),(2,'multi','Multiple Choice','multichoice','true'),(3,'fillgap','Cloze Exercise','cloze','true'),(4,'match','Matching Pairs','matching','true');

/*Table structure for table `assess_question` */

DROP TABLE IF EXISTS `assess_question`;

CREATE TABLE `assess_question` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `marks` int(11) DEFAULT NULL,
  `question` text NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess_question` */

/*Table structure for table `assess_response` */

DROP TABLE IF EXISTS `assess_response`;

CREATE TABLE `assess_response` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `question_id` int(11) NOT NULL,
  `response` text,
  `comment` text NOT NULL,
  `mark` float DEFAULT '0',
  `automarked_already` enum('true','false') DEFAULT 'false',
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`response_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess_response` */

/*Table structure for table `assess_section` */

DROP TABLE IF EXISTS `assess_section`;

CREATE TABLE `assess_section` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `assess_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT '0',
  `title` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `random` int(11) NOT NULL DEFAULT '0',
  `pool` int(11) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `assess_section` */

/*Table structure for table `blog` */

DROP TABLE IF EXISTS `blog`;

CREATE TABLE `blog` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `heading` varchar(50) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `blog` */

/*Table structure for table `blogComment` */

DROP TABLE IF EXISTS `blogComment`;

CREATE TABLE `blogComment` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `bid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `blogComment` */

/*Table structure for table `bookmarks` */

DROP TABLE IF EXISTS `bookmarks`;

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL DEFAULT '0',
  `url` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `bookmarks` */

/*Table structure for table `cache` */

DROP TABLE IF EXISTS `cache`;

CREATE TABLE `cache` (
  `cache_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `session_id` varchar(40) DEFAULT NULL,
  `cache_key` varchar(128) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `data` longtext NOT NULL,
  PRIMARY KEY (`cache_id`),
  KEY `user_id` (`user_id`),
  KEY `cache_key` (`cache_key`),
  KEY `session_id` (`session_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `cache` */

/*Table structure for table `category` */

DROP TABLE IF EXISTS `category`;

CREATE TABLE `category` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `parent` int(5) NOT NULL DEFAULT '0',
  `template` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `teacherid` int(4) NOT NULL DEFAULT '0',
  `code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `alt_code` (`code`),
  FULLTEXT KEY `fulltext` (`name`,`code`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Data for the table `category` */

insert  into `category`(`id`,`name`,`parent`,`template`,`teacherid`,`code`) values (3,'Root Folder',0,0,0,NULL);

/*Table structure for table `categoryCode` */

DROP TABLE IF EXISTS `categoryCode`;

CREATE TABLE `categoryCode` (
  `categoryID` int(11) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  UNIQUE KEY `categoryIDcode` (`categoryID`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `categoryCode` */

/*Table structure for table `chat` */

DROP TABLE IF EXISTS `chat`;

CREATE TABLE `chat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(20) NOT NULL,
  `time` datetime NOT NULL,
  `body` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `chat` */

/*Table structure for table `chat_poll` */

DROP TABLE IF EXISTS `chat_poll`;

CREATE TABLE `chat_poll` (
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `chat_poll` */

/*Table structure for table `cmsLog` */

DROP TABLE IF EXISTS `cmsLog`;

CREATE TABLE `cmsLog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object` enum('folder','file','link','myfile','myfolder') NOT NULL DEFAULT 'folder',
  `objectId` int(10) unsigned NOT NULL DEFAULT '0',
  `action` enum('create','modify','delete') NOT NULL DEFAULT 'create',
  `user` int(10) unsigned NOT NULL DEFAULT '0',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

/*Data for the table `cmsLog` */

insert  into `cmsLog`(`id`,`object`,`objectId`,`action`,`user`,`time`) values (1,'folder',2,'delete',1,'2011-05-05 00:20:53'),(2,'folder',1,'delete',1,'2011-05-05 00:21:16');

/*Table structure for table `config` */

DROP TABLE IF EXISTS `config`;

CREATE TABLE `config` (
  `configID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) DEFAULT NULL,
  `value` text,
  `title` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `field` enum('input','textarea','boolean','select','checkbox') DEFAULT 'input',
  `options` varchar(100) DEFAULT NULL,
  `type` enum('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB') DEFAULT NULL,
  `editable` enum('true','false') DEFAULT 'false',
  `hidden` enum('true','false') DEFAULT 'false',
  PRIMARY KEY (`configID`),
  KEY `configName` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=69 DEFAULT CHARSET=utf8;

/*Data for the table `config` */

insert  into `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`) values (1,'title','Schoolbox','Title','Title of site displayed at top of browser window','input',NULL,'Site','true','false'),(2,'school_name','Alaress Pty Ltd','School Name','Name of school displayed in footer','input',NULL,'Site','true','false'),(3,'school_adddress','http://www.alaress.com.au','School Website Address','Address of school website','input',NULL,'Site','true','false'),(4,'site_location','core','Site Identifier','Name of site used to identify school in custom code and templates','input',NULL,'Site','false','false'),(5,'site_hostname','core','Hostname','Hostname used to access server without the domain','input',NULL,'Site','true','false'),(6,'site_domain','portal.alaress-dev.com.au','Domain','Network domain that is used to access server remotely','input',NULL,'Site','true','false'),(7,'site_detect','campus','Template Detection','Method which template is detected','select','none:campus:domain','Site','true','false'),(8,'login_grace','60','Login Grace Time','Amount of time (secs) between failed logins before brute force lockout','input',NULL,'Security','true','false'),(9,'login_allowed','3','Failed Logins Allowed','Amount of logins allowed in login grace time before brute force lockout','input',NULL,'Security','true','false'),(10,'track_ip','0','Track Session IPs','Track session IPs, prevents session stealing but can cause problems with proxies','boolean',NULL,'Security','true','false'),(11,'session_length','1800','Session Expiry Time','Length of idle time (secs) before a user is logged out','input',NULL,'Security','true','false'),(12,'kerb_enable','1','Enable Kerberos SSO','Enable Kerberos SSO authentication.  This enables sessions to be created based on mod_auth_kerb authentication','boolean',NULL,'Security','true','false'),(13,'ldap_enable','0','Enable LDAP Authentication','Enable authentication via LDAP server. System will attempt first to bind to LDAP if this fails, it will fall back local authentication','boolean',NULL,'Security','true','false'),(14,'ldap_host','','LDAP Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false'),(15,'ldap_user','','LDAP Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false'),(16,'ldap_pass','','LDAP Password','Password for binding to LDAP server','input',NULL,'Security','true','false'),(17,'ldap_base','','LDAP Search Base','Base search path for username lookup','input',NULL,'Security','true','false'),(18,'ldap_host_alt','','LDAP Alternate Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false'),(19,'ldap_user_alt','','LDAP Alternate Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false'),(20,'ldap_base_alt','','LDAP Alternate Search Base','Base search path for username lookup','input',NULL,'Security','true','false'),(21,'ldap_username','SAMAccountName','LDAP Username Search Field','Field to search for username in LDAP','input',NULL,'Security','true','false'),(22,'template_dir','/var/www/schoolbox/www/conf/../../templates','Template Location','Location of templates','input',NULL,'Paths','false','false'),(23,'file_dir','/var/www/schoolbox/www/conf/../../data/files','File Location','Location of files uploaded to resources are stored','input',NULL,'Paths','false','false'),(24,'myfile_dir','/var/www/schoolbox/www/conf/../../data/myFiles','MyFiles Location','Location of personal files uploaded to my files or eportfolio are stored','input',NULL,'Paths','false','false'),(25,'submission_dir','/var/www/schoolbox/www/conf/../../data/submissions','Submission Location','Location of student submissions','input',NULL,'Paths','false','false'),(26,'returns_dir','/var/www/schoolbox/www/conf/../../data/returns','Returns Location','Location of teacher returned work','input',NULL,'Paths','false','false'),(27,'dropbox_dir','/var/www/schoolbox/www/conf/../../data/dropbox','Dropbox Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false'),(28,'zip_dir','/var/www/schoolbox/www/conf/../../data/zip','ZIP Temp Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false'),(29,'thumbnail_dir','/var/www/schoolbox/www/conf/../../data/thumbnails','Image Cache Location','Location of where thumbnails and resized images are stored after they are generated','input',NULL,'Paths','false','false'),(30,'xml_dir','/var/www/schoolbox/www/conf/../../data/cache/xml','XML Cache Location','Location of RSS and XML caching','input',NULL,'Paths','false','false'),(31,'thumbnail_size','100','Thumbnail pixel size','Maximum height/width of thumbnails generated for image gallery','input',NULL,'Resources','true','false'),(32,'file_upload_count','10','File Upload Limit','The total amount of files that can be uploaded via a single multifile upload page','input',NULL,'Resources','true','false'),(33,'max_file_size','10485760','File Size Limit','The maximum size (bytes) of a single file that can be uploaded','input',NULL,'Resources','true','false'),(34,'news_edit_other','1','Allow Others to Edit News','Controls whether users with write access to a page can edit each others news posts','boolean',NULL,'Resources','true','false'),(35,'group_staff_only','1','Allow Students to be Group Admins','Allow users in student groups to become group admins, beware of the consequences','boolean',NULL,'Resources','true','false'),(36,'webdav_enable','0','Enable WebDAV connector in MyFiles','Turns on webDAV in the MyFiles area','boolean',NULL,'Resources','false','false'),(37,'search_all_limit','3','Search Summary Item Limit','The amount of items shown for each section on the all tab in the search','boolean',NULL,'Resources','false','false'),(38,'search_type_limit','15','Search Items Per Page','The amount of results shown per page in the search results','boolean',NULL,'Resources','false','false'),(39,'welcome_image_folder','140','Image Rotation Folder ID','The welcome page image scroller utilises this to find images','input',NULL,'Welcome','true','false'),(40,'welcome_rss_url','http://www.abc.net.au/news/indexes/justin/rss.xml','URL for RSS','The URL for the RSS feed displayed on the welcome page','input',NULL,'Welcome','true','false'),(41,'welcome_rss_limit','6','RSS Item Limit','The amount of items displayed from the RSS feed on the welcome page','input',NULL,'Welcome','true','false'),(42,'weather_enable','1','Enable Weather','Reguarily fetches weather data from Google weather feeds','boolean',NULL,'Welcome','true','false'),(43,'weather_location','Melbourne, Victoria','Weather Location','By default display weather at this location','input',NULL,'Welcome','true','false'),(44,'name_format','{title} {firstname} {lastname}','Name Output Format','Format of names available fields; {title}, {firstname}, {lastname}','input',NULL,'Localisation','true','false'),(45,'calendar_time_format','%l:%i%p','Calendar Time Format','Time output style 24 hour = \'%k:%i\', 12 hour = \'%l:%i%p\'','input',NULL,'Localisation','true','false'),(46,'date_long','l F jS Y g:ia','Long Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false'),(47,'date_short','jS M Y g:ia','Short Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false'),(48,'date_database','Y-m-d H:i:s','Database Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false'),(49,'date_notime','jS M Y','Date with no Time Format','A PHP date() time format string','input',NULL,'Localisation','true','false'),(50,'external_enable','0','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records','boolean',NULL,'External DB','true','false'),(51,'external_type','tass','Database Provider','The name of the database product currently being used','select','synergetic:tass','External DB','true','false'),(52,'external_db_host','synergy','Database Host','The server hostname where SQL database is located','input',NULL,'External DB','true','false'),(53,'external_db_user','intranet','Database User','SQL user with read access to the required tables, see documentation','input',NULL,'External DB','true','false'),(54,'external_db_passwd','Qu4LitY','Database Password','SQL user password','input',NULL,'External DB','true','false'),(55,'external_db_db','SynergyOne','Database Name','The name of the database on the SQL server','input',NULL,'External DB','true','false'),(56,'external_portal','http://synergy.portal.com','External DB Portal URL','Schoolbox can integrate with your existing school database to fetch timetable and student records','input',NULL,'External DB','true','false'),(57,'external_sync_data','0','Syncronise Data','Controls if we should syncronise the emails with school database, currently only works for parents','boolean',NULL,'External DB','true','false'),(58,'mail_enable','1','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records','boolean','','Email','true','false'),(59,'mail_type','roundcube','Mail System','Simple send mail capability, or full imap support with roundcube webmail','select','simple:roundcube','Email','true','false'),(60,'mail_host','ssl://mail.alaress.com.au','Mail Server','Location of IMAP mail server for use with roundcube','input','','Email','true','false'),(61,'mail_host_port','993','Mail Host Port','Port of mail server, typically 143','input','','Email','true','false'),(62,'mail_smtp_host','ssl://mail.alaress.com.au','SMTP Server','Address of server to send email via, this should be your email gateway','input','','Email','true','false'),(63,'mail_smtp_port','465','SMTP Port','Port of the SMTP server, typically 25','input','','Email','true','false'),(64,'mail_address','alaress.com.au','Email Domain','Email domains with which emails should originate from, if no alt emails are specfied email will be username@domain','input','','Email','true','false'),(65,'mail_webmail','','Webmail URL','URL to access OWA or other existing webmail service.  Should be accessible internally and externally','input','','Email','true','false'),(66,'mail_from_address','portal@lektec.com.au','Email From Address','Emails that originate from Schoolbox will come from this address','input','','Email','true','false'),(67,'mail_from_name','Schoolbox','Email From Name','Emails that originate from Schoolbox will come from this name','input','','Email','true','false'),(68,'mail_support_address','support@alaress.com.au','Portal Feedback Address','Requests sent to the Schoolbox feedback form will be sent to this address','input','','Email','true','false');

/*Table structure for table `dropbox` */

DROP TABLE IF EXISTS `dropbox`;

CREATE TABLE `dropbox` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT '0',
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `realname` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(40) DEFAULT NULL,
  `deleted` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`category`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `dropbox` */

/*Table structure for table `eportfolioTemplateLayout` */

DROP TABLE IF EXISTS `eportfolioTemplateLayout`;

CREATE TABLE `eportfolioTemplateLayout` (
  `templateID` int(11) DEFAULT NULL,
  `componentId` int(11) DEFAULT NULL,
  `column` smallint(6) DEFAULT NULL,
  `position` smallint(6) DEFAULT NULL,
  UNIQUE KEY `table` (`templateID`,`componentId`,`column`,`position`),
  KEY `homepageID` (`templateID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eportfolioTemplateLayout` */

/*Table structure for table `eportfolio_blog` */

DROP TABLE IF EXISTS `eportfolio_blog`;

CREATE TABLE `eportfolio_blog` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `heading` varchar(50) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_blog` */

/*Table structure for table `eportfolio_blogComment` */

DROP TABLE IF EXISTS `eportfolio_blogComment`;

CREATE TABLE `eportfolio_blogComment` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `bid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_blogComment` */

/*Table structure for table `eportfolio_comp` */

DROP TABLE IF EXISTS `eportfolio_comp`;

CREATE TABLE `eportfolio_comp` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `size` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `method` varchar(18) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `description` mediumtext,
  `eportfolioCategoryID` int(10) unsigned NOT NULL,
  `filename` varchar(22) DEFAULT NULL,
  `config` mediumtext NOT NULL,
  `invisible` enum('true','false') NOT NULL DEFAULT 'false',
  `allowMultiple` enum('true','false') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Data for the table `eportfolio_comp` */

insert  into `eportfolio_comp`(`id`,`size`,`method`,`name`,`description`,`eportfolioCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) values (1,1,'outputLinkList','Links','Web addresses.',0,'links_n.htm','','false',NULL),(2,1,'outputTextBox','Text Box','Text box\r\n',0,'textBox.html','','false',NULL),(3,1,'outputBlog','Blog','Personal blog.',0,'blog.html','\r\n','false',NULL),(4,1,'outputFileList','Published Files','Files that you have published to your ePortfolio.',0,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:11:\"Description\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false',NULL),(5,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location',0,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false',NULL),(6,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this ePortfolio',0,'gallery.htm','','false',NULL);

/*Table structure for table `eportfolio_comp_category` */

DROP TABLE IF EXISTS `eportfolio_comp_category`;

CREATE TABLE `eportfolio_comp_category` (
  `eportfolioComponentCategoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`eportfolioComponentCategoryID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_comp_category` */

/*Table structure for table `eportfolio_comp_location` */

DROP TABLE IF EXISTS `eportfolio_comp_location`;

CREATE TABLE `eportfolio_comp_location` (
  `eportfolioComponentLocationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(6) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `column` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `component` int(11) unsigned NOT NULL DEFAULT '0',
  `config` mediumtext NOT NULL,
  PRIMARY KEY (`eportfolioComponentLocationID`),
  KEY `cid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_comp_location` */

/*Table structure for table `eportfolio_config` */

DROP TABLE IF EXISTS `eportfolio_config`;

CREATE TABLE `eportfolio_config` (
  `uid` int(6) unsigned NOT NULL DEFAULT '0',
  `template` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `left_head_font` char(7) DEFAULT NULL,
  `left_head_bg` char(7) DEFAULT NULL,
  `left_font` char(7) DEFAULT NULL,
  `left_bg_primary` char(7) DEFAULT NULL,
  `left_bg_secondary` char(7) DEFAULT NULL,
  `left_link` char(7) DEFAULT NULL,
  `left_hover` char(7) DEFAULT NULL,
  `right_head_font` char(7) DEFAULT NULL,
  `right_head_bg` char(7) DEFAULT NULL,
  `right_font` char(7) DEFAULT NULL,
  `right_bg_primary` char(6) DEFAULT NULL,
  `right_bg_secondary` char(7) DEFAULT NULL,
  `right_link` char(7) DEFAULT NULL,
  `right_hover` char(7) DEFAULT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_config` */

/*Table structure for table `eportfolio_link` */

DROP TABLE IF EXISTS `eportfolio_link`;

CREATE TABLE `eportfolio_link` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `owner` int(5) unsigned NOT NULL DEFAULT '0',
  `url` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` mediumtext,
  `hidden` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`url`,`description`,`comment`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_link` */

/*Table structure for table `eportfolio_log` */

DROP TABLE IF EXISTS `eportfolio_log`;

CREATE TABLE `eportfolio_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT '0',
  `uid` int(6) NOT NULL DEFAULT '0',
  `hitcount` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`user`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_log` */

/*Table structure for table `eportfolio_privateNotes` */

DROP TABLE IF EXISTS `eportfolio_privateNotes`;

CREATE TABLE `eportfolio_privateNotes` (
  `uid` int(8) DEFAULT NULL,
  `privateNotes` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_privateNotes` */

/*Table structure for table `eportfolio_textBox` */

DROP TABLE IF EXISTS `eportfolio_textBox`;

CREATE TABLE `eportfolio_textBox` (
  `textBoxID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) DEFAULT NULL,
  `heading` varchar(100) DEFAULT NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`textBoxID`),
  KEY `categoryID` (`userID`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

/*Data for the table `eportfolio_textBox` */

/*Table structure for table `error` */

DROP TABLE IF EXISTS `error`;

CREATE TABLE `error` (
  `id` int(3) unsigned NOT NULL DEFAULT '0',
  `message` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `error` */

insert into `error` (`id`, `message`) values('1','You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.');
insert into `error` (`id`, `message`) values('2','Not enough information was submitted to the form.');
insert into `error` (`id`, `message`) values('3','Please enter either a file or a url, not both.');
insert into `error` (`id`, `message`) values('4','Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the portal.<br><br>Please contact your System Administrator if problems persist.');
insert into `error` (`id`, `message`) values('5','You do not have permission to download this file.');
insert into `error` (`id`, `message`) values('6','You do not have write permission to this category.');
insert into `error` (`id`, `message`) values('7','Mail cannot be sent at this time. Please try again later.');
insert into `error` (`id`, `message`) values('8','You do not have permission to delete this resource.');
insert into `error` (`id`, `message`) values('9','Processing failed for the following reason: %MESSAGE%');
insert into `error` (`id`, `message`) values('10','The size of this file exceeds the allowed limit.');
insert into `error` (`id`, `message`) values('11','%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Portal.<br>If you would like to be able to add files of this type to the Portal please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Portal.');
insert into `error` (`id`, `message`) values('12','The category that you are trying to delete is not empty. Please delete all its contents, then try again.');
insert into `error` (`id`, `message`) values('13','The category that you are trying to view does not exist.');
insert into `error` (`id`, `message`) values('14','You do not have permission to view this category.');
insert into `error` (`id`, `message`) values('15','The date that you have submitted is invalid. Please check it, then try again.');
insert into `error` (`id`, `message`) values('16','There was a problem validating your account: <br><b>%MESSAGE%</b>');
insert into `error` (`id`, `message`) values('17','The submission box that you are trying to access does not exist or you do not have permission to view it.');
insert into `error` (`id`, `message`) values('18','The subject selected does not exist.');
insert into `error` (`id`, `message`) values('19','You do not have permission to view this page.');
insert into `error` (`id`, `message`) values('20','You do not have permission to create a category in this location.');
insert into `error` (`id`, `message`) values('21','A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%');
insert into `error` (`id`, `message`) values('22','The database is unavailable at this time. Please try again later.');
insert into `error` (`id`, `message`) values('23','You do not have permission to modify this news.');
insert into `error` (`id`, `message`) values('24','You are not enrolled in this subject.');
insert into `error` (`id`, `message`) values('25','You do not have permission to return this work to this student.');
insert into `error` (`id`, `message`) values('26','The work you are trying to return either does not exist or has already been returned.');
insert into `error` (`id`, `message`) values('27','The work you are trying to submit has already been submitted.');
insert into `error` (`id`, `message`) values('28','You do not have permission to post in this category.');
insert into `error` (`id`, `message`) values('29','You do not have permission to modify or delete this post.');
insert into `error` (`id`, `message`) values('30','This user cannot be the group administrator.');
insert into `error` (`id`, `message`) values('31','No user was found that matches your request.');
insert into `error` (`id`, `message`) values('32','The absentee system cannot be accessed on the weekend.');
insert into `error` (`id`, `message`) values('33','That period does not exist today.');
insert into `error` (`id`, `message`) values('34','You do not have permission to modify this event.');
insert into `error` (`id`, `message`) values('35','You do not have permission to add an event of this context.');
insert into `error` (`id`, `message`) values('36','You are attempting to submit work to a submission box that has not yet opened.');
insert into `error` (`id`, `message`) values('37','You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.');
insert into `error` (`id`, `message`) values('38','This is already in the database. Please modify, then try again.');
insert into `error` (`id`, `message`) values('39','No teams exist. You must add teams before trying to add events.');
insert into `error` (`id`, `message`) values('40','Attendances for Grimwade have not yet been implemented. Please wait until further notice.');
insert into `error` (`id`, `message`) values('41','That file does not exist.');
insert into `error` (`id`, `message`) values('42','No email account information was found. Please either use an external mail client to send email, or provide the portal with an email address.');
insert into `error` (`id`, `message`) values('43','The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.');
insert into `error` (`id`, `message`) values('44','You are trying to view or delete work that either does not exist or does not belong to you.');
insert into `error` (`id`, `message`) values('45','Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".');
insert into `error` (`id`, `message`) values('46','You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.');
insert into `error` (`id`, `message`) values('101','The resource you are trying to access does not exist or you do not have permission to view it.');
insert into `error` (`id`, `message`) values('102','A booking already exists during the time you have selected.  Please press back and select a time that is free.');
insert into `error` (`id`, `message`) values('103','The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.');
insert into `error` (`id`, `message`) values('104','You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.');
insert into `error` (`id`, `message`) values('47','You do not have permission to modify this user\'s file.');
insert into `error` (`id`, `message`) values('48','You do not have permission to delete this user\'s file.');
insert into `error` (`id`, `message`) values('49','The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.');
insert into `error` (`id`, `message`) values('105','<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>');
insert into `error` (`id`, `message`) values('51','The section you are trying to add questions to does not exist.');
insert into `error` (`id`, `message`) values('106','You do not have permission to modify or delete this link.');
insert into `error` (`id`, `message`) values('107','You do not have permission to modify this blog.');
insert into `error` (`id`, `message`) values('108','You do not have permission to leave comments on this blog.');
insert into `error` (`id`, `message`) values('109','You do not have permission to publish this file.');
insert into `error` (`id`, `message`) values('110','You do not have permission to modify these notes.');
insert into `error` (`id`, `message`) values('111','There are no blogs in this archive.');
insert into `error` (`id`, `message`) values('112','This assessment no longer exists.');
insert into `error` (`id`, `message`) values('113','You have not answered all questions; please go back and submit them.');
insert into `error` (`id`, `message`) values('114','You have not selected an attempt to review.');
insert into `error` (`id`, `message`) values('116','Unspecified question type.');
insert into `error` (`id`, `message`) values('117','Please choose a group for this assessment.');
insert into `error` (`id`, `message`) values('118','This attempt no longer exists.');
insert into `error` (`id`, `message`) values('119','This question no longer exists.');
insert into `error` (`id`, `message`) values('120','This online assessment has expired.');
insert into `error` (`id`, `message`) values('121','You have already attempted this online assessment and cannot re-sit it.');
insert into `error` (`id`, `message`) values('122','You have run out of time to complete this online assessment and cannot re-sit it.');
insert into `error` (`id`, `message`) values('123','This attempt has not been recorded.');
insert into `error` (`id`, `message`) values('115','This section no longer exists.');
insert into `error` (`id`, `message`) values('124','You are the owner of this test and therefore cannot attempt it.');
insert into `error` (`id`, `message`) values('125','Only the test owner can view this page.');
insert into `error` (`id`, `message`) values('126','Only teachers can create and modify tests.');
insert into `error` (`id`, `message`) values('127','Only the test owner can review this test.');
insert into `error` (`id`, `message`) values('128','This attempt no longer exists.');
insert into `error` (`id`, `message`) values('129','You are not allowed to view this students test.');
insert into `error` (`id`, `message`) values('130','Only teachers can create and modify their test contents.');
insert into `error` (`id`, `message`) values('131','Question could not be saved.');
insert into `error` (`id`, `message`) values('132','Answer could not be saved.');
insert into `error` (`id`, `message`) values('133','Could not load question: unknown type.');
insert into `error` (`id`, `message`) values('100','Your e-portfolio has not been enabled.');
insert into `error` (`id`, `message`) values('134','No import file found.');
insert into `error` (`id`, `message`) values('50','You do not have permission to delete this user\'s file.');
insert into `error` (`id`, `message`) values('52','This link is already shared in your ePortfolio.');
/*Table structure for table `event` */

DROP TABLE IF EXISTS `event`;

CREATE TABLE `event` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `owner` int(6) NOT NULL DEFAULT '0',
  `access` tinyint(1) NOT NULL DEFAULT '0',
  `sid` int(6) DEFAULT NULL,
  `priority` smallint(1) NOT NULL DEFAULT '0',
  `type` int(10) unsigned DEFAULT NULL,
  `note` varchar(250) NOT NULL DEFAULT '',
  `date` date NOT NULL DEFAULT '0000-00-00',
  `startTime` time DEFAULT NULL,
  `endTime` time DEFAULT NULL,
  `detail` varchar(250) DEFAULT NULL,
  `location` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `access` (`access`,`sid`),
  KEY `owner` (`owner`),
  KEY `date` (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `event` */

/*Table structure for table `eventCampus` */

DROP TABLE IF EXISTS `eventCampus`;

CREATE TABLE `eventCampus` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `campusID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eventID`,`campusID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eventCampus` */

/*Table structure for table `eventGroup` */

DROP TABLE IF EXISTS `eventGroup`;

CREATE TABLE `eventGroup` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `groupID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eventID`,`groupID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `eventGroup` */

/*Table structure for table `file` */

DROP TABLE IF EXISTS `file`;

CREATE TABLE `file` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) DEFAULT NULL,
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `realname` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` mediumtext,
  `type` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `deleted` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  FULLTEXT KEY `fulltext` (`realname`,`description`,`comment`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `file` */

/*Table structure for table `file_type` */

DROP TABLE IF EXISTS `file_type`;

CREATE TABLE `file_type` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

/*Data for the table `file_type` */

insert  into `file_type`(`id`,`name`) values (1,'Assessment Task'),(2,'Assignment'),(3,'Homework'),(6,'Research Project'),(5,'Essay'),(7,'Class Work'),(8,'Presentation'),(9,'Oral'),(10,'Extension'),(11,'Online Assessment');

/*Table structure for table `forum` */

DROP TABLE IF EXISTS `forum`;

CREATE TABLE `forum` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `owner` int(6) unsigned NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `heading` varchar(250) NOT NULL DEFAULT '',
  `message` mediumtext,
  `parent` int(6) NOT NULL DEFAULT '0',
  `cid` int(6) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `thread` int(6) unsigned NOT NULL DEFAULT '0',
  `last_post_id` int(11) DEFAULT NULL,
  `last_post_title` varchar(200) DEFAULT NULL,
  `last_post_time` datetime DEFAULT NULL,
  `views` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `category` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `forum` */

/*Table structure for table `forumPost` */

DROP TABLE IF EXISTS `forumPost`;

CREATE TABLE `forumPost` (
  `postID` int(11) NOT NULL AUTO_INCREMENT,
  `threadID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  `modified` datetime DEFAULT NULL,
  `censored` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`postID`),
  KEY `threadID` (`threadID`),
  KEY `userID` (`userID`),
  FULLTEXT KEY `fulltext` (`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `forumPost` */

/*Table structure for table `forumThread` */

DROP TABLE IF EXISTS `forumThread`;

CREATE TABLE `forumThread` (
  `threadID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `title` varchar(250) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  `locked` enum('true','false') NOT NULL DEFAULT 'false',
  `views` int(11) NOT NULL,
  `last_post_id` int(11) DEFAULT NULL,
  `last_post_date` datetime DEFAULT NULL,
  `last_post_user_id` int(11) DEFAULT NULL,
  `last_post_user` varchar(250) DEFAULT NULL,
  `tempID` int(11) DEFAULT NULL,
  PRIMARY KEY (`threadID`),
  KEY `categoryID` (`categoryID`),
  KEY `userID` (`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `forumThread` */

/*Table structure for table `groups` */

DROP TABLE IF EXISTS `groups`;

CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL DEFAULT '',
  `staff` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

/*Data for the table `groups` */

insert  into `groups`(`id`,`name`,`staff`) values (1,'Administration',1),(2,'Teachers',1),(3,'Students',0),(4,'Parents',0);

/*Table structure for table `homepageComponentCategory` */

DROP TABLE IF EXISTS `homepageComponentCategory`;

CREATE TABLE `homepageComponentCategory` (
  `homepageComponentCategoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  PRIMARY KEY (`homepageComponentCategoryID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

/*Data for the table `homepageComponentCategory` */

insert  into `homepageComponentCategory`(`homepageComponentCategoryID`,`name`) values (1,'Information'),(2,'Collaboration'),(3,'Learning'),(4,'Images'),(5,'Resources'),(6,'Feeds');

/*Table structure for table `homepageTemplate` */

DROP TABLE IF EXISTS `homepageTemplate`;

CREATE TABLE `homepageTemplate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `layout` smallint(6) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

/*Data for the table `homepageTemplate` */

insert  into `homepageTemplate`(`id`,`name`,`layout`) values (1,'Class',1),(2,'Collab Group',1),(4,'Faculty',1),(3,'Extra-Curricular',1),(5,'Committee',1),(6,'House',1),(7,'Blog',1);

/*Table structure for table `homepageTemplateLayout` */

DROP TABLE IF EXISTS `homepageTemplateLayout`;

CREATE TABLE `homepageTemplateLayout` (
  `templateID` int(11) DEFAULT NULL,
  `componentId` int(11) DEFAULT NULL,
  `column` smallint(6) DEFAULT NULL,
  `position` smallint(6) DEFAULT NULL,
  UNIQUE KEY `table` (`templateID`,`componentId`,`column`,`position`),
  KEY `homepageID` (`templateID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `homepageTemplateLayout` */

insert  into `homepageTemplateLayout`(`templateID`,`componentId`,`column`,`position`) values (1,2,2,3),(1,3,1,3),(1,8,1,1),(1,9,2,5),(1,10,2,6),(1,25,2,2),(1,28,1,2),(1,30,2,1),(1,35,2,4),(2,3,1,3),(2,4,2,1),(2,5,2,3),(2,8,1,1),(2,10,2,5),(2,21,2,2),(2,34,1,2),(2,35,2,4),(3,2,2,5),(3,3,1,3),(3,4,2,2),(3,5,2,3),(3,8,1,2),(3,10,2,6),(3,21,2,1),(3,23,1,1),(3,33,2,4),(3,35,1,4),(4,2,2,3),(4,3,1,3),(4,8,1,2),(4,10,2,4),(4,13,2,1),(4,15,1,1),(4,20,2,2),(5,3,1,3),(5,4,2,2),(5,5,2,3),(5,8,1,1),(5,10,2,5),(5,21,2,2),(5,34,1,2),(5,35,2,4),(6,3,1,3),(6,4,2,1),(6,5,2,3),(6,8,1,1),(6,10,2,5),(6,21,2,2),(6,34,1,2),(6,35,2,4),(7,2,2,2),(7,10,2,3),(7,33,2,1),(7,39,1,1);

/*Table structure for table `homepage_comp` */

DROP TABLE IF EXISTS `homepage_comp`;

CREATE TABLE `homepage_comp` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `size` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `method` varchar(18) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `description` mediumtext,
  `homepageCategoryID` int(10) unsigned NOT NULL,
  `filename` varchar(22) DEFAULT NULL,
  `config` mediumtext NOT NULL,
  `invisible` enum('true','false') NOT NULL DEFAULT 'false',
  `allowMultiple` enum('true','false') DEFAULT 'false',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=44 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Data for the table `homepage_comp` */

insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) values (8,1,'outputNews','News','Provides a text area for you to post information, news, blogs and introductions. ',1,'news_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(2,1,'outputLinkList','Links','Provide links to interesting, related and helpful resource on the portal or the WWW.',5,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(3,1,'outputFileList','Files','List general files that have been uploaded to your homepage. The large version shows both titles and comments.',5,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}','false','false'),(4,2,'outputMembers','Group Members','Show the group members associated with your homepage.  This also provides an email all group members link.',2,'members_s.htm','','false','false'),(5,2,'outputForumList','Forum','Show recent discussion topics going on in your homepages forum.',2,'forum_s.htm','a:2:{s:4:\"sort\";s:4:\"Date\";s:4:\"size\";i:0;}','false','false'),(6,2,'outputLinkList','Small Links','Provide links to interesting, related and helpful resource on the portal or the WWW.',5,'links_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(7,2,'outputFileList','Small Files','List general files that have been uploaded to your homepage. The small version only shows file titles.',5,'files_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(9,2,'initSubject','Class Management','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.',3,'class_manage.htm','','false','false'),(10,2,'folderManage','Folder Management','Provides links to browse the files, manage group membership and view homepage statistics.',5,'folder_manage.htm','','false','false'),(11,2,'outputAssignList','Small Assignment','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.',3,'assign_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(12,2,'outputHomeworkList','Small Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.',3,'homework_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(13,2,'outputManagers','Group Managers','Displays a list of group members with write access to the homepage.  Useful in larger groups to show manages of the group.',2,'staff_s.htm','','false','false'),(14,1,'outputImage','Image Simple','Just displays an image on your page.',4,'image_s.htm','','false','false'),(15,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location',4,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(16,1,'outputAssignList','Assignments','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.',3,'assign_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(17,1,'outputHomeworkList','Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.',3,'homework_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(20,2,'outputFolderList','Folders','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.',5,'folders_s.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(21,1,'outputEventList','Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(22,2,'outputEventList','Small Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(23,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ',4,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(24,1,'outputRandImage','Random Image','Display a random image selected from the images on this homepage.',4,'image_s.htm','a:1:{s:5:\"pixel\";i:400;}','false','false'),(25,2,'outputDropBox','File Drop Box','Provides an area for teachers to recieve work via the portal without the need for a submission box.',3,'dropbox.htm','','false','false'),(28,1,'outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.',3,'duework_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(27,2,'outputRSS','Small RSS Feed','Display external RSS site headlines and brief description. The RSS feed can be from any site that provides a feed.',6,'rss_s.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','true','false'),(26,1,'outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.',6,'rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','false','true'),(30,2,'outputTeachers','Class Teachers','Show a list of teachers responsible for this subject if it has a synergetic subject code.',3,'teacher_s.htm','','false','false'),(29,2,'outputDueWorkList','Small Due Work','Shows a list of all due work and its due date.',3,'duework_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(31,1,'outputCalendarList','Campus Calendar','Show a calendar for the entire campus on your homepage.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(32,0,'outputDuties','Duties',NULL,1,'duties.htm','','true','false'),(33,2,'outputPoll','Poll','Create a poll to determine group members opinion on a given subject.',2,'poll.htm','a:1:{s:6:\"pollID\";i:0;}','false','true'),(34,1,'outputWiki','Wiki','Provides an area for group members to work together on joint documents.',2,'wiki.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(35,1,'outputFeed','Homepage Feed','Provides a running commentary on recent changes to the homepage',6,'feed.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(36,1,'outputTextBox','Text Box','Use this space to write introductory message or information relating to the content that is displayed',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(37,1,'outputAudio','Audio','Lists audio files and allows them to be played.',5,'audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(38,1,'outputPodcast','Podcast','Subscribe to a podcast ',6,'podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false','true'),(39,1,'outputBlog','Blog','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),(40,2,'outputTranslate','Language Translator','Translates text and webpages into another language',3,'translate.html','','false','false'),(41,1,'outputScroller','Image Scroller','An image carousel.',4,'scroller.html','a:1:{s:5:\"pixel\";i:400;}','false','false'),(42,1,'outputAssess','Online Assessment','Online tests and quizzes.',3,'assess.html','','false','false'),(43,0,'outputChat','Chat','Allows users who are viewing the same homepage to chat with each other.',2,'chat.html','a:2:{s:5:\"state\";s:3:\"off\";s:17:\"teacher_timestamp\";s:0:\"\";}','false','false');

/*Table structure for table `homepage_comp_location` */

DROP TABLE IF EXISTS `homepage_comp_location`;

CREATE TABLE `homepage_comp_location` (
  `homepageComponentLocationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(6) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `column` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `component` int(11) unsigned NOT NULL DEFAULT '0',
  `config` mediumtext NOT NULL,
  PRIMARY KEY (`homepageComponentLocationID`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `homepage_comp_location` */

/*Table structure for table `homepage_config` */

DROP TABLE IF EXISTS `homepage_config`;

CREATE TABLE `homepage_config` (
  `cid` int(6) unsigned NOT NULL DEFAULT '0',
  `template` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `left_head_font` char(7) DEFAULT NULL,
  `left_head_bg` char(7) DEFAULT NULL,
  `left_font` char(7) DEFAULT NULL,
  `left_bg_primary` char(7) DEFAULT NULL,
  `left_bg_secondary` char(7) DEFAULT NULL,
  `left_link` char(7) DEFAULT NULL,
  `left_hover` char(7) DEFAULT NULL,
  `right_head_font` char(7) DEFAULT NULL,
  `right_head_bg` char(7) DEFAULT NULL,
  `right_font` char(7) DEFAULT NULL,
  `right_bg_primary` char(6) DEFAULT NULL,
  `right_bg_secondary` char(7) DEFAULT NULL,
  `right_link` char(7) DEFAULT NULL,
  `right_hover` char(7) DEFAULT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `homepage_config` */

/*Table structure for table `homepage_log` */

DROP TABLE IF EXISTS `homepage_log`;

CREATE TABLE `homepage_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT '0',
  `cid` int(6) NOT NULL DEFAULT '0',
  `hitcount` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`user`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `homepage_log` */

insert  into `homepage_log`(`datetime`,`user`,`cid`,`hitcount`) values ('2011-05-05 00:21:12',1,1,1),('2011-05-05 00:20:48',1,2,0),('2011-05-05 00:39:34',1,3,3);

/*Table structure for table `house` */

DROP TABLE IF EXISTS `house`;

CREATE TABLE `house` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `house` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;

/*Data for the table `house` */

/*Table structure for table `link` */

DROP TABLE IF EXISTS `link`;

CREATE TABLE `link` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT '0',
  `owner` int(5) unsigned NOT NULL DEFAULT '0',
  `url` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` mediumtext,
  `hidden` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  FULLTEXT KEY `fulltext` (`url`,`description`,`comment`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `link` */

/*Table structure for table `login_log` */

DROP TABLE IF EXISTS `login_log`;

CREATE TABLE `login_log` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL DEFAULT '',
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip` varchar(15) NOT NULL DEFAULT '',
  `login` tinyint(1) unsigned DEFAULT NULL,
  `logout` tinyint(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

/*Data for the table `login_log` */

insert  into `login_log`(`id`,`username`,`date`,`ip`,`login`,`logout`) values (1,'jleckie','2011-05-05 00:04:51','127.0.0.1',1,NULL),(2,'jleckie','2011-05-05 00:05:34','127.0.0.1',1,NULL),(3,'alaress','2011-05-05 00:50:32','127.0.0.1',1,NULL);

/*Table structure for table `luCampus` */

DROP TABLE IF EXISTS `luCampus`;

CREATE TABLE `luCampus` (
  `name` varchar(255) DEFAULT NULL,
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

/*Data for the table `luCampus` */

insert  into `luCampus`(`name`,`id`) values ('Main',1),('Other',2);

/*Table structure for table `luEventType` */

DROP TABLE IF EXISTS `luEventType`;

CREATE TABLE `luEventType` (
  `eventTypeID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventType` varchar(100) DEFAULT NULL,
  `eventColour` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`eventTypeID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

/*Data for the table `luEventType` */

insert  into `luEventType`(`eventTypeID`,`eventType`,`eventColour`) values (1,'Event','cee6ea'),(2,'Excursion','f8deb8'),(3,'Report','d4efc4'),(4,'Exam','F2CED6'),(5,'Meeting','d2cef2'),(6,'Online Assessment','fff3b2');

/*Table structure for table `luServices` */

DROP TABLE IF EXISTS `luServices`;

CREATE TABLE `luServices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

/*Data for the table `luServices` */

insert  into `luServices`(`id`,`name`) values (1,'Homepage'),(2,'Calendar'),(3,'News'),(4,'Tutorial'),(5,'CMS');

/*Table structure for table `luTitle` */

DROP TABLE IF EXISTS `luTitle`;

CREATE TABLE `luTitle` (
  `titleID` int(11) NOT NULL AUTO_INCREMENT,
  `titleName` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`titleID`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Data for the table `luTitle` */

insert  into `luTitle`(`titleID`,`titleName`) values (2,'Mr'),(3,'Mrs'),(4,'Ms'),(9,'Prof'),(8,'Hon Dr'),(7,'Hon'),(6,'Dr'),(5,'Miss'),(10,'A/Prof'),(1,NULL);

/*Table structure for table `messages` */

DROP TABLE IF EXISTS `messages`;

CREATE TABLE `messages` (
  `message_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `cache_key` varchar(128) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `idx` int(11) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `from` varchar(255) NOT NULL DEFAULT '',
  `to` varchar(255) NOT NULL DEFAULT '',
  `cc` varchar(255) NOT NULL DEFAULT '',
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `size` int(11) unsigned NOT NULL DEFAULT '0',
  `headers` mediumtext NOT NULL,
  `structure` mediumtext,
  PRIMARY KEY (`message_id`),
  UNIQUE KEY `uniqueness` (`user_id`,`cache_key`,`uid`),
  KEY `user_id` (`user_id`),
  KEY `idx` (`idx`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `messages` */

/*Table structure for table `myFile` */

DROP TABLE IF EXISTS `myFile`;

CREATE TABLE `myFile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent` int(11) NOT NULL DEFAULT '0',
  `owner` int(11) NOT NULL DEFAULT '0',
  `realname` tinytext NOT NULL,
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `description` longtext NOT NULL,
  `file_size` int(11) NOT NULL DEFAULT '0',
  `mime_type` tinytext NOT NULL,
  `deleted` int(11) NOT NULL DEFAULT '0',
  `published` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `myFile` */

/*Table structure for table `myFolder` */

DROP TABLE IF EXISTS `myFolder`;

CREATE TABLE `myFolder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL DEFAULT '0',
  `parent` int(11) NOT NULL DEFAULT '0',
  `name` mediumtext NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `myFolder` */

/*Table structure for table `newsCampus` */

DROP TABLE IF EXISTS `newsCampus`;

CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL DEFAULT '0',
  `campus` int(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `newsCampus` */

/*Table structure for table `newsGroup` */

DROP TABLE IF EXISTS `newsGroup`;

CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL DEFAULT '0',
  `gid` int(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `newsGroup` */

/*Table structure for table `newsTopic` */

DROP TABLE IF EXISTS `newsTopic`;

CREATE TABLE `newsTopic` (
  `newsTopicID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`newsTopicID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

/*Data for the table `newsTopic` */

insert  into `newsTopic`(`newsTopicID`,`name`) values (1,'General'),(2,'Music'),(3,'Sport'),(4,'Academic');

/*Table structure for table `newsUser` */

DROP TABLE IF EXISTS `newsUser`;

CREATE TABLE `newsUser` (
  `newsID` int(10) unsigned NOT NULL,
  `userID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`newsID`,`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `newsUser` */

/*Table structure for table `news_items` */

DROP TABLE IF EXISTS `news_items`;

CREATE TABLE `news_items` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_topic_id` smallint(5) unsigned DEFAULT '0',
  `item_heading` varchar(50) NOT NULL DEFAULT '',
  `item_datetime_posted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `item_datetime_expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `item_datetime_modified` datetime DEFAULT NULL,
  `item_mainpage` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `item_body` mediumtext NOT NULL,
  `item_deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `item_owner` int(6) unsigned NOT NULL DEFAULT '0',
  `item_campus` int(4) DEFAULT '0',
  `item_group` int(4) unsigned DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `access` (`item_topic_id`,`item_campus`),
  FULLTEXT KEY `fulltext` (`item_heading`,`item_body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `news_items` */

/*Table structure for table `perms` */

DROP TABLE IF EXISTS `perms`;

CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL DEFAULT '0',
  `gid` int(4) NOT NULL DEFAULT '0',
  `type` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `perms` */

/*Table structure for table `poll` */

DROP TABLE IF EXISTS `poll`;

CREATE TABLE `poll` (
  `pollID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) DEFAULT NULL,
  `title` varchar(120) DEFAULT NULL,
  `question` varchar(250) DEFAULT NULL,
  `option_0` varchar(120) DEFAULT NULL,
  `option_1` varchar(120) DEFAULT NULL,
  `option_2` varchar(120) DEFAULT NULL,
  `option_3` varchar(120) DEFAULT NULL,
  `option_4` varchar(120) DEFAULT NULL,
  `option_5` varchar(120) DEFAULT NULL,
  `option_6` varchar(120) DEFAULT NULL,
  `option_7` varchar(120) DEFAULT NULL,
  `option_8` varchar(120) DEFAULT NULL,
  `option_9` varchar(120) DEFAULT NULL,
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
  `frequency` enum('once','hourly','daily') NOT NULL DEFAULT 'once',
  `created` datetime DEFAULT NULL,
  `close` datetime DEFAULT NULL,
  PRIMARY KEY (`pollID`),
  KEY `categoryID` (`categoryID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `poll` */

/*Table structure for table `pollLog` */

DROP TABLE IF EXISTS `pollLog`;

CREATE TABLE `pollLog` (
  `pollID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  KEY `pollID` (`pollID`,`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `pollLog` */

/*Table structure for table `revision` */

DROP TABLE IF EXISTS `revision`;

CREATE TABLE `revision` (
  `revisionID` int(11) NOT NULL AUTO_INCREMENT,
  `wikiID` int(11) DEFAULT NULL,
  `heading` varchar(200) DEFAULT NULL,
  `body` mediumtext,
  `userID` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`revisionID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `revision` */

/*Table structure for table `sideMenu` */

DROP TABLE IF EXISTS `sideMenu`;

CREATE TABLE `sideMenu` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(3) unsigned NOT NULL DEFAULT '0',
  `txt` varchar(150) NOT NULL DEFAULT '0',
  `url` varchar(200) DEFAULT '0',
  `target` varchar(10) DEFAULT '0',
  `sequence` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=232 DEFAULT CHARSET=utf8;

/*Data for the table `sideMenu` */

insert  into `sideMenu`(`id`,`parent`,`txt`,`url`,`target`,`sequence`) values (1,0,'Home','/home.php','',0),(10,1,'News','/news','',6),(96,10,'View News','/news','',1),(97,10,'Add News','/news/modify','',0),(14,1,'Teacher Resources','/resources','',1),(231,14,'Create Submission','/submission/createSubmission/new','',0),(230,1,'Feedback','/feedback.php','',11),(103,14,'Upload File to Multiple Folders','/cms/file/multicat','',0),(105,14,'Add Link to Multiple Folders','/cms/link/multicat','',1),(229,1,'Change Password','/account/modify','',9),(207,0,'Logout','/logout.php','',8),(208,1,'Admin','/adminv2/home','',2),(213,1,'Due Work','/submission/dueWork','',4),(225,224,'Uncompleted Attendance','/modules/tutorial/report/attendance.php','',0),(226,224,'Hours Performed','/modules/tutorial/report/hours.php','',1),(227,224,'Student Attendance','/modules/tutorial/report/student.php','',2),(228,0,'Manage Groups','/group/join','',7);

/*Table structure for table `sideMenuCampus` */

DROP TABLE IF EXISTS `sideMenuCampus`;

CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL DEFAULT '0',
  `campus` int(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `sideMenuCampus` */

insert  into `sideMenuCampus`(`id`,`campus`) values (1,1),(2,1),(3,1),(7,1),(8,1),(9,1),(10,1),(13,1),(14,1),(15,1),(93,1),(94,1),(96,1),(97,1),(103,1),(105,1),(207,1),(213,1),(214,1),(220,1),(221,1),(222,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(220,2),(221,2),(222,2),(224,2),(225,2),(226,2),(227,2),(228,2),(229,2),(230,2);

/*Table structure for table `sideMenuGroup` */

DROP TABLE IF EXISTS `sideMenuGroup`;

CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL DEFAULT '0',
  `gid` int(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `sideMenuGroup` */

insert  into `sideMenuGroup`(`id`,`gid`) values (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(2,3),(2,4),(2,5),(3,1),(3,2),(3,3),(3,5),(4,1),(4,2),(4,3),(4,4),(4,5),(7,1),(7,2),(7,3),(7,4),(7,5),(8,1),(8,2),(8,3),(8,5),(9,1),(9,2),(9,3),(9,4),(9,5),(10,1),(10,2),(10,3),(10,4),(10,5),(13,1),(13,2),(13,3),(13,4),(13,5),(14,1),(14,2),(14,3),(14,5),(15,1),(15,2),(93,1),(93,2),(93,3),(93,4),(93,5),(94,1),(94,2),(94,3),(94,4),(94,5),(96,1),(96,2),(96,3),(96,4),(96,5),(97,1),(97,2),(103,1),(103,2),(105,1),(105,2),(207,1),(207,2),(207,3),(207,4),(207,5),(213,3),(213,4),(213,5),(214,1),(214,2),(214,3),(214,5),(220,1),(220,2),(220,3),(220,4),(220,5),(221,1),(221,2),(222,1),(222,2),(224,1),(225,1),(226,1),(227,1),(228,1),(228,2),(228,3),(228,4),(228,5),(229,1),(229,2),(229,3),(229,4),(229,5),(230,1),(230,2),(230,3),(230,4),(230,5),(231,1),(231,2);

/*Table structure for table `student_submission` */

DROP TABLE IF EXISTS `student_submission`;

CREATE TABLE `student_submission` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `realname` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `marked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `late` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `box_id` int(4) unsigned NOT NULL DEFAULT '0',
  `mime_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `student_submission` */

/*Table structure for table `subject_news` */

DROP TABLE IF EXISTS `subject_news`;

CREATE TABLE `subject_news` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `heading` varchar(50) DEFAULT NULL,
  `datetime_posted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `datetime_expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `body` mediumtext NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `code` int(6) unsigned NOT NULL DEFAULT '0',
  `owner` int(6) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`code`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `subject_news` */

/*Table structure for table `submission_box` */

DROP TABLE IF EXISTS `submission_box`;

CREATE TABLE `submission_box` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT '0',
  `type` int(11) DEFAULT NULL,
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `open_date` datetime DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `late_date` datetime DEFAULT '0000-00-00 00:00:00',
  `fid` int(5) unsigned DEFAULT '0',
  `accept_submission` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `description` varchar(32) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `file` (`fid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `submission_box` */

/*Table structure for table `submission_log` */

DROP TABLE IF EXISTS `submission_log`;

CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT '0',
  `page` tinyint(4) NOT NULL DEFAULT '0',
  `sid` int(6) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user`,`page`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `submission_log` */

/*Table structure for table `submission_return` */

DROP TABLE IF EXISTS `submission_return`;

CREATE TABLE `submission_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `box_id` int(11) NOT NULL DEFAULT '0',
  `owner` int(11) NOT NULL DEFAULT '0',
  `comment` text,
  `mark` varchar(15) DEFAULT NULL,
  `filename` varchar(80) DEFAULT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `returned` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `submitted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `submission_return` */

/*Table structure for table `superusers` */

DROP TABLE IF EXISTS `superusers`;

CREATE TABLE `superusers` (
  `id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `superusers` */

insert  into `superusers`(`id`) values (1);

/*Table structure for table `textBox` */

DROP TABLE IF EXISTS `textBox`;

CREATE TABLE `textBox` (
  `textBoxID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) DEFAULT NULL,
  `heading` varchar(100) DEFAULT NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`textBoxID`),
  KEY `categoryID` (`categoryID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `textBox` */

/*Table structure for table `uhomepage` */

DROP TABLE IF EXISTS `uhomepage`;

CREATE TABLE `uhomepage` (
  `owner` int(11) NOT NULL DEFAULT '0',
  `education` mediumtext,
  `personal` mediumtext,
  `school` mediumtext,
  PRIMARY KEY (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `uhomepage` */

/*Table structure for table `user` */

DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL DEFAULT '',
  `password` varchar(80) DEFAULT NULL,
  `group_id` int(4) unsigned NOT NULL DEFAULT '0',
  `title` varchar(10) DEFAULT NULL,
  `firstname` varchar(30) NOT NULL DEFAULT '',
  `lastname` varchar(30) NOT NULL DEFAULT '',
  `email_pass` varchar(40) DEFAULT NULL,
  `synergy_id` int(6) unsigned NOT NULL DEFAULT '0',
  `campus` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `year` tinyint(2) unsigned DEFAULT NULL,
  `house` int(11) DEFAULT NULL,
  `local_email` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `alt_email` varchar(60) DEFAULT NULL,
  `mail_handler` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `hash` varchar(40) DEFAULT NULL,
  `key` varchar(40) DEFAULT NULL,
  `eportfolio_enabled` int(8) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `synergy` (`synergy_id`),
  KEY `username` (`username`),
  KEY `groupind` (`group_id`),
  FULLTEXT KEY `fulltext` (`username`,`title`,`firstname`,`lastname`,`alt_email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

/*Data for the table `user` */

insert  into `user`(`id`,`username`,`password`,`group_id`,`title`,`firstname`,`lastname`,`email_pass`,`synergy_id`,`campus`,`year`,`house`,`local_email`,`alt_email`,`mail_handler`,`hash`,`key`,`eportfolio_enabled`) values (1,'alaress','*A09E580BE049CBB0F83F646FB629AC061E1C68D2',1,NULL,'Alaress','Admin',NULL,0,0,NULL,NULL,0,'support@alaress.com.au',0,NULL,NULL,0);

/*Table structure for table `userAccess` */

DROP TABLE IF EXISTS `userAccess`;

CREATE TABLE `userAccess` (
  `uid` int(11) NOT NULL DEFAULT '0',
  `service` int(11) NOT NULL DEFAULT '0',
  `sid` int(11) NOT NULL DEFAULT '0',
  `type` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`service`,`sid`,`uid`),
  KEY `uid` (`uid`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `userAccess` */

/*Table structure for table `userCampus` */

DROP TABLE IF EXISTS `userCampus`;

CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL DEFAULT '0',
  `campus` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `userCampus` */

insert  into `userCampus`(`uid`,`campus`,`defaultCampus`) values (1,1,1);

/*Table structure for table `userGroups` */

DROP TABLE IF EXISTS `userGroups`;

CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL DEFAULT '0',
  `type` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `userGroups` */

/*Table structure for table `welcomeComponent` */

DROP TABLE IF EXISTS `welcomeComponent`;

CREATE TABLE `welcomeComponent` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `filename` varchar(100) DEFAULT '0',
  `function` varchar(100) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

/*Data for the table `welcomeComponent` */

insert  into `welcomeComponent`(`id`,`name`,`filename`,`function`) values (1,'News','news.htm','outputNews'),(2,'Weather','weather.htm','outputWeather'),(3,'Calendar','calendar.htm','outputCalendar'),(4,'Whats New','changedfolder.htm','outputWhatNew'),(5,'My Students','studentSubjects.htm','outputChildSubjects'),(6,'Timetable','','outputTimetable'),(7,'Helpdesk','helpdesk.htm','outputHelpdesk'),(8,'Parent Helpdesk','parentHelpdesk.htm','outputParentHelpdesk'),(9,'Wenona Intranet Login','wenonaIntranet.htm','outputWenonaIntranet'),(10,'RSS Feed','rss.htm','outputRSS'),(11,'Tutorials','tutorial.html','outputTutorial'),(12,'Grimwade Buttons','grimwade.html','outputPrimary'),(13,'Image Slideshow','gallery.htm','outputSlideshow'),(14,'Icons','shortcuts.htm','outputShortcuts');

/*Table structure for table `welcomeLayout` */

DROP TABLE IF EXISTS `welcomeLayout`;

CREATE TABLE `welcomeLayout` (
  `gid` int(6) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `componentID` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`gid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

/*Data for the table `welcomeLayout` */

insert  into `welcomeLayout`(`gid`,`position`,`componentID`) values (1,1,1),(1,2,3),(1,3,10),(1,4,13),(1,5,2),(1,6,4),(1,7,7),(2,1,1),(2,2,3),(2,3,10),(2,4,13),(2,5,2),(2,6,4),(2,7,7),(3,8,0),(3,1,1),(3,2,3),(3,3,NULL),(3,4,13),(3,5,2),(3,6,4),(3,7,7),(2,8,0),(4,1,1),(4,2,3),(4,3,8),(4,4,13),(4,5,2),(4,6,4),(4,7,5),(4,8,0),(1,0,6),(2,0,6),(3,0,6),(4,0,6),(1,8,0),(1,9,NULL),(2,9,NULL),(3,9,NULL),(4,9,NULL);

/*Table structure for table `wiki` */

DROP TABLE IF EXISTS `wiki`;

CREATE TABLE `wiki` (
  `wikiID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) DEFAULT NULL,
  `latestRevision` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  `locked` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`wikiID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `wiki` */

/*Table structure for table `wikiOpens` */

DROP TABLE IF EXISTS `wikiOpens`;

CREATE TABLE `wikiOpens` (
  `openID` int(8) NOT NULL AUTO_INCREMENT,
  `wikiID` int(8) DEFAULT NULL,
  `userID` int(8) DEFAULT NULL,
  `lastopened` datetime DEFAULT NULL,
  PRIMARY KEY (`openID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `wikiOpens` */

/*Table structure for table `wikiRevision` */

DROP TABLE IF EXISTS `wikiRevision`;

CREATE TABLE `wikiRevision` (
  `revisionID` int(11) NOT NULL AUTO_INCREMENT,
  `wikiID` int(11) DEFAULT NULL,
  `heading` varchar(200) DEFAULT NULL,
  `body` mediumtext,
  `userID` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`revisionID`),
  KEY `wikiID` (`wikiID`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Data for the table `wikiRevision` */

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

UPDATE error
SET message = 'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href="/mail/mailInfo.php">click here</a> to update the email password stored on Schoolbox.<br><br>Please contact your System Administrator if problems persist.'
WHERE message = 'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href="/mail/mailInfo.php">click here</a> to update the email password stored on the portal.<br><br>Please contact your System Administrator if problems persist.';

UPDATE error
SET message = '%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to Schoolbox.<br>If you would like to be able to add files of this type to Schoolbox please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to Schoolbox.'
WHERE message = '%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Portal.<br>If you would like to be able to add files of this type to the Portal please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Portal.';
UPDATE error
SET message = 'No email account information was found. Please either use an external mail client to send email, or provide Schoolbox with an email address.'
WHERE message = 'No email account information was found. Please either use an external mail client to send email, or provide the portal with an email address.';
ALTER TABLE allowedFileExtension 
CHANGE extension extension CHAR(10);
