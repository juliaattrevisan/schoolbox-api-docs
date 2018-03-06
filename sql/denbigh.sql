-- MySQL dump 10.16  Distrib 10.1.31-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: denbigh
-- ------------------------------------------------------
-- Server version	10.1.31-MariaDB-1~trusty

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
-- Table structure for table `Addresses`
--

DROP TABLE IF EXISTS `Addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `addressType` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `addressDenbighID` varchar(16) CHARACTER SET latin1 NOT NULL,
  `familyDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `mailingTitle` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `property` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `street` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `suburb` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `state` varchar(64) CHARACTER SET latin1 DEFAULT NULL,
  `postcode` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `telephoneHome` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `familyDenbighID` (`familyDenbighID`),
  CONSTRAINT `Addresses_ibfk_1` FOREIGN KEY (`familyDenbighID`) REFERENCES `Family` (`familyDenbighID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Classes`
--

DROP TABLE IF EXISTS `Classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courseCode` varchar(32) CHARACTER SET latin1 NOT NULL,
  `staffCode` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `classCode` varchar(32) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `courseCode` (`courseCode`,`staffCode`,`classCode`),
  KEY `courseCode_2` (`courseCode`),
  KEY `staffCode` (`staffCode`),
  KEY `classCode` (`classCode`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Courses`
--

DROP TABLE IF EXISTS `Courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courseCode` varchar(32) CHARACTER SET latin1 NOT NULL,
  `subjectName` varchar(255) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `courseCode` (`courseCode`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Enrolments`
--

DROP TABLE IF EXISTS `Enrolments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Enrolments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classCode` varchar(32) CHARACTER SET latin1 NOT NULL,
  `studentID` varchar(16) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `classCode` (`classCode`,`studentID`),
  KEY `studentID` (`studentID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Family`
--

DROP TABLE IF EXISTS `Family`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Family` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `familyDenbighID` varchar(16) CHARACTER SET latin1 NOT NULL,
  `familyName` varchar(255) CHARACTER SET latin1 NOT NULL,
  `email` text CHARACTER SET latin1,
  `EmergencyContact1` text,
  `EmergencyContact2` text,
  `EmergencyRelationship1` text,
  `EmergencyRelationship2` text,
  `EmergencyPhone1` text,
  `EmergencyPhone2` text,
  `EmergencyEmail1` text,
  `EmergencyEmail2` text,
  PRIMARY KEY (`id`),
  KEY `familyDenbighID` (`familyDenbighID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Houses`
--

DROP TABLE IF EXISTS `Houses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Houses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `house` varchar(128) CHARACTER SET latin1 DEFAULT NULL,
  `house_abbreviation` varchar(6) CHARACTER SET latin1 DEFAULT NULL,
  `housemaster_id` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Lessons`
--

DROP TABLE IF EXISTS `Lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day` int(11) NOT NULL,
  `period` int(11) NOT NULL,
  `startTime` time DEFAULT NULL,
  `endTime` time DEFAULT NULL,
  `classCode` varchar(32) CHARACTER SET latin1 DEFAULT NULL,
  `staffCode` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `room` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `staff_TT_Desc` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `student_TT_Desc` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `room_TT_Desc` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `staffCodeSecond` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classCode` (`classCode`,`staffCode`,`room`),
  KEY `staffCode` (`staffCode`),
  KEY `room` (`room`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Parents`
--

DROP TABLE IF EXISTS `Parents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peopleDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `familyDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `relationship` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `type` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `deltaFamilyCode` varchar(50) CHARACTER SET latin1 DEFAULT NULL,
  `loginID` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `deceased` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `familyDenbighID` (`familyDenbighID`),
  KEY `peopleDenbighID` (`peopleDenbighID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `People`
--

DROP TABLE IF EXISTS `People`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `People` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `denbighID` varchar(16) CHARACTER SET latin1 NOT NULL,
  `surname` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `given_names` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `preferred_name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `title` varchar(32) CHARACTER SET latin1 DEFAULT NULL,
  `gender` varchar(6) CHARACTER SET latin1 DEFAULT NULL,
  `dateOfBirth` date DEFAULT NULL,
  `deltalinkID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `email` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `denbighID` (`denbighID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Rooms`
--

DROP TABLE IF EXISTS `Rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room` varchar(255) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `room` (`room`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `Schoolbox_Students`
--

DROP TABLE IF EXISTS `Schoolbox_Students`;
/*!50001 DROP VIEW IF EXISTS `Schoolbox_Students`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `Schoolbox_Students` (
  `surname` tinyint NOT NULL,
  `firstname` tinyint NOT NULL,
  `birthdate` tinyint NOT NULL,
  `deltalinkID` tinyint NOT NULL,
  `username` tinyint NOT NULL,
  `cohort` tinyint NOT NULL,
  `academicYear` tinyint NOT NULL,
  `tutorGroup` tinyint NOT NULL,
  `denbighID` tinyint NOT NULL,
  `familyDenbighID` tinyint NOT NULL,
  `boardingStatus` tinyint NOT NULL,
  `email` tinyint NOT NULL,
  `house` tinyint NOT NULL,
  `cateringHouse` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Staff`
--

DROP TABLE IF EXISTS `Staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peopleDenbighID` varchar(24) CHARACTER SET latin1 DEFAULT NULL,
  `staffCode` varchar(24) CHARACTER SET latin1 DEFAULT NULL,
  `tutorGroup` varchar(32) CHARACTER SET latin1 DEFAULT NULL,
  `campus` varchar(24) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `peopleDenbighID` (`peopleDenbighID`),
  KEY `staffCode` (`staffCode`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Students`
--

DROP TABLE IF EXISTS `Students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peopleDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `familyDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `academicYear` int(11) DEFAULT NULL,
  `tutorGroup` varchar(32) CHARACTER SET latin1 DEFAULT NULL,
  `house` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `residentialHouse` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `cohort` varchar(25) CHARACTER SET latin1 DEFAULT NULL,
  `loginIDforIT` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `cateringHouse` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `boardingStatus` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `peopleDenbighID` (`peopleDenbighID`),
  KEY `familyDenbighID` (`familyDenbighID`),
  CONSTRAINT `Students_ibfk_1` FOREIGN KEY (`peopleDenbighID`) REFERENCES `People` (`denbighID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Students_ibfk_2` FOREIGN KEY (`familyDenbighID`) REFERENCES `Family` (`familyDenbighID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `Schoolbox_Students`
--

/*!50001 DROP TABLE IF EXISTS `Schoolbox_Students`*/;
/*!50001 DROP VIEW IF EXISTS `Schoolbox_Students`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `Schoolbox_Students` AS select `People`.`surname` AS `surname`,`People`.`preferred_name` AS `firstname`,`People`.`dateOfBirth` AS `birthdate`,`People`.`deltalinkID` AS `deltalinkID`,`Students`.`loginIDforIT` AS `username`,`Students`.`cohort` AS `cohort`,`Students`.`academicYear` AS `academicYear`,`Students`.`tutorGroup` AS `tutorGroup`,`People`.`denbighID` AS `denbighID`,`Students`.`familyDenbighID` AS `familyDenbighID`,`Students`.`boardingStatus` AS `boardingStatus`,`People`.`email` AS `email`,`Students`.`house` AS `house`,`Students`.`cateringHouse` AS `cateringHouse` from (`People` join `Students`) where (`Students`.`peopleDenbighID` = `People`.`denbighID`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-03-06 10:50:12
