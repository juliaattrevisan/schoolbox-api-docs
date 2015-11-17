-- MySQL dump 10.13  Distrib 5.5.43, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: schoolbox
-- ------------------------------------------------------
-- Server version	5.5.43-0ubuntu0.14.04.1

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
  `extension` varchar(48) DEFAULT NULL,
  UNIQUE KEY `extension` (`extension`),
  KEY `fileTypeID` (`fileTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowedFileExtension`
--

LOCK TABLES `allowedFileExtension` WRITE;
/*!40000 ALTER TABLE `allowedFileExtension` DISABLE KEYS */;
INSERT INTO `allowedFileExtension` (`fileTypeID`, `extension`) VALUES (1,'gif'),(2,'html'),(2,'htm'),(3,'txt'),(4,'rtx'),(4,'rtf'),(5,'css'),(7,'pjpg'),(8,'png'),(9,'bmp'),(10,'pdf'),(12,'ppt'),(12,'pps'),(13,'xls'),(15,'ps'),(15,'ai'),(15,'eps'),(16,'psd'),(18,'gsp'),(19,'cab'),(19,'swf'),(21,'msw'),(21,'mswmm'),(21,'re3'),(22,'wav'),(23,'mp2'),(23,'mp3'),(24,'ram'),(24,'rm'),(25,'ra'),(26,'midi'),(26,'mid'),(27,'mpg'),(27,'mpeg'),(28,'qt'),(28,'mov'),(11,'doc'),(37,'ppsx'),(38,'pptx'),(39,'xlsx'),(40,'xps'),(42,'tif'),(42,'tiff'),(43,'pub'),(44,'mdb'),(45,'mp4'),(45,'mpv'),(47,'cpp'),(48,'xml'),(49,'dwg'),(50,'gm81'),(50,'gmk'),(51,'flipchart'),(51,'flp'),(44,'accdb'),(21,'camrec'),(73,'ogg'),(52,'one'),(52,'onea'),(52,'onepkg'),(52,'onetoc'),(53,'wp3'),(54,'piv'),(55,'scw'),(56,'ggb'),(56,'ggt'),(57,'kml'),(58,'kmz'),(17,'ogx'),(13,'csv'),(38,'sldx'),(59,'potx'),(46,'docx'),(60,'mpp'),(61,'skp'),(62,'dotx'),(63,'aup'),(64,'epub'),(65,'ibooks'),(20,'ins'),(20,'isf'),(66,'indd'),(19,'fla'),(67,'sib'),(68,'nbk'),(68,'notebook'),(68,'xbk'),(14,'zip'),(69,'aac'),(70,'aiff'),(70,'aif'),(70,'aifc'),(71,'flac'),(72,'m4a'),(73,'oga'),(74,'wma'),(6,'jpe'),(6,'jpeg'),(6,'jpg'),(75,'svg'),(76,'java'),(41,'avi'),(45,'m4v'),(45,'mv4'),(78,'ogv'),(79,'flv'),(79,'f4v'),(29,'wmv'),(80,'7z'),(81,'dir'),(82,'ktz'),(83,'ndp'),(84,'ma'),(85,'vsd'),(86,'key'),(86,'keynote'),(87,'pages'),(88,'numbers'),(89,'webm'),(90,'weba'),(91,'rar'),(92,'webp'),(93,'odb'),(94,'odp'),(95,'otp'),(96,'ods'),(97,'ots'),(98,'odt'),(99,'ott'),(100,'gz'),(100,'gzip'),(101,'mka'),(102,'mkv'),(103,'divx');
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
  `max_file_size` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`fileTypeID`),
  UNIQUE KEY `mime` (`mime`),
  KEY `deleted` (`deleted`)
) ENGINE=MyISAM AUTO_INCREMENT=104 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowedFileTypes`
--

LOCK TABLES `allowedFileTypes` WRITE;
/*!40000 ALTER TABLE `allowedFileTypes` DISABLE KEYS */;
INSERT INTO `allowedFileTypes` (`fileTypeID`, `description`, `mime`, `iconFile`, `inline`, `enable`, `max_file_size`, `deleted`) VALUES (1,'GIF Image','image/gif','image_gif.png','true','true',0,0),(2,'HTML Document','text/html','html.png','true','true',0,0),(3,'Plain Text','text/plain','txt.png','true','true',0,0),(4,'Rich Text','text/richtext','document_alt1.png','true','true',0,0),(5,'CSS','text/css','txt.png','true','true',0,0),(6,'JPEG Image','image/jpeg','image_jpeg.png','true','true',0,0),(7,'Progressing JPEG Image','image/pjpeg','image_jpeg.png','true','true',0,0),(8,'PNG Image','image/png','image_png.png','true','true',0,0),(9,'Bitmap Image','image/bmp','image_bmp.png','true','true',0,0),(10,'PDF Document','application/pdf','pdf_alt.png','true','true',0,0),(11,'Word Document','application/msword','msword_doc.png','false','true',0,0),(12,'Powerpoint Presentation','application/vnd.ms-powerpoint','mspowerpoint_ppt.png','false','true',0,0),(13,'Excel Spreadsheet','application/vnd.ms-excel','msexcel_xls.png','false','true',0,0),(14,'ZIP Archive','application/x-zip-compressed','archive_zip.png','false','true',0,0),(15,'Postscript','application/postscript','postscript_alt.png','true','true',0,0),(16,'Photoshop Image','application/x-photoshop','image_psd.png','false','true',0,0),(17,'OGG Multiplexed','application/ogg','sound_ogg.png','true','true',0,0),(18,'Geometer\'s Sketchpad','application/x-gsp','widget.png','false','true',0,0),(19,'Shockwave Flash','application/x-shockwave-flash','flash_swf.png','true','true',0,0),(20,'Inspiration','application/x-Inspiration','umbrellofile.png','false','true',0,0),(21,'Binary Data (Unknown)','application/octet-stream','binary.png','false','true',0,0),(22,'WAV Audio','audio/wav','sound_wav.png','false','true',0,0),(23,'MPEG Audio','audio/mpeg','sound_mp3.png','false','true',0,0),(24,'Real Audio Stream','audio/x-pn-realaudio','sound.png','false','true',0,0),(25,'Real Audio','audio/x-realaudio','sound.png','false','true',0,0),(26,'MIDI Audio','audio/mid','sound.png','false','true',0,0),(27,'MPEG Video','video/mpeg','video_alt.png','false','true',0,0),(28,'Quicktime Video','video/quicktime','quicktime.png','false','true',0,0),(29,'WMV Video','video/x-ms-wmv','video_alt.png','true','true',0,0),(46,'Microsoft Word 2007 Document','application/vnd.openxmlformats-officedocument.wordprocessingml.document','msword_doc.png','false','true',0,0),(37,'Microsoft Office Slideshow 2007','application/vnd.openxmlformats-officedocument.presentationml.slideshow','mspowerpoint_ppt.png','false','true',0,0),(38,'Microsoft Office Presentation 2007','application/vnd.openxmlformats-officedocument.presentationml.presentation','mspowerpoint_ppt.png','false','true',0,0),(39,'Microsoft Office Spreadsheet 2007','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','msexcel_xls.png','false','true',0,0),(40,'Microsoft XPS','application/vnd.ms-xpsdocument','widget_alt.png','false','true',0,0),(41,'AVI Video','video/avi','video_alt.png','false','true',0,0),(42,'Scanned Image','image/tiff','image_tiff.png','false','true',0,0),(43,'Microsoft Publisher','application/vnd.ms-publisher','document.png','false','true',0,0),(44,'Microsoft Access','application/msaccess','msaccess_mdb.png','false','true',0,0),(45,'MP4 Video','video/mp4','video_alt.png','false','true',0,0),(47,'C++ File','text/x-c++src','image_dwg.png','false','true',0,0),(48,'XML File','application/xml','video_alt.png','false','true',0,0),(49,'AutoCAD','application/x-dwg','vectorgfx_drawingboard.png','false','true',0,0),(50,'Game Maker','application/gamemaker','shellscript.png','false','true',0,0),(51,'ActiveInspire Flipchart','application/Inspire','kpresenter_kpr.png','false','true',0,0),(52,'Microsoft OneNote','application/onenote','notepaper.png','false','true',0,0),(53,'Microsoft Photo Story','application/photostory','video_alt.png','false','true',0,0),(54,'Pivot Animation Files','application/pivot','video_alt.png','false','true',0,0),(55,'Scorewriter Document','application/scorewriter','sound.png','false','true',0,0),(56,'GeoGebra','application/vnd.geogebra.file','log_alt5.png','false','true',0,0),(57,'Google Earth','application/vnd.google-earth.kml+xml','html_alt1.png','false','true',0,0),(58,'Google Earth Compressed','application/vnd.google-earth.kmz ','html_alt1.png','false','true',0,0),(59,'Microsoft Powerpoint 2007 Template','application/vnd.openxmlformats-officedocument.presentationml.template','mspowerpoint_ppt.png','false','true',0,0),(60,'Microsoft Project','application/vnd.ms-project','umbrellofile.png','false','true',0,0),(61,'Google Sketchup','application/vnd.sketchup.skp','database.png','false','true',0,0),(62,'Microsoft Word 2007 Template ','application/vnd.openxmlformats-officedocument.wordprocessingml.template','msword_doc.png','false','true',0,0),(63,'Audacity','application/x-aup','cdtrack.png','false','true',0,0),(64,'eBook','application/epub+zip','man.png','false','true',0,0),(65,'Apple iBook','application/x-ibooks+zip','man.png','false','true',0,0),(66,'Adobe Indesign','application/x-indesign','document.png','false','true',0,0),(67,'Sibelius','application/x-sibelius-score','sound.png','false','true',0,0),(68,'Smartboard Notebook','application/x-smarttech-notebook','kpresenter_kpr.png','false','true',0,0),(69,'AAC Audio','audio/aac','video_alt.png','false','true',0,0),(70,'AIFF Audio','audio/aiff','sound_aiff.png','false','true',0,0),(71,'FLAC Audio','audio/flac','sound.png','false','true',0,0),(72,'MP4 Audio','audio/m4a','sound.png','false','true',0,0),(73,'OGG Audio','audio/ogg','sound_ogg.png','true','true',0,0),(74,'WMA Audio','audio/x-ms-wma','sound_wma.png','false','true',0,0),(75,'Scalar Vector Graphic','image/svg+xml','image_svg_drawingboard.png','true','true',0,0),(76,'Java Source','text/x-java-source','source_java.png','true','true',0,0),(79,'Flash Video File','video/x-flv','video_alt.png','true','true',0,0),(80,'7-Zip','application/x-7z-compressed','archive.png','false','true',0,0),(81,'Adobe Shockwave Player','application/x-director','flash_swf.png','false','true',0,0),(82,'Kahootz','application/vnd.kahootz','shellscript.png','false','true',0,0),(83,'Mathematica Notebook Player','application/vnd.wolfram.player','log_alt1.png','false','true',0,0),(84,'Mathematica Notebooks','application/mathematica','log_alt1.png','false','true',0,0),(85,'Microsoft Visio','application/vnd.visio','umbrellofile.png','false','true',0,0),(86,'Apple Keynote','application/x-iwork-keynote-sffkey','kpresenter_kpr.png','false','true',0,0),(87,'Apple Pages','application/x-iwork-pages-sffpages','document.png','false','true',0,0),(88,'Apple Numbers','application/x-iwork-numbers-sffnumbers','spreadsheet.png','false','true',0,0),(89,'Open Web Media Project - Video','video/webm','video_alt.png','true','true',0,0),(90,'Open Web Media Project - Audio','audio/webm','sound.png','true','true',0,0),(91,'RAR Archive','application/x-rar-compressed','archive_rar.png','false','true',0,0),(92,'WebP Image','image/webp','image_alt.png','true','true',0,0),(93,'OpenDocument Database','application/vnd.oasis.opendocument.database','database.png','false','true',0,0),(94,'OpenDocument Presentation','application/vnd.oasis.opendocument.presentation','kpresenter_kpr.png','false','true',0,0),(95,'OpenDocument Presentation Template','application/vnd.oasis.opendocument.presentation-template','kpresenter_kpr.png','false','true',0,0),(96,'OpenDocument Spreadsheet','application/vnd.oasis.opendocument.spreadsheet','ooo_spread.png','false','true',0,0),(97,'OpenDocument Spreadsheet Template','application/vnd.oasis.opendocument.spreadsheet-template','ooo_spread.png','false','true',0,0),(98,'OpenDocument Text','application/vnd.oasis.opendocument.text','ooo_sxw.png','false','true',0,0),(99,'OpenDocument Text Template','application/vnd.oasis.opendocument.text-template','ooo_sxw.png','false','true',0,0),(100,'GZ Archive','application/x-gzip','archive_gz.png','false','true',0,0),(101,'Matroska Audio','audio/x-matroska','sound.png','false','true',0,0),(102,'Matroska Video','video/x-matroska','video_alt.png','false','true',0,0),(103,'DivX','video/x-divx','video_alt.png','false','true',0,0);
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
  `task_id` int(11) DEFAULT NULL,
  `task_instance_id` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT '11',
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `completion_message` text,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT 'in minutes',
  `max_attempts` int(1) NOT NULL DEFAULT '0',
  `weight` int(11) unsigned NOT NULL DEFAULT '100',
  `availability` enum('assess','survey') DEFAULT 'assess',
  `anonymous_result` int(1) NOT NULL DEFAULT '0',
  `notify_author` int(1) NOT NULL DEFAULT '0',
  `mark_visibility` int(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`assess_id`),
  UNIQUE KEY `id` (`assess_id`),
  KEY `fk_assess_category1` (`category_id`),
  KEY `fk_assess_user1` (`user_id`),
  KEY `assess_task_id` (`task_id`),
  KEY `assess_task_instance_id` (`task_instance_id`),
  KEY `deleted` (`deleted_at`),
  KEY `availability` (`availability`)
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
  `finish` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `fk_assess_attempt_assess1` (`assess_id`),
  KEY `fk_assess_attempt_user1` (`user_id`),
  KEY `deleted` (`deleted_at`)
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
  `type_id` int(8) NOT NULL AUTO_INCREMENT,
  `classname` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `moodle` varchar(255) DEFAULT NULL,
  `automated_marking` enum('true','false') NOT NULL,
  `availability` enum('assess','survey','all') DEFAULT NULL,
  `has_legend` enum('true','false') DEFAULT 'true',
  `has_config` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`type_id`),
  KEY `availability` (`availability`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_luQuestionType`
--

LOCK TABLES `assess_luQuestionType` WRITE;
/*!40000 ALTER TABLE `assess_luQuestionType` DISABLE KEYS */;
INSERT INTO `assess_luQuestionType` (`type_id`, `classname`, `filename`, `description`, `moodle`, `automated_marking`, `availability`, `has_legend`, `has_config`) VALUES (1,'Short','short','Short Answer','shortanswer','false','all','false','true'),(2,'Multi','multi','Multiple Choice (Multiple Answer)','multichoice','true','all','true','true'),(3,'Fillgap','fillgap','Cloze Exercise','cloze','true','assess','true','true'),(4,'Match','match','Matching Pairs','matching','true','assess','true','true'),(5,'Radio','radio','Multiple Choice (Single Answer)','radio','true','all','true','true'),(6,'Rating_Agree','ratingAgree','Rating Scale (Agree)',NULL,'false','survey','true','true'),(7,'Rating_Like','ratingLike','Rating Scale (Like)',NULL,'false','survey','true','true'),(8,'Rating_Important','ratingImportant','Rating Scale (Important)',NULL,'false','survey','true','true'),(9,'Rating_Satisfied','ratingSatisfied','Rating Scale (Satisfied)',NULL,'false','survey','true','true'),(10,'YesNo','yesno','Yes/No',NULL,'false','survey','true','true'),(11,'TrueFalse','truefalse','True/False',NULL,'false','survey','true','true'),(12,'MultiOther','multiOther','Multiple Choice w/ Other','multiOther','false','survey','true','true'),(13,'Numeric','numeric','Numeric Answer','numeric','true','all','false','true'),(14,'RichText','richtext','Rich Text Answer','richtext','false','assess','false','true'),(15,'Exact','exact','Exact Answer','exact','true','assess','true','true'),(16,'Signature','signature','Signature','signature','false','survey','false','true'),(17,'Ranking','ranking','Preferences','ranking','false','survey','true','true');
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
  `config` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_id`),
  KEY `fk_assess_question_assess_luQuestionType1` (`type_id`),
  KEY `fk_assess_question_assess_section1` (`section_id`),
  KEY `deleted_at` (`deleted_at`)
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
  `config` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_bank_id`),
  KEY `question_category_id` (`question_category_id`),
  KEY `type_id` (`type_id`),
  KEY `deleted_at` (`deleted_at`)
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
  PRIMARY KEY (`question_category_id`),
  KEY `parent_id` (`parent_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assess_question_category`
--

LOCK TABLES `assess_question_category` WRITE;
/*!40000 ALTER TABLE `assess_question_category` DISABLE KEYS */;
INSERT INTO `assess_question_category` (`question_category_id`, `parent_id`, `name`, `availability`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,0,'Maths','assess','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(2,0,'English','assess','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(3,0,'Geography','assess','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(4,0,'Science','assess','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(5,0,'History','assess','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(6,0,'LOTE','assess','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(7,0,'Elective Selection','survey','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(8,0,'Teacher Review','survey','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL),(9,0,'Medical','survey','2012-05-30 15:16:52','2012-05-30 15:16:52',NULL);
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
  KEY `fk_assess_response_assess_section1` (`section_id`),
  KEY `deleted_at` (`deleted_at`)
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
  KEY `fk_assess_section_assess1` (`assess_id`),
  KEY `assess_id` (`assess_id`),
  KEY `deleted_at` (`deleted_at`)
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
  `heading` varchar(100) NOT NULL,
  `body` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blog_category1` (`cid`),
  KEY `fk_blog_user1` (`owner`),
  KEY `deleted` (`deleted`),
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
  `body` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blogComment_blog1` (`bid`),
  KEY `fk_blogComment_user1` (`owner`),
  KEY `deleted` (`deleted`)
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
-- Table structure for table `calendar_connector`
--

DROP TABLE IF EXISTS `calendar_connector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_connector` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'For human readability only',
  `connector_class` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'For human readability only',
  `config` text,
  `status` tinyint(1) DEFAULT '1' COMMENT 'Whether this config is active or not',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_connector`
--

LOCK TABLES `calendar_connector` WRITE;
/*!40000 ALTER TABLE `calendar_connector` DISABLE KEYS */;
INSERT INTO `calendar_connector` (`id`, `event_type`, `connector_class`, `config`, `status`) VALUES (1,'Event','Schoolbox','TzozOToiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfU2Nob29sYm94Ijo2OntzOjU6IgAqAGlkIjtpOi0xO3M6ODoiACoAbGFiZWwiO3M6NjoiRXZlbnRzIjtzOjEyOiIAKgBldmVudFR5cGUiO3M6NToiRXZlbnQiO3M6MTc6IgAqAGNvbm5lY3RvckNsYXNzIjtzOjk6IlNjaG9vbGJveCI7czoxNDoiACoAbHVFdmVudFR5cGUiO3M6MToiMSI7czoxNzoiACoAaGFzUGVybWlzc2lvbnMiO2I6MDt9',1),(2,'Submission','Schoolbox','TzozOToiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfU2Nob29sYm94Ijo2OntzOjU6IgAqAGlkIjtpOi0xO3M6ODoiACoAbGFiZWwiO3M6NjoiRXZlbnRzIjtzOjEyOiIAKgBldmVudFR5cGUiO3M6MTA6IlN1Ym1pc3Npb24iO3M6MTc6IgAqAGNvbm5lY3RvckNsYXNzIjtzOjk6IlNjaG9vbGJveCI7czoxNDoiACoAbHVFdmVudFR5cGUiO2k6NDtzOjE3OiIAKgBoYXNQZXJtaXNzaW9ucyI7YjowO30=',1);
/*!40000 ALTER TABLE `calendar_connector` ENABLE KEYS */;
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
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `deleted` (`deleted_at`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` (`id`, `name`, `parent`, `deleted_at`) VALUES (1,'Staff Services',0,NULL),(2,'Faculties',0,NULL),(3,'Extra-Curricular',0,NULL),(4,'Sport',0,NULL),(5,'Groups',0,NULL),(6,'Student Services',0,NULL),(7,'Houses',0,NULL);
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
  `deleted_at` datetime DEFAULT NULL,
  UNIQUE KEY `categoryIDcode` (`categoryID`,`code`),
  KEY `fk_categoryCode_category1` (`categoryID`),
  KEY `code` (`code`),
  KEY `deleted` (`deleted_at`)
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
  `body` text NOT NULL,
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
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
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
  `teacher_id` int(11) DEFAULT NULL,
  `room` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_period_teacher_id` (`teacher_id`),
  KEY `class_period_class_id` (`class_id`),
  KEY `class_period_period_id` (`period_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3985 DEFAULT CHARSET=utf8;
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
  PRIMARY KEY (`id`),
  KEY `class_user_user_id` (`user_id`),
  KEY `class_user_class_id` (`class_id`)
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
  `object` enum('folder','file','link','content','myfile','myfolder') NOT NULL DEFAULT 'folder',
  `objectId` int(10) unsigned NOT NULL DEFAULT '0',
  `action` enum('create','modify','delete','access') NOT NULL DEFAULT 'create',
  `user` int(10) unsigned NOT NULL DEFAULT '0',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `objectId` (`objectId`),
  KEY `time` (`time`),
  KEY `object` (`object`),
  KEY `action` (`action`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cmsLog`
--

LOCK TABLES `cmsLog` WRITE;
/*!40000 ALTER TABLE `cmsLog` DISABLE KEYS */;
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
) ENGINE=MyISAM AUTO_INCREMENT=238 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_config_definition`
--

LOCK TABLES `component_config_definition` WRITE;
/*!40000 ALTER TABLE `component_config_definition` DISABLE KEYS */;
INSERT INTO `component_config_definition` (`id`, `component_definition_id`, `key`, `default_value`, `label`, `description`) VALUES (1,21,'componentname','News','Heading',NULL),(2,21,'size','0','Limit',NULL),(3,21,'src','','Source',NULL),(4,22,'sort','date','Sort by',NULL),(5,22,'sortdir','desc','Direction',NULL),(6,22,'size','0','Limit',NULL),(7,22,'compactMode','1','Compact Mode',NULL),(8,22,'componentname','Links','Heading',NULL),(9,22,'src','','Source',NULL),(10,23,'filter','31','Filter',NULL),(11,23,'sort','date','Sort by',NULL),(12,23,'sortdir','desc','Direction',NULL),(13,23,'size','0','Limit',NULL),(14,23,'componentname','Files','Heading',NULL),(15,23,'compactMode','1','Compact Mode',NULL),(16,23,'src','','Source',NULL),(17,24,'displayPortraits','grid','Display Portraits',NULL),(18,25,'sort','date','Sort by',NULL),(19,25,'size','0','Limit',NULL),(20,25,'componentname','Forum','Heading',NULL),(91,3,'days','1','Days',NULL),(22,25,'src','','Source',NULL),(23,28,'displayPortraits','grid','Display Portraits',NULL),(24,29,'pixel','200','Max width / height',NULL),(25,29,'url','#','Url',NULL),(26,29,'image','Preview Image','Image',NULL),(27,30,'size','0','Limit',NULL),(28,30,'componentname','Folders','Heading',NULL),(90,45,'compactMode','1','Compact Mode',NULL),(30,30,'src','','Source',NULL),(31,31,'days','0','Days',NULL),(32,32,'url','','Url',NULL),(33,33,'componentname','Image Gallery','Heading',NULL),(34,33,'size','0','Limit',NULL),(35,33,'src','','Source',NULL),(36,34,'pixel','400','Max width / height',NULL),(37,36,'size','0','Limit',NULL),(38,36,'compactMode','1','Compact Mode',NULL),(39,37,'url','','Url',NULL),(40,37,'size','5','Limit',NULL),(41,37,'compactMode','1','Compact Mode',NULL),(42,38,'displayPortraits','grid','Display Portraits',NULL),(43,39,'size','0','Limit',NULL),(44,41,'poll','0','Question',NULL),(45,42,'componentname','Wiki','Heading',NULL),(46,42,'src','','Source',NULL),(47,42,'sort','heading','Sort by',NULL),(48,42,'sortdir','desc','Direction',NULL),(49,42,'size','0','Limit',NULL),(50,42,'compactMode','1','Compact Mode',NULL),(51,43,'size','0','Limit',NULL),(52,44,'textbox','0','Textbox',NULL),(53,45,'sort','name','Sort by',NULL),(54,45,'sortdir','asc','Direction',NULL),(55,45,'size','0','Limit',NULL),(56,46,'url','','Url',NULL),(57,46,'size','0','Limit',NULL),(58,46,'compactMode','1','Compact Mode',NULL),(59,47,'componentname','Blog','Heading',NULL),(60,47,'size','3','Limit',NULL),(62,47,'src','','Source',NULL),(63,49,'pixel','200','Max width / height',NULL),(64,49,'captionState','Inactive','Captions',NULL),(65,49,'timing','2','Timing',NULL),(66,49,'componentname','Image Slideshow','Heading',NULL),(67,49,'src','','Source',NULL),(68,50,'compactMode','1','Compact Mode',NULL),(69,51,'componentname','Countdown','Heading',NULL),(70,51,'time','','Time',NULL),(71,51,'date','','Date',NULL),(72,52,'state','off','State',NULL),(73,52,'teacher_timestamp','','',NULL),(74,52,'muted_users','a:0:{}','',NULL),(75,53,'compactMode','1','Compact Mode',NULL),(76,54,'username','','Author',NULL),(77,54,'keywords','','Hashtag',NULL),(78,54,'size','3','Limit',NULL),(79,55,'compactMode','1','Hide heading',NULL),(80,55,'textboxSearch','0','Textbox Search',NULL),(81,2,'componentname','{timeofday}, <strong>{title} {firstname} {lastname}</strong>','Greeting Format',NULL),(82,20,'size','10','Limit',NULL),(83,4,'size','10','Limit',NULL),(84,13,'url','http://www.abc.net.au/news/indexes/justin/rss.xml','Url',NULL),(85,13,'size','10','Limit',NULL),(86,18,'size','10','Limit',NULL),(87,5,'url','','Location',NULL),(88,5,'size','3','Forecast Days',NULL),(89,56,'displayPortraits','grid','Layout Style',NULL),(92,13,'compactMode','0','Compact Mode',NULL),(93,31,'size','0','Limit',NULL),(94,29,'align','center','Align',NULL),(95,49,'align','left','Align',NULL),(96,57,'componentname','My List','List Title',NULL),(97,57,'description','A list component','Purpose',NULL),(98,58,'componentname','My Advanced List','List Title',NULL),(99,58,'description','An advanced list component','Purpose',NULL),(100,59,'componentname','My Link List','List Title',NULL),(101,59,'description','A link list component','Purpose',NULL),(102,60,'componentname','My Textbox','Textbox Title',NULL),(103,60,'description','An textbox component','Purpose',NULL),(104,61,'componentname','My Files','Title',NULL),(105,61,'description','A myFiles component','Purpose',NULL),(106,62,'componentname','Image Gallery','Title',NULL),(107,62,'description','An image gallery component','Purpose',NULL),(108,63,'componentname','Blog','Title',NULL),(109,63,'description','An blog component','Purpose',NULL),(110,64,'componentname','Video','Heading',NULL),(111,64,'size','10','Limit',NULL),(112,64,'src','','Source',NULL),(113,64,'video','','Video',NULL),(114,65,'url','','URL or Embed Code',NULL),(115,66,'componentname','Course Outline','Heading',NULL),(116,66,'courseHomepage','','',NULL),(117,67,'componentname','Course Builder','Heading',NULL),(118,68,'componentname','Rubrics','Heading',NULL),(119,69,'componentname','My Social Stream','Stream Title',NULL),(120,69,'size','5','Size',NULL),(121,69,'src','','Source',NULL),(122,69,'collaborate','1','Collaborate',NULL),(123,69,'commentsAllowed','1','Comments',NULL),(124,71,'componentname','Library Search','Heading',NULL),(125,44,'src','0','Source',NULL),(126,64,'sort','date','Sort by',NULL),(127,64,'sortdir','desc','Direction',NULL),(128,41,'componentname','Poll','Heading',NULL),(129,72,'componentname','Breadcrumbs','Heading',NULL),(130,72,'src','','Source',NULL),(131,73,'componentname','Image Slideshow','Heading',NULL),(132,73,'align','left','Align',NULL),(133,73,'captionState','Active','Captions',NULL),(134,73,'pixel','200','Max width/height',NULL),(135,73,'src','200','Source',NULL),(136,73,'timing','2','Timing',NULL),(137,73,'compactMode','1','Hide header',NULL),(138,74,'componentname','Collab Docs','Heading',NULL),(139,74,'size','5','Size',NULL),(140,74,'src','','Source',NULL),(141,74,'sort','name','Sort By',NULL),(142,74,'sortdir','asc','Direction',NULL),(143,74,'compactMode','1','Compact Mode',NULL),(144,49,'randomStartingSlide','0','Random start',NULL),(145,75,'componentname','Resource Management','Heading',NULL),(146,75,'days','0','Days',NULL),(147,76,'componentname','Image Slider','Heading',NULL),(148,76,'pixel','200','Max width/height',NULL),(149,76,'src','','Source',NULL),(150,76,'timing','5','Timing',NULL),(151,76,'compactMode','1','Hide header',NULL),(152,53,'componentname','Surveys','Heading',NULL),(153,76,'sort','date','Sort by',NULL),(154,76,'sortdir','desc','Direction',NULL),(155,28,'componentname','Group Managers','Heading',NULL),(156,24,'componentname','Members','Heading',NULL),(157,54,'componentname','Twitter','Heading',NULL),(158,77,'componentname','Image Gallery','Heading',NULL),(159,77,'size','10','Limit',NULL),(160,77,'src','200','Source',NULL),(161,78,'componentname','Twitter','Heading',NULL),(162,78,'size','3','Limit',NULL),(163,78,'keywords','','Hashtag',NULL),(164,78,'username','','Author',NULL),(165,79,'componentname','Video','Heading',NULL),(166,79,'size','10','Limit',NULL),(167,79,'src','','Source',NULL),(168,79,'video','','Video',NULL),(169,79,'sort','date','Sort By',NULL),(170,79,'sortdir','desc','Direction',NULL),(171,80,'eportfolioImage','Preview Image','Image',NULL),(172,80,'align','left','Align',NULL),(173,80,'pixel','200','Max width/height',NULL),(174,81,'componentname','Video','Heading',NULL),(175,81,'size','10','Limit',NULL),(176,81,'eportfolioVideo','','Video',NULL),(177,81,'sort','date','Sort By',NULL),(178,81,'sortdir','desc','Direction',NULL),(179,82,'componentname','Pinterest','Heading',NULL),(180,82,'url','','URL',NULL),(181,83,'componentname','Tiles','Heading',NULL),(182,83,'layoutId','0','Layout',NULL),(183,84,'layoutId','0','Layout',NULL),(184,83,'src','0','Source',NULL),(185,84,'src','0','Source',NULL),(186,83,'tileSource','0','Tile',NULL),(187,84,'tileSource','0','Tile',NULL),(188,45,'src','0','Source',NULL),(189,19,'size','0','Limit',NULL),(190,42,'allowCreate','0','Allow Create',NULL),(191,45,'collapse','0','Collapse Content',NULL),(192,47,'collapse','0','Collapse Content',NULL),(193,72,'collapse','0','Collapse Content',NULL),(194,31,'collapse','0','Collapse Content',NULL),(195,52,'collapse','0','Collapse Content',NULL),(196,56,'collapse','0','Collapse Content',NULL),(197,38,'collapse','0','Collapse Content',NULL),(198,32,'collapse','0','Collapse Content',NULL),(199,74,'collapse','0','Collapse Content',NULL),(200,51,'collapse','0','Collapse Content',NULL),(201,67,'collapse','0','Collapse Content',NULL),(202,68,'collapse','0','Collapse Content',NULL),(203,66,'collapse','0','Collapse Content',NULL),(204,36,'collapse','0','Collapse Content',NULL),(205,35,'collapse','0','Collapse Content',NULL),(206,23,'collapse','0','Collapse Content',NULL),(207,30,'collapse','0','Collapse Content',NULL),(208,25,'collapse','0','Collapse Content',NULL),(209,28,'collapse','0','Collapse Content',NULL),(210,24,'collapse','0','Collapse Content',NULL),(211,43,'collapse','0','Collapse Content',NULL),(212,29,'collapse','0','Collapse Content',NULL),(213,33,'collapse','0','Collapse Content',NULL),(214,49,'collapse','0','Collapse Content',NULL),(215,22,'collapse','0','Collapse Content',NULL),(216,21,'collapse','0','Collapse Content',NULL),(217,82,'collapse','0','Collapse Content',NULL),(218,46,'collapse','0','Collapse Content',NULL),(219,41,'collapse','0','Collapse Content',NULL),(220,50,'collapse','0','Collapse Content',NULL),(221,34,'collapse','0','Collapse Content',NULL),(222,37,'collapse','0','Collapse Content',NULL),(223,69,'collapse','0','Collapse Content',NULL),(224,53,'collapse','0','Collapse Content',NULL),(225,44,'collapse','0','Collapse Content',NULL),(226,83,'collapse','0','Collapse Content',NULL),(227,54,'collapse','0','Collapse Content',NULL),(228,64,'collapse','0','Collapse Content',NULL),(229,42,'collapse','0','Collapse Content',NULL),(230,65,'collapse','0','Collapse Content',NULL),(231,85,'collapse','0','Collapse Content',NULL),(232,85,'componentname','Curriculum Details','Heading',NULL),(233,85,'curriculumCode','','Curriculum Code',NULL),(234,86,'collapse','0','Collapse Content',NULL),(235,86,'componentname','Curriculum Overview ','Heading',NULL),(236,86,'curriculumCode','','Curriculum Code',NULL),(237,84,'compactMode','1','Hide Heading',NULL);
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
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `component_config_definition` (`component_config_definition_id`,`component_instance_id`),
  KEY `fk_dashboard_component_option_dashboard_component_instance1` (`component_config_definition_id`),
  KEY `component_instance_id` (`component_instance_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3905 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_config_instance`
--

LOCK TABLES `component_config_instance` WRITE;
/*!40000 ALTER TABLE `component_config_instance` DISABLE KEYS */;
INSERT INTO `component_config_instance` (`id`, `component_config_definition_id`, `component_instance_id`, `value`) VALUES (1,2,93,'0'),(2,37,94,'0'),(3,10,95,'31'),(4,11,95,'Date'),(5,12,95,'Desc'),(6,13,95,'0'),(7,4,96,'Date'),(8,5,96,'Desc'),(9,6,96,'0'),(10,51,100,'0'),(11,4,104,'Date'),(12,5,104,'Desc'),(13,6,104,'0'),(14,63,105,'400'),(15,72,106,'Inactive'),(16,73,106,''),(17,47,107,'Heading'),(18,48,107,'Desc'),(19,49,107,'0'),(20,24,108,'200'),(21,25,108,'#'),(22,26,108,'Preview Image'),(23,44,109,'0'),(24,18,111,'Date'),(25,19,111,'0'),(26,51,113,'0'),(27,34,115,'0'),(28,2,116,'0'),(29,4,117,'Date'),(30,5,117,'Desc'),(31,6,117,'0'),(32,14,118,'Shakespear in love worksheets'),(33,15,118,'0'),(34,10,118,'31'),(35,11,118,'Date'),(36,12,118,'Desc'),(37,16,118,'/homepage/35'),(38,13,118,'0'),(39,10,119,'31'),(40,11,119,'Date'),(41,12,119,'Desc'),(42,13,119,'0'),(43,37,120,'0'),(44,76,121,'alaress'),(45,77,121,'kony'),(46,78,121,'3'),(47,25,122,''),(48,26,122,'Shakespeare in Love '),(49,24,122,'200'),(50,2,123,'0'),(51,10,125,'31'),(52,11,125,'Date'),(53,12,125,'Desc'),(54,13,125,'0'),(55,4,127,'Date'),(56,5,127,'Desc'),(57,6,127,'0'),(58,51,128,'0'),(59,2,131,'0'),(60,52,133,'1'),(61,4,135,'Date'),(62,5,135,'Desc'),(63,6,135,'0'),(64,51,136,'0'),(65,2,139,'0'),(66,25,141,''),(67,26,141,'DoE'),(68,24,141,'200'),(69,25,143,''),(70,26,143,'Outdoor Education'),(71,24,143,'400'),(72,10,149,'31'),(73,11,149,'Date'),(74,12,149,'Desc'),(75,13,149,'0'),(76,44,150,'1'),(77,4,151,'Date'),(78,5,151,'Desc'),(79,6,151,'0'),(80,51,152,'0'),(81,51,156,'0'),(82,2,157,'0'),(83,2,162,'0'),(84,37,163,'0'),(85,10,164,'31'),(86,11,164,'Date'),(87,12,164,'Desc'),(88,13,164,'0'),(89,4,168,'Date'),(90,5,168,'Desc'),(91,6,168,'0'),(92,51,169,'0'),(93,2,172,'0'),(94,4,174,'Date'),(95,5,174,'Desc'),(96,6,174,'0'),(97,2,178,'0'),(98,10,179,'31'),(99,11,179,'Date'),(100,12,179,'Desc'),(101,13,179,'0'),(102,51,181,'0'),(103,60,184,'3'),(104,2,185,'0'),(105,47,186,'Heading'),(106,48,186,'Desc'),(107,49,186,'0'),(108,10,187,'31'),(109,11,187,'Date'),(110,12,187,'Desc'),(111,13,187,'0'),(112,18,188,'Date'),(113,19,188,'0'),(114,31,189,'0'),(115,51,191,'0'),(116,52,193,'0'),(117,2,194,'0'),(118,44,195,'4'),(119,52,196,'4'),(120,52,197,'3'),(121,37,198,'0'),(122,31,200,'0'),(123,53,201,'Name'),(124,54,201,'Asc'),(125,55,201,'0'),(126,8,202,'Information Resources'),(127,7,202,'0'),(128,4,202,'Date'),(129,5,202,'Desc'),(130,9,202,''),(131,6,202,'0'),(132,18,205,'Date'),(133,19,205,'0'),(134,44,206,'3'),(135,25,207,'#'),(136,26,207,'Safety First'),(137,24,207,'900'),(138,8,208,'Safety FIRST!'),(139,7,208,'0'),(140,4,208,'Date'),(141,5,208,'Desc'),(142,9,208,''),(143,6,208,'0'),(144,2,209,'0'),(145,37,210,'0'),(146,63,211,'500'),(147,64,211,'Inactive'),(148,65,211,'15'),(149,31,212,'0'),(150,44,215,'5'),(151,2,216,'0'),(152,34,217,'0'),(153,31,219,'0'),(154,10,220,'31'),(155,11,220,'Date'),(156,12,220,'Desc'),(157,13,220,'0'),(158,51,221,'0'),(159,27,223,'0'),(3370,34,2935,'0'),(3369,33,2935,'2013 Sport Photos'),(164,34,229,'0'),(165,18,230,'Date'),(166,19,230,'0'),(167,31,231,'0'),(168,41,235,'1'),(169,40,235,'5'),(170,39,235,'http://www.abc.net.au/bestof/bestofabc.xml'),(171,2,236,'0'),(172,10,237,'31'),(173,11,237,'Date'),(174,12,237,'Desc'),(175,13,237,'0'),(176,31,239,'0'),(177,18,240,'Date'),(178,19,240,'0'),(179,2,242,'0'),(180,47,243,'Heading'),(181,48,243,'Desc'),(182,49,243,'0'),(183,10,244,'31'),(184,11,244,'Date'),(185,12,244,'Desc'),(186,13,244,'0'),(187,31,246,'0'),(188,18,247,'Date'),(189,19,247,'0'),(190,52,249,'0'),(191,2,250,'0'),(192,47,251,'Heading'),(193,48,251,'Desc'),(194,49,251,'0'),(195,10,252,'31'),(196,11,252,'Date'),(197,12,252,'Desc'),(198,13,252,'0'),(199,31,254,'0'),(200,18,255,'Date'),(201,19,255,'0'),(202,51,256,'0'),(203,47,258,'Heading'),(204,48,258,'Desc'),(205,49,258,'0'),(206,18,259,'Date'),(207,19,259,'0'),(208,37,260,'0'),(209,4,261,'Date'),(210,5,261,'Desc'),(211,6,261,'0'),(212,10,262,'31'),(213,11,262,'Date'),(214,12,262,'Desc'),(215,13,262,'0'),(216,72,265,'Inactive'),(217,73,265,''),(218,2,266,'0'),(219,37,267,'0'),(220,10,268,'31'),(221,11,268,'Date'),(222,12,268,'Desc'),(223,13,268,'0'),(224,4,271,'Date'),(225,5,271,'Desc'),(226,6,271,'0'),(227,51,272,'0'),(228,2,275,'0'),(229,37,276,'0'),(230,10,277,'31'),(231,11,277,'Date'),(232,12,277,'Desc'),(233,13,277,'0'),(234,4,280,'Date'),(235,5,280,'Desc'),(236,6,280,'0'),(237,51,281,'0'),(238,2,284,'0'),(239,37,285,'0'),(240,14,286,'Files'),(241,15,286,'0'),(242,10,286,'31'),(243,11,286,'Date'),(244,12,286,'Desc'),(245,16,286,'/homepage/35'),(246,13,286,'0'),(247,4,289,'Date'),(248,5,289,'Desc'),(249,6,289,'0'),(250,51,290,'0'),(251,2,293,'0'),(252,37,294,'0'),(253,10,295,'31'),(254,11,295,'Date'),(255,12,295,'Desc'),(256,13,295,'0'),(257,4,298,'Date'),(258,5,298,'Desc'),(259,6,298,'0'),(260,51,299,'0'),(261,2,302,'0'),(262,37,303,'0'),(263,10,304,'31'),(264,11,304,'Date'),(265,12,304,'Desc'),(266,13,304,'0'),(267,4,307,'Date'),(268,5,307,'Desc'),(269,6,307,'0'),(270,51,308,'0'),(271,2,311,'0'),(272,37,312,'0'),(273,10,313,'31'),(274,11,313,'Date'),(275,12,313,'Desc'),(276,13,313,'0'),(277,4,316,'Date'),(278,5,316,'Desc'),(279,6,316,'0'),(280,51,317,'0'),(281,2,320,'0'),(282,37,321,'0'),(283,10,322,'31'),(284,11,322,'Date'),(285,12,322,'Desc'),(286,13,322,'0'),(287,4,325,'Date'),(288,5,325,'Desc'),(289,6,325,'0'),(290,51,326,'0'),(291,2,329,'0'),(292,37,330,'0'),(293,10,331,'31'),(294,11,331,'Date'),(295,12,331,'Desc'),(296,13,331,'0'),(297,4,334,'Date'),(298,5,334,'Desc'),(299,6,334,'0'),(300,51,335,'0'),(301,2,338,'0'),(302,37,339,'0'),(303,10,340,'31'),(304,11,340,'Date'),(305,12,340,'Desc'),(306,13,340,'0'),(307,4,343,'Date'),(308,5,343,'Desc'),(309,6,343,'0'),(310,51,344,'0'),(311,2,347,'0'),(312,37,348,'0'),(313,10,349,'31'),(314,11,349,'Date'),(315,12,349,'Desc'),(316,13,349,'0'),(317,4,352,'Date'),(318,5,352,'Desc'),(319,6,352,'0'),(320,51,353,'0'),(321,2,356,'0'),(322,37,357,'0'),(323,10,358,'31'),(324,11,358,'Date'),(325,12,358,'Desc'),(326,13,358,'0'),(327,4,361,'Date'),(328,5,361,'Desc'),(329,6,361,'0'),(330,51,362,'0'),(331,2,365,'0'),(332,37,366,'0'),(333,10,367,'31'),(334,11,367,'Date'),(335,12,367,'Desc'),(336,13,367,'0'),(337,4,370,'Date'),(338,5,370,'Desc'),(339,6,370,'0'),(340,51,371,'0'),(341,2,374,'0'),(342,37,375,'0'),(343,10,376,'31'),(344,11,376,'Date'),(345,12,376,'Desc'),(346,13,376,'0'),(347,4,379,'Date'),(348,5,379,'Desc'),(349,6,379,'0'),(350,51,380,'0'),(351,2,383,'0'),(352,37,384,'0'),(353,10,385,'31'),(354,11,385,'Date'),(355,12,385,'Desc'),(356,13,385,'0'),(357,4,388,'Date'),(358,5,388,'Desc'),(359,6,388,'0'),(360,51,389,'0'),(361,2,392,'0'),(362,37,393,'0'),(363,10,394,'31'),(364,11,394,'Date'),(365,12,394,'Desc'),(366,13,394,'0'),(367,4,397,'Date'),(368,5,397,'Desc'),(369,6,397,'0'),(370,51,398,'0'),(371,2,401,'0'),(372,37,402,'0'),(373,10,403,'31'),(374,11,403,'Date'),(375,12,403,'Desc'),(376,13,403,'0'),(377,4,406,'Date'),(378,5,406,'Desc'),(379,6,406,'0'),(380,51,407,'0'),(381,2,410,'0'),(382,37,411,'0'),(383,10,412,'31'),(384,11,412,'Date'),(385,12,412,'Desc'),(386,13,412,'0'),(387,4,415,'Date'),(388,5,415,'Desc'),(389,6,415,'0'),(390,51,416,'0'),(391,2,419,'0'),(392,37,420,'0'),(393,10,421,'31'),(394,11,421,'Date'),(395,12,421,'Desc'),(396,13,421,'0'),(397,4,424,'Date'),(398,5,424,'Desc'),(399,6,424,'0'),(400,51,425,'0'),(401,2,428,'0'),(402,37,429,'0'),(403,10,430,'31'),(404,11,430,'Date'),(405,12,430,'Desc'),(406,13,430,'0'),(407,4,433,'Date'),(408,5,433,'Desc'),(409,6,433,'0'),(410,51,434,'0'),(411,2,437,'0'),(412,37,438,'0'),(413,10,439,'31'),(414,11,439,'Date'),(415,12,439,'Desc'),(416,13,439,'0'),(417,4,442,'Date'),(418,5,442,'Desc'),(419,6,442,'0'),(420,51,443,'0'),(421,2,446,'0'),(422,37,447,'0'),(423,10,448,'31'),(424,11,448,'Date'),(425,12,448,'Desc'),(426,13,448,'0'),(427,4,451,'Date'),(428,5,451,'Desc'),(429,6,451,'0'),(430,51,452,'0'),(431,2,455,'0'),(432,37,456,'0'),(433,10,457,'31'),(434,11,457,'Date'),(435,12,457,'Desc'),(436,13,457,'0'),(437,4,460,'Date'),(438,5,460,'Desc'),(439,6,460,'0'),(440,51,461,'0'),(441,2,464,'0'),(442,37,465,'0'),(443,10,466,'31'),(444,11,466,'Date'),(445,12,466,'Desc'),(446,13,466,'0'),(447,4,469,'Date'),(448,5,469,'Desc'),(449,6,469,'0'),(450,51,470,'0'),(451,2,473,'0'),(452,37,474,'0'),(453,10,475,'31'),(454,11,475,'Date'),(455,12,475,'Desc'),(456,13,475,'0'),(457,4,478,'Date'),(458,5,478,'Desc'),(459,6,478,'0'),(460,51,479,'0'),(461,2,482,'0'),(462,37,483,'0'),(463,10,484,'31'),(464,11,484,'Date'),(465,12,484,'Desc'),(466,13,484,'0'),(467,4,487,'Date'),(468,5,487,'Desc'),(469,6,487,'0'),(470,51,488,'0'),(471,2,491,'0'),(472,37,492,'0'),(473,10,493,'31'),(474,11,493,'Date'),(475,12,493,'Desc'),(476,13,493,'0'),(477,4,496,'Date'),(478,5,496,'Desc'),(479,6,496,'0'),(480,51,497,'0'),(481,2,500,'0'),(482,37,501,'0'),(483,10,502,'31'),(484,11,502,'Date'),(485,12,502,'Desc'),(486,13,502,'0'),(487,4,505,'Date'),(488,5,505,'Desc'),(489,6,505,'0'),(490,51,506,'0'),(491,2,509,'0'),(492,37,510,'0'),(493,10,511,'31'),(494,11,511,'Date'),(495,12,511,'Desc'),(496,13,511,'0'),(497,4,514,'Date'),(498,5,514,'Desc'),(499,6,514,'0'),(500,51,515,'0'),(501,2,518,'0'),(502,37,519,'0'),(503,10,520,'31'),(504,11,520,'Date'),(505,12,520,'Desc'),(506,13,520,'0'),(507,4,523,'Date'),(508,5,523,'Desc'),(509,6,523,'0'),(510,51,524,'0'),(511,2,527,'0'),(512,37,528,'0'),(513,10,529,'31'),(514,11,529,'Date'),(515,12,529,'Desc'),(516,13,529,'0'),(517,4,532,'Date'),(518,5,532,'Desc'),(519,6,532,'0'),(520,51,533,'0'),(521,2,536,'0'),(522,37,537,'0'),(523,10,538,'31'),(524,11,538,'Date'),(525,12,538,'Desc'),(526,13,538,'0'),(527,4,541,'Date'),(528,5,541,'Desc'),(529,6,541,'0'),(530,51,542,'0'),(531,2,545,'0'),(532,37,546,'0'),(533,10,547,'31'),(534,11,547,'Date'),(535,12,547,'Desc'),(536,13,547,'0'),(537,4,550,'Date'),(538,5,550,'Desc'),(539,6,550,'0'),(540,51,551,'0'),(541,2,554,'0'),(542,37,555,'0'),(543,10,556,'31'),(544,11,556,'Date'),(545,12,556,'Desc'),(546,13,556,'0'),(547,4,559,'Date'),(548,5,559,'Desc'),(549,6,559,'0'),(550,51,560,'0'),(551,2,563,'0'),(552,37,564,'0'),(553,10,565,'31'),(554,11,565,'Date'),(555,12,565,'Desc'),(556,13,565,'0'),(557,4,568,'Date'),(558,5,568,'Desc'),(559,6,568,'0'),(560,51,569,'0'),(561,2,572,'0'),(562,37,573,'0'),(563,10,574,'31'),(564,11,574,'Date'),(565,12,574,'Desc'),(566,13,574,'0'),(567,4,577,'Date'),(568,5,577,'Desc'),(569,6,577,'0'),(570,51,578,'0'),(571,2,581,'0'),(572,37,582,'0'),(573,10,583,'31'),(574,11,583,'Date'),(575,12,583,'Desc'),(576,13,583,'0'),(577,4,586,'Date'),(578,5,586,'Desc'),(579,6,586,'0'),(580,51,587,'0'),(581,2,590,'0'),(582,37,591,'0'),(583,10,592,'31'),(584,11,592,'Date'),(585,12,592,'Desc'),(586,13,592,'0'),(587,4,595,'Date'),(588,5,595,'Desc'),(589,6,595,'0'),(590,51,596,'0'),(591,2,599,'0'),(592,37,600,'0'),(593,10,601,'31'),(594,11,601,'Date'),(595,12,601,'Desc'),(596,13,601,'0'),(597,4,604,'Date'),(598,5,604,'Desc'),(599,6,604,'0'),(600,51,605,'0'),(601,2,608,'0'),(602,37,609,'0'),(603,10,610,'31'),(604,11,610,'Date'),(605,12,610,'Desc'),(606,13,610,'0'),(607,4,613,'Date'),(608,5,613,'Desc'),(609,6,613,'0'),(610,51,614,'0'),(611,2,617,'0'),(612,37,618,'0'),(613,10,619,'31'),(614,11,619,'Date'),(615,12,619,'Desc'),(616,13,619,'0'),(3251,35,2890,NULL),(3249,33,2890,NULL),(3231,52,2866,'11'),(621,2,626,'0'),(622,37,627,'0'),(623,10,628,'31'),(624,11,628,'Date'),(625,12,628,'Desc'),(626,13,628,'0'),(627,4,631,'Date'),(628,5,631,'Desc'),(629,6,631,'0'),(630,51,632,'0'),(631,2,635,'0'),(632,37,636,'0'),(633,10,637,'31'),(634,11,637,'Date'),(635,12,637,'Desc'),(636,13,637,'0'),(637,4,640,'Date'),(638,5,640,'Desc'),(639,6,640,'0'),(640,51,641,'0'),(641,2,644,'0'),(642,37,645,'0'),(643,10,646,'31'),(644,11,646,'Date'),(645,12,646,'Desc'),(646,13,646,'0'),(647,4,649,'Date'),(648,5,649,'Desc'),(649,6,649,'0'),(650,51,650,'0'),(651,2,653,'0'),(652,37,654,'0'),(653,10,655,'31'),(654,11,655,'Date'),(655,12,655,'Desc'),(656,13,655,'0'),(657,4,658,'Date'),(658,5,658,'Desc'),(659,6,658,'0'),(660,51,659,'0'),(661,2,662,'0'),(662,37,663,'0'),(663,10,664,'31'),(664,11,664,'Date'),(665,12,664,'Desc'),(666,13,664,'0'),(667,4,667,'Date'),(668,5,667,'Desc'),(669,6,667,'0'),(670,51,668,'0'),(671,2,671,'0'),(672,37,672,'0'),(673,10,673,'31'),(674,11,673,'Date'),(675,12,673,'Desc'),(676,13,673,'0'),(677,4,676,'Date'),(678,5,676,'Desc'),(679,6,676,'0'),(680,51,677,'0'),(681,2,680,'0'),(682,37,681,'0'),(683,10,682,'31'),(684,11,682,'Date'),(685,12,682,'Desc'),(686,13,682,'0'),(687,4,685,'Date'),(688,5,685,'Desc'),(689,6,685,'0'),(690,51,686,'0'),(691,2,689,'0'),(692,37,690,'0'),(693,10,691,'31'),(694,11,691,'Date'),(695,12,691,'Desc'),(696,13,691,'0'),(697,4,694,'Date'),(698,5,694,'Desc'),(699,6,694,'0'),(700,51,695,'0'),(701,2,698,'0'),(702,37,699,'0'),(703,10,700,'31'),(704,11,700,'Date'),(705,12,700,'Desc'),(706,13,700,'0'),(707,4,703,'Date'),(708,5,703,'Desc'),(709,6,703,'0'),(710,51,704,'0'),(711,2,707,'0'),(712,37,708,'0'),(713,10,709,'31'),(714,11,709,'Date'),(715,12,709,'Desc'),(716,13,709,'0'),(717,4,712,'Date'),(718,5,712,'Desc'),(719,6,712,'0'),(720,51,713,'0'),(721,2,716,'0'),(722,37,717,'0'),(723,10,718,'31'),(724,11,718,'Date'),(725,12,718,'Desc'),(726,13,718,'0'),(727,4,721,'Date'),(728,5,721,'Desc'),(729,6,721,'0'),(730,51,722,'0'),(731,2,725,'0'),(732,37,726,'0'),(733,10,727,'31'),(734,11,727,'Date'),(735,12,727,'Desc'),(736,13,727,'0'),(737,4,730,'Date'),(738,5,730,'Desc'),(739,6,730,'0'),(740,51,731,'0'),(741,2,734,'0'),(742,37,735,'0'),(743,10,736,'31'),(744,11,736,'Date'),(745,12,736,'Desc'),(746,13,736,'0'),(747,4,739,'Date'),(748,5,739,'Desc'),(749,6,739,'0'),(750,51,740,'0'),(751,2,743,'0'),(752,37,744,'0'),(753,10,745,'31'),(754,11,745,'Date'),(755,12,745,'Desc'),(756,13,745,'0'),(757,4,748,'Date'),(758,5,748,'Desc'),(759,6,748,'0'),(760,51,749,'0'),(761,2,752,'0'),(762,37,753,'0'),(763,10,754,'31'),(764,11,754,'Date'),(765,12,754,'Desc'),(766,13,754,'0'),(767,4,757,'Date'),(768,5,757,'Desc'),(769,6,757,'0'),(770,51,758,'0'),(771,2,761,'0'),(772,37,762,'0'),(773,10,763,'31'),(774,11,763,'Date'),(775,12,763,'Desc'),(776,13,763,'0'),(777,4,766,'Date'),(778,5,766,'Desc'),(779,6,766,'0'),(780,51,767,'0'),(781,2,770,'0'),(782,37,771,'0'),(783,10,772,'31'),(784,11,772,'Date'),(785,12,772,'Desc'),(786,13,772,'0'),(787,4,775,'Date'),(788,5,775,'Desc'),(789,6,775,'0'),(790,51,776,'0'),(791,2,779,'0'),(792,37,780,'0'),(793,10,781,'31'),(794,11,781,'Date'),(795,12,781,'Desc'),(796,13,781,'0'),(797,4,784,'Date'),(798,5,784,'Desc'),(799,6,784,'0'),(800,51,785,'0'),(801,2,788,'0'),(802,37,789,'0'),(803,10,790,'31'),(804,11,790,'Date'),(805,12,790,'Desc'),(806,13,790,'0'),(807,4,793,'Date'),(808,5,793,'Desc'),(809,6,793,'0'),(810,51,794,'0'),(811,2,797,'0'),(812,37,798,'0'),(813,10,799,'31'),(814,11,799,'Date'),(815,12,799,'Desc'),(816,13,799,'0'),(817,4,802,'Date'),(818,5,802,'Desc'),(819,6,802,'0'),(820,51,803,'0'),(821,2,806,'0'),(822,37,807,'0'),(823,10,808,'31'),(824,11,808,'Date'),(825,12,808,'Desc'),(826,13,808,'0'),(827,4,811,'Date'),(828,5,811,'Desc'),(829,6,811,'0'),(830,51,812,'0'),(831,2,815,'0'),(832,37,816,'0'),(833,10,817,'31'),(834,11,817,'Date'),(835,12,817,'Desc'),(836,13,817,'0'),(837,4,820,'Date'),(838,5,820,'Desc'),(839,6,820,'0'),(840,51,821,'0'),(841,2,824,'0'),(842,37,825,'0'),(843,10,826,'31'),(844,11,826,'Date'),(845,12,826,'Desc'),(846,13,826,'0'),(847,4,829,'Date'),(848,5,829,'Desc'),(849,6,829,'0'),(850,51,830,'0'),(851,2,833,'0'),(852,37,834,'0'),(853,10,835,'31'),(854,11,835,'Date'),(855,12,835,'Desc'),(856,13,835,'0'),(857,4,838,'Date'),(858,5,838,'Desc'),(859,6,838,'0'),(860,51,839,'0'),(861,2,842,'0'),(862,37,843,'0'),(863,10,844,'31'),(864,11,844,'Date'),(865,12,844,'Desc'),(866,13,844,'0'),(867,4,847,'Date'),(868,5,847,'Desc'),(869,6,847,'0'),(870,51,848,'0'),(871,2,851,'0'),(872,37,852,'0'),(873,10,853,'31'),(874,11,853,'Date'),(875,12,853,'Desc'),(876,13,853,'0'),(877,4,856,'Date'),(878,5,856,'Desc'),(879,6,856,'0'),(880,51,857,'0'),(881,2,860,'0'),(882,37,861,'0'),(883,10,862,'31'),(884,11,862,'Date'),(885,12,862,'Desc'),(886,13,862,'0'),(887,4,865,'Date'),(888,5,865,'Desc'),(889,6,865,'0'),(890,51,866,'0'),(891,2,869,'0'),(892,37,870,'0'),(893,10,871,'31'),(894,11,871,'Date'),(895,12,871,'Desc'),(896,13,871,'0'),(897,4,874,'Date'),(898,5,874,'Desc'),(899,6,874,'0'),(900,51,875,'0'),(901,2,878,'0'),(902,37,879,'0'),(903,10,880,'31'),(904,11,880,'Date'),(905,12,880,'Desc'),(906,13,880,'0'),(907,4,883,'Date'),(908,5,883,'Desc'),(909,6,883,'0'),(910,51,884,'0'),(911,2,887,'0'),(912,37,888,'0'),(913,10,889,'31'),(914,11,889,'Date'),(915,12,889,'Desc'),(916,13,889,'0'),(917,4,892,'Date'),(918,5,892,'Desc'),(919,6,892,'0'),(920,51,893,'0'),(921,2,896,'0'),(922,37,897,'0'),(923,10,898,'31'),(924,11,898,'Date'),(925,12,898,'Desc'),(926,13,898,'0'),(927,4,901,'Date'),(928,5,901,'Desc'),(929,6,901,'0'),(930,51,902,'0'),(931,2,905,'0'),(932,37,906,'0'),(933,10,907,'31'),(934,11,907,'Date'),(935,12,907,'Desc'),(936,13,907,'0'),(937,4,910,'Date'),(938,5,910,'Desc'),(939,6,910,'0'),(940,51,911,'0'),(941,2,914,'0'),(942,37,915,'0'),(943,10,916,'31'),(944,11,916,'Date'),(945,12,916,'Desc'),(946,13,916,'0'),(947,4,919,'Date'),(948,5,919,'Desc'),(949,6,919,'0'),(950,51,920,'0'),(951,2,923,'0'),(952,37,924,'0'),(953,10,925,'31'),(954,11,925,'Date'),(955,12,925,'Desc'),(956,13,925,'0'),(957,4,928,'Date'),(958,5,928,'Desc'),(959,6,928,'0'),(960,51,929,'0'),(961,2,932,'0'),(962,37,933,'0'),(963,10,934,'31'),(964,11,934,'Date'),(965,12,934,'Desc'),(966,13,934,'0'),(967,4,937,'Date'),(968,5,937,'Desc'),(969,6,937,'0'),(970,51,938,'0'),(971,2,941,'0'),(972,37,942,'0'),(973,10,943,'31'),(974,11,943,'Date'),(975,12,943,'Desc'),(976,13,943,'0'),(977,4,946,'Date'),(978,5,946,'Desc'),(979,6,946,'0'),(980,51,947,'0'),(981,2,950,'0'),(982,37,951,'0'),(983,10,952,'31'),(984,11,952,'Date'),(985,12,952,'Desc'),(986,13,952,'0'),(987,4,955,'Date'),(988,5,955,'Desc'),(989,6,955,'0'),(990,51,956,'0'),(991,2,959,'0'),(992,37,960,'0'),(993,10,961,'31'),(994,11,961,'Date'),(995,12,961,'Desc'),(996,13,961,'0'),(997,4,964,'Date'),(998,5,964,'Desc'),(999,6,964,'0'),(1000,51,965,'0'),(1001,2,968,'0'),(1002,37,969,'0'),(1003,10,970,'31'),(1004,11,970,'Date'),(1005,12,970,'Desc'),(1006,13,970,'0'),(1007,4,973,'Date'),(1008,5,973,'Desc'),(1009,6,973,'0'),(1010,51,974,'0'),(1011,2,977,'0'),(1012,37,978,'0'),(1013,10,979,'31'),(1014,11,979,'Date'),(1015,12,979,'Desc'),(1016,13,979,'0'),(1017,34,980,'0'),(1018,4,983,'Date'),(1019,5,983,'Desc'),(1020,6,983,'0'),(1021,51,984,'0'),(1022,2,987,'0'),(1023,37,988,'0'),(1024,10,989,'31'),(1025,11,989,'Date'),(1026,12,989,'Desc'),(1027,13,989,'0'),(1028,4,992,'Date'),(1029,5,992,'Desc'),(1030,6,992,'0'),(1031,51,993,'0'),(1032,2,996,'0'),(1033,37,997,'0'),(1034,10,998,'31'),(1035,11,998,'Date'),(1036,12,998,'Desc'),(1037,13,998,'0'),(1038,4,1001,'Date'),(1039,5,1001,'Desc'),(1040,6,1001,'0'),(1041,51,1002,'0'),(1042,2,1005,'0'),(1043,37,1006,'0'),(1044,10,1007,'31'),(1045,11,1007,'Date'),(1046,12,1007,'Desc'),(1047,13,1007,'0'),(1048,4,1010,'Date'),(1049,5,1010,'Desc'),(1050,6,1010,'0'),(1051,51,1011,'0'),(1052,2,1014,'0'),(1053,37,1015,'0'),(1054,10,1016,'31'),(1055,11,1016,'Date'),(1056,12,1016,'Desc'),(1057,13,1016,'0'),(1058,4,1019,'Date'),(1059,5,1019,'Desc'),(1060,6,1019,'0'),(1061,51,1020,'0'),(1062,2,1023,'0'),(1063,37,1024,'0'),(1064,10,1025,'31'),(1065,11,1025,'Date'),(1066,12,1025,'Desc'),(1067,13,1025,'0'),(1068,4,1028,'Date'),(1069,5,1028,'Desc'),(1070,6,1028,'0'),(1071,51,1029,'0'),(1072,2,1032,'0'),(1073,37,1033,'0'),(1074,10,1034,'31'),(1075,11,1034,'Date'),(1076,12,1034,'Desc'),(1077,13,1034,'0'),(1078,4,1037,'Date'),(1079,5,1037,'Desc'),(1080,6,1037,'0'),(1081,51,1038,'0'),(1082,2,1041,'0'),(1083,37,1042,'0'),(1084,10,1043,'31'),(1085,11,1043,'Date'),(1086,12,1043,'Desc'),(1087,13,1043,'0'),(1088,4,1046,'Date'),(1089,5,1046,'Desc'),(1090,6,1046,'0'),(1091,51,1047,'0'),(1092,2,1050,'0'),(1093,37,1051,'0'),(1094,10,1052,'31'),(1095,11,1052,'Date'),(1096,12,1052,'Desc'),(1097,13,1052,'0'),(1098,4,1055,'Date'),(1099,5,1055,'Desc'),(1100,6,1055,'0'),(1101,51,1056,'0'),(1102,2,1059,'0'),(1103,37,1060,'0'),(1104,10,1061,'31'),(1105,11,1061,'Date'),(1106,12,1061,'Desc'),(1107,13,1061,'0'),(1108,4,1064,'Date'),(1109,5,1064,'Desc'),(1110,6,1064,'0'),(1111,51,1065,'0'),(1112,2,1068,'0'),(1113,37,1069,'0'),(1114,10,1070,'31'),(1115,11,1070,'Date'),(1116,12,1070,'Desc'),(1117,13,1070,'0'),(1118,4,1073,'Date'),(1119,5,1073,'Desc'),(1120,6,1073,'0'),(1121,51,1074,'0'),(1122,2,1077,'0'),(1123,37,1078,'0'),(1124,10,1079,'31'),(1125,11,1079,'Date'),(1126,12,1079,'Desc'),(1127,13,1079,'0'),(1128,4,1082,'Date'),(1129,5,1082,'Desc'),(1130,6,1082,'0'),(1131,51,1083,'0'),(1132,2,1086,'0'),(1133,37,1087,'0'),(1134,10,1088,'31'),(1135,11,1088,'Date'),(1136,12,1088,'Desc'),(1137,13,1088,'0'),(1138,4,1091,'Date'),(1139,5,1091,'Desc'),(1140,6,1091,'0'),(1141,51,1092,'0'),(1142,2,1095,'0'),(1143,37,1096,'0'),(1144,10,1097,'31'),(1145,11,1097,'Date'),(1146,12,1097,'Desc'),(1147,13,1097,'0'),(1148,4,1100,'Date'),(1149,5,1100,'Desc'),(1150,6,1100,'0'),(1151,51,1101,'0'),(1152,2,1104,'0'),(1153,37,1105,'0'),(1154,10,1106,'31'),(1155,11,1106,'Date'),(1156,12,1106,'Desc'),(1157,13,1106,'0'),(1158,4,1109,'Date'),(1159,5,1109,'Desc'),(1160,6,1109,'0'),(1161,51,1110,'0'),(1162,2,1113,'0'),(1163,37,1114,'0'),(1164,10,1115,'31'),(1165,11,1115,'Date'),(1166,12,1115,'Desc'),(1167,13,1115,'0'),(1168,4,1118,'Date'),(1169,5,1118,'Desc'),(1170,6,1118,'0'),(1171,51,1119,'0'),(1172,2,1122,'0'),(1173,37,1123,'0'),(1174,10,1124,'31'),(1175,11,1124,'Date'),(1176,12,1124,'Desc'),(1177,13,1124,'0'),(1178,4,1127,'Date'),(1179,5,1127,'Desc'),(1180,6,1127,'0'),(1181,51,1128,'0'),(1182,2,1131,'0'),(1183,37,1132,'0'),(1184,10,1133,'31'),(1185,11,1133,'Date'),(1186,12,1133,'Desc'),(1187,13,1133,'0'),(1188,4,1136,'Date'),(1189,5,1136,'Desc'),(1190,6,1136,'0'),(1191,51,1137,'0'),(1192,2,1140,'0'),(1193,37,1141,'0'),(1194,10,1142,'31'),(1195,11,1142,'Date'),(1196,12,1142,'Desc'),(1197,13,1142,'0'),(1198,4,1145,'Date'),(1199,5,1145,'Desc'),(1200,6,1145,'0'),(1201,51,1146,'0'),(1202,2,1149,'0'),(1203,37,1150,'0'),(1204,10,1151,'31'),(1205,11,1151,'Date'),(1206,12,1151,'Desc'),(1207,13,1151,'0'),(1208,4,1154,'Date'),(1209,5,1154,'Desc'),(1210,6,1154,'0'),(1211,51,1155,'0'),(1212,2,1158,'0'),(1213,37,1159,'0'),(1214,10,1160,'31'),(1215,11,1160,'Date'),(1216,12,1160,'Desc'),(1217,13,1160,'0'),(1218,4,1163,'Date'),(1219,5,1163,'Desc'),(1220,6,1163,'0'),(1221,51,1164,'0'),(1222,2,1167,'0'),(1223,37,1168,'0'),(1224,10,1169,'31'),(1225,11,1169,'Date'),(1226,12,1169,'Desc'),(1227,13,1169,'0'),(1228,4,1172,'Date'),(1229,5,1172,'Desc'),(1230,6,1172,'0'),(1231,51,1173,'0'),(1232,2,1176,'0'),(1233,37,1177,'0'),(1234,10,1178,'31'),(1235,11,1178,'Date'),(1236,12,1178,'Desc'),(1237,13,1178,'0'),(1238,4,1181,'Date'),(1239,5,1181,'Desc'),(1240,6,1181,'0'),(1241,51,1182,'0'),(1242,2,1185,'0'),(1243,37,1186,'0'),(1244,10,1187,'31'),(1245,11,1187,'Date'),(1246,12,1187,'Desc'),(1247,13,1187,'0'),(1248,4,1190,'Date'),(1249,5,1190,'Desc'),(1250,6,1190,'0'),(1251,51,1191,'0'),(1252,2,1194,'0'),(1253,37,1195,'0'),(1254,10,1196,'31'),(1255,11,1196,'Date'),(1256,12,1196,'Desc'),(1257,13,1196,'0'),(1258,4,1199,'Date'),(1259,5,1199,'Desc'),(1260,6,1199,'0'),(1261,51,1200,'0'),(1262,2,1203,'0'),(1263,37,1204,'0'),(1264,10,1205,'31'),(1265,11,1205,'Date'),(1266,12,1205,'Desc'),(1267,13,1205,'0'),(1268,4,1208,'Date'),(1269,5,1208,'Desc'),(1270,6,1208,'0'),(1271,51,1209,'0'),(1272,2,1212,'0'),(1273,37,1213,'0'),(1274,10,1214,'31'),(1275,11,1214,'Date'),(1276,12,1214,'Desc'),(1277,13,1214,'0'),(1278,4,1217,'Date'),(1279,5,1217,'Desc'),(1280,6,1217,'0'),(1281,51,1218,'0'),(1282,2,1221,'0'),(1283,37,1222,'0'),(1284,10,1223,'31'),(1285,11,1223,'Date'),(1286,12,1223,'Desc'),(1287,13,1223,'0'),(1288,4,1226,'Date'),(1289,5,1226,'Desc'),(1290,6,1226,'0'),(1291,51,1227,'0'),(1292,2,1230,'0'),(1293,37,1231,'0'),(1294,10,1232,'31'),(1295,11,1232,'Date'),(1296,12,1232,'Desc'),(1297,13,1232,'0'),(1298,4,1235,'Date'),(1299,5,1235,'Desc'),(1300,6,1235,'0'),(1301,51,1236,'0'),(1302,2,1239,'0'),(1303,37,1240,'0'),(1304,10,1241,'31'),(1305,11,1241,'Date'),(1306,12,1241,'Desc'),(1307,13,1241,'0'),(1308,4,1244,'Date'),(1309,5,1244,'Desc'),(1310,6,1244,'0'),(1311,51,1245,'0'),(1312,2,1248,'0'),(1313,37,1249,'0'),(1314,10,1250,'31'),(1315,11,1250,'Date'),(1316,12,1250,'Desc'),(1317,13,1250,'0'),(1318,4,1253,'Date'),(1319,5,1253,'Desc'),(1320,6,1253,'0'),(1321,51,1254,'0'),(1322,2,1257,'0'),(1323,37,1258,'0'),(1324,10,1259,'31'),(1325,11,1259,'Date'),(1326,12,1259,'Desc'),(1327,13,1259,'0'),(1328,4,1262,'Date'),(1329,5,1262,'Desc'),(1330,6,1262,'0'),(1331,51,1263,'0'),(1332,2,1266,'0'),(1333,37,1267,'0'),(1334,10,1268,'31'),(1335,11,1268,'Date'),(1336,12,1268,'Desc'),(1337,13,1268,'0'),(1338,4,1271,'Date'),(1339,5,1271,'Desc'),(1340,6,1271,'0'),(1341,51,1272,'0'),(1342,2,1275,'0'),(1343,37,1276,'0'),(1344,10,1277,'31'),(1345,11,1277,'Date'),(1346,12,1277,'Desc'),(1347,13,1277,'0'),(1348,4,1280,'Date'),(1349,5,1280,'Desc'),(1350,6,1280,'0'),(1351,51,1281,'0'),(1352,2,1284,'0'),(1353,37,1285,'0'),(1354,10,1286,'31'),(1355,11,1286,'Date'),(1356,12,1286,'Desc'),(1357,13,1286,'0'),(1358,4,1289,'Date'),(1359,5,1289,'Desc'),(1360,6,1289,'0'),(1361,51,1290,'0'),(1362,2,1293,'0'),(1363,37,1294,'0'),(1364,10,1295,'31'),(1365,11,1295,'Date'),(1366,12,1295,'Desc'),(1367,13,1295,'0'),(1368,4,1298,'Date'),(1369,5,1298,'Desc'),(1370,6,1298,'0'),(1371,51,1299,'0'),(1372,2,1302,'0'),(1373,37,1303,'0'),(1374,10,1304,'31'),(1375,11,1304,'Date'),(1376,12,1304,'Desc'),(1377,13,1304,'0'),(1378,4,1307,'Date'),(1379,5,1307,'Desc'),(1380,6,1307,'0'),(1381,51,1308,'0'),(1382,2,1311,'0'),(1383,37,1312,'0'),(1384,10,1313,'31'),(1385,11,1313,'Date'),(1386,12,1313,'Desc'),(1387,13,1313,'0'),(1388,4,1316,'Date'),(1389,5,1316,'Desc'),(1390,6,1316,'0'),(1391,51,1317,'0'),(1392,2,1320,'0'),(1393,37,1321,'0'),(1394,10,1322,'31'),(1395,11,1322,'Date'),(1396,12,1322,'Desc'),(1397,13,1322,'0'),(1398,4,1325,'Date'),(1399,5,1325,'Desc'),(1400,6,1325,'0'),(1401,51,1326,'0'),(1402,2,1329,'0'),(1403,37,1330,'0'),(1404,10,1331,'31'),(1405,11,1331,'Date'),(1406,12,1331,'Desc'),(1407,13,1331,'0'),(1408,4,1334,'Date'),(1409,5,1334,'Desc'),(1410,6,1334,'0'),(1411,51,1335,'0'),(1412,2,1338,'0'),(1413,37,1339,'0'),(1414,10,1340,'31'),(1415,11,1340,'Date'),(1416,12,1340,'Desc'),(1417,13,1340,'0'),(1418,4,1343,'Date'),(1419,5,1343,'Desc'),(1420,6,1343,'0'),(1421,51,1344,'0'),(1422,2,1347,'0'),(1423,37,1348,'0'),(1424,10,1349,'31'),(1425,11,1349,'Date'),(1426,12,1349,'Desc'),(1427,13,1349,'0'),(1428,4,1352,'Date'),(1429,5,1352,'Desc'),(1430,6,1352,'0'),(1431,51,1353,'0'),(1432,2,1356,'0'),(1433,37,1357,'0'),(1434,10,1358,'31'),(1435,11,1358,'Date'),(1436,12,1358,'Desc'),(1437,13,1358,'0'),(1438,4,1361,'Date'),(1439,5,1361,'Desc'),(1440,6,1361,'0'),(1441,51,1362,'0'),(1442,2,1365,'0'),(1443,37,1366,'0'),(1444,10,1367,'31'),(1445,11,1367,'Date'),(1446,12,1367,'Desc'),(1447,13,1367,'0'),(1448,4,1370,'Date'),(1449,5,1370,'Desc'),(1450,6,1370,'0'),(1451,51,1371,'0'),(1452,2,1374,'0'),(1453,37,1375,'0'),(1454,10,1376,'31'),(1455,11,1376,'Date'),(1456,12,1376,'Desc'),(1457,13,1376,'0'),(1458,4,1379,'Date'),(1459,5,1379,'Desc'),(1460,6,1379,'0'),(1461,51,1380,'0'),(1462,2,1383,'0'),(1463,37,1384,'0'),(1464,10,1385,'31'),(1465,11,1385,'Date'),(1466,12,1385,'Desc'),(1467,13,1385,'0'),(1468,4,1388,'Date'),(1469,5,1388,'Desc'),(1470,6,1388,'0'),(1471,51,1389,'0'),(1472,2,1392,'0'),(1473,37,1393,'0'),(1474,10,1394,'31'),(1475,11,1394,'Date'),(1476,12,1394,'Desc'),(1477,13,1394,'0'),(1478,4,1397,'Date'),(1479,5,1397,'Desc'),(1480,6,1397,'0'),(1481,51,1398,'0'),(1482,2,1401,'0'),(1483,37,1402,'0'),(1484,10,1403,'31'),(1485,11,1403,'Date'),(1486,12,1403,'Desc'),(1487,13,1403,'0'),(1488,4,1406,'Date'),(1489,5,1406,'Desc'),(1490,6,1406,'0'),(1491,51,1407,'0'),(1492,2,1410,'0'),(1493,37,1411,'0'),(1494,10,1412,'31'),(1495,11,1412,'Date'),(1496,12,1412,'Desc'),(1497,13,1412,'0'),(1498,4,1415,'Date'),(1499,5,1415,'Desc'),(1500,6,1415,'0'),(1501,51,1416,'0'),(1502,2,1419,'0'),(1503,37,1420,'0'),(1504,10,1421,'31'),(1505,11,1421,'Date'),(1506,12,1421,'Desc'),(1507,13,1421,'0'),(1508,4,1424,'Date'),(1509,5,1424,'Desc'),(1510,6,1424,'0'),(1511,51,1425,'0'),(1512,2,1428,'0'),(1513,37,1429,'0'),(1514,10,1430,'31'),(1515,11,1430,'Date'),(1516,12,1430,'Desc'),(1517,13,1430,'0'),(1518,4,1433,'Date'),(1519,5,1433,'Desc'),(1520,6,1433,'0'),(1521,51,1434,'0'),(1522,2,1437,'0'),(1523,37,1438,'0'),(1524,10,1439,'31'),(1525,11,1439,'Date'),(1526,12,1439,'Desc'),(1527,13,1439,'0'),(1528,4,1442,'Date'),(1529,5,1442,'Desc'),(1530,6,1442,'0'),(1531,51,1443,'0'),(1532,2,1446,'0'),(1533,37,1447,'0'),(1534,10,1448,'31'),(1535,11,1448,'Date'),(1536,12,1448,'Desc'),(1537,13,1448,'0'),(1538,4,1451,'Date'),(1539,5,1451,'Desc'),(1540,6,1451,'0'),(1541,51,1452,'0'),(1542,2,1455,'0'),(1543,37,1456,'0'),(1544,10,1457,'31'),(1545,11,1457,'Date'),(1546,12,1457,'Desc'),(1547,13,1457,'0'),(1548,4,1460,'Date'),(1549,5,1460,'Desc'),(1550,6,1460,'0'),(1551,51,1461,'0'),(1552,2,1464,'0'),(1553,37,1465,'0'),(1554,10,1466,'31'),(1555,11,1466,'Date'),(1556,12,1466,'Desc'),(1557,13,1466,'0'),(1558,4,1469,'Date'),(1559,5,1469,'Desc'),(1560,6,1469,'0'),(1561,51,1470,'0'),(1562,2,1473,'0'),(1563,37,1474,'0'),(1564,10,1475,'31'),(1565,11,1475,'Date'),(1566,12,1475,'Desc'),(1567,13,1475,'0'),(1568,4,1478,'Date'),(1569,5,1478,'Desc'),(1570,6,1478,'0'),(1571,51,1479,'0'),(1572,2,1482,'0'),(1573,37,1483,'0'),(1574,10,1484,'31'),(1575,11,1484,'Date'),(1576,12,1484,'Desc'),(1577,13,1484,'0'),(1578,4,1487,'Date'),(1579,5,1487,'Desc'),(1580,6,1487,'0'),(1581,51,1488,'0'),(1582,2,1491,'0'),(1583,37,1492,'0'),(1584,10,1493,'31'),(1585,11,1493,'Date'),(1586,12,1493,'Desc'),(1587,13,1493,'0'),(1588,4,1496,'Date'),(1589,5,1496,'Desc'),(1590,6,1496,'0'),(1591,51,1497,'0'),(1592,2,1500,'0'),(1593,37,1501,'0'),(1594,10,1502,'31'),(1595,11,1502,'Date'),(1596,12,1502,'Desc'),(1597,13,1502,'0'),(1598,4,1505,'Date'),(1599,5,1505,'Desc'),(1600,6,1505,'0'),(1601,51,1506,'0'),(1602,2,1509,'0'),(1603,37,1510,'0'),(1604,10,1511,'31'),(1605,11,1511,'Date'),(1606,12,1511,'Desc'),(1607,13,1511,'0'),(1608,4,1514,'Date'),(1609,5,1514,'Desc'),(1610,6,1514,'0'),(1611,51,1515,'0'),(1612,2,1518,'0'),(1613,37,1519,'0'),(1614,10,1520,'31'),(1615,11,1520,'Date'),(1616,12,1520,'Desc'),(1617,13,1520,'0'),(1618,4,1523,'Date'),(1619,5,1523,'Desc'),(1620,6,1523,'0'),(1621,51,1524,'0'),(1622,2,1527,'0'),(1623,37,1528,'0'),(1624,10,1529,'31'),(1625,11,1529,'Date'),(1626,12,1529,'Desc'),(1627,13,1529,'0'),(1628,4,1532,'Date'),(1629,5,1532,'Desc'),(1630,6,1532,'0'),(1631,51,1533,'0'),(1632,2,1536,'0'),(1633,37,1537,'0'),(1634,10,1538,'31'),(1635,11,1538,'Date'),(1636,12,1538,'Desc'),(1637,13,1538,'0'),(1638,4,1541,'Date'),(1639,5,1541,'Desc'),(1640,6,1541,'0'),(1641,51,1542,'0'),(1642,2,1545,'0'),(1643,37,1546,'0'),(1644,10,1547,'31'),(1645,11,1547,'Date'),(1646,12,1547,'Desc'),(1647,13,1547,'0'),(1648,4,1550,'Date'),(1649,5,1550,'Desc'),(1650,6,1550,'0'),(1651,51,1551,'0'),(1652,2,1554,'0'),(1653,37,1555,'0'),(1654,10,1556,'31'),(1655,11,1556,'Date'),(1656,12,1556,'Desc'),(1657,13,1556,'0'),(1658,4,1559,'Date'),(1659,5,1559,'Desc'),(1660,6,1559,'0'),(1661,51,1560,'0'),(1662,2,1563,'0'),(1663,37,1564,'0'),(1664,10,1565,'31'),(1665,11,1565,'Date'),(1666,12,1565,'Desc'),(1667,13,1565,'0'),(1668,4,1568,'Date'),(1669,5,1568,'Desc'),(1670,6,1568,'0'),(1671,51,1569,'0'),(1672,2,1572,'0'),(1673,37,1573,'0'),(3676,14,3126,NULL),(3675,15,3126,NULL),(3674,3,3125,NULL),(1678,4,1577,'Date'),(1679,5,1577,'Desc'),(1680,6,1577,'0'),(3673,2,3125,NULL),(1682,2,1581,'0'),(1683,37,1582,'0'),(1684,10,1583,'31'),(1685,11,1583,'Date'),(1686,12,1583,'Desc'),(1687,13,1583,'0'),(1688,4,1586,'Date'),(1689,5,1586,'Desc'),(1690,6,1586,'0'),(1691,51,1587,'0'),(1692,2,1590,'0'),(1693,37,1591,'0'),(1694,10,1592,'31'),(1695,11,1592,'Date'),(1696,12,1592,'Desc'),(1697,13,1592,'0'),(1698,4,1595,'Date'),(1699,5,1595,'Desc'),(1700,6,1595,'0'),(1701,51,1596,'0'),(1702,2,1599,'0'),(1703,37,1600,'0'),(1704,10,1601,'31'),(1705,11,1601,'Date'),(1706,12,1601,'Desc'),(1707,13,1601,'0'),(1708,4,1604,'Date'),(1709,5,1604,'Desc'),(1710,6,1604,'0'),(1711,51,1605,'0'),(1712,2,1608,'0'),(1713,37,1609,'0'),(1714,10,1610,'31'),(1715,11,1610,'Date'),(1716,12,1610,'Desc'),(1717,13,1610,'0'),(1718,4,1613,'Date'),(1719,5,1613,'Desc'),(1720,6,1613,'0'),(1721,51,1614,'0'),(1722,2,1617,'0'),(1723,37,1618,'0'),(1724,10,1619,'31'),(1725,11,1619,'Date'),(1726,12,1619,'Desc'),(1727,13,1619,'0'),(1728,4,1622,'Date'),(1729,5,1622,'Desc'),(1730,6,1622,'0'),(1731,51,1623,'0'),(1732,2,1626,'0'),(1733,37,1627,'0'),(1734,10,1628,'31'),(1735,11,1628,'Date'),(1736,12,1628,'Desc'),(1737,13,1628,'0'),(1738,4,1631,'Date'),(1739,5,1631,'Desc'),(1740,6,1631,'0'),(1741,51,1632,'0'),(1742,2,1635,'0'),(1743,37,1636,'0'),(1744,10,1637,'31'),(1745,11,1637,'Date'),(1746,12,1637,'Desc'),(1747,13,1637,'0'),(1748,4,1640,'Date'),(1749,5,1640,'Desc'),(1750,6,1640,'0'),(1751,51,1641,'0'),(1752,2,1644,'0'),(1753,37,1645,'0'),(1754,10,1646,'31'),(1755,11,1646,'Date'),(1756,12,1646,'Desc'),(1757,13,1646,'0'),(1758,4,1649,'Date'),(1759,5,1649,'Desc'),(1760,6,1649,'0'),(1761,51,1650,'0'),(1762,2,1653,'0'),(1763,37,1654,'0'),(1764,10,1655,'31'),(1765,11,1655,'Date'),(1766,12,1655,'Desc'),(1767,13,1655,'0'),(1768,4,1658,'Date'),(1769,5,1658,'Desc'),(1770,6,1658,'0'),(1771,51,1659,'0'),(1772,2,1662,'0'),(1773,37,1663,'0'),(1774,10,1664,'31'),(1775,11,1664,'Date'),(1776,12,1664,'Desc'),(1777,13,1664,'0'),(1778,4,1667,'Date'),(1779,5,1667,'Desc'),(1780,6,1667,'0'),(1781,51,1668,'0'),(1782,2,1671,'0'),(1783,37,1672,'0'),(1784,10,1673,'31'),(1785,11,1673,'Date'),(1786,12,1673,'Desc'),(1787,13,1673,'0'),(1788,4,1676,'Date'),(1789,5,1676,'Desc'),(1790,6,1676,'0'),(1791,51,1677,'0'),(1792,2,1680,'0'),(1793,37,1681,'0'),(1794,10,1682,'31'),(1795,11,1682,'Date'),(1796,12,1682,'Desc'),(1797,13,1682,'0'),(1798,4,1685,'Date'),(1799,5,1685,'Desc'),(1800,6,1685,'0'),(1801,51,1686,'0'),(1802,2,1689,'0'),(1803,37,1690,'0'),(1804,10,1691,'31'),(1805,11,1691,'Date'),(1806,12,1691,'Desc'),(1807,13,1691,'0'),(1808,4,1694,'Date'),(1809,5,1694,'Desc'),(1810,6,1694,'0'),(1811,51,1695,'0'),(1812,2,1698,'0'),(1813,37,1699,'0'),(1814,10,1700,'31'),(1815,11,1700,'Date'),(1816,12,1700,'Desc'),(1817,13,1700,'0'),(1818,4,1703,'Date'),(1819,5,1703,'Desc'),(1820,6,1703,'0'),(1821,51,1704,'0'),(1822,2,1707,'0'),(1823,37,1708,'0'),(1824,10,1709,'31'),(1825,11,1709,'Date'),(1826,12,1709,'Desc'),(1827,13,1709,'0'),(1828,4,1712,'Date'),(1829,5,1712,'Desc'),(1830,6,1712,'0'),(1831,51,1713,'0'),(1832,2,1716,'0'),(1833,37,1717,'0'),(1834,10,1718,'31'),(1835,11,1718,'Date'),(1836,12,1718,'Desc'),(1837,13,1718,'0'),(1838,4,1721,'Date'),(1839,5,1721,'Desc'),(1840,6,1721,'0'),(1841,51,1722,'0'),(1842,2,1725,'0'),(1843,37,1726,'0'),(1844,10,1727,'31'),(1845,11,1727,'Date'),(1846,12,1727,'Desc'),(1847,13,1727,'0'),(1848,4,1730,'Date'),(1849,5,1730,'Desc'),(1850,6,1730,'0'),(1851,51,1731,'0'),(1852,2,1734,'0'),(1853,37,1735,'0'),(1854,10,1736,'31'),(1855,11,1736,'Date'),(1856,12,1736,'Desc'),(1857,13,1736,'0'),(1858,4,1739,'Date'),(1859,5,1739,'Desc'),(1860,6,1739,'0'),(1861,51,1740,'0'),(1862,2,1743,'0'),(1863,37,1744,'0'),(1864,10,1745,'31'),(1865,11,1745,'Date'),(1866,12,1745,'Desc'),(1867,13,1745,'0'),(1868,4,1748,'Date'),(1869,5,1748,'Desc'),(1870,6,1748,'0'),(1871,51,1749,'0'),(1872,2,1752,'0'),(1873,37,1753,'0'),(1874,10,1754,'31'),(1875,11,1754,'Date'),(1876,12,1754,'Desc'),(1877,13,1754,'0'),(1878,4,1757,'Date'),(1879,5,1757,'Desc'),(1880,6,1757,'0'),(1881,51,1758,'0'),(1882,2,1761,'0'),(1883,37,1762,'0'),(1884,10,1763,'31'),(1885,11,1763,'Date'),(1886,12,1763,'Desc'),(1887,13,1763,'0'),(1888,4,1766,'Date'),(1889,5,1766,'Desc'),(1890,6,1766,'0'),(1891,51,1767,'0'),(1892,2,1770,'0'),(1893,37,1771,'0'),(1894,10,1772,'31'),(1895,11,1772,'Date'),(1896,12,1772,'Desc'),(1897,13,1772,'0'),(1898,4,1775,'Date'),(1899,5,1775,'Desc'),(1900,6,1775,'0'),(1901,51,1776,'0'),(1902,2,1779,'0'),(1903,37,1780,'0'),(1904,10,1781,'31'),(1905,11,1781,'Date'),(1906,12,1781,'Desc'),(1907,13,1781,'0'),(1908,4,1784,'Date'),(1909,5,1784,'Desc'),(1910,6,1784,'0'),(1911,51,1785,'0'),(1912,2,1788,'0'),(1913,37,1789,'0'),(1914,10,1790,'31'),(1915,11,1790,'Date'),(1916,12,1790,'Desc'),(1917,13,1790,'0'),(1918,4,1793,'Date'),(1919,5,1793,'Desc'),(1920,6,1793,'0'),(1921,51,1794,'0'),(1922,2,1797,'0'),(1923,37,1798,'0'),(1924,10,1799,'31'),(1925,11,1799,'Date'),(1926,12,1799,'Desc'),(1927,13,1799,'0'),(1928,4,1802,'Date'),(1929,5,1802,'Desc'),(1930,6,1802,'0'),(1931,51,1803,'0'),(1932,2,1806,'0'),(1933,37,1807,'0'),(1934,10,1808,'31'),(1935,11,1808,'Date'),(1936,12,1808,'Desc'),(1937,13,1808,'0'),(1938,4,1811,'Date'),(1939,5,1811,'Desc'),(1940,6,1811,'0'),(1941,51,1812,'0'),(1942,2,1815,'0'),(1943,37,1816,'0'),(1944,10,1817,'31'),(1945,11,1817,'Date'),(1946,12,1817,'Desc'),(1947,13,1817,'0'),(1948,4,1820,'Date'),(1949,5,1820,'Desc'),(1950,6,1820,'0'),(1951,51,1821,'0'),(1952,2,1824,'0'),(1953,37,1825,'0'),(1954,10,1826,'31'),(1955,11,1826,'Date'),(1956,12,1826,'Desc'),(1957,13,1826,'0'),(1958,4,1829,'Date'),(1959,5,1829,'Desc'),(1960,6,1829,'0'),(1961,51,1830,'0'),(1962,2,1833,'0'),(1963,37,1834,'0'),(1964,10,1835,'31'),(1965,11,1835,'Date'),(1966,12,1835,'Desc'),(1967,13,1835,'0'),(1968,4,1838,'Date'),(1969,5,1838,'Desc'),(1970,6,1838,'0'),(1971,51,1839,'0'),(1972,2,1842,'0'),(1973,37,1843,'0'),(1974,10,1844,'31'),(1975,11,1844,'Date'),(1976,12,1844,'Desc'),(1977,13,1844,'0'),(1978,4,1847,'Date'),(1979,5,1847,'Desc'),(1980,6,1847,'0'),(1981,51,1848,'0'),(1982,2,1851,'0'),(1983,37,1852,'0'),(1984,10,1853,'31'),(1985,11,1853,'Date'),(1986,12,1853,'Desc'),(1987,13,1853,'0'),(1988,4,1856,'Date'),(1989,5,1856,'Desc'),(1990,6,1856,'0'),(1991,51,1857,'0'),(1992,2,1860,'0'),(1993,37,1861,'0'),(1994,10,1862,'31'),(1995,11,1862,'Date'),(1996,12,1862,'Desc'),(1997,13,1862,'0'),(1998,4,1865,'Date'),(1999,5,1865,'Desc'),(2000,6,1865,'0'),(2001,51,1866,'0'),(2002,2,1869,'0'),(2003,37,1870,'0'),(2004,10,1871,'31'),(2005,11,1871,'Date'),(2006,12,1871,'Desc'),(2007,13,1871,'0'),(2008,4,1874,'Date'),(2009,5,1874,'Desc'),(2010,6,1874,'0'),(2011,51,1875,'0'),(2012,2,1878,'0'),(2013,37,1879,'0'),(2014,10,1880,'31'),(2015,11,1880,'Date'),(2016,12,1880,'Desc'),(2017,13,1880,'0'),(2018,4,1883,'Date'),(2019,5,1883,'Desc'),(2020,6,1883,'0'),(2021,51,1884,'0'),(2022,2,1887,'0'),(2023,37,1888,'0'),(2024,10,1889,'31'),(2025,11,1889,'Date'),(2026,12,1889,'Desc'),(2027,13,1889,'0'),(2028,4,1892,'Date'),(2029,5,1892,'Desc'),(2030,6,1892,'0'),(2031,51,1893,'0'),(2032,2,1896,'0'),(2033,37,1897,'0'),(2034,10,1898,'31'),(2035,11,1898,'Date'),(2036,12,1898,'Desc'),(2037,13,1898,'0'),(2038,4,1901,'Date'),(2039,5,1901,'Desc'),(2040,6,1901,'0'),(2041,51,1902,'0'),(2042,2,1905,'0'),(2043,37,1906,'0'),(2044,10,1907,'31'),(2045,11,1907,'Date'),(2046,12,1907,'Desc'),(2047,13,1907,'0'),(2048,4,1910,'Date'),(2049,5,1910,'Desc'),(2050,6,1910,'0'),(2051,51,1911,'0'),(2052,2,1914,'0'),(2053,37,1915,'0'),(2054,10,1916,'31'),(2055,11,1916,'Date'),(2056,12,1916,'Desc'),(2057,13,1916,'0'),(2058,4,1919,'Date'),(2059,5,1919,'Desc'),(2060,6,1919,'0'),(2061,51,1920,'0'),(2062,2,1923,'0'),(2063,37,1924,'0'),(2064,10,1925,'31'),(2065,11,1925,'Date'),(2066,12,1925,'Desc'),(2067,13,1925,'0'),(2068,4,1928,'Date'),(2069,5,1928,'Desc'),(2070,6,1928,'0'),(2071,51,1929,'0'),(2072,2,1932,'0'),(2073,37,1933,'0'),(2074,10,1934,'31'),(2075,11,1934,'Date'),(2076,12,1934,'Desc'),(2077,13,1934,'0'),(2078,4,1937,'Date'),(2079,5,1937,'Desc'),(2080,6,1937,'0'),(2081,51,1938,'0'),(2082,2,1941,'0'),(2083,37,1942,'0'),(2084,10,1943,'31'),(2085,11,1943,'Date'),(2086,12,1943,'Desc'),(2087,13,1943,'0'),(2088,4,1946,'Date'),(2089,5,1946,'Desc'),(2090,6,1946,'0'),(2091,51,1947,'0'),(2092,2,1950,'0'),(2093,37,1951,'0'),(2094,10,1952,'31'),(2095,11,1952,'Date'),(2096,12,1952,'Desc'),(2097,13,1952,'0'),(2098,4,1955,'Date'),(2099,5,1955,'Desc'),(2100,6,1955,'0'),(2101,51,1956,'0'),(2102,2,1959,'0'),(2103,37,1960,'0'),(2104,10,1961,'31'),(2105,11,1961,'Date'),(2106,12,1961,'Desc'),(2107,13,1961,'0'),(2108,4,1964,'Date'),(2109,5,1964,'Desc'),(2110,6,1964,'0'),(2111,51,1965,'0'),(2112,2,1968,'0'),(2113,37,1969,'0'),(2114,10,1970,'31'),(2115,11,1970,'Date'),(2116,12,1970,'Desc'),(2117,13,1970,'0'),(2118,4,1973,'Date'),(2119,5,1973,'Desc'),(2120,6,1973,'0'),(2121,51,1974,'0'),(2122,2,1977,'0'),(2123,37,1978,'0'),(2124,10,1979,'31'),(2125,11,1979,'Date'),(2126,12,1979,'Desc'),(2127,13,1979,'0'),(2128,4,1982,'Date'),(2129,5,1982,'Desc'),(2130,6,1982,'0'),(2131,51,1983,'0'),(2132,2,1986,'0'),(2133,37,1987,'0'),(2134,10,1988,'31'),(2135,11,1988,'Date'),(2136,12,1988,'Desc'),(2137,13,1988,'0'),(2138,4,1991,'Date'),(2139,5,1991,'Desc'),(2140,6,1991,'0'),(2141,51,1992,'0'),(2142,2,1995,'0'),(2143,37,1996,'0'),(2144,10,1997,'31'),(2145,11,1997,'Date'),(2146,12,1997,'Desc'),(2147,13,1997,'0'),(2148,4,2000,'Date'),(2149,5,2000,'Desc'),(2150,6,2000,'0'),(2151,51,2001,'0'),(2152,2,2004,'0'),(2153,37,2005,'0'),(2154,10,2006,'31'),(2155,11,2006,'Date'),(2156,12,2006,'Desc'),(2157,13,2006,'0'),(2158,4,2009,'Date'),(2159,5,2009,'Desc'),(2160,6,2009,'0'),(2161,51,2010,'0'),(2162,2,2013,'0'),(2163,37,2014,'0'),(2164,10,2015,'31'),(2165,11,2015,'Date'),(2166,12,2015,'Desc'),(2167,13,2015,'0'),(2168,4,2018,'Date'),(2169,5,2018,'Desc'),(2170,6,2018,'0'),(2171,51,2019,'0'),(2172,2,2022,'0'),(2173,37,2023,'0'),(2174,10,2024,'31'),(2175,11,2024,'Date'),(2176,12,2024,'Desc'),(2177,13,2024,'0'),(2178,4,2027,'Date'),(2179,5,2027,'Desc'),(2180,6,2027,'0'),(2181,51,2028,'0'),(2182,2,2031,'0'),(2183,37,2032,'0'),(2184,10,2033,'31'),(2185,11,2033,'Date'),(2186,12,2033,'Desc'),(2187,13,2033,'0'),(2188,4,2036,'Date'),(2189,5,2036,'Desc'),(2190,6,2036,'0'),(2191,51,2037,'0'),(2192,2,2040,'0'),(2193,37,2041,'0'),(2194,10,2042,'31'),(2195,11,2042,'Date'),(2196,12,2042,'Desc'),(2197,13,2042,'0'),(2198,4,2045,'Date'),(2199,5,2045,'Desc'),(2200,6,2045,'0'),(2201,51,2046,'0'),(2202,2,2049,'0'),(2203,37,2050,'0'),(2204,10,2051,'31'),(2205,11,2051,'Date'),(2206,12,2051,'Desc'),(2207,13,2051,'0'),(2208,4,2054,'Date'),(2209,5,2054,'Desc'),(2210,6,2054,'0'),(2211,51,2055,'0'),(2212,2,2058,'0'),(2213,37,2059,'0'),(2214,10,2060,'31'),(2215,11,2060,'Date'),(2216,12,2060,'Desc'),(2217,13,2060,'0'),(2218,4,2063,'Date'),(2219,5,2063,'Desc'),(2220,6,2063,'0'),(2221,51,2064,'0'),(2222,2,2067,'0'),(2223,37,2068,'0'),(2224,10,2069,'31'),(2225,11,2069,'Date'),(2226,12,2069,'Desc'),(2227,13,2069,'0'),(2228,4,2072,'Date'),(2229,5,2072,'Desc'),(2230,6,2072,'0'),(2231,51,2073,'0'),(2232,2,2076,'0'),(2233,37,2077,'0'),(2234,10,2078,'31'),(2235,11,2078,'Date'),(2236,12,2078,'Desc'),(2237,13,2078,'0'),(2238,4,2081,'Date'),(2239,5,2081,'Desc'),(2240,6,2081,'0'),(2241,51,2082,'0'),(2242,2,2085,'0'),(2243,37,2086,'0'),(2244,10,2087,'31'),(2245,11,2087,'Date'),(2246,12,2087,'Desc'),(2247,13,2087,'0'),(2248,4,2090,'Date'),(2249,5,2090,'Desc'),(2250,6,2090,'0'),(2251,51,2091,'0'),(2252,2,2094,'0'),(2253,37,2095,'0'),(2254,10,2096,'31'),(2255,11,2096,'Date'),(2256,12,2096,'Desc'),(2257,13,2096,'0'),(2258,4,2099,'Date'),(2259,5,2099,'Desc'),(2260,6,2099,'0'),(2261,51,2100,'0'),(2262,2,2103,'0'),(2263,37,2104,'0'),(2264,10,2105,'31'),(2265,11,2105,'Date'),(2266,12,2105,'Desc'),(2267,13,2105,'0'),(2268,4,2108,'Date'),(2269,5,2108,'Desc'),(2270,6,2108,'0'),(2271,51,2109,'0'),(2272,2,2112,'0'),(2273,37,2113,'0'),(2274,10,2114,'31'),(2275,11,2114,'Date'),(2276,12,2114,'Desc'),(2277,13,2114,'0'),(2278,4,2117,'Date'),(2279,5,2117,'Desc'),(2280,6,2117,'0'),(2281,51,2118,'0'),(2282,2,2121,'0'),(2283,37,2122,'0'),(2284,10,2123,'31'),(2285,11,2123,'Date'),(2286,12,2123,'Desc'),(2287,13,2123,'0'),(2288,4,2126,'Date'),(2289,5,2126,'Desc'),(2290,6,2126,'0'),(2291,51,2127,'0'),(2292,2,2130,'0'),(2293,37,2131,'0'),(2294,10,2132,'31'),(2295,11,2132,'Date'),(2296,12,2132,'Desc'),(2297,13,2132,'0'),(2298,4,2135,'Date'),(2299,5,2135,'Desc'),(2300,6,2135,'0'),(2301,51,2136,'0'),(2302,2,2139,'0'),(2303,37,2140,'0'),(2304,10,2141,'31'),(2305,11,2141,'Date'),(2306,12,2141,'Desc'),(2307,13,2141,'0'),(2308,4,2144,'Date'),(2309,5,2144,'Desc'),(2310,6,2144,'0'),(2311,51,2145,'0'),(2312,2,2148,'0'),(2313,37,2149,'0'),(2314,10,2150,'31'),(2315,11,2150,'Date'),(2316,12,2150,'Desc'),(2317,13,2150,'0'),(2318,4,2153,'Date'),(2319,5,2153,'Desc'),(2320,6,2153,'0'),(2321,51,2154,'0'),(2322,2,2157,'0'),(2323,37,2158,'0'),(2324,10,2159,'31'),(2325,11,2159,'Date'),(2326,12,2159,'Desc'),(2327,13,2159,'0'),(2328,4,2162,'Date'),(2329,5,2162,'Desc'),(2330,6,2162,'0'),(2331,51,2163,'0'),(2332,2,2166,'0'),(2333,37,2167,'0'),(2334,10,2168,'31'),(2335,11,2168,'Date'),(2336,12,2168,'Desc'),(2337,13,2168,'0'),(2338,4,2171,'Date'),(2339,5,2171,'Desc'),(2340,6,2171,'0'),(2341,51,2172,'0'),(2342,2,2175,'0'),(2343,37,2176,'0'),(2344,10,2177,'31'),(2345,11,2177,'Date'),(2346,12,2177,'Desc'),(2347,13,2177,'0'),(2348,4,2180,'Date'),(2349,5,2180,'Desc'),(2350,6,2180,'0'),(2351,51,2181,'0'),(2352,2,2184,'0'),(2353,37,2185,'0'),(2354,10,2186,'31'),(2355,11,2186,'Date'),(2356,12,2186,'Desc'),(2357,13,2186,'0'),(2358,4,2189,'Date'),(2359,5,2189,'Desc'),(2360,6,2189,'0'),(2361,51,2190,'0'),(2362,2,2193,'0'),(2363,37,2194,'0'),(2364,10,2195,'31'),(2365,11,2195,'Date'),(2366,12,2195,'Desc'),(2367,13,2195,'0'),(2368,4,2198,'Date'),(2369,5,2198,'Desc'),(2370,6,2198,'0'),(2371,51,2199,'0'),(2372,2,2202,'0'),(2373,37,2203,'0'),(2374,10,2204,'31'),(2375,11,2204,'Date'),(2376,12,2204,'Desc'),(2377,13,2204,'0'),(2378,4,2207,'Date'),(2379,5,2207,'Desc'),(2380,6,2207,'0'),(2381,51,2208,'0'),(2382,2,2211,'0'),(2383,37,2212,'0'),(2384,10,2213,'31'),(2385,11,2213,'Date'),(2386,12,2213,'Desc'),(2387,13,2213,'0'),(2388,4,2216,'Date'),(2389,5,2216,'Desc'),(2390,6,2216,'0'),(2391,51,2217,'0'),(2392,2,2220,'0'),(2393,37,2221,'0'),(2394,10,2222,'31'),(2395,11,2222,'Date'),(2396,12,2222,'Desc'),(2397,13,2222,'0'),(2398,4,2225,'Date'),(2399,5,2225,'Desc'),(2400,6,2225,'0'),(2401,51,2226,'0'),(2402,2,2229,'0'),(2403,37,2230,'0'),(2404,10,2231,'31'),(2405,11,2231,'Date'),(2406,12,2231,'Desc'),(2407,13,2231,'0'),(2408,4,2234,'Date'),(2409,5,2234,'Desc'),(2410,6,2234,'0'),(2411,51,2235,'0'),(2412,2,2238,'0'),(2413,37,2239,'0'),(2414,10,2240,'31'),(2415,11,2240,'Date'),(2416,12,2240,'Desc'),(2417,13,2240,'0'),(2418,4,2243,'Date'),(2419,5,2243,'Desc'),(2420,6,2243,'0'),(2421,51,2244,'0'),(2422,2,2247,'0'),(2423,37,2248,'0'),(2424,10,2249,'31'),(2425,11,2249,'Date'),(2426,12,2249,'Desc'),(2427,13,2249,'0'),(2428,4,2252,'Date'),(2429,5,2252,'Desc'),(2430,6,2252,'0'),(2431,51,2253,'0'),(2432,2,2256,'0'),(2433,37,2257,'0'),(2434,10,2258,'31'),(2435,11,2258,'Date'),(2436,12,2258,'Desc'),(2437,13,2258,'0'),(2438,4,2261,'Date'),(2439,5,2261,'Desc'),(2440,6,2261,'0'),(2441,51,2262,'0'),(2442,2,2265,'0'),(2443,37,2266,'0'),(2444,10,2267,'31'),(2445,11,2267,'Date'),(2446,12,2267,'Desc'),(2447,13,2267,'0'),(2448,4,2270,'Date'),(2449,5,2270,'Desc'),(2450,6,2270,'0'),(2451,51,2271,'0'),(2452,2,2274,'0'),(2453,37,2275,'0'),(2454,10,2276,'31'),(2455,11,2276,'Date'),(2456,12,2276,'Desc'),(2457,13,2276,'0'),(2458,4,2279,'Date'),(2459,5,2279,'Desc'),(2460,6,2279,'0'),(2461,51,2280,'0'),(2462,2,2283,'0'),(2463,37,2284,'0'),(2464,10,2285,'31'),(2465,11,2285,'Date'),(2466,12,2285,'Desc'),(2467,13,2285,'0'),(2468,4,2288,'Date'),(2469,5,2288,'Desc'),(2470,6,2288,'0'),(2471,51,2289,'0'),(2472,2,2292,'0'),(2473,37,2293,'0'),(2474,10,2294,'31'),(2475,11,2294,'Date'),(2476,12,2294,'Desc'),(2477,13,2294,'0'),(2478,4,2297,'Date'),(2479,5,2297,'Desc'),(2480,6,2297,'0'),(2481,51,2298,'0'),(2482,2,2301,'0'),(2483,37,2302,'0'),(2484,10,2303,'31'),(2485,11,2303,'Date'),(2486,12,2303,'Desc'),(2487,13,2303,'0'),(2488,4,2306,'Date'),(2489,5,2306,'Desc'),(2490,6,2306,'0'),(2491,51,2307,'0'),(2492,2,2310,'0'),(2493,37,2311,'0'),(2494,10,2312,'31'),(2495,11,2312,'Date'),(2496,12,2312,'Desc'),(2497,13,2312,'0'),(2498,4,2315,'Date'),(2499,5,2315,'Desc'),(2500,6,2315,'0'),(2501,51,2316,'0'),(2502,2,2319,'0'),(2503,37,2320,'0'),(2504,10,2321,'31'),(2505,11,2321,'Date'),(2506,12,2321,'Desc'),(2507,13,2321,'0'),(2508,4,2324,'Date'),(2509,5,2324,'Desc'),(2510,6,2324,'0'),(2511,51,2325,'0'),(2512,2,2328,'0'),(2513,37,2329,'0'),(2514,10,2330,'31'),(2515,11,2330,'Date'),(2516,12,2330,'Desc'),(2517,13,2330,'0'),(2518,4,2333,'Date'),(2519,5,2333,'Desc'),(2520,6,2333,'0'),(2521,51,2334,'0'),(2522,2,2337,'0'),(2523,37,2338,'0'),(2524,10,2339,'31'),(2525,11,2339,'Date'),(2526,12,2339,'Desc'),(2527,13,2339,'0'),(2528,4,2342,'Date'),(2529,5,2342,'Desc'),(2530,6,2342,'0'),(2531,51,2343,'0'),(2532,2,2346,'0'),(2533,37,2347,'0'),(2534,10,2348,'31'),(2535,11,2348,'Date'),(2536,12,2348,'Desc'),(2537,13,2348,'0'),(2538,4,2351,'Date'),(2539,5,2351,'Desc'),(2540,6,2351,'0'),(2541,51,2352,'0'),(2542,2,2355,'0'),(2543,37,2356,'0'),(2544,10,2357,'31'),(2545,11,2357,'Date'),(2546,12,2357,'Desc'),(2547,13,2357,'0'),(2548,4,2360,'Date'),(2549,5,2360,'Desc'),(2550,6,2360,'0'),(2551,51,2361,'0'),(2552,2,2364,'0'),(2553,37,2365,'0'),(2554,10,2366,'31'),(2555,11,2366,'Date'),(2556,12,2366,'Desc'),(2557,13,2366,'0'),(2558,4,2369,'Date'),(2559,5,2369,'Desc'),(2560,6,2369,'0'),(2561,51,2370,'0'),(2562,2,2373,'0'),(2563,37,2374,'0'),(2564,10,2375,'31'),(2565,11,2375,'Date'),(2566,12,2375,'Desc'),(2567,13,2375,'0'),(2568,4,2378,'Date'),(2569,5,2378,'Desc'),(2570,6,2378,'0'),(2571,51,2379,'0'),(2572,2,2382,'0'),(2573,37,2383,'0'),(2574,10,2384,'31'),(2575,11,2384,'Date'),(2576,12,2384,'Desc'),(2577,13,2384,'0'),(2578,4,2387,'Date'),(2579,5,2387,'Desc'),(2580,6,2387,'0'),(2581,51,2388,'0'),(2582,2,2391,'0'),(2583,37,2392,'0'),(2584,10,2393,'31'),(2585,11,2393,'Date'),(2586,12,2393,'Desc'),(2587,13,2393,'0'),(2588,4,2396,'Date'),(2589,5,2396,'Desc'),(2590,6,2396,'0'),(2591,51,2397,'0'),(2592,2,2400,'0'),(2593,37,2401,'0'),(2594,10,2402,'31'),(2595,11,2402,'Date'),(2596,12,2402,'Desc'),(2597,13,2402,'0'),(2598,4,2405,'Date'),(2599,5,2405,'Desc'),(2600,6,2405,'0'),(2601,51,2406,'0'),(2602,2,2409,'0'),(3767,60,3163,'3'),(3765,71,3162,'2015-05-31'),(3764,70,3162,'00:00:00'),(3762,52,3158,'52'),(2608,4,2414,'Date'),(2609,5,2414,'Desc'),(2610,6,2414,'0'),(3766,59,3163,'Principals Blog'),(2612,2,2418,'0'),(2613,37,2419,'0'),(3757,19,3153,'0'),(3756,18,3153,'Threads'),(3755,52,3151,'48'),(2618,4,2423,'Date'),(2619,5,2423,'Desc'),(2620,6,2423,'0'),(2621,51,2424,'0'),(2622,2,2427,'0'),(3763,69,3162,'Next Ride'),(2624,10,2429,'31'),(2625,11,2429,'Date'),(2626,12,2429,'Desc'),(2627,13,2429,'0'),(2628,4,2432,'Date'),(2629,5,2432,'Desc'),(2630,6,2432,'0'),(2632,2,2436,'0'),(2633,37,2437,'0'),(2634,10,2438,'31'),(2635,11,2438,'Date'),(2636,12,2438,'Desc'),(2637,13,2438,'0'),(2638,4,2441,'Date'),(2639,5,2441,'Desc'),(2640,6,2441,'0'),(2641,51,2442,'0'),(2642,2,2445,'0'),(2643,37,2446,'0'),(2644,10,2447,'31'),(2645,11,2447,'Date'),(2646,12,2447,'Desc'),(2647,13,2447,'0'),(2648,4,2450,'Date'),(2649,5,2450,'Desc'),(2650,6,2450,'0'),(2651,51,2451,'0'),(2652,2,2454,'0'),(2653,37,2455,'0'),(2654,10,2456,'31'),(2655,11,2456,'Date'),(2656,12,2456,'Desc'),(2657,13,2456,'0'),(2658,4,2459,'Date'),(2659,5,2459,'Desc'),(2660,6,2459,'0'),(2661,51,2460,'0'),(2662,2,2463,'0'),(2663,37,2464,'0'),(2664,10,2465,'31'),(2665,11,2465,'Date'),(2666,12,2465,'Desc'),(2667,13,2465,'0'),(2668,4,2468,'Date'),(2669,5,2468,'Desc'),(2670,6,2468,'0'),(2671,51,2469,'0'),(2672,2,2472,'0'),(2673,37,2473,'0'),(2674,10,2474,'31'),(2675,11,2474,'Date'),(2676,12,2474,'Desc'),(2677,13,2474,'0'),(2678,4,2477,'Date'),(2679,5,2477,'Desc'),(2680,6,2477,'0'),(2681,51,2478,'0'),(2682,2,2481,'0'),(2683,37,2482,'0'),(2684,10,2483,'31'),(2685,11,2483,'Date'),(2686,12,2483,'Desc'),(2687,13,2483,'0'),(2688,4,2486,'Date'),(2689,5,2486,'Desc'),(2690,6,2486,'0'),(2691,51,2487,'0'),(2692,2,2490,'0'),(2693,37,2491,'0'),(2694,10,2492,'31'),(2695,11,2492,'Date'),(2696,12,2492,'Desc'),(2697,13,2492,'0'),(2698,4,2495,'Date'),(2699,5,2495,'Desc'),(2700,6,2495,'0'),(2701,51,2496,'0'),(2702,2,2499,'0'),(2703,37,2500,'0'),(2704,10,2501,'31'),(2705,11,2501,'Date'),(2706,12,2501,'Desc'),(2707,13,2501,'0'),(2708,4,2504,'Date'),(2709,5,2504,'Desc'),(2710,6,2504,'0'),(2711,51,2505,'0'),(2712,2,2508,'0'),(2713,37,2509,'0'),(2714,10,2510,'31'),(2715,11,2510,'Date'),(2716,12,2510,'Desc'),(2717,13,2510,'0'),(2718,4,2513,'Date'),(2719,5,2513,'Desc'),(2720,6,2513,'0'),(2721,51,2514,'0'),(2722,2,2517,'0'),(2723,37,2518,'0'),(2724,10,2519,'31'),(2725,11,2519,'Date'),(2726,12,2519,'Desc'),(2727,13,2519,'0'),(2728,4,2522,'Date'),(2729,5,2522,'Desc'),(2730,6,2522,'0'),(2731,51,2523,'0'),(2732,2,2526,'0'),(2733,37,2527,'0'),(2734,10,2528,'31'),(2735,11,2528,'Date'),(2736,12,2528,'Desc'),(2737,13,2528,'0'),(2738,4,2531,'Date'),(2739,5,2531,'Desc'),(2740,6,2531,'0'),(2741,51,2532,'0'),(2742,2,2535,'0'),(2743,37,2536,'0'),(2744,10,2537,'31'),(2745,11,2537,'Date'),(2746,12,2537,'Desc'),(2747,13,2537,'0'),(2748,4,2540,'Date'),(2749,5,2540,'Desc'),(2750,6,2540,'0'),(2751,51,2541,'0'),(2752,2,2544,'0'),(2753,37,2545,'0'),(2754,10,2546,'31'),(2755,11,2546,'Date'),(2756,12,2546,'Desc'),(2757,13,2546,'0'),(2758,4,2549,'Date'),(2759,5,2549,'Desc'),(2760,6,2549,'0'),(2761,51,2550,'0'),(2762,2,2553,'0'),(2763,37,2554,'0'),(2764,10,2555,'31'),(2765,11,2555,'Date'),(2766,12,2555,'Desc'),(2767,13,2555,'0'),(2768,4,2558,'Date'),(2769,5,2558,'Desc'),(2770,6,2558,'0'),(2771,51,2559,'0'),(2772,2,2562,'0'),(2773,37,2563,'0'),(2774,10,2564,'31'),(2775,11,2564,'Date'),(2776,12,2564,'Desc'),(2777,13,2564,'0'),(2778,4,2567,'Date'),(2779,5,2567,'Desc'),(2780,6,2567,'0'),(2781,51,2568,'0'),(2782,2,2571,'0'),(2783,37,2572,'0'),(2784,10,2573,'31'),(2785,11,2573,'Date'),(2786,12,2573,'Desc'),(2787,13,2573,'0'),(2788,4,2576,'Date'),(2789,5,2576,'Desc'),(2790,6,2576,'0'),(2791,51,2577,'0'),(2792,2,2580,'0'),(2793,37,2581,'0'),(2794,10,2582,'31'),(2795,11,2582,'Date'),(2796,12,2582,'Desc'),(2797,13,2582,'0'),(2798,4,2585,'Date'),(2799,5,2585,'Desc'),(2800,6,2585,'0'),(2801,51,2586,'0'),(2802,2,2589,'0'),(2803,37,2590,'0'),(2804,10,2591,'31'),(2805,11,2591,'Date'),(2806,12,2591,'Desc'),(2807,13,2591,'0'),(2808,4,2594,'Date'),(2809,5,2594,'Desc'),(2810,6,2594,'0'),(2811,51,2595,'0'),(2812,2,2598,'0'),(2813,37,2599,'0'),(2814,10,2600,'31'),(2815,11,2600,'Date'),(2816,12,2600,'Desc'),(2817,13,2600,'0'),(2818,4,2603,'Date'),(2819,5,2603,'Desc'),(2820,6,2603,'0'),(2821,51,2604,'0'),(2822,2,2607,'0'),(2823,37,2608,'0'),(2824,10,2609,'31'),(2825,11,2609,'Date'),(2826,12,2609,'Desc'),(2827,13,2609,'0'),(2828,4,2612,'Date'),(2829,5,2612,'Desc'),(2830,6,2612,'0'),(2831,51,2613,'0'),(2832,2,2616,'0'),(2833,37,2617,'0'),(2834,10,2618,'31'),(2835,11,2618,'Date'),(2836,12,2618,'Desc'),(2837,13,2618,'0'),(2838,4,2621,'Date'),(2839,5,2621,'Desc'),(2840,6,2621,'0'),(2841,51,2622,'0'),(2842,2,2625,'0'),(2843,37,2626,'0'),(2844,10,2627,'31'),(2845,11,2627,'Date'),(2846,12,2627,'Desc'),(2847,13,2627,'0'),(2848,4,2630,'Date'),(2849,5,2630,'Desc'),(2850,6,2630,'0'),(2851,51,2631,'0'),(2852,2,2634,'0'),(2853,37,2635,'0'),(2854,10,2636,'31'),(2855,11,2636,'Date'),(2856,12,2636,'Desc'),(2857,13,2636,'0'),(2858,4,2639,'Date'),(2859,5,2639,'Desc'),(2860,6,2639,'0'),(2861,51,2640,'0'),(2862,2,2643,'0'),(2863,37,2644,'0'),(2864,10,2645,'31'),(2865,11,2645,'Date'),(2866,12,2645,'Desc'),(2867,13,2645,'0'),(2868,4,2648,'Date'),(2869,5,2648,'Desc'),(2870,6,2648,'0'),(2871,51,2649,'0'),(2872,2,2652,'0'),(2873,37,2653,'0'),(2874,10,2654,'31'),(2875,11,2654,'Date'),(2876,12,2654,'Desc'),(2877,13,2654,'0'),(2878,4,2657,'Date'),(2879,5,2657,'Desc'),(2880,6,2657,'0'),(2881,51,2658,'0'),(2882,2,2661,'0'),(2883,37,2662,'0'),(2884,10,2663,'31'),(2885,11,2663,'Date'),(2886,12,2663,'Desc'),(2887,13,2663,'0'),(2888,4,2666,'Date'),(2889,5,2666,'Desc'),(2890,6,2666,'0'),(2891,51,2667,'0'),(2892,2,2670,'0'),(2893,37,2671,'0'),(2894,10,2672,'31'),(2895,11,2672,'Date'),(2896,12,2672,'Desc'),(2897,13,2672,'0'),(2898,4,2675,'Date'),(2899,5,2675,'Desc'),(2900,6,2675,'0'),(2901,51,2676,'0'),(2902,2,2679,'0'),(2903,37,2680,'0'),(2904,10,2681,'31'),(2905,11,2681,'Date'),(2906,12,2681,'Desc'),(2907,13,2681,'0'),(2908,4,2684,'Date'),(2909,5,2684,'Desc'),(2910,6,2684,'0'),(2911,51,2685,'0'),(2912,2,2688,'0'),(2913,37,2689,'0'),(2914,10,2690,'31'),(2915,11,2690,'Date'),(2916,12,2690,'Desc'),(2917,13,2690,'0'),(2918,4,2693,'Date'),(2919,5,2693,'Desc'),(2920,6,2693,'0'),(2921,51,2694,'0'),(2922,2,2697,'0'),(2923,37,2698,'0'),(2924,10,2699,'31'),(2925,11,2699,'Date'),(2926,12,2699,'Desc'),(2927,13,2699,'0'),(2928,4,2702,'Date'),(2929,5,2702,'Desc'),(2930,6,2702,'0'),(2931,51,2703,'0'),(2932,2,2706,'0'),(2933,37,2707,'0'),(2934,10,2708,'31'),(2935,11,2708,'Date'),(2936,12,2708,'Desc'),(2937,13,2708,'0'),(2938,4,2711,'Date'),(2939,5,2711,'Desc'),(2940,6,2711,'0'),(2941,51,2712,'0'),(2942,2,2715,'0'),(2943,37,2716,'0'),(2944,10,2717,'31'),(2945,11,2717,'Date'),(2946,12,2717,'Desc'),(2947,13,2717,'0'),(2948,4,2720,'Date'),(2949,5,2720,'Desc'),(2950,6,2720,'0'),(2951,51,2721,'0'),(2952,2,2724,'0'),(2953,37,2725,'0'),(3273,28,2900,'Unit 1 - Areas of Study'),(3272,29,2900,'1'),(2958,4,2729,'Date'),(2959,5,2729,'Desc'),(2960,6,2729,'0'),(2961,51,2730,'0'),(2962,2,2733,'0'),(2963,37,2734,'0'),(2964,10,2735,'31'),(2965,11,2735,'Date'),(2966,12,2735,'Desc'),(2967,13,2735,'0'),(2968,4,2738,'Date'),(2969,5,2738,'Desc'),(2970,6,2738,'0'),(2971,51,2739,'0'),(3472,15,3005,'1'),(3470,12,2997,'asc'),(3469,11,2997,'date'),(3468,13,2997,'0'),(3467,10,2997,'31'),(3466,14,2997,'Policies'),(3465,15,2997,'1'),(3471,16,2997,'425'),(3473,14,3005,'General Parent Forms'),(2984,44,2753,'6'),(2985,1,2755,NULL),(2986,2,2755,NULL),(2987,3,2755,NULL),(2988,38,2756,NULL),(2989,37,2756,NULL),(2990,15,2757,NULL),(2991,14,2757,NULL),(2992,10,2757,NULL),(2993,13,2757,NULL),(2994,11,2757,NULL),(2995,12,2757,NULL),(2996,16,2757,NULL),(2997,42,2758,NULL),(2998,7,2760,NULL),(2999,8,2760,NULL),(3000,6,2760,NULL),(3001,4,2760,NULL),(3002,5,2760,NULL),(3003,9,2760,NULL),(3004,51,2761,NULL),(3256,5,2891,'asc'),(3255,4,2891,'Date'),(3254,6,2891,'0'),(3253,8,2891,'Useful Course Links'),(3252,7,2891,'1'),(3250,34,2890,NULL),(3248,52,2889,'16'),(3018,7,2769,'1'),(3019,8,2769,'Links'),(3020,6,2769,'0'),(3021,4,2769,'Date'),(3022,5,2769,'asc'),(3023,9,2769,''),(3257,9,2891,''),(3025,1,2773,NULL),(3026,2,2773,NULL),(3027,3,2773,NULL),(3028,38,2774,NULL),(3029,37,2774,NULL),(3030,15,2775,NULL),(3031,14,2775,NULL),(3032,10,2775,NULL),(3033,13,2775,NULL),(3034,11,2775,NULL),(3035,12,2775,NULL),(3036,16,2775,NULL),(3037,42,2776,NULL),(3038,7,2778,NULL),(3039,8,2778,NULL),(3040,6,2778,NULL),(3041,4,2778,NULL),(3042,5,2778,NULL),(3043,9,2778,NULL),(3044,51,2779,NULL),(3238,7,2883,NULL),(3237,35,2882,NULL),(3236,34,2882,NULL),(3235,33,2882,NULL),(3234,52,2881,'49'),(3233,52,2880,'13'),(3232,52,2879,'12'),(3244,44,2884,'7'),(3243,9,2883,NULL),(3242,5,2883,NULL),(3241,4,2883,NULL),(3240,6,2883,NULL),(3239,8,2883,NULL),(3245,44,2885,'8'),(3247,52,2888,'15'),(3246,52,2887,NULL),(3065,1,2791,NULL),(3066,2,2791,NULL),(3067,3,2791,NULL),(3068,38,2792,NULL),(3069,37,2792,NULL),(3070,15,2793,NULL),(3071,14,2793,NULL),(3072,10,2793,NULL),(3073,13,2793,NULL),(3074,11,2793,NULL),(3075,12,2793,NULL),(3076,16,2793,NULL),(3077,42,2794,NULL),(3078,7,2796,NULL),(3079,8,2796,NULL),(3080,6,2796,NULL),(3081,4,2796,NULL),(3082,5,2796,NULL),(3083,9,2796,NULL),(3084,51,2797,NULL),(3215,28,2857,'Unit 2 - Areas of study'),(3214,29,2857,'0'),(3089,52,2801,'7'),(3090,29,2802,'1'),(3091,28,2802,'Unit 1 - Areas of study'),(3092,27,2802,'0'),(3093,30,2802,'437'),(3094,1,2803,NULL),(3095,2,2803,NULL),(3096,3,2803,NULL),(3097,38,2804,NULL),(3098,37,2804,NULL),(3099,15,2805,NULL),(3100,14,2805,NULL),(3101,10,2805,NULL),(3102,13,2805,NULL),(3103,11,2805,NULL),(3104,12,2805,NULL),(3105,16,2805,NULL),(3106,42,2806,NULL),(3107,7,2808,NULL),(3108,8,2808,NULL),(3109,6,2808,NULL),(3110,4,2808,NULL),(3111,5,2808,NULL),(3112,9,2808,NULL),(3113,51,2809,NULL),(3476,11,3005,'date'),(3475,13,3005,'0'),(3474,10,3005,'31'),(3119,15,2814,'1'),(3120,14,2814,'Files'),(3121,10,2814,'31'),(3122,13,2814,'0'),(3123,11,2814,'date'),(3124,12,2814,'asc'),(3125,16,2814,''),(3457,52,2987,'33'),(3460,52,2990,'35'),(3459,68,2989,'1'),(3458,52,2988,'34'),(3464,52,2994,'39'),(3463,52,2993,'38'),(3461,52,2991,'36'),(3139,15,2823,NULL),(3140,14,2823,NULL),(3141,10,2823,NULL),(3142,13,2823,NULL),(3143,11,2823,NULL),(3144,12,2823,NULL),(3145,16,2823,NULL),(3147,7,2826,NULL),(3148,8,2826,NULL),(3149,6,2826,NULL),(3150,4,2826,NULL),(3151,5,2826,NULL),(3152,9,2826,NULL),(3462,52,2992,'37'),(3174,1,2839,NULL),(3175,2,2839,NULL),(3176,3,2839,NULL),(3177,38,2840,NULL),(3178,37,2840,NULL),(3179,15,2841,NULL),(3180,14,2841,NULL),(3181,10,2841,NULL),(3182,13,2841,NULL),(3183,11,2841,NULL),(3184,12,2841,NULL),(3185,16,2841,NULL),(3186,42,2842,NULL),(3187,7,2844,NULL),(3188,8,2844,NULL),(3189,6,2844,NULL),(3190,4,2844,NULL),(3191,5,2844,NULL),(3192,9,2844,NULL),(3193,51,2845,NULL),(3194,1,2848,NULL),(3195,2,2848,NULL),(3196,3,2848,NULL),(3197,38,2849,NULL),(3198,37,2849,NULL),(3199,15,2850,NULL),(3200,14,2850,NULL),(3201,10,2850,NULL),(3202,13,2850,NULL),(3203,11,2850,NULL),(3204,12,2850,NULL),(3205,16,2850,NULL),(3206,42,2851,NULL),(3207,7,2853,NULL),(3208,8,2853,NULL),(3209,6,2853,NULL),(3210,4,2853,NULL),(3211,5,2853,NULL),(3212,9,2853,NULL),(3213,51,2854,NULL),(3216,27,2857,'0'),(3217,30,2857,'439'),(3218,29,2858,'0'),(3219,28,2858,'Unit 3 - Areas of study'),(3220,27,2858,'0'),(3221,30,2858,'441'),(3258,44,2892,NULL),(3230,52,2864,'10'),(3229,52,2863,'9'),(3228,52,2862,'8'),(3259,52,2893,'14'),(3455,52,2983,'31'),(3261,52,2895,'28'),(3262,33,2896,NULL),(3263,34,2896,NULL),(3264,35,2896,NULL),(3265,7,2897,NULL),(3266,8,2897,NULL),(3267,6,2897,NULL),(3268,4,2897,NULL),(3269,5,2897,NULL),(3270,9,2897,NULL),(3271,44,2898,NULL),(3274,27,2900,'0'),(3275,30,2900,'461'),(3276,15,2899,'1'),(3277,14,2899,'Sample scope documents'),(3278,10,2899,'31'),(3279,13,2899,'0'),(3280,11,2899,'date'),(3281,12,2899,'asc'),(3282,16,2899,''),(3572,52,3078,'41'),(3284,52,2903,'18'),(3285,52,2904,'19'),(3311,52,2917,'20'),(3317,7,2921,NULL),(3324,52,2924,'23'),(3313,52,2919,'22'),(3312,52,2918,'21'),(3330,3,2926,NULL),(3329,2,2926,NULL),(3328,1,2926,NULL),(3323,44,2922,'9'),(3322,9,2921,NULL),(3321,5,2921,NULL),(3320,4,2921,NULL),(3319,6,2921,NULL),(3318,8,2921,NULL),(3360,52,2939,'26'),(3359,52,2938,'25'),(3358,65,2937,'2'),(3357,67,2937,'470'),(3354,64,2937,'Active'),(3340,17,2930,NULL),(3356,63,2937,'400'),(3355,66,2937,'2013 Tennis Photos'),(3353,52,2936,'24'),(3347,7,2933,'1'),(3348,8,2933,'Links'),(3349,6,2933,'0'),(3350,4,2933,'Date'),(3351,5,2933,'asc'),(3352,9,2933,''),(3781,28,3188,'Units'),(3371,35,2935,'470'),(3372,29,223,'0'),(3373,28,223,'Our School Houses'),(3374,30,223,''),(3375,33,2949,NULL),(3376,34,2949,NULL),(3377,35,2949,NULL),(3378,1,2950,NULL),(3379,2,2950,NULL),(3380,3,2950,NULL),(3381,15,2951,NULL),(3382,14,2951,NULL),(3383,10,2951,NULL),(3384,13,2951,NULL),(3385,11,2951,NULL),(3386,12,2951,NULL),(3387,16,2951,NULL),(3573,52,3077,'42'),(3389,31,2953,NULL),(3390,17,2954,NULL),(3391,21,2955,NULL),(3392,20,2955,NULL),(3393,19,2955,NULL),(3394,18,2955,NULL),(3395,22,2955,NULL),(3396,44,2956,NULL),(3397,7,2957,NULL),(3398,8,2957,NULL),(3399,6,2957,NULL),(3400,4,2957,NULL),(3401,5,2957,NULL),(3402,9,2957,NULL),(3403,52,2959,'27'),(3426,3,2968,NULL),(3425,2,2968,NULL),(3424,1,2968,NULL),(3423,30,2967,'450'),(3422,27,2967,'0'),(3421,28,2967,'Moderated Groups'),(3420,29,2967,'0'),(3416,29,2965,'0'),(3417,28,2965,'Free Groups'),(3418,27,2965,'0'),(3419,30,2965,'449'),(3452,77,2978,'#macroscopic'),(3451,76,2978,''),(3454,52,2982,'30'),(3433,15,2970,NULL),(3434,14,2970,NULL),(3435,10,2970,NULL),(3436,13,2970,NULL),(3437,11,2970,NULL),(3438,12,2970,NULL),(3439,16,2970,NULL),(3440,17,2971,NULL),(3441,31,2972,NULL),(3442,21,2973,NULL),(3443,20,2973,NULL),(3444,19,2973,NULL),(3445,18,2973,NULL),(3446,22,2973,NULL),(3447,51,2974,NULL),(3453,78,2978,'3'),(3456,52,2984,'32'),(3477,12,3005,'asc'),(3478,16,3005,'478'),(3479,52,3007,'40'),(3480,102,3019,'Values and Beliefs'),(3481,103,3019,'Describe what is important to you and what you believe in'),(3482,98,3020,'My Favourite Things'),(3483,99,3020,'Enter a list of your most important possessions, provide photos if you would like'),(3484,98,3021,'Places I\'ve been'),(3485,99,3021,'Enter a list of all your holiday destinations and travel'),(3486,96,3022,'Co-curricular Activities'),(3487,97,3022,'List all your sports, clubs and camps that you\'re involved with'),(3488,96,3023,'My Hobbies'),(3489,97,3023,'Provide a list of all your interests and hobbies that you undertake in your spare time'),(3490,108,3024,'My Career Aspirations'),(3491,109,3024,'Provide a regular personal journal detailing what you would like to achieve in the future and how you will achieve your goals'),(3492,96,3025,'My Goals'),(3493,97,3025,'List your career goals and the steps required to achieve your dream career'),(3494,106,3026,'Images that inspire me'),(3495,107,3026,'Post some images that represent the career that you aspire to and what you would like to achieve'),(3496,102,3027,'Introduction'),(3497,103,3027,'Provide a brief overview of who you are and skills that you believe you possess'),(3498,102,3028,'My Dream Job'),(3499,103,3028,'Explain what it is you are looking for in the perfect job'),(3500,106,3029,'Photos of me'),(3501,107,3029,'Post some photos that represent you and your interests'),(3502,102,3030,'Work Experience'),(3503,103,3030,'Enter any work experience that you have completed, for example babysitting or mowing the lawns'),(3504,96,3031,'Hobbies'),(3505,97,3031,'Provide a list of all your interests and hobbies that you undertake in your spare time '),(3506,108,3032,'My Reflections Blog'),(3507,109,3032,'Provide a regular update on your work and school life'),(3508,102,3033,'My Goals'),(3509,103,3033,'List your goals that you would like to achieve, both in the short term and long term'),(3510,98,3034,'Awards and Achievements'),(3511,99,3034,'As you achieve your goals or are recognised for your efforts store a list here'),(3512,102,3035,'Values and Beliefs'),(3513,103,3035,'Describe what is important to you and what you believe in'),(3514,98,3036,'My Favourite Things'),(3515,99,3036,'Enter a list of your most important possessions, provide photos if you would like'),(3516,98,3037,'Places I\'ve been'),(3517,99,3037,'Enter a list of all your holiday destinations and travel'),(3518,96,3038,'Co-curricular Activities'),(3519,97,3038,'List all your sports, clubs and camps that you\'re involved with'),(3520,96,3039,'My Hobbies'),(3521,97,3039,'Provide a list of all your interests and hobbies that you undertake in your spare time'),(3522,102,3040,'test'),(3523,102,3041,'Values and Beliefs'),(3524,103,3041,'Describe what is important to you and what you believe in'),(3525,98,3042,'My Favourite Things'),(3526,99,3042,'Enter a list of your most important possessions, provide photos if you would like'),(3527,98,3043,'Places I\'ve been'),(3528,99,3043,'Enter a list of all your holiday destinations and travel'),(3529,96,3044,'Co-curricular Activities'),(3530,97,3044,'List all your sports, clubs and camps that you\'re involved with'),(3531,96,3045,'My Hobbies'),(3532,97,3045,'Provide a list of all your interests and hobbies that you undertake in your spare time'),(3533,102,3046,'Values and Beliefs'),(3534,103,3046,'Describe what is important to you and what you believe in'),(3535,98,3047,'My Favourite Things'),(3536,99,3047,'Enter a list of your most important possessions, provide photos if you would like'),(3537,98,3048,'Places I\'ve been'),(3538,99,3048,'Enter a list of all your holiday destinations and travel'),(3539,96,3049,'Co-curricular Activities'),(3540,97,3049,'List all your sports, clubs and camps that you\'re involved with'),(3541,96,3050,'My Hobbies'),(3542,97,3050,'Provide a list of all your interests and hobbies that you undertake in your spare time'),(3543,102,3053,'Award'),(3544,102,3054,'Cover Letter'),(3545,102,3055,'Goal'),(3546,102,3056,'My portfolio'),(3547,102,3057,'Values and Beliefs'),(3548,103,3057,'Describe what is important to you and what you believe in'),(3549,98,3058,'My Favourite Things'),(3550,99,3058,'Enter a list of your most important possessions, provide photos if you would like'),(3551,98,3059,'Places I\'ve been'),(3552,99,3059,'Enter a list of all your holiday destinations and travel'),(3553,96,3060,'Co-curricular Activities'),(3554,97,3060,'List all your sports, clubs and camps that you\'re involved with'),(3555,96,3061,'My Hobbies'),(3556,97,3061,'Provide a list of all your interests and hobbies that you undertake in your spare time'),(3557,102,3062,'Values and Beliefs'),(3558,103,3062,'Describe what is important to you and what you believe in'),(3559,98,3063,'My Favourite Things'),(3560,99,3063,'Enter a list of your most important possessions, provide photos if you would like'),(3561,98,3064,'Places I\'ve been'),(3562,99,3064,'Enter a list of all your holiday destinations and travel'),(3563,96,3065,'Co-curricular Activities'),(3564,97,3065,'List all your sports, clubs and camps that you\'re involved with'),(3565,96,3066,'My Hobbies'),(3566,97,3066,'Provide a list of all your interests and hobbies that you undertake in your spare time'),(3567,102,3070,'Award'),(3568,102,3071,'Cover Letter'),(3569,102,3072,'Goal'),(3570,102,3073,'Teacher Notes'),(3571,102,3074,'My portfolio'),(3574,52,3076,'43'),(3780,91,4,'1'),(3783,30,3188,''),(3782,27,3188,'0'),(3585,52,3085,'44'),(3586,52,3084,'45'),(3587,76,3086,''),(3588,77,3086,'chemistry'),(3589,78,3086,'4'),(3590,52,3091,'46'),(3653,9,3111,NULL),(3652,5,3111,NULL),(3651,4,3111,NULL),(3650,6,3111,NULL),(3649,8,3111,NULL),(3648,7,3111,NULL),(3671,25,3124,NULL),(3672,1,3125,NULL),(3677,10,3126,NULL),(3678,13,3126,NULL),(3669,26,3124,NULL),(3657,52,3116,'47'),(3656,35,3115,'487'),(3655,34,3115,'0'),(3670,24,3124,NULL),(3668,94,3124,NULL),(3654,33,3115,'Year 9 Saturday sport photos'),(3679,11,3126,NULL),(3680,12,3126,NULL),(3681,16,3126,NULL),(3682,23,3127,NULL),(3683,28,3128,NULL),(3684,27,3128,NULL),(3685,30,3128,NULL),(3686,7,3129,NULL),(3687,8,3129,NULL),(3688,6,3129,NULL),(3689,4,3129,NULL),(3690,5,3129,NULL),(3691,9,3129,NULL),(3697,24,3132,NULL),(3696,26,3132,NULL),(3695,94,3132,NULL),(3698,25,3132,NULL),(3699,1,3133,NULL),(3700,2,3133,NULL),(3701,3,3133,NULL),(3702,15,3134,NULL),(3703,14,3134,NULL),(3704,10,3134,NULL),(3705,13,3134,NULL),(3706,11,3134,NULL),(3707,12,3134,NULL),(3708,16,3134,NULL),(3709,23,3135,NULL),(3710,28,3136,NULL),(3711,27,3136,NULL),(3712,30,3136,NULL),(3713,7,3137,NULL),(3714,8,3137,NULL),(3715,6,3137,NULL),(3716,4,3137,NULL),(3717,5,3137,NULL),(3718,9,3137,NULL),(3719,28,3139,'Unit 2 resources'),(3720,27,3139,'0'),(3721,30,3139,'497'),(3722,10,3140,'31'),(3723,11,3140,'date'),(3724,12,3140,'desc'),(3725,13,3140,'0'),(3726,14,3140,'Shared images'),(3727,15,3140,'1'),(3728,16,3140,'496'),(3761,52,3155,'51'),(3760,52,3154,'50'),(3759,22,3153,''),(3733,1,3142,NULL),(3734,2,3142,NULL),(3735,3,3142,NULL),(3758,20,3153,'Collaboration Forum'),(3754,3,1572,'499'),(3753,1,1572,'News'),(3743,23,3144,NULL),(3744,28,3145,NULL),(3745,27,3145,NULL),(3746,30,3145,NULL),(3747,7,3146,NULL),(3748,8,3146,NULL),(3749,6,3146,NULL),(3750,4,3146,NULL),(3751,5,3146,NULL),(3752,9,3146,NULL),(3768,62,3163,''),(3776,52,3168,'53'),(3777,84,5,'http://www.abc.net.au/news/indexes/justin/rss.xml'),(3778,85,5,'4'),(3779,92,5,'0'),(3850,192,89,'0'),(3851,194,52,'0'),(3852,194,60,'0'),(3853,194,77,'0'),(3854,194,85,'0'),(3855,201,3183,'0'),(3856,202,3182,'0'),(3857,203,3176,'0'),(3858,204,3179,'0'),(3859,206,3185,'0'),(3860,206,3178,'0'),(3861,206,50,'0'),(3862,206,58,'0'),(3863,206,68,'0'),(3864,206,75,'0'),(3865,206,83,'0'),(3866,206,3174,'0'),(3867,207,3188,'0'),(3868,207,70,'0'),(3869,208,53,'0'),(3870,208,62,'0'),(3871,208,78,'0'),(3872,208,86,'0'),(3873,209,69,'0'),(3874,210,51,'0'),(3875,210,61,'0'),(3876,210,76,'0'),(3877,210,84,'0'),(3878,211,54,'0'),(3879,211,59,'0'),(3880,211,79,'0'),(3881,211,87,'0'),(3882,212,66,'0'),(3883,212,3175,'0'),(3884,213,56,'0'),(3885,215,2876,'0'),(3886,215,2868,'0'),(3887,215,64,'0'),(3888,215,71,'0'),(3889,215,91,'0'),(3890,215,3186,'0'),(3891,216,3177,'0'),(3892,216,48,'0'),(3893,216,57,'0'),(3894,216,67,'0'),(3895,216,73,'0'),(3896,216,81,'0'),(3897,219,63,'0'),(3898,219,90,'0'),(3899,225,2873,'0'),(3900,225,2869,'0'),(3901,225,3184,'0'),(3902,229,49,'0'),(3903,229,74,'0'),(3904,229,82,'0');
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
  UNIQUE KEY `class` (`class`),
  KEY `module` (`module`)
) ENGINE=MyISAM AUTO_INCREMENT=87 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_definition`
--

LOCK TABLES `component_definition` WRITE;
/*!40000 ALTER TABLE `component_definition` DISABLE KEYS */;
INSERT INTO `component_definition` (`id`, `name`, `class`, `description`, `module`, `component_category`, `visible`, `multiple`) VALUES (1,'Timetable','Component_Dashboard_TimetableController','','dashboard',NULL,'true','false'),(2,'Greeting','Component_Dashboard_GreetingController','','dashboard',NULL,'true','false'),(3,'Calendar','Component_Dashboard_CalendarController','','dashboard',NULL,'true','false'),(4,'News','Component_Dashboard_NewsController','','dashboard',NULL,'true','false'),(5,'Weather','Component_Dashboard_WeatherController','','dashboard',NULL,'true','false'),(6,'What\'s New','Component_Dashboard_ActivityController','','dashboard',NULL,'true','false'),(7,'Child Subject','Component_Dashboard_ChildSubjectController','','dashboard',NULL,'true','false'),(13,'RSS','Component_Dashboard_RssController','','dashboard',NULL,'true','true'),(18,'Upcoming Due Work','Component_Dashboard_UpcomingWorkController','','dashboard',NULL,'true','false'),(19,'Recent Submissions','Component_Dashboard_RecentSubmissionsController','','dashboard',NULL,'true','false'),(20,'Homepage Activity','Component_Dashboard_HomepageNewsController','','dashboard',NULL,'true','false'),(21,'News','Component_Homepage_NewsController','Provides a text area for you to post information, news, blogs and introductions. ','homepage',1,'true','true'),(22,'Links','Component_Homepage_LinkListController','Provide links to interesting, related and helpful resources on Schoolbox or the WWW.','homepage',5,'true','true'),(23,'Files','Component_Homepage_FileListController','List general files that have been uploaded to your homepage. The large version shows both titles and comments.','homepage',5,'true','true'),(24,'Group Members','Component_Homepage_MembersController','Show the group members associated with your homepage.  This also provides an email all group members link.','homepage',2,'true','false'),(25,'Forum','Component_Homepage_ForumListController','Show recent discussion topics going on in your homepages forum.','homepage',2,'true','true'),(26,'Class Management','Component_Homepage_ManageClassController','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.','homepage',3,'false','false'),(27,'Folder Management','Component_Homepage_ManageFolderController','Provides links to browse the files, manage group membership and view homepage statistics.','homepage',5,'false','false'),(28,'Group Managers','Component_Homepage_ManagersController','Displays a list of group members with write access to the homepage. Useful in larger groups to show who manages the group.','homepage',2,'true','false'),(29,'Image','Component_Homepage_ImageController','Completely configurable image display, select the image, control the size and link to another location','homepage',4,'true','true'),(30,'Folders','Component_Homepage_FolderListController','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.','homepage',5,'true','true'),(31,'Calendar','Component_Homepage_EventListController','Display upcoming events that are in the calendar for this group.','homepage',1,'true','false'),(32,'ClickView','Component_Homepage_ClickviewController','Embed Clickview Home videos directly into your homepage by providing the ID of the video','homepage',4,'true','true'),(33,'Image Gallery','Component_Homepage_ImageGalleryController','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ','homepage',4,'true','true'),(34,'Random Image','Component_Homepage_RandImageController','Display a random image selected from the images on this homepage.','homepage',4,'true','false'),(35,'File Drop Box','Component_Homepage_DropBoxController','Provides an area for teachers to receive files from students without the need for a submission box and due date','homepage',3,'true','false'),(36,'Due Work','Component_Homepage_DueWorkListController','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.','homepage',3,'true','false'),(37,'RSS Feed','Component_Homepage_RSSController','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.','homepage',6,'true','true'),(38,'Class Teachers','Component_Homepage_TeachersController','Show a list of teachers responsible for this subject if it has a synergetic subject code.','homepage',3,'true','false'),(39,'Campus Calendar','Component_Homepage_CalendarListController','Show a calendar for the entire campus on your homepage.','homepage',1,'false','false'),(41,'Poll','Component_Homepage_PollController','Create a poll to determine group members opinion on a given subject.','homepage',2,'true','true'),(42,'Wiki','Component_Homepage_WikiController','Provides an area for group members to work together on joint documents.','homepage',2,'true','true'),(43,'Homepage Feed','Component_Homepage_FeedController','Provides a running commentary on recent changes to the homepage','homepage',6,'true','false'),(44,'Text Box','Component_Homepage_TextBoxController','Use this space to write introductory message or information relating to the content that is displayed','homepage',1,'true','true'),(45,'Audio','Component_Homepage_AudioController','Lists audio files and allows them to be played.','homepage',4,'true','false'),(46,'Podcast','Component_Homepage_PodcastController','Subscribe to audio podcasts and provide inline playback of the media directly in the homepage','homepage',6,'true','true'),(47,'Blog','Component_Homepage_BlogController','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.','homepage',1,'true','true'),(49,'Image Slideshow','Component_Homepage_ScrollerController','Display an animated image carousel, fading between all the images uploaded to your homepage','homepage',4,'true','true'),(50,'Quizzes','Component_Homepage_AssessController','Create tests and quizzes for your students. Results show in the class marksheet. Build resources using the school question bank.','homepage',3,'true','false'),(51,'Countdown','Component_Homepage_CountdownController','Displays a countdown to the specified time.','homepage',1,'true','true'),(52,'Chat','Component_Homepage_ChatController','Allows users who are viewing the same homepage to chat with each other.','homepage',2,'true','false'),(53,'Surveys','Component_Homepage_SurveyController','Create online surveys or forms to collect data from your class or group.  Useful for collecting feedback or enrolling in electives.','homepage',2,'true','false'),(54,'Twitter','Component_Homepage_TwitterController','Connect and feed Twitter posts directly on to your homepage. Search users, hashtags or keywords to create dynamic streams.','homepage',6,'true','true'),(55,'TextBox','Component_Dashboard_TextboxController','Module for displaying custom content on dashboards by being linked to a pre-existing textbox.','dashboard',NULL,'true','true'),(56,'Class List','Component_Homepage_ClassListController','Show your students portraits on your class homepage','homepage',3,'true','false'),(57,'Basic List','Component_Eportfolio_BasicListController','A list component','eportfolio',NULL,'true','true'),(58,'Advanced List','Component_Eportfolio_AdvancedListController','An advanced list component','eportfolio',NULL,'true','true'),(59,'Link List','Component_Eportfolio_LinkListController','A link list component','eportfolio',NULL,'true','true'),(60,'Textbox','Component_Eportfolio_TextboxController','A textbox component','eportfolio',NULL,'true','true'),(61,'My Files','Component_Eportfolio_FileListController','A files component','eportfolio',NULL,'true','false'),(62,'Image Gallery','Component_Eportfolio_ImageListController','An image component','eportfolio',NULL,'true','false'),(63,'Blog','Component_Eportfolio_BlogController','A blog component','eportfolio',NULL,'true','false'),(64,'Video Player','Component_Homepage_VideoController','Upload and play videos directly in your homepage','homepage',4,'true','true'),(65,'Wolfram|Alpha','Component_Homepage_WolframController','Embed Wolfram|Alpha widgets directly into your page','homepage',6,'true','true'),(85,'Curriculum Details','Schoolbox\\Curriculum\\Component\\CurriculumDetailsController','Shows the full details of a curriculum code that has been attached to this homepage','homepage',3,'false','true'),(66,'Course Outline','Component_Homepage_CourseInstanceController','Import a course onto your class page, along with its tasks, assessments and rubrics.','homepage',3,'true','true'),(67,'Course Builder','Component_Homepage_CourseTaskListController','Use this component to build tasks and assessments for a course.','homepage',3,'true','false'),(68,'Course Criteria','Component_Homepage_RubricController','View and edit the criteria for this subject or course.','homepage',3,'true','false'),(69,'Social Stream','Component_Homepage_SocialStreamController','Allow comments, links, images, video and files to be posted in a collaborative stream.','homepage',2,'true','true'),(70,'Subject List','Component_Dashboard_SubjectController','Displays a list of subjects for the logged-in student.','dashboard',1,'true','false'),(72,'Breadcrumbs','Component_Homepage_BreadcrumbController','Display links to pages located above this page in the resource browser','homepage',5,'true','false'),(73,'Image Slideshow','Component_Dashboard_ScrollerController','Display an animated image carousel, fading between all the images','dashboard',NULL,'true','true'),(74,'Collab Docs','Component_Homepage_EtherpadController','Create documents with simultaneous multiple authors and real-time editing','homepage',2,'true','false'),(75,'Resource Management','Schoolbox\\ResourceBooking\\Booking\\Component\\ResourceManagement','Resource Management Component','dashboard',1,'false','false'),(76,'Image Slider','Component_Dashboard_ImageSliderController','An interactive slider for displaying ads that can be clicked','dashboard',NULL,'true','false'),(77,'Image Gallery','Component_Dashboard_ImageGalleryController','Show a grid of image thumbnails sourced from the files uploaded into the Resource Browser.','dashboard',NULL,'true','true'),(78,'Twitter','Component_Dashboard_TwitterController','Completely configurable image display, select the image, control the size and link to another location','dashboard',NULL,'true','true'),(79,'Video','Component_Dashboard_VideoController','Upload and play videos directly in your Dashboard','dashboard',NULL,'true','true'),(80,'Image','Component_Eportfolio_ImageController','Completely configurable image display, select the image, control the size and link to another location','eportfolio',NULL,'true','true'),(81,'Video','Component_Eportfolio_VideoController','Upload and play videos directly in your ePortfolio','eportfolio',NULL,'true','true'),(82,'Pinterest','Component_Homepage_PinterestController','Display Pinterest user profiles or their boards.','homepage',6,'true','true'),(83,'Tiles','Schoolbox\\Tile\\Component\\HomepageTileController','Shows a list of tiles, arranged in either a fixed-width grid or spanned across a number of columns.','homepage',1,'true','true'),(84,'Tiles','Schoolbox\\Tile\\Component\\DashboardTileController','Shows a list of tiles, arranged in either a fixed-width grid or spanned across a number of columns.','dashboard',1,'true','true'),(86,'Curriculum Overview','Schoolbox\\Curriculum\\Component\\CurriculumOverviewController','Shows an overview of a curriculum code that has been attached to this homepage, as well as any sub-codes that this code might have.','homepage',3,'false','true');
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
  `module` enum('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') DEFAULT NULL,
  `identifier` int(10) unsigned NOT NULL COMMENT 'Foreign ID is used to reference the context of the instance. (Polymorphic)',
  `position` enum('top','left','right') NOT NULL DEFAULT 'left',
  `sequence` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_instance_dashboard_component1` (`component_definition_id`),
  KEY `fk_dashboard_component_instance_groups1` (`identifier`),
  KEY `module` (`module`),
  KEY `position` (`position`)
) ENGINE=MyISAM AUTO_INCREMENT=3210 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `component_instance`
--

LOCK TABLES `component_instance` WRITE;
/*!40000 ALTER TABLE `component_instance` DISABLE KEYS */;
INSERT INTO `component_instance` (`id`, `component_definition_id`, `module`, `identifier`, `position`, `sequence`) VALUES (1,2,'dashboard',1,'top',1),(3,4,'dashboard',1,'left',1),(4,3,'dashboard',1,'right',2),(5,13,'dashboard',1,'left',1),(6,5,'dashboard',1,'right',1),(3183,67,'homepage_template',10,'left',2),(9,2,'dashboard',2,'top',1),(10,1,'dashboard',2,'top',2),(11,4,'dashboard',2,'left',1),(12,3,'dashboard',2,'right',3),(13,13,'dashboard',2,'left',3),(14,5,'dashboard',2,'right',1),(15,6,'dashboard',2,'left',2),(2941,19,'dashboard',2,'right',2),(17,2,'dashboard',3,'top',1),(18,1,'dashboard',3,'top',2),(19,4,'dashboard',3,'left',1),(20,3,'dashboard',3,'right',1),(21,5,'dashboard',3,'right',1),(3171,20,'dashboard',3,'left',1),(2944,18,'dashboard',3,'right',1),(24,2,'dashboard',4,'top',1),(3188,30,'homepage_template',8,'right',2),(26,4,'dashboard',4,'left',1),(27,3,'dashboard',4,'right',1),(29,5,'dashboard',4,'right',1),(30,6,'dashboard',4,'left',1),(31,7,'dashboard',4,'right',1),(32,2,'dashboard',5,'top',1),(3185,23,'homepage_template',10,'right',1),(34,4,'dashboard',5,'left',1),(35,3,'dashboard',5,'right',1),(36,5,'dashboard',5,'right',1),(3172,20,'dashboard',5,'left',1),(2876,22,'homepage_template',8,'right',4),(3176,66,'homepage_template',1,'right',1),(2873,44,'homepage_template',8,'left',1),(3178,23,'homepage_template',1,'right',2),(3177,21,'homepage_template',1,'left',2),(3179,36,'homepage_template',1,'left',3),(2869,44,'homepage_template',1,'left',1),(2868,22,'homepage_template',1,'right',3),(48,21,'homepage_template',2,'left',1),(49,42,'homepage_template',2,'left',2),(50,23,'homepage_template',2,'left',3),(51,24,'homepage_template',2,'right',1),(52,31,'homepage_template',2,'right',2),(53,25,'homepage_template',2,'right',3),(54,43,'homepage_template',2,'right',4),(56,33,'homepage_template',3,'left',1),(57,21,'homepage_template',3,'left',2),(58,23,'homepage_template',3,'left',3),(59,43,'homepage_template',3,'left',4),(60,31,'homepage_template',3,'right',1),(61,24,'homepage_template',3,'right',2),(62,25,'homepage_template',3,'right',3),(63,41,'homepage_template',3,'right',4),(64,22,'homepage_template',3,'right',5),(66,29,'homepage_template',4,'left',1),(67,21,'homepage_template',4,'left',2),(68,23,'homepage_template',4,'left',3),(69,28,'homepage_template',4,'right',1),(70,30,'homepage_template',4,'right',2),(71,22,'homepage_template',4,'right',3),(73,21,'homepage_template',5,'left',1),(74,42,'homepage_template',5,'left',2),(75,23,'homepage_template',5,'left',3),(76,24,'homepage_template',5,'right',2),(77,31,'homepage_template',5,'right',2),(78,25,'homepage_template',5,'right',3),(79,43,'homepage_template',5,'right',4),(81,21,'homepage_template',6,'left',1),(82,42,'homepage_template',6,'left',2),(83,23,'homepage_template',6,'left',3),(84,24,'homepage_template',6,'right',1),(85,31,'homepage_template',6,'right',2),(86,25,'homepage_template',6,'right',3),(87,43,'homepage_template',6,'right',4),(89,47,'homepage_template',7,'left',1),(90,41,'homepage_template',7,'right',1),(91,22,'homepage_template',7,'right',2),(3175,29,'homepage_template',8,'right',1),(3174,23,'homepage_template',8,'right',3),(3182,68,'homepage_template',8,'left',2),(3019,60,'eportfolio_template',1,'left',1),(3020,58,'eportfolio_template',1,'left',2),(3021,58,'eportfolio_template',1,'right',1),(3022,57,'eportfolio_template',1,'right',2),(3023,57,'eportfolio_template',1,'right',3),(3024,63,'eportfolio_template',2,'left',1),(3025,57,'eportfolio_template',2,'right',1),(3026,62,'eportfolio_template',2,'right',2),(3027,60,'eportfolio_template',3,'top',1),(3028,60,'eportfolio_template',3,'left',1),(3029,62,'eportfolio_template',3,'left',2),(3030,60,'eportfolio_template',3,'left',3),(3031,57,'eportfolio_template',3,'right',1),(3032,63,'eportfolio_template',4,'left',1),(3033,60,'eportfolio_template',4,'left',2),(3034,58,'eportfolio_template',4,'right',1),(3035,60,'eportfolio',1,'left',1),(3036,58,'eportfolio',1,'left',2),(3037,58,'eportfolio',1,'right',1),(3038,57,'eportfolio',1,'right',2),(3039,57,'eportfolio',1,'right',3),(3040,60,'eportfolio',1,'left',0),(3041,60,'eportfolio',2,'left',1),(3042,58,'eportfolio',2,'left',2),(3043,58,'eportfolio',2,'right',1),(3044,57,'eportfolio',2,'right',2),(3045,57,'eportfolio',2,'right',3),(3046,60,'eportfolio',3,'left',1),(3047,58,'eportfolio',3,'left',2),(3048,58,'eportfolio',3,'right',1),(3049,57,'eportfolio',3,'right',2),(3050,57,'eportfolio',3,'right',3),(3051,63,'eportfolio',3,'left',0),(3052,61,'eportfolio',3,'left',0),(3053,60,'eportfolio',3,'left',0),(3054,60,'eportfolio',3,'left',0),(3055,60,'eportfolio',3,'left',0),(3056,60,'eportfolio',3,'left',0),(3057,60,'eportfolio',4,'left',1),(3058,58,'eportfolio',4,'left',2),(3059,58,'eportfolio',4,'right',1),(3060,57,'eportfolio',4,'right',2),(3061,57,'eportfolio',4,'right',3),(3062,60,'eportfolio',5,'left',1),(3063,58,'eportfolio',5,'left',2),(3064,58,'eportfolio',5,'right',1),(3065,57,'eportfolio',5,'right',2),(3066,57,'eportfolio',5,'right',3),(3067,63,'eportfolio',5,'left',0),(3068,59,'eportfolio',5,'left',0),(3069,61,'eportfolio',5,'left',0),(3070,60,'eportfolio',5,'left',0),(3071,60,'eportfolio',5,'left',0),(3072,60,'eportfolio',5,'left',0),(3073,60,'eportfolio',5,'left',0),(3074,60,'eportfolio',5,'left',0),(3169,20,'dashboard',1,'right',3),(3186,22,'homepage_template',10,'right',2),(3184,44,'homepage_template',10,'left',1),(3152,1,'dashboard',1,'top',2);
/*!40000 ALTER TABLE `component_instance` ENABLE KEYS */;
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
  `field_id` int(11) DEFAULT NULL,
  `options` varchar(300) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `editable` enum('true','false') DEFAULT 'false',
  `hidden` enum('true','false') DEFAULT 'false',
  `required` enum('false','true') DEFAULT 'false',
  PRIMARY KEY (`configID`),
  UNIQUE KEY `configName` (`name`),
  KEY `config_field_id` (`field_id`),
  KEY `config_type_id` (`type_id`)
) ENGINE=MyISAM AUTO_INCREMENT=246 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` (`configID`, `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`) VALUES (1,'title','Schoolbox','Title','Title of site displayed at top of browser window',1,'',1,'true','false','true'),(2,'school_name','Alaress Pty Ltd','School Name','Name of school displayed in footer',1,'',1,'true','false','true'),(3,'school_address','http://www.alaress.com.au','School Website Address','Address of school website',1,'',1,'true','false','true'),(4,'site_location','demo','Site Identifier','Name of site used to identify school in custom code and templates',1,'',1,'false','false','true'),(5,'site_hostname','demo','Hostname','Hostname used to access server without the domain',1,'',1,'true','false','true'),(6,'site_domain','schoolbox.com.au','Domain','Network domain that is used to access server remotely',1,'',1,'true','false','true'),(7,'site_detect','campus','Template Detection','Method which template is detected',4,'none:campus:domain',1,'false','false','true'),(8,'login_grace','60','Login Grace Time','Amount of time (secs) between failed logins before brute force lockout',1,'',2,'true','false','true'),(9,'login_allowed','3','Failed Logins Allowed','Amount of logins allowed in login grace time before brute force lockout',1,'',2,'true','false','true'),(10,'track_ip','0','Track Session IPs','Track session IPs, prevents session stealing but can cause problems with proxies',3,'',2,'true','false','true'),(11,'session_length','3600','Session Expiry Time','Length of idle time (secs) before a user is logged out',1,'',2,'true','false','true'),(12,'kerb_enable','0','Enable Kerberos SSO','Enable Kerberos SSO authentication.  This enables sessions to be created based on mod_auth_kerb authentication',3,'',2,'true','false','true'),(13,'ldap_enable','0','Enable LDAP Authentication','Enable authentication via LDAP server. System will attempt first to bind to LDAP if this fails, it will fall back local authentication',3,'',16,'true','false','false'),(14,'ldap_host','','LDAP Host','Address of LDAP server to which search should be performed',1,'',16,'true','false','false'),(15,'ldap_user','','LDAP Username','User account for binding to LDAP, should be complete DN',1,'',16,'true','false','false'),(16,'ldap_pass','','LDAP Password','Password for binding to LDAP server',1,'',16,'true','false','false'),(17,'ldap_base','DC=schoolboxdemo,DC=local','LDAP Search Base','Base search path for username lookup',1,'',16,'true','false','false'),(18,'ldap_host_alt','','LDAP Alternate Host','Address of LDAP server to which search should be performed',1,'',16,'true','false','false'),(19,'ldap_user_alt','','LDAP Alternate Username','User account for binding to LDAP, should be complete DN',1,'',16,'true','false','false'),(20,'ldap_base_alt','','LDAP Alternate Search Base','Base search path for username lookup',1,'',16,'true','false','false'),(21,'ldap_username','sAMAccountName','LDAP Username Search Field','Field to search for username in LDAP',1,'',16,'true','false','false'),(22,'template_dir','/usr/share/schoolbox/templates','Template Location','Location of templates',1,NULL,3,'false','false','true'),(23,'file_dir','/var/lib/schoolbox/files','File Location','Location of files uploaded to resources are stored',1,NULL,3,'false','false','true'),(24,'myfile_dir','/var/lib/schoolbox/myFiles','MyFiles Location','Location of personal files uploaded to my files or eportfolio are stored',1,NULL,3,'false','false','true'),(25,'submission_dir','/var/lib/schoolbox/submissions','Submission Location','Location of student submissions',1,NULL,3,'false','false','true'),(26,'returns_dir','/var/lib/schoolbox/returns','Returns Location','Location of teacher returned work',1,NULL,3,'false','false','true'),(27,'dropbox_dir','/var/lib/schoolbox/dropbox','Dropbox Location','Location of files uploaded to dropbox',1,NULL,3,'false','false','true'),(28,'zip_dir','/var/lib/schoolbox/cache/zip','ZIP Temp Location','Location of files uploaded to dropbox',1,NULL,3,'false','false','true'),(29,'thumbnail_dir','/var/lib/schoolbox/thumbnails','Image Cache Location','Location of where thumbnails and resized images are stored after they are generated',1,NULL,3,'false','false','true'),(30,'xml_dir','/var/lib/schoolbox/cache/xml','XML Cache Location','Location of RSS and XML caching',1,NULL,3,'false','false','true'),(31,'thumbnail_size','100','Thumbnail pixel size','Maximum height/width of thumbnails generated for image gallery',1,'',4,'true','false','true'),(32,'file_upload_count','30','File Upload Limit','The total amount of files that can be uploaded via a single multifile upload page',1,'',4,'true','false','true'),(33,'max_file_size','52428800','File Size Limit','The maximum size of a single file that can be uploaded. Accepts formats like 40M and 1GB.',6,'',4,'true','false','true'),(34,'news_edit_other','1','Allow Others to Edit News','Controls whether users with write access to a page can edit each others news posts',3,'',5,'true','false','true'),(35,'group_staff_only','1','Allow only staff to be Group Admins','Disabling this option allows students and parents to be promoted to group admin. Beware of the consequences.',3,'',5,'true','false','true'),(36,'webdav_enable','0','Enable WebDAV connector','Turns on webDAV, enabling the network drives tab',3,NULL,4,'false','false','true'),(37,'search_all_limit','3','Search Summary Item Limit','The amount of items shown for each section on the all tab in the search',3,NULL,4,'false','false','true'),(38,'search_type_limit','15','Search Items Per Page','The amount of results shown per page in the search results',3,NULL,4,'false','false','true'),(110,'xml_cache_timeout','3600','XML Update Frequency','How long xml data should be cached for before being re-queried in seconds',1,'',10,'true','false','true'),(40,'welcome_rss_url','http://www.abc.net.au/news/indexes/justin/rss.xml','URL for RSS','The URL for the RSS feed displayed on the welcome page',1,'',10,'false','true','true'),(41,'welcome_rss_limit','6','RSS Item Limit','The amount of items displayed from the RSS feed on the welcome page',1,'',10,'false','true','true'),(42,'weather_enable','1','Enable Weather','Reguarily fetches weather data from Google weather feeds',3,'',10,'true','false','true'),(43,'weather_location','Australia/Melbourne','Weather Location','By default display weather at this location. This value can be formatted as Country/City; latitude,longitude; airport code; or IP address.',1,'',10,'true','false','true'),(44,'name_format','{title} {firstname} {lastname}','Name Output Format','Format of names available fields; {title}, {firstname}, {lastname}',1,NULL,6,'true','false','true'),(45,'calendar_time_format','%l:%i%p','Calendar Time Format','Time output style 24 hour = \'%k:%i\', 12 hour = \'%l:%i%p\'',1,NULL,6,'true','true','true'),(46,'date_long','l F jS Y','Long Date Format','The date format used in emails, notifications and due dates, as well as other places where the day name of an event may be relevant. Do not include a time format here.',1,NULL,6,'true','false','true'),(47,'date_short','jS M Y','Short Date Format','The default date format used throughout the system. Do not include a time format here.',1,NULL,6,'true','false','true'),(48,'date_database','Y-m-d H:i:s','Database Date Format','A PHP date() time format string',1,NULL,6,'true','true','true'),(49,'date_notime','jS M Y','Date with no Time Format','A PHP date() time format string',1,NULL,6,'true','true','true'),(50,'external_enable','0','Enable External Database','Schoolbox can integrate with your existing school database to fetch timetable and student records',3,'',7,'true','false','false'),(51,'external_type','schoolbox','Database Provider','The name of the database product currently being used',4,'synergetic:tass:denbigh:denbigh2:edumate:schoolbox:schoolpro:maze',7,'true','false','false'),(52,'external_db_host','','Database Host','The server hostname where SQL database is located',1,'',7,'true','false','false'),(53,'external_db_user','','Database User','SQL user with read access to the required tables, see documentation',1,'',7,'true','false','false'),(54,'external_db_passwd','','Database Password','SQL user password',1,'',7,'true','false','false'),(55,'external_db_db','','Database Name','The name of the database on the SQL server',1,'',7,'true','false','false'),(56,'external_portal','','External DB Portal URL','Schoolbox can integrate with your existing school database to fetch timetable and student records',1,'',7,'true','false','false'),(57,'external_sync_data','0','Synchronise Data','Use this to control your automatic sync script, only use this if you have a sync script installed for your service',3,'',7,'true','false','false'),(58,'mail_enable','0','Enable e-Mail','Enables your email configuration for Schoolbox',3,'',8,'true','false','true'),(62,'mail_smtp_host','mail.yourschool.edu.au','SMTP Server','Address of server to send email via, this should be your email gateway',1,'',8,'true','false','true'),(63,'mail_smtp_port','25','SMTP Port','Port of the SMTP server, typically 25',1,'',8,'true','false','true'),(64,'mail_address','yourschool.edu.au','Email Domain','Email domains with which emails should originate from, if no alt emails are specfied email will be username@domain',1,'',8,'true','false','true'),(65,'mail_webmail','http://owa.yourschool.edu.au','Webmail URL','URL to access OWA or other existing webmail service.  Should be accessible internally and externally',1,'',8,'true','false','false'),(66,'mail_from_address','helpdesk@yourschool.edu.au','Email From Address','Emails that originate from Schoolbox will come from this address',1,'',8,'true','false','true'),(67,'mail_from_name','Schoolbox','Email From Name','Emails that originate from Schoolbox will come from this name',1,'',8,'true','false','true'),(68,'mail_support_address','helpdesk@yourschool.edu.au','Schoolbox Feedback Address','Requests sent to the Schoolbox feedback form will be sent to this address',1,'',8,'true','false','true'),(69,'site_version','15.0.9','Schoolbox Version','Version of Schoolbox currently installed',1,NULL,1,'false','false','true'),(70,'policy','','Policy','A policy description which appears in the footer of each page.',8,'',5,'true','false',''),(71,'protect_teacher_photo','0','Hide Teacher Photo','Tick this box to prevent teacher photos being displayed to student and parents in search results.',3,'',5,'true','false','true'),(72,'protect_student_photo','0','Hide Student Photo','Tick this box to hide student photos from other students',3,'',5,'true','false','true'),(73,'homepage_colour_head_font','000000','Heading Text','Colour of the heading text on homepage and eportfolio components',1,'',9,'true','false','false'),(74,'homepage_colour_head_bg','e5eff7','Heading Background','Colour of the background of the headings on homepage and eportfolio components',1,'',9,'true','false','false'),(75,'homepage_colour_font','000000','Body Text','Colour of the body text on homepage and eportfolio components',1,'',9,'true','false','false'),(76,'homepage_colour_bg_primary','ffffff','Primary Background','Primary background colour on homepage and eportfolio components',1,'',9,'true','false','false'),(77,'homepage_colour_bg_secondary','faf9f5','Secondary Background','Secondary background colour homepage and eportfolio components',1,'',9,'true','false','false'),(78,'homepage_colour_link','1d9ffc','Link','Colour of links on homepage and eportfolio components',1,'',9,'true','false','false'),(79,'homepage_colour_hover','ff5a00','Link Hover','Colour of links when your mouse over them on homepage and eportfolio components',1,'',9,'true','false','false'),(80,'date_timezone','Australia/Victoria','Timezone','Set the local timezone for the server, use the PHP timezones available on the site',4,'Australia/Victoria:Australia/NSW:Australia/Queensland:Australia/West:Australia/South:Pacific/Auckland',6,'true','false','true'),(81,'recaptcha_private_api_key','6LfS0sgSAAAAADh12n8weyLb8irboBWfhseikfCF','reCAPTCHA Private Key','The reCAPTCHA private key',1,NULL,10,'false','true','false'),(82,'recaptcha_public_api_key','6LfS0sgSAAAAALQEAZ9Di5rG_RZ7saoLKsgNo6Iq','reCAPTCHA Public Key','The reCAPTCHA public key',1,NULL,10,'false','true','false'),(83,'external_web_services_url','','SOAP Web Services URL','URL of the web services API that can handle SOAP requests',1,'',7,'true','false','false'),(84,'external_vendor_token_guid','','Synergetic Vendor Token GUID','The Vendor Token GUID used to access your Synergetic SOAP API',1,'',7,'true','false','false'),(85,'external_vendor_password','','Synergetic Vendor Password','The Vendor Password used to access your Synergetic SOAP API',1,'',7,'true','false','false'),(103,'weather_api_caching','1800','Weather API Update Frequency','How long weather data should be cached for before being re-queried',1,'',10,'false','true','true'),(102,'weather_api_key','784036067287ef7a','Weather API Key','A key generated in the Wunderground administration interface',1,'',10,'true','false','true'),(91,'plagscan_api_key','','PlagScan API Key','A key key generated in the PlagScan administration interface',1,'',10,'true','false','false'),(92,'plagscan_username','','PlagScan Username','The username you used when registering for the PlagScan service.',1,'',10,'true','false','false'),(207,'homepage_news_expiry','+1 week','Default Homepage News expiry date','The relative date that Homepage News will expire on by default. Can be any string like \'tomorrow\', \'+1 week\', \'next Thursday\'',1,'',5,'true','false','true'),(95,'remote_secret','','Remote Shared Secret','This string is used in the authentication process with remote systems',1,'',2,'true','false','false'),(96,'portrait_dir','/var/lib/schoolbox/cache/portrait','Portrait Location','The path to the portrait data store',1,NULL,3,'false','false','true'),(100,'news_staff_post_enabled','1','Allow Staff to create News','If ticked, staff members can create news posts.',3,'true:false',5,'true','false','false'),(97,'restrict_parent_directory','0','Restrict Parent Directory','If ticked, parents can only see the parent directories for year levels in which they have a student, otherwise they can see all parent directories.',3,'true:false',5,'true','false','false'),(98,'restrict_student_submissions','0','Hide Work Details from Parents','This option will prevent parents from accessing the due work details, this includes files attached and the student submissions.  They will still be able to see the dates and titles for the work via the due work calendar.',3,'true:false',15,'true','false','false'),(99,'stream_dir','/var/lib/schoolbox/cache/stream','Stream Cache Dir','Cache folder for streaming content (RSS, XML, Twitter JSON etc)',1,NULL,3,'false','false','true'),(101,'news_default_expiry','+1 week','Default School News expiry date','The relative date that School News will expire on by default. Can be any string like \'tomorrow\', \'+1 week\', \'next Thursday\'',1,'',5,'true','false','true'),(104,'due_work_notifications_enabled','1','Due Work Notifications Enabled','If ticked, students can automatically be sent reminder emails regarding upcoming due work.',3,'true:false',8,'true','false','false'),(105,'myfiles_cache_dir','/var/lib/schoolbox/cache/myfiles','MyFiles Thumbnails Cache Location','Location of cached thumbnails for MyFiles items',1,NULL,3,'false','false','true'),(106,'external_attendance','0','Enable Online Attendance','Allows teachers to mark attendance online, only compatible with Synergetic.',3,'',7,'true','false','false'),(107,'weather_dir','/var/lib/schoolbox/cache/weather','Weather Cache Location','Location of Weather caching',1,NULL,3,'false','false','true'),(108,'storage_dir','/var/lib/schoolbox/storage','Data Store Location','Multi purpose storage engine, stores all files in this path',1,NULL,3,'false','false','true'),(109,'reveal_password','0','Show Passwords To Admins','If enabled passwords may be revealled via the admin system',3,NULL,5,'false','false','false'),(111,'boarder_leave_enabled','0','Enable Boarder Leave Module','Enables boarder leave module and connectivity to Event Attendance',3,'true:false',11,'false','false','false'),(112,'mobile_enabled','0','Enable Mobile Module','Enables mobile edition of Schoolbox for phones and tablets.',3,'true:false',11,'false','false','false'),(113,'minify_dir','/var/lib/schoolbox/cache/minify','Data Minifier Location','Location of minifier cache files',1,NULL,3,'false','false','true'),(114,'show_all_subjects','1','Show All Subjects on Profile','If enabled all enrolled subjects will be shown on student profile.  If disabled system will hide subjects that do not exist in Schoolbox',3,'',5,'true','false','false'),(115,'sync_patterns','','Sync User Types','User strategies for synchronisation, must be set by Alaress',1,NULL,7,'false','false','true'),(116,'logs_dir','/var/lib/schoolbox/cache/logs','Logs Cache Location','Location of log files',1,NULL,3,'false','false','true'),(117,'ldap_search_filter','','LDAP Search Filter','Pre-filter to put onto LDAP login searches - e.g. objectClass=User',1,'',16,'true','false','false'),(118,'webdav_allow_insecure','0','Enable WebDAV insecure connections','If WebDAV is enabled, this allows connections to be made if the WebDAV host\'s SSL certificate cannot be verified. Please attempt to use a verified certificate before resorting to this option.',3,NULL,4,'false','true','true'),(119,'debug_email_address','','Debug Email Address','Parent Notifications go here',1,'',12,'true','false','false'),(120,'parent_notification','0','Disable Parent Notification','Disable parent notification email when a leave request is created',3,'true:false',12,'true','false','false'),(121,'terms_and_conditions','<ol>\r\n<li>An email will automatically be sent to the email address your parents/guardian have supplied the school. Your parents/guardian then need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.</li>\r\n<li>If you are staying at a host other than family, then the host parent/guardian will need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.  You can forward your email copy of your leave request to them.</li>\r\n<li>You and your parents/guardian will receive notification by email of the status of your leave - approved, modified or rejected.</li>\r\n<li>Confirmation from your parents/guardian and/or hosts must be received by your Housemaster by 8:00pm on the Thursday prior to your leave request.</li>\r\n<li>You should advise your Housemaster as soon as possible if your leave arrangements change.</li>\r\n<li>This application is a request for leave and the School reserves the right to approve/deny leave.</li>\r\n</ol>','Terms and Conditions','Terms and Conditions',8,'',12,'true','false','false'),(122,'edumate_auth_enabled','0','Enable Edumate Authentication','Enable authentication via Edumate system. If this fails, authentication will fall back to local.',3,'',2,'true','false','false'),(123,'edumate_auth_url','','Edumate Authentication URL','The URL to send auth requests to if Edumate authentication is being used.',1,'',2,'true','false','false'),(124,'email_all_parents','1','Allow Email All Parents from Class List','If ticked, allows staff to email all the parents of a class with a single button',3,'true:false',5,'true','false','false'),(125,'file_default_to_hidden','0','File uploads default to hidden','If this is checked, then file uploads will default to hidden - this can be changed on a per-file basis.',3,'',4,'true','false','false'),(126,'protect_medicalspecial','0','Hide Medical and Special Needs on Class Lists','Tick this box to hide all Medical and Special Needs information from displaying on the Class List pages',3,'true:false',5,'true','false','false'),(127,'restrict_parent_emails','0','Restrict Display of Parent Emails','If ticked, restricts the display of parent email addresses to staff only.',3,'true:false',5,'true','false','false'),(128,'twitter_api_consumer_key','hJYli4TfG9f4aRqHlXKFg','Twitter API consumer key','Consumer key for Twitter API.',1,NULL,10,'false','false','false'),(129,'twitter_api_consumer_secret','14g4I80nKDD2gRlorwuFDzqpbVPXdbZDZZslvLwWydw','Twitter API consumer secret','Consumer secret for Twitter API.',1,NULL,10,'false','false','false'),(130,'twitter_api_oauth_token','327753084-KjtaRGaMaNxvBiOy0DSTF0ENzyXZqPG2Tkev9XEL','Twitter API OAuth token','OAuth token for Twitter API.',1,NULL,10,'false','false','false'),(131,'twitter_api_oauth_token_secret','iGAY5wxnLrg0beOf2JcQNG0whjWBmAQyeLSS18o8jU','Twitter API OAuth token secret','OAuth token secret for Twitter API.',1,NULL,10,'false','false','false'),(132,'twitter_api_caching','1800','Twitter API update frequency','The amount of time (in seconds) that tweets should be cached for before being re-queried.',1,NULL,10,'false','false','false'),(133,'webdav_allow_any_auth','0','Enable WebDAV any auth type','If this is enabled, then Schoolbox will poll the WebDAV server for the best authentication method - otherwise, only basic authentication will be attempted.',3,NULL,4,'false','true','true'),(134,'boarder_breakfast_start','07:00','Breakfast Start Time','These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)',1,NULL,12,'true','false','true'),(135,'boarder_breakfast_end','08:00','Breakfast End Time','These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)',1,NULL,12,'true','false','true'),(136,'boarder_lunch_start','11:00','Lunch Start Time','These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)',1,NULL,12,'true','false','true'),(137,'boarder_lunch_end','13:00','Lunch End Time','These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)',1,NULL,12,'true','false','true'),(138,'boarder_dinner_start','17:00','Dinner Start Time','These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)',1,NULL,12,'true','false','true'),(139,'boarder_dinner_end','18:00','Dinner End Time','These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)',1,NULL,12,'true','false','true'),(140,'display_grade_average','1','Display Class Average on Student Grade Graph','The class average is shown as a blue line on the students\' grade report. This option allows you to hide that line from students and parents.',3,'',15,'true','false','false'),(141,'show_overall_grade','1','Display Grade Average to Students and Parents','Disable the display of the overall grade on the Due Work and Grade Report page for students and parents.',3,'',15,'true','false','false'),(164,'saml_sso_url','','Single Sign-On URL','The URL to direct single sign-on requests to.',1,'',2,'true','false','false'),(163,'safe_iframe_source_list','','Safe IFrame Sources','Any URL that begins with a line in this list may be used as the source of an IFrame in text boxes. Do not include the scheme (HTTP or HTTPS), only the location and path (example: www.schoolbox.com.au/embed/).',2,'',2,'true','false','false'),(144,'mail_admin_address','admin@yourschool.edu.au','Administrator Email Address','Emails for the Schoolbox Administrator (you, if you\'re changing this setting) will be sent to this address.',1,'',8,'true','false','false'),(145,'password_change_email_from','helpdesk@yourschool.edu.au','Password Change Email From Address','The email address that users receive password change emails from.',1,'',8,'true','false','false'),(146,'password_change_email_subject','Schoolbox Password Change','Password Change Email Subject','The subject of the email that users receive when they change their password.',1,'',8,'true','false','false'),(147,'password_change_failed_email_subject','Schoolbox Password Change Failed','Password Change Failed Email Subject','The subject of the email that is sent to the Schoolbox Administrator when someone fails to change their password.',1,'',8,'true','false','false'),(148,'calendar_api_caching','600','Calendar API update frequency','The amount of time (in seconds) that external Calendars should be cached for before being re-queried.',1,NULL,10,'false','false','false'),(149,'plagscan_student_visible','1','Display Plagiarism Score to Students','When students submit work this option will allow them to view the plagiarism score they received. Note that the plagiarism system must be enabled for this to work.',3,'',15,'true','false','false'),(150,'submission_access','2','Default Submission Grade Visibility','This controls the default setting for grade visibility.  Note this is just a default and can be overridden per-submission.  Combine this with Maximum Submission visibility to control the default and what is shown',7,'0;Staff Only:1;Staff and Students:2;Staff, Students and their Parents',15,'true','false','false'),(151,'restrict_parent_forum_access','1','Restrict Parent Access to Forums','Prevents parents from reading forums of classes and pages they are not directly involved in as a group member. If this is off, they may read (but not post to) forums for pages that a child is involved in.',3,'true:false',5,'true','false','false'),(152,'homepage_enable_colour','1','Enable homepage colours?','Is the colour selector enabled on homepages?',3,'',9,'true','false','true'),(153,'video_dir','/var/lib/schoolbox/video','Video Storage Location','Location that all transcoded videos are stored',1,NULL,3,'false','false','true'),(154,'video_processing_timeout','3600','Video Processing Timeout','The maximum number of seconds that a video may process for before timing out and being marked as failed. (Set to 0 or less to disable timeout.)',1,'',4,'true','false','false'),(155,'proxy_host','','Proxy Host','The proxy server hostname or IP address',1,'',17,'true','false','false'),(156,'proxy_port','','Proxy Port','The proxy server port',1,'',17,'true','false','false'),(157,'proxy_user','','Proxy Username','The username Schoolbox uses for proxy authentication (can be blank for anonymous)',1,'',17,'true','false','false'),(158,'proxy_pass','','Proxy Password','The password Schoolbox uses for proxy authentication (can be blank for anonymous)',1,'',17,'true','false','false'),(159,'api_key','','API Key','The API key must be set before the Schoolbox API can be used. This key should be kept safe as it is possible to gain superuser privileges with it.',1,'',2,'true','false','false'),(160,'course_homepage','/homepage/2','Course Folder Location','This folder will be used as the base for the course selector, this simplifies course selection',9,'',15,'true','false','false'),(161,'debug_mode','0','Debug mode','If this is on, a debugging toolbar is displayed on the bottom of each page.',3,NULL,14,'true','false','false'),(162,'debug_xhprof','0','Debug XHProf output','Displays XHProf output on the debugging toolbar. Has no effect unless debug mode is on, and the XHProf PHP extension is installed.',3,NULL,14,'true','false','false'),(165,'saml_idp_certificate','','Single Sign-On IDP certificate','The public key certificate that is used to encode single sign-on requests.',2,'',2,'true','false','false'),(166,'mail_smtp_username','','SMTP Username','If this is set, SMTP Auth will be used with this username and corresponding password',1,'',8,'true','false','false'),(167,'mail_smtp_password','','SMTP Password','Password for SMTP Auth, only used if username is set',1,'',8,'true','false','false'),(168,'redis_url','tcp://127.0.0.1:6379','Redis URL','URL for Redis server',1,'',4,'false','true','false'),(169,'scrub_html_cache_timeout','604800','Scrubbed HTML cache timeout','Timeout for cache of scrubbed HTML values',1,'',4,'false','true','false'),(170,'restrict_parent_duework_access','0','Hide Due Work from Parents','This will prevent parents from being able to access the due work calendar for their students',3,'true:false',15,'true','false','false'),(171,'mysubjects_split','1','Display Classes by Type','Split classes in side navigation and profile by class type (Academic, Music, Sport, etc). This only works when external database has support for type.',3,'true:false',5,'true','false','false'),(172,'boarder_leave_creation_threshold','24','Boarder Leave Creation Threshold','Number of hours from Friday 12PM of the weekend leave is intended to commence. During this period leave requests cannot be created.',1,NULL,12,'true','false','false'),(173,'display_grade_graph','1','Display Grade Graph on Student Grade Card','This will show the grade graph on the student grade card to all users',3,NULL,15,'true','false','false'),(174,'saml_krb_firefox','0','Single Sign-On Kerberos Firefox','Enable Firefox browser on desktop for using kerberos authentication with SAML.',3,NULL,2,'true','false','false'),(175,'saml_krb_safari','0','Single Sign-On Kerberos Safari','Enable Safari browser on desktop for using kerberos authentication with SAML.',3,NULL,2,'true','false','false'),(176,'saml_krb_whitelist','','Single Sign-On Kerberos Whitelist','Comma separated list of individual IPs and CIDR ranges for forcing kerberos authentication with SAML. E.g. 192.168.1.0/24,10.0.5.1,172.0.0.0/8',2,NULL,2,'true','false','false'),(177,'saml_logout_url','','Single Sign-On Logout URL','The logout URL for the SAML identity provider to redirect users to upon logout. You can use {SCHOOLBOX_ADDRESS} in the URL which will be automatically replaced with the correct address, e.g. schoolbox.school.edu',1,NULL,2,'true','false','false'),(178,'submission_upload_tmp_dir','/var/lib/schoolbox/cache/submission_upload','Submission File Upload','Temporary cache folder for uploading submissions to external locations',1,NULL,3,'false','false','false'),(179,'crocodoc_api_token','','Crocodoc API Token','Crocodoc API token for inline grading. Used to download and annotate submissions.',1,'',10,'true','false','false'),(180,'etherpad_api_token','xbxs&e#sa8@$2@k73^f32!7wz2djy%','Etherpad API Token','Used to access Etherpad documents',1,NULL,15,'false','true','false'),(181,'lms_student_policy','','Student Submit Terms','If set, students will be asked to agree to these terms when submitting work',8,NULL,15,'true','false','false'),(182,'restrict_parent_grades','0','Hide Grades from Parents','This will prevent parents from seeing grades in due work and from seeing the grade card completely',3,'true:false',15,'true','false','false'),(183,'submission_visibility_max','2','Maximum Submission Grade Visibility','This will control the maximum grade visibility teachers can use when creating submission boxes',7,'0;Staff Only:1;Staff and Students:2;Staff, Students and their Parents',15,'true','false','false'),(184,'amqp_host','localhost','Message Queue Host','The message queue hostname or IP address',1,'',7,'true','true','true'),(185,'amqp_port','5672','Message Queue Port','The message queue port',1,'',7,'true','true','true'),(186,'amqp_stomp_port','61613','Message Queue STOMP Port','The message queue STOMP subscription port',1,'',7,'true','true','true'),(187,'amqp_user','guest','Message Queue Username','The username Schoolbox uses to authenticate to the message queue server',1,'',7,'true','true','true'),(188,'amqp_pass','guest','Message Queue Password','The password Schoolbox uses to authenticate to the message queue server',1,'',7,'true','true','true'),(189,'websocket_path','/websocket','Websocket Listening Path','If set, the path on which messages will be listened for. Only necessary if intending to use port 80 for sockets',1,'',5,'true','true','false'),(190,'websocket_port','8080','Websocket Port','The port on which Messaging listens to websockets (defaults to 8080)',1,'',5,'true','true','true'),(191,'websocket_port_ssl','8081','Websocket SSL Port','The port on which Messaging listens to secure websockets (defaults to 8081)',1,'',5,'true','true','true'),(192,'digest_oldest_first','1','Digest Sort Descending','If set, digest emails will be sorted oldest to newest, otherwise newest to oldest',3,'',5,'true','false','false'),(193,'digest_subject','News Digest','Digest Email Subject','The subject of digest emails',1,'',5,'true','false','true'),(194,'messaging_enabled','1','Messaging Enabled','Is the messaging system enabled',3,'',5,'true','false','false'),(196,'debug_templates','0','Debug Templates','If this is on, Twig templates will be loaded in debug mode. This may be slower, but changes to templates are guaranteed to be recognised immediately.',3,'',NULL,'false','true','false'),(195,'resource_booking_enabled','0','Enable Resource Booking Module','Is the resource booking system enabled?',3,'',11,'false','false','false'),(197,'enable_grades_criteria','1','Display Grades Criteria','If set, the Criteria section will be displayed in a student\'s Grades page',3,NULL,15,'true','false','false'),(198,'external_liss_username','','External Liss Username','Liss Password',1,'',7,'true','false','false'),(199,'external_liss_password','','External Liss Password','Liss Password',1,'',7,'true','false','false'),(200,'external_liss_schoolcode','','External Liss Schoolcode','Liss School Code',1,'',7,'true','false','false'),(201,'enable_teacher_zip_upload','0','Enable Teachers Zip Upload','If set, staff members will be able to upload zip files to create folder structures',3,'true:false',4,'true','false','false'),(202,'mobile_application_cache_enabled','0','Enable Mobile Module Application Cache','If this is on, the mobile application cache will be enabled. It is disabled by default, because many users have reported issues with it.',3,'',11,'false','true','false'),(203,'saml_debug','0','Enable Single Sign-On Debug Logging','Enables debug logging for Single Sign On via SAML',3,NULL,2,'true','false','false'),(204,'saml_enabled','0','Enable Single Sign-On','Check this to enable Single Sign On via SAML',3,NULL,2,'true','false','false'),(205,'grade_comments_enable','1','Allow Comments on Student Grades','Enables the ability for parents, students and teachers to leave comments on student grades. If this is off, comments that were made when this setting was on will not be shown.',3,NULL,15,'true','false','false'),(206,'session_remember','1','Allow Remember Me','If checked users will be able to request their session lasts for 60 days, rather than the normal session limit.',3,NULL,2,'true','false','false'),(208,'synergetic_staff_dir_restrict','SC','Staff Directory Restriction','Remove staff in this category from the staff directory page, eg \'SC,NP\'',1,NULL,7,'true','false','false'),(209,'googleapps_api_key','','Google Apps API Key','The public API access key for your Schoolbox installation',1,'',10,'true','false','false'),(210,'googleapps_client_id','','Google Apps Client ID','The client ID for your Schoolbox installation',1,'',10,'true','false','false'),(211,'default_grade_group','1','Default Letter Grade Group','The default letter grade group that is selected for new due work items.\n  This group will also be used to determine the letter grade average,\n  if there are no due work items that have a different group.',10,'a:1:{s:6:\"mapper\";s:69:\"\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Group\\Mapper\";}',15,'true','false','false'),(212,'amqp_vhost','/','Message Queue Virtualhost','The virtualhost Schoolbox uses on AMQP',1,'',7,'true','true','true'),(213,'parent_search_enable','1','Allow Parent Search','If checked parents will appear in the search results conducted by students and other parents.',3,NULL,5,'true','false','false'),(214,'push_notifications_enabled','0','Enable Push Notification Module','Enables push notification module.',3,'',11,'false','false','false'),(215,'push_gcm_api_key','','Google Cloud Messaging API Key','',1,'',11,'false','true','false'),(216,'push_apns_cert_path','','Apple Push Notification Service Certificate Path','',1,'',11,'false','true','false'),(217,'push_apns_cert_key','','Apple Push Notification Service Certificate Key','',1,'',11,'false','true','false'),(218,'calendar_collapse_events','1','Collapse events on calendar?','If this setting is on, events on the calendar month view will be collapsed to fit the screen, and hidden behind a \"View More\" link. If this is off, all events will display at once.',3,NULL,6,'true','false','false'),(219,'calendar_start_day','0','Calendar Start Day','The day the calendar display starts on - usually Sunday or Monday.',7,'0;Sunday:1;Monday:2;Tuesday:3;Wednesday:4;Thursday:5;Friday:6;Saturday',6,'true','false','false'),(220,'report_staff_access','0','Allow Parents Into Staff Directory','If this setting is on, parents role types will have access into the staff directory.  Otherwise its only accessible to staff.',3,NULL,5,'true','false','false'),(221,'protect_medicalspecial_profile','0','Hide Medical and Special Needs on Profiles','Tick this box to hide all Medical and Special Needs information from displaying on the Profile pages',3,'true:false',5,'true','false','false'),(222,'curriculum_tagging_enabled','0','Enable Curriculum Tagging','Allow Due Work, Folders and Quizzes to be associated with Curriculum Codes. (This setting should not be enabled before curricula have been set up.)',3,NULL,15,'true','false','false'),(223,'date_time','g:ia','Time Format','The time format used throughout the system.',1,NULL,6,'true','false','false'),(233,'webdav_auth_type','digest','WebDAV authentication type','Select a WebDAV authentication type. You should use \"Digest\" unless your school uses SAML, in which case you must use \"Basic\".',4,'digest:basic',4,'true','false','false'),(224,'date_local','d/m/Y','Local Date Format','The short date format used in form inputs. Changing this format will change the format that date form inputs use! Do not include a time format here.',1,NULL,6,'true','false','false'),(225,'date_only','jS M','Date Only Format','The date format used in calendar popups, and other places where the year is either implied or irrelevant. Do not include a time format here.',1,NULL,6,'true','false','false'),(226,'external_cache_timeout','300','External Cache Timeout','The amount of time (in seconds) that external DB call results should be cached for.',1,NULL,7,'true','false','false'),(227,'external_markbook','0','Enable Markbook Synchronisation','Enables markbook data to be automatically written back into the Synergetic markbook.',3,NULL,7,'true','false','false'),(228,'office365_tenant','','Microsoft Office 365 Tenant Name','The tenant name for your Office 365 installation',1,NULL,10,'true','false','false'),(229,'office365_sitepath','','Microsoft Office 365 Site Path','The site path for your Office 365 installation (optional, only if configured)',1,NULL,10,'true','false','false'),(230,'office365_client','','Microsoft Office 365 App ClientID','The client app ID for your Office 365 Schoolbox installation',1,NULL,10,'true','false','false'),(231,'office365_secret','','Microsoft Office 365 App Secret','The client secret for your Office 365 Schoolbox installation',1,NULL,10,'true','false','false'),(232,'external_staff_portal','','External Management Portal','Provide the full URL to your SIS Management Portal (ie. Synweb).  This will be used to provide quicklinks into the system',1,NULL,7,'true','false','false'),(234,'clickview_school_id','','Clickview School ID','The School ID that Clickview has provided you with, this is only required if you use SSO',1,'',10,'true','false','false'),(235,'external_tass_sso_server','','External TASS SSO Server','SSO Server for TASSWeb services. (Enter your TASSWeb URL in \"External Management Portal\".)',1,NULL,7,'true','true','false'),(236,'external_tass_sso_token_key','','External TASS SSO Token Key','SSO Token Key for TASSWeb services',1,NULL,7,'true','true','false'),(237,'external_tass_app_code_parent_lounge','','TASS Parent Lounge App Code','App code for TASS Parent Lounge (required for generating a SSO token)',1,NULL,7,'true','true','false'),(238,'external_tass_app_code_tassweb','','TASSWeb App Code','App code for TASSWeb (required for generating a SSO token)',1,NULL,7,'true','true','false'),(239,'external_tass_app_code_teacherkiosk','','TASS Teacher Kiosk App Code','App code for TASS Teacher Kiosk (required for generating a SSO token)',1,NULL,7,'true','true','false'),(240,'external_tass_app_code_webbook','','TASS WebBook Code','App code for TASS WebBook (required for generating a SSO token)',1,NULL,7,'true','true','false'),(241,'modules_parentdirectory_emailall','0','Allow Email All Parents from Parent Directory','If enabled, staff and parents may bulk email parents from the parent directory. (This setting respects the parent\'s directory privacy setting.)',3,NULL,5,'true','false','false'),(242,'external_tass_parent_id_length','7','TASS Parent ID length','Length of parent IDs as stored in TASS. (This needs to be set correctly for split family IDs at the end of a parent ID to be recognised.)',1,NULL,7,'true','true','false'),(243,'amqp_admin_port','15672','Message Queue Admin Port','The message queue admin subscription port',1,'',7,'true','true','true'),(244,'saml_auth_comparison','exact','Single Sign-On Authentication Type Comparison','Allow the SAML identity provider to choose the authentication type (Web Form, WIA/Kerberos) based on the browsers support, or not (Default: exact)',4,'exact:minimum:maximum:better',2,'true','false','false'),(245,'saml_krb_chrome','1','Single Sign-On Kerberos Chrome','Enable Chrome browser on desktop for using kerberos authentication with SAML.',3,'',2,'true','false','false');
/*!40000 ALTER TABLE `config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_unit_instance`
--

DROP TABLE IF EXISTS `course_unit_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_unit_instance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_category_id` int(11) NOT NULL,
  `title` varchar(250) NOT NULL,
  `open_date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_unit_instance_component_id` (`component_id`),
  KEY `course_unit_instance_unit_category_id` (`unit_category_id`),
  KEY `course_unit_instance_open_date` (`open_date`),
  KEY `course_unit_instance_due_date` (`due_date`),
  KEY `course_unit_instance_deleted_at` (`deleted_at`),
  KEY `course_unit_instance_category_id` (`category_id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_unit_instance`
--

LOCK TABLES `course_unit_instance` WRITE;
/*!40000 ALTER TABLE `course_unit_instance` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_unit_instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_learning_area`
--

DROP TABLE IF EXISTS `curriculum_learning_area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_learning_area` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_source_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `reference` varchar(500) NOT NULL,
  `order` tinyint(4) NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `curriculum_source_id_2` (`curriculum_source_id`,`name`),
  KEY `curriculum_source_id` (`curriculum_source_id`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `curriculum_learning_area_ibfk_1` FOREIGN KEY (`curriculum_source_id`) REFERENCES `curriculum_source` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_learning_area`
--

LOCK TABLES `curriculum_learning_area` WRITE;
/*!40000 ALTER TABLE `curriculum_learning_area` DISABLE KEYS */;
INSERT INTO `curriculum_learning_area` (`id`, `curriculum_source_id`, `name`, `description`, `reference`, `order`, `created_at`, `modified_at`, `deleted_at`) VALUES (1,2,'English','English','',0,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(2,2,'Mathematics','Mathematics','',1,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(3,2,'Science','Science','',2,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(4,2,'Arts','Arts','',3,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(5,2,'Humanities','Humanities','',4,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL);
/*!40000 ALTER TABLE `curriculum_learning_area` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_learning_level`
--

DROP TABLE IF EXISTS `curriculum_learning_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_learning_level` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_source_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `reference` varchar(500) NOT NULL,
  `order` tinyint(4) NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `curriculum_source_id_2` (`curriculum_source_id`,`name`),
  KEY `curriculum_source_id` (`curriculum_source_id`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `curriculum_year_level_ibfk_1` FOREIGN KEY (`curriculum_source_id`) REFERENCES `curriculum_source` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_learning_level`
--

LOCK TABLES `curriculum_learning_level` WRITE;
/*!40000 ALTER TABLE `curriculum_learning_level` DISABLE KEYS */;
INSERT INTO `curriculum_learning_level` (`id`, `curriculum_source_id`, `name`, `description`, `reference`, `order`, `created_at`, `modified_at`, `deleted_at`) VALUES (1,2,'ELC','Early Learning','',0,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(2,2,'Prep','Prep','',1,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(3,2,'Year 1','Year 1','',2,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(4,2,'Year 2','Year 2','',3,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(5,2,'Year 3','Year 3','',4,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(6,2,'Year 4','Year 4','',5,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(7,2,'Year 5','Year 5','',6,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(8,2,'Year 6','Year 6','',7,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(9,2,'Year 7','Year 7','',8,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(10,2,'Year 8','Year 8','',9,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(11,2,'Year 9','Year 9','',10,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(12,2,'Year 10','Year 10','',11,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(13,2,'Year 11','Year 11','',12,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(14,2,'Year 12','Year 12','',13,'2015-07-29 08:52:56','2015-07-29 08:52:56',NULL);
/*!40000 ALTER TABLE `curriculum_learning_level` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_node`
--

DROP TABLE IF EXISTS `curriculum_node`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_node` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_node_parent_id` int(11) DEFAULT NULL,
  `curriculum_learning_level_id` int(11) DEFAULT NULL,
  `curriculum_learning_area_id` int(11) NOT NULL,
  `name` varchar(500) NOT NULL,
  `code` varchar(64) NOT NULL,
  `reference` varchar(500) NOT NULL,
  `order` tinyint(4) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_node_ibfk_1` (`curriculum_learning_area_id`),
  KEY `curriculum_node_ibfk_2` (`curriculum_node_parent_id`),
  KEY `curriculum_node_ibfk_3` (`curriculum_learning_level_id`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `curriculum_node_ibfk_1` FOREIGN KEY (`curriculum_learning_area_id`) REFERENCES `curriculum_learning_area` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `curriculum_node_ibfk_2` FOREIGN KEY (`curriculum_node_parent_id`) REFERENCES `curriculum_node` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `curriculum_node_ibfk_3` FOREIGN KEY (`curriculum_learning_level_id`) REFERENCES `curriculum_learning_level` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_node`
--

LOCK TABLES `curriculum_node` WRITE;
/*!40000 ALTER TABLE `curriculum_node` DISABLE KEYS */;
/*!40000 ALTER TABLE `curriculum_node` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_node_for_assess`
--

DROP TABLE IF EXISTS `curriculum_node_for_assess`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_node_for_assess` (
  `curriculum_node_id` int(11) NOT NULL,
  `assess_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_assess_unique` (`curriculum_node_id`,`assess_id`),
  KEY `curriculum_node_for_assess_curriculum_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_assess_assess_id` (`assess_id`),
  CONSTRAINT `curriculum_node_for_assess_ibfk_1` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_node_for_assess`
--

LOCK TABLES `curriculum_node_for_assess` WRITE;
/*!40000 ALTER TABLE `curriculum_node_for_assess` DISABLE KEYS */;
/*!40000 ALTER TABLE `curriculum_node_for_assess` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_node_for_category`
--

DROP TABLE IF EXISTS `curriculum_node_for_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_node_for_category` (
  `curriculum_node_id` int(11) NOT NULL,
  `category_id` int(5) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_category_unique` (`curriculum_node_id`,`category_id`),
  KEY `curriculum_node_for_category_curriculum_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_category_category_id` (`category_id`),
  CONSTRAINT `curriculum_node_for_category_ibfk_1` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_node_for_category`
--

LOCK TABLES `curriculum_node_for_category` WRITE;
/*!40000 ALTER TABLE `curriculum_node_for_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `curriculum_node_for_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_node_for_submission_box`
--

DROP TABLE IF EXISTS `curriculum_node_for_submission_box`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_node_for_submission_box` (
  `curriculum_node_id` int(11) NOT NULL,
  `submission_box_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_submission_box_unique` (`curriculum_node_id`,`submission_box_id`),
  KEY `curriculum_node_for_submission_box_curriculum_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_submission_box_submission_box_id` (`submission_box_id`),
  CONSTRAINT `curriculum_node_for_submission_box_ibfk_1` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_node_for_submission_box`
--

LOCK TABLES `curriculum_node_for_submission_box` WRITE;
/*!40000 ALTER TABLE `curriculum_node_for_submission_box` DISABLE KEYS */;
/*!40000 ALTER TABLE `curriculum_node_for_submission_box` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_source`
--

DROP TABLE IF EXISTS `curriculum_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curriculum_source` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `class` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_source`
--

LOCK TABLES `curriculum_source` WRITE;
/*!40000 ALTER TABLE `curriculum_source` DISABLE KEYS */;
INSERT INTO `curriculum_source` (`id`, `name`, `class`, `description`, `created_at`, `modified_at`, `deleted_at`) VALUES (1,'Australian Curriculum','\\Schoolbox\\Curriculum\\Source\\AustralianCurriculum','Australian Curriculum, available at http://www.australiancurriculum.edu.au/','2015-07-29 08:52:56','2015-07-29 08:52:56',NULL),(2,'Custom','','Customised curriculum for your school','2015-07-29 08:52:56','2015-07-29 08:52:56',NULL);
/*!40000 ALTER TABLE `curriculum_source` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dailyquote_config`
--

DROP TABLE IF EXISTS `dailyquote_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dailyquote_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dailyquote_config`
--

LOCK TABLES `dailyquote_config` WRITE;
/*!40000 ALTER TABLE `dailyquote_config` DISABLE KEYS */;
INSERT INTO `dailyquote_config` (`id`, `name`, `value`) VALUES (1,'last_selected_date','2014-03-12'),(2,'last_selected_index','1');
/*!40000 ALTER TABLE `dailyquote_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dailyquote_data`
--

DROP TABLE IF EXISTS `dailyquote_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dailyquote_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` text,
  `author` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dailyquote_data`
--

LOCK TABLES `dailyquote_data` WRITE;
/*!40000 ALTER TABLE `dailyquote_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `dailyquote_data` ENABLE KEYS */;
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
  `deleted_at` datetime DEFAULT NULL,
  `hash` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `fk_dropbox_user1` (`owner`),
  KEY `deleted` (`deleted`),
  KEY `deleted_at` (`deleted_at`)
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
-- Table structure for table `eotc_activitylog`
--

DROP TABLE IF EXISTS `eotc_activitylog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_activitylog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actioner_id` int(11) DEFAULT NULL,
  `metadata_id` int(11) DEFAULT NULL,
  `message` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  KEY `id` (`id`),
  KEY `actioner_id` (`actioner_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_activitylog`
--

LOCK TABLES `eotc_activitylog` WRITE;
/*!40000 ALTER TABLE `eotc_activitylog` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_activitylog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_config`
--

DROP TABLE IF EXISTS `eotc_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_config`
--

LOCK TABLES `eotc_config` WRITE;
/*!40000 ALTER TABLE `eotc_config` DISABLE KEYS */;
INSERT INTO `eotc_config` (`id`, `name`, `value`) VALUES (1,'first_reminder','10'),(2,'daily_reminder','3'),(3,'urgent_threshold','3'),(4,'policy',NULL);
/*!40000 ALTER TABLE `eotc_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_event`
--

DROP TABLE IF EXISTS `eotc_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(6) DEFAULT NULL,
  `author_id` int(6) DEFAULT NULL,
  `teacher_in_charge_id` int(11) DEFAULT NULL,
  `type` int(10) unsigned DEFAULT NULL,
  `title` varchar(250) NOT NULL DEFAULT '',
  `startDate` date DEFAULT NULL COMMENT 'deprecated',
  `startTime` time DEFAULT NULL COMMENT 'deprecated',
  `start_datetime` datetime DEFAULT NULL,
  `endDate` date DEFAULT NULL COMMENT 'deprecated',
  `endTime` time DEFAULT NULL COMMENT 'deprecated',
  `end_datetime` datetime DEFAULT NULL,
  `detail` text,
  `location` varchar(250) DEFAULT NULL,
  `allday` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `start_datetime` (`start_datetime`),
  KEY `end_datetime` (`end_datetime`),
  KEY `series_id` (`series_id`),
  KEY `author_id` (`author_id`),
  KEY `teacher_in_charge_id` (`teacher_in_charge_id`),
  FULLTEXT KEY `fulltext` (`title`,`detail`,`location`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_event`
--

LOCK TABLES `eotc_event` WRITE;
/*!40000 ALTER TABLE `eotc_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_event_campus`
--

DROP TABLE IF EXISTS `eotc_event_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_event_campus` (
  `event_id` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  KEY `event_id` (`event_id`),
  KEY `campus_id` (`campus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_event_campus`
--

LOCK TABLES `eotc_event_campus` WRITE;
/*!40000 ALTER TABLE `eotc_event_campus` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_event_campus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_event_metadata`
--

DROP TABLE IF EXISTS `eotc_event_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_event_metadata` (
  `event_id` int(11) DEFAULT NULL,
  `metadata_id` int(11) DEFAULT NULL,
  UNIQUE KEY `event_metadata` (`event_id`,`metadata_id`),
  KEY `event_id` (`event_id`),
  KEY `metadata_id` (`metadata_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_event_metadata`
--

LOCK TABLES `eotc_event_metadata` WRITE;
/*!40000 ALTER TABLE `eotc_event_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_event_metadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_metadata`
--

DROP TABLE IF EXISTS `eotc_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type_id` int(11) DEFAULT NULL COMMENT 'FK for luEventType',
  `status_id` int(11) DEFAULT NULL COMMENT 'FK for eotc_status',
  `rams_required` tinyint(1) DEFAULT NULL,
  `comment` text COMMENT 'iAdditional notes',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_type_id` (`event_type_id`),
  KEY `status_id` (`status_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_metadata`
--

LOCK TABLES `eotc_metadata` WRITE;
/*!40000 ALTER TABLE `eotc_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_metadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_metadata_file`
--

DROP TABLE IF EXISTS `eotc_metadata_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_metadata_file` (
  `metadata_id` int(11) DEFAULT NULL,
  `storage_id` int(11) DEFAULT NULL,
  UNIQUE KEY `metadata_storage` (`metadata_id`,`storage_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `storage_id` (`storage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_metadata_file`
--

LOCK TABLES `eotc_metadata_file` WRITE;
/*!40000 ALTER TABLE `eotc_metadata_file` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_metadata_file` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_metadata_resourcebooking`
--

DROP TABLE IF EXISTS `eotc_metadata_resourcebooking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_metadata_resourcebooking` (
  `metadata_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  KEY `metadata_id` (`metadata_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_metadata_resourcebooking`
--

LOCK TABLES `eotc_metadata_resourcebooking` WRITE;
/*!40000 ALTER TABLE `eotc_metadata_resourcebooking` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_metadata_resourcebooking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_metadata_staff`
--

DROP TABLE IF EXISTS `eotc_metadata_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_metadata_staff` (
  `metadata_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  UNIQUE KEY `metadata_staff` (`metadata_id`,`user_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_metadata_staff`
--

LOCK TABLES `eotc_metadata_staff` WRITE;
/*!40000 ALTER TABLE `eotc_metadata_staff` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_metadata_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_metadata_student`
--

DROP TABLE IF EXISTS `eotc_metadata_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_metadata_student` (
  `metadata_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  UNIQUE KEY `metadata_student` (`metadata_id`,`user_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_metadata_student`
--

LOCK TABLES `eotc_metadata_student` WRITE;
/*!40000 ALTER TABLE `eotc_metadata_student` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_metadata_student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_permission`
--

DROP TABLE IF EXISTS `eotc_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `is_subscribed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `campus_id` (`campus_id`),
  KEY `status_id` (`status_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_permission`
--

LOCK TABLES `eotc_permission` WRITE;
/*!40000 ALTER TABLE `eotc_permission` DISABLE KEYS */;
/*!40000 ALTER TABLE `eotc_permission` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eotc_status`
--

DROP TABLE IF EXISTS `eotc_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eotc_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eotc_status`
--

LOCK TABLES `eotc_status` WRITE;
/*!40000 ALTER TABLE `eotc_status` DISABLE KEYS */;
INSERT INTO `eotc_status` (`id`, `name`) VALUES (1,'Initial Application'),(2,'Approved In Principle'),(3,'Details Completed'),(4,'Ready For Final Approval'),(5,'Approved'),(6,'Cancelled');
/*!40000 ALTER TABLE `eotc_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolioTemplate`
--

DROP TABLE IF EXISTS `eportfolioTemplate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolioTemplate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `layout` smallint(6) DEFAULT NULL,
  `left_column_width` float DEFAULT NULL,
  `right_column_width` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolioTemplate`
--

LOCK TABLES `eportfolioTemplate` WRITE;
/*!40000 ALTER TABLE `eportfolioTemplate` DISABLE KEYS */;
INSERT INTO `eportfolioTemplate` (`id`, `name`, `layout`, `left_column_width`, `right_column_width`) VALUES (1,'About Me',NULL,69.5,29.5),(2,'Career Aspirations',NULL,69.5,29.5),(3,'My Resume',NULL,69.5,29.5),(4,'My Learning',NULL,69.5,29.5);
/*!40000 ALTER TABLE `eportfolioTemplate` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_advancedlist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_advancedlist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_advancedlist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `imagehash` varchar(255) DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_advancedlist_item`
--

LOCK TABLES `eportfolio_component_advancedlist_item` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_advancedlist_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_advancedlist_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_basiclist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_basiclist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_basiclist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(11) NOT NULL,
  `content` text,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_basiclist_item`
--

LOCK TABLES `eportfolio_component_basiclist_item` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_basiclist_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_basiclist_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_blog`
--

DROP TABLE IF EXISTS `eportfolio_component_blog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_blog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_blog`
--

LOCK TABLES `eportfolio_component_blog` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_blog` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_blog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_blogcomment`
--

DROP TABLE IF EXISTS `eportfolio_component_blogcomment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_blogcomment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blog_id` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `content` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_blogcomment`
--

LOCK TABLES `eportfolio_component_blogcomment` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_blogcomment` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_blogcomment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_filelist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_filelist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_filelist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) DEFAULT NULL,
  `file_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_filelist_item`
--

LOCK TABLES `eportfolio_component_filelist_item` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_filelist_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_filelist_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_linklist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_linklist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_linklist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `sequence` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_linklist_item`
--

LOCK TABLES `eportfolio_component_linklist_item` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_linklist_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_linklist_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_component_textbox_item`
--

DROP TABLE IF EXISTS `eportfolio_component_textbox_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_component_textbox_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(11) NOT NULL,
  `content` text,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_component_textbox_item`
--

LOCK TABLES `eportfolio_component_textbox_item` WRITE;
/*!40000 ALTER TABLE `eportfolio_component_textbox_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_component_textbox_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_coverphoto`
--

DROP TABLE IF EXISTS `eportfolio_coverphoto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_coverphoto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL,
  `imagehash` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_coverphoto`
--

LOCK TABLES `eportfolio_coverphoto` WRITE;
/*!40000 ALTER TABLE `eportfolio_coverphoto` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_coverphoto` ENABLE KEYS */;
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
-- Table structure for table `eportfolio_page`
--

DROP TABLE IF EXISTS `eportfolio_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_page` (
  `page_id` int(11) NOT NULL AUTO_INCREMENT,
  `page_title` varchar(255) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `template` int(11) DEFAULT NULL,
  `left_column_width` float DEFAULT NULL,
  `right_column_width` float DEFAULT NULL,
  `left_head_font` varchar(7) DEFAULT NULL,
  `left_head_bg` varchar(7) DEFAULT NULL,
  `left_font` varchar(7) DEFAULT NULL,
  `left_bg_primary` varchar(7) DEFAULT NULL,
  `left_bg_secondary` varchar(7) DEFAULT NULL,
  `left_link` varchar(7) DEFAULT NULL,
  `left_hover` varchar(7) DEFAULT NULL,
  `right_head_font` varchar(7) DEFAULT NULL,
  `right_head_bg` varchar(7) DEFAULT NULL,
  `right_font` varchar(7) DEFAULT NULL,
  `right_bg_primary` varchar(7) DEFAULT NULL,
  `right_bg_secondary` varchar(7) DEFAULT NULL,
  `right_link` varchar(7) DEFAULT NULL,
  `right_hover` varchar(7) DEFAULT NULL,
  `top_head_font` varchar(7) DEFAULT NULL,
  `top_head_bg` varchar(7) DEFAULT NULL,
  `top_font` varchar(7) DEFAULT NULL,
  `top_bg_primary` varchar(7) DEFAULT NULL,
  `top_bg_secondary` varchar(7) DEFAULT NULL,
  `top_link` varchar(7) DEFAULT NULL,
  `top_hover` varchar(7) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`page_id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_page`
--

LOCK TABLES `eportfolio_page` WRITE;
/*!40000 ALTER TABLE `eportfolio_page` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_page` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_permission`
--

DROP TABLE IF EXISTS `eportfolio_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `type` enum('parent','group','student') NOT NULL DEFAULT 'student',
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_permission`
--

LOCK TABLES `eportfolio_permission` WRITE;
/*!40000 ALTER TABLE `eportfolio_permission` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_permission` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eportfolio_showcase_item`
--

DROP TABLE IF EXISTS `eportfolio_showcase_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eportfolio_showcase_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_box_id` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eportfolio_showcase_item`
--

LOCK TABLES `eportfolio_showcase_item` WRITE;
/*!40000 ALTER TABLE `eportfolio_showcase_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `eportfolio_showcase_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `error`
--

DROP TABLE IF EXISTS `error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `error` (
  `id` int(3) DEFAULT NULL,
  `message` text,
  KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `error`
--

LOCK TABLES `error` WRITE;
/*!40000 ALTER TABLE `error` DISABLE KEYS */;
INSERT INTO `error` (`id`, `message`) VALUES (1,'You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.'),(2,'Not enough information was submitted to the form.'),(3,'Please enter either a file or a url, not both.'),(4,'Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the schoolbox.<br><br>Please contact your System Administrator if problems persist.'),(5,'You do not have permission to download this file.'),(6,'You do not have write permission to this category.'),(7,'Mail cannot be sent at this time. Please try again later.'),(8,'You do not have permission to delete this resource.'),(9,'Processing failed for the following reason: %MESSAGE%'),(10,'The size of this file exceeds the allowed limit. %MESSAGE%'),(11,'%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Schoolbox.<br>If you would like to be able to add files of this type to the Schoolbox please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Schoolbox.'),(12,'The category that you are trying to delete is not empty. Please delete all its contents, then try again.'),(13,'The category that you are trying to view does not exist.'),(14,'You do not have permission to view this category.'),(15,'The date that you have submitted is invalid. Please check it, then try again.'),(16,'There was a problem validating your account: <br><b>%MESSAGE%</b>'),(17,'The submission box that you are trying to access does not exist or you do not have permission to view it.'),(18,'The subject selected does not exist.'),(19,'You do not have permission to view this page.'),(20,'You do not have permission to create a category in this location.'),(21,'A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%'),(22,'The database is unavailable at this time. Please try again later.'),(23,'You do not have permission to modify this news.'),(24,'You are not enrolled in this subject.'),(25,'You do not have permission to return this work to this student.'),(26,'The work you are trying to return either does not exist or has already been returned.'),(27,'The work you are trying to submit has already been submitted.'),(28,'You do not have permission to post in this category.'),(29,'You do not have permission to modify or delete this post.'),(30,'This user cannot be the group administrator.'),(31,'No user was found that matches your request.'),(32,'The absentee system cannot be accessed on the weekend.'),(33,'That period does not exist today.'),(34,'You do not have permission to modify this event.'),(35,'You do not have permission to add an event of this context.'),(36,'You are attempting to submit work to a submission box that has not yet opened.'),(37,'You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.'),(38,'This is already in the database. Please modify, then try again.'),(39,'No teams exist. You must add teams before trying to add events.'),(40,'Attendances for Grimwade have not yet been implemented. Please wait until further notice.'),(41,'That file does not exist.'),(42,'No email account information was found. Please either use an external mail client to send email, or provide the schoolbox with an email address.'),(43,'The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.'),(44,'You are trying to view or delete work that either does not exist or does not belong to you.'),(45,'Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".'),(46,'You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.'),(47,'You do not have permission to modify this user\'s file.'),(48,'You do not have permission to delete this user\'s file.'),(49,'The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.'),(50,'You do not have permission to delete this user\'s file.'),(51,'The section you are trying to add questions to does not exist.'),(52,'This link is already shared in your ePortfolio.'),(100,'Your e-portfolio has not been enabled.'),(101,'The resource you are trying to access does not exist or you do not have permission to view it.'),(102,'A booking already exists during the time you have selected.  Please press back and select a time that is free.'),(103,'The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.'),(104,'You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.'),(105,'<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>'),(106,'You do not have permission to modify or delete this link.'),(107,'You do not have permission to modify this blog.'),(108,'You do not have permission to leave comments on this blog.'),(109,'You do not have permission to publish this file.'),(110,'You do not have permission to modify these notes.'),(111,'There are no blogs in this archive.'),(112,'This quiz no longer exists.'),(113,'You have not answered all questions; please go back and submit them.'),(114,'You have not selected an attempt to review.'),(115,'This section no longer exists.'),(116,'Unspecified question type.'),(117,'Please choose a group for this quiz.'),(118,'This attempt no longer exists.'),(119,'This question no longer exists.'),(120,'This quiz has expired.'),(121,'You have already attempted this quiz and cannot re-sit it.'),(122,'You have run out of time to complete this quiz and cannot re-sit it.'),(123,'This attempt has not been recorded.'),(124,'You are the owner of this test and therefore cannot attempt it.'),(125,'You do not have permission to view this page.'),(126,'You do not have permissions to create and modify tests.'),(127,'Only the test owner can review this test.'),(128,'This attempt no longer exists.'),(129,'You are not allowed to view this students test.'),(130,'You do not have permissions to create and modify test contents.'),(131,'Question could not be saved.'),(132,'Answer could not be saved.'),(133,'Could not load question: unknown type.'),(134,'No import file found.'),(135,'This is not a valid Assess Question XML.'),(136,'Unable to create or modify folder as class code already exists.'),(137,'There are no questions to attempt in this quiz.'),(138,'This username already exists.'),(139,'The link provided is not a correctly formatted URL.'),(140,'You already have that link in your list.'),(141,'WebDAV error: %MESSAGE%'),(142,'You do not have permissions to delete this attempt(s).'),(143,'This quiz is not open yet.'),(144,'This Survey is not open yet.'),(145,'Start time cannot be later than finish time.'),(147,'Please specify a class code, date and period.'),(148,'This class does not exist.'),(149,'Please specify a date in the format: yyyy-mm-dd'),(150,'Please specify a valid period number.'),(151,'You cannot edit attendance records this far in the past.'),(152,'You cannot edit attendance records in the future.'),(153,'You are currently not enrolled in any classes so this page is unavailable'),(146,'You have reached the maximum number of attempts.'),(154,'You do not have write permission to any categories.'),(155,'You cannot view the Parent Directory for the specified year level.'),(156,'This page does not exist.'),(158,'Please select a campus.'),(157,'Please compose a message before posting to the SocialStream.'),(159,'This subject has no tasks associated with it yet.<br /><br />\n To add tasks to this subject, add a Course Outline component to this subject\'s homepage, and import a course into it.<br /><br />\n %MESSAGE%'),(162,'Leave request commences too soon.'),(160,'This event could not be found.'),(161,'Please upload a RAMS file.'),(163,'Unknown event status.'),(164,'This Collab Doc doesn\'t exist.'),(165,'An EOTC event may not be approved if there are no student attendees.'),(166,'You must select a category in the Question Bank.');
/*!40000 ALTER TABLE `error` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `etherpad_pad`
--

DROP TABLE IF EXISTS `etherpad_pad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etherpad_pad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pad_id` varchar(255) DEFAULT NULL COMMENT 'Etherpad Pad ID',
  `environment` varchar(255) DEFAULT NULL,
  `homepage_id` int(11) DEFAULT NULL COMMENT 'The homepage this pad started in',
  `name` varchar(255) DEFAULT NULL COMMENT 'Pad name',
  `body` text COMMENT 'Pad body',
  `lock` tinyint(1) DEFAULT '0' COMMENT 'Whether or not this pad is locked',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`),
  KEY `pad_id` (`pad_id`),
  KEY `homepage_id` (`homepage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etherpad_pad`
--

LOCK TABLES `etherpad_pad` WRITE;
/*!40000 ALTER TABLE `etherpad_pad` DISABLE KEYS */;
/*!40000 ALTER TABLE `etherpad_pad` ENABLE KEYS */;
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
  `author_id` int(6) DEFAULT NULL,
  `access` tinyint(1) NOT NULL DEFAULT '0',
  `sid` int(6) DEFAULT NULL,
  `priority` smallint(1) NOT NULL DEFAULT '0',
  `type` int(10) unsigned DEFAULT NULL,
  `title` varchar(250) NOT NULL DEFAULT '',
  `startDate` date DEFAULT NULL COMMENT 'deprecated',
  `startTime` time DEFAULT NULL COMMENT 'deprecated',
  `start_datetime` datetime DEFAULT NULL,
  `endDate` date DEFAULT NULL COMMENT 'deprecated',
  `endTime` time DEFAULT NULL COMMENT 'deprecated',
  `end_datetime` datetime DEFAULT NULL,
  `detail` text,
  `location` varchar(250) DEFAULT NULL,
  `attendance` tinyint(1) DEFAULT NULL,
  `publish_date` datetime DEFAULT NULL,
  `completed` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'For marking Tasks as complete or not.',
  `allday` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `access` (`access`,`sid`),
  KEY `owner` (`owner`),
  KEY `start_datetime` (`start_datetime`),
  KEY `end_datetime` (`end_datetime`),
  KEY `series_id` (`series_id`),
  KEY `author_id` (`author_id`),
  FULLTEXT KEY `fulltext` (`title`,`detail`,`location`)
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
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attendance`
--

LOCK TABLES `event_attendance` WRITE;
/*!40000 ALTER TABLE `event_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_campus`
--

DROP TABLE IF EXISTS `event_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_campus` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `campus_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`event_id`,`campus_id`),
  KEY `event_id` (`event_id`),
  KEY `campus_id` (`campus_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_campus`
--

LOCK TABLES `event_campus` WRITE;
/*!40000 ALTER TABLE `event_campus` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_campus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_category`
--

DROP TABLE IF EXISTS `event_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_category` (
  `event_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  UNIQUE KEY `event_category_id` (`event_id`,`category_id`),
  KEY `event_id` (`event_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_category`
--

LOCK TABLES `event_category` WRITE;
/*!40000 ALTER TABLE `event_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_group`
--

DROP TABLE IF EXISTS `event_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_group` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`event_id`,`group_id`),
  KEY `event_id` (`event_id`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_group`
--

LOCK TABLES `event_group` WRITE;
/*!40000 ALTER TABLE `event_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_user`
--

DROP TABLE IF EXISTS `event_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_user` (
  `event_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  UNIQUE KEY `event_user_id` (`event_id`,`user_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_user`
--

LOCK TABLES `event_user` WRITE;
/*!40000 ALTER TABLE `event_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_user` ENABLE KEYS */;
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
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `deleted` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `hash` varchar(40) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `deleted` (`deleted`),
  KEY `owner` (`owner`),
  KEY `hash` (`hash`),
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
-- Table structure for table `forumPost`
--

DROP TABLE IF EXISTS `forumPost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forumPost` (
  `postID` int(11) NOT NULL AUTO_INCREMENT,
  `threadID` int(11) DEFAULT NULL,
  `parentID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `body` text,
  `deleted` enum('true','false') NOT NULL DEFAULT 'false',
  `modified` datetime DEFAULT NULL,
  `censored` enum('true','false') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`postID`),
  KEY `threadID` (`threadID`),
  KEY `userID` (`userID`),
  KEY `deleted` (`deleted`),
  KEY `parentID` (`parentID`),
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
  `views` int(11) NOT NULL DEFAULT '0',
  `last_post_id` int(11) DEFAULT NULL,
  `last_post_date` datetime DEFAULT NULL,
  `last_post_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`threadID`),
  KEY `categoryID` (`categoryID`),
  KEY `userID` (`userID`),
  KEY `deleted` (`deleted`)
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
  `digest_schedule` varchar(24) NOT NULL DEFAULT '0' COMMENT '0: Never, 1: Weekly, 2: Daily, [pattern]: Crontab',
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` (`id`, `name`, `group_type`, `can_change_password`, `digest_schedule`) VALUES (1,'Administration','staff','true','0'),(2,'Teachers','staff','true','0'),(3,'Senior Students','student','true','0'),(4,'Parents','parent','true','0'),(5,'Junior Students','student','true','0');
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
INSERT INTO `homepageComponentCategory` (`homepageComponentCategoryID`, `name`) VALUES (1,'Information'),(2,'Collaboration'),(3,'Learning'),(4,'Multimedia'),(5,'Resources'),(6,'Feeds');
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
  `left_column_width` float DEFAULT '69.9',
  `right_column_width` float DEFAULT '29.9',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepageTemplate`
--

LOCK TABLES `homepageTemplate` WRITE;
/*!40000 ALTER TABLE `homepageTemplate` DISABLE KEYS */;
INSERT INTO `homepageTemplate` (`id`, `name`, `left_column_width`, `right_column_width`) VALUES (1,'Class',69.5,29.5),(2,'Collab Group',69.5,29.5),(4,'Faculty',69.5,29.5),(3,'Extra-Curricular',69.5,29.5),(5,'Committee',69.5,29.5),(6,'House',69.5,29.5),(7,'Blog',69.5,29.5),(8,'Course',69.5,29.5),(10,'Course Unit',69.5,29.5),(11,'Blank',69.5,29.5);
/*!40000 ALTER TABLE `homepageTemplate` ENABLE KEYS */;
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
INSERT INTO `homepage_config` (`cid`, `template`, `left_column_width`, `right_column_width`, `left_head_font`, `left_head_bg`, `left_font`, `left_bg_primary`, `left_bg_secondary`, `left_link`, `left_hover`, `right_head_font`, `right_head_bg`, `right_font`, `right_bg_primary`, `right_bg_secondary`, `right_link`, `right_hover`, `top_head_font`, `top_head_bg`, `top_font`, `top_bg_primary`, `top_bg_secondary`, `top_link`, `top_hover`) VALUES (1,0,0,0,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(2,0,0,0,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(3,0,69.5,29.5,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(4,0,69.5,29.5,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(5,0,69.5,29.5,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(6,0,0,0,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00'),(7,0,0,0,'000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00','000000','e5eff7','000000','ffffff','faf9f5','1d9ffc','ff5a00');
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
INSERT INTO `homepage_log` (`datetime`, `user`, `cid`, `hitcount`) VALUES ('2014-01-09 12:12:44',1,1,1),('2014-01-09 12:14:42',1,2,6),('2014-01-09 12:13:08',1,3,4),('2014-01-09 12:12:54',1,4,5),('2014-01-09 12:12:09',1,5,3),('2014-01-09 12:12:35',1,6,0),('2014-01-09 12:13:22',1,7,0);
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
INSERT INTO `homepage_visits` (`date`, `user_group_id`, `category_id`, `hitcount`) VALUES ('2014-01-09',1,1,2),('2014-01-09',1,2,7),('2014-01-09',1,3,3),('2014-01-09',1,4,4),('2014-01-09',1,5,2),('2014-01-09',1,6,1),('2014-01-09',1,7,1);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `house`
--

LOCK TABLES `house` WRITE;
/*!40000 ALTER TABLE `house` DISABLE KEYS */;
/*!40000 ALTER TABLE `house` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_request`
--

DROP TABLE IF EXISTS `leave_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `user_phone` varchar(255) NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime NOT NULL,
  `host_type` int(11) NOT NULL,
  `host_name` varchar(255) NOT NULL,
  `host_phone` varchar(255) NOT NULL,
  `host_mobile` varchar(255) DEFAULT NULL,
  `host_address` text,
  `collector_name` varchar(255) DEFAULT NULL,
  `returner_name` varchar(255) DEFAULT NULL,
  `reason` text,
  `description` varchar(255) NOT NULL,
  `created` datetime DEFAULT NULL,
  `status` int(11) NOT NULL,
  `authorisation_datetime` datetime DEFAULT NULL,
  `authorisation_user_id` int(11) DEFAULT NULL,
  `notes` text,
  `confirmed_by` int(11) DEFAULT NULL,
  `travel_type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_request_travel_type` (`travel_type_id`),
  KEY `user_id` (`user_id`),
  KEY `author_id` (`author_id`),
  KEY `start` (`start`),
  KEY `finish` (`finish`),
  KEY `confirmed_by` (`confirmed_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_request`
--

LOCK TABLES `leave_request` WRITE;
/*!40000 ALTER TABLE `leave_request` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_request` ENABLE KEYS */;
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
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `deleted` (`deleted_at`),
  KEY `owner` (`owner`),
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
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `date` (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_log`
--

LOCK TABLES `login_log` WRITE;
/*!40000 ALTER TABLE `login_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `luCampus`
--

DROP TABLE IF EXISTS `luCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `luCampus` (
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `ext_timetable_code` varchar(20) DEFAULT NULL,
  `timetable_day` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `fulltext` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luCampus`
--

LOCK TABLES `luCampus` WRITE;
/*!40000 ALTER TABLE `luCampus` DISABLE KEYS */;
INSERT INTO `luCampus` (`id`, `name`, `ext_timetable_code`, `timetable_day`) VALUES (1,'Senior','SEN',5),(2,'Junior','JUN',7);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `luServices`
--

LOCK TABLES `luServices` WRITE;
/*!40000 ALTER TABLE `luServices` DISABLE KEYS */;
INSERT INTO `luServices` (`id`, `name`) VALUES (1,'Homepage'),(2,'Calendar'),(3,'News'),(4,'Tutorial'),(5,'CMS'),(6,'Housemaster'),(7,'Resource Booking'),(8,'Curriculum');
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
-- Table structure for table `lu_config_field`
--

DROP TABLE IF EXISTS `lu_config_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_config_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lu_config_field_field` (`field`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_config_field`
--

LOCK TABLES `lu_config_field` WRITE;
/*!40000 ALTER TABLE `lu_config_field` DISABLE KEYS */;
INSERT INTO `lu_config_field` (`id`, `field`) VALUES (3,'boolean'),(6,'bytes'),(5,'checkbox'),(8,'html'),(1,'input'),(9,'resource'),(4,'select'),(7,'selectKey'),(10,'selectLookup'),(2,'textarea');
/*!40000 ALTER TABLE `lu_config_field` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_config_type`
--

DROP TABLE IF EXISTS `lu_config_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_config_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lu_config_type_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_config_type`
--

LOCK TABLES `lu_config_type` WRITE;
/*!40000 ALTER TABLE `lu_config_type` DISABLE KEYS */;
INSERT INTO `lu_config_type` (`id`, `type`) VALUES (10,'3rd Party Services'),(12,'Board Leave'),(9,'Colours'),(14,'Debugging'),(8,'Email'),(7,'External DB'),(16,'LDAP'),(15,'LMS Policy'),(6,'Localisation'),(11,'Modules'),(3,'Paths'),(5,'Policy'),(17,'Proxy'),(4,'Resources'),(2,'Security'),(1,'Site'),(13,'Welcome');
/*!40000 ALTER TABLE `lu_config_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_leave_request_host_type`
--

DROP TABLE IF EXISTS `lu_leave_request_host_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_leave_request_host_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_leave_request_host_type`
--

LOCK TABLES `lu_leave_request_host_type` WRITE;
/*!40000 ALTER TABLE `lu_leave_request_host_type` DISABLE KEYS */;
INSERT INTO `lu_leave_request_host_type` (`id`, `name`) VALUES (1,'Mother'),(2,'Father'),(3,'Guardian'),(4,'Self'),(5,'Other');
/*!40000 ALTER TABLE `lu_leave_request_host_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_leave_request_travel_type`
--

DROP TABLE IF EXISTS `lu_leave_request_travel_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_leave_request_travel_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_leave_request_travel_type`
--

LOCK TABLES `lu_leave_request_travel_type` WRITE;
/*!40000 ALTER TABLE `lu_leave_request_travel_type` DISABLE KEYS */;
INSERT INTO `lu_leave_request_travel_type` (`id`, `name`, `sequence`) VALUES (1,'Host\'s Car',NULL),(2,'Taxi',NULL),(3,'Train',NULL),(4,'Bus',NULL),(5,'Walk',NULL);
/*!40000 ALTER TABLE `lu_leave_request_travel_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_resource_booking_status`
--

DROP TABLE IF EXISTS `lu_resource_booking_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_resource_booking_status` (
  `id` tinyint(4) NOT NULL,
  `name` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_resource_booking_status`
--

LOCK TABLES `lu_resource_booking_status` WRITE;
/*!40000 ALTER TABLE `lu_resource_booking_status` DISABLE KEYS */;
INSERT INTO `lu_resource_booking_status` (`id`, `name`) VALUES (1,'Pending'),(2,'Approved'),(3,'Rejected');
/*!40000 ALTER TABLE `lu_resource_booking_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_resource_moderation_type`
--

DROP TABLE IF EXISTS `lu_resource_moderation_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_resource_moderation_type` (
  `id` tinyint(4) NOT NULL,
  `name` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_resource_moderation_type`
--

LOCK TABLES `lu_resource_moderation_type` WRITE;
/*!40000 ALTER TABLE `lu_resource_moderation_type` DISABLE KEYS */;
INSERT INTO `lu_resource_moderation_type` (`id`, `name`, `description`) VALUES (1,'Open','Any staff member may book this item, and the booking will be automatically confirmed'),(2,'Moderated','Any staff member may book this item, but a moderator must confirm the booking'),(3,'Managed','Only a moderator may book this item');
/*!40000 ALTER TABLE `lu_resource_moderation_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lu_submission_type`
--

DROP TABLE IF EXISTS `lu_submission_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lu_submission_type` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lu_submission_type`
--

LOCK TABLES `lu_submission_type` WRITE;
/*!40000 ALTER TABLE `lu_submission_type` DISABLE KEYS */;
INSERT INTO `lu_submission_type` (`id`, `name`) VALUES (1,'Assessment Task'),(2,'Assignment'),(3,'Homework'),(6,'Research Project'),(5,'Essay'),(7,'Class Work'),(8,'Presentation'),(9,'Oral'),(10,'Extension'),(11,'Quiz');
/*!40000 ALTER TABLE `lu_submission_type` ENABLE KEYS */;
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
) ENGINE=MyISAM AUTO_INCREMENT=32 DEFAULT CHARSET=utf8;
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
-- Table structure for table `mobile_app_registry`
--

DROP TABLE IF EXISTS `mobile_app_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mobile_app_registry` (
  `user_id` int(10) unsigned NOT NULL,
  `app_token` varchar(4096) COLLATE utf8_bin NOT NULL,
  `provider` enum('GCM','APNS') COLLATE utf8_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `touched_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user_id`,`app_token`(255),`provider`),
  KEY `app_token` (`app_token`(255)),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mobile_app_registry`
--

LOCK TABLES `mobile_app_registry` WRITE;
/*!40000 ALTER TABLE `mobile_app_registry` DISABLE KEYS */;
/*!40000 ALTER TABLE `mobile_app_registry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `msg_namespace`
--

DROP TABLE IF EXISTS `msg_namespace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_namespace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(32) COLLATE utf8_bin NOT NULL,
  `label` varchar(64) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `msg_namespace`
--

LOCK TABLES `msg_namespace` WRITE;
/*!40000 ALTER TABLE `msg_namespace` DISABLE KEYS */;
INSERT INTO `msg_namespace` (`id`, `slug`, `label`) VALUES (1,'duework','Due Work'),(2,'assess','Quiz'),(3,'blog','Blog'),(4,'forum','Forum'),(5,'news','News'),(6,'calendar','Calendar'),(8,'wiki','Wiki'),(10,'group','Groups'),(11,'cms','Content Management'),(13,'resource_booking','Resource Booking'),(14,'report_card','Grades'),(15,'sstream','Social Stream');
/*!40000 ALTER TABLE `msg_namespace` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `msg_reroute`
--

DROP TABLE IF EXISTS `msg_reroute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_reroute` (
  `user_id` int(10) unsigned NOT NULL,
  `msg_source_id` int(10) unsigned NOT NULL,
  `outgoing_level` int(10) unsigned DEFAULT NULL,
  `delivery` set('MU','DI','IN','MO','EM') COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`user_id`,`msg_source_id`),
  KEY `delivery` (`delivery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `msg_reroute`
--

LOCK TABLES `msg_reroute` WRITE;
/*!40000 ALTER TABLE `msg_reroute` DISABLE KEYS */;
/*!40000 ALTER TABLE `msg_reroute` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `msg_reroute_override`
--

DROP TABLE IF EXISTS `msg_reroute_override`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_reroute_override` (
  `user_id` int(10) unsigned NOT NULL,
  `msg_route_key` varchar(64) COLLATE utf8_bin NOT NULL,
  `outgoing_level` int(10) unsigned DEFAULT NULL,
  `delivery` set('MU','DI','IN','MO','EM') COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`user_id`,`msg_route_key`),
  KEY `user_id` (`user_id`),
  KEY `delivery` (`delivery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `msg_reroute_override`
--

LOCK TABLES `msg_reroute_override` WRITE;
/*!40000 ALTER TABLE `msg_reroute_override` DISABLE KEYS */;
/*!40000 ALTER TABLE `msg_reroute_override` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `msg_route_perm`
--

DROP TABLE IF EXISTS `msg_route_perm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_route_perm` (
  `msg_source_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `min_level` int(10) unsigned DEFAULT NULL,
  `max_level` int(10) unsigned DEFAULT NULL,
  `default_level` int(10) unsigned DEFAULT NULL,
  `mask` set('MU','DI','IN','MO','EM') COLLATE utf8_bin DEFAULT NULL,
  `role_default` set('MU','DI','IN','MO','EM') COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`msg_source_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `mask` (`mask`),
  KEY `role_default` (`role_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `msg_route_perm`
--

LOCK TABLES `msg_route_perm` WRITE;
/*!40000 ALTER TABLE `msg_route_perm` DISABLE KEYS */;
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`, `mask`, `role_default`) VALUES (1,3,200,300,250,'DI,IN,MO,EM','IN'),(1,5,200,300,250,'DI,IN,MO,EM','IN'),(2,3,200,300,250,'DI,IN,MO,EM','IN'),(2,5,200,300,250,'DI,IN,MO,EM','IN'),(3,3,200,300,250,'DI,IN,MO,EM','IN'),(3,5,200,300,250,'DI,IN,MO,EM','IN'),(4,3,250,300,300,'IN,MO,EM','IN,MO,EM'),(4,5,250,300,300,'IN,MO,EM','IN,MO,EM'),(5,3,200,300,250,'DI,IN,MO,EM','IN'),(5,5,200,300,250,'DI,IN,MO,EM','IN'),(6,3,200,300,250,'DI,IN,MO,EM','IN'),(6,5,200,300,250,'DI,IN,MO,EM','IN'),(7,3,200,300,250,'DI,IN,MO,EM','IN'),(7,5,200,300,250,'DI,IN,MO,EM','IN'),(8,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(8,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(9,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(9,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(10,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(10,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(11,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(12,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(13,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(14,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(15,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(16,3,200,300,250,'DI,IN,MO,EM','IN'),(16,5,200,300,250,'DI,IN,MO,EM','IN'),(17,3,200,300,250,'DI,IN,MO,EM','IN'),(17,5,200,300,250,'DI,IN,MO,EM','IN'),(18,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(19,3,200,300,250,'DI,IN,MO,EM','IN'),(19,5,200,300,250,'DI,IN,MO,EM','IN'),(20,3,250,300,300,'IN,MO,EM','IN,MO,EM'),(20,5,250,300,300,'IN,MO,EM','IN,MO,EM'),(21,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(22,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(22,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(23,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(23,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(24,3,200,300,250,'DI,IN,MO,EM','IN'),(24,5,200,300,250,'DI,IN,MO,EM','IN'),(25,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(26,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(26,5,0,300,250,'MU,DI,IN,MO,EM','IN'),(27,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(27,5,0,300,250,'MU,DI,IN,MO,EM','IN'),(28,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(28,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(29,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(29,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(30,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(30,5,0,300,250,'MU,DI,IN,MO,EM','IN'),(31,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(31,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(32,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(32,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(33,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(33,5,0,300,250,'MU,DI,IN,MO,EM','IN'),(34,3,200,300,200,'DI,IN,MO,EM','DI'),(34,5,200,300,200,'DI,IN,MO,EM','DI'),(35,4,200,300,200,'DI,IN,MO,EM','DI'),(36,1,200,300,200,'DI,IN,MO,EM','DI'),(36,2,200,300,200,'DI,IN,MO,EM','DI'),(37,3,0,300,0,'MU,DI,IN,MO,EM','MU'),(37,5,0,300,0,'MU,DI,IN,MO,EM','MU'),(38,1,0,300,0,'MU,DI,IN,MO,EM','MU'),(38,2,0,300,0,'MU,DI,IN,MO,EM','MU'),(39,4,0,300,0,'MU,DI,IN,MO,EM','MU'),(40,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(40,5,0,300,250,'MU,DI,IN,MO,EM','IN'),(41,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(41,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(42,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(43,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(43,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(44,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(44,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(45,3,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(45,5,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(46,4,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(47,3,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(47,5,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(48,4,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(49,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(49,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(50,1,0,300,0,'MU,DI,IN,MO,EM','MU'),(50,2,0,300,0,'MU,DI,IN,MO,EM','MU'),(51,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(51,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(52,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(52,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(53,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(53,5,0,300,250,'MU,DI,IN,MO,EM','IN'),(54,4,0,300,250,'MU,DI,IN,MO,EM','IN'),(55,1,0,300,200,'MU,DI,IN,MO,EM','DI'),(55,2,0,300,200,'MU,DI,IN,MO,EM','DI'),(56,3,0,300,200,'MU,DI,IN,MO,EM','DI'),(56,5,0,300,200,'MU,DI,IN,MO,EM','DI'),(57,4,0,300,200,'MU,DI,IN,MO,EM','DI'),(58,1,0,300,200,'MU,DI,IN,MO,EM','DI'),(58,2,0,300,200,'MU,DI,IN,MO,EM','DI'),(59,3,0,300,200,'MU,DI,IN,MO,EM','DI'),(59,5,0,300,200,'MU,DI,IN,MO,EM','DI'),(60,4,0,300,200,'MU,DI,IN,MO,EM','DI'),(61,1,0,300,0,'MU,DI,IN,MO,EM','MU'),(61,2,0,300,0,'MU,DI,IN,MO,EM','MU'),(62,3,0,300,0,'MU,DI,IN,MO,EM','MU'),(62,5,0,300,0,'MU,DI,IN,MO,EM','MU'),(63,4,0,300,0,'MU,DI,IN,MO,EM','MU'),(64,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(64,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(65,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(65,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(66,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(66,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(67,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(67,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(68,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(68,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(69,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(69,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(70,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(70,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(71,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(71,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(72,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(72,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(73,3,0,300,200,'MU,DI,IN,MO,EM','DI'),(73,5,0,300,200,'MU,DI,IN,MO,EM','DI'),(74,1,0,300,200,'MU,DI,IN,MO,EM','DI'),(74,2,0,300,200,'MU,DI,IN,MO,EM','DI'),(75,4,0,300,200,'MU,DI,IN,MO,EM','DI'),(76,3,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(76,5,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(77,1,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(77,2,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(78,4,0,300,300,'MU,DI,IN,MO,EM','IN,MO,EM'),(79,3,200,300,250,'DI,IN,MO,EM','IN'),(79,5,200,300,250,'DI,IN,MO,EM','IN'),(80,1,200,300,250,'DI,IN,MO,EM','IN'),(80,2,200,300,250,'DI,IN,MO,EM','IN'),(81,3,200,300,250,'DI,IN,MO,EM','IN'),(81,5,200,300,250,'DI,IN,MO,EM','IN'),(82,1,200,300,250,'DI,IN,MO,EM','IN'),(82,2,200,300,250,'DI,IN,MO,EM','IN'),(83,1,0,300,200,'MU,DI,IN,MO,EM','DI'),(83,2,0,300,200,'MU,DI,IN,MO,EM','DI'),(84,3,0,300,200,'MU,DI,IN,MO,EM','DI'),(84,5,0,300,200,'MU,DI,IN,MO,EM','DI'),(85,4,0,300,200,'MU,DI,IN,MO,EM','DI'),(86,1,0,300,200,'MU,DI,IN,MO,EM','DI'),(86,2,0,300,200,'MU,DI,IN,MO,EM','DI'),(87,3,0,300,200,'MU,DI,IN,MO,EM','DI'),(87,5,0,300,200,'MU,DI,IN,MO,EM','DI'),(88,4,0,300,200,'MU,DI,IN,MO,EM','DI'),(89,4,0,300,0,'MU,DI,IN,MO,EM','MU'),(90,4,0,300,0,'MU,DI,IN,MO,EM','MU'),(91,3,0,300,200,'MU,DI,IN,MO,EM','DI'),(91,5,0,300,200,'MU,DI,IN,MO,EM','DI'),(92,1,0,300,200,'MU,DI,IN,MO,EM','DI'),(92,2,0,300,200,'MU,DI,IN,MO,EM','DI'),(93,4,0,300,0,'MU,DI,IN,MO,EM','MU'),(94,1,0,300,250,'MU,DI,IN,MO,EM','IN'),(94,2,0,300,250,'MU,DI,IN,MO,EM','IN'),(95,3,0,300,250,'MU,DI,IN,MO,EM','IN'),(95,5,0,300,250,'MU,DI,IN,MO,EM','IN');
/*!40000 ALTER TABLE `msg_route_perm` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `msg_source`
--

DROP TABLE IF EXISTS `msg_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `namespace` int(10) NOT NULL,
  `key` varchar(64) COLLATE utf8_bin NOT NULL,
  `role_type` enum('student','parent','staff') COLLATE utf8_bin NOT NULL,
  `description` varchar(255) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `namespace` (`namespace`,`key`,`role_type`),
  KEY `namespace_2` (`namespace`,`key`),
  KEY `key` (`key`),
  KEY `namespace_3` (`namespace`),
  KEY `role_type` (`role_type`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `msg_source`
--

LOCK TABLES `msg_source` WRITE;
/*!40000 ALTER TABLE `msg_source` DISABLE KEYS */;
INSERT INTO `msg_source` (`id`, `namespace`, `key`, `role_type`, `description`) VALUES (1,1,'open','student','a submission box that you need to submit to opens'),(2,1,'upcoming','student','you have outstanding work that is due soon'),(3,1,'updated','student','a submission box is updated'),(4,1,'overdue','student','you have outstanding work that is overdue'),(5,1,'returned','student','a teacher marks your work'),(6,1,'datechanged','student','the due date of a submission box is changed'),(7,1,'renamed','student','a submission box is renamed'),(8,1,'renamed','staff','a submission box is renamed'),(9,1,'closed','staff','a submission box has closed with submissions outstanding'),(10,1,'submitted','staff','a student submits work'),(11,1,'returned','parent','a teacher marks your child\'s work'),(12,1,'overdue','parent','your child has outstanding work that is overdue'),(13,1,'upcoming','parent','your child has outstanding work that is due soon'),(14,1,'datechanged','parent','the due date of a submission box is changed'),(15,1,'renamed','parent','a submission box is renamed'),(16,2,'open','student','a quiz that you need to attempt opens'),(17,2,'upcoming','student','you have yet to attempt a quiz that is due soon'),(18,2,'upcoming','parent','your child has yet to attempt a quiz that is due soon'),(19,2,'updated','student','a quiz is updated'),(20,2,'overdue','student','you failed to attempt a quiz'),(21,2,'overdue','parent','your child failed to attempt a quiz'),(22,2,'closed','staff','a quiz you posted has closed'),(23,2,'attempted','staff','a student attempts a quiz you posted'),(24,2,'marked','student','a teacher marks your attempt of a quiz'),(25,2,'marked','parent','a teacher marks your child\'s attempt of a quiz'),(26,3,'commented','student','somebody comments on a blog post you have written'),(27,3,'followed','student','somebody comments after you on a blog post'),(28,3,'followed','staff','somebody comments after you on a blog post'),(29,3,'commented','staff','somebody comments on a blog post you have written'),(30,4,'followed','student','somebody posts on a forum'),(31,4,'followed','staff','somebody posts on a forum'),(32,4,'mentioned','staff','somebody mentions you on a forum'),(33,4,'mentioned','student','somebody mentions you on a forum'),(34,5,'posted','student','somebody posts school news'),(35,5,'posted','parent','somebody posts school news'),(36,5,'posted','staff','somebody posts school news'),(37,6,'upcoming','student','an event on your calendar is about to start'),(38,6,'upcoming','staff','an event on your calendar is about to start'),(39,6,'upcoming','parent','an event on your calendar is about to start'),(40,6,'updated','student','an event on your calendar is updated'),(41,6,'updated','staff','an event on your calendar is updated'),(42,6,'updated','parent','an event on your calendar is updated'),(49,10,'joined','staff','somebody joins a group of which you are an admin'),(50,10,'left','staff','somebody leaves a group of which you are an admin'),(51,10,'requested','staff','somebody requests access to a group you moderate'),(52,10,'approved','staff','your request to join a moderated group is approved'),(53,10,'approved','student','your request to join a moderated group is approved'),(54,10,'approved','parent','your request to join a moderated group is approved'),(55,11,'created','staff','somebody creates homepage content'),(56,11,'created','student','somebody creates homepage content'),(57,11,'created','parent','somebody creates homepage content'),(58,11,'updated','staff','somebody updates homepage content'),(59,11,'updated','student','somebody updates homepage content'),(60,11,'updated','parent','somebody updates homepage content'),(61,11,'deleted','staff','somebody deletes homepage content'),(62,11,'deleted','student','somebody deletes homepage content'),(63,11,'deleted','parent','somebody deletes homepage content'),(67,13,'created','staff','somebody books a resource'),(68,13,'updated','staff','somebody updates a booking for a resource'),(69,13,'approved','staff','somebody approves a booking for a resource'),(70,13,'pending','staff','somebody sets a booking for a resource as pending'),(71,13,'rejected','staff','somebody rejects a booking for a resource'),(72,13,'cancelled','staff','somebody cancels a booking for a resource'),(73,5,'published','student','somebody posts homepage news'),(74,5,'published','staff','somebody posts homepage news'),(75,5,'published','parent','somebody posts homepage news'),(76,14,'commented','student','somebody comments on your grades'),(77,14,'commented','staff','somebody comments on one of your students\' grades'),(78,14,'commented','parent','somebody comments on your child\'s grades'),(79,15,'posted','student','somebody posts in a Social Stream'),(80,15,'posted','staff','somebody posts in a Social Stream'),(81,15,'replied','student','somebody replies to a post in a Social Stream'),(82,15,'replied','staff','somebody replies to a post in a Social Stream'),(83,8,'revised','staff','somebody revises a wiki'),(84,8,'revised','student','somebody revises a wiki'),(85,8,'revised','parent','somebody revises a wiki'),(86,8,'created','staff','somebody creates a wiki'),(87,8,'created','student','somebody creates a wiki'),(88,8,'created','parent','somebody creates a wiki'),(89,3,'commented','parent','somebody comments on a blog post you have written'),(90,3,'followed','parent','somebody comments after you on a blog post'),(91,3,'posted','student','somebody creates a blog post'),(92,3,'posted','staff','somebody creates a blog post'),(93,3,'posted','parent','somebody creates a blog post'),(94,1,'resubmitted','staff','a student resubmits work'),(95,1,'deleted','student','a teacher removes your submitted work'),(96,13,'created','parent','somebody books a resource'),(97,13,'updated','parent','somebody updates a booking for a resource'),(98,13,'approved','parent','somebody approves a booking for a resource'),(99,13,'pending','parent','somebody sets a booking for a resource as pending'),(100,13,'rejected','parent','somebody rejects a booking for a resource'),(101,13,'cancelled','parent','somebody cancels a booking for a resource'),(102,13,'created','student','somebody books a resource'),(103,13,'updated','student','somebody updates a booking for a resource'),(104,13,'approved','student','somebody approves a booking for a resource'),(105,13,'pending','student','somebody sets a booking for a resource as pending'),(106,13,'rejected','student','somebody rejects a booking for a resource'),(107,13,'cancelled','student','somebody cancels a booking for a resource'),(108,2,'updated','parent','a quiz in your child\'s class is updated'),(109,1,'updated','parent','a submission box in your child\'s class is updated');
/*!40000 ALTER TABLE `msg_source` ENABLE KEYS */;
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
  `hash` varchar(40) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `deleted` (`deleted`),
  KEY `parent` (`parent`),
  KEY `owner` (`owner`),
  KEY `hash` (`hash`)
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
  UNIQUE KEY `id` (`id`),
  KEY `parent` (`parent`),
  KEY `owner` (`owner`)
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
INSERT INTO `newsCampus` (`id`, `campus`) VALUES (1,1),(1,2);
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
INSERT INTO `newsGroup` (`id`, `gid`) VALUES (1,1),(1,2),(1,3);
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
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`newsTopicID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsTopic`
--

LOCK TABLES `newsTopic` WRITE;
/*!40000 ALTER TABLE `newsTopic` DISABLE KEYS */;
INSERT INTO `newsTopic` (`newsTopicID`, `name`, `sequence`) VALUES (1,'General',NULL),(2,'Music',NULL),(3,'Sport',NULL),(4,'Academic',NULL);
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
  `item_heading` varchar(100) DEFAULT NULL,
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
  `item_image` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `access` (`item_topic_id`,`item_campus`),
  KEY `topic` (`item_topic_id`),
  KEY `published` (`item_datetime_published`),
  KEY `expires` (`item_datetime_expires`),
  KEY `deleted` (`item_deleted`),
  FULLTEXT KEY `fulltext` (`item_heading`,`item_body`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news_items`
--

LOCK TABLES `news_items` WRITE;
/*!40000 ALTER TABLE `news_items` DISABLE KEYS */;
INSERT INTO `news_items` (`item_id`, `item_topic_id`, `item_heading`, `item_datetime_posted`, `item_datetime_published`, `item_datetime_expires`, `item_datetime_modified`, `item_mainpage`, `item_body`, `item_deleted`, `item_owner`, `item_campus`, `item_group`, `item_image`) VALUES (1,1,'Welcome to Schoolbox Demonstration','2012-06-20 16:05:36','2012-06-20 00:00:00','2013-12-19 00:00:00','2012-11-26 09:07:46',0,'<p>Welcome to all new users visiting for the first time. &nbsp;For more information on Schoolbox please visit our <a href=\"http://wiki.schoolbox.com.au\" target=\"_blank\">wiki</a></p>',0,19,0,NULL,'5ae60cd92d4b13254fc22a12d20949137c87f5da');
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
  PRIMARY KEY (`cid`,`gid`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perms`
--

LOCK TABLES `perms` WRITE;
/*!40000 ALTER TABLE `perms` DISABLE KEYS */;
INSERT INTO `perms` (`cid`, `gid`, `type`) VALUES (1,5,0),(1,4,0),(1,3,0),(1,2,1),(1,1,2),(2,1,2),(2,2,2),(2,3,1),(2,4,1),(2,5,1),(3,1,2),(3,2,2),(3,3,1),(3,4,1),(3,5,1),(4,1,2),(4,2,2),(4,3,1),(4,4,1),(4,5,1),(5,5,1),(5,4,1),(5,3,1),(5,2,2),(5,1,2),(6,1,2),(6,2,2),(6,3,1),(6,4,1),(6,5,1),(7,1,2),(7,2,2),(7,3,1),(7,4,1),(7,5,1);
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
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `file_id` (`file_id`),
  KEY `plagscan_id` (`plagscan_id`)
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
  `created_by` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `close` datetime DEFAULT NULL,
  PRIMARY KEY (`pollID`),
  KEY `categoryID` (`categoryID`),
  KEY `created_by` (`created_by`),
  KEY `close` (`close`)
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
-- Table structure for table `reportcard_comment`
--

DROP TABLE IF EXISTS `reportcard_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportcard_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `commenter_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `body` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NULL DEFAULT NULL,
  `deleted` timestamp NULL DEFAULT NULL,
  `student_visibility` tinyint(1) NOT NULL DEFAULT '1',
  `parent_visibility` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`,`student_id`),
  KEY `created` (`created`),
  KEY `deleted` (`deleted`),
  KEY `student_id` (`student_id`),
  KEY `student_visibility` (`student_visibility`),
  KEY `parent_visibility` (`parent_visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportcard_comment`
--

LOCK TABLES `reportcard_comment` WRITE;
/*!40000 ALTER TABLE `reportcard_comment` DISABLE KEYS */;
/*!40000 ALTER TABLE `reportcard_comment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource`
--

DROP TABLE IF EXISTS `resource`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `resource_category_id` int(11) NOT NULL,
  `resource_moderation_type_id` tinyint(4) NOT NULL DEFAULT '1',
  `image_url` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `min_lead_time` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `min_reservation_time` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `max_reservation_time` mediumint(8) unsigned NOT NULL DEFAULT '86400',
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `storage_image_id` int(11) DEFAULT NULL,
  `image_hash` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `room_code` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_resource_category_id` (`resource_category_id`),
  KEY `resource_resource_moderation_type_id` (`resource_moderation_type_id`),
  KEY `resource_storage_image_id` (`storage_image_id`),
  KEY `resource_room_code` (`room_code`),
  KEY `deleted` (`deleted_at`),
  KEY `image_hash` (`image_hash`),
  CONSTRAINT `resource_resource_category_id` FOREIGN KEY (`resource_category_id`) REFERENCES `resource_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resource_resource_moderation_type_id` FOREIGN KEY (`resource_moderation_type_id`) REFERENCES `lu_resource_moderation_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource`
--

LOCK TABLES `resource` WRITE;
/*!40000 ALTER TABLE `resource` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_booking`
--

DROP TABLE IF EXISTS `resource_booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource_booking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `resource_booking_status_id` tinyint(4) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_booking_resource_id` (`resource_id`),
  KEY `resource_booking_resource_booking_status_id` (`resource_booking_status_id`),
  KEY `resource_booking_event_id` (`event_id`),
  KEY `resource_booking_reviewer_id` (`reviewer_id`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `resource_booking_resource_booking_status_id` FOREIGN KEY (`resource_booking_status_id`) REFERENCES `lu_resource_booking_status` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resource_booking_resource_id` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_booking`
--

LOCK TABLES `resource_booking` WRITE;
/*!40000 ALTER TABLE `resource_booking` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource_booking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_category`
--

DROP TABLE IF EXISTS `resource_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_category`
--

LOCK TABLES `resource_category` WRITE;
/*!40000 ALTER TABLE `resource_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_moderator`
--

DROP TABLE IF EXISTS `resource_moderator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource_moderator` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `moderator_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_id` (`resource_id`,`moderator_id`),
  KEY `resource_moderator_resource_id` (`resource_id`),
  KEY `resource_moderator_moderator_id` (`moderator_id`),
  CONSTRAINT `resource_moderator_resource_id` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_moderator`
--

LOCK TABLES `resource_moderator` WRITE;
/*!40000 ALTER TABLE `resource_moderator` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource_moderator` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_relation`
--

DROP TABLE IF EXISTS `resource_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource_relation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `extra_resource_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_id` (`resource_id`,`extra_resource_id`),
  KEY `resource_relation_resource_id` (`resource_id`),
  KEY `resource_relation_extra_resource_id` (`extra_resource_id`),
  CONSTRAINT `resource_relation_resource_id` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resource_relation_extra_resource_id` FOREIGN KEY (`extra_resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_relation`
--

LOCK TABLES `resource_relation` WRITE;
/*!40000 ALTER TABLE `resource_relation` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource_relation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_role_permission`
--

DROP TABLE IF EXISTS `resource_role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resource_role_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `role_id` int(4) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Are users in this role allowed to book this resource?',
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_role_unique` (`resource_id`,`role_id`),
  KEY `resource_role_role` (`role_id`),
  CONSTRAINT `resource_role_resource` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_role_permission`
--

LOCK TABLES `resource_role_permission` WRITE;
/*!40000 ALTER TABLE `resource_role_permission` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource_role_permission` ENABLE KEYS */;
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
  `name` varchar(250) DEFAULT NULL,
  `parent_id` int(11) NOT NULL,
  `parent_type` enum('submission','category','curriculum') NOT NULL DEFAULT 'category',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grouping` (`grouping`,`name`,`parent_id`,`parent_type`),
  KEY `deleted` (`deleted_at`),
  KEY `parent_id` (`parent_id`)
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
  PRIMARY KEY (`criterion_id`,`value`,`submission_box_id`),
  KEY `criterion_id` (`criterion_id`),
  KEY `submission_box_id` (`submission_box_id`),
  KEY `valid` (`valid`)
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
  PRIMARY KEY (`student_id`,`submission_box_id`,`criterion_id`,`tstamp`),
  KEY `student_id` (`student_id`),
  KEY `submission_box_id` (`submission_box_id`),
  KEY `criterion_id` (`criterion_id`),
  KEY `valid` (`valid`)
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
  PRIMARY KEY (`resultset_id`,`value`),
  KEY `resultset_id` (`resultset_id`)
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
  PRIMARY KEY (`criterion_id`,`submission_box_id`),
  KEY `criterion_id` (`criterion_id`),
  KEY `submission_box_id` (`submission_box_id`)
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
-- Table structure for table `scheduleq`
--

DROP TABLE IF EXISTS `scheduleq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduleq` (
  `id_hash` varchar(40) COLLATE utf8_bin NOT NULL,
  `identifier` blob NOT NULL,
  `command` varchar(32) COLLATE utf8_bin NOT NULL,
  `data` blob NOT NULL,
  PRIMARY KEY (`id_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheduleq`
--

LOCK TABLES `scheduleq` WRITE;
/*!40000 ALTER TABLE `scheduleq` DISABLE KEYS */;
/*!40000 ALTER TABLE `scheduleq` ENABLE KEYS */;
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
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`)
) ENGINE=MyISAM AUTO_INCREMENT=272 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenu`
--

LOCK TABLES `sideMenu` WRITE;
/*!40000 ALTER TABLE `sideMenu` DISABLE KEYS */;
INSERT INTO `sideMenu` (`id`, `parent`, `txt`, `url`, `target`, `sequence`) VALUES (230,0,'Contact Support','/feedback.php','',13),(4,0,'Faculties','#','',4),(8,0,'Sport','/homepage/469','',8),(208,0,'Schoolbox Administration','/adminv2/home','',0),(228,0,'Manage Groups','/group/join','',11),(236,0,'Houses','/homepage/51','',7),(241,252,'Schoolbox Wiki','http://wiki.schoolbox.com.au','',2),(242,252,'Schoolbox Website','http://schoolbox.com.au/','',1),(243,0,'Extra-Curricular','#','',5),(247,0,'Senior School Library','/homepage/467','',6),(249,0,'Staff Services','#','',2),(250,0,'Parent Services','#','',1),(251,0,'Student Services','#','',3),(252,0,'Links and Resources','#','',9),(253,251,'Create Boarder Leave Request','/modules/leave/create','',0),(254,249,'Boarder Leave Management','#','',1),(255,254,'View Boarder Leave Requests','/modules/leave/house/1','',0),(256,254,'View Weekend Leave Report','/modules/leave/report/house/1','',1),(257,254,'View Boarder Catering Report','/modules/leave/report/catering','',2),(260,249,'Staff Directory','/modules/report/staff','',0),(261,250,'Parent Directory','/modules/report/parents','',0),(262,0,'Change Password','/account/modify','',12),(263,0,'Weather Forecast','/weather','',10),(269,250,'Synergetic Parent Portal','/synergetic','',1),(270,252,'Schoolbox Videos','https://www.youtube.com/user/SchoolboxVideo','',3),(271,249,'Manage Curriculum','/curriculum','',2);
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
  PRIMARY KEY (`campus`,`id`),
  KEY `id` (`id`),
  KEY `campus` (`campus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenuCampus`
--

LOCK TABLES `sideMenuCampus` WRITE;
/*!40000 ALTER TABLE `sideMenuCampus` DISABLE KEYS */;
INSERT INTO `sideMenuCampus` (`id`, `campus`) VALUES (4,1),(8,1),(228,1),(230,1),(236,1),(241,1),(242,1),(243,1),(247,1),(249,1),(250,1),(251,1),(252,1),(262,1),(263,1),(270,1),(4,2),(8,2),(228,2),(230,2),(236,2),(241,2),(242,2),(243,2),(247,2),(249,2),(250,2),(251,2),(252,2),(262,2),(263,2),(270,2);
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
  PRIMARY KEY (`id`,`gid`),
  KEY `id` (`id`),
  KEY `gid` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sideMenuGroup`
--

LOCK TABLES `sideMenuGroup` WRITE;
/*!40000 ALTER TABLE `sideMenuGroup` DISABLE KEYS */;
INSERT INTO `sideMenuGroup` (`id`, `gid`) VALUES (4,1),(4,2),(4,3),(8,1),(8,2),(8,3),(8,4),(8,5),(228,1),(228,2),(228,3),(228,4),(228,5),(230,1),(230,2),(230,3),(230,4),(230,5),(236,1),(236,2),(236,3),(236,4),(236,5),(241,1),(241,2),(241,3),(241,4),(241,5),(242,1),(242,2),(242,3),(242,4),(242,5),(243,1),(243,2),(243,3),(243,4),(243,5),(247,1),(247,2),(247,3),(247,5),(249,1),(249,2),(250,1),(250,4),(251,1),(251,3),(251,4),(251,5),(252,1),(252,2),(252,3),(252,4),(252,5),(262,1),(262,2),(262,3),(262,4),(262,5),(263,1),(263,2),(263,3),(263,4),(263,5),(270,1),(270,2),(270,3),(270,4),(270,5);
/*!40000 ALTER TABLE `sideMenuGroup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `socialstream_post`
--

DROP TABLE IF EXISTS `socialstream_post`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `socialstream_post` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `root_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `homepage_id` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `mimetype` varchar(255) DEFAULT NULL,
  `cache_hash` varchar(40) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `url` varchar(1024) DEFAULT NULL,
  `external_title` varchar(255) DEFAULT NULL COMMENT 'Title for the external link',
  `external_body` text COMMENT 'Description for the external link',
  `body` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  KEY `deleted` (`deleted_at`),
  KEY `owner` (`owner_id`),
  KEY `homepage` (`homepage_id`),
  KEY `cache_hash` (`cache_hash`),
  KEY `root_id` (`root_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `socialstream_post`
--

LOCK TABLES `socialstream_post` WRITE;
/*!40000 ALTER TABLE `socialstream_post` DISABLE KEYS */;
/*!40000 ALTER TABLE `socialstream_post` ENABLE KEYS */;
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
  `ext` varchar(16) NOT NULL,
  `hash` varchar(100) NOT NULL,
  `filesize` int(11) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_on` datetime NOT NULL,
  `last_accessed` datetime DEFAULT NULL,
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `expired_at` datetime DEFAULT NULL,
  PRIMARY KEY (`storage_id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `deleted` (`expired_at`),
  KEY `ext` (`ext`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
  `hash` varchar(40) NOT NULL,
  `realname` varchar(255) NOT NULL DEFAULT '',
  `submitted` datetime DEFAULT NULL,
  `marked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `late` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `box_id` int(4) unsigned NOT NULL DEFAULT '0',
  `mime_type` varchar(100) DEFAULT NULL,
  `body` text,
  `draft` tinyint(1) NOT NULL DEFAULT '0',
  `filesize` int(11) DEFAULT NULL,
  `uuid` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`),
  KEY `student` (`owner`),
  KEY `hash` (`hash`)
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
  `heading` varchar(100) DEFAULT NULL,
  `datetime_posted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `datetime_published` date NOT NULL,
  `datetime_expires` date NOT NULL DEFAULT '0000-00-00',
  `body` mediumtext NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `code` int(6) unsigned NOT NULL DEFAULT '0',
  `owner` int(6) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category` (`code`),
  KEY `deleted` (`deleted`),
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
  `task_id` int(11) DEFAULT NULL,
  `task_instance_id` int(11) DEFAULT NULL,
  `publish_date` datetime DEFAULT NULL,
  `open_date` datetime DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `late_date` datetime DEFAULT '0000-00-00 00:00:00',
  `fid` int(5) unsigned DEFAULT '0',
  `accept_submission` tinyint(1) NOT NULL DEFAULT '0',
  `multi_submission` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `description` text NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `weight` int(11) unsigned NOT NULL DEFAULT '100',
  `mark_type` int(11) DEFAULT NULL,
  `submission_grade_group_id` int(11) DEFAULT NULL,
  `max_mark` int(11) DEFAULT NULL,
  `rubric_result_set` int(10) unsigned NOT NULL DEFAULT '0',
  `mark_visibility` int(11) DEFAULT '1',
  `skip_unsubmitted` int(1) NOT NULL DEFAULT '0' COMMENT 'Skip students who haven''t submitted Due Work, in the People Selector',
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `file` (`fid`),
  KEY `submission_box_task_id` (`task_id`),
  KEY `submission_box_task_instance_id` (`task_instance_id`),
  KEY `submission_grade_group_id` (`submission_grade_group_id`),
  KEY `deleted` (`deleted`),
  KEY `publish_date` (`publish_date`),
  KEY `open_date` (`open_date`),
  KEY `date` (`date`),
  KEY `late_date` (`late_date`)
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
-- Table structure for table `submission_box_file`
--

DROP TABLE IF EXISTS `submission_box_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_box_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `storage_id` varchar(40) NOT NULL,
  `box_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_id_2` (`storage_id`,`box_id`),
  KEY `storage_id` (`storage_id`),
  KEY `box_id` (`box_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_box_file`
--

LOCK TABLES `submission_box_file` WRITE;
/*!40000 ALTER TABLE `submission_box_file` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_box_file` ENABLE KEYS */;
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
  `is_default_for_new_submissions` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_errors`
--

LOCK TABLES `submission_errors` WRITE;
/*!40000 ALTER TABLE `submission_errors` DISABLE KEYS */;
INSERT INTO `submission_errors` (`id`, `code`, `display_as`, `normalise_as`, `is_default_for_new_submissions`) VALUES (1,'ABS','Absent',0,0),(2,'NA','Not Assessed',NULL,0),(3,'NS','Not Submitted',0,0),(4,'S','Submitted',100,0),(5,'PE','Pending',NULL,1);
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
  `submission_grade_group_id` int(11) NOT NULL,
  `lower_bound` int(11) NOT NULL,
  `raw_grade` int(11) NOT NULL,
  `caption` varchar(64) NOT NULL,
  PRIMARY KEY (`grade_id`),
  KEY `submission_grade_group_id` (`submission_grade_group_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_grade`
--

LOCK TABLES `submission_grade` WRITE;
/*!40000 ALTER TABLE `submission_grade` DISABLE KEYS */;
INSERT INTO `submission_grade` (`grade_id`, `submission_grade_group_id`, `lower_bound`, `raw_grade`, `caption`) VALUES (1,1,0,5,'E'),(2,1,10,15,'E+'),(3,1,20,25,'D'),(4,1,30,35,'D+'),(5,1,40,45,'C'),(6,1,50,55,'C+'),(7,1,60,65,'B'),(8,1,70,75,'B+'),(9,1,80,85,'A'),(10,1,90,95,'A+');
/*!40000 ALTER TABLE `submission_grade` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_grade_group`
--

DROP TABLE IF EXISTS `submission_grade_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_grade_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_grade_group`
--

LOCK TABLES `submission_grade_group` WRITE;
/*!40000 ALTER TABLE `submission_grade_group` DISABLE KEYS */;
INSERT INTO `submission_grade_group` (`id`, `name`) VALUES (1,'Standard');
/*!40000 ALTER TABLE `submission_grade_group` ENABLE KEYS */;
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
  PRIMARY KEY (`user`,`page`,`sid`),
  KEY `user` (`user`),
  KEY `page` (`page`),
  KEY `sid` (`sid`)
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
INSERT INTO `submission_mark_type` (`mark_type_id`, `hr_name`, `slug`, `comment`, `enabled`) VALUES (1,'Percentage (%)','percent','This should be a whole percentage between 0 and 100.',1),(2,'Letter Grade','letter','Select a letter grade from the list.',1),(3,'Total Mark','raw','This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.',1),(4,'Free Type (legacy support)','free','Should be a letter (A), score (15/20), or percentage (88%).',0),(5,'Rubric','rubric','To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.',1),(6,'None','none','This due work is not marked.',1);
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
  `marker` int(10) unsigned NOT NULL,
  `owner` int(11) NOT NULL DEFAULT '0',
  `hash` varchar(40) DEFAULT NULL,
  `comment` text,
  `mark` varchar(64) DEFAULT NULL,
  `norm_mark` decimal(14,11) DEFAULT NULL,
  `filename` varchar(80) DEFAULT NULL,
  `filesize` int(11) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `uuid` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `submitted` datetime DEFAULT NULL,
  `returned` datetime NOT NULL,
  `is_confirmation_of_submission` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`),
  KEY `deleted` (`deleted`),
  KEY `marker` (`marker`),
  KEY `hash` (`hash`),
  KEY `submitted` (`submitted`),
  KEY `returned` (`returned`)
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
-- Table structure for table `task`
--

DROP TABLE IF EXISTS `task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `title` varchar(250) NOT NULL,
  `link` varchar(250) DEFAULT NULL,
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT '0',
  `assess_id` int(11) DEFAULT NULL,
  `box_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_category_id` (`category_id`),
  KEY `task_assess_id` (`assess_id`),
  KEY `task_box_id` (`box_id`),
  KEY `task_user_id` (`user_id`),
  KEY `task_order` (`order`),
  KEY `task_deleted_at` (`deleted_at`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task`
--

LOCK TABLES `task` WRITE;
/*!40000 ALTER TABLE `task` DISABLE KEYS */;
/*!40000 ALTER TABLE `task` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_instance`
--

DROP TABLE IF EXISTS `task_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_instance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `course_unit_instance_id` int(11) NOT NULL,
  `title` varchar(250) NOT NULL,
  `link` varchar(250) DEFAULT NULL,
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT '0',
  `assess_id` int(11) DEFAULT NULL,
  `box_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_instance_task_id` (`task_id`),
  KEY `task_instance_course_unit_instance_id` (`course_unit_instance_id`),
  KEY `task_instance_assess_id` (`assess_id`),
  KEY `task_instance_box_id` (`box_id`),
  KEY `task_instance_order` (`order`),
  KEY `task_instance_deleted_at` (`deleted_at`),
  KEY `task_instance_user_id` (`user_id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_instance`
--

LOCK TABLES `task_instance` WRITE;
/*!40000 ALTER TABLE `task_instance` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_instance_completion`
--

DROP TABLE IF EXISTS `task_instance_completion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_instance_completion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_instance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `marking_user_id` int(11) DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `completion_status` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `task_instance_completion_task_instance_id` (`task_instance_id`),
  KEY `task_instance_completion_student_id` (`student_id`),
  KEY `task_instance_completion_marking_user_id` (`marking_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_instance_completion`
--

LOCK TABLES `task_instance_completion` WRITE;
/*!40000 ALTER TABLE `task_instance_completion` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_instance_completion` ENABLE KEYS */;
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
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`textBoxID`),
  KEY `categoryID` (`categoryID`),
  KEY `deleted` (`deleted`),
  FULLTEXT KEY `fulltext` (`heading`,`body`)
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
-- Table structure for table `tile`
--

DROP TABLE IF EXISTS `tile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tile_layout_id` int(11) NOT NULL,
  `title_text` varchar(250) NOT NULL,
  `title_color` varchar(30) NOT NULL COMMENT 'CSS color value',
  `title_background_color` varchar(30) NOT NULL COMMENT 'CSS color value',
  `title_position` tinyint(1) NOT NULL COMMENT '1: top, 2: bottom',
  `link` varchar(250) NOT NULL,
  `link_target` varchar(10) DEFAULT '_self',
  `background_image_id` int(11) DEFAULT NULL,
  `background_image_contained` tinyint(1) NOT NULL DEFAULT '0',
  `background_image_hash` varchar(40) DEFAULT NULL,
  `background_color` varchar(30) NOT NULL COMMENT 'RRGGBBAA',
  `background_position` tinyint(1) NOT NULL DEFAULT '5',
  `hover_color` varchar(30) NOT NULL COMMENT 'CSS color value',
  `order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `background_image_id` (`background_image_id`),
  KEY `tile_tile_layout` (`tile_layout_id`),
  KEY `deleted` (`deleted_at`),
  KEY `background_image_hash` (`background_image_hash`),
  CONSTRAINT `tile_tile_layout` FOREIGN KEY (`tile_layout_id`) REFERENCES `tile_layout` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tile`
--

LOCK TABLES `tile` WRITE;
/*!40000 ALTER TABLE `tile` DISABLE KEYS */;
/*!40000 ALTER TABLE `tile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tile_layout`
--

DROP TABLE IF EXISTS `tile_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tile_layout` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `layout_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1: grid, 2: column',
  `grid_width` int(4) NOT NULL DEFAULT '0',
  `grid_padding` tinyint(4) NOT NULL DEFAULT '0',
  `column_count` tinyint(1) NOT NULL DEFAULT '1',
  `column_padding` tinyint(4) NOT NULL DEFAULT '0',
  `aspect_ratio_x` tinyint(2) NOT NULL DEFAULT '16',
  `aspect_ratio_y` tinyint(2) NOT NULL DEFAULT '9',
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tile_layout`
--

LOCK TABLES `tile_layout` WRITE;
/*!40000 ALTER TABLE `tile_layout` DISABLE KEYS */;
/*!40000 ALTER TABLE `tile_layout` ENABLE KEYS */;
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
  PRIMARY KEY (`cycle_id`),
  KEY `start` (`start`),
  KEY `reset` (`reset`),
  KEY `finish` (`finish`)
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
  UNIQUE KEY `day` (`day`,`sequence`,`cycle_id`),
  UNIQUE KEY `title` (`title`,`day`,`cycle_id`)
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
  `year` varchar(3) DEFAULT NULL,
  `house` int(11) DEFAULT NULL,
  `residential_house_id` int(11) DEFAULT NULL,
  `boarding_status` varchar(255) DEFAULT NULL,
  `local_email` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `webmail` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `alt_email` varchar(60) DEFAULT NULL,
  `mail_handler` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `hash` varchar(40) DEFAULT NULL,
  `digest` varchar(250) DEFAULT NULL,
  `key` varchar(40) DEFAULT NULL,
  `eportfolio_enabled` int(8) NOT NULL DEFAULT '0',
  `calendar_view` enum('month','agendaWeek','agendaDay') NOT NULL DEFAULT 'month' COMMENT 'Remembers the last view of the calendar the user used. To be refactored into preferences system.',
  `enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Is this account active?',
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
INSERT INTO `user` (`id`, `username`, `password`, `group_id`, `title`, `firstname`, `lastname`, `email_pass`, `synergy_id`, `year`, `house`, `residential_house_id`, `boarding_status`, `local_email`, `webmail`, `alt_email`, `mail_handler`, `hash`, `digest`, `key`, `eportfolio_enabled`, `calendar_view`, `enabled`) VALUES (1,'alaress','*CF13A85DDC912117E33FD86AC6E1EB94CB1D973F',1,NULL,'Alaress','Admin','0','0',NULL,NULL,NULL,NULL,0,1,'support@alaress.com.au',0,'171303c6b137d38a01d26b66600dda58',NULL,NULL,0,'month',1);
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
  KEY `sid` (`sid`),
  KEY `service` (`service`)
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
  PRIMARY KEY (`uid`,`campus`),
  KEY `uid` (`uid`),
  KEY `campus` (`campus`)
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
  `force_follow` tinyint(1) unsigned NOT NULL DEFAULT '0',
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
-- Table structure for table `user_activity_log`
--

DROP TABLE IF EXISTS `user_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `date` datetime NOT NULL,
  `ip` varchar(15) NOT NULL,
  `event` varchar(20) NOT NULL,
  `message` varchar(250) NOT NULL,
  `level` enum('error','warn','info','debug','trace') NOT NULL DEFAULT 'info',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `date` (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_activity_log`
--

LOCK TABLES `user_activity_log` WRITE;
/*!40000 ALTER TABLE `user_activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_activity_log` ENABLE KEYS */;
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_assume_log`
--

LOCK TABLES `user_assume_log` WRITE;
/*!40000 ALTER TABLE `user_assume_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_assume_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_link`
--

DROP TABLE IF EXISTS `user_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_link` (
  `user_id` int(10) unsigned NOT NULL,
  `linked_user_id` int(10) unsigned NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`linked_user_id`),
  KEY `user_id` (`user_id`),
  KEY `linked_user_id` (`linked_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_link`
--

LOCK TABLES `user_link` WRITE;
/*!40000 ALTER TABLE `user_link` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_profile`
--

DROP TABLE IF EXISTS `user_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_profile` (
  `user_id` int(11) NOT NULL,
  `dob` date DEFAULT NULL,
  `level` int(20) DEFAULT NULL,
  `homegroup` varchar(255) DEFAULT NULL,
  `house` varchar(255) DEFAULT NULL,
  `guardian1Name` varchar(255) DEFAULT NULL,
  `guardian1HomePhone` varchar(255) DEFAULT NULL,
  `guardian1WorkPhone` varchar(255) DEFAULT NULL,
  `guardian1MobilePhone` varchar(255) DEFAULT NULL,
  `guardian1Address` varchar(255) DEFAULT NULL,
  `guardian1Suburb` varchar(255) DEFAULT NULL,
  `guardian1Postcode` varchar(255) DEFAULT NULL,
  `guardian1Email` varchar(255) DEFAULT NULL,
  `guardian1MailAddress` varchar(255) DEFAULT NULL,
  `guardian2Name` varchar(255) DEFAULT NULL,
  `guardian2HomePhone` varchar(255) DEFAULT NULL,
  `guardian2WorkPhone` varchar(255) DEFAULT NULL,
  `guardian2MobilePhone` varchar(255) DEFAULT NULL,
  `guardian2Address` varchar(255) DEFAULT NULL,
  `guardian2Suburb` varchar(255) DEFAULT NULL,
  `guardian2Postcode` varchar(255) DEFAULT NULL,
  `guardian2Email` varchar(255) DEFAULT NULL,
  `guardian2MailAddress` varchar(255) DEFAULT NULL,
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
  PRIMARY KEY (`user_id`,`relation_id`),
  KEY `user_id` (`user_id`),
  KEY `relation_id` (`relation_id`)
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
-- Table structure for table `video_queue`
--

DROP TABLE IF EXISTS `video_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_queue` (
  `video_queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(10) unsigned NOT NULL,
  `hash` varchar(40) DEFAULT NULL,
  `type` enum('sbfs','storage','submission','myfile') NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL,
  `message` text,
  `duration` int(11) DEFAULT NULL,
  `createdOn` datetime NOT NULL,
  `modifiedOn` datetime NOT NULL,
  PRIMARY KEY (`video_queue_id`),
  KEY `file_id` (`file_id`),
  KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_queue`
--

LOCK TABLES `video_queue` WRITE;
/*!40000 ALTER TABLE `video_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `video_queue` ENABLE KEYS */;
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
  KEY `category_slug` (`categoryID`,`slug`),
  KEY `categoryID` (`categoryID`),
  KEY `deleted` (`deleted`)
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
  PRIMARY KEY (`openID`),
  KEY `wiki` (`wikiID`),
  KEY `user` (`userID`)
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
  KEY `userID` (`userID`),
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

-- Dump completed on 2015-07-29  9:13:45
