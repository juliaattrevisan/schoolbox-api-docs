/*
SQLyog Ultimate v12.11 (64 bit)
MySQL - 5.5.37-0ubuntu0.12.04.1 : Database - denbigh
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`denbigh` /*!40100 DEFAULT CHARACTER SET latin1 */;

/*Table structure for table `Addresses` */

DROP TABLE IF EXISTS `Addresses`;

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
) ENGINE=InnoDB AUTO_INCREMENT=1969 DEFAULT CHARSET=utf8;

/*Table structure for table `Classes` */

DROP TABLE IF EXISTS `Classes`;

CREATE TABLE `Classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courseCode` varchar(16) CHARACTER SET latin1 NOT NULL,
  `staffCode` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `classCode` varchar(16) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `courseCode` (`courseCode`,`staffCode`,`classCode`),
  KEY `courseCode_2` (`courseCode`),
  KEY `staffCode` (`staffCode`),
  KEY `classCode` (`classCode`)
) ENGINE=InnoDB AUTO_INCREMENT=2710 DEFAULT CHARSET=utf8;

/*Table structure for table `Courses` */

DROP TABLE IF EXISTS `Courses`;

CREATE TABLE `Courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `courseCode` varchar(16) CHARACTER SET latin1 NOT NULL,
  `subjectName` varchar(255) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `courseCode` (`courseCode`)
) ENGINE=InnoDB AUTO_INCREMENT=2492 DEFAULT CHARSET=utf8;

/*Table structure for table `Enrolments` */

DROP TABLE IF EXISTS `Enrolments`;

CREATE TABLE `Enrolments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classCode` varchar(16) CHARACTER SET latin1 NOT NULL,
  `studentID` varchar(16) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `classCode` (`classCode`,`studentID`),
  KEY `studentID` (`studentID`)
) ENGINE=InnoDB AUTO_INCREMENT=44731 DEFAULT CHARSET=utf8;

/*Table structure for table `Family` */

DROP TABLE IF EXISTS `Family`;

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
) ENGINE=InnoDB AUTO_INCREMENT=550 DEFAULT CHARSET=utf8;

/*Table structure for table `Houses` */

DROP TABLE IF EXISTS `Houses`;

CREATE TABLE `Houses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `house` varchar(128) CHARACTER SET latin1 DEFAULT NULL,
  `house_abbreviation` varchar(6) CHARACTER SET latin1 DEFAULT NULL,
  `housemaster_id` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

/*Table structure for table `Lessons` */

DROP TABLE IF EXISTS `Lessons`;

CREATE TABLE `Lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day` int(11) NOT NULL,
  `period` int(11) NOT NULL,
  `classCode` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=6693 DEFAULT CHARSET=utf8;

/*Table structure for table `Parents` */

DROP TABLE IF EXISTS `Parents`;

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
) ENGINE=InnoDB AUTO_INCREMENT=1103 DEFAULT CHARSET=utf8;

/*Table structure for table `People` */

DROP TABLE IF EXISTS `People`;

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
) ENGINE=InnoDB AUTO_INCREMENT=1896 DEFAULT CHARSET=utf8;

/*Table structure for table `Rooms` */

DROP TABLE IF EXISTS `Rooms`;

CREATE TABLE `Rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room` varchar(255) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `room` (`room`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8;

/*Table structure for table `Staff` */

DROP TABLE IF EXISTS `Staff`;

CREATE TABLE `Staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peopleDenbighID` varchar(16) CHARACTER SET latin1 NOT NULL,
  `staffCode` varchar(16) CHARACTER SET latin1 NOT NULL,
  `tutorGroup` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `peopleDenbighID` (`peopleDenbighID`),
  KEY `staffCode` (`staffCode`),
  CONSTRAINT `Staff_ibfk_1` FOREIGN KEY (`peopleDenbighID`) REFERENCES `People` (`denbighID`)
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8;

/*Table structure for table `Students` */

DROP TABLE IF EXISTS `Students`;

CREATE TABLE `Students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peopleDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `familyDenbighID` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `academicYear` int(11) DEFAULT NULL,
  `tutorGroup` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=779 DEFAULT CHARSET=utf8;

/*Table structure for table `Schoolbox_Students` */

DROP TABLE IF EXISTS `Schoolbox_Students`;

/*!50001 DROP VIEW IF EXISTS `Schoolbox_Students` */;
/*!50001 DROP TABLE IF EXISTS `Schoolbox_Students` */;

/*!50001 CREATE TABLE  `Schoolbox_Students`(
 `surname` varchar(255) ,
 `firstname` varchar(255) ,
 `birthdate` date ,
 `deltalinkID` varchar(16) ,
 `username` varchar(100) ,
 `cohort` varchar(25) ,
 `academicYear` int(11) ,
 `tutorGroup` varchar(16) ,
 `denbighID` varchar(16) ,
 `familyDenbighID` varchar(16) ,
 `boardingStatus` varchar(100) ,
 `email` varchar(255) ,
 `house` varchar(255) ,
 `cateringHouse` varchar(255) 
)*/;

/*View structure for view Schoolbox_Students */

/*!50001 DROP TABLE IF EXISTS `Schoolbox_Students` */;
/*!50001 DROP VIEW IF EXISTS `Schoolbox_Students` */;

/*!50001 CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `Schoolbox_Students` AS select `People`.`surname` AS `surname`,`People`.`preferred_name` AS `firstname`,`People`.`dateOfBirth` AS `birthdate`,`People`.`deltalinkID` AS `deltalinkID`,`Students`.`loginIDforIT` AS `username`,`Students`.`cohort` AS `cohort`,`Students`.`academicYear` AS `academicYear`,`Students`.`tutorGroup` AS `tutorGroup`,`People`.`denbighID` AS `denbighID`,`Students`.`familyDenbighID` AS `familyDenbighID`,`Students`.`boardingStatus` AS `boardingStatus`,`People`.`email` AS `email`,`Students`.`house` AS `house`,`Students`.`cateringHouse` AS `cateringHouse` from (`People` join `Students`) where ((`Students`.`peopleDenbighID` = `People`.`denbighID`) and (`Students`.`loginIDforIT` is not null)) */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
