-- MySQL dump 10.13  Distrib 5.1.49, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: demo_trinity
-- ------------------------------------------------------
-- Server version	5.1.49-1ubuntu8.1-log

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
INSERT INTO `allowedFileExtension` VALUES (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtx'),(4,'rtf'),(5,'css'),(6,'jpe'),(6,'jpeg'),(6,'jpg'),(7,'pjpg'),(7,'jpg'),(7,'jpeg'),(7,'jpe'),(8,'png'),(9,'bmp'),(10,'pdf'),(11,'docx'),(12,'ppt'),(12,'pps'),(13,'xls'),(13,'csv'),(14,'zip'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(17,'ogg'),(18,'gsp'),(19,'cab'),(19,'swf'),(20,'ins'),(20,'isf'),(21,'msw'),(21,'mswmm'),(21,'re3'),(21,'ogg'),(21,'isf'),(21,'ins'),(22,'wav'),(23,'mp2'),(23,'mp3'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'midi'),(26,'mid'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(29,'wmv'),(30,'avi'),(21,'wmv'),(3,'csv'),(31,'zip'),(11,'doc'),(36,'docx'),(37,'ppsx'),(0,'ppsx'),(38,'pptx'),(39,'xlsx'),(40,'xps'),(41,'avi'),(42,'tif'),(42,'tiff'),(0,'tif'),(0,'tiff'),(43,'pub'),(44,'mdb'),(45,'mp4'),(45,'mpv');
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
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowedFileTypes`
--

LOCK TABLES `allowedFileTypes` WRITE;
/*!40000 ALTER TABLE `allowedFileTypes` DISABLE KEYS */;
INSERT INTO `allowedFileTypes` VALUES (1,'GIF Image','image/gif','image_gif.png','true','true'),(2,'HTML Document','text/html','html.png','true','true'),(3,'Plain Text','text/plain','txt.png','true','true'),(4,'Rich Text','text/richtext','document_alt1.png','true','true'),(5,'CSS','text/css','txt.png','true','true'),(6,'JPEG Image','image/jpeg','image_jpeg.png','true','true'),(7,'Progressing JPEG Image','image/pjpeg','image_jpeg.png','true','true'),(8,'PNG Image','image/png','image_png.png','true','true'),(9,'Bitmap Image','image/bmp','image_bmp.png','true','true'),(10,'PDF Document','application/pdf','pdf_alt.png','true','true'),(11,'Word Document','application/msword','msword_doc.png','true','true'),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','mspowerpoint_ppt.png','true','true'),(13,'Excel Spreadsheet','application/vnd.ms-excel','msexcel_xls.png','true','true'),(14,'ZIP Archive','application/x-zip-compressed','archive_zip.png','false','true'),(15,'Postscript','application/postscript','postscript_alt.png','true','true'),(16,'Photoshop Image','application/x-photoshop','image_psd.png','false','true'),(17,'OGG Audio','application/ogg','sound_ogg.png','true','true'),(18,'Geometer\'s Sketchpad','application/x-gsp','widget.png','false','true'),(19,'Shockwave Flash','application/x-shockwave-flash','flash_swf.png','true','true'),(20,'Inspiration','application/x-Inspiration','umbrellofile.png','false','true'),(21,'Binary Data (Unknown)','application/octet-stream','binary.png','false','true'),(22,'WAV Audio','audio/wav','sound_wav.png','false','true'),(23,'MPEG Audio','audio/mpeg','sound_mp3.png','false','true'),(24,'Real Audio Stream','audio/x-pn-realaudio','sound.png','false','true'),(25,'Real Audio','audio/x-realaudio','sound.png','false','true'),(26,'MIDI Audio','audio/mid','sound.png','false','true'),(27,'MPEG Video','video/mpeg','video_alt.png','false','true'),(28,'Quicktime Video','video/quicktime','quicktime.png','false','true'),(29,'WMV Video','video/x-ms-wmv','video_alt.png','true','true'),(30,'Microsoft Video','video/x-msvideo','video_alt.png','false','true'),(31,'ZIP Archive','application/zip','archive_zip.png','true','true'),(32,'Flash','video/x-flv','flash_swf.png','true','true'),(33,'Windows Media','video/x-ms-asf','video_alt.png','true','true'),(35,'Rich Text Format','text/rtf','document_alt2.png','false','true'),(34,'MPEG 4','video/x-m4v','video_alt.png','true','true'),(36,'Microsoft Office Document 2007','application/vnd.openxmlformats-officedocument.wordprocessingml.document','msword_doc.png','false','true'),(37,'Microsoft Office Slideshow 2007','application/vnd.openxmlformats-officedocument.presentationml.slideshow','mspowerpoint_ppt.png','true','true'),(38,'Microsoft Office Presentation 2007','application/vnd.openxmlformats-officedocument.presentationml.presentation','mspowerpoint_ppt.png','true','true'),(39,'Microsoft Office Spreadsheet 2007','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','msexcel_xls.png','false','true'),(40,'Microsoft XPS','application/vnd.ms-xpsdocument','widget_alt.png','false','true'),(41,'AVI Video','video/avi','video_alt.png','true','true'),(42,'Scanned Image','image/tiff','image_tiff.png','false','true'),(43,'Microsoft Publisher','application/vnd.ms-publisher','document.png','false','true'),(44,'Microsoft Access','application/msaccess','msaccess_mdb.png','false','true'),(45,'MP4 Video','video/mp4','video_alt.png','false','true');
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
  `allow_resit` int(1) NOT NULL DEFAULT '0',
  `availability` enum('assess','survey') DEFAULT 'assess',
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
INSERT INTO `assess_luQuestionType` VALUES (1,'short','Short Answer','shortanswer','false','all','false'),(2,'multi','Multiple Choice','multichoice','true','all','true'),(3,'fillgap','Cloze Exercise','cloze','true','assess','true'),(4,'match','Matching Pairs','matching','true','assess','true'),(5,'radio','Single Answer','radio','true','survey','true'),(6,'ratingAgree','Rating Scale (Agree)',NULL,'false','survey','true'),(7,'ratingLike','Rating Scale (Like)',NULL,'false','survey','true'),(8,'ratingImportant','Rating Scale (Important)',NULL,'false','survey','true'),(9,'ratingSatisfied','Rating Scale (Satisfied)',NULL,'false','survey','true'),(10,'yesno','Yes/No',NULL,'false','survey','true'),(11,'truefalse','True/False',NULL,'false','survey','true');
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
  `name` varchar(255) DEFAULT NULL,
  `availability` enum('assess','survey') DEFAULT NULL,
  PRIMARY KEY (`question_category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_question_category`
--

LOCK TABLES `assess_question_category` WRITE;
/*!40000 ALTER TABLE `assess_question_category` DISABLE KEYS */;
INSERT INTO `assess_question_category` VALUES (1,'Maths','assess'),(2,'English','assess'),(3,'Geography','assess'),(4,'Science','assess'),(5,'History','assess'),(6,'LOTE','assess'),(7,'Elective Selection','survey'),(8,'Teacher Review','survey'),(9,'Medical','survey');
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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (3,'Root Folder',0,0,0,NULL);
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
  PRIMARY KEY (`id`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
  `class_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  PRIMARY KEY (`class_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
  `action` enum('create','modify','delete') NOT NULL DEFAULT 'create',
  `user` int(10) unsigned NOT NULL DEFAULT '0',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cmsLog`
--

LOCK TABLES `cmsLog` WRITE;
/*!40000 ALTER TABLE `cmsLog` DISABLE KEYS */;
INSERT INTO `cmsLog` VALUES (1,'folder',2,'delete',1,'2011-05-04 14:20:53'),(2,'folder',1,'delete',1,'2011-05-04 14:21:16');
/*!40000 ALTER TABLE `cmsLog` ENABLE KEYS */;
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
  `module` enum('dashboard','homepage','eportfolio') DEFAULT 'dashboard',
  `visible` enum('true','false') DEFAULT 'true',
  `multiple` enum('false','true') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_definition`
--

LOCK TABLES `component_definition` WRITE;
/*!40000 ALTER TABLE `component_definition` DISABLE KEYS */;
INSERT INTO `component_definition` VALUES (1,'Timetable','Component_Dashboard_TimetableController',NULL,'dashboard','true','false'),(2,'Greeting','Component_Dashboard_GreetingController',NULL,'dashboard','true','false'),(3,'Calendar','Component_Dashboard_CalendarController',NULL,'dashboard','true','false'),(4,'News','Component_Dashboard_NewsController',NULL,'dashboard','true','false'),(5,'Weather','Component_Dashboard_WeatherController',NULL,'dashboard','true','false'),(6,'Activity','Component_Dashboard_ActivityController',NULL,'dashboard','true','false'),(7,'Child Subject','Component_Dashboard_ChildSubjectController',NULL,'dashboard','true','false'),(8,'Helpdesk','Component_Dashboard_HelpdeskController',NULL,'dashboard','false','false'),(9,'Link','Component_Dashboard_LinkController',NULL,'dashboard','false','false'),(11,'Parent Helpdesk','Component_Dashboard_ParentHelpdeskController',NULL,'dashboard','false','false'),(13,'RSS','Component_Dashboard_RssController',NULL,'dashboard','true','false'),(14,'Shortcut','Component_Dashboard_ShortcutController',NULL,'dashboard','false','false'),(15,'Suggestion','Component_Dashboard_SuggestionController',NULL,'dashboard','false','false'),(16,'Tutorial','Component_Dashboard_TutorialController',NULL,'dashboard','false','false'),(17,'Wenona Intranet','Component_Dashboard_WenonaIntranetController',NULL,'dashboard','false','false'),(18,'Primary','Component_Dashboard_PrimaryController',NULL,'dashboard','false','false'),(19,'Organisation','Component_Dashboard_OrganisationController',NULL,'dashboard','false','false');
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
  `module` varchar(128) NOT NULL,
  `identifier` int(10) unsigned NOT NULL COMMENT 'Foreign ID is used to reference the context of the instance. (Polymorphic)',
  `position` varchar(32) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_instance_dashboard_component1` (`component_definition_id`),
  KEY `fk_dashboard_component_instance_groups1` (`identifier`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_instance`
--

LOCK TABLES `component_instance` WRITE;
/*!40000 ALTER TABLE `component_instance` DISABLE KEYS */;
INSERT INTO `component_instance` VALUES (2,2,'dashboard',1,'top',1),(3,1,'dashboard',1,'top',2),(4,4,'dashboard',1,'left',1),(5,3,'dashboard',1,'left',2),(6,13,'dashboard',1,'left',3),(7,5,'dashboard',1,'right',5),(8,6,'dashboard',1,'right',6),(10,2,'dashboard',2,'top',1),(11,1,'dashboard',2,'top',2),(12,4,'dashboard',2,'left',1),(13,3,'dashboard',2,'left',2),(14,13,'dashboard',2,'left',3),(15,5,'dashboard',2,'right',5),(16,6,'dashboard',2,'right',6),(18,2,'dashboard',3,'top',1),(19,1,'dashboard',3,'top',2),(20,4,'dashboard',3,'left',1),(21,3,'dashboard',3,'left',2),(22,5,'dashboard',3,'right',5),(23,6,'dashboard',3,'right',6),(25,2,'dashboard',4,'top',1),(26,1,'dashboard',4,'top',2),(27,4,'dashboard',4,'left',1),(28,3,'dashboard',4,'left',2),(30,5,'dashboard',4,'right',5),(31,6,'dashboard',4,'right',6);
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
  `options` varchar(100) DEFAULT NULL,
  `type` enum('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB','Colours') DEFAULT NULL,
  `editable` enum('true','false') DEFAULT 'false',
  `hidden` enum('true','false') DEFAULT 'false',
  `required` enum('false','true') DEFAULT 'false',
  PRIMARY KEY (`configID`),
  KEY `configName` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=94 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` VALUES (1,'title','Schoolbox','Title','Title of site displayed at top of browser window','input',NULL,'Site','true','false','true'),(2,'school_name','Alaress Pty Ltd','School Name','Name of school displayed in footer','input',NULL,'Site','true','false','true'),(3,'school_adddress','http://www.alaress.com.au','School Website Address','Address of school website','input',NULL,'Site','true','false','true'),(4,'site_location','core','Site Identifier','Name of site used to identify school in custom code and templates','input',NULL,'Site','false','false','true'),(5,'site_hostname','core','Hostname','Hostname used to access server without the domain','input',NULL,'Site','true','false','true'),(6,'site_domain','portal.alaress-dev.com.au','Domain','Network domain that is used to access server remotely','input',NULL,'Site','true','false','true'),(7,'site_detect','campus','Template Detection','Method which template is detected','select','none:campus:domain','Site','false','false','true'),(8,'login_grace','60','Login Grace Time','Amount of time (secs) between failed logins before brute force lockout','input',NULL,'Security','true','false','true'),(9,'login_allowed','3','Failed Logins Allowed','Amount of logins allowed in login grace time before brute force lockout','input',NULL,'Security','true','false','true'),(10,'track_ip','0','Track Session IPs','Track session IPs, prevents session stealing but can cause problems with proxies','boolean',NULL,'Security','true','false','true'),(11,'session_length','1800','Session Expiry Time','Length of idle time (secs) before a user is logged out','input',NULL,'Security','true','false','true'),(12,'kerb_enable','1','Enable Kerberos SSO','Enable Kerberos SSO authentication.  This enables sessions to be created based on mod_auth_kerb authentication','boolean',NULL,'Security','true','false','true'),(13,'ldap_enable','0','Enable LDAP Authentication','Enable authentication via LDAP server. System will attempt first to bind to LDAP if this fails, it will fall back local authentication','boolean',NULL,'Security','true','false','false'),(14,'ldap_host','','LDAP Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false','false'),(15,'ldap_user','','LDAP Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false','false'),(16,'ldap_pass','','LDAP Password','Password for binding to LDAP server','input',NULL,'Security','true','false','false'),(17,'ldap_base','','LDAP Search Base','Base search path for username lookup','input',NULL,'Security','true','false','false'),(18,'ldap_host_alt','','LDAP Alternate Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false','false'),(19,'ldap_user_alt','','LDAP Alternate Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false','false'),(20,'ldap_base_alt','','LDAP Alternate Search Base','Base search path for username lookup','input',NULL,'Security','true','false','false'),(21,'ldap_username','SAMAccountName','LDAP Username Search Field','Field to search for username in LDAP','input',NULL,'Security','true','false','false'),(22,'template_dir','/var/www/demo_trinity/templates/','Template Location','Location of templates','input',NULL,'Paths','false','false','true'),(23,'file_dir','/var/www/demo_trinity/data/files','File Location','Location of files uploaded to resources are stored','input',NULL,'Paths','false','false','true'),(24,'myfile_dir','/var/www/demo_trinity/data/myFiles','MyFiles Location','Location of personal files uploaded to my files or eportfolio are stored','input',NULL,'Paths','false','false','true'),(25,'submission_dir','/var/www/demo_trinity/data/submissions','Submission Location','Location of student submissions','input',NULL,'Paths','false','false','true'),(26,'returns_dir','/var/www/demo_trinity/data/returns','Returns Location','Location of teacher returned work','input',NULL,'Paths','false','false','true'),(27,'dropbox_dir','/var/www/demo_trinity/data/dropbox','Dropbox Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false','true'),(28,'zip_dir','/var/www/demo_trinity/data/thumbnails','ZIP Temp Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false','true'),(29,'thumbnail_dir','/var/www/schoolbox/www/conf/../../data/thumbnails','Image Cache Location','Location of where thumbnails and resized images are stored after they are generated','input',NULL,'Paths','false','false','true'),(30,'xml_dir','/var/www/demo_trinity/data/cache/xml','XML Cache Location','Location of RSS and XML caching','input',NULL,'Paths','false','false','true'),(31,'thumbnail_size','100','Thumbnail pixel size','Maximum height/width of thumbnails generated for image gallery','input',NULL,'Resources','true','false','true'),(32,'file_upload_count','10','File Upload Limit','The total amount of files that can be uploaded via a single multifile upload page','input',NULL,'Resources','true','false','true'),(33,'max_file_size','10485760','File Size Limit','The maximum size of a single file that can be uploaded. Excepts human formats like 40M and 1GB.','bytes',NULL,'Resources','true','false','true'),(34,'news_edit_other','1','Allow Others to Edit News','Controls whether users with write access to a page can edit each others news posts','boolean',NULL,'Resources','true','false','true'),(35,'group_staff_only','1','Allow Students to be Group Admins','Allow users in student groups to become group admins, beware of the consequences','boolean',NULL,'Resources','true','false','true'),(36,'webdav_enable','0','Enable WebDAV connector in MyFiles','Turns on webDAV in the MyFiles area','boolean',NULL,'Resources','false','false','true'),(37,'search_all_limit','3','Search Summary Item Limit','The amount of items shown for each section on the all tab in the search','boolean',NULL,'Resources','false','false','true'),(38,'search_type_limit','15','Search Items Per Page','The amount of results shown per page in the search results','boolean',NULL,'Resources','false','false','true'),(39,'welcome_image_folder','140','Image Rotation Folder ID','The welcome page image scroller utilises this to find images','input',NULL,'Welcome','true','false','true'),(40,'welcome_rss_url','http://www.abc.net.au/news/indexes/justin/rss.xml','URL for RSS','The URL for the RSS feed displayed on the welcome page','input',NULL,'Welcome','true','false','true'),(41,'welcome_rss_limit','6','RSS Item Limit','The amount of items displayed from the RSS feed on the welcome page','input',NULL,'Welcome','true','false','true'),(42,'weather_enable','1','Enable Weather','Reguarily fetches weather data from Google weather feeds','boolean',NULL,'Welcome','true','false','true'),(43,'weather_location','Melbourne, Victoria','Weather Location','By default display weather at this location','input',NULL,'Welcome','true','false','true'),(44,'name_format','{title} {firstname} {lastname}','Name Output Format','Format of names available fields; {title}, {firstname}, {lastname}','input',NULL,'Localisation','true','false','true'),(45,'calendar_time_format','%l:%i%p','Calendar Time Format','Time output style 24 hour = \'%k:%i\', 12 hour = \'%l:%i%p\'','input',NULL,'Localisation','true','false','true'),(46,'date_long','l F jS Y g:ia','Long Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(47,'date_short','jS M Y g:ia','Short Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(48,'date_database','Y-m-d H:i:s','Database Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(49,'date_notime','jS M Y','Date with no Time Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(50,'external_enable','0','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records','boolean',NULL,'External DB','true','false','false'),(51,'external_type','tass','Database Provider','The name of the database product currently being used','select','synergetic:tass:denbigh:schoolbox','External DB','true','false','false'),(52,'external_db_host','synergy','Database Host','The server hostname where SQL database is located','input',NULL,'External DB','true','false','false'),(53,'external_db_user','intranet','Database User','SQL user with read access to the required tables, see documentation','input',NULL,'External DB','true','false','false'),(54,'external_db_passwd','Qu4LitY','Database Password','SQL user password','input',NULL,'External DB','true','false','false'),(55,'external_db_db','SynergyOne','Database Name','The name of the database on the SQL server','input',NULL,'External DB','true','false','false'),(56,'external_portal','http://synergy.portal.com','External DB Portal URL','Schoolbox can integrate with your existing school database to fetch timetable and student records','input',NULL,'External DB','true','false','false'),(57,'external_sync_data','0','Synchronise Data','Controls if we should syncronise the emails with school database, currently only works for parents','boolean',NULL,'External DB','true','false','false'),(58,'mail_enable','1','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records','boolean','','Email','true','false','true'),(59,'mail_type','roundcube','Mail System','Simple send mail capability, or full imap support with roundcube webmail','select','simple:roundcube','Email','true','false','true'),(60,'mail_host','ssl://mail.alaress.com.au','Mail Server','Location of IMAP mail server for use with roundcube','input','','Email','true','false','true'),(61,'mail_host_port','993','Mail Host Port','Port of mail server, typically 143','input','','Email','true','false','true'),(62,'mail_smtp_host','ssl://mail.alaress.com.au','SMTP Server','Address of server to send email via, this should be your email gateway','input','','Email','true','false','true'),(63,'mail_smtp_port','465','SMTP Port','Port of the SMTP server, typically 25','input','','Email','true','false','true'),(64,'mail_address','alaress.com.au','Email Domain','Email domains with which emails should originate from, if no alt emails are specfied email will be username@domain','input','','Email','true','false','true'),(65,'mail_webmail','','Webmail URL','URL to access OWA or other existing webmail service.  Should be accessible internally and externally','input','','Email','true','false','false'),(66,'mail_from_address','portal@lektec.com.au','Email From Address','Emails that originate from Schoolbox will come from this address','input','','Email','true','false','true'),(67,'mail_from_name','Schoolbox','Email From Name','Emails that originate from Schoolbox will come from this name','input','','Email','true','false','true'),(68,'mail_support_address','support@alaress.com.au','Schoolbox Feedback Address','Requests sent to the Schoolbox feedback form will be sent to this address','input','','Email','true','false','true'),(69,'site_version','12.0.0','Schoolbox Version','Version of Schoolbox currently installed','input',NULL,'Site','false','false','true'),(70,'api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.','input','','Security','true','false','true'),(72,'api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.','input','','Security','true','false','true'),(73,'policy',NULL,'Policy','A key key generated in the PlagScan administration interface','textarea',NULL,'Site','true','false','false'),(74,'protect_teacher_photo','1','Hide Teacher Photo','The username you used when registering for the PlagScan service.','boolean',NULL,'Resources','true','false','true'),(75,'protect_student_photo','1','Hide Student Photo','Tick this box to hide student photos from other students','boolean',NULL,'Resources','true','false','true'),(76,'homepage_colour_head_font','013a63','Heading Text','Colour of the heading text on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(77,'homepage_colour_head_bg','cae9b9','Heading Background','Colour of the background of the headings on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(78,'homepage_colour_font','000000','Body Text','Colour of the body text on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(79,'homepage_colour_bg_primary','ffffff','Primary Background','Primary background colour on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(80,'homepage_colour_bg_secondary','faf9f5','Secondary Background','Secondary background colour homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(81,'homepage_colour_link','1d9ffc','Link','Colour of links on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(82,'homepage_colour_hover','ff5a00','Link Hover','Colour of links when your mouse over them on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(83,'date_timezone','Australia/Victoria','Timezone','Set the local timezone for the server, use the PHP timezones available on the site','select','Australia/Victoria:Australia/NSW:Australia/Queensland:Australia/West:Australia/South','Localisation','true','false','true'),(84,'recaptcha_private_api_key','6LfS0sgSAAAAADh12n8weyLb8irboBWfhseikfCF','reCAPTCHA Private Key','The reCAPTCHA private key','input',NULL,'External DB','false','true','false'),(85,'recaptcha_public_api_key','6LfS0sgSAAAAALQEAZ9Di5rG_RZ7saoLKsgNo6Iq','reCAPTCHA Public Key','The reCAPTCHA public key','input',NULL,'External DB','false','true','false'),(86,'external_web_services_url',NULL,'SOAP Web Services URL','URL of the web services API that can handle SOAP requests','input',NULL,'External DB','true','false','false'),(87,'external_vendor_token_guid',NULL,'Synergetic Vendor Token GUID','The Vendor Token GUID used to access your Synergetic SOAP API','input',NULL,'External DB','true','false','false'),(88,'external_vendor_password',NULL,'Synergetic Vendor Password','The Vendor Password used to access your Synergetic SOAP API','input',NULL,'External DB','true','false','false'),(89,'donvale_students_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/StudDetails.csv','Donvale Students CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(90,'donvale_staff_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/StaffDetails.csv','Donvale Staff CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(91,'donvale_classes_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/ACCClasses.csv','Donvale Classes CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(92,'donvale_classinvolvement_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/ACCClassesStud.csv','Donvale Class Involvement CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(93,'donvale_timetable_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/ACCTimeTables.csv','Donvale Timetable CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false');
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
  `mime_type` varchar(40) DEFAULT NULL,
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
INSERT INTO `eportfolio_comp` VALUES (1,1,'outputLinkList','Links','Add links to your favourite sites and resources',1,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(2,1,'outputTextBox','Text Box','Creates a space where you can enter your own information',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(3,1,'outputBlog','Blog','Write a blog about your experiences',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),(4,1,'outputFileList','Published Files','Files that you have published to your ePortfolio.',1,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:11:\"Description\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(5,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location',3,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(6,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this ePortfolio',3,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(7,1,'outputObjectiveSkill','Skills','A list of your skills for your resume',2,'objectiveSkill.html','','false','false'),(8,1,'outputObjectiveGoal','Goals','A list of your goals for your resume',2,'objectiveGoal.html','','false','false'),(9,1,'outputObjectiveAward','Awards','A list of your awards for your resume',2,'objectiveAward.html','','false','false'),(10,1,'outputObjectiveCoverLetter','Cover Letter','A cover letter for your resume',2,'objectiveCoverLetter.html','','false','false'),(11,1,'outputObjectiveInterest','Interests','A list of interests for your resume',2,'objectiveInterest.html','','false','false'),(12,1,'outputObjectiveEmploymentHistory','Employment History','An employment history for your resume',2,'objectiveEmploymentHistory.html','','false','false'),(13,1,'outputObjectiveEducationHistory','Education History','An education history for your resume',2,'objectiveEducationHistory.html','','false','false'),(14,1,'outputImageScroller','Image Scroller','An image scroller',3,'imageScroller.html','','false','false');
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
INSERT INTO `eportfolio_comp_category` VALUES (1,'General'),(2,'Resume'),(3,'Images');
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
  `right_column_width` float DEFAULT '69.5',
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
  `id` int(3) unsigned NOT NULL DEFAULT '0',
  `message` mediumtext,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `error`
--

LOCK TABLES `error` WRITE;
/*!40000 ALTER TABLE `error` DISABLE KEYS */;
INSERT INTO `error` VALUES (1,'You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.'),(2,'Not enough information was submitted to the form.'),(3,'Please enter either a file or a url, not both.'),(4,'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on Schoolbox.<br><br>Please contact your System Administrator if problems persist.'),(5,'You do not have permission to download this file.'),(6,'You do not have write permission to this category.'),(7,'Mail cannot be sent at this time. Please try again later.'),(8,'You do not have permission to delete this resource.'),(9,'Processing failed for the following reason: %MESSAGE%'),(10,'The size of this file exceeds the allowed limit.'),(11,'%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to Schoolbox.<br>If you would like to be able to add files of this type to Schoolbox please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to Schoolbox.'),(12,'The category that you are trying to delete is not empty. Please delete all its contents, then try again.'),(13,'The category that you are trying to view does not exist.'),(14,'You do not have permission to view this category.'),(15,'The date that you have submitted is invalid. Please check it, then try again.'),(16,'There was a problem validating your account: <br><b>%MESSAGE%</b>'),(17,'The submission box that you are trying to access does not exist or you do not have permission to view it.'),(18,'The subject selected does not exist.'),(19,'You do not have permission to view this page.'),(20,'You do not have permission to create a category in this location.'),(21,'A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%'),(22,'The database is unavailable at this time. Please try again later.'),(23,'You do not have permission to modify this news.'),(24,'You are not enrolled in this subject.'),(25,'You do not have permission to return this work to this student.'),(26,'The work you are trying to return either does not exist or has already been returned.'),(27,'The work you are trying to submit has already been submitted.'),(28,'You do not have permission to post in this category.'),(29,'You do not have permission to modify or delete this post.'),(30,'This user cannot be the group administrator.'),(31,'No user was found that matches your request.'),(32,'The absentee system cannot be accessed on the weekend.'),(33,'That period does not exist today.'),(34,'You do not have permission to modify this event.'),(35,'You do not have permission to add an event of this context.'),(36,'You are attempting to submit work to a submission box that has not yet opened.'),(37,'You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.'),(38,'This is already in the database. Please modify, then try again.'),(39,'No teams exist. You must add teams before trying to add events.'),(40,'Attendances for Grimwade have not yet been implemented. Please wait until further notice.'),(41,'That file does not exist.'),(42,'No email account information was found. Please either use an external mail client to send email, or provide Schoolbox with an email address.'),(43,'The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.'),(44,'You are trying to view or delete work that either does not exist or does not belong to you.'),(45,'Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".'),(46,'You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.'),(101,'The resource you are trying to access does not exist or you do not have permission to view it.'),(102,'A booking already exists during the time you have selected.  Please press back and select a time that is free.'),(103,'The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.'),(104,'You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.'),(47,'You do not have permission to modify this user\'s file.'),(48,'You do not have permission to delete this user\'s file.'),(49,'The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.'),(105,'<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>'),(51,'The section you are trying to add questions to does not exist.'),(106,'You do not have permission to modify or delete this link.'),(107,'You do not have permission to modify this blog.'),(108,'You do not have permission to leave comments on this blog.'),(109,'You do not have permission to publish this file.'),(110,'You do not have permission to modify these notes.'),(111,'There are no blogs in this archive.'),(112,'This assessment no longer exists.'),(113,'You have not answered all questions; please go back and submit them.'),(114,'You have not selected an attempt to review.'),(116,'Unspecified question type.'),(117,'Please choose a group for this assessment.'),(118,'This attempt no longer exists.'),(119,'This question no longer exists.'),(120,'This online assessment has expired.'),(121,'You have already attempted this online assessment and cannot re-sit it.'),(122,'You have run out of time to complete this online assessment and cannot re-sit it.'),(123,'This attempt has not been recorded.'),(115,'This section no longer exists.'),(124,'You are the owner of this test and therefore cannot attempt it.'),(125,'Only the test owner can view this page.'),(126,'Only teachers can create and modify tests.'),(127,'Only the test owner can review this test.'),(128,'This attempt no longer exists.'),(129,'You are not allowed to view this students test.'),(130,'Only teachers can create and modify their test contents.'),(131,'Question could not be saved.'),(132,'Answer could not be saved.'),(133,'Could not load question: unknown type.'),(100,'Your e-portfolio has not been enabled.'),(134,'No import file found.'),(50,'You do not have permission to delete this user\'s file.'),(52,'This link is already shared in your ePortfolio.'),(138,'This username already exists.'),(135,'This is not a valid Assess Question XML.'),(136,'Unable to create or modify folder as class code already exists.'),(137,'The words you entered in to the human checker are incorrect.'),(139,'The link provided is not a correctly formatted URL.'),(140,'You already have that link in your list.');
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
INSERT INTO `file_type` VALUES (1,'Assessment Task'),(2,'Assignment'),(3,'Homework'),(6,'Research Project'),(5,'Essay'),(7,'Class Work'),(8,'Presentation'),(9,'Oral'),(10,'Extension'),(11,'Online Assessment');
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
INSERT INTO `groups` VALUES (1,'Administration','staff','true'),(2,'Teachers','staff','true'),(3,'Students','student','true'),(4,'Parents','parent','true');
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
INSERT INTO `homepageComponentCategory` VALUES (1,'Information'),(2,'Collaboration'),(3,'Learning'),(4,'Images'),(5,'Resources'),(6,'Feeds');
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepageTemplate`
--

LOCK TABLES `homepageTemplate` WRITE;
/*!40000 ALTER TABLE `homepageTemplate` DISABLE KEYS */;
INSERT INTO `homepageTemplate` VALUES (1,'Class',1),(2,'Collab Group',1),(4,'Faculty',1),(3,'Extra-Curricular',1),(5,'Committee',1),(6,'House',1),(7,'Blog',1);
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
INSERT INTO `homepageTemplateLayout` VALUES (1,2,2,3),(1,3,1,3),(1,8,1,1),(1,9,2,5),(1,10,2,6),(1,25,2,2),(1,28,1,2),(1,30,2,1),(1,35,2,4),(2,3,1,3),(2,4,2,1),(2,5,2,3),(2,8,1,1),(2,10,2,5),(2,21,2,2),(2,34,1,2),(2,35,2,4),(3,2,2,5),(3,3,1,3),(3,4,2,2),(3,5,2,3),(3,8,1,2),(3,10,2,6),(3,21,2,1),(3,23,1,1),(3,33,2,4),(3,35,1,4),(4,2,2,3),(4,3,1,3),(4,8,1,2),(4,10,2,4),(4,13,2,1),(4,15,1,1),(4,20,2,2),(5,3,1,3),(5,4,2,2),(5,5,2,3),(5,8,1,1),(5,10,2,5),(5,21,2,2),(5,34,1,2),(5,35,2,4),(6,3,1,3),(6,4,2,1),(6,5,2,3),(6,8,1,1),(6,10,2,5),(6,21,2,2),(6,34,1,2),(6,35,2,4),(7,2,2,2),(7,10,2,3),(7,33,2,1),(7,39,1,1);
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
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_comp`
--

LOCK TABLES `homepage_comp` WRITE;
/*!40000 ALTER TABLE `homepage_comp` DISABLE KEYS */;
INSERT INTO `homepage_comp` VALUES (8,1,'outputNews','News','Provides a text area for you to post information, news, blogs and introductions. ',1,'news_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(2,1,'outputLinkList','Links','Provide links to interesting, related and helpful resources on Schoolbox or the WWW.',5,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(3,1,'outputFileList','Files','List general files that have been uploaded to your homepage. The large version shows both titles and comments.',5,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}','false','false'),(4,2,'outputMembers','Group Members','Show the group members associated with your homepage.  This also provides an email all group members link.',2,'members_s.htm','','false','false'),(5,2,'outputForumList','Forum','Show recent discussion topics going on in your homepages forum.',2,'forum_s.htm','a:2:{s:4:\"sort\";s:4:\"Date\";s:4:\"size\";i:0;}','false','false'),(6,2,'outputLinkList','Small Links','Provide links to interesting, related and helpful resources on Schoolbox or the WWW.',5,'links_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(7,2,'outputFileList','Small Files','List general files that have been uploaded to your homepage. The small version only shows file titles.',5,'files_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(9,2,'initSubject','Class Management','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.',3,'class_manage.htm','','false','false'),(10,2,'folderManage','Folder Management','Provides links to browse the files, manage group membership and view homepage statistics.',5,'folder_manage.htm','','false','false'),(11,2,'outputAssignList','Small Assignment','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.',3,'assign_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(12,2,'outputHomeworkList','Small Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.',3,'homework_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(13,2,'outputManagers','Group Managers','Displays a list of group members with write access to the homepage.  Useful in larger groups to show manages of the group.',2,'staff_s.htm','','false','false'),(14,1,'outputImage','Image Simple','Just displays an image on your page.',4,'image_s.htm','','true','false'),(15,1,'outputImage','Image','Completely configurable image display, select the image, control the size and link to another location',4,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(16,1,'outputAssignList','Assignments','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.',3,'assign_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(17,1,'outputHomeworkList','Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.',3,'homework_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(20,2,'outputFolderList','Folders','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.',5,'folders_s.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(21,1,'outputEventList','Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(22,2,'outputEventList','Small Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(23,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ',4,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(24,1,'outputRandImage','Random Image','Display a random image selected from the images on this homepage.',4,'image_s.htm','a:1:{s:5:\"pixel\";i:400;}','false','false'),(25,2,'outputDropBox','File Drop Box','Provides an area for teachers to receive files from students without the need for a submission box and due date',3,'dropbox.htm','','false','false'),(28,1,'outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.',3,'duework_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(27,2,'outputRSS','Small RSS Feed','Display external RSS site headlines and brief description. The RSS feed can be from any site that provides a feed.',6,'rss_s.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','true','false'),(26,1,'outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.',6,'rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','false','true'),(30,2,'outputTeachers','Class Teachers','Show a list of teachers responsible for this subject if it has a synergetic subject code.',3,'teacher_s.htm','','false','false'),(29,2,'outputDueWorkList','Small Due Work','Shows a list of all due work and its due date.',3,'duework_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(31,1,'outputCalendarList','Campus Calendar','Show a calendar for the entire campus on your homepage.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(32,0,'outputDuties','Duties',NULL,1,'duties.htm','','true','false'),(33,2,'outputPoll','Poll','Create a poll to determine group members opinion on a given subject.',2,'poll.htm','a:1:{s:6:\"pollID\";i:0;}','false','true'),(34,1,'outputWiki','Wiki','Provides an area for group members to work together on joint documents.',2,'wiki.htm','a:3:{s:4:\"sort\";s:7:\"Heading\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(35,1,'outputFeed','Homepage Feed','Provides a running commentary on recent changes to the homepage',6,'feed.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(36,1,'outputTextBox','Text Box','Use this space to write introductory message or information relating to the content that is displayed',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(37,1,'outputAudio','Audio','Lists audio files and allows them to be played.',5,'audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(38,1,'outputPodcast','Podcast','Provides an embedded audio player to enable the playback of audio from a podcast feed.',6,'podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false','true'),(39,1,'outputBlog','Blog','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),(40,2,'outputTranslate','Language Translator','Translates text and webpages into another language',3,'translate.html','','false','false'),(41,1,'outputScroller','Image Carousel','Automatically fades through all the images uploaded to this homepage.',4,'scroller.html','a:1:{s:5:\"pixel\";i:400;}','false','false'),(42,1,'outputAssess','Online Assessment','Online tests and quizzes.',3,'assess.html','','false','false'),(43,0,'outputChat','Chat','Allows users who are viewing the same homepage to chat with each other.',2,'chat.html','a:2:{s:5:\"state\";s:3:\"off\";s:17:\"teacher_timestamp\";s:0:\"\";}','false','false'),(44,1,'outputSurvey','Surveys','Create surveys for your homepage visitors with various question types and graphical summaries',3,'survey.html','','false','false');
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
  `right_column_width` float DEFAULT '69.5',
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
  PRIMARY KEY (`user`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_log`
--

LOCK TABLES `homepage_log` WRITE;
/*!40000 ALTER TABLE `homepage_log` DISABLE KEYS */;
INSERT INTO `homepage_log` VALUES ('2011-05-05 00:21:12',1,1,1),('2011-05-05 00:20:48',1,2,0),('2011-05-05 00:39:34',1,3,3);
/*!40000 ALTER TABLE `homepage_log` ENABLE KEYS */;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_log`
--

LOCK TABLES `login_log` WRITE;
/*!40000 ALTER TABLE `login_log` DISABLE KEYS */;
INSERT INTO `login_log` VALUES (1,'jleckie','2011-05-05 00:04:51','127.0.0.1',1,NULL),(2,'jleckie','2011-05-05 00:05:34','127.0.0.1',1,NULL),(3,'alaress','2011-05-05 00:50:32','127.0.0.1',1,NULL),(4,'alaress','2011-11-24 10:05:02','192.168.1.155',0,NULL),(5,'alaress','2011-11-24 10:05:05','192.168.1.155',1,NULL);
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
  `timetable_period_count` int(11) DEFAULT NULL,
  `timetable_day_count` int(11) DEFAULT NULL,
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
INSERT INTO `luCampus` VALUES ('Main',NULL,NULL,1),('Other',NULL,NULL,2);
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
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luEventType`
--

LOCK TABLES `luEventType` WRITE;
/*!40000 ALTER TABLE `luEventType` DISABLE KEYS */;
INSERT INTO `luEventType` VALUES (1,'Event','cee6ea'),(2,'Excursion','f8deb8'),(3,'Report','d4efc4'),(4,'Exam','F2CED6'),(5,'Meeting','d2cef2'),(6,'Online Assessment','fff3b2');
/*!40000 ALTER TABLE `luEventType` ENABLE KEYS */;
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
INSERT INTO `luServices` VALUES (1,'Homepage'),(2,'Calendar'),(3,'News'),(4,'Tutorial'),(5,'CMS');
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
INSERT INTO `luTitle` VALUES (2,'Mr'),(3,'Mrs'),(4,'Ms'),(9,'Prof'),(8,'Hon Dr'),(7,'Hon'),(6,'Dr'),(5,'Miss'),(10,'A/Prof'),(1,NULL);
/*!40000 ALTER TABLE `luTitle` ENABLE KEYS */;
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
INSERT INTO `newsCampus` VALUES (1,1);
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
INSERT INTO `newsGroup` VALUES (1,1);
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
INSERT INTO `newsTopic` VALUES (1,'General'),(2,'Music'),(3,'Sport'),(4,'Academic');
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
  `item_datetime_published` datetime NOT NULL,
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news_items`
--

LOCK TABLES `news_items` WRITE;
/*!40000 ALTER TABLE `news_items` DISABLE KEYS */;
INSERT INTO `news_items` VALUES (1,1,'Welcome to Schoolbox!','2011-11-24 10:25:42','2011-11-24 00:00:00','2031-12-01 00:00:00','2011-11-24 10:26:25',0,'<p>Welcome to Schoolbox, our Fantastic Community Base Learning Management System!</p>\r\n<p>&nbsp;</p>\r\n<p>You will find as the Administrator Most of your tools are located <a href=\"/adminv2/home\">here </a></p>\r\n<p>The way you can get to this normally is via the Side menu on the left of the screen, simply go to Home, then Click on Admin!</p>\r\n<p>This item is only visible to Super users.</p>\r\n<p>&nbsp;</p>\r\n<p>To Delete this post, Click on the modify icon in the top right corner of this post and then the delete button on the modify page.</p>',0,1,0,NULL);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poll`
--

LOCK TABLES `poll` WRITE;
/*!40000 ALTER TABLE `poll` DISABLE KEYS */;
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
) ENGINE=MyISAM AUTO_INCREMENT=232 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenu`
--

LOCK TABLES `sideMenu` WRITE;
/*!40000 ALTER TABLE `sideMenu` DISABLE KEYS */;
INSERT INTO `sideMenu` VALUES (1,0,'Home','/home.php','',0),(10,1,'News','/news','',6),(96,10,'View News','/news','',1),(97,10,'Add News','/news/modify','',0),(14,1,'Teacher Resources','/resources','',1),(231,14,'Create Submission','/submission/createSubmission/new','',0),(230,1,'Feedback','/feedback.php','',11),(103,14,'Upload File to Multiple Folders','/cms/file/multicat','',0),(105,14,'Add Link to Multiple Folders','/cms/link/multicat','',1),(229,1,'Change Password','/account/modify','',9),(207,0,'Logout','/logout.php','',8),(208,1,'Admin','/adminv2/home','',2),(213,1,'Due Work','/submission/dueWork','',4),(225,224,'Uncompleted Attendance','/modules/tutorial/report/attendance.php','',0),(226,224,'Hours Performed','/modules/tutorial/report/hours.php','',1),(227,224,'Student Attendance','/modules/tutorial/report/student.php','',2),(228,0,'Manage Groups','/group/join','',7);
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
INSERT INTO `sideMenuCampus` VALUES (1,1),(2,1),(3,1),(7,1),(8,1),(9,1),(10,1),(13,1),(14,1),(15,1),(93,1),(94,1),(96,1),(97,1),(103,1),(105,1),(207,1),(213,1),(214,1),(220,1),(221,1),(222,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(220,2),(221,2),(222,2),(224,2),(225,2),(226,2),(227,2),(228,2),(229,2),(230,2);
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
INSERT INTO `sideMenuGroup` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(2,3),(2,4),(2,5),(3,1),(3,2),(3,3),(3,5),(4,1),(4,2),(4,3),(4,4),(4,5),(7,1),(7,2),(7,3),(7,4),(7,5),(8,1),(8,2),(8,3),(8,5),(9,1),(9,2),(9,3),(9,4),(9,5),(10,1),(10,2),(10,3),(10,4),(10,5),(13,1),(13,2),(13,3),(13,4),(13,5),(14,1),(14,2),(14,3),(14,5),(15,1),(15,2),(93,1),(93,2),(93,3),(93,4),(93,5),(94,1),(94,2),(94,3),(94,4),(94,5),(96,1),(96,2),(96,3),(96,4),(96,5),(97,1),(97,2),(103,1),(103,2),(105,1),(105,2),(207,1),(207,2),(207,3),(207,4),(207,5),(213,3),(213,4),(213,5),(214,1),(214,2),(214,3),(214,5),(220,1),(220,2),(220,3),(220,4),(220,5),(221,1),(221,2),(222,1),(222,2),(224,1),(225,1),(226,1),(227,1),(228,1),(228,2),(228,3),(228,4),(228,5),(229,1),(229,2),(229,3),(229,4),(229,5),(230,1),(230,2),(230,3),(230,4),(230,5),(231,1),(231,2);
/*!40000 ALTER TABLE `sideMenuGroup` ENABLE KEYS */;
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
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
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
  `datetime_published` datetime DEFAULT NULL,
  `datetime_expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
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
  `accept_submission` tinyint(1) NOT NULL DEFAULT '1',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `name` varchar(150) DEFAULT NULL,
  `description` varchar(250) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
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
-- Table structure for table `submission_return`
--

DROP TABLE IF EXISTS `submission_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `box_id` int(11) NOT NULL DEFAULT '0',
  `owner` int(11) NOT NULL DEFAULT '0',
  `comment` varchar(255) DEFAULT NULL,
  `mark` varchar(15) DEFAULT NULL,
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
INSERT INTO `superusers` VALUES (1);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `textBox`
--

LOCK TABLES `textBox` WRITE;
/*!40000 ALTER TABLE `textBox` DISABLE KEYS */;
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
  PRIMARY KEY (`cycle_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable_campus`
--

LOCK TABLES `timetable_campus` WRITE;
/*!40000 ALTER TABLE `timetable_campus` DISABLE KEYS */;
/*!40000 ALTER TABLE `timetable_campus` ENABLE KEYS */;
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
  `synergy_id` int(6) unsigned DEFAULT NULL,
  `campus` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `year` varchar(3) DEFAULT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'alaress','*A09E580BE049CBB0F83F646FB629AC061E1C68D2',1,NULL,'Alaress','Admin',NULL,0,0,NULL,NULL,0,'support@alaress.com.au',0,NULL,NULL,0);
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
INSERT INTO `userCampus` VALUES (1,1,1);
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
INSERT INTO `welcomeComponent` VALUES (1,'News','news.htm','outputNews'),(2,'Weather','weather.htm','outputWeather'),(3,'Calendar','calendar.htm','outputCalendar'),(4,'Whats New','changedfolder.htm','outputWhatNew'),(5,'My Students','studentSubjects.htm','outputChildSubjects'),(6,'Timetable','','outputTimetable'),(7,'Helpdesk','helpdesk.htm','outputHelpdesk'),(8,'Parent Helpdesk','parentHelpdesk.htm','outputParentHelpdesk'),(9,'Wenona Intranet Login','wenonaIntranet.htm','outputWenonaIntranet'),(10,'RSS Feed','rss.htm','outputRSS'),(11,'Tutorials','tutorial.html','outputTutorial'),(12,'Grimwade Buttons','grimwade.html','outputPrimary'),(13,'Image Slideshow','gallery.htm','outputSlideshow'),(14,'Icons','shortcuts.htm','outputShortcuts');
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
INSERT INTO `welcomeLayout` VALUES (1,1,1),(1,2,3),(1,3,10),(1,4,13),(1,5,2),(1,6,4),(1,7,7),(2,1,1),(2,2,3),(2,3,10),(2,4,13),(2,5,2),(2,6,4),(2,7,7),(3,8,0),(3,1,1),(3,2,3),(3,3,NULL),(3,4,13),(3,5,2),(3,6,4),(3,7,7),(2,8,0),(4,1,1),(4,2,3),(4,3,8),(4,4,13),(4,5,2),(4,6,4),(4,7,5),(4,8,0),(1,0,6),(2,0,6),(3,0,6),(4,0,6),(1,8,0),(1,9,NULL),(2,9,NULL),(3,9,NULL),(4,9,NULL);
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

-- Dump completed on 2012-06-25 13:17:50
-- MySQL dump 10.13  Distrib 5.1.49, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: demo_trinity
-- ------------------------------------------------------
-- Server version	5.1.49-1ubuntu8.1-log

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
INSERT INTO `allowedFileExtension` VALUES (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtx'),(4,'rtf'),(5,'css'),(6,'jpe'),(6,'jpeg'),(6,'jpg'),(7,'pjpg'),(7,'jpg'),(7,'jpeg'),(7,'jpe'),(8,'png'),(9,'bmp'),(10,'pdf'),(11,'docx'),(12,'ppt'),(12,'pps'),(13,'xls'),(13,'csv'),(14,'zip'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(17,'ogg'),(18,'gsp'),(19,'cab'),(19,'swf'),(20,'ins'),(20,'isf'),(21,'msw'),(21,'mswmm'),(21,'re3'),(21,'ogg'),(21,'isf'),(21,'ins'),(22,'wav'),(23,'mp2'),(23,'mp3'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'midi'),(26,'mid'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(29,'wmv'),(30,'avi'),(21,'wmv'),(3,'csv'),(31,'zip'),(11,'doc'),(36,'docx'),(37,'ppsx'),(0,'ppsx'),(38,'pptx'),(39,'xlsx'),(40,'xps'),(41,'avi'),(42,'tif'),(42,'tiff'),(0,'tif'),(0,'tiff'),(43,'pub'),(44,'mdb'),(45,'mp4'),(45,'mpv');
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
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowedFileTypes`
--

LOCK TABLES `allowedFileTypes` WRITE;
/*!40000 ALTER TABLE `allowedFileTypes` DISABLE KEYS */;
INSERT INTO `allowedFileTypes` VALUES (1,'GIF Image','image/gif','image_gif.png','true','true'),(2,'HTML Document','text/html','html.png','true','true'),(3,'Plain Text','text/plain','txt.png','true','true'),(4,'Rich Text','text/richtext','document_alt1.png','true','true'),(5,'CSS','text/css','txt.png','true','true'),(6,'JPEG Image','image/jpeg','image_jpeg.png','true','true'),(7,'Progressing JPEG Image','image/pjpeg','image_jpeg.png','true','true'),(8,'PNG Image','image/png','image_png.png','true','true'),(9,'Bitmap Image','image/bmp','image_bmp.png','true','true'),(10,'PDF Document','application/pdf','pdf_alt.png','true','true'),(11,'Word Document','application/msword','msword_doc.png','true','true'),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','mspowerpoint_ppt.png','true','true'),(13,'Excel Spreadsheet','application/vnd.ms-excel','msexcel_xls.png','true','true'),(14,'ZIP Archive','application/x-zip-compressed','archive_zip.png','false','true'),(15,'Postscript','application/postscript','postscript_alt.png','true','true'),(16,'Photoshop Image','application/x-photoshop','image_psd.png','false','true'),(17,'OGG Audio','application/ogg','sound_ogg.png','true','true'),(18,'Geometer\'s Sketchpad','application/x-gsp','widget.png','false','true'),(19,'Shockwave Flash','application/x-shockwave-flash','flash_swf.png','true','true'),(20,'Inspiration','application/x-Inspiration','umbrellofile.png','false','true'),(21,'Binary Data (Unknown)','application/octet-stream','binary.png','false','true'),(22,'WAV Audio','audio/wav','sound_wav.png','false','true'),(23,'MPEG Audio','audio/mpeg','sound_mp3.png','false','true'),(24,'Real Audio Stream','audio/x-pn-realaudio','sound.png','false','true'),(25,'Real Audio','audio/x-realaudio','sound.png','false','true'),(26,'MIDI Audio','audio/mid','sound.png','false','true'),(27,'MPEG Video','video/mpeg','video_alt.png','false','true'),(28,'Quicktime Video','video/quicktime','quicktime.png','false','true'),(29,'WMV Video','video/x-ms-wmv','video_alt.png','true','true'),(30,'Microsoft Video','video/x-msvideo','video_alt.png','false','true'),(31,'ZIP Archive','application/zip','archive_zip.png','true','true'),(32,'Flash','video/x-flv','flash_swf.png','true','true'),(33,'Windows Media','video/x-ms-asf','video_alt.png','true','true'),(35,'Rich Text Format','text/rtf','document_alt2.png','false','true'),(34,'MPEG 4','video/x-m4v','video_alt.png','true','true'),(36,'Microsoft Office Document 2007','application/vnd.openxmlformats-officedocument.wordprocessingml.document','msword_doc.png','false','true'),(37,'Microsoft Office Slideshow 2007','application/vnd.openxmlformats-officedocument.presentationml.slideshow','mspowerpoint_ppt.png','true','true'),(38,'Microsoft Office Presentation 2007','application/vnd.openxmlformats-officedocument.presentationml.presentation','mspowerpoint_ppt.png','true','true'),(39,'Microsoft Office Spreadsheet 2007','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','msexcel_xls.png','false','true'),(40,'Microsoft XPS','application/vnd.ms-xpsdocument','widget_alt.png','false','true'),(41,'AVI Video','video/avi','video_alt.png','true','true'),(42,'Scanned Image','image/tiff','image_tiff.png','false','true'),(43,'Microsoft Publisher','application/vnd.ms-publisher','document.png','false','true'),(44,'Microsoft Access','application/msaccess','msaccess_mdb.png','false','true'),(45,'MP4 Video','video/mp4','video_alt.png','false','true');
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
  `allow_resit` int(1) NOT NULL DEFAULT '0',
  `availability` enum('assess','survey') DEFAULT 'assess',
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
INSERT INTO `assess_luQuestionType` VALUES (1,'Short','short','Short Answer','shortanswer','false','all','false'),(2,'Multi','multi','Multiple Choice','multichoice','true','all','true'),(3,'Fillgap','fillgap','Cloze Exercise','cloze','true','assess','true'),(4,'Match','match','Matching Pairs','matching','true','assess','true'),(5,'Radio','radio','Single Answer','radio','true','survey','true'),(6,'Rating_Agree','ratingAgree','Rating Scale (Agree)',NULL,'false','survey','true'),(7,'Rating_Like','ratingLike','Rating Scale (Like)',NULL,'false','survey','true'),(8,'Rating_Important','ratingImportant','Rating Scale (Important)',NULL,'false','survey','true'),(9,'Rating_Satisfied','ratingSatisfied','Rating Scale (Satisfied)',NULL,'false','survey','true'),(10,'YesNo','yesno','Yes/No',NULL,'false','survey','true'),(11,'TrueFalse','truefalse','True/False',NULL,'false','survey','true');
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
INSERT INTO `assess_question_category` VALUES (1,0,'Maths','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(2,0,'English','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(3,0,'Geography','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(4,0,'Science','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(5,0,'History','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(6,0,'LOTE','assess','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(7,0,'Elective Selection','survey','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(8,0,'Teacher Review','survey','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL),(9,0,'Medical','survey','2012-06-25 14:13:13','2012-06-25 14:13:13',NULL);
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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (3,'Root Folder',0,0,0,NULL);
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
  `action` enum('create','modify','delete') NOT NULL DEFAULT 'create',
  `user` int(10) unsigned NOT NULL DEFAULT '0',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cmsLog`
--

LOCK TABLES `cmsLog` WRITE;
/*!40000 ALTER TABLE `cmsLog` DISABLE KEYS */;
INSERT INTO `cmsLog` VALUES (1,'folder',2,'delete',1,'2011-05-04 14:20:53'),(2,'folder',1,'delete',1,'2011-05-04 14:21:16');
/*!40000 ALTER TABLE `cmsLog` ENABLE KEYS */;
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
  `module` enum('dashboard','homepage','eportfolio') DEFAULT 'dashboard',
  `visible` enum('true','false') DEFAULT 'true',
  `multiple` enum('false','true') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_definition`
--

LOCK TABLES `component_definition` WRITE;
/*!40000 ALTER TABLE `component_definition` DISABLE KEYS */;
INSERT INTO `component_definition` VALUES (1,'Timetable','Component_Dashboard_TimetableController',NULL,'dashboard','true','false'),(2,'Greeting','Component_Dashboard_GreetingController',NULL,'dashboard','true','false'),(3,'Calendar','Component_Dashboard_CalendarController',NULL,'dashboard','true','false'),(4,'News','Component_Dashboard_NewsController',NULL,'dashboard','true','false'),(5,'Weather','Component_Dashboard_WeatherController',NULL,'dashboard','true','false'),(6,'Activity','Component_Dashboard_ActivityController',NULL,'dashboard','true','false'),(7,'Child Subject','Component_Dashboard_ChildSubjectController',NULL,'dashboard','true','false'),(8,'Helpdesk','Component_Dashboard_HelpdeskController',NULL,'dashboard','false','false'),(9,'Link','Component_Dashboard_LinkController',NULL,'dashboard','false','false'),(11,'Parent Helpdesk','Component_Dashboard_ParentHelpdeskController',NULL,'dashboard','false','false'),(13,'RSS','Component_Dashboard_RssController',NULL,'dashboard','true','false'),(14,'Shortcut','Component_Dashboard_ShortcutController',NULL,'dashboard','false','false'),(15,'Suggestion','Component_Dashboard_SuggestionController',NULL,'dashboard','false','false'),(16,'Tutorial','Component_Dashboard_TutorialController',NULL,'dashboard','false','false'),(17,'Wenona Intranet','Component_Dashboard_WenonaIntranetController',NULL,'dashboard','false','false'),(18,'Primary','Component_Dashboard_PrimaryController',NULL,'dashboard','false','false'),(19,'Organisation','Component_Dashboard_OrganisationController',NULL,'dashboard','false','false'),(20,'Upcoming Due Work','Component_Dashboard_UpcomingWorkController',NULL,'dashboard','true','false'),(21,'Recent Submissions','Component_Dashboard_RecentSubmissionsController',NULL,'dashboard','true','false'),(22,'Homepage Activity','Component_Dashboard_HomepageNewsController',NULL,'dashboard','true','false');
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
  `module` varchar(128) NOT NULL,
  `identifier` int(10) unsigned NOT NULL COMMENT 'Foreign ID is used to reference the context of the instance. (Polymorphic)',
  `position` varchar(32) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_instance_dashboard_component1` (`component_definition_id`),
  KEY `fk_dashboard_component_instance_groups1` (`identifier`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_instance`
--

LOCK TABLES `component_instance` WRITE;
/*!40000 ALTER TABLE `component_instance` DISABLE KEYS */;
INSERT INTO `component_instance` VALUES (2,2,'dashboard',1,'top',1),(3,1,'dashboard',1,'top',2),(4,4,'dashboard',1,'left',1),(5,3,'dashboard',1,'left',2),(6,13,'dashboard',1,'left',3),(7,5,'dashboard',1,'right',5),(8,6,'dashboard',1,'right',6),(10,2,'dashboard',2,'top',1),(11,1,'dashboard',2,'top',2),(12,4,'dashboard',2,'left',1),(13,3,'dashboard',2,'left',2),(14,13,'dashboard',2,'left',3),(15,5,'dashboard',2,'right',5),(16,6,'dashboard',2,'right',6),(18,2,'dashboard',3,'top',1),(19,1,'dashboard',3,'top',2),(20,4,'dashboard',3,'left',1),(21,3,'dashboard',3,'left',2),(22,5,'dashboard',3,'right',5),(23,6,'dashboard',3,'right',6),(25,2,'dashboard',4,'top',1),(26,1,'dashboard',4,'top',2),(27,4,'dashboard',4,'left',1),(28,3,'dashboard',4,'left',2),(30,5,'dashboard',4,'right',5),(31,6,'dashboard',4,'right',6);
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
  `options` varchar(100) DEFAULT NULL,
  `type` enum('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB','Colours') DEFAULT NULL,
  `editable` enum('true','false') DEFAULT 'false',
  `hidden` enum('true','false') DEFAULT 'false',
  `required` enum('false','true') DEFAULT 'false',
  PRIMARY KEY (`configID`),
  KEY `configName` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=103 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` VALUES (1,'title','Schoolbox','Title','Title of site displayed at top of browser window','input',NULL,'Site','true','false','true'),(2,'school_name','Alaress Pty Ltd','School Name','Name of school displayed in footer','input',NULL,'Site','true','false','true'),(3,'school_address','http://www.alaress.com.au','School Website Address','Address of school website','input',NULL,'Site','true','false','true'),(4,'site_location','core','Site Identifier','Name of site used to identify school in custom code and templates','input',NULL,'Site','false','false','true'),(5,'site_hostname','core','Hostname','Hostname used to access server without the domain','input',NULL,'Site','true','false','true'),(6,'site_domain','portal.alaress-dev.com.au','Domain','Network domain that is used to access server remotely','input',NULL,'Site','true','false','true'),(7,'site_detect','campus','Template Detection','Method which template is detected','select','none:campus:domain','Site','false','false','true'),(8,'login_grace','60','Login Grace Time','Amount of time (secs) between failed logins before brute force lockout','input',NULL,'Security','true','false','true'),(9,'login_allowed','3','Failed Logins Allowed','Amount of logins allowed in login grace time before brute force lockout','input',NULL,'Security','true','false','true'),(10,'track_ip','0','Track Session IPs','Track session IPs, prevents session stealing but can cause problems with proxies','boolean',NULL,'Security','true','false','true'),(11,'session_length','1800','Session Expiry Time','Length of idle time (secs) before a user is logged out','input',NULL,'Security','true','false','true'),(12,'kerb_enable','1','Enable Kerberos SSO','Enable Kerberos SSO authentication.  This enables sessions to be created based on mod_auth_kerb authentication','boolean',NULL,'Security','true','false','true'),(13,'ldap_enable','0','Enable LDAP Authentication','Enable authentication via LDAP server. System will attempt first to bind to LDAP if this fails, it will fall back local authentication','boolean',NULL,'Security','true','false','false'),(14,'ldap_host','','LDAP Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false','false'),(15,'ldap_user','','LDAP Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false','false'),(16,'ldap_pass','','LDAP Password','Password for binding to LDAP server','input',NULL,'Security','true','false','false'),(17,'ldap_base','','LDAP Search Base','Base search path for username lookup','input',NULL,'Security','true','false','false'),(18,'ldap_host_alt','','LDAP Alternate Host','Address of LDAP server to which search should be performed','input',NULL,'Security','true','false','false'),(19,'ldap_user_alt','','LDAP Alternate Username','User account for binding to LDAP, should be complete DN','input',NULL,'Security','true','false','false'),(20,'ldap_base_alt','','LDAP Alternate Search Base','Base search path for username lookup','input',NULL,'Security','true','false','false'),(21,'ldap_username','SAMAccountName','LDAP Username Search Field','Field to search for username in LDAP','input',NULL,'Security','true','false','false'),(22,'template_dir','/var/www/demo_trinity/templates/','Template Location','Location of templates','input',NULL,'Paths','false','false','true'),(23,'file_dir','/var/www/demo_trinity/data/files','File Location','Location of files uploaded to resources are stored','input',NULL,'Paths','false','false','true'),(24,'myfile_dir','/var/www/demo_trinity/data/myFiles','MyFiles Location','Location of personal files uploaded to my files or eportfolio are stored','input',NULL,'Paths','false','false','true'),(25,'submission_dir','/var/www/demo_trinity/data/submissions','Submission Location','Location of student submissions','input',NULL,'Paths','false','false','true'),(26,'returns_dir','/var/www/demo_trinity/data/returns','Returns Location','Location of teacher returned work','input',NULL,'Paths','false','false','true'),(27,'dropbox_dir','/var/www/demo_trinity/data/dropbox','Dropbox Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false','true'),(28,'zip_dir','/var/www/demo_trinity/data/thumbnails','ZIP Temp Location','Location of files uploaded to dropbox','input',NULL,'Paths','false','false','true'),(29,'thumbnail_dir','/var/www/schoolbox/www/conf/../../data/thumbnails','Image Cache Location','Location of where thumbnails and resized images are stored after they are generated','input',NULL,'Paths','false','false','true'),(30,'xml_dir','/var/www/demo_trinity/data/cache/xml','XML Cache Location','Location of RSS and XML caching','input',NULL,'Paths','false','false','true'),(31,'thumbnail_size','100','Thumbnail pixel size','Maximum height/width of thumbnails generated for image gallery','input',NULL,'Resources','true','false','true'),(32,'file_upload_count','10','File Upload Limit','The total amount of files that can be uploaded via a single multifile upload page','input',NULL,'Resources','true','false','true'),(33,'max_file_size','10485760','File Size Limit','The maximum size of a single file that can be uploaded. Excepts human formats like 40M and 1GB.','bytes',NULL,'Resources','true','false','true'),(34,'news_edit_other','1','Allow Others to Edit News','Controls whether users with write access to a page can edit each others news posts','boolean',NULL,'Resources','true','false','true'),(35,'group_staff_only','1','Allow Students to be Group Admins','Allow users in student groups to become group admins, beware of the consequences','boolean',NULL,'Resources','true','false','true'),(36,'webdav_enable','0','Enable WebDAV connector in MyFiles','Turns on webDAV in the MyFiles area','boolean',NULL,'Resources','false','false','true'),(37,'search_all_limit','3','Search Summary Item Limit','The amount of items shown for each section on the all tab in the search','boolean',NULL,'Resources','false','false','true'),(38,'search_type_limit','15','Search Items Per Page','The amount of results shown per page in the search results','boolean',NULL,'Resources','false','false','true'),(39,'welcome_image_folder','140','Image Rotation Folder ID','The welcome page image scroller utilises this to find images','input',NULL,'Welcome','true','false','true'),(40,'welcome_rss_url','http://www.abc.net.au/news/indexes/justin/rss.xml','URL for RSS','The URL for the RSS feed displayed on the welcome page','input',NULL,'Welcome','true','false','true'),(41,'welcome_rss_limit','6','RSS Item Limit','The amount of items displayed from the RSS feed on the welcome page','input',NULL,'Welcome','true','false','true'),(42,'weather_enable','1','Enable Weather','Reguarily fetches weather data from Google weather feeds','boolean',NULL,'Welcome','true','false','true'),(43,'weather_location','Melbourne, Victoria','Weather Location','By default display weather at this location','input',NULL,'Welcome','true','false','true'),(44,'name_format','{title} {firstname} {lastname}','Name Output Format','Format of names available fields; {title}, {firstname}, {lastname}','input',NULL,'Localisation','true','false','true'),(45,'calendar_time_format','%l:%i%p','Calendar Time Format','Time output style 24 hour = \'%k:%i\', 12 hour = \'%l:%i%p\'','input',NULL,'Localisation','true','false','true'),(46,'date_long','l F jS Y g:ia','Long Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(47,'date_short','jS M Y g:ia','Short Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(48,'date_database','Y-m-d H:i:s','Database Date Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(49,'date_notime','jS M Y','Date with no Time Format','A PHP date() time format string','input',NULL,'Localisation','true','false','true'),(50,'external_enable','0','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records','boolean',NULL,'External DB','true','false','false'),(51,'external_type','tass','Database Provider','The name of the database product currently being used','select','synergetic:tass:denbigh:schoolbox','External DB','true','false','false'),(52,'external_db_host','synergy','Database Host','The server hostname where SQL database is located','input',NULL,'External DB','true','false','false'),(53,'external_db_user','intranet','Database User','SQL user with read access to the required tables, see documentation','input',NULL,'External DB','true','false','false'),(54,'external_db_passwd','Qu4LitY','Database Password','SQL user password','input',NULL,'External DB','true','false','false'),(55,'external_db_db','SynergyOne','Database Name','The name of the database on the SQL server','input',NULL,'External DB','true','false','false'),(56,'external_portal','http://synergy.portal.com','External DB Portal URL','Schoolbox can integrate with your existing school database to fetch timetable and student records','input',NULL,'External DB','true','false','false'),(57,'external_sync_data','0','Synchronise Data','Controls if we should syncronise the emails with school database, currently only works for parents','boolean',NULL,'External DB','true','false','false'),(58,'mail_enable','1','Enable e-Mail','Enables your email configuration for Schoolbox','boolean','','Email','true','false','true'),(59,'mail_type','roundcube','Mail System','Simple send mail capability, or full imap support with roundcube webmail','select','simple:roundcube','Email','true','false','true'),(60,'mail_host','ssl://mail.alaress.com.au','Mail Server','Location of IMAP mail server for use with roundcube','input','','Email','true','false','true'),(61,'mail_host_port','993','Mail Host Port','Port of mail server, typically 143','input','','Email','true','false','true'),(62,'mail_smtp_host','ssl://mail.alaress.com.au','SMTP Server','Address of server to send email via, this should be your email gateway','input','','Email','true','false','true'),(63,'mail_smtp_port','465','SMTP Port','Port of the SMTP server, typically 25','input','','Email','true','false','true'),(64,'mail_address','alaress.com.au','Email Domain','Email domains with which emails should originate from, if no alt emails are specfied email will be username@domain','input','','Email','true','false','true'),(65,'mail_webmail','','Webmail URL','URL to access OWA or other existing webmail service.  Should be accessible internally and externally','input','','Email','true','false','false'),(66,'mail_from_address','portal@lektec.com.au','Email From Address','Emails that originate from Schoolbox will come from this address','input','','Email','true','false','true'),(67,'mail_from_name','Schoolbox','Email From Name','Emails that originate from Schoolbox will come from this name','input','','Email','true','false','true'),(68,'mail_support_address','support@alaress.com.au','Schoolbox Feedback Address','Requests sent to the Schoolbox feedback form will be sent to this address','input','','Email','true','false','true'),(69,'site_version','12.0.0','Schoolbox Version','Version of Schoolbox currently installed','input',NULL,'Site','false','false','true'),(70,'api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.','input','','Security','true','false','true'),(72,'api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.','input','','Security','true','false','true'),(73,'policy',NULL,'Policy','A key key generated in the PlagScan administration interface','textarea',NULL,'Site','true','false',''),(74,'protect_teacher_photo','1','Hide Teacher Photo','The username you used when registering for the PlagScan service.','boolean',NULL,'Resources','true','false','true'),(75,'protect_student_photo','1','Hide Student Photo','Tick this box to hide student photos from other students','boolean',NULL,'Resources','true','false','true'),(76,'homepage_colour_head_font','013a63','Heading Text','Colour of the heading text on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(77,'homepage_colour_head_bg','cae9b9','Heading Background','Colour of the background of the headings on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(78,'homepage_colour_font','000000','Body Text','Colour of the body text on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(79,'homepage_colour_bg_primary','ffffff','Primary Background','Primary background colour on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(80,'homepage_colour_bg_secondary','faf9f5','Secondary Background','Secondary background colour homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(81,'homepage_colour_link','1d9ffc','Link','Colour of links on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(82,'homepage_colour_hover','ff5a00','Link Hover','Colour of links when your mouse over them on homepage and eportfolio components','input',NULL,'Colours','true','false','false'),(83,'date_timezone','Australia/Victoria','Timezone','Set the local timezone for the server, use the PHP timezones available on the site','select','Australia/Victoria:Australia/NSW:Australia/Queensland:Australia/West:Australia/South','Localisation','true','false','true'),(84,'recaptcha_private_api_key','6LfS0sgSAAAAADh12n8weyLb8irboBWfhseikfCF','reCAPTCHA Private Key','The reCAPTCHA private key','input',NULL,'External DB','false','true','false'),(85,'recaptcha_public_api_key','6LfS0sgSAAAAALQEAZ9Di5rG_RZ7saoLKsgNo6Iq','reCAPTCHA Public Key','The reCAPTCHA public key','input',NULL,'External DB','false','true','false'),(86,'external_web_services_url',NULL,'SOAP Web Services URL','URL of the web services API that can handle SOAP requests','input',NULL,'External DB','true','false','false'),(87,'external_vendor_token_guid',NULL,'Synergetic Vendor Token GUID','The Vendor Token GUID used to access your Synergetic SOAP API','input',NULL,'External DB','true','false','false'),(88,'external_vendor_password',NULL,'Synergetic Vendor Password','The Vendor Password used to access your Synergetic SOAP API','input',NULL,'External DB','true','false','false'),(89,'donvale_students_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/StudDetails.csv','Donvale Students CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(90,'donvale_staff_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/StaffDetails.csv','Donvale Staff CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(91,'donvale_classes_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/ACCClasses.csv','Donvale Classes CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(92,'donvale_classinvolvement_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/ACCClassesStud.csv','Donvale Class Involvement CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(93,'donvale_timetable_csv','/var/www/portal-dev/tools/synchronise/donvale/testdata/ACCTimeTables.csv','Donvale Timetable CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(94,'plagscan_api_key',NULL,'PlagScan API Key','A key key generated in the PlagScan administration interface','input','','External DB','true','false','false'),(95,'plagscan_username',NULL,'PlagScan Username','The username you used when registering for the PlagScan service.','input','','External DB','true','false','false'),(96,'donvale_parents_csv','','Donvale Parents CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(97,'donvale_parent_relation_csv','','Donvale Parent Relationships CSV Import','The full path to smb share','input',NULL,'External DB','true','false','false'),(98,'remote_secret','','Remoted Shared Secret','This string is used in the authentication process with remote systems','input',NULL,'Security','true','false','false'),(99,'portrait_dir','','Portrait Location','The path to the portrait data store','input',NULL,'Paths','false','false','true'),(100,'restrict_parent_directory','1','Restrict Parent Directory','If ticked, parents can only see the parent directories for year levels in which they have a student, otherwise they can see all parent directories.','boolean','true:false','Security','true','false','false'),(101,'restrict_student_submissions','true','Restrict Student Submissions','If ticked, parents cannot see marks and details of their students\' submissions.','boolean','true:false','Security','true','false','false'),(102,'stream_dir','/var/www/portal-dev/www/conf/../../data/cache/stream','Stream Cache Dir','Cache folder for streaming content (RSS, XML, Twitter JSON etc)','input',NULL,'Paths','false','false','true');
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
  `mime_type` varchar(40) DEFAULT NULL,
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
INSERT INTO `eportfolio_comp` VALUES (1,1,'outputLinkList','Links','Add links to your favourite sites and resources',1,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(2,1,'outputTextBox','Text Box','Creates a space where you can enter your own information',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(3,1,'outputBlog','Blog','Write a blog about your experiences',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),(4,1,'outputFileList','Published Files','Files that you have published to your ePortfolio.',1,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:11:\"Description\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(5,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location',3,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(6,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this ePortfolio',3,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(7,1,'outputObjectiveSkill','Skills','A list of your skills for your resume',2,'objectiveSkill.html','','false','false'),(8,1,'outputObjectiveGoal','Goals','A list of your goals for your resume',2,'objectiveGoal.html','','false','false'),(9,1,'outputObjectiveAward','Awards','A list of your awards for your resume',2,'objectiveAward.html','','false','false'),(10,1,'outputObjectiveCoverLetter','Cover Letter','A cover letter for your resume',2,'objectiveCoverLetter.html','','false','false'),(11,1,'outputObjectiveInterest','Interests','A list of interests for your resume',2,'objectiveInterest.html','','false','false'),(12,1,'outputObjectiveEmploymentHistory','Employment History','An employment history for your resume',2,'objectiveEmploymentHistory.html','','false','false'),(13,1,'outputObjectiveEducationHistory','Education History','An education history for your resume',2,'objectiveEducationHistory.html','','false','false'),(14,1,'outputImageScroller','Image Scroller','An image scroller',3,'imageScroller.html','','false','false');
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
INSERT INTO `eportfolio_comp_category` VALUES (1,'General'),(2,'Resume'),(3,'Images');
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
INSERT INTO `error` VALUES (1,'You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.'),(2,'Not enough information was submitted to the form.'),(3,'Please enter either a file or a url, not both.'),(4,'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the schoolbox.<br><br>Please contact your System Administrator if problems persist.'),(5,'You do not have permission to download this file.'),(6,'You do not have write permission to this category.'),(7,'Mail cannot be sent at this time. Please try again later.'),(8,'You do not have permission to delete this resource.'),(9,'Processing failed for the following reason: %MESSAGE%'),(10,'The size of this file exceeds the allowed limit.'),(11,'%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Schoolbox.<br>If you would like to be able to add files of this type to the Schoolbox please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Schoolbox.'),(12,'The category that you are trying to delete is not empty. Please delete all its contents, then try again.'),(13,'The category that you are trying to view does not exist.'),(14,'You do not have permission to view this category.'),(15,'The date that you have submitted is invalid. Please check it, then try again.'),(16,'There was a problem validating your account: <br><b>%MESSAGE%</b>'),(17,'The submission box that you are trying to access does not exist or you do not have permission to view it.'),(18,'The subject selected does not exist.'),(19,'You do not have permission to view this page.'),(20,'You do not have permission to create a category in this location.'),(21,'A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%'),(22,'The database is unavailable at this time. Please try again later.'),(23,'You do not have permission to modify this news.'),(24,'You are not enrolled in this subject.'),(25,'You do not have permission to return this work to this student.'),(26,'The work you are trying to return either does not exist or has already been returned.'),(27,'The work you are trying to submit has already been submitted.'),(28,'You do not have permission to post in this category.'),(29,'You do not have permission to modify or delete this post.'),(30,'This user cannot be the group administrator.'),(31,'No user was found that matches your request.'),(32,'The absentee system cannot be accessed on the weekend.'),(33,'That period does not exist today.'),(34,'You do not have permission to modify this event.'),(35,'You do not have permission to add an event of this context.'),(36,'You are attempting to submit work to a submission box that has not yet opened.'),(37,'You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.'),(38,'This is already in the database. Please modify, then try again.'),(39,'No teams exist. You must add teams before trying to add events.'),(40,'Attendances for Grimwade have not yet been implemented. Please wait until further notice.'),(41,'That file does not exist.'),(42,'No email account information was found. Please either use an external mail client to send email, or provide the schoolbox with an email address.'),(43,'The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.'),(44,'You are trying to view or delete work that either does not exist or does not belong to you.'),(45,'Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".'),(46,'You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.'),(47,'You do not have permission to modify this user\'s file.'),(48,'You do not have permission to delete this user\'s file.'),(49,'The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.'),(50,'You do not have permission to delete this user\'s file.'),(51,'The section you are trying to add questions to does not exist.'),(52,'This link is already shared in your ePortfolio.'),(100,'Your e-portfolio has not been enabled.'),(101,'The resource you are trying to access does not exist or you do not have permission to view it.'),(102,'A booking already exists during the time you have selected.  Please press back and select a time that is free.'),(103,'The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.'),(104,'You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.'),(105,'<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>'),(106,'You do not have permission to modify or delete this link.'),(107,'You do not have permission to modify this blog.'),(108,'You do not have permission to leave comments on this blog.'),(109,'You do not have permission to publish this file.'),(110,'You do not have permission to modify these notes.'),(111,'There are no blogs in this archive.'),(112,'This assessment no longer exists.'),(113,'You have not answered all questions; please go back and submit them.'),(114,'You have not selected an attempt to review.'),(115,'This section no longer exists.'),(116,'Unspecified question type.'),(117,'Please choose a group for this assessment.'),(118,'This attempt no longer exists.'),(119,'This question no longer exists.'),(120,'This online assessment has expired.'),(121,'You have already attempted this online assessment and cannot re-sit it.'),(122,'You have run out of time to complete this online assessment and cannot re-sit it.'),(123,'This attempt has not been recorded.'),(124,'You are the owner of this test and therefore cannot attempt it.'),(125,'Only the test owner can view this page.'),(126,'Only teachers can create and modify tests.'),(127,'Only the test owner can review this test.'),(128,'This attempt no longer exists.'),(129,'You are not allowed to view this students test.'),(130,'Only teachers can create and modify their test contents.'),(131,'Question could not be saved.'),(132,'Answer could not be saved.'),(133,'Could not load question: unknown type.'),(134,'No import file found.'),(135,'This is not a valid Assess Question XML.'),(136,'Unable to create or modify folder as class code already exists.'),(137,'There are no questions to attempt in this assessment.'),(138,'This username already exists.'),(139,'The link provided is not a correctly formatted URL.'),(140,'You already have that link in your list.');
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
INSERT INTO `file_type` VALUES (1,'Assessment Task'),(2,'Assignment'),(3,'Homework'),(6,'Research Project'),(5,'Essay'),(7,'Class Work'),(8,'Presentation'),(9,'Oral'),(10,'Extension'),(11,'Online Assessment');
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
INSERT INTO `groups` VALUES (1,'Administration','staff','true'),(2,'Teachers','staff','true'),(3,'Students','student','true'),(4,'Parents','parent','true');
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
INSERT INTO `homepageComponentCategory` VALUES (1,'Information'),(2,'Collaboration'),(3,'Learning'),(4,'Images'),(5,'Resources'),(6,'Feeds');
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
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepageTemplate`
--

LOCK TABLES `homepageTemplate` WRITE;
/*!40000 ALTER TABLE `homepageTemplate` DISABLE KEYS */;
INSERT INTO `homepageTemplate` VALUES (1,'Class',1,69.9,29.9),(2,'Collab Group',1,69.9,29.9),(4,'Faculty',1,69.9,29.9),(3,'Extra-Curricular',1,69.9,29.9),(5,'Committee',1,69.9,29.9),(6,'House',1,69.9,29.9),(7,'Blog',1,69.9,29.9);
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
INSERT INTO `homepageTemplateLayout` VALUES (1,2,2,3),(1,3,1,3),(1,8,1,1),(1,9,2,5),(1,10,2,6),(1,25,2,2),(1,28,1,2),(1,30,2,1),(1,35,2,4),(2,3,1,3),(2,4,2,1),(2,5,2,3),(2,8,1,1),(2,10,2,5),(2,21,2,2),(2,34,1,2),(2,35,2,4),(3,2,2,5),(3,3,1,3),(3,4,2,2),(3,5,2,3),(3,8,1,2),(3,10,2,6),(3,21,2,1),(3,23,1,1),(3,33,2,4),(3,35,1,4),(4,2,2,3),(4,3,1,3),(4,8,1,2),(4,10,2,4),(4,13,2,1),(4,15,1,1),(4,20,2,2),(5,3,1,3),(5,4,2,2),(5,5,2,3),(5,8,1,1),(5,10,2,5),(5,21,2,2),(5,34,1,2),(5,35,2,4),(6,3,1,3),(6,4,2,1),(6,5,2,3),(6,8,1,1),(6,10,2,5),(6,21,2,2),(6,34,1,2),(6,35,2,4),(7,2,2,2),(7,10,2,3),(7,33,2,1),(7,39,1,1);
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
) ENGINE=MyISAM AUTO_INCREMENT=47 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_comp`
--

LOCK TABLES `homepage_comp` WRITE;
/*!40000 ALTER TABLE `homepage_comp` DISABLE KEYS */;
INSERT INTO `homepage_comp` VALUES (8,1,'outputNews','News','Provides a text area for you to post information, news, blogs and introductions. ',1,'news_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(2,1,'outputLinkList','Links','Provide links to interesting, related and helpful resources on Schoolbox or the WWW.',5,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','true'),(3,1,'outputFileList','Files','List general files that have been uploaded to your homepage. The large version shows both titles and comments.',5,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}','false','true'),(4,2,'outputMembers','Group Members','Show the group members associated with your homepage.  This also provides an email all group members link.',2,'members_s.htm','','false','false'),(5,2,'outputForumList','Forum','Show recent discussion topics going on in your homepages forum.',2,'forum_s.htm','a:2:{s:4:\"sort\";s:4:\"Date\";s:4:\"size\";i:0;}','false','false'),(9,2,'initSubject','Class Management','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.',3,'class_manage.htm','','false','false'),(10,2,'folderManage','Folder Management','Provides links to browse the files, manage group membership and view homepage statistics.',5,'folder_manage.htm','','false','false'),(13,2,'outputManagers','Group Managers','Displays a list of group members with write access to the homepage.  Useful in larger groups to show manages of the group.',2,'staff_s.htm','','false','false'),(15,1,'outputImage','Image','Completely configurable image display, select the image, control the size and link to another location',4,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(20,2,'outputFolderList','Folders','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.',5,'folders_s.htm','a:1:{s:4:\"size\";i:0;}','false','true'),(21,1,'outputEventList','Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(46,0,'outputClickview','ClickView','Embed Clickview Home videos directly into your homepage by providing the ID of the video',5,'clickview.html','a:1:{s:3:\"url\";s:0:\"\";}','false','true'),(23,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ',4,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(24,1,'outputRandImage','Random Image','Display a random image selected from the images on this homepage.',4,'image_s.htm','a:1:{s:5:\"pixel\";i:400;}','false','false'),(25,2,'outputDropBox','File Drop Box','Provides an area for teachers to receive files from students without the need for a submission box and due date',3,'dropbox.htm','','false','false'),(28,1,'outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.',3,'duework_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(26,1,'outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.',6,'rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','false','true'),(30,2,'outputTeachers','Class Teachers','Show a list of teachers responsible for this subject if it has a synergetic subject code.',3,'teacher_s.htm','','false','false'),(31,1,'outputCalendarList','Campus Calendar','Show a calendar for the entire campus on your homepage.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(32,0,'outputDuties','Duties',NULL,1,'duties.htm','','true','false'),(33,2,'outputPoll','Poll','Create a poll to determine group members opinion on a given subject.',2,'poll.htm','a:1:{s:6:\"pollID\";i:0;}','false','true'),(34,1,'outputWiki','Wiki','Provides an area for group members to work together on joint documents.',2,'wiki.htm','a:3:{s:4:\"sort\";s:7:\"Heading\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),(35,1,'outputFeed','Homepage Feed','Provides a running commentary on recent changes to the homepage',6,'feed.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(36,1,'outputTextBox','Text Box','Use this space to write introductory message or information relating to the content that is displayed',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(37,1,'outputAudio','Audio','Lists audio files and allows them to be played.',5,'audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(38,1,'outputPodcast','Podcast','Subscribe to audio podcasts and provide inline playback of the media directly in the homepage',6,'podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false','true'),(39,1,'outputBlog','Blog','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),(40,2,'outputTranslate','Language Translator','Translates text and webpages into another language',3,'translate.html','','false','false'),(41,1,'outputScroller','Image Carousel','Display an animated image carousel, fading between all the images uploaded to your homepage',4,'scroller.html','a:1:{s:5:\"pixel\";i:400;}','false','false'),(42,1,'outputAssess','Online Assessment','Create online tests and quizzes for your students. Results will automatically be populated into your class marksheet. Questions can be stored and retrieved from the school wide question bank.',3,'assess.html','','false','false'),(43,0,'outputChat','Chat','Allows users who are viewing the same homepage to chat with each other.',2,'chat.html','a:2:{s:5:\"state\";s:3:\"off\";s:17:\"teacher_timestamp\";s:0:\"\";}','false','false'),(44,1,'outputSurvey','Surveys','Create online surveys or forms to collect data from your class or group.  Useful for collecting feedback or enrolling in electives.',3,'survey.html','','false','false'),(45,0,'outputTwitter','Twitter','Connect and feed Twitter posts directly on to your homepage. Search via users, hashtags or keywords to create dynamic streams',6,'twitter.html','a:3:{s:8:\"username\";s:7:\"alaress\";s:8:\"keywords\";s:4:\"kony\";s:9:\"maxtweets\";s:1:\"3\";}','false','false');
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
  PRIMARY KEY (`user`,`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_log`
--

LOCK TABLES `homepage_log` WRITE;
/*!40000 ALTER TABLE `homepage_log` DISABLE KEYS */;
INSERT INTO `homepage_log` VALUES ('2011-05-05 00:39:34',1,3,3);
/*!40000 ALTER TABLE `homepage_log` ENABLE KEYS */;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_log`
--

LOCK TABLES `login_log` WRITE;
/*!40000 ALTER TABLE `login_log` DISABLE KEYS */;
INSERT INTO `login_log` VALUES (1,'jleckie','2011-05-05 00:04:51','127.0.0.1',1,NULL),(2,'jleckie','2011-05-05 00:05:34','127.0.0.1',1,NULL),(3,'alaress','2011-05-05 00:50:32','127.0.0.1',1,NULL),(4,'alaress','2011-11-24 10:05:02','192.168.1.155',0,NULL),(5,'alaress','2011-11-24 10:05:05','192.168.1.155',1,NULL);
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
INSERT INTO `luCampus` VALUES ('Main',1),('Other',2);
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
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luEventType`
--

LOCK TABLES `luEventType` WRITE;
/*!40000 ALTER TABLE `luEventType` DISABLE KEYS */;
INSERT INTO `luEventType` VALUES (1,'Event','cee6ea'),(2,'Excursion','f8deb8'),(3,'Report','d4efc4'),(4,'Exam','F2CED6'),(5,'Meeting','d2cef2'),(6,'Online Assessment','fff3b2');
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
INSERT INTO `luServices` VALUES (1,'Homepage'),(2,'Calendar'),(3,'News'),(4,'Tutorial'),(5,'CMS');
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
INSERT INTO `luTitle` VALUES (2,'Mr'),(3,'Mrs'),(4,'Ms'),(9,'Prof'),(8,'Hon Dr'),(7,'Hon'),(6,'Dr'),(5,'Miss'),(10,'A/Prof'),(1,NULL);
/*!40000 ALTER TABLE `luTitle` ENABLE KEYS */;
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
INSERT INTO `newsCampus` VALUES (1,1);
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
INSERT INTO `newsGroup` VALUES (1,1);
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
INSERT INTO `newsTopic` VALUES (1,'General'),(2,'Music'),(3,'Sport'),(4,'Academic');
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
  `item_datetime_published` datetime NOT NULL,
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news_items`
--

LOCK TABLES `news_items` WRITE;
/*!40000 ALTER TABLE `news_items` DISABLE KEYS */;
INSERT INTO `news_items` VALUES (1,1,'Welcome to Schoolbox!','2011-11-24 10:25:42','2011-11-24 00:00:00','2031-12-01 00:00:00','2011-11-24 10:26:25',0,'<p>Welcome to Schoolbox, our Fantastic Community Base Learning Management System!</p>\r\n<p>&nbsp;</p>\r\n<p>You will find as the Administrator Most of your tools are located <a href=\"/adminv2/home\">here </a></p>\r\n<p>The way you can get to this normally is via the Side menu on the left of the screen, simply go to Home, then Click on Admin!</p>\r\n<p>This item is only visible to Super users.</p>\r\n<p>&nbsp;</p>\r\n<p>To Delete this post, Click on the modify icon in the top right corner of this post and then the delete button on the modify page.</p>',0,1,0,NULL);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poll`
--

LOCK TABLES `poll` WRITE;
/*!40000 ALTER TABLE `poll` DISABLE KEYS */;
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
) ENGINE=MyISAM AUTO_INCREMENT=232 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenu`
--

LOCK TABLES `sideMenu` WRITE;
/*!40000 ALTER TABLE `sideMenu` DISABLE KEYS */;
INSERT INTO `sideMenu` VALUES (1,0,'Home','/home.php','',0),(10,1,'News','/news','',6),(96,10,'View News','/news','',1),(97,10,'Add News','/news/modify','',0),(14,1,'Teacher Resources','/resources','',1),(231,14,'Create Submission','/submission/createSubmission/new','',0),(230,1,'Feedback','/feedback.php','',11),(103,14,'Upload File to Multiple Folders','/cms/file/multicat','',0),(105,14,'Add Link to Multiple Folders','/cms/link/multicat','',1),(229,1,'Change Password','/account/modify','',9),(207,0,'Logout','/logout.php','',8),(208,1,'Admin','/adminv2/home','',2),(213,1,'Due Work','/submission/dueWork','',4),(225,224,'Uncompleted Attendance','/modules/tutorial/report/attendance.php','',0),(226,224,'Hours Performed','/modules/tutorial/report/hours.php','',1),(227,224,'Student Attendance','/modules/tutorial/report/student.php','',2),(228,0,'Manage Groups','/group/join','',7);
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
INSERT INTO `sideMenuCampus` VALUES (1,1),(2,1),(3,1),(7,1),(8,1),(9,1),(10,1),(13,1),(14,1),(15,1),(93,1),(94,1),(96,1),(97,1),(103,1),(105,1),(207,1),(213,1),(214,1),(220,1),(221,1),(222,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(220,2),(221,2),(222,2),(224,2),(225,2),(226,2),(227,2),(228,2),(229,2),(230,2);
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
INSERT INTO `sideMenuGroup` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(2,3),(2,4),(2,5),(3,1),(3,2),(3,3),(3,5),(4,1),(4,2),(4,3),(4,4),(4,5),(7,1),(7,2),(7,3),(7,4),(7,5),(8,1),(8,2),(8,3),(8,5),(9,1),(9,2),(9,3),(9,4),(9,5),(10,1),(10,2),(10,3),(10,4),(10,5),(13,1),(13,2),(13,3),(13,4),(13,5),(14,1),(14,2),(14,3),(14,5),(15,1),(15,2),(93,1),(93,2),(93,3),(93,4),(93,5),(94,1),(94,2),(94,3),(94,4),(94,5),(96,1),(96,2),(96,3),(96,4),(96,5),(97,1),(97,2),(103,1),(103,2),(105,1),(105,2),(207,1),(207,2),(207,3),(207,4),(207,5),(213,3),(213,4),(213,5),(214,1),(214,2),(214,3),(214,5),(220,1),(220,2),(220,3),(220,4),(220,5),(221,1),(221,2),(222,1),(222,2),(224,1),(225,1),(226,1),(227,1),(228,1),(228,2),(228,3),(228,4),(228,5),(229,1),(229,2),(229,3),(229,4),(229,5),(230,1),(230,2),(230,3),(230,4),(230,5),(231,1),(231,2);
/*!40000 ALTER TABLE `sideMenuGroup` ENABLE KEYS */;
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
  `datetime_published` datetime DEFAULT NULL,
  `datetime_expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
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
  `accept_submission` tinyint(1) NOT NULL DEFAULT '1',
  `multi_submission` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `description` varchar(750) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `mark_type` int(11) DEFAULT NULL,
  `max_mark` int(11) DEFAULT NULL,
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
INSERT INTO `submission_grade` VALUES (1,0,'E'),(2,10,'E+'),(3,20,'D'),(4,30,'D+'),(5,40,'C'),(6,50,'C+'),(7,60,'B'),(8,70,'B+'),(9,80,'A'),(10,90,'A+');
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
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_mark_type`
--

LOCK TABLES `submission_mark_type` WRITE;
/*!40000 ALTER TABLE `submission_mark_type` DISABLE KEYS */;
INSERT INTO `submission_mark_type` VALUES (1,'Percentage (&#37;)','percent','This should be a whole percentage between 0 and 100.',1),(2,'Letter Grade','letter','Select a letter grade from the list.',1),(3,'Total Mark','raw','This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.',1),(4,'Free Type (legacy support)','free','Should be a letter (A), score (15/20), or percentage (88%).',0);
/*!40000 ALTER TABLE `submission_mark_type` ENABLE KEYS */;
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
  `comment` varchar(255) DEFAULT NULL,
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
INSERT INTO `superusers` VALUES (1);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `textBox`
--

LOCK TABLES `textBox` WRITE;
/*!40000 ALTER TABLE `textBox` DISABLE KEYS */;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable_campus`
--

LOCK TABLES `timetable_campus` WRITE;
/*!40000 ALTER TABLE `timetable_campus` DISABLE KEYS */;
/*!40000 ALTER TABLE `timetable_campus` ENABLE KEYS */;
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
INSERT INTO `user` VALUES (1,'alaress','*CF13A85DDC912117E33FD86AC6E1EB94CB1D973F',1,NULL,'Alaress','Admin',NULL,'0',0,NULL,NULL,NULL,0,'support@alaress.com.au',0,NULL,NULL,NULL,0);
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
INSERT INTO `userCampus` VALUES (1,1,1);
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
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
INSERT INTO `welcomeComponent` VALUES (1,'News','news.htm','outputNews'),(2,'Weather','weather.htm','outputWeather'),(3,'Calendar','calendar.htm','outputCalendar'),(4,'Whats New','changedfolder.htm','outputWhatNew'),(5,'My Students','studentSubjects.htm','outputChildSubjects'),(6,'Timetable','','outputTimetable'),(7,'Helpdesk','helpdesk.htm','outputHelpdesk'),(8,'Parent Helpdesk','parentHelpdesk.htm','outputParentHelpdesk'),(9,'Wenona Intranet Login','wenonaIntranet.htm','outputWenonaIntranet'),(10,'RSS Feed','rss.htm','outputRSS'),(11,'Tutorials','tutorial.html','outputTutorial'),(12,'Grimwade Buttons','grimwade.html','outputPrimary'),(13,'Image Slideshow','gallery.htm','outputSlideshow'),(14,'Icons','shortcuts.htm','outputShortcuts');
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
INSERT INTO `welcomeLayout` VALUES (1,1,1),(1,2,3),(1,3,10),(1,4,13),(1,5,2),(1,6,4),(1,7,7),(2,1,1),(2,2,3),(2,3,10),(2,4,13),(2,5,2),(2,6,4),(2,7,7),(3,8,0),(3,1,1),(3,2,3),(3,3,NULL),(3,4,13),(3,5,2),(3,6,4),(3,7,7),(2,8,0),(4,1,1),(4,2,3),(4,3,8),(4,4,13),(4,5,2),(4,6,4),(4,7,5),(4,8,0),(1,0,6),(2,0,6),(3,0,6),(4,0,6),(1,8,0),(1,9,NULL),(2,9,NULL),(3,9,NULL),(4,9,NULL);
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

-- Dump completed on 2012-06-25 14:13:32

/* Fixes for actual fresh deployments */

UPDATE config SET value='/var/www/schoolbox/templates' WHERE name='template_dir';
UPDATE config SET value='/var/www/schoolbox/data/files' WHERE name='file_dir';
UPDATE config SET value='/var/www/schoolbox/data/myFiles' WHERE name='myfile_dir';
UPDATE config SET value='/var/www/schoolbox/data/submissions' WHERE name='submission_dir';
UPDATE config SET value='/var/www/schoolbox/data/returns' WHERE name='returns_dir';
UPDATE config SET value='/var/www/schoolbox/data/dropbox' WHERE name='dropbox_dir';
UPDATE config SET value='/var/www/schoolbox/data/zip' WHERE name='zip_dir';
UPDATE config SET value='/var/www/schoolbox/data/thumbnails' WHERE name='thumbnail_dir';
UPDATE config SET value='/var/www/schoolbox/data/cache/xml' WHERE name='xml_dir';
UPDATE config SET value='/var/www/schoolbox/data/cache/stream' WHERE name='stream_dir';
UPDATE config SET value='/var/www/schoolbox/data/cache/portrait' WHERE name='portrait_dir';
DELETE FROM config WHERE name='donvale_students_csv';
DELETE FROM config WHERE name='donvale_staff_csv';
DELETE FROM config WHERE name='donvale_classes_csv';
DELETE FROM config WHERE name='donvale_classinvolvement_csv';
DELETE FROM config WHERE name='donvale_timetable_csv';
DELETE FROM config WHERE name='donvale_parents_csv';
DELETE FROM config WHERE name='donvale_parent_relation_csv';
UPDATE config SET value='simple' WHERE name='mail_type';

/* Config variable which prevents staff members from posting news items
 */
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'news_staff_post_enabled','0','Allow Staff to create News','If ticked, staff members can create news posts.','boolean','true:false','Resources','true','false','false');

/* Added custom error handling for cms/portfolio (webdav)
 */
INSERT INTO `error`(`id`,`message`) VALUES ('141','WebDAV error: %MESSAGE%');

UPDATE `config` SET value='12.0.1' WHERE name='site_version';


/* Created new config for setting the default news expiry */

INSERT INTO `config`
(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`)
VALUES
( NULL, 'news_default_expiry','+1 week','Default News expiry date','The relative date that news will expire on by default. Can be any string like \'tomorrow\', \'+1 week\', \'next Thursday\'','input',NULL,'Resources','true','false','true');
UPDATE `homepage_comp` SET `config`='a:3:{s:8:\"username\";s:7:\"alaress\";s:8:\"keywords\";s:4:\"kony\";s:4:\"size\";s:1:\"3\";}' WHERE `id`='47';

UPDATE `config` SET value='12.0.2' WHERE name='site_version';


/* Remember when a student's portrait was last loaded, for caching purposes
 */
ALTER TABLE `user` ADD COLUMN `portrait_updated_at` DATETIME NULL AFTER `eportfolio_enabled`;
/* Update to survey permissions
 */
INSERT INTO `error`(`id`,`message`) VALUES ( '142','You do not have permissions to delete this attempt(s).');
UPDATE `error` SET `message`='You do not have permissions to create and modify test contents.' WHERE `id`='130';
UPDATE `error` SET `message`='You do not have permissions to create and modify tests.' WHERE `id`='126';
UPDATE `error` SET `message`='You do not have permission to view this page.' WHERE `id`='125';

/* Obey start dates error messages
 */
INSERT INTO `error`(`id`,`message`) VALUES ( '143','This Online Assessment is not open yet.');
INSERT INTO `error`(`id`,`message`) VALUES ( '144','This Survey is not open yet.');
INSERT INTO `error`(`id`,`message`) VALUES ( '145','Start time cannot be later than finish time.');
/* Hide google translate homepage component
 */
UPDATE homepage_comp SET invisible = 'true' WHERE method = 'outputTranslate';

/* Remove this component entirely from all homepages
 */
DELETE FROM homepage_comp_location WHERE component IN (SELECT id FROM homepage_comp WHERE method = 'outputTranslate');

UPDATE `config` SET value='12.0.3' WHERE name='site_version';


/* Remove duplicate extensions with incorrect fileTypeID of zero */
DELETE FROM `allowedFileExtension` WHERE fileTypeID = '0';

UPDATE `config` SET value='12.0.4' WHERE name='site_version';

