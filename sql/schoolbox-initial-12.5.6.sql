-- MySQL dump 10.13  Distrib 5.5.29, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: schoolbox
-- ------------------------------------------------------
-- Server version	5.5.29-0ubuntu0.12.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `allowedFileExtension`
--

DROP TABLE IF EXISTS `allowedFileExtension`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `allowedFileExtension` (
  `fileTypeID` int(11) DEFAULT NULL,
  `extension` char(12) DEFAULT NULL,
  KEY `fileTypeID` (`fileTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowedFileExtension`
--

LOCK TABLES `allowedFileExtension` WRITE;
/*!40000 ALTER TABLE `allowedFileExtension` DISABLE KEYS */;
INSERT INTO `allowedFileExtension` (`fileTypeID`, `extension`) VALUES (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtx'),(4,'rtf'),(5,'css'),(6,'jpe'),(6,'jpeg'),(6,'jpg'),(7,'pjpg'),(7,'jpg'),(7,'jpeg'),(7,'jpe'),(8,'png'),(9,'bmp'),(11,'docx'),(12,'ppt'),(12,'pps'),(13,'xls'),(13,'csv'),(14,'zip'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(17,'ogg'),(18,'gsp'),(19,'cab'),(19,'swf'),(20,'ins'),(20,'isf'),(22,'wav'),(23,'mp2'),(23,'mp3'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'midi'),(26,'mid'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(29,'wmv'),(30,'avi'),(3,'csv'),(31,'zip'),(11,'doc'),(36,'docx'),(37,'ppsx'),(38,'pptx'),(39,'xlsx'),(40,'xps'),(41,'avi'),(42,'tif'),(42,'tiff'),(43,'pub'),(44,'mdb'),(45,'mp4'),(45,'mpv'),(46,'xml'),(51,'wma'),(50,'m4a'),(49,'notebook'),(21,'re3'),(21,'wmv'),(21,'mswmm'),(21,'ins'),(21,'msw'),(21,'isf'),(21,'ogg'),(10,'pdf'),(0,'asx'),(47,'ggb'),(48,'kmz');
/*!40000 ALTER TABLE `allowedFileExtension` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `allowedFileTypes`
--

DROP TABLE IF EXISTS `allowedFileTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `allowedFileTypes` (
  `fileTypeID` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(150) NOT NULL DEFAULT '',
  `mime` varchar(100) NOT NULL DEFAULT '',
  `iconFile` varchar(80) NOT NULL DEFAULT 'application-x-none.png',
  `inline` enum('true','false') NOT NULL DEFAULT 'true',
  `enable` enum('true','false') NOT NULL DEFAULT 'true',
  PRIMARY KEY (`fileTypeID`),
  UNIQUE KEY `mime` (`mime`)
) ENGINE=MyISAM AUTO_INCREMENT=52 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowedFileTypes`
--

LOCK TABLES `allowedFileTypes` WRITE;
/*!40000 ALTER TABLE `allowedFileTypes` DISABLE KEYS */;
INSERT INTO `allowedFileTypes` (`fileTypeID`, `description`, `mime`, `iconFile`, `inline`, `enable`) VALUES (1,'GIF Image','image/gif','image_gif.png','true','true'),(2,'HTML Document','text/html','html.png','true','true'),(3,'Plain Text','text/plain','txt.png','true','true'),(4,'Rich Text','text/richtext','document_alt1.png','true','true'),(5,'CSS','text/css','txt.png','true','true'),(6,'JPEG Image','image/jpeg','image_jpeg.png','true','true'),(7,'Progressing JPEG Image','image/pjpeg','image_jpeg.png','true','true'),(8,'PNG Image','image/png','image_png.png','true','true'),(9,'Bitmap Image','image/bmp','image_bmp.png','true','true'),(10,'PDF Document','application/pdf','pdf_alt.png','true','true'),(11,'Word Document','application/msword','msword_doc.png','true','true'),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','mspowerpoint_ppt.png','true','true'),(13,'Excel Spreadsheet','application/vnd.ms-excel','msexcel_xls.png','true','true'),(14,'ZIP Archive','application/x-zip-compressed','archive_zip.png','false','true'),(15,'Postscript','application/postscript','postscript_alt.png','true','true'),(16,'Photoshop Image','application/x-photoshop','image_psd.png','false','true'),(17,'OGG Audio','application/ogg','sound_ogg.png','true','true'),(18,'Geometer\'s Sketchpad','application/x-gsp','widget.png','false','true'),(19,'Shockwave Flash','application/x-shockwave-flash','flash_swf.png','true','true'),(20,'Inspiration','application/x-Inspiration','umbrellofile.png','false','true'),(21,'Binary Data (Unknown)','application/octet-stream','binary.png','false','true'),(22,'WAV Audio','audio/wav','sound_wav.png','false','true'),(23,'MPEG Audio','audio/mpeg','sound_mp3.png','false','true'),(24,'Real Audio Stream','audio/x-pn-realaudio','sound.png','false','true'),(25,'Real Audio','audio/x-realaudio','sound.png','false','true'),(26,'MIDI Audio','audio/mid','sound.png','false','true'),(27,'MPEG Video','video/mpeg','video_alt.png','false','true'),(28,'Quicktime Video','video/quicktime','quicktime.png','false','true'),(29,'WMV Video','video/x-ms-wmv','video_alt.png','true','true'),(30,'Microsoft Video','video/x-msvideo','video_alt.png','false','true'),(31,'ZIP Archive','application/zip','archive_zip.png','true','true'),(32,'Flash','video/x-flv','flash_swf.png','true','true'),(33,'Windows Media','video/x-ms-asf','video_alt.png','true','true'),(35,'Rich Text Format','text/rtf','document_alt2.png','false','true'),(34,'MPEG 4','video/x-m4v','video_alt.png','true','true'),(36,'Microsoft Office Document 2007','application/vnd.openxmlformats-officedocument.wordprocessingml.document','msword_doc.png','false','true'),(37,'Microsoft Office Slideshow 2007','application/vnd.openxmlformats-officedocument.presentationml.slideshow','mspowerpoint_ppt.png','true','true'),(38,'Microsoft Office Presentation 2007','application/vnd.openxmlformats-officedocument.presentationml.presentation','mspowerpoint_ppt.png','true','true'),(39,'Microsoft Office Spreadsheet 2007','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','msexcel_xls.png','false','true'),(40,'Microsoft XPS','application/vnd.ms-xpsdocument','widget_alt.png','false','true'),(41,'AVI Video','video/avi','video_alt.png','true','true'),(42,'Scanned Image','image/tiff','image_tiff.png','false','true'),(43,'Microsoft Publisher','application/vnd.ms-publisher','document.png','false','true'),(44,'Microsoft Access','application/msaccess','msaccess_mdb.png','false','true'),(45,'MP4 Video','video/mp4','video_alt.png','false','true'),(46,'XML File','application/xml','txt.png','false','true'),(47,'Geogebra File','application/vnd.geogebra.file','video_alt.png','false','true'),(48,'Google Earth Zip File','application/vnd.google-earth.kmz','video_alt.png','true','true'),(49,'Smart Notebook','application/x-smarttech-notebook','video_alt.png','false','true'),(50,'Audio','audio/mp4a-latm','video_alt.png','false','true'),(51,'Windows Media','audio/x-ms-wma','video_alt.png','false','true');
/*!40000 ALTER TABLE `allowedFileTypes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess`
--

DROP TABLE IF EXISTS `assess`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `max_attempts` int(1) NOT NULL DEFAULT '0',
  `availability` enum('assess','survey') DEFAULT 'assess',
  `anonymous_result` int(1) NOT NULL DEFAULT '0',
  `notify_author` int(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`assess_id`),
  UNIQUE KEY `id` (`assess_id`),
  KEY `fk_assess_category1` (`category_id`),
  KEY `fk_assess_user1` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess`
--

LOCK TABLES `assess` WRITE;
/*!40000 ALTER TABLE `assess` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_answer`
--

DROP TABLE IF EXISTS `assess_answer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_answer` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `sequence` int(11) DEFAULT NULL,
  `answer` text,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `fk_assess_answer_assess_question1` (`question_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_answer`
--

LOCK TABLES `assess_answer` WRITE;
/*!40000 ALTER TABLE `assess_answer` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess_answer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_attempt`
--

DROP TABLE IF EXISTS `assess_attempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_attempt` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `assess_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `fk_assess_attempt_assess1` (`assess_id`),
  KEY `fk_assess_attempt_user1` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_attempt`
--

LOCK TABLES `assess_attempt` WRITE;
/*!40000 ALTER TABLE `assess_attempt` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess_attempt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_luQuestionType`
--

DROP TABLE IF EXISTS `assess_luQuestionType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_luQuestionType` (
  `type_id` int(8) NOT NULL,
  `classname` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `moodle` varchar(255) DEFAULT NULL,
  `automated_marking` enum('true','false') NOT NULL,
  `availability` enum('assess','survey','all') DEFAULT NULL,
  `has_legend` enum('true','false') DEFAULT 'true',
  PRIMARY KEY (`type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_luQuestionType`
--

LOCK TABLES `assess_luQuestionType` WRITE;
/*!40000 ALTER TABLE `assess_luQuestionType` DISABLE KEYS */;
INSERT INTO `assess_luQuestionType` (`type_id`, `classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`, `has_legend`) VALUES (1,'Short','short','Short Answer','shortanswer','false','all','false'),(2,'Multi','multi','Multiple Choice','multichoice','true','all','true'),(3,'Fillgap','fillgap','Cloze Exercise','cloze','true','assess','true'),(4,'Match','match','Matching Pairs','matching','true','assess','true'),(5,'Radio','radio','Single Answer','radio','true','survey','true'),(6,'Rating_Agree','ratingAgree','Rating Scale (Agree)',NULL,'false','survey','true'),(7,'Rating_Like','ratingLike','Rating Scale (Like)',NULL,'false','survey','true'),(8,'Rating_Important','ratingImportant','Rating Scale (Important)',NULL,'false','survey','true'),(9,'Rating_Satisfied','ratingSatisfied','Rating Scale (Satisfied)',NULL,'false','survey','true'),(10,'YesNo','yesno','Yes/No',NULL,'false','survey','true'),(11,'TrueFalse','truefalse','True/False',NULL,'false','survey','true');
/*!40000 ALTER TABLE `assess_luQuestionType` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_question`
--

DROP TABLE IF EXISTS `assess_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_question` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `marks` decimal(15,2) NOT NULL DEFAULT '0.00',
  `question` text NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_id`),
  KEY `fk_assess_question_assess_luQuestionType1` (`type_id`),
  KEY `fk_assess_question_assess_section1` (`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_question`
--

LOCK TABLES `assess_question` WRITE;
/*!40000 ALTER TABLE `assess_question` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess_question` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_question_bank`
--

DROP TABLE IF EXISTS `assess_question_bank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_question_bank` (
  `question_bank_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `question_category_id` bigint(20) DEFAULT NULL,
  `type_id` bigint(20) DEFAULT NULL,
  `question` text,
  `answer` text,
  `marks` bigint(20) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_bank_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_question_bank`
--

LOCK TABLES `assess_question_bank` WRITE;
/*!40000 ALTER TABLE `assess_question_bank` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess_question_bank` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_question_category`
--

DROP TABLE IF EXISTS `assess_question_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_question_category` (
  `question_category_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `availability` enum('assess','survey') DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_question_category`
--

LOCK TABLES `assess_question_category` WRITE;
/*!40000 ALTER TABLE `assess_question_category` DISABLE KEYS */;
INSERT INTO `assess_question_category` (`question_category_id`, `parent_id`, `name`, `availability`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,0,'Maths','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(2,0,'English','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(3,0,'Geography','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(4,0,'Science','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(5,0,'History','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(6,0,'LOTE','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(7,0,'Elective Selection','survey','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(8,0,'Teacher Review','survey','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(9,0,'Medical','survey','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL);
/*!40000 ALTER TABLE `assess_question_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_response`
--

DROP TABLE IF EXISTS `assess_response`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_response` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `question_id` int(11) NOT NULL,
  `response` text,
  `comment` text NOT NULL,
  `mark` decimal(15,2) DEFAULT '0.00',
  `automarked_already` enum('true','false') DEFAULT 'false',
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`response_id`),
  KEY `fk_assess_response_assess_attempt1` (`attempt_id`),
  KEY `fk_assess_response_assess_question1` (`question_id`),
  KEY `fk_assess_response_assess_section1` (`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_response`
--

LOCK TABLES `assess_response` WRITE;
/*!40000 ALTER TABLE `assess_response` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess_response` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assess_section`
--

DROP TABLE IF EXISTS `assess_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assess_section` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `assess_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT '0',
  `title` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `random` int(11) NOT NULL DEFAULT '0',
  `pool` int(11) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`section_id`),
  KEY `fk_assess_section_assess1` (`assess_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_section`
--

LOCK TABLES `assess_section` WRITE;
/*!40000 ALTER TABLE `assess_section` DISABLE KEYS */;
/*!40000 ALTER TABLE `assess_section` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog`
--

DROP TABLE IF EXISTS `blog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `heading` varchar(50) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blog_category1` (`cid`),
  KEY `fk_blog_user1` (`owner`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog`
--

LOCK TABLES `blog` WRITE;
/*!40000 ALTER TABLE `blog` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blogComment`
--

DROP TABLE IF EXISTS `blogComment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blogComment` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `bid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blogComment_blog1` (`bid`),
  KEY `fk_blogComment_user1` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blogComment`
--

LOCK TABLES `blogComment` WRITE;
/*!40000 ALTER TABLE `blogComment` DISABLE KEYS */;
/*!40000 ALTER TABLE `blogComment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL DEFAULT '0',
  `url` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookmarks`
--

LOCK TABLES `bookmarks` WRITE;
/*!40000 ALTER TABLE `bookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookmarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=MyISAM AUTO_INCREMENT=39 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` (`id`, `name`, `parent`, `template`, `teacherid`, `code`) VALUES (5,'Current Classes',0,0,0,''),(4,'Faculties',0,0,0,''),(6,'Admin Resources',0,0,0,''),(7,'Dashboard Components',6,0,0,''),(8,'System Resources',6,0,0,''),(10,'Archived Classes',0,0,0,''),(11,'Extra Curricular',0,0,0,''),(12,'Sport',11,3,0,''),(13,'Football (example)',12,3,0,''),(14,'Debating (example)',11,3,0,''),(15,'English',4,0,0,''),(16,'Year 12',15,0,0,''),(17,'Student restricted resources',16,0,0,''),(18,'Unit 1',16,0,0,''),(19,'Area of study 1',18,4,0,''),(20,'Groups',0,0,0,''),(21,'Browse Groups',20,4,0,''),(22,'Free Groups',20,0,0,''),(23,'Moderated Groups',20,0,0,''),(24,'Restricted Groups',20,0,0,''),(25,'Schoolbox Champions',23,8,0,''),(26,'Sustainability (example)',22,2,0,''),(27,'Houses',0,8,0,''),(28,'Library',0,8,0,''),(29,'Parent Services',0,0,0,''),(30,'Staff Services',0,0,0,''),(31,'Student Services',0,0,0,''),(32,'School Councellors Office (example)',31,8,0,''),(33,'Forms, Policies & Templates',30,8,0,''),(34,'Policies',33,0,0,''),(35,'OH&S',33,2,0,''),(36,'Forms and Policies',29,8,0,''),(37,'IT Services',0,3,0,'');
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categoryCode`
--

DROP TABLE IF EXISTS `categoryCode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categoryCode` (
  `categoryID` int(11) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  UNIQUE KEY `categoryIDcode` (`categoryID`,`code`),
  KEY `fk_categoryCode_category1` (`categoryID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categoryCode`
--

LOCK TABLES `categoryCode` WRITE;
/*!40000 ALTER TABLE `categoryCode` DISABLE KEYS */;
/*!40000 ALTER TABLE `categoryCode` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat`
--

DROP TABLE IF EXISTS `chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(20) NOT NULL,
  `time` datetime NOT NULL,
  `body` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_chat_category1` (`category_id`),
  KEY `fk_chat_user1` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat`
--

LOCK TABLES `chat` WRITE;
/*!40000 ALTER TABLE `chat` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_poll`
--

DROP TABLE IF EXISTS `chat_poll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_poll` (
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`category_id`),
  KEY `fk_chat_poll_category1` (`category_id`),
  KEY `fk_chat_poll_user1` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_poll`
--

LOCK TABLES `chat_poll` WRITE;
/*!40000 ALTER TABLE `chat_poll` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_poll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class`
--

DROP TABLE IF EXISTS `class`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `code` varchar(16) NOT NULL,
  `description` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class`
--

LOCK TABLES `class` WRITE;
/*!40000 ALTER TABLE `class` DISABLE KEYS */;
/*!40000 ALTER TABLE `class` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_period`
--

DROP TABLE IF EXISTS `class_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_period`
--

LOCK TABLES `class_period` WRITE;
/*!40000 ALTER TABLE `class_period` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_period` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_user`
--

DROP TABLE IF EXISTS `class_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_user`
--

LOCK TABLES `class_user` WRITE;
/*!40000 ALTER TABLE `class_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cmsLog`
--

DROP TABLE IF EXISTS `cmsLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cmsLog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object` enum('folder','file','link','myfile','myfolder') NOT NULL DEFAULT 'folder',
  `objectId` int(10) unsigned NOT NULL DEFAULT '0',
  `action` enum('create','modify','delete','access') NOT NULL DEFAULT 'create',
  `user` int(10) unsigned NOT NULL DEFAULT '0',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM AUTO_INCREMENT=54 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cmsLog`
--

LOCK TABLES `cmsLog` WRITE;
/*!40000 ALTER TABLE `cmsLog` DISABLE KEYS */;
INSERT INTO `cmsLog` (`id`, `object`, `objectId`, `action`, `user`, `time`) VALUES (1,'folder',2,'delete',1,'2011-05-04 14:20:53'),(2,'folder',1,'delete',1,'2011-05-04 14:21:16'),(3,'folder',4,'create',1,'2013-04-15 05:04:50'),(4,'folder',3,'delete',1,'2013-04-15 05:04:55'),(5,'folder',5,'create',1,'2013-04-15 05:06:01'),(6,'folder',6,'create',1,'2013-04-15 05:06:59'),(7,'folder',7,'create',1,'2013-04-15 05:07:11'),(8,'folder',7,'modify',1,'2013-04-15 05:07:41'),(9,'folder',8,'create',1,'2013-04-15 05:08:31'),(10,'folder',9,'create',1,'2013-04-15 05:08:51'),(11,'folder',9,'modify',1,'2013-04-15 05:09:10'),(12,'folder',10,'create',1,'2013-04-15 05:22:59'),(13,'folder',11,'create',1,'2013-04-15 05:23:46'),(14,'folder',12,'create',1,'2013-04-15 05:24:38'),(15,'folder',13,'create',1,'2013-04-15 05:25:29'),(16,'folder',13,'modify',1,'2013-04-15 05:25:48'),(17,'folder',14,'create',1,'2013-04-15 05:26:23'),(18,'folder',14,'modify',1,'2013-04-15 05:27:18'),(19,'folder',15,'create',1,'2013-04-15 05:28:09'),(20,'folder',16,'create',1,'2013-04-15 05:28:44'),(21,'folder',17,'create',1,'2013-04-15 05:29:00'),(22,'folder',18,'create',1,'2013-04-15 05:29:26'),(23,'folder',18,'modify',1,'2013-04-15 05:29:36'),(24,'folder',19,'create',1,'2013-04-15 05:30:08'),(25,'folder',20,'create',1,'2013-04-15 05:31:13'),(26,'folder',21,'create',1,'2013-04-15 05:31:32'),(27,'folder',22,'create',1,'2013-04-15 05:32:36'),(28,'folder',23,'create',1,'2013-04-15 05:32:49'),(29,'folder',20,'modify',1,'2013-04-15 05:33:09'),(30,'folder',20,'modify',1,'2013-04-15 05:33:27'),(31,'folder',24,'create',1,'2013-04-15 05:33:34'),(32,'folder',25,'create',1,'2013-04-15 05:34:03'),(33,'folder',26,'create',1,'2013-04-15 05:34:51'),(34,'folder',27,'create',1,'2013-04-15 05:36:48'),(35,'folder',28,'create',1,'2013-04-15 05:38:47'),(36,'folder',29,'create',1,'2013-04-15 05:46:57'),(37,'folder',30,'create',1,'2013-04-15 05:47:17'),(38,'folder',31,'create',1,'2013-04-15 05:47:34'),(39,'folder',32,'create',1,'2013-04-15 05:48:01'),(40,'folder',32,'modify',1,'2013-04-15 05:54:26'),(41,'link',1,'create',1,'2013-04-15 05:55:05'),(42,'folder',33,'create',1,'2013-04-15 05:57:54'),(43,'folder',34,'create',1,'2013-04-15 05:59:29'),(44,'folder',35,'create',1,'2013-04-15 05:59:45'),(45,'folder',36,'create',1,'2013-04-15 06:00:50'),(46,'folder',37,'create',1,'2013-04-15 06:01:55'),(47,'folder',26,'modify',1,'2013-04-15 07:17:38'),(48,'folder',9,'delete',1,'2013-04-15 07:19:52'),(49,'folder',38,'create',1,'2013-04-15 07:25:08'),(50,'folder',38,'modify',1,'2013-04-15 07:25:20'),(51,'folder',38,'delete',1,'2013-04-15 07:28:35'),(52,'link',1,'delete',1,'2013-04-15 07:33:18'),(53,'folder',15,'modify',1,'2013-04-15 07:34:37');
/*!40000 ALTER TABLE `cmsLog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `component_config_definition`
--

DROP TABLE IF EXISTS `component_config_definition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_config_definition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_definition_id` int(10) unsigned NOT NULL,
  `key` varchar(32) NOT NULL,
  `default_value` varchar(128) NOT NULL,
  `label` varchar(64) DEFAULT NULL,
  `description` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `component_definition_id` (`component_definition_id`,`key`),
  KEY `fk_dashboard_component_option_default_dashboard_component1` (`component_definition_id`)
) ENGINE=MyISAM AUTO_INCREMENT=90 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_config_definition`
--

LOCK TABLES `component_config_definition` WRITE;
/*!40000 ALTER TABLE `component_config_definition` DISABLE KEYS */;
INSERT INTO `component_config_definition` (`id`, `component_definition_id`, `key`, `default_value`, `label`, `description`) VALUES (1,23,'componentname','News','Heading',NULL),(2,23,'size','0','Limit',NULL),(3,23,'src','','Source',NULL),(4,24,'sort','date','Sort by',NULL),(5,24,'sortdir','desc','Direction',NULL),(6,24,'size','0','Limit',NULL),(7,24,'compactMode','false','Compact Mode',NULL),(8,24,'componentname','Links','Heading',NULL),(9,24,'src','','Source',NULL),(10,25,'filter','31','Filter',NULL),(11,25,'sort','date','Sort by',NULL),(12,25,'sortdir','desc','Direction',NULL),(13,25,'size','0','Limit',NULL),(14,25,'componentname','Files','Heading',NULL),(15,25,'compactMode','false','Compact Mode',NULL),(16,25,'src','','Source',NULL),(17,26,'displayPortraits','grid','Display Portraits',NULL),(18,27,'sort','date','Sort by',NULL),(19,27,'size','0','Limit',NULL),(20,27,'componentname','Forum','Heading',NULL),(21,27,'compactMode','false','Compact Mode',NULL),(22,27,'src','','Source',NULL),(23,30,'displayPortraits','grid','Display Portraits',NULL),(24,31,'pixel','200','Max width / height',NULL),(25,31,'url','#','Url',NULL),(26,31,'image','Preview Image','Image',NULL),(27,32,'size','0','Limit',NULL),(28,32,'componentname','Folders','Heading',NULL),(29,32,'compactMode','false','Compact Mode',NULL),(30,32,'src','','Source',NULL),(31,33,'size','0','Limit',NULL),(32,34,'url','','Url',NULL),(33,35,'componentname','Image Gallery','Heading',NULL),(34,35,'size','0','Limit',NULL),(35,35,'src','','Source',NULL),(36,36,'pixel','400','Max width / height',NULL),(37,38,'size','0','Limit',NULL),(38,38,'compactMode','false','Compact Mode',NULL),(39,39,'url','','Url',NULL),(40,39,'size','5','Limit',NULL),(41,39,'compactMode','false','Compact Mode',NULL),(42,40,'displayPortraits','grid','Display Portraits',NULL),(43,41,'size','0','Limit',NULL),(44,43,'poll','0','Poll',NULL),(45,44,'componentname','Wiki','Heading',NULL),(46,44,'src','','Source',NULL),(47,44,'sort','heading','Sort by',NULL),(48,44,'sortdir','desc','Direction',NULL),(49,44,'size','0','Limit',NULL),(50,44,'compactMode','false','Compact Mode',NULL),(51,45,'size','0','Limit',NULL),(52,46,'textbox','0','Textbox',NULL),(53,47,'sort','name','Sort by',NULL),(54,47,'sortdir','asc','Direction',NULL),(55,47,'size','0','Limit',NULL),(56,48,'url','','Url',NULL),(57,48,'size','0','Limit',NULL),(58,48,'compactMode','false','Compact Mode',NULL),(59,49,'componentname','Blog','Heading',NULL),(60,49,'size','3','Limit',NULL),(62,49,'src','','Source',NULL),(63,51,'pixel','200','Max width / height',NULL),(64,51,'captionState','on','Captions',NULL),(65,51,'timing','2','Timing',NULL),(66,51,'componentname','Image Scroller','Heading',NULL),(67,51,'src','','Source',NULL),(68,52,'compactMode','false','Compact Mode',NULL),(69,53,'state','off','State',NULL),(70,53,'teacher_timestamp','','',NULL),(71,53,'muted_users','a:0:{}','',NULL),(72,54,'compactMode','false','Compact Mode',NULL),(73,55,'username','alaress','Author',NULL),(74,55,'keywords','kony','Hashtags',NULL),(75,55,'size','3','Limit',NULL),(76,56,'componentname','Countdown','Heading',NULL),(77,56,'time','','Time',NULL),(78,56,'date','','Date',NULL),(79,57,'compactMode','false','Hide heading',NULL),(80,57,'textboxSearch','0','Textbox Search',NULL),(81,2,'componentname','{timeofday}, <strong>{title} {firstname} {lastname}</strong>','Greeting Format',NULL),(82,22,'size','10','Limit',NULL),(83,4,'size','10','Limit',NULL),(84,13,'url','http://www.abc.net.au/news/indexes/justin/rss.xml','Url',NULL),(85,13,'size','10','Limit',NULL),(86,20,'size','10','Limit',NULL),(87,5,'url','','Location',NULL),(88,5,'size','3','Forecast Days',NULL),(89,58,'displayPortraits','grid','Layout Style',NULL);
/*!40000 ALTER TABLE `component_config_definition` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `component_config_instance`
--

DROP TABLE IF EXISTS `component_config_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_config_instance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_config_definition_id` int(10) unsigned NOT NULL,
  `component_instance_id` int(10) unsigned NOT NULL,
  `value` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `component_config_definition` (`component_config_definition_id`,`component_instance_id`),
  KEY `fk_dashboard_component_option_dashboard_component_instance1` (`component_config_definition_id`)
) ENGINE=MyISAM AUTO_INCREMENT=213 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_config_instance`
--

LOCK TABLES `component_config_instance` WRITE;
/*!40000 ALTER TABLE `component_config_instance` DISABLE KEYS */;
INSERT INTO `component_config_instance` (`id`, `component_config_definition_id`, `component_instance_id`, `value`) VALUES (195,52,195,NULL),(2,52,102,'2'),(3,52,104,'3'),(4,52,103,'4'),(5,52,105,NULL),(6,1,106,NULL),(7,2,106,NULL),(8,3,106,NULL),(9,15,107,NULL),(10,14,107,NULL),(11,10,107,NULL),(12,13,107,NULL),(13,11,107,NULL),(14,12,107,NULL),(15,16,107,NULL),(28,1,116,NULL),(27,52,115,NULL),(18,44,110,NULL),(19,23,111,NULL),(20,31,113,NULL),(21,7,114,NULL),(22,8,114,NULL),(23,6,114,NULL),(24,4,114,NULL),(25,5,114,NULL),(26,9,114,NULL),(29,2,116,NULL),(30,3,116,NULL),(31,15,117,NULL),(32,14,117,NULL),(33,10,117,NULL),(34,13,117,NULL),(35,11,117,NULL),(36,12,117,NULL),(37,16,117,NULL),(38,51,118,NULL),(39,17,119,NULL),(40,44,120,NULL),(41,23,121,NULL),(42,31,123,NULL),(43,7,124,NULL),(44,8,124,NULL),(45,6,124,NULL),(46,4,124,NULL),(47,5,124,NULL),(48,9,124,NULL),(49,52,125,NULL),(50,1,126,NULL),(51,2,126,NULL),(52,3,126,NULL),(53,15,127,NULL),(54,14,127,NULL),(55,10,127,NULL),(56,13,127,NULL),(57,11,127,NULL),(58,12,127,NULL),(59,16,127,NULL),(60,51,128,NULL),(61,17,129,NULL),(62,44,130,NULL),(63,23,131,NULL),(64,31,133,NULL),(65,7,134,NULL),(66,8,134,NULL),(67,6,134,NULL),(68,4,134,NULL),(69,5,134,NULL),(70,9,134,NULL),(71,52,135,NULL),(72,52,136,NULL),(73,29,137,NULL),(74,28,137,NULL),(75,27,137,NULL),(76,30,137,NULL),(77,7,139,NULL),(78,8,139,NULL),(79,6,139,NULL),(80,4,139,NULL),(81,5,139,NULL),(82,9,139,NULL),(83,15,140,NULL),(84,14,140,NULL),(85,10,140,NULL),(86,13,140,NULL),(87,11,140,NULL),(88,12,140,NULL),(89,16,140,NULL),(90,52,141,NULL),(91,52,142,NULL),(92,29,143,NULL),(93,28,143,NULL),(94,27,143,NULL),(95,30,143,NULL),(96,7,145,NULL),(97,8,145,NULL),(98,6,145,NULL),(99,4,145,NULL),(100,5,145,NULL),(101,9,145,NULL),(102,15,146,NULL),(103,14,146,NULL),(104,10,146,NULL),(105,13,146,NULL),(106,11,146,NULL),(107,12,146,NULL),(108,16,146,NULL),(109,52,147,'5'),(146,30,160,'27'),(111,29,149,'0'),(112,28,149,'Moderated Groups'),(113,27,149,'0'),(114,30,149,'23'),(145,27,160,'0'),(144,28,160,'Our School Houses'),(143,29,160,'0'),(142,30,159,'22'),(141,27,159,'0'),(140,28,159,'Free Groups'),(139,29,159,'0'),(128,1,153,NULL),(129,2,153,NULL),(130,3,153,NULL),(131,17,154,NULL),(132,23,156,NULL),(133,31,157,NULL),(134,21,158,NULL),(135,20,158,NULL),(136,19,158,NULL),(137,18,158,NULL),(138,22,158,NULL),(147,52,164,'6'),(148,44,166,'1'),(149,52,162,'7'),(186,79,86,'1'),(194,52,194,NULL),(152,1,173,NULL),(153,2,173,NULL),(154,3,173,NULL),(155,17,174,NULL),(156,23,176,NULL),(157,31,177,NULL),(158,21,178,NULL),(159,20,178,NULL),(160,19,178,NULL),(161,18,178,NULL),(162,22,178,NULL),(163,52,179,NULL),(164,1,180,NULL),(165,2,180,NULL),(166,3,180,NULL),(167,15,181,NULL),(168,14,181,NULL),(169,10,181,NULL),(170,13,181,NULL),(171,11,181,NULL),(172,12,181,NULL),(173,16,181,NULL),(174,51,182,NULL),(175,17,183,NULL),(176,44,184,NULL),(177,23,185,NULL),(178,31,187,NULL),(179,7,188,NULL),(180,8,188,NULL),(181,6,188,NULL),(182,4,188,NULL),(183,5,188,NULL),(184,9,188,NULL),(187,80,86,'2'),(188,79,88,'1'),(189,80,88,'2'),(190,79,89,'1'),(191,80,89,'2'),(192,79,91,'1'),(193,80,91,'2'),(196,29,196,NULL),(197,28,196,NULL),(198,27,196,NULL),(199,30,196,NULL),(200,7,198,NULL),(201,8,198,NULL),(202,6,198,NULL),(203,4,198,NULL),(204,5,198,NULL),(205,9,198,NULL),(206,15,199,NULL),(207,14,199,NULL),(208,10,199,NULL),(209,13,199,NULL),(210,11,199,NULL),(211,12,199,NULL),(212,16,199,NULL);
/*!40000 ALTER TABLE `component_config_instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `component_definition`
--

DROP TABLE IF EXISTS `component_definition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_definition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `class` varchar(64) NOT NULL,
  `description` varchar(512) DEFAULT NULL,
  `module` enum('dashboard','homepage','eportfolio') DEFAULT NULL,
  `component_category` int(10) unsigned DEFAULT NULL,
  `visible` enum('true','false') NOT NULL DEFAULT 'true',
  `multiple` enum('false','true') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`id`),
  UNIQUE KEY `class` (`class`)
) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_definition`
--

LOCK TABLES `component_definition` WRITE;
/*!40000 ALTER TABLE `component_definition` DISABLE KEYS */;
INSERT INTO `component_definition` (`id`, `name`, `class`, `description`, `module`, `component_category`, `visible`, `multiple`) VALUES (1,'Timetable','Component_Dashboard_TimetableController',NULL,'dashboard',NULL,'true','false'),(2,'Greeting','Component_Dashboard_GreetingController',NULL,'dashboard',NULL,'true','false'),(3,'Calendar','Component_Dashboard_CalendarController',NULL,'dashboard',NULL,'true','false'),(4,'News','Component_Dashboard_NewsController',NULL,'dashboard',NULL,'true','false'),(5,'Weather','Component_Dashboard_WeatherController',NULL,'dashboard',NULL,'true','false'),(6,'Activity','Component_Dashboard_ActivityController',NULL,'dashboard',NULL,'true','false'),(7,'Child Subject','Component_Dashboard_ChildSubjectController',NULL,'dashboard',NULL,'true','false'),(8,'Helpdesk','Component_Dashboard_HelpdeskController',NULL,'dashboard',NULL,'false','false'),(9,'Link','Component_Dashboard_LinkController',NULL,'dashboard',NULL,'false','false'),(11,'Parent Helpdesk','Component_Dashboard_ParentHelpdeskController',NULL,'dashboard',NULL,'false','false'),(13,'RSS','Component_Dashboard_RssController',NULL,'dashboard',NULL,'true','false'),(15,'Suggestion','Component_Dashboard_SuggestionController',NULL,'dashboard',NULL,'false','false'),(16,'Tutorial','Component_Dashboard_TutorialController',NULL,'dashboard',NULL,'false','false'),(17,'Wenona Intranet','Component_Dashboard_WenonaIntranetController',NULL,'dashboard',NULL,'false','false'),(18,'Primary','Component_Dashboard_PrimaryController',NULL,'dashboard',NULL,'false','false'),(19,'Organisation','Component_Dashboard_OrganisationController',NULL,'dashboard',NULL,'false','false'),(20,'Upcoming Due Work','Component_Dashboard_UpcomingWorkController',NULL,'dashboard',NULL,'true','false'),(21,'Recent Submissions','Component_Dashboard_RecentSubmissionsController',NULL,'dashboard',NULL,'true','false'),(22,'Homepage Activity','Component_Dashboard_HomepageNewsController',NULL,'dashboard',NULL,'true','false'),(23,'News','Component_Homepage_NewsController','Provides a text area for you to post information, news, blogs and introductions. ','homepage',1,'true','true'),(24,'Links','Component_Homepage_LinkListController','Provide links to interesting, related and helpful resources on Schoolbox or the WWW.','homepage',5,'true','true'),(25,'Files','Component_Homepage_FileListController','List general files that have been uploaded to your homepage. The large version shows both titles and comments.','homepage',5,'true','true'),(26,'Group Members','Component_Homepage_MembersController','Show the group members associated with your homepage.  This also provides an email all group members link.','homepage',2,'true','false'),(27,'Forum','Component_Homepage_ForumListController','Show recent discussion topics going on in your homepages forum.','homepage',2,'true','true'),(28,'Class Management','Component_Homepage_ManageClassController','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.','homepage',3,'true','false'),(29,'Folder Management','Component_Homepage_ManageFolderController','Provides links to browse the files, manage group membership and view homepage statistics.','homepage',5,'true','false'),(30,'Group Managers','Component_Homepage_ManagersController','Displays a list of group members with write access to the homepage. Useful in larger groups to show who manages the group.','homepage',2,'true','false'),(31,'Image','Component_Homepage_ImageController','Completely configurable image display, select the image, control the size and link to another location','homepage',4,'true','true'),(32,'Folders','Component_Homepage_FolderListController','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.','homepage',5,'true','true'),(33,'Calendar','Component_Homepage_EventListController','Display upcoming events that are in the calendar for this group.','homepage',1,'true','false'),(34,'ClickView','Component_Homepage_ClickviewController','Embed Clickview Home videos directly into your homepage by providing the ID of the video','homepage',5,'true','true'),(35,'Image Gallery','Component_Homepage_ImageGalleryController','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ','homepage',4,'true','true'),(36,'Random Image','Component_Homepage_RandImageController','Display a random image selected from the images on this homepage.','homepage',4,'true','false'),(37,'File Drop Box','Component_Homepage_DropBoxController','Provides an area for teachers to receive files from students without the need for a submission box and due date','homepage',3,'true','false'),(38,'Due Work','Component_Homepage_DueWorkListController','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.','homepage',3,'true','false'),(39,'RSS Feed','Component_Homepage_RSSController','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.','homepage',6,'true','true'),(40,'Class Teachers','Component_Homepage_TeachersController','Show a list of teachers responsible for this subject if it has a synergetic subject code.','homepage',3,'true','false'),(41,'Campus Calendar','Component_Homepage_CalendarListController','Show a calendar for the entire campus on your homepage.','homepage',1,'false','false'),(42,'Duties','Component_Homepage_DutiesController',NULL,'homepage',1,'false','false'),(43,'Poll','Component_Homepage_PollController','Create a poll to determine group members opinion on a given subject.','homepage',2,'true','true'),(44,'Wiki','Component_Homepage_WikiController','Provides an area for group members to work together on joint documents.','homepage',2,'true','true'),(45,'Homepage Feed','Component_Homepage_FeedController','Provides a running commentary on recent changes to the homepage','homepage',6,'true','false'),(46,'Text Box','Component_Homepage_TextBoxController','Use this space to write introductory message or information relating to the content that is displayed','homepage',1,'true','true'),(47,'Audio','Component_Homepage_AudioController','Lists audio files and allows them to be played.','homepage',5,'true','false'),(48,'Podcast','Component_Homepage_PodcastController','Subscribe to audio podcasts and provide inline playback of the media directly in the homepage','homepage',6,'true','true'),(49,'Blog','Component_Homepage_BlogController','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.','homepage',1,'true','true'),(50,'Language Translator','Component_Homepage_TranslateController','Translates text and webpages into another language','homepage',3,'false','false'),(51,'Image Slideshow','Component_Homepage_ScrollerController','Display an animated image carousel, fading between all the images uploaded to your homepage','homepage',4,'true','true'),(52,'Online Assessment','Component_Homepage_AssessController','Create tests and quizzes for your students. Results show in the class marksheet. Build resources using the school question bank.','homepage',3,'true','false'),(53,'Chat','Component_Homepage_ChatController','Allows users who are viewing the same homepage to chat with each other.','homepage',2,'true','false'),(54,'Surveys','Component_Homepage_SurveyController','Create online surveys or forms to collect data from your class or group.  Useful for collecting feedback or enrolling in electives.','homepage',2,'true','false'),(55,'Twitter','Component_Homepage_TwitterController','Connect and feed Twitter posts directly on to your homepage. Search users, hashtags or keywords to create dynamic streams.','homepage',6,'true','true'),(56,'Countdown','Component_Homepage_CountdownController','Displays a countdown to the specified time.','homepage',1,'true','false'),(57,'TextBox','Component_Dashboard_TextboxController','Module for displaying custom content on dashboards by being linked to a pre-existing textbox.','dashboard',NULL,'true','true'),(58,'Class List','Component_Homepage_ClassListController','Show your students portraits on your class homepage','homepage',3,'true','false');
/*!40000 ALTER TABLE `component_definition` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `component_instance`
--

DROP TABLE IF EXISTS `component_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_instance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_definition_id` int(10) unsigned NOT NULL,
  `module` enum('dashboard','homepage','homepage_template','eportfolio') DEFAULT NULL,
  `identifier` int(10) unsigned NOT NULL COMMENT 'Foreign ID is used to reference the context of the instance. (Polymorphic)',
  `position` enum('top','left','right') NOT NULL DEFAULT 'left',
  `sequence` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_instance_dashboard_component1` (`component_definition_id`),
  KEY `fk_dashboard_component_instance_groups1` (`identifier`)
) ENGINE=MyISAM AUTO_INCREMENT=200 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_instance`
--

LOCK TABLES `component_instance` WRITE;
/*!40000 ALTER TABLE `component_instance` DISABLE KEYS */;
INSERT INTO `component_instance` (`id`, `component_definition_id`, `module`, `identifier`, `position`, `sequence`) VALUES (2,2,'dashboard',1,'top',1),(87,21,'dashboard',2,'right',2),(4,4,'dashboard',1,'left',1),(5,3,'dashboard',1,'right',3),(6,13,'dashboard',1,'left',2),(7,5,'dashboard',1,'right',2),(86,57,'dashboard',1,'right',1),(10,2,'dashboard',2,'top',1),(11,1,'dashboard',2,'top',2),(12,4,'dashboard',2,'left',1),(13,3,'dashboard',2,'right',4),(14,13,'dashboard',2,'left',2),(15,5,'dashboard',2,'right',3),(16,6,'dashboard',2,'left',3),(18,2,'dashboard',3,'top',1),(19,1,'dashboard',3,'top',2),(20,4,'dashboard',3,'left',1),(21,3,'dashboard',3,'right',2),(22,5,'dashboard',3,'left',2),(23,6,'dashboard',3,'left',3),(25,2,'dashboard',4,'top',2),(91,57,'dashboard',4,'right',4),(27,4,'dashboard',4,'left',3),(28,3,'dashboard',4,'right',4),(30,5,'dashboard',4,'left',3),(31,6,'dashboard',4,'left',3),(32,23,'homepage_template',1,'left',1),(33,38,'homepage_template',1,'left',2),(34,25,'homepage_template',1,'left',3),(35,40,'homepage_template',1,'right',1),(95,30,'homepage_template',2,'right',2),(37,24,'homepage_template',1,'right',4),(38,45,'homepage_template',1,'right',5),(39,28,'homepage_template',1,'right',3),(40,29,'homepage_template',1,'right',2),(41,23,'homepage_template',2,'left',1),(103,46,'homepage',7,'right',2),(102,46,'homepage',7,'left',1),(44,26,'homepage_template',2,'left',2),(45,33,'homepage_template',2,'right',3),(46,27,'homepage_template',2,'right',4),(96,32,'homepage_template',4,'left',2),(48,29,'homepage_template',2,'right',1),(97,30,'homepage_template',3,'right',1),(50,23,'homepage_template',3,'left',1),(51,25,'homepage_template',3,'left',2),(52,45,'homepage_template',3,'left',3),(53,33,'homepage_template',3,'right',3),(54,26,'homepage_template',3,'left',4),(197,29,'homepage',15,'right',1),(56,43,'homepage_template',3,'left',5),(57,24,'homepage_template',3,'right',4),(58,29,'homepage_template',3,'right',2),(94,46,'homepage_template',4,'left',1),(93,46,'homepage_template',4,'top',1),(61,25,'homepage_template',4,'right',3),(105,46,'homepage',12,'top',1),(104,46,'homepage',7,'right',1),(64,24,'homepage_template',4,'right',2),(65,29,'homepage_template',4,'right',1),(66,23,'homepage_template',5,'left',1),(67,44,'homepage_template',5,'left',2),(68,25,'homepage_template',5,'left',3),(69,26,'homepage_template',5,'right',2),(70,33,'homepage_template',5,'right',2),(71,27,'homepage_template',5,'right',3),(72,45,'homepage_template',5,'right',4),(73,29,'homepage_template',5,'right',5),(74,23,'homepage_template',6,'left',1),(100,46,'homepage_template',3,'top',1),(99,46,'homepage_template',6,'top',1),(77,26,'homepage_template',6,'left',3),(78,33,'homepage_template',6,'right',2),(98,30,'homepage_template',6,'right',1),(80,45,'homepage_template',6,'left',2),(81,29,'homepage_template',6,'right',3),(82,49,'homepage_template',7,'left',1),(83,43,'homepage_template',7,'right',2),(84,24,'homepage_template',7,'right',3),(85,29,'homepage_template',7,'right',1),(88,57,'dashboard',2,'right',1),(89,57,'dashboard',3,'right',1),(90,20,'dashboard',3,'right',3),(92,7,'dashboard',4,'right',4),(106,23,'homepage',12,'left',1),(107,25,'homepage',12,'left',2),(116,23,'homepage',13,'left',1),(115,46,'homepage',13,'top',1),(110,43,'homepage',12,'left',5),(111,30,'homepage',12,'right',1),(112,29,'homepage',12,'right',2),(113,33,'homepage',12,'right',3),(114,24,'homepage',12,'right',4),(117,25,'homepage',13,'left',2),(118,45,'homepage',13,'left',3),(119,26,'homepage',13,'left',4),(120,43,'homepage',13,'left',5),(121,30,'homepage',13,'right',1),(122,29,'homepage',13,'right',2),(123,33,'homepage',13,'right',3),(124,24,'homepage',13,'right',4),(125,46,'homepage',14,'top',1),(126,23,'homepage',14,'left',1),(127,25,'homepage',14,'left',2),(128,45,'homepage',14,'left',3),(129,26,'homepage',14,'left',4),(130,43,'homepage',14,'left',5),(131,30,'homepage',14,'right',1),(132,29,'homepage',14,'right',2),(133,33,'homepage',14,'right',3),(134,24,'homepage',14,'right',4),(135,46,'homepage',18,'top',1),(136,46,'homepage',18,'left',1),(137,32,'homepage',18,'left',2),(138,29,'homepage',18,'right',1),(139,24,'homepage',18,'right',2),(140,25,'homepage',18,'right',3),(141,46,'homepage',19,'top',1),(142,46,'homepage',19,'left',1),(143,32,'homepage',19,'left',2),(144,29,'homepage',19,'right',1),(145,24,'homepage',19,'right',2),(146,25,'homepage',19,'right',3),(147,46,'homepage',21,'top',1),(162,46,'homepage',28,'top',1),(149,32,'homepage',21,'right',1),(150,29,'homepage',21,'right',2),(161,29,'homepage',27,'right',1),(160,32,'homepage',27,'left',1),(153,23,'homepage',26,'left',1),(154,26,'homepage',26,'left',2),(155,29,'homepage',26,'right',1),(156,30,'homepage',26,'right',2),(157,33,'homepage',26,'right',3),(158,27,'homepage',26,'right',4),(159,32,'homepage',21,'left',1),(163,23,'homepage',28,'left',2),(164,46,'homepage',28,'right',1),(165,24,'homepage',28,'right',2),(166,43,'homepage',28,'right',3),(190,13,'dashboard',3,'left',4),(194,46,'homepage',15,'top',1),(195,46,'homepage',15,'left',1),(170,23,'homepage',33,'left',1),(171,29,'homepage',33,'left',2),(172,32,'homepage',33,'right',1),(173,23,'homepage',35,'left',1),(174,26,'homepage',35,'left',2),(175,29,'homepage',35,'right',1),(176,30,'homepage',35,'right',2),(177,33,'homepage',35,'right',3),(178,27,'homepage',35,'right',4),(179,46,'homepage',37,'top',1),(180,23,'homepage',37,'left',1),(181,25,'homepage',37,'left',2),(182,45,'homepage',37,'left',3),(183,26,'homepage',37,'left',4),(184,43,'homepage',37,'left',5),(185,30,'homepage',37,'right',1),(186,29,'homepage',37,'right',2),(187,33,'homepage',37,'right',3),(188,24,'homepage',37,'right',4),(196,32,'homepage',15,'left',2),(198,24,'homepage',15,'right',2),(199,25,'homepage',15,'right',3);
/*!40000 ALTER TABLE `component_instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `component_option`
--

DROP TABLE IF EXISTS `component_option`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_option_default_id` int(10) unsigned NOT NULL,
  `instance_id` int(10) unsigned NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_option_dashboard_component_instance1` (`instance_id`),
  KEY `fk_component_option_component_option_default1` (`component_option_default_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_option`
--

LOCK TABLES `component_option` WRITE;
/*!40000 ALTER TABLE `component_option` DISABLE KEYS */;
/*!40000 ALTER TABLE `component_option` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `component_option_definition`
--

DROP TABLE IF EXISTS `component_option_definition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `component_option_definition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_definition_id` int(10) unsigned NOT NULL,
  `key` varchar(32) NOT NULL,
  `type` int(11) NOT NULL,
  `value` text NOT NULL,
  `default` text,
  `label` varchar(64) DEFAULT NULL,
  `description` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_option_default_dashboard_component1` (`component_definition_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_option_definition`
--

LOCK TABLES `component_option_definition` WRITE;
/*!40000 ALTER TABLE `component_option_definition` DISABLE KEYS */;
/*!40000 ALTER TABLE `component_option_definition` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config` (
  `configID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) DEFAULT NULL,
  `value` text,
  `title` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `field` enum('input','textarea','boolean','select','checkbox','bytes') DEFAULT 'input',
  `options` varchar(300) DEFAULT NULL,
  `type` enum('Site','Security','Policy','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave','Modules','3rd Party Services') DEFAULT NULL,
  `editable` enum('true','false') DEFAULT 'false',
  `hidden` enum('true','false') DEFAULT 'false',
  `required` enum('false','true') DEFAULT 'false',
  PRIMARY KEY (`configID`),
  KEY `configName` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=135 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` (`configID`, `name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES (1,'title','Schoolbox','Title','Title of site displayed at top of browser window','input',NULL,'Site','true','false','true'),(2,'school_name','Alaress Pty Ltd','School Name','Name of school displayed in footer','input',NULL,'Site','true','false','true'),(3,'school_address','http://www.alaress.com.au','School Website Address','Address of school website','input',NULL,'Site','true','false','true'),(4,'site_location','demo','Site Identifier','Name of site used to identify school in custom code and templates','input',NULL,'Site','false','false','true'),(5,'site_hostname','core','Hostname','Hostname used to access server without the domain','input',NULL,'Site','true','false','true'),(6,'site_domain','portal.alaress-dev.com.au','Domain','Network domain that is used to access server remotely','input',NULL,'Site','true','false','true'),(7,'site_detect','campus','Template Detection','Method which template is detected','select','none:campus:domain','Site','false','false','true'),(8,'login_grace','60','Login Grace Time','Amount of time (secs) between failed logins before brute force lockout','input',NULL,'Security','true','false','true'),(9,'login_allowed','3','Failed Logins Allowed','Amount of logins allowed in login grace time before brute force lockout','input',NULL,'Security','true','false','true'),(10,'track_ip','0','Track Session IPs','Track session IPs, prevents session stealing but can cause problems with proxies','boolean',NULL,'Security','true','false','true'),(11,'session_length','1800','Session Expiry Time','Length of idle time (secs) before a user is logged out','input',NULL,'Security','true','false','true'),(12,'kerb_enable','1','Enable Kerberos SSO','Enable Kerberos SSO authentication.  This enables sessions to be created based on mod_auth_kerb authentication','boolean',NULL,'Security','true','false','true'),(13,'ldap_enable','0','Enable LDAP Authentication','Enable authentication via LDAP server. System will attempt first to bind to LDAP if this fails, it will fall back local authentication','boolean',NULL,'Security','true','false','false'),(14,'ldap_host','','LDAP Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false','false'),(15,'ldap_user','','LDAP Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false','false'),(16,'ldap_pass','','LDAP Password','Password for binding to LDAP server','input',NULL,'Security','true','false','false'),(17,'ldap_base','','LDAP Search Base','Base search path for username lookup','input',NULL,'Security','true','false','false'),(18,'ldap_host_alt','','LDAP Alternate Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false','false'),(19,'ldap_user_alt','','LDAP Alternate Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false','false'),(20,'ldap_base_alt','','LDAP Alternate Search Base','Base search path for username lookup','input',NULL,'Security','true','false','false'),(21,'ldap_username','SAMAccountName','LDAP Username Search Field','Field to search for username in LDAP','input',NULL,'Security','true','false','false'),(22,'template_dir','/vagrant/templates','Template Location','Location of templates','input',NULL,'Paths','false','false','true'),(23,'file_dir','/vagrant/data/files','File Location','Location of files uploaded to resources are stored','input',NULL,'Paths','false','false','true'),(24,'myfile_dir','/vagrant/data/myFiles','MyFiles Location','Location of personal files uploaded to my files or eportfolio are stored','input',NULL,'Paths','false','false','true'),(25,'submission_dir','/vagrant/data/submissions','Submission Location','Location of student submissions','input',NULL,'Paths','false','false','true'),(26,'returns_dir','/vagrant/data/returns','Returns Location','Location of teacher returned work','input',NULL,'Paths','false','false','true'),(27,'dropbox_dir','/vagrant/data/dropbox','Dropbox Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false','true'),(28,'zip_dir','/vagrant/data/cache/zip','ZIP Temp Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false','true'),(29,'thumbnail_dir','/vagrant/data/thumbnails','Image Cache Location','Location of where thumbnails and resized images are stored after they are generated','input',NULL,'Paths','false','false','true'),(30,'xml_dir','/vagrant/data/cache/xml','XML Cache Location','Location of RSS and XML caching','input',NULL,'Paths','false','false','true'),(31,'thumbnail_size','100','Thumbnail pixel size','Maximum height/width of thumbnails generated for image gallery','input',NULL,'Resources','true','false','true'),(32,'file_upload_count','10','File Upload Limit','The total amount of files that can be uploaded via a single multifile upload page','input',NULL,'Resources','true','false','true'),(33,'max_file_size','10485760','File Size Limit','The maximum size of a single file that can be uploaded. Accepts formats like 40M and 1GB.','bytes',NULL,'Resources','true','false','true'),(34,'news_edit_other','1','Allow Others to Edit News','Controls whether users with write access to a page can edit each others news posts','boolean',NULL,'Policy','true','false','true'),(35,'group_staff_only','1','Allow only staff to be Group Admins','Disabling this option allows students and parents to be promoted to group admin. Beware of the consequences.','boolean',NULL,'Policy','true','false','true'),(36,'webdav_enable','0','Enable WebDAV connector','Turns on webDAV, enabling the network drives tab','boolean',NULL,'Resources','false','false','true'),(37,'search_all_limit','3','Search Summary Item Limit','The amount of items shown for each section on the all tab in the search','boolean',NULL,'Resources','false','false','true'),(38,'search_type_limit','15','Search Items Per Page','The amount of results shown per page in the search results','boolean',NULL,'Resources','false','false','true'),(115,'minify_dir','/vagrant/data/cache/minify','Data Minifier Location','Location of minifier cache files','input',NULL,'Paths','false','false','true'),(40,'welcome_rss_url','http://www.abc.net.au/news/indexes/justin/rss.xml','URL for RSS','The URL for the RSS feed displayed on the welcome page','input',NULL,'3rd Party Services','false','true','true'),(41,'welcome_rss_limit','6','RSS Item Limit','The amount of items displayed from the RSS feed on the welcome page','input',NULL,'3rd Party Services','false','true','true'),(42,'weather_enable','1','Enable Weather','Reguarily fetches weather data from Google weather feeds','boolean',NULL,'3rd Party Services','true','false','true'),(43,'weather_location','Australia/Melbourne','Weather Location','By default display weather at this location. This value can be formatted as Country/City; latitude,longitude; airport code; or IP address.','input',NULL,'3rd Party Services','true','false','true'),(44,'name_format','{title} {firstname} {lastname}','Name Output Format','Format of names available fields; {title}, {firstname}, {lastname}','input',NULL,'Localisation','true','false','true'),(45,'calendar_time_format','%l:%i%p','Calendar Time Format','Time output style 24 hour = \'%k:%i\', 12 hour = \'%l:%i%p\'','input',NULL,'Localisation','true','false','true'),(46,'date_long','l F jS Y g:ia','Long Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(47,'date_short','jS M Y g:ia','Short Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(48,'date_database','Y-m-d H:i:s','Database Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(49,'date_notime','jS M Y','Date with no Time Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(50,'external_enable','0','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records','boolean',NULL,'External DB','true','false','false'),(51,'external_type','tass','Database Provider','The name of the database product currently being used','select','synergetic:tass:denbigh:edumate:schoolbox','External DB','true','false','false'),(52,'external_db_host','','Database Host','The server hostname where SQL database is located','input',NULL,'External DB','true','false','false'),(53,'external_db_user','','Database User','SQL user with read access to the required tables, see documentation','input',NULL,'External DB','true','false','false'),(54,'external_db_passwd','','Database Password','SQL user password','input',NULL,'External DB','true','false','false'),(55,'external_db_db','SynergyOne','Database Name','The name of the database on the SQL server','input',NULL,'External DB','true','false','false'),(56,'external_portal','http://synergy.portal.com','External DB Portal URL','Schoolbox can integrate with your existing school database to fetch timetable and student records','input',NULL,'External DB','true','false','false'),(57,'external_sync_data','0','Synchronise Data','Use this to control your automatic sync script, only use this if you have a sync script installed for your service','boolean',NULL,'External DB','true','false','false'),(58,'mail_enable','1','Enable e-Mail','Enables your email configuration for Schoolbox','boolean','','Email','true','false','true'),(107,'weather_dir','/vagrant/data/cache/weather','Weather Cache Location','Location of Weather caching','input',NULL,'Paths','false','false','true'),(62,'mail_smtp_host','','SMTP Server','Address of server to send email via, this should be your email gateway','input','','Email','true','false','true'),(63,'mail_smtp_port','25','SMTP Port','Port of the SMTP server, typically 25','input','','Email','true','false','true'),(64,'mail_address','','Email Domain','Email domains with which emails should originate from, if no alt emails are specfied email will be username@domain','input','','Email','true','false','true'),(65,'mail_webmail','','Webmail URL','URL to access OWA or other existing webmail service.  Should be accessible internally and externally','input','','Email','true','false','false'),(66,'mail_from_address','','Email From Address','Emails that originate from Schoolbox will come from this address','input','','Email','true','false','true'),(67,'mail_from_name','Schoolbox','Email From Name','Emails that originate from Schoolbox will come from this name','input','','Email','true','false','true'),(68,'mail_support_address','support@alaress.com.au','Schoolbox Feedback Address','Requests sent to the Schoolbox feedback form will be sent to this address','input','','Email','true','false','true'),(69,'site_version','12.5.6','Schoolbox Version','Version of Schoolbox currently installed','input',NULL,'Site','false','false','true'),(70,'api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.','input','','Security','true','false','true'),(73,'policy',NULL,'Policy','A key key generated in the PlagScan administration interface','textarea',NULL,'Policy','true','false',''),(74,'protect_teacher_photo','1','Hide Teacher Photo','Tick this box to prevent teacher photos being displayed to student and parents in search results.','boolean',NULL,'Policy','true','false','true'),(75,'protect_student_photo','1','Hide Student Photo','Tick this box to hide student photos from other students','boolean',NULL,'Policy','true','false','true'),(76,'homepage_colour_head_font','013a63','Heading Text','Colour of the heading text on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(77,'homepage_colour_head_bg','cae9b9','Heading Background','Colour of the background of the headings on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(78,'homepage_colour_font','000000','Body Text','Colour of the body text on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(79,'homepage_colour_bg_primary','ffffff','Primary Background','Primary background colour on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(80,'homepage_colour_bg_secondary','faf9f5','Secondary Background','Secondary background colour homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(81,'homepage_colour_link','1d9ffc','Link','Colour of links on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(82,'homepage_colour_hover','ff5a00','Link Hover','Colour of links when your mouse over them on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(83,'date_timezone','Australia/Victoria','Timezone','Set the local timezone for the server, use the PHP timezones available on the site','select','Australia/Victoria:Australia/NSW:Australia/Queensland:Australia/West:Australia/South:Pacific/Auckland','Localisation','true','false','true'),(84,'recaptcha_private_api_key','6LfS0sgSAAAAADh12n8weyLb8irboBWfhseikfCF','reCAPTCHA Private Key','The reCAPTCHA private key','input',NULL,'3rd Party Services','false','true','false'),(85,'recaptcha_public_api_key','6LfS0sgSAAAAALQEAZ9Di5rG_RZ7saoLKsgNo6Iq','reCAPTCHA Public Key','The reCAPTCHA public key','input',NULL,'3rd Party Services','false','true','false'),(86,'external_web_services_url',NULL,'SOAP Web Services URL','URL of the web services API that can handle SOAP requests','input',NULL,'External DB','true','false','false'),(87,'external_vendor_token_guid',NULL,'Synergetic Vendor Token GUID','The Vendor Token GUID used to access your Synergetic SOAP API','input',NULL,'External DB','true','false','false'),(88,'external_vendor_password',NULL,'Synergetic Vendor Password','The Vendor Password used to access your Synergetic SOAP API','input',NULL,'External DB','true','false','false'),(105,'weather_api_key','784036067287ef7a','Weather API Key','A key generated in the Wunderground administration interface','input','','3rd Party Services','false','false','true'),(106,'weather_api_caching','1800','Weather API Update Frequency','How long weather data should be cached for before being re-queried','input','','3rd Party Services','false','true','true'),(94,'plagscan_api_key',NULL,'PlagScan API Key','A key key generated in the PlagScan administration interface','input','','3rd Party Services','true','false','false'),(95,'plagscan_username',NULL,'PlagScan Username','The username you used when registering for the PlagScan service.','input','','3rd Party Services','true','false','false'),(104,'news_default_expiry','+1 week','Default News expiry date','The relative date that news will expire on by default. Can be any string like \'tomorrow\', \'+1 week\', \'next Thursday\'','input',NULL,'Policy','true','false','true'),(103,'news_staff_post_enabled','0','Allow Staff to create News','If ticked, staff members can create news posts.','boolean','true:false','Policy','true','false','false'),(98,'remote_secret','','Remoted Shared Secret','This string is used in the authentication process with remote systems','input',NULL,'Security','true','false','false'),(99,'portrait_dir','/vagrant/data/cache/portrait','Portrait Location','The path to the portrait data store','input',NULL,'Paths','false','false','true'),(100,'restrict_parent_directory','1','Restrict Parent Directory','If ticked, parents can only see the parent directories for year levels in which they have a student, otherwise they can see all parent directories.','boolean','true:false','Policy','true','false','false'),(101,'restrict_student_submissions','true','Restrict Student Submissions','If ticked, parents cannot see marks and details of their students\' submissions.','boolean','true:false','Policy','true','false','false'),(102,'stream_dir','/vagrant/data/cache/stream','Stream Cache Dir','Cache folder for streaming content (RSS, XML, Twitter JSON etc)','input',NULL,'Paths','false','false','true'),(108,'due_work_notifications_enabled','1','Due Work Notifications Enabled','If ticked, students can automatically be sent reminder emails regarding upcoming due work.','boolean','true:false','Email','true','false','false'),(109,'sync_patterns','','Sync User Types','User strategies for synchronisation, must be set by Alaress','input',NULL,'External DB','false','false','true'),(110,'myfiles_cache_dir','/vagrant/data/cache/myfiles','MyFiles Thumbnails Cache Location','Location of cached thumbnails for MyFiles items','input',NULL,'Paths','false','false','true'),(111,'xml_cache_timeout','3000','XML Update Frequency','How long xml data should be cached for before being re-queried in seconds','input',NULL,'3rd Party Services','true','false','true'),(112,'reveal_password','0','Show Passwords To Admins','If enabled passwords may be revealled via the admin system','boolean',NULL,'Policy','false','false','false'),(113,'boarder_leave_enabled','0','Enable Boarder Leave Module','Enables boarder leave module and connectivity to Event Attendance','boolean','true:false','Modules','false','false','false'),(114,'mobile_enabled','0','Enable Mobile Module','Enables mobile edition of Schoolbox for phones and tablets.','boolean','true:false','Modules','false','false','false'),(116,'show_all_subjects','0','Show All Subjects on Profile','If enabled all enrolled subjects will be shown on student profile.  If disabled system will hide subjects that do not exist in Schoolbox','boolean',NULL,'Policy','true','false','false'),(117,'external_attendance','0','Enable Online Attendance','Allows teachers to mark attendance online, only compatible with Synergetic.','boolean',NULL,'External DB','true','false','false'),(118,'storage_dir','/vagrant/data/storage','Data Store Location','Multi purpose storage engine, stores all files in this path','input',NULL,'Paths','false','false','true'),(119,'logs_dir','/vagrant/data/cache/logs','Logs Cache Location','Location of log files','input',NULL,'Paths','false','false','true'),(120,'external_db_encoding','ISO-8859-1','Database Encoding','The encoding that the external database uses to store text fields. Most SQL Server databases will use ISO-8859-1.','input','','External DB','true','false','false'),(121,'ldap_search_filter','','LDAP Search Filter','Pre-filter to put onto LDAP login searches - e.g. objectClass=User','input','','Security','true','false','false'),(122,'webdav_allow_insecure','0','Enable WebDAV insecure connections','If WebDAV is enabled, this allows connections to be made if the WebDAV host\'s SSL certificate cannot be verified. Please attempt to use a verified certificate before resorting to this option.','boolean',NULL,'Resources','false','true','true'),(123,'edumate_auth_enabled','0','Enable Edumate Authentication','Enable authentication via Edumate system. If this fails, authentication will fall back to local.','boolean','','Security','true','false','false'),(124,'edumate_auth_url','','Edumate Authentication URL','The URL to send auth requests to if Edumate authentication is being used.','input','','Security','true','false','false'),(125,'email_all_parents','1','Allow Email All Parents','If ticked, allows staff to email all the parents of a class with a single button','boolean','true:false','Policy','true','false','false'),(126,'file_default_to_hidden','0','File uploads default to hidden','If this is checked, then file uploads will default to hidden - this can be changed on a per-file basis.','boolean',NULL,'Resources','true','false','false'),(127,'protect_medicalspecial','0','Hide Medical and Special Needs on Class Lists','Tick this box to hide all Medical and Special Needs information from displaying on the Class List pages','boolean','true:false','Policy','true','false','false'),(128,'restrict_parent_emails','0','Restrict Display of Parent Emails','If ticked, restricts the display of parent email addresses to staff only.','boolean','true:false','Policy','true','false','false'),(129,'twitter_api_consumer_key','hJYli4TfG9f4aRqHlXKFg','Twitter API consumer key','Consumer key for Twitter API.','input',NULL,'3rd Party Services','false','false','false'),(130,'twitter_api_consumer_secret','14g4I80nKDD2gRlorwuFDzqpbVPXdbZDZZslvLwWydw','Twitter API consumer secret','Consumer secret for Twitter API.','input',NULL,'3rd Party Services','false','false','false'),(131,'twitter_api_oauth_token','327753084-KjtaRGaMaNxvBiOy0DSTF0ENzyXZqPG2Tkev9XEL','Twitter API OAuth token','OAuth token for Twitter API.','input',NULL,'3rd Party Services','false','false','false'),(132,'twitter_api_oauth_token_secret','iGAY5wxnLrg0beOf2JcQNG0whjWBmAQyeLSS18o8jU','Twitter API OAuth token secret','OAuth token secret for Twitter API.','input',NULL,'3rd Party Services','false','false','false'),(133,'twitter_api_caching','1800','Twitter API update frequency','The amount of time (in seconds) that tweets should be cached for before being re-queried.','input',NULL,'3rd Party Services','false','false','false'),(134,'webdav_allow_any_auth','0','Enable WebDAV any auth type','If this is enabled, then Schoolbox will poll the WebDAV server for the best authentication method - otherwise, only basic authentication will be attempted.','boolean',NULL,'Resources','false','true','true');
/*!40000 ALTER TABLE `config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dropbox`
--

DROP TABLE IF EXISTS `dropbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dropbox` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT '0',
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `realname` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `deleted` tinyint(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `fk_dropbox_user1` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dropbox`
--

LOCK TABLES `dropbox` WRITE;
/*!40000 ALTER TABLE `dropbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `dropbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolioTemplateLayout`
--

DROP TABLE IF EXISTS `eportfolioTemplateLayout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolioTemplateLayout` (
  `templateID` int(11) DEFAULT NULL,
  `componentId` int(11) DEFAULT NULL,
  `column` smallint(6) DEFAULT NULL,
  `position` smallint(6) DEFAULT NULL,
  UNIQUE KEY `table` (`templateID`,`componentId`,`column`,`position`),
  KEY `homepageID` (`templateID`),
  KEY `fk_eportfolioTemplateLayout_eportfolio_comp1` (`componentId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolioTemplateLayout`
--

LOCK TABLES `eportfolioTemplateLayout` WRITE;
/*!40000 ALTER TABLE `eportfolioTemplateLayout` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolioTemplateLayout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_blog`
--

DROP TABLE IF EXISTS `eportfolio_blog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_blog` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `heading` varchar(50) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_blog_user1` (`owner`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_blog`
--

LOCK TABLES `eportfolio_blog` WRITE;
/*!40000 ALTER TABLE `eportfolio_blog` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_blog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_blogComment`
--

DROP TABLE IF EXISTS `eportfolio_blogComment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_blogComment` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `bid` int(6) unsigned NOT NULL,
  `owner` int(6) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_blogComment_eportfolio_blog1` (`bid`),
  KEY `fk_eportfolio_blogComment_user1` (`owner`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_blogComment`
--

LOCK TABLES `eportfolio_blogComment` WRITE;
/*!40000 ALTER TABLE `eportfolio_blogComment` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_blogComment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_comp`
--

DROP TABLE IF EXISTS `eportfolio_comp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_comp` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `size` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `method` varchar(255) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `description` mediumtext,
  `eportfolioCategoryID` int(10) unsigned NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `config` mediumtext NOT NULL,
  `invisible` enum('true','false') NOT NULL DEFAULT 'false',
  `allowMultiple` enum('true','false') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_comp_eportfolio_comp_category1` (`eportfolioCategoryID`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_comp`
--

LOCK TABLES `eportfolio_comp` WRITE;
/*!40000 ALTER TABLE `eportfolio_comp` DISABLE KEYS */;
INSERT INTO `eportfolio_comp` (`id`, `size`, `method`, `name`, `description`, `eportfolioCategoryID`, `filename`, `config`, `invisible`, `allowMultiple`) VALUES (1,1,'outputLinkList','Links','Add links to your favourite sites and resources',1,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(2,1,'outputTextBox','Text Box','Creates a space where you can enter your own information',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(3,1,'outputBlog','Blog','Write a blog about your experiences',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),(4,1,'outputFileList','Published Files','Files that you have published to your ePortfolio.',1,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:11:\"Description\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(5,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location',3,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(6,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this ePortfolio',3,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(7,1,'outputObjectiveSkill','Skills','A list of your skills for your resume',2,'objectiveSkill.html','','false','false'),(8,1,'outputObjectiveGoal','Goals','A list of your goals for your resume',2,'objectiveGoal.html','','false','false'),(9,1,'outputObjectiveAward','Awards','A list of your awards for your resume',2,'objectiveAward.html','','false','false'),(10,1,'outputObjectiveCoverLetter','Cover Letter','A cover letter for your resume',2,'objectiveCoverLetter.html','','false','false'),(11,1,'outputObjectiveInterest','Interests','A list of interests for your resume',2,'objectiveInterest.html','','false','false'),(12,1,'outputObjectiveEmploymentHistory','Employment History','An employment history for your resume',2,'objectiveEmploymentHistory.html','','false','false'),(13,1,'outputObjectiveEducationHistory','Education History','An education history for your resume',2,'objectiveEducationHistory.html','','false','false'),(14,1,'outputImageScroller','Image Scroller','An image scroller',3,'imageScroller.html','','false','false');
/*!40000 ALTER TABLE `eportfolio_comp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_comp_category`
--

DROP TABLE IF EXISTS `eportfolio_comp_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_comp_category` (
  `eportfolioComponentCategoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`eportfolioComponentCategoryID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_comp_category`
--

LOCK TABLES `eportfolio_comp_category` WRITE;
/*!40000 ALTER TABLE `eportfolio_comp_category` DISABLE KEYS */;
INSERT INTO `eportfolio_comp_category` (`eportfolioComponentCategoryID`, `name`) VALUES (1,'General'),(2,'Resume'),(3,'Images');
/*!40000 ALTER TABLE `eportfolio_comp_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_comp_location`
--

DROP TABLE IF EXISTS `eportfolio_comp_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_comp_location` (
  `eportfolioComponentLocationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(6) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `column` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `component` int(11) unsigned NOT NULL DEFAULT '0',
  `config` mediumtext NOT NULL,
  PRIMARY KEY (`eportfolioComponentLocationID`),
  KEY `cid` (`uid`),
  KEY `fk_eportfolio_comp_location_eportfolio_comp1` (`component`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_comp_location`
--

LOCK TABLES `eportfolio_comp_location` WRITE;
/*!40000 ALTER TABLE `eportfolio_comp_location` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_comp_location` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_config`
--

DROP TABLE IF EXISTS `eportfolio_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_config` (
  `uid` int(6) unsigned NOT NULL DEFAULT '0',
  `template` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `left_column_width` float DEFAULT '29.5',
  `right_column_width` float DEFAULT '79.5',
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
  `top_head_font` char(7) DEFAULT NULL,
  `top_head_bg` char(7) DEFAULT NULL,
  `top_font` char(7) DEFAULT NULL,
  `top_bg_primary` char(7) DEFAULT NULL,
  `top_bg_secondary` char(7) DEFAULT NULL,
  `top_link` char(7) DEFAULT NULL,
  `top_hover` char(7) DEFAULT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_config`
--

LOCK TABLES `eportfolio_config` WRITE;
/*!40000 ALTER TABLE `eportfolio_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_link`
--

DROP TABLE IF EXISTS `eportfolio_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_link`
--

LOCK TABLES `eportfolio_link` WRITE;
/*!40000 ALTER TABLE `eportfolio_link` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_log`
--

DROP TABLE IF EXISTS `eportfolio_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT '0',
  `uid` int(6) NOT NULL DEFAULT '0',
  `hitcount` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`user`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_log`
--

LOCK TABLES `eportfolio_log` WRITE;
/*!40000 ALTER TABLE `eportfolio_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_award`
--

DROP TABLE IF EXISTS `eportfolio_objective_award`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_award` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_award`
--

LOCK TABLES `eportfolio_objective_award` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_award` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_award` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_coverletter`
--

DROP TABLE IF EXISTS `eportfolio_objective_coverletter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_coverletter` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_coverletter`
--

LOCK TABLES `eportfolio_objective_coverletter` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_coverletter` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_coverletter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_educationhistory`
--

DROP TABLE IF EXISTS `eportfolio_objective_educationhistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_educationhistory` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_educationhistory`
--

LOCK TABLES `eportfolio_objective_educationhistory` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_educationhistory` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_educationhistory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_employmenthistory`
--

DROP TABLE IF EXISTS `eportfolio_objective_employmenthistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_employmenthistory` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_employmenthistory`
--

LOCK TABLES `eportfolio_objective_employmenthistory` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_employmenthistory` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_employmenthistory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_goal`
--

DROP TABLE IF EXISTS `eportfolio_objective_goal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_goal` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_goal`
--

LOCK TABLES `eportfolio_objective_goal` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_goal` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_goal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_interest`
--

DROP TABLE IF EXISTS `eportfolio_objective_interest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_interest` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_interest`
--

LOCK TABLES `eportfolio_objective_interest` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_interest` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_interest` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_objective_skill`
--

DROP TABLE IF EXISTS `eportfolio_objective_skill`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_objective_skill` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `body` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_objective_skill`
--

LOCK TABLES `eportfolio_objective_skill` WRITE;
/*!40000 ALTER TABLE `eportfolio_objective_skill` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_objective_skill` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_privateNotes`
--

DROP TABLE IF EXISTS `eportfolio_privateNotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_privateNotes` (
  `uid` int(8) DEFAULT NULL,
  `privateNotes` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_privateNotes`
--

LOCK TABLES `eportfolio_privateNotes` WRITE;
/*!40000 ALTER TABLE `eportfolio_privateNotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_privateNotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_textBox`
--

DROP TABLE IF EXISTS `eportfolio_textBox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_textBox` (
  `textBoxID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) DEFAULT NULL,
  `componentLocationID` int(11) DEFAULT NULL,
  `heading` varchar(100) DEFAULT NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`textBoxID`),
  KEY `categoryID` (`componentLocationID`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_textBox`
--

LOCK TABLES `eportfolio_textBox` WRITE;
/*!40000 ALTER TABLE `eportfolio_textBox` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_textBox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `error`
--

DROP TABLE IF EXISTS `error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `error` (
  `id` int(3) DEFAULT NULL,
  `message` blob
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `error`
--

LOCK TABLES `error` WRITE;
/*!40000 ALTER TABLE `error` DISABLE KEYS */;
INSERT INTO `error` (`id`, `message`) VALUES (1,'You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.'),(2,'Not enough information was submitted to the form.'),(3,'Please enter either a file or a url, not both.'),(4,'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the schoolbox.<br><br>Please contact your System Administrator if problems persist.'),(5,'You do not have permission to download this file.'),(6,'You do not have write permission to this category.'),(7,'Mail cannot be sent at this time. Please try again later.'),(8,'You do not have permission to delete this resource.'),(9,'Processing failed for the following reason: %MESSAGE%'),(10,'The size of this file exceeds the allowed limit.'),(11,'%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Schoolbox.<br>If you would like to be able to add files of this type to the Schoolbox please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Schoolbox.'),(12,'The category that you are trying to delete is not empty. Please delete all its contents, then try again.'),(13,'The category that you are trying to view does not exist.'),(14,'You do not have permission to view this category.'),(15,'The date that you have submitted is invalid. Please check it, then try again.'),(16,'There was a problem validating your account: <br><b>%MESSAGE%</b>'),(17,'The submission box that you are trying to access does not exist or you do not have permission to view it.'),(18,'The subject selected does not exist.'),(19,'You do not have permission to view this page.'),(20,'You do not have permission to create a category in this location.'),(21,'A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%'),(22,'The database is unavailable at this time. Please try again later.'),(23,'You do not have permission to modify this news.'),(24,'You are not enrolled in this subject.'),(25,'You do not have permission to return this work to this student.'),(26,'The work you are trying to return either does not exist or has already been returned.'),(27,'The work you are trying to submit has already been submitted.'),(28,'You do not have permission to post in this category.'),(29,'You do not have permission to modify or delete this post.'),(30,'This user cannot be the group administrator.'),(31,'No user was found that matches your request.'),(32,'The absentee system cannot be accessed on the weekend.'),(33,'That period does not exist today.'),(34,'You do not have permission to modify this event.'),(35,'You do not have permission to add an event of this context.'),(36,'You are attempting to submit work to a submission box that has not yet opened.'),(37,'You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.'),(38,'This is already in the database. Please modify, then try again.'),(39,'No teams exist. You must add teams before trying to add events.'),(40,'Attendances for Grimwade have not yet been implemented. Please wait until further notice.'),(41,'That file does not exist.'),(42,'No email account information was found. Please either use an external mail client to send email, or provide the schoolbox with an email address.'),(43,'The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.'),(44,'You are trying to view or delete work that either does not exist or does not belong to you.'),(45,'Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".'),(46,'You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.'),(47,'You do not have permission to modify this user\'s file.'),(48,'You do not have permission to delete this user\'s file.'),(49,'The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.'),(50,'You do not have permission to delete this user\'s file.'),(51,'The section you are trying to add questions to does not exist.'),(52,'This link is already shared in your ePortfolio.'),(100,'Your e-portfolio has not been enabled.'),(101,'The resource you are trying to access does not exist or you do not have permission to view it.'),(102,'A booking already exists during the time you have selected.  Please press back and select a time that is free.'),(103,'The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.'),(104,'You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.'),(105,'<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>'),(106,'You do not have permission to modify or delete this link.'),(107,'You do not have permission to modify this blog.'),(108,'You do not have permission to leave comments on this blog.'),(109,'You do not have permission to publish this file.'),(110,'You do not have permission to modify these notes.'),(111,'There are no blogs in this archive.'),(112,'This assessment no longer exists.'),(113,'You have not answered all questions; please go back and submit them.'),(114,'You have not selected an attempt to review.'),(115,'This section no longer exists.'),(116,'Unspecified question type.'),(117,'Please choose a group for this assessment.'),(118,'This attempt no longer exists.'),(119,'This question no longer exists.'),(120,'This online assessment has expired.'),(121,'You have already attempted this online assessment and cannot re-sit it.'),(122,'You have run out of time to complete this online assessment and cannot re-sit it.'),(123,'This attempt has not been recorded.'),(124,'You are the owner of this test and therefore cannot attempt it.'),(125,'You do not have permission to view this page.'),(126,'You do not have permissions to create and modify tests.'),(127,'Only the test owner can review this test.'),(128,'This attempt no longer exists.'),(129,'You are not allowed to view this students test.'),(130,'You do not have permissions to create and modify test contents.'),(131,'Question could not be saved.'),(132,'Answer could not be saved.'),(133,'Could not load question: unknown type.'),(134,'No import file found.'),(135,'This is not a valid Assess Question XML.'),(136,'Unable to create or modify folder as class code already exists.'),(137,'There are no questions to attempt in this assessment.'),(138,'This username already exists.'),(139,'The link provided is not a correctly formatted URL.'),(140,'You already have that link in your list.'),(141,'WebDAV error: %MESSAGE%'),(142,'You do not have permissions to delete this attempt(s).'),(143,'This Online Assessment is not open yet.'),(144,'This Survey is not open yet.'),(145,'Start time cannot be later than finish time.'),(146,'You have reached the maximum number of attempts.'),(153,'You are currently not enrolled in any classes so this page is unavailable'),(147,'Please specify a class code, date and period.'),(148,'This class does not exist.'),(149,'Please specify a date in the format: yyyy-mm-dd'),(150,'Please specify a valid period number.'),(151,'You cannot edit attendance records this far in the past.'),(152,'You cannot edit attendance records in the future.'),(154,'You do not have write permission to any categories.'),(155,'You cannot view the Parent Directory for the specified year level.');
/*!40000 ALTER TABLE `error` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event`
--

DROP TABLE IF EXISTS `event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `series_id` int(6) DEFAULT NULL,
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
  `attendance` tinyint(1) DEFAULT NULL,
  `publish_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `access` (`access`,`sid`),
  KEY `owner` (`owner`),
  KEY `date` (`date`),
  FULLTEXT KEY `fulltext` (`note`,`detail`,`location`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event`
--

LOCK TABLES `event` WRITE;
/*!40000 ALTER TABLE `event` DISABLE KEYS */;
/*!40000 ALTER TABLE `event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventCampus`
--

DROP TABLE IF EXISTS `eventCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventCampus` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `campusID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eventID`,`campusID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventCampus`
--

LOCK TABLES `eventCampus` WRITE;
/*!40000 ALTER TABLE `eventCampus` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventCampus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventGroup`
--

DROP TABLE IF EXISTS `eventGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventGroup` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `groupID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eventID`,`groupID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventGroup`
--

LOCK TABLES `eventGroup` WRITE;
/*!40000 ALTER TABLE `eventGroup` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventGroup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_attendance`
--

DROP TABLE IF EXISTS `event_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `intention` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attendance`
--

LOCK TABLES `event_attendance` WRITE;
/*!40000 ALTER TABLE `event_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file`
--

DROP TABLE IF EXISTS `file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file`
--

LOCK TABLES `file` WRITE;
/*!40000 ALTER TABLE `file` DISABLE KEYS */;
/*!40000 ALTER TABLE `file` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_type`
--

DROP TABLE IF EXISTS `file_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_type` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_type`
--

LOCK TABLES `file_type` WRITE;
/*!40000 ALTER TABLE `file_type` DISABLE KEYS */;
INSERT INTO `file_type` (`id`, `name`) VALUES (1,'Assessment Task'),(2,'Assignment'),(3,'Homework'),(6,'Research Project'),(5,'Essay'),(7,'Class Work'),(8,'Presentation'),(9,'Oral'),(10,'Extension'),(11,'Online Assessment');
/*!40000 ALTER TABLE `file_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forum`
--

DROP TABLE IF EXISTS `forum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forum`
--

LOCK TABLES `forum` WRITE;
/*!40000 ALTER TABLE `forum` DISABLE KEYS */;
/*!40000 ALTER TABLE `forum` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forumPost`
--

DROP TABLE IF EXISTS `forumPost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forumPost`
--

LOCK TABLES `forumPost` WRITE;
/*!40000 ALTER TABLE `forumPost` DISABLE KEYS */;
/*!40000 ALTER TABLE `forumPost` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forumThread`
--

DROP TABLE IF EXISTS `forumThread`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forumThread`
--

LOCK TABLES `forumThread` WRITE;
/*!40000 ALTER TABLE `forumThread` DISABLE KEYS */;
/*!40000 ALTER TABLE `forumThread` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL DEFAULT '',
  `group_type` enum('guest','student','parent','staff') DEFAULT 'guest',
  `can_change_password` enum('true','false') DEFAULT 'true',
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` (`id`, `name`, `group_type`, `can_change_password`) VALUES (1,'Administration','staff','true'),(2,'Teachers','staff','true'),(3,'Students','student','true'),(4,'Parents','parent','true');
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepageComponentCategory`
--

DROP TABLE IF EXISTS `homepageComponentCategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepageComponentCategory` (
  `homepageComponentCategoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  PRIMARY KEY (`homepageComponentCategoryID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepageComponentCategory`
--

LOCK TABLES `homepageComponentCategory` WRITE;
/*!40000 ALTER TABLE `homepageComponentCategory` DISABLE KEYS */;
INSERT INTO `homepageComponentCategory` (`homepageComponentCategoryID`, `name`) VALUES (1,'Information'),(2,'Collaboration'),(3,'Learning'),(4,'Images'),(5,'Resources'),(6,'Feeds');
/*!40000 ALTER TABLE `homepageComponentCategory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepageTemplate`
--

DROP TABLE IF EXISTS `homepageTemplate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepageTemplate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `layout` smallint(6) DEFAULT '1',
  `left_column_width` float DEFAULT '69.9',
  `right_column_width` float DEFAULT '29.9',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepageTemplate`
--

LOCK TABLES `homepageTemplate` WRITE;
/*!40000 ALTER TABLE `homepageTemplate` DISABLE KEYS */;
INSERT INTO `homepageTemplate` (`id`, `name`, `layout`, `left_column_width`, `right_column_width`) VALUES (1,'Class',1,69.9,29.9),(2,'Collab Group',1,69.9,29.9),(4,'Faculty',1,69.9,29.9),(3,'Extra-Curricular',1,69.9,29.9),(5,'Committee',1,69.9,29.9),(6,'House',1,69.9,29.9),(7,'Blog',1,69.9,29.9),(8,'Blank',1,69.9,29.9);
/*!40000 ALTER TABLE `homepageTemplate` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepageTemplateLayout`
--

DROP TABLE IF EXISTS `homepageTemplateLayout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepageTemplateLayout` (
  `templateID` int(11) DEFAULT NULL,
  `componentId` int(11) DEFAULT NULL,
  `column` smallint(6) DEFAULT NULL,
  `position` smallint(6) DEFAULT NULL,
  UNIQUE KEY `table` (`templateID`,`componentId`,`column`,`position`),
  KEY `homepageID` (`templateID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepageTemplateLayout`
--

LOCK TABLES `homepageTemplateLayout` WRITE;
/*!40000 ALTER TABLE `homepageTemplateLayout` DISABLE KEYS */;
INSERT INTO `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) VALUES (1,2,2,3),(1,3,1,3),(1,8,1,1),(1,9,2,5),(1,10,2,6),(1,25,2,2),(1,28,1,2),(1,30,2,1),(1,35,2,4),(2,3,1,3),(2,4,2,1),(2,5,2,3),(2,8,1,1),(2,10,2,5),(2,21,2,2),(2,34,1,2),(2,35,2,4),(3,2,2,5),(3,3,1,3),(3,4,2,2),(3,5,2,3),(3,8,1,2),(3,10,2,6),(3,21,2,1),(3,23,1,1),(3,33,2,4),(3,35,1,4),(4,2,2,3),(4,3,1,3),(4,8,1,2),(4,10,2,4),(4,13,2,1),(4,15,1,1),(4,20,2,2),(5,3,1,3),(5,4,2,2),(5,5,2,3),(5,8,1,1),(5,10,2,5),(5,21,2,2),(5,34,1,2),(5,35,2,4),(6,3,1,3),(6,4,2,1),(6,5,2,3),(6,8,1,1),(6,10,2,5),(6,21,2,2),(6,34,1,2),(6,35,2,4),(7,2,2,2),(7,10,2,3),(7,33,2,1),(7,39,1,1);
/*!40000 ALTER TABLE `homepageTemplateLayout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_comp`
--

DROP TABLE IF EXISTS `homepage_comp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=MyISAM AUTO_INCREMENT=48 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_comp`
--

LOCK TABLES `homepage_comp` WRITE;
/*!40000 ALTER TABLE `homepage_comp` DISABLE KEYS */;
INSERT INTO `homepage_comp` (`id`, `size`, `method`, `name`, `description`, `homepageCategoryID`, `filename`, `config`, `invisible`, `allowMultiple`) VALUES (8,1,'outputNews','News','Provides a text area for you to post information, news, blogs and introductions. ',1,'news_n.htm','a:2:{s:13:\"componentname\";s:4:\"News\";s:4:\"size\";s:1:\"0\";}','false','true'),(2,1,'outputLinkList','Links','Provide links to interesting, related and helpful resources on Schoolbox or the WWW.',5,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','true'),(3,1,'outputFileList','Files','List general files that have been uploaded to your homepage. The large version shows both titles and comments.',5,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}','false','true'),(4,2,'outputMembers','Group Members','Show the group members associated with your homepage.  This also provides an email all group members link.',2,'members_s.htm','','false','false'),(5,2,'outputForumList','Forum','Show recent discussion topics going on in your homepages forum.',2,'forum_s.htm','a:2:{s:4:\"sort\";s:4:\"Date\";s:4:\"size\";i:0;}','false','true'),(9,2,'initSubject','Class Management','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.',3,'class_manage.htm','','false','false'),(10,2,'folderManage','Folder Management','Provides links to browse the files, manage group membership and view homepage statistics.',5,'folder_manage.htm','','false','false'),(13,2,'outputManagers','Group Managers','Displays a list of group members with write access to the homepage.  Useful in larger groups to show manages of the group.',2,'staff_s.htm','','false','false'),(15,1,'outputImage','Image','Completely configurable image display, select the image, control the size and link to another location',4,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(20,2,'outputFolderList','Folders','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.',5,'folders_s.htm','a:1:{s:4:\"size\";i:0;}','false','true'),(21,1,'outputEventList','Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(46,0,'outputClickview','ClickView','Embed Clickview Home videos directly into your homepage by providing the ID of the video',5,'clickview.html','a:1:{s:3:\"url\";s:0:\"\";}','false','true'),(23,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ',4,'gallery.htm','a:3:{s:13:\"componentname\";s:13:\"Image Gallery\";s:4:\"size\";s:1:\"0\";s:21:\"homepage-imagegallery\";s:0:\"\";}','false','true'),(24,1,'outputRandImage','Random Image','Display a random image selected from the images on this homepage.',4,'image_s.htm','a:1:{s:5:\"pixel\";i:400;}','false','false'),(25,2,'outputDropBox','File Drop Box','Provides an area for teachers to receive files from students without the need for a submission box and due date',3,'dropbox.htm','','false','false'),(28,1,'outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.',3,'duework_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(26,1,'outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.',6,'rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','false','true'),(30,2,'outputTeachers','Class Teachers','Show a list of teachers responsible for this subject if it has a synergetic subject code.',3,'teacher_s.htm','','false','false'),(31,1,'outputCalendarList','Campus Calendar','Show a calendar for the entire campus on your homepage.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(32,0,'outputDuties','Duties',NULL,1,'duties.htm','','true','false'),(33,2,'outputPoll','Poll','Create a poll to determine group members opinion on a given subject.',2,'poll.htm','a:1:{s:6:\"pollID\";i:0;}','false','true'),(34,1,'outputWiki','Wiki','Provides an area for group members to work together on joint documents.',2,'wiki.htm','a:5:{s:13:\"componentname\";s:4:\"Wiki\";s:13:\"homepage-wiki\";s:0:\"\";s:4:\"sort\";s:7:\"Heading\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','true'),(35,1,'outputFeed','Homepage Feed','Provides a running commentary on recent changes to the homepage',6,'feed.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(36,1,'outputTextBox','Text Box','Use this space to write introductory message or information relating to the content that is displayed',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(37,1,'outputAudio','Audio','Lists audio files and allows them to be played.',5,'audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(38,1,'outputPodcast','Podcast','Subscribe to audio podcasts and provide inline playback of the media directly in the homepage',6,'podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false','true'),(39,1,'outputBlog','Blog','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.',1,'blog.html','a:4:{s:13:\"componentname\";s:4:\"Blog\";s:4:\"size\";s:1:\"3\";s:13:\"comments_size\";s:1:\"2\";s:13:\"homepage-blog\";s:0:\"\";}','false','true'),(40,2,'outputTranslate','Language Translator','Translates text and webpages into another language',3,'translate.html','','true','false'),(41,1,'outputScroller','Image Carousel','Display an animated image carousel, fading between all the images uploaded to your homepage',4,'scroller.html','a:3:{s:5:\"pixel\";s:3:\"200\";s:12:\"captionState\";s:6:\"Active\";s:6:\"timing\";i:5;}','false','true'),(42,1,'outputAssess','Online Assessment','Create tests and quizzes for your students. Results show in the class marksheet. Build resources using the school question bank.',3,'assess.html','','false','false'),(43,0,'outputChat','Chat','Allows users who are viewing the same homepage to chat with each other.',2,'chat.html','a:2:{s:5:\"state\";s:3:\"off\";s:17:\"teacher_timestamp\";s:0:\"\";}','false','false'),(44,1,'outputSurvey','Surveys','Create online surveys or forms to collect data from your class or group.  Useful for collecting feedback or enrolling in electives.',2,'survey.html','','false','false'),(45,0,'outputTwitter','Twitter','Connect and feed Twitter posts directly on to your homepage. Search users, hashtags or keywords to create dynamic streams.',6,'twitter.html','a:3:{s:8:\"username\";s:7:\"alaress\";s:8:\"keywords\";s:4:\"kony\";s:9:\"maxtweets\";s:1:\"3\";}','false','false'),(47,0,'outputCountdown','Countdown','Displays a countdown to the specified time.',1,'countdown.html','a:3:{s:13:\"componentname\";s:9:\"Countdown\";s:4:\"time\";s:0:\"\";s:4:\"date\";s:0:\"\";}','false','false');
/*!40000 ALTER TABLE `homepage_comp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_comp_location`
--

DROP TABLE IF EXISTS `homepage_comp_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_comp_location`
--

LOCK TABLES `homepage_comp_location` WRITE;
/*!40000 ALTER TABLE `homepage_comp_location` DISABLE KEYS */;
/*!40000 ALTER TABLE `homepage_comp_location` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_config`
--

DROP TABLE IF EXISTS `homepage_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepage_config` (
  `cid` int(6) unsigned NOT NULL DEFAULT '0',
  `template` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `left_column_width` float DEFAULT '29.5',
  `right_column_width` float DEFAULT '79.5',
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
  `top_head_font` char(7) DEFAULT NULL,
  `top_head_bg` char(7) DEFAULT NULL,
  `top_font` char(7) DEFAULT NULL,
  `top_bg_primary` char(7) DEFAULT NULL,
  `top_bg_secondary` char(7) DEFAULT NULL,
  `top_link` char(7) DEFAULT NULL,
  `top_hover` char(7) DEFAULT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_config`
--

LOCK TABLES `homepage_config` WRITE;
/*!40000 ALTER TABLE `homepage_config` DISABLE KEYS */;
INSERT INTO `homepage_config` (`cid`, `template`, `left_column_width`, `right_column_width`, `left_head_font`, `left_head_bg`, `left_font`, `left_bg_primary`, `left_bg_secondary`, `left_link`, `left_hover`, `right_head_font`, `right_head_bg`, `right_font`, `right_bg_primary`, `right_bg_secondary`, `right_link`, `right_hover`, `top_head_font`, `top_head_bg`, `top_font`, `top_bg_primary`, `top_bg_secondary`, `top_link`, `top_hover`) VALUES (7,8,69.9,29.9,'ffffff','0b6bda','000000','ffffff','faf9f5','1d9ffc','ff5a00','ffffff','0b6bda','000000','ffffff','faf9f5','1d9ffc','ff5a00','ffffff','0b6bda','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(9,8,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(12,3,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(13,3,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(14,3,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(18,4,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(19,4,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(21,4,49.5,49.5,'ffffff','219b12','000000','ffffff','faf9f5','1d9ffc','ff5a00','ffffff','219b12','000000','ffffff','faf9f5','1d9ffc','ff5a00','ffffff','219b12','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(25,8,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(26,2,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(27,8,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(28,8,69.9,29.9,'013a63','bee2fe','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','bee2fe','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','bee2fe','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(32,8,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(33,8,29.5,69.5,'ffffff','4803d8','000000','ffffff','faf9f5','1d9ffc','ff5a00','ffffff','4803d8','000000','ffffff','faf9f5','1d9ffc','ff5a00','ffffff','4803d8','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(35,2,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(36,8,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(37,3,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(38,8,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(15,4,69.9,29.9,'013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00','013a63','cae9b9','000000','ffffff','faf9f5','1d9ffc','ff5a00');
/*!40000 ALTER TABLE `homepage_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_log`
--

DROP TABLE IF EXISTS `homepage_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepage_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT '0',
  `cid` int(6) NOT NULL DEFAULT '0',
  `hitcount` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`user`,`cid`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_log`
--

LOCK TABLES `homepage_log` WRITE;
/*!40000 ALTER TABLE `homepage_log` DISABLE KEYS */;
INSERT INTO `homepage_log` (`datetime`, `user`, `cid`, `hitcount`) VALUES ('2011-05-05 00:39:34',1,3,3),('2013-04-15 17:20:17',1,7,14),('2013-04-15 15:08:31',1,6,0),('2013-04-15 17:19:52',1,8,2),('2013-04-15 15:10:34',1,9,4),('2013-04-15 15:27:18',1,11,2),('2013-04-15 15:25:29',1,12,2),('2013-04-15 17:33:50',1,13,1),('2013-04-15 15:28:09',1,4,0),('2013-04-15 17:34:40',1,15,3),('2013-04-15 15:29:26',1,16,1),('2013-04-15 15:31:13',1,18,4),('2013-04-15 15:33:34',1,20,5),('2013-04-15 17:16:38',1,21,7),('2013-04-15 15:36:48',1,23,2),('2013-04-15 15:34:51',1,22,0),('2013-04-15 15:38:47',1,27,3),('2013-04-15 17:34:05',1,28,26),('2013-04-15 16:00:50',1,29,2),('2013-04-15 17:25:09',1,31,1),('2013-04-15 17:33:54',1,32,19),('2013-04-15 16:01:55',1,30,2),('2013-04-15 16:47:33',1,33,7),('2013-04-15 16:00:20',1,35,0),('2013-04-15 16:12:42',1,14,1),('2013-04-15 16:47:46',1,36,2),('2013-04-15 16:47:44',1,37,0),('2013-04-15 17:17:38',1,26,0),('2013-04-15 17:24:58',1,34,0),('2013-04-15 17:28:32',1,38,4);
/*!40000 ALTER TABLE `homepage_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_visits`
--

DROP TABLE IF EXISTS `homepage_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepage_visits` (
  `date` date NOT NULL,
  `user_group_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `hitcount` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`date`,`user_group_id`,`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_visits`
--

LOCK TABLES `homepage_visits` WRITE;
/*!40000 ALTER TABLE `homepage_visits` DISABLE KEYS */;
INSERT INTO `homepage_visits` (`date`, `user_group_id`, `category_id`, `hitcount`) VALUES ('2013-04-15',1,7,15),('2013-04-15',1,6,1),('2013-04-15',1,8,3),('2013-04-15',1,9,5),('2013-04-15',1,11,3),('2013-04-15',1,12,3),('2013-04-15',1,13,2),('2013-04-15',1,4,1),('2013-04-15',1,15,4),('2013-04-15',1,16,2),('2013-04-15',1,18,5),('2013-04-15',1,20,6),('2013-04-15',1,21,8),('2013-04-15',1,23,3),('2013-04-15',1,22,1),('2013-04-15',1,27,4),('2013-04-15',1,28,27),('2013-04-15',1,29,3),('2013-04-15',1,31,2),('2013-04-15',1,32,20),('2013-04-15',1,30,3),('2013-04-15',1,33,8),('2013-04-15',1,35,1),('2013-04-15',1,14,2),('2013-04-15',1,36,3),('2013-04-15',1,37,1),('2013-04-15',1,26,1),('2013-04-15',1,34,1),('2013-04-15',1,38,5);
/*!40000 ALTER TABLE `homepage_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `house`
--

DROP TABLE IF EXISTS `house`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `house` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `house` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `house`
--

LOCK TABLES `house` WRITE;
/*!40000 ALTER TABLE `house` DISABLE KEYS */;
/*!40000 ALTER TABLE `house` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `link`
--

DROP TABLE IF EXISTS `link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `link`
--

LOCK TABLES `link` WRITE;
/*!40000 ALTER TABLE `link` DISABLE KEYS */;
/*!40000 ALTER TABLE `link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_log`
--

DROP TABLE IF EXISTS `login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_log` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL DEFAULT '',
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip` varchar(15) NOT NULL DEFAULT '',
  `login` tinyint(1) unsigned DEFAULT NULL,
  `logout` tinyint(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `date` (`date`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_log`
--

LOCK TABLES `login_log` WRITE;
/*!40000 ALTER TABLE `login_log` DISABLE KEYS */;
INSERT INTO `login_log` (`id`, `username`, `date`, `ip`, `login`, `logout`) VALUES (1,'jleckie','2011-05-05 00:04:51','127.0.0.1',1,NULL),(2,'jleckie','2011-05-05 00:05:34','127.0.0.1',1,NULL),(3,'alaress','2011-05-05 00:50:32','127.0.0.1',1,NULL),(4,'alaress','2011-11-24 10:05:02','192.168.1.155',0,NULL),(5,'alaress','2011-11-24 10:05:05','192.168.1.155',1,NULL),(6,'alaress','2012-11-23 16:07:10','192.168.1.88',1,NULL),(7,'alaress','2013-04-15 14:37:44','192.168.1.88',1,NULL),(8,'alaress','2013-04-15 14:48:48','192.168.1.120',1,NULL),(9,'alaress','2013-04-16 08:45:28','192.168.1.88',0,NULL),(10,'alaress','2013-04-16 08:45:33','192.168.1.88',1,NULL);
/*!40000 ALTER TABLE `login_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `luCampus`
--

DROP TABLE IF EXISTS `luCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `luCampus` (
  `name` varchar(255) DEFAULT NULL,
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luCampus`
--

LOCK TABLES `luCampus` WRITE;
/*!40000 ALTER TABLE `luCampus` DISABLE KEYS */;
INSERT INTO `luCampus` (`name`, `id`) VALUES ('Main',1),('Other',2);
/*!40000 ALTER TABLE `luCampus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `luEventType`
--

DROP TABLE IF EXISTS `luEventType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `luEventType` (
  `eventTypeID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventType` varchar(100) DEFAULT NULL,
  `eventColour` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`eventTypeID`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luEventType`
--

LOCK TABLES `luEventType` WRITE;
/*!40000 ALTER TABLE `luEventType` DISABLE KEYS */;
INSERT INTO `luEventType` (`eventTypeID`, `eventType`, `eventColour`) VALUES (1,'Event','cee6ea'),(2,'Excursion','f8deb8'),(3,'Report','d4efc4'),(4,'Exam','F2CED6'),(5,'Meeting','d2cef2'),(6,'Online Assessment','fff3b2'),(7,'Holidays','E01B3C'),(8,'Task','B8DDFF');
/*!40000 ALTER TABLE `luEventType` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `luResidentialHouse`
--

DROP TABLE IF EXISTS `luResidentialHouse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `luResidentialHouse` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luResidentialHouse`
--

LOCK TABLES `luResidentialHouse` WRITE;
/*!40000 ALTER TABLE `luResidentialHouse` DISABLE KEYS */;
/*!40000 ALTER TABLE `luResidentialHouse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `luServices`
--

DROP TABLE IF EXISTS `luServices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `luServices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luServices`
--

LOCK TABLES `luServices` WRITE;
/*!40000 ALTER TABLE `luServices` DISABLE KEYS */;
INSERT INTO `luServices` (`id`, `name`) VALUES (1,'Homepage'),(2,'Calendar'),(3,'News'),(4,'Tutorial'),(5,'CMS');
/*!40000 ALTER TABLE `luServices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `luTitle`
--

DROP TABLE IF EXISTS `luTitle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `luTitle` (
  `titleID` int(11) NOT NULL AUTO_INCREMENT,
  `titleName` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`titleID`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luTitle`
--

LOCK TABLES `luTitle` WRITE;
/*!40000 ALTER TABLE `luTitle` DISABLE KEYS */;
INSERT INTO `luTitle` (`titleID`, `titleName`) VALUES (2,'Mr'),(3,'Mrs'),(4,'Ms'),(9,'Prof'),(8,'Hon Dr'),(7,'Hon'),(6,'Dr'),(5,'Miss'),(10,'A/Prof'),(1,NULL);
/*!40000 ALTER TABLE `luTitle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_timetable_colour`
--

DROP TABLE IF EXISTS `lu_timetable_colour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_timetable_colour` (
  `timetable_colour_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hex_colour` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`timetable_colour_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_timetable_colour`
--

LOCK TABLES `lu_timetable_colour` WRITE;
/*!40000 ALTER TABLE `lu_timetable_colour` DISABLE KEYS */;
INSERT INTO `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) VALUES (17,'ffe0cc'),(18,'ccffcc'),(19,'cce0ff'),(20,'ffccf5'),(21,'fff5cc'),(22,'ccffe0'),(23,'ccccff'),(24,'ffcce0'),(25,'f5ffcc'),(26,'ccfff5'),(27,'e0ccff'),(28,'ffcccc'),(29,'e0ffcc'),(30,'ccf5ff'),(31,'f5ccff');
/*!40000 ALTER TABLE `lu_timetable_colour` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `myFile`
--

DROP TABLE IF EXISTS `myFile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `myFile`
--

LOCK TABLES `myFile` WRITE;
/*!40000 ALTER TABLE `myFile` DISABLE KEYS */;
/*!40000 ALTER TABLE `myFile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `myFolder`
--

DROP TABLE IF EXISTS `myFolder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `myFolder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL DEFAULT '0',
  `parent` int(11) NOT NULL DEFAULT '0',
  `name` mediumtext NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `myFolder`
--

LOCK TABLES `myFolder` WRITE;
/*!40000 ALTER TABLE `myFolder` DISABLE KEYS */;
/*!40000 ALTER TABLE `myFolder` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsCampus`
--

DROP TABLE IF EXISTS `newsCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsCampus` (
  `id` int(6) unsigned NOT NULL DEFAULT '0',
  `campus` int(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`campus`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsCampus`
--

LOCK TABLES `newsCampus` WRITE;
/*!40000 ALTER TABLE `newsCampus` DISABLE KEYS */;
INSERT INTO `newsCampus` (`id`, `campus`) VALUES (1,1);
/*!40000 ALTER TABLE `newsCampus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsGroup`
--

DROP TABLE IF EXISTS `newsGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsGroup` (
  `id` int(6) unsigned NOT NULL DEFAULT '0',
  `gid` int(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`gid`),
  KEY `group` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsGroup`
--

LOCK TABLES `newsGroup` WRITE;
/*!40000 ALTER TABLE `newsGroup` DISABLE KEYS */;
INSERT INTO `newsGroup` (`id`, `gid`) VALUES (1,1);
/*!40000 ALTER TABLE `newsGroup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsTopic`
--

DROP TABLE IF EXISTS `newsTopic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsTopic` (
  `newsTopicID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`newsTopicID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsTopic`
--

LOCK TABLES `newsTopic` WRITE;
/*!40000 ALTER TABLE `newsTopic` DISABLE KEYS */;
INSERT INTO `newsTopic` (`newsTopicID`, `name`) VALUES (1,'General'),(2,'Music'),(3,'Sport'),(4,'Academic');
/*!40000 ALTER TABLE `newsTopic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsUser`
--

DROP TABLE IF EXISTS `newsUser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsUser` (
  `newsID` int(10) unsigned NOT NULL,
  `userID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`newsID`,`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsUser`
--

LOCK TABLES `newsUser` WRITE;
/*!40000 ALTER TABLE `newsUser` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsUser` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `news_items`
--

DROP TABLE IF EXISTS `news_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news_items` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_topic_id` smallint(5) unsigned DEFAULT '0',
  `item_heading` varchar(50) NOT NULL DEFAULT '',
  `item_datetime_posted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `item_datetime_published` date NOT NULL,
  `item_datetime_expires` date NOT NULL DEFAULT '0000-00-00',
  `item_datetime_modified` datetime DEFAULT NULL,
  `item_mainpage` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `item_body` mediumtext NOT NULL,
  `item_deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `item_owner` int(6) unsigned NOT NULL DEFAULT '0',
  `item_campus` int(4) DEFAULT '0',
  `item_group` int(4) unsigned DEFAULT NULL,
  `item_image` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `access` (`item_topic_id`,`item_campus`),
  KEY `topic` (`item_topic_id`),
  KEY `published` (`item_datetime_published`),
  KEY `expires` (`item_datetime_expires`),
  FULLTEXT KEY `fulltext` (`item_heading`,`item_body`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news_items`
--

LOCK TABLES `news_items` WRITE;
/*!40000 ALTER TABLE `news_items` DISABLE KEYS */;
INSERT INTO `news_items` (`item_id`, `item_topic_id`, `item_heading`, `item_datetime_posted`, `item_datetime_published`, `item_datetime_expires`, `item_datetime_modified`, `item_mainpage`, `item_body`, `item_deleted`, `item_owner`, `item_campus`, `item_group`, `item_image`) VALUES (1,1,'Welcome to Schoolbox!','2011-11-24 10:25:42','2011-11-24','2031-12-01','2011-11-24 10:26:25',0,'<p>Welcome to Schoolbox, our Fantastic Community Base Learning Management System!</p>\r\n<p>&nbsp;</p>\r\n<p>You will find as the Administrator Most of your tools are located <a href=\"/adminv2/home\">here </a></p>\r\n<p>The way you can get to this normally is via the Side menu on the left of the screen, simply go to Home, then Click on Admin!</p>\r\n<p>This item is only visible to Super users.</p>\r\n<p>&nbsp;</p>\r\n<p>To Delete this post, Click on the modify icon in the top right corner of this post and then the delete button on the modify page.</p>',0,1,0,NULL,NULL);
/*!40000 ALTER TABLE `news_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perms`
--

DROP TABLE IF EXISTS `perms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL DEFAULT '0',
  `gid` int(4) NOT NULL DEFAULT '0',
  `type` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perms`
--

LOCK TABLES `perms` WRITE;
/*!40000 ALTER TABLE `perms` DISABLE KEYS */;
INSERT INTO `perms` (`cid`, `gid`, `type`) VALUES (4,1,2),(4,2,2),(4,3,0),(4,4,0),(5,1,2),(5,2,2),(5,3,0),(5,4,0),(6,1,2),(6,2,0),(6,3,0),(6,4,0),(7,4,1),(7,3,1),(7,2,1),(7,1,2),(8,1,2),(8,2,0),(8,3,0),(8,4,0),(10,1,2),(10,2,0),(10,3,0),(10,4,0),(11,1,2),(11,2,1),(11,3,1),(11,4,1),(12,1,2),(12,2,2),(12,3,1),(12,4,1),(13,4,1),(13,3,1),(13,2,2),(13,1,2),(14,4,1),(14,3,1),(14,2,2),(14,1,2),(15,4,0),(15,3,0),(15,2,2),(15,1,2),(16,1,2),(16,2,2),(16,3,0),(16,4,0),(17,1,2),(17,2,2),(17,3,0),(17,4,0),(18,4,1),(18,3,1),(18,2,2),(18,1,2),(19,1,2),(19,2,2),(19,3,1),(19,4,1),(20,1,2),(20,2,0),(20,3,0),(20,4,0),(21,1,2),(21,2,1),(21,3,1),(21,4,1),(22,1,2),(22,2,1),(22,3,1),(22,4,1),(23,1,2),(23,2,1),(23,3,1),(23,4,1),(24,1,2),(24,2,0),(24,3,0),(24,4,0),(25,1,2),(25,2,1),(25,3,0),(25,4,1),(26,4,1),(26,3,1),(26,2,1),(26,1,2),(27,1,2),(27,2,1),(27,3,1),(27,4,1),(28,1,2),(28,2,1),(28,3,1),(28,4,1),(29,1,2),(29,2,0),(29,3,0),(29,4,1),(30,1,2),(30,2,1),(30,3,0),(30,4,0),(31,1,2),(31,2,0),(31,3,1),(31,4,1),(32,4,1),(32,3,1),(32,2,0),(32,1,2),(33,1,2),(33,2,2),(33,3,0),(33,4,0),(34,1,2),(34,2,2),(34,3,0),(34,4,0),(35,1,2),(35,2,2),(35,3,0),(35,4,0),(36,1,2),(36,2,0),(36,3,0),(36,4,1),(37,1,2),(37,2,1),(37,3,1),(37,4,1);
/*!40000 ALTER TABLE `perms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plagscan_document`
--

DROP TABLE IF EXISTS `plagscan_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plagscan_document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `plagscan_id` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '0',
  `score` float unsigned DEFAULT NULL,
  `html` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plagscan_document`
--

LOCK TABLES `plagscan_document` WRITE;
/*!40000 ALTER TABLE `plagscan_document` DISABLE KEYS */;
/*!40000 ALTER TABLE `plagscan_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `poll`
--

DROP TABLE IF EXISTS `poll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poll`
--

LOCK TABLES `poll` WRITE;
/*!40000 ALTER TABLE `poll` DISABLE KEYS */;
INSERT INTO `poll` (`pollID`, `categoryID`, `title`, `question`, `option_0`, `option_1`, `option_2`, `option_3`, `option_4`, `option_5`, `option_6`, `option_7`, `option_8`, `option_9`, `result_0`, `result_1`, `result_2`, `result_3`, `result_4`, `result_5`, `result_6`, `result_7`, `result_8`, `result_9`, `frequency`, `created`, `close`) VALUES (1,28,'Weekly Library Poll','What\'s your favourite genre of reading?','History & Biography','Fiction & Mystery','Poetry & Prose','Contemporary Life','','','','','','',0,0,0,0,0,0,0,0,0,0,'hourly',NULL,'2099-01-01 00:00:00');
/*!40000 ALTER TABLE `poll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pollLog`
--

DROP TABLE IF EXISTS `pollLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pollLog` (
  `pollID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  KEY `pollID` (`pollID`,`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pollLog`
--

LOCK TABLES `pollLog` WRITE;
/*!40000 ALTER TABLE `pollLog` DISABLE KEYS */;
/*!40000 ALTER TABLE `pollLog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `revision`
--

DROP TABLE IF EXISTS `revision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revision` (
  `revisionID` int(11) NOT NULL AUTO_INCREMENT,
  `wikiID` int(11) DEFAULT NULL,
  `heading` varchar(200) DEFAULT NULL,
  `body` mediumtext,
  `userID` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`revisionID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `revision`
--

LOCK TABLES `revision` WRITE;
/*!40000 ALTER TABLE `revision` DISABLE KEYS */;
/*!40000 ALTER TABLE `revision` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rubric_criterion`
--

DROP TABLE IF EXISTS `rubric_criterion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_criterion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grouping` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `parent_type` enum('submission','category','curriculum') NOT NULL DEFAULT 'category',
  PRIMARY KEY (`id`),
  UNIQUE KEY `grouping` (`grouping`,`name`,`parent_id`,`parent_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rubric_criterion`
--

LOCK TABLES `rubric_criterion` WRITE;
/*!40000 ALTER TABLE `rubric_criterion` DISABLE KEYS */;
/*!40000 ALTER TABLE `rubric_criterion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rubric_descriptor`
--

DROP TABLE IF EXISTS `rubric_descriptor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_descriptor` (
  `criterion_id` int(10) unsigned NOT NULL,
  `submission_box_id` int(10) unsigned NOT NULL,
  `value` int(10) unsigned NOT NULL,
  `description` varchar(512) DEFAULT NULL,
  `valid` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`criterion_id`,`value`,`submission_box_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rubric_descriptor`
--

LOCK TABLES `rubric_descriptor` WRITE;
/*!40000 ALTER TABLE `rubric_descriptor` DISABLE KEYS */;
/*!40000 ALTER TABLE `rubric_descriptor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rubric_mark`
--

DROP TABLE IF EXISTS `rubric_mark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_mark` (
  `student_id` int(10) unsigned NOT NULL,
  `submission_box_id` int(10) unsigned NOT NULL,
  `criterion_id` int(10) unsigned NOT NULL,
  `tstamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mark` int(10) unsigned NOT NULL,
  `valid` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`student_id`,`submission_box_id`,`criterion_id`,`tstamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rubric_mark`
--

LOCK TABLES `rubric_mark` WRITE;
/*!40000 ALTER TABLE `rubric_mark` DISABLE KEYS */;
/*!40000 ALTER TABLE `rubric_mark` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rubric_result`
--

DROP TABLE IF EXISTS `rubric_result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_result` (
  `resultset_id` int(11) NOT NULL,
  `value` int(11) NOT NULL,
  `label` varchar(32) NOT NULL,
  PRIMARY KEY (`resultset_id`,`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rubric_result`
--

LOCK TABLES `rubric_result` WRITE;
/*!40000 ALTER TABLE `rubric_result` DISABLE KEYS */;
INSERT INTO `rubric_result` (`resultset_id`, `value`, `label`) VALUES (1,0,'Never'),(1,1,'Sometimes'),(1,2,'Always'),(2,0,'None'),(2,1,'Very Poor'),(2,2,'Poor'),(2,3,'Average'),(2,4,'Good'),(2,5,'Very Good');
/*!40000 ALTER TABLE `rubric_result` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rubric_usage`
--

DROP TABLE IF EXISTS `rubric_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rubric_usage` (
  `criterion_id` int(11) NOT NULL,
  `submission_box_id` int(11) NOT NULL,
  PRIMARY KEY (`criterion_id`,`submission_box_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rubric_usage`
--

LOCK TABLES `rubric_usage` WRITE;
/*!40000 ALTER TABLE `rubric_usage` DISABLE KEYS */;
/*!40000 ALTER TABLE `rubric_usage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sideMenu`
--

DROP TABLE IF EXISTS `sideMenu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sideMenu` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(3) unsigned NOT NULL DEFAULT '0',
  `txt` varchar(150) NOT NULL DEFAULT '0',
  `url` varchar(200) DEFAULT '0',
  `target` varchar(10) DEFAULT '0',
  `sequence` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=253 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenu`
--

LOCK TABLES `sideMenu` WRITE;
/*!40000 ALTER TABLE `sideMenu` DISABLE KEYS */;
INSERT INTO `sideMenu` (`id`, `parent`, `txt`, `url`, `target`, `sequence`) VALUES (1,0,'Home','/home.php','',0),(14,1,'Resources','/resources','',1),(232,0,'Weather Forecast','/weather','',15),(230,233,'Feedback','/feedback.php','',2),(103,14,'Upload File to Multiple Folders','/cms/file/multicat','',1),(105,14,'Add Link to Multiple Folders','/cms/link/multicat','',0),(229,0,'Change Password','/account/modify','',16),(233,0,'Links and Resources','#','',14),(208,1,'Admin','/adminv2/home','',0),(225,224,'Uncompleted Attendance','/modules/tutorial/report/attendance.php','',0),(226,224,'Hours Performed','/modules/tutorial/report/hours.php','',1),(227,224,'Student Attendance','/modules/tutorial/report/student.php','',2),(228,0,'Manage Groups','/group/join','',5),(234,233,'Schoolbox Wiki','http://wiki.schoolbox.com.au','',1),(235,0,'Sport','/homepage/12','',11),(236,235,'Football','/homepage/13','',1),(237,0,'Houses','#','',3),(238,0,'Faculties','#','',1),(239,238,'English','/homepage/15','',1),(240,0,'Extra Curricular','#','',2),(241,240,'Debating','/homepage/14','',1),(242,0,'Library','/homepage/28','',4),(243,0,'IT Services','/homepage/37','',13),(244,0,'Browse Available Groups','/homepage/21','',6),(245,0,'Parent Services','#','',8),(246,245,'Forms and policies','/homepage/36','',1),(247,245,'Parent Directory','/modules/report/parents','',2),(248,0,'Staff Services','#','',10),(249,248,'Forms and Policies','/homepage/33','',1),(250,248,'Staff Directory','/modules/report/staff','',2),(251,0,'Student Services','#','',7),(252,251,'School Councellor\'s Office','/homepage/32','',1);
/*!40000 ALTER TABLE `sideMenu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sideMenuCampus`
--

DROP TABLE IF EXISTS `sideMenuCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sideMenuCampus` (
  `id` int(3) unsigned NOT NULL DEFAULT '0',
  `campus` int(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`campus`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenuCampus`
--

LOCK TABLES `sideMenuCampus` WRITE;
/*!40000 ALTER TABLE `sideMenuCampus` DISABLE KEYS */;
INSERT INTO `sideMenuCampus` (`id`, `campus`) VALUES (1,1),(2,1),(3,1),(7,1),(8,1),(9,1),(13,1),(14,1),(15,1),(93,1),(94,1),(103,1),(105,1),(214,1),(220,1),(221,1),(222,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(232,1),(233,1),(234,1),(235,1),(236,1),(237,1),(238,1),(239,1),(240,1),(241,1),(242,1),(243,1),(244,1),(245,1),(246,1),(247,1),(248,1),(249,1),(250,1),(251,1),(252,1),(220,2),(221,2),(222,2),(224,2),(225,2),(226,2),(227,2),(228,2),(229,2),(230,2),(238,2);
/*!40000 ALTER TABLE `sideMenuCampus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sideMenuGroup`
--

DROP TABLE IF EXISTS `sideMenuGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sideMenuGroup` (
  `id` int(3) unsigned NOT NULL DEFAULT '0',
  `gid` int(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenuGroup`
--

LOCK TABLES `sideMenuGroup` WRITE;
/*!40000 ALTER TABLE `sideMenuGroup` DISABLE KEYS */;
INSERT INTO `sideMenuGroup` (`id`, `gid`) VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(2,3),(2,4),(2,5),(3,1),(3,2),(3,3),(3,5),(4,1),(4,2),(4,3),(4,4),(4,5),(7,1),(7,2),(7,3),(7,4),(7,5),(8,1),(8,2),(8,3),(8,5),(9,1),(9,2),(9,3),(9,4),(9,5),(13,1),(13,2),(13,3),(13,4),(13,5),(14,1),(14,2),(14,3),(15,1),(15,2),(93,1),(93,2),(93,3),(93,4),(93,5),(94,1),(94,2),(94,3),(94,4),(94,5),(103,1),(103,2),(105,1),(105,2),(214,1),(214,2),(214,3),(214,5),(220,1),(220,2),(220,3),(220,4),(220,5),(221,1),(221,2),(222,1),(222,2),(224,1),(225,1),(226,1),(227,1),(228,1),(228,2),(228,3),(228,4),(228,5),(229,1),(229,2),(229,3),(229,4),(229,5),(230,1),(230,2),(230,3),(230,4),(230,5),(232,1),(232,2),(232,3),(232,4),(233,1),(233,2),(233,3),(233,4),(234,1),(234,2),(234,3),(234,4),(235,1),(235,2),(235,3),(235,4),(236,1),(236,2),(236,3),(236,4),(237,1),(237,2),(237,3),(237,4),(238,1),(238,2),(238,3),(239,1),(239,2),(239,3),(240,1),(240,2),(240,3),(240,4),(241,1),(241,2),(241,3),(241,4),(242,1),(242,2),(242,3),(243,1),(243,2),(243,3),(244,1),(244,2),(244,3),(244,4),(245,1),(245,4),(246,1),(246,4),(247,1),(247,4),(248,1),(248,2),(249,1),(249,2),(250,1),(250,2),(251,1),(251,3),(251,4),(252,1),(252,3),(252,4);
/*!40000 ALTER TABLE `sideMenuGroup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `storage`
--

DROP TABLE IF EXISTS `storage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage` (
  `storage_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(250) NOT NULL,
  `hash` varchar(100) NOT NULL,
  `filesize` int(11) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_on` datetime NOT NULL,
  `last_accessed` datetime DEFAULT NULL,
  PRIMARY KEY (`storage_id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `storage`
--

LOCK TABLES `storage` WRITE;
/*!40000 ALTER TABLE `storage` DISABLE KEYS */;
/*!40000 ALTER TABLE `storage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_submission`
--

DROP TABLE IF EXISTS `student_submission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_submission` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `realname` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime DEFAULT NULL,
  `marked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `late` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `box_id` int(4) unsigned NOT NULL DEFAULT '0',
  `mime_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_submission`
--

LOCK TABLES `student_submission` WRITE;
/*!40000 ALTER TABLE `student_submission` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_submission` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject_news`
--

DROP TABLE IF EXISTS `subject_news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject_news` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `heading` varchar(50) DEFAULT NULL,
  `datetime_posted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `datetime_published` date NOT NULL,
  `datetime_expires` date NOT NULL DEFAULT '0000-00-00',
  `body` mediumtext NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `code` int(6) unsigned NOT NULL DEFAULT '0',
  `owner` int(6) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`code`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject_news`
--

LOCK TABLES `subject_news` WRITE;
/*!40000 ALTER TABLE `subject_news` DISABLE KEYS */;
/*!40000 ALTER TABLE `subject_news` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_box`
--

DROP TABLE IF EXISTS `submission_box`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_box` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT '0',
  `type` int(11) DEFAULT NULL,
  `owner` int(4) unsigned NOT NULL DEFAULT '0',
  `open_date` datetime DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `late_date` datetime DEFAULT '0000-00-00 00:00:00',
  `fid` int(5) unsigned DEFAULT '0',
  `accept_submission` tinyint(1) NOT NULL DEFAULT '0',
  `multi_submission` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `description` varchar(750) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `mark_type` int(11) DEFAULT NULL,
  `max_mark` int(11) DEFAULT NULL,
  `rubric_result_set` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `file` (`fid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_box`
--

LOCK TABLES `submission_box` WRITE;
/*!40000 ALTER TABLE `submission_box` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_box` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_errors`
--

DROP TABLE IF EXISTS `submission_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_errors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(4) COLLATE utf8_bin NOT NULL,
  `display_as` varchar(16) COLLATE utf8_bin NOT NULL,
  `normalise_as` decimal(10,0) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_errors`
--

LOCK TABLES `submission_errors` WRITE;
/*!40000 ALTER TABLE `submission_errors` DISABLE KEYS */;
INSERT INTO `submission_errors` (`id`, `code`, `display_as`, `normalise_as`) VALUES (1,'ABS','Absent',0),(2,'NA','Not Assessed',NULL),(3,'NS','Not Submitted',0);
/*!40000 ALTER TABLE `submission_errors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_grade`
--

DROP TABLE IF EXISTS `submission_grade`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_grade` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `lower_bound` int(11) NOT NULL,
  `caption` varchar(64) NOT NULL,
  PRIMARY KEY (`grade_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_grade`
--

LOCK TABLES `submission_grade` WRITE;
/*!40000 ALTER TABLE `submission_grade` DISABLE KEYS */;
INSERT INTO `submission_grade` (`grade_id`, `lower_bound`, `caption`) VALUES (1,0,'E'),(2,10,'E+'),(3,20,'D'),(4,30,'D+'),(5,40,'C'),(6,50,'C+'),(7,60,'B'),(8,70,'B+'),(9,80,'A'),(10,90,'A+');
/*!40000 ALTER TABLE `submission_grade` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_log`
--

DROP TABLE IF EXISTS `submission_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT '0',
  `page` tinyint(4) NOT NULL DEFAULT '0',
  `sid` int(6) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user`,`page`,`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_log`
--

LOCK TABLES `submission_log` WRITE;
/*!40000 ALTER TABLE `submission_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_mark_type`
--

DROP TABLE IF EXISTS `submission_mark_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_mark_type` (
  `mark_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_name` varchar(32) NOT NULL,
  `slug` varchar(16) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `enabled` int(1) DEFAULT '1',
  PRIMARY KEY (`mark_type_id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_mark_type`
--

LOCK TABLES `submission_mark_type` WRITE;
/*!40000 ALTER TABLE `submission_mark_type` DISABLE KEYS */;
INSERT INTO `submission_mark_type` (`mark_type_id`, `hr_name`, `slug`, `comment`, `enabled`) VALUES (1,'Percentage (&#37;)','percent','This should be a whole percentage between 0 and 100.',1),(2,'Letter Grade','letter','Select a letter grade from the list.',1),(3,'Total Mark','raw','This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.',1),(4,'Free Type (legacy support)','free','Should be a letter (A), score (15/20), or percentage (88%).',0),(5,'Rubric','rubric','To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.',1),(6,'None','none','This due work is not marked.',1);
/*!40000 ALTER TABLE `submission_mark_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_notification`
--

DROP TABLE IF EXISTS `submission_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_notification` (
  `box_id` int(10) unsigned NOT NULL,
  `type` enum('upcoming','overdue','returned') NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sent` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`box_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_notification`
--

LOCK TABLES `submission_notification` WRITE;
/*!40000 ALTER TABLE `submission_notification` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_notification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_return`
--

DROP TABLE IF EXISTS `submission_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `box_id` int(11) NOT NULL DEFAULT '0',
  `owner` int(11) NOT NULL DEFAULT '0',
  `comment` text,
  `mark` varchar(64) DEFAULT NULL,
  `norm_mark` decimal(14,11) DEFAULT NULL,
  `filename` varchar(80) DEFAULT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `submitted` datetime DEFAULT NULL,
  `returned` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_return`
--

LOCK TABLES `submission_return` WRITE;
/*!40000 ALTER TABLE `submission_return` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_return` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `superusers`
--

DROP TABLE IF EXISTS `superusers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `superusers` (
  `id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superusers`
--

LOCK TABLES `superusers` WRITE;
/*!40000 ALTER TABLE `superusers` DISABLE KEYS */;
INSERT INTO `superusers` (`id`) VALUES (1);
/*!40000 ALTER TABLE `superusers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `textBox`
--

DROP TABLE IF EXISTS `textBox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `textBox` (
  `textBoxID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) DEFAULT NULL,
  `heading` varchar(100) DEFAULT NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`textBoxID`),
  KEY `categoryID` (`categoryID`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `textBox`
--

LOCK TABLES `textBox` WRITE;
/*!40000 ALTER TABLE `textBox` DISABLE KEYS */;
INSERT INTO `textBox` (`textBoxID`, `categoryID`, `heading`, `body`, `deleted`) VALUES (1,9,'Example','<p>This folder would be used to upload images and other assets that you can then reference in components like TextBoxes used to style up pages like Library pages as an example.</p>','false'),(2,7,'Principals Weekly Message','<div style=\"margin: 0; background: #0565af;\">\r\n<div style=\"padding: 5px 10px 10px 10px;\">\r\n<p style=\"margin: 0; font: bold 24px Arial, Helvetica, sans-serif; color: #fff;\">Principal\'s Message</p>\r\n<p style=\"margin: 1.2em 0 1.2em 0; font: bold 10px Arial, Helvetica, sans-seriff; color: #fff;\">At the heart of our enterprise are our values: compassion, justice, wisdom, learning, harmony, balance, hospitality and stewardship. Our central values of compassion and justice are inspired by the parable of the Good Samaritan in the Gospel (Luke 10: 25-37).March 11th.</p>\r\n<p style=\"0 0 1.2em 0;font: bold 10px Arial, Helvetica, sans-serif; color: #fff;\">Middle School students need to read every night in order to build the stamina, vocabulary and reading rate needed for success in high school. Please make this a routine part of your nightly routine with homework, etc.</p>\r\n<p style=\"margin: 0; padding: 10px 0 10px 0; font: bold 10px Arial, Helvetica, sans-serif; color: #fff600;\">Custom dashboard textbox components like this can be created to display almost any kind of content and multimedia.</p>\r\n<div style=\"clear: both; height: 0;\">&nbsp;</div>\r\n</div>\r\n</div>','false'),(3,7,'Example','<p>You could upload images into this folder and then use those images in the textbox\'s shown here.</p>','false'),(4,7,'Current live shared dashboard component content','<p>If you want to regularly swap the content in a dashboard component out you can add a \"Current Live\" component to each dashboard and then substitute in the content into this component to update across multiple groups. This is in case you don\'t want to lose the previous content you had created.</p>','false'),(5,21,'Welcome to the Schoolbox groups page','<p>Here you can find all of the Free, and Moderated Groups within Schoolbox. Feel free to join any of the free groups available. If you wish to join the moderated groups simply joining will notify the groups administrators and they will then be able to provide you access.&nbsp;</p>\r\n<p>After joining a group you will receive notifications of news and events directly to your dashboard activity feed.</p>','false'),(6,28,'Book of the Month','<p><span>This month we are featuring the latest novel from ...</span></p>\r\n<p><span><img src=\"http://farm2.staticflickr.com/1379/543016844_f5c2346fe2_b.jpg\" border=\"0\" width=\"300\" height=\"265\" /></span></p>','false'),(7,28,'      ','<div style=\"margin: 0; background: transparent url(\'http://farm3.staticflickr.com/2314/2854849909_30e3809b75_b.jpg\') no-repeat 0 0; overflow: hidden; height: 300px;\">\r\n<div style=\"padding: 5px;\">\r\n<p style=\"margin: 50px 0; font: bold 56px Arial, Helvetica, sans-serif; color: #fff; text-shadow: 2px 2px 2px #0066b0; filter: dropshadow(color=#0066b0, offx=2, offy=2);\">School Library</p>\r\n<div style=\"clear: both; height: 0;\">&nbsp;</div>\r\n</div>\r\n</div>','false'),(8,28,'','<table style=\"width: 100%; border: 0;\" cellspacing=\"0\" cellpadding=\"0\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"vertical-align: top; width: 33%; border: 1px solid #ab0008;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #ab0008;\"><span style=\"color: #fff; font-weight: bold;\">CATALOGUE</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"#\">Keyword Search</a><br /><a href=\"#\">Title Search</a><br /><a href=\"#\">Series Search</a><br /><a href=\"#\">New Items</a><br /><a href=\"#\">Author Search</a><br /><a href=\"#\">Subject Search</a><br /><a href=\"#\">Advanced Search</a><br /><a href=\"#\">My Details</a><br /><a href=\"#\">eResources Subscriptions</a><br /><a href=\"http://www.plvn.net.au/\">Public Libraries Victoria</a><br /><a href=\"http://catalogue.portphillip.vic.gov.au/cgi-bin/spydus.exe/MSGTRN/OPAC/BSEARCH\">Port Phillip Lib Catalogue</a><br /><a href=\"http://stonlib.stonnington.vic.gov.au/cgi-bin/spydus.exe/MSGTRN/OPAC/HOME\">Stonnington Lib Catalogue</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n<td style=\"vertical-align: top; width: 33%; border: 1px solid #f48f2c;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #f48f2c;\"><span style=\"color: #fff; font-weight: bold;\">READING</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"#\">New releases<br /></a><a href=\"#\">Reading Resources<br /></a>Request a book<br /><a href=\"#\">Children\'s Book Council of Australia<br /></a><a href=\"http://www.goodreads.com/\">Goodreads<br /></a><a href=\"http://www.goodreadingmagazine.com.au/libraries.cfm?lib=137&amp;src=ZxvLEqFE\">Spine Out<br /></a><a href=\"http://www.eduweb.vic.gov.au/edulibrary/public/commrel/celeb/vicprc/2013PRCbooklist_9to10.pdf\"></a><a href=\"http://www.education.vic.gov.au/prc/\">Premiers\' Reading Challenge 2013</a><a href=\"http://www.eduweb.vic.gov.au/edulibrary/public/commrel/celeb/vicprc/2013PRCbooklist_9to10.pdf\"><br /></a><a href=\"#\">Yrs 7-8 Reading Challenge List</a><a href=\"http://www.eduweb.vic.gov.au/edulibrary/public/commrel/celeb/vicprc/2013PRCbooklist_9to10.pdf\"><br />Yrs 9-10 Reading Challenge List</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n<td style=\"vertical-align: top; width: 34%; border: 1px solid #008000;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #008000;\"><span style=\"color: #fff; font-weight: bold;\">RESEARCH SKILLS</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"http://ergo.slv.vic.gov.au/\">Research, Resources and Essay Writing (ERGO)<br /></a><a href=\"http://big6.com/\">Big 6 Support Site<br /></a><a href=\"#\">How to use a search engine</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<p>&nbsp;</p>\r\n<!-- ROW 2 -->\r\n<table style=\"width: 100%; border: 0;\" cellspacing=\"0\" cellpadding=\"0\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"vertical-align: top; width: 33%; border: 1px solid #f6cb01;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #f6cb01;\"><span style=\"color: #fff; font-weight: bold;\">MEDIA SERVICES</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"http://online.clickview.com.au\">Clickview<br /></a><a href=\"http://www.acmi.net.au/\">Enhance TV<br /></a><a href=\"http://www.acmi.net.au/\">ACMI<br /></a><a href=\"http://www.abc.net.au/iview\">ABC iView</a>&nbsp;<br /><a href=\"http://player.sbs.com.au/programs\">SBS catch-up TV<br /></a><a href=\"http://au.tv.yahoo.com/plus7\">Seven Yahoo Plus</a><br /><a href=\"http://video.ninemsn.com.au/\">Nine MSN<br /></a><a href=\"http://ten.com.au/video.htm\">Ten On Demand</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n<td style=\"vertical-align: top; width: 33%; border: 1px solid #00008b;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #00008b;\"><span style=\"color: #fff; font-weight: bold;\">LITERATURE</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"#\">Literature Guides<br /></a><a href=\"#\">Edgar Allan Poe</a><a href=\"#\"></a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n<td style=\"vertical-align: top; width: 34%; border: 1px solid #e8371f;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #e8371f;\"><span style=\"color: #fff; font-weight: bold;\">STUDY SKILLS</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"#\">Effective Notetaking</a><br /><a href=\"http://au.tv.yahoo.com/plus7\"></a><a href=\"#\">15 Essential Web Tools to help students</a>&nbsp;<br /><a href=\"http://video.ninemsn.com.au/\"></a><a href=\"http://philosophy.hku.hk/think/\">Critical Thinking Web</a><br /><a href=\"http://www.gleancomparisonsearch.org/\">Comparison Search</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<p>&nbsp;</p>\r\n<!-- ROW 3 -->\r\n<table style=\"width: 100%; border: 0;\" cellspacing=\"0\" cellpadding=\"0\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"vertical-align: top; width: 33%; border: 1px solid #6e109f;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #6e109f;\"><span style=\"color: #fff; font-weight: bold;\">PROFESSIONAL LEARNING</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td><a href=\"#\">Library Newsletter</a><br /><a href=\"#\">Teaching Resources</a><a href=\"#\"></a><br />SMILES Project<br />Web 2.0 Tools<br />Articles<br />Future of Libraries<br /><a href=\"https://www.pledgerconsulting.com/Security/login?BackURL=%2Flogin%2Fweblinks-database&amp;u=smgs&amp;p=3200\">Weblinks Database</a><br />Are you studying?</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n<td style=\"vertical-align: top; width: 33%; border: 1px solid #0b7ff0;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #0b7ff0;\"><span style=\"color: #fff; font-weight: bold;\">WRITING</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td>15 Writing tips and hints<br /><a href=\"http://www.mwf.com.au/\">Melbourne Writers Festival</a><br /><a href=\"http://www.mwf.com.au/\">Emerging Writers Festival</a><br /><a href=\"http://www.mwf.com.au/\">Write Club</a><br /><a href=\"http://grammar.ccc.commnet.edu/grammar/\">Grammar and Writing Guide</a><br /><a href=\"http://www.readwritethink.org/classroom-resources/student-interactives/persuasion-30034.html\">Writing Persuasion Template</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n<td style=\"vertical-align: top; width: 34%; border: 1px solid #0dbc74;\" colspan=\"2\" valign=\"top\">\r\n<table style=\"width: 100%; margin: 0;\">\r\n<tbody>\r\n<tr style=\"vertical-align: top;\">\r\n<td style=\"background-color: #0dbc74;\"><span style=\"color: #fff; font-weight: bold;\">SUBJECT RESOURCES</span></td>\r\n</tr>\r\n<tr style=\"vertical-align: top;\">\r\n<td>Subject Specific<br /><a href=\"#\">Pathfinders</a><br /><a href=\"#\">Catalogue</a><br />General Resources<br /><a href=\"#\">eResource Subscriptions</a></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>','true'),(9,32,'Welcome','<p><span><img src=\"http://farm6.staticflickr.com/5018/5482703673_4d1491b9a1.jpg\" border=\"0\" width=\"500\" height=\"281\" style=\"float: right; margin-left: 10px; margin-right: 10px;\" />Students&nbsp;often come and talk about problems at home, friendship difficulties.......</span></p>\r\n<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Nam liber tempor cum soluta nobis eleifend option congue nihil imperdiet doming id quod mazim placerat facer possim assum. Typi non habent claritatem insitam; est usus legentis in iis qui facit eorum claritatem.</p>\r\n<p>Investigationes demonstraverunt lectores legere me lius quod ii legunt saepius. Claritas est etiam processus dynamicus, qui sequitur mutationem consuetudium lectorum. Mirum est notare quam littera gothica, quam nunc putamus parum claram, anteposuerit litterarum formas humanitatis per seacula quarta decima et quinta decima. Eodem modo typi, qui nunc nobis videntur parum clari, fiant sollemnes in futurum.</p>\r\n<p><span><br /></span></p>','true');
/*!40000 ALTER TABLE `textBox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timetable_campus`
--

DROP TABLE IF EXISTS `timetable_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetable_campus` (
  `cycle_id` int(10) unsigned NOT NULL,
  `campus_id` int(11) NOT NULL,
  `start` date NOT NULL,
  `reset` date DEFAULT NULL,
  `finish` date NOT NULL,
  `offset` int(11) DEFAULT NULL,
  PRIMARY KEY (`cycle_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable_campus`
--

LOCK TABLES `timetable_campus` WRITE;
/*!40000 ALTER TABLE `timetable_campus` DISABLE KEYS */;
/*!40000 ALTER TABLE `timetable_campus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timetable_cycle`
--

DROP TABLE IF EXISTS `timetable_cycle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetable_cycle` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(32) NOT NULL,
  `cycle_length` int(11) unsigned DEFAULT NULL,
  `day_length` int(10) unsigned DEFAULT NULL,
  `regular` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable_cycle`
--

LOCK TABLES `timetable_cycle` WRITE;
/*!40000 ALTER TABLE `timetable_cycle` DISABLE KEYS */;
/*!40000 ALTER TABLE `timetable_cycle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timetable_period`
--

DROP TABLE IF EXISTS `timetable_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable_period`
--

LOCK TABLES `timetable_period` WRITE;
/*!40000 ALTER TABLE `timetable_period` DISABLE KEYS */;
/*!40000 ALTER TABLE `timetable_period` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uhomepage`
--

DROP TABLE IF EXISTS `uhomepage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uhomepage` (
  `owner` int(11) NOT NULL DEFAULT '0',
  `education` mediumtext,
  `personal` mediumtext,
  `school` mediumtext,
  PRIMARY KEY (`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uhomepage`
--

LOCK TABLES `uhomepage` WRITE;
/*!40000 ALTER TABLE `uhomepage` DISABLE KEYS */;
/*!40000 ALTER TABLE `uhomepage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL DEFAULT '',
  `password` varchar(80) DEFAULT NULL,
  `group_id` int(4) unsigned NOT NULL DEFAULT '0',
  `title` varchar(10) DEFAULT NULL,
  `firstname` varchar(30) NOT NULL DEFAULT '',
  `lastname` varchar(30) NOT NULL DEFAULT '',
  `email_pass` varchar(40) DEFAULT NULL,
  `synergy_id` varchar(16) DEFAULT NULL,
  `campus` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `year` varchar(3) DEFAULT NULL,
  `house` int(11) DEFAULT NULL,
  `residential_house_id` int(11) DEFAULT NULL,
  `local_email` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `alt_email` varchar(60) DEFAULT NULL,
  `mail_handler` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `hash` varchar(40) DEFAULT NULL,
  `digest` varchar(250) DEFAULT NULL,
  `key` varchar(40) DEFAULT NULL,
  `eportfolio_enabled` int(8) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `synergy` (`synergy_id`),
  KEY `username` (`username`),
  KEY `groupind` (`group_id`),
  FULLTEXT KEY `fulltext` (`username`,`title`,`firstname`,`lastname`,`alt_email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` (`id`, `username`, `password`, `group_id`, `title`, `firstname`, `lastname`, `email_pass`, `synergy_id`, `campus`, `year`, `house`, `residential_house_id`, `local_email`, `alt_email`, `mail_handler`, `hash`, `digest`, `key`, `eportfolio_enabled`) VALUES (1,'alaress','*CF13A85DDC912117E33FD86AC6E1EB94CB1D973F',1,NULL,'Alaress','Admin',NULL,'0',0,NULL,NULL,NULL,0,'support@alaress.com.au',0,'171303c6b137d38a01d26b66600dda58',NULL,NULL,0);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `userAccess`
--

DROP TABLE IF EXISTS `userAccess`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userAccess` (
  `uid` int(11) NOT NULL DEFAULT '0',
  `service` int(11) NOT NULL DEFAULT '0',
  `sid` int(11) NOT NULL DEFAULT '0',
  `type` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`service`,`sid`,`uid`),
  KEY `uid` (`uid`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userAccess`
--

LOCK TABLES `userAccess` WRITE;
/*!40000 ALTER TABLE `userAccess` DISABLE KEYS */;
/*!40000 ALTER TABLE `userAccess` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `userCampus`
--

DROP TABLE IF EXISTS `userCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL DEFAULT '0',
  `campus` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `defaultCampus` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`,`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userCampus`
--

LOCK TABLES `userCampus` WRITE;
/*!40000 ALTER TABLE `userCampus` DISABLE KEYS */;
INSERT INTO `userCampus` (`uid`, `campus`, `defaultCampus`) VALUES (1,1,1);
/*!40000 ALTER TABLE `userCampus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `userGroups`
--

DROP TABLE IF EXISTS `userGroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userGroups` (
  `cid` int(6) NOT NULL DEFAULT '0',
  `type` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userGroups`
--

LOCK TABLES `userGroups` WRITE;
/*!40000 ALTER TABLE `userGroups` DISABLE KEYS */;
/*!40000 ALTER TABLE `userGroups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_assume_log`
--

DROP TABLE IF EXISTS `user_assume_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_assume_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(255) DEFAULT NULL,
  `assumed` varchar(255) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_assume_log`
--

LOCK TABLES `user_assume_log` WRITE;
/*!40000 ALTER TABLE `user_assume_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_assume_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_profile`
--

DROP TABLE IF EXISTS `user_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_profile`
--

LOCK TABLES `user_profile` WRITE;
/*!40000 ALTER TABLE `user_profile` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_relation`
--

DROP TABLE IF EXISTS `user_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_relation` (
  `user_id` int(11) NOT NULL,
  `relation_id` int(11) NOT NULL,
  `relation_type` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`relation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_relation`
--

LOCK TABLES `user_relation` WRITE;
/*!40000 ALTER TABLE `user_relation` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_relation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `welcomeComponent`
--

DROP TABLE IF EXISTS `welcomeComponent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `welcomeComponent` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `filename` varchar(100) DEFAULT '0',
  `function` varchar(100) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `welcomeComponent`
--

LOCK TABLES `welcomeComponent` WRITE;
/*!40000 ALTER TABLE `welcomeComponent` DISABLE KEYS */;
INSERT INTO `welcomeComponent` (`id`, `name`, `filename`, `function`) VALUES (1,'News','news.htm','outputNews'),(2,'Weather','weather.htm','outputWeather'),(3,'Calendar','calendar.htm','outputCalendar'),(4,'Whats New','changedfolder.htm','outputWhatNew'),(5,'My Students','studentSubjects.htm','outputChildSubjects'),(6,'Timetable','','outputTimetable'),(7,'Helpdesk','helpdesk.htm','outputHelpdesk'),(8,'Parent Helpdesk','parentHelpdesk.htm','outputParentHelpdesk'),(9,'Wenona Intranet Login','wenonaIntranet.htm','outputWenonaIntranet'),(10,'RSS Feed','rss.htm','outputRSS'),(11,'Tutorials','tutorial.html','outputTutorial'),(12,'Grimwade Buttons','grimwade.html','outputPrimary'),(13,'Image Slideshow','gallery.htm','outputSlideshow'),(14,'Icons','shortcuts.htm','outputShortcuts');
/*!40000 ALTER TABLE `welcomeComponent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `welcomeLayout`
--

DROP TABLE IF EXISTS `welcomeLayout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `welcomeLayout` (
  `gid` int(6) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `componentID` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`gid`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `welcomeLayout`
--

LOCK TABLES `welcomeLayout` WRITE;
/*!40000 ALTER TABLE `welcomeLayout` DISABLE KEYS */;
INSERT INTO `welcomeLayout` (`gid`, `position`, `componentID`) VALUES (1,1,1),(1,2,3),(1,3,10),(1,4,13),(1,5,2),(1,6,4),(1,7,7),(2,1,1),(2,2,3),(2,3,10),(2,4,13),(2,5,2),(2,6,4),(2,7,7),(3,8,0),(3,1,1),(3,2,3),(3,3,NULL),(3,4,13),(3,5,2),(3,6,4),(3,7,7),(2,8,0),(4,1,1),(4,2,3),(4,3,8),(4,4,13),(4,5,2),(4,6,4),(4,7,5),(4,8,0),(1,0,6),(2,0,6),(3,0,6),(4,0,6),(1,8,0),(1,9,NULL),(2,9,NULL),(3,9,NULL),(4,9,NULL);
/*!40000 ALTER TABLE `welcomeLayout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wiki`
--

DROP TABLE IF EXISTS `wiki`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wiki` (
  `wikiID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) DEFAULT NULL,
  `latestRevision` int(11) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  `locked` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`wikiID`),
  KEY `category_slug` (`categoryID`,`slug`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wiki`
--

LOCK TABLES `wiki` WRITE;
/*!40000 ALTER TABLE `wiki` DISABLE KEYS */;
/*!40000 ALTER TABLE `wiki` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wikiOpens`
--

DROP TABLE IF EXISTS `wikiOpens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wikiOpens` (
  `openID` int(8) NOT NULL AUTO_INCREMENT,
  `wikiID` int(8) DEFAULT NULL,
  `userID` int(8) DEFAULT NULL,
  `lastopened` datetime DEFAULT NULL,
  PRIMARY KEY (`openID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wikiOpens`
--

LOCK TABLES `wikiOpens` WRITE;
/*!40000 ALTER TABLE `wikiOpens` DISABLE KEYS */;
/*!40000 ALTER TABLE `wikiOpens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wikiRevision`
--

DROP TABLE IF EXISTS `wikiRevision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wikiRevision`
--

LOCK TABLES `wikiRevision` WRITE;
/*!40000 ALTER TABLE `wikiRevision` DISABLE KEYS */;
/*!40000 ALTER TABLE `wikiRevision` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-04-16  9:02:32
