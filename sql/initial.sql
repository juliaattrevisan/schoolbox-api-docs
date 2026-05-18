/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: mariadb    Database: schoolbox
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `allowedFileExtension`
--

DROP TABLE IF EXISTS `allowedFileExtension`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `allowedFileExtension` (
  `fileTypeID` int(11) NOT NULL,
  `extension` varchar(48) DEFAULT NULL,
  UNIQUE KEY `extension` (`extension`),
  KEY `fileTypeID` (`fileTypeID`),
  CONSTRAINT `fk_allowedFileExtension_file_type_id` FOREIGN KEY (`fileTypeID`) REFERENCES `allowedFileTypes` (`fileTypeID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `allowedFileTypes`
--

DROP TABLE IF EXISTS `allowedFileTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `allowedFileTypes` (
  `fileTypeID` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(150) DEFAULT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `iconFile` varchar(80) DEFAULT NULL,
  `enable` enum('true','false') DEFAULT NULL,
  `max_file_size` bigint(20) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fileTypeID`),
  UNIQUE KEY `mime` (`mime`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess`
--

DROP TABLE IF EXISTS `assess`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess` (
  `assess_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `assessment_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `type` int(11) DEFAULT 11,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `completion_message` text DEFAULT NULL,
  `start` datetime DEFAULT NULL,
  `finish` datetime DEFAULT NULL,
  `duration` int(11) NOT NULL DEFAULT 0 COMMENT 'in minutes',
  `reading_time_duration` int(11) NOT NULL DEFAULT 0,
  `shuffle_items` tinyint(1) NOT NULL DEFAULT 0,
  `prompts_mode` tinyint(1) NOT NULL DEFAULT 0,
  `max_attempts` int(1) NOT NULL DEFAULT 0,
  `weight` int(11) unsigned NOT NULL DEFAULT 100,
  `mark_type_id` int(11) NOT NULL DEFAULT 3,
  `grade_group_id` int(11) DEFAULT NULL,
  `availability` enum('assess','survey') DEFAULT NULL,
  `anonymous_result` int(1) NOT NULL DEFAULT 0,
  `notify_author` int(1) NOT NULL DEFAULT 0,
  `mark_visibility` int(1) DEFAULT 1,
  `external_id` varchar(32) DEFAULT NULL COMMENT 'External-system specific identifier',
  `learnosity_reference` varchar(255) DEFAULT NULL,
  `learnosity_job_reference` varchar(255) DEFAULT NULL,
  `learnosity_job_status` enum('pending','processing','completed','failed','unprocessable') DEFAULT NULL,
  `learnosity_job_attempt_count` tinyint(3) unsigned DEFAULT NULL,
  `learnosity_source_assessment_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`assess_id`),
  UNIQUE KEY `id` (`assess_id`),
  UNIQUE KEY `fk_assess_assessment_id_idx` (`assessment_id`),
  KEY `fk_assess_category1` (`category_id`),
  KEY `fk_assess_user1` (`user_id`),
  KEY `deleted` (`deleted_at`),
  KEY `availability` (`availability`),
  KEY `idx_assess_external_id` (`external_id`),
  KEY `fk_assess_learnosity_source_assessment_id_fk` (`learnosity_source_assessment_id`),
  CONSTRAINT `fk_assess_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_assess_learnosity_source_assessment_id_fk` FOREIGN KEY (`learnosity_source_assessment_id`) REFERENCES `assessment` (`id`),
  CONSTRAINT `fk_assess_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_answer`
--

DROP TABLE IF EXISTS `assess_answer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_answer` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `sequence` int(11) DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `fk_assess_answer_assess_question1` (`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=926 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_attempt`
--

DROP TABLE IF EXISTS `assess_attempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_attempt` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `assess_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `start` datetime NOT NULL,
  `finish` datetime DEFAULT NULL,
  `reviewer_id` int(11) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `mark` varchar(255) DEFAULT NULL,
  `max_mark` decimal(15,2) DEFAULT NULL,
  `norm_mark` decimal(14,11) DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `learnosity_reference` varchar(255) DEFAULT NULL,
  `learnosity_status` enum('pending','processing','completed','failed','unprocessable') DEFAULT NULL,
  `learnosity_poll_count` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  UNIQUE KEY `uq_assess_attempt_learnosity_reference` (`learnosity_reference`),
  KEY `fk_assess_attempt_assess1` (`assess_id`),
  KEY `fk_assess_attempt_user1` (`user_id`),
  KEY `deleted` (`deleted_at`),
  KEY `fk_assess_attempt_reviewer_id` (`reviewer_id`),
  KEY `reviewed_at` (`reviewed_at`),
  KEY `idx_assess_attempt_finish` (`finish`),
  CONSTRAINT `fk_assess_attempt_reviewer_id` FOREIGN KEY (`reviewer_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_assess_attempt_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_luQuestionType`
--

DROP TABLE IF EXISTS `assess_luQuestionType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_luQuestionType` (
  `type_id` int(8) NOT NULL AUTO_INCREMENT,
  `classname` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `moodle` varchar(255) DEFAULT NULL,
  `automated_marking` enum('true','false') DEFAULT NULL,
  `availability` enum('assess','survey','all') DEFAULT NULL,
  `has_legend` enum('true','false') DEFAULT NULL,
  `has_config` enum('true','false') DEFAULT NULL,
  PRIMARY KEY (`type_id`),
  KEY `availability` (`availability`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_question`
--

DROP TABLE IF EXISTS `assess_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_question` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `marks` decimal(15,2) NOT NULL DEFAULT 0.00,
  `question` text DEFAULT NULL,
  `config` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_id`),
  KEY `fk_assess_question_assess_luQuestionType1` (`type_id`),
  KEY `fk_assess_question_assess_section1` (`section_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=921 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_question_bank`
--

DROP TABLE IF EXISTS `assess_question_bank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_question_bank` (
  `question_bank_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `question_category_id` bigint(20) DEFAULT NULL,
  `type_id` bigint(20) DEFAULT NULL,
  `question` text DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `marks` bigint(20) DEFAULT NULL,
  `config` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`question_bank_id`),
  KEY `question_category_id` (`question_category_id`),
  KEY `type_id` (`type_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_question_category`
--

DROP TABLE IF EXISTS `assess_question_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_response`
--

DROP TABLE IF EXISTS `assess_response`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_response` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `question_id` int(11) NOT NULL,
  `response` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `mark` decimal(15,2) DEFAULT 0.00,
  `automarked_already` enum('true','false') DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`response_id`),
  KEY `fk_assess_response_assess_attempt1` (`attempt_id`),
  KEY `fk_assess_response_assess_question1` (`question_id`),
  KEY `fk_assess_response_assess_section1` (`section_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=740 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assess_section`
--

DROP TABLE IF EXISTS `assess_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assess_section` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `assess_id` int(11) NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `random` int(11) NOT NULL DEFAULT 0,
  `pool` int(11) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`section_id`),
  KEY `fk_assess_section_assess1` (`assess_id`),
  KEY `assess_id` (`assess_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment`
--

DROP TABLE IF EXISTS `assessment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `publish_date` datetime DEFAULT NULL,
  `open_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `weight` decimal(12,2) DEFAULT NULL,
  `sequence` smallint(6) unsigned DEFAULT NULL,
  `work_type_id` int(4) DEFAULT NULL,
  `default_open_span` int(11) DEFAULT NULL COMMENT 'Default period in days to schedule during Course Import',
  `course_managed` int(10) unsigned NOT NULL DEFAULT 0,
  `seb_enabled` int(10) unsigned NOT NULL DEFAULT 0,
  `original_assessment_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at` (`deleted_at`),
  KEY `due_date` (`due_date`),
  KEY `assessment_work_type_id` (`work_type_id`),
  KEY `idx_publish_date` (`publish_date`),
  KEY `idx_open_date` (`open_date`),
  KEY `idx_close_date` (`close_date`),
  KEY `assessment_original_assessment_id` (`original_assessment_id`),
  CONSTRAINT `fk_assessment_original_assessment_id` FOREIGN KEY (`original_assessment_id`) REFERENCES `assessment` (`id`),
  CONSTRAINT `fk_assessment_work_type_id` FOREIGN KEY (`work_type_id`) REFERENCES `lu_work_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=733 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment_course_unit_implementation`
--

DROP TABLE IF EXISTS `assessment_course_unit_implementation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_course_unit_implementation` (
  `assessment_id` int(10) unsigned NOT NULL,
  `course_unit_implementation_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`assessment_id`,`course_unit_implementation_id`),
  KEY `fk_assessment_course_unit_implementation_assessment_id_idx` (`assessment_id`),
  KEY `fk_assessment_course_unit_implementation_cui_id_idx` (`course_unit_implementation_id`),
  CONSTRAINT `fk_assessment_course_unit_implementation_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_assessment_course_unit_implementation_cui_id` FOREIGN KEY (`course_unit_implementation_id`) REFERENCES `course_unit_implementation` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment_course_unit_specification`
--

DROP TABLE IF EXISTS `assessment_course_unit_specification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_course_unit_specification` (
  `assessment_id` int(10) unsigned NOT NULL,
  `course_unit_specification_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`assessment_id`,`course_unit_specification_id`),
  KEY `fk_assessment_course_unit_specification_assessment_id_idx` (`assessment_id`),
  KEY `fk_assessment_course_unit_specification_cus_id_idx` (`course_unit_specification_id`),
  CONSTRAINT `fk_assessment_course_unit_specification_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_assessment_course_unit_specification_cus_id` FOREIGN KEY (`course_unit_specification_id`) REFERENCES `course_unit_specification` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment_folder`
--

DROP TABLE IF EXISTS `assessment_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_folder` (
  `assessment_id` int(10) unsigned NOT NULL,
  `folder_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`assessment_id`,`folder_id`),
  KEY `fk_assessment_folder_folder_id_idx` (`folder_id`),
  KEY `fk_assessment_folder_assessment_id_idx` (`assessment_id`),
  CONSTRAINT `fk_assessment_folder_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_assessment_folder_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment_participant`
--

DROP TABLE IF EXISTS `assessment_participant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_participant` (
  `assessment_id` int(10) unsigned NOT NULL,
  `student_id` int(4) unsigned NOT NULL,
  `open_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  UNIQUE KEY `assessment_participant_key` (`student_id`,`assessment_id`),
  KEY `fk_assessment_participant_assessment_idx` (`assessment_id`),
  CONSTRAINT `fk_assessment_participant_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_assessment_participant_participant` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment_specification_implementation`
--

DROP TABLE IF EXISTS `assessment_specification_implementation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_specification_implementation` (
  `specification_assessment_id` int(10) unsigned NOT NULL,
  `implementation_assessment_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`specification_assessment_id`,`implementation_assessment_id`),
  UNIQUE KEY `fk_assessment_implementation_specification_2_idx` (`implementation_assessment_id`),
  KEY `fk_assessment_implementation_specification_1_idx` (`specification_assessment_id`),
  CONSTRAINT `fk_assessment_implementation_specification_1` FOREIGN KEY (`specification_assessment_id`) REFERENCES `assessment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assessment_implementation_specification_2` FOREIGN KEY (`implementation_assessment_id`) REFERENCES `assessment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assessment_view`
--

DROP TABLE IF EXISTS `assessment_view`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `participant_id` int(4) unsigned NOT NULL COMMENT 'The user whose work was viewed.',
  `user_id` int(4) unsigned NOT NULL COMMENT 'The user who viewed the assessment.',
  `last_visit` datetime NOT NULL COMMENT 'The time the view occurred.  Only the most recent view is stored.',
  `feedback_was_visible` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'True if feedback existed and was visible to user at the time the assessment was viewed.',
  PRIMARY KEY (`id`),
  KEY `assessment_view_assessment_id_fk` (`assessment_id`),
  KEY `assessment_view_participant_fk` (`participant_id`),
  KEY `assessment_view_user_id_fk` (`user_id`),
  CONSTRAINT `assessment_view_assessment_id_fk` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`),
  CONSTRAINT `assessment_view_participant_fk` FOREIGN KEY (`participant_id`) REFERENCES `user` (`id`),
  CONSTRAINT `assessment_view_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `badge`
--

DROP TABLE IF EXISTS `badge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `image_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `allow_as_goal` tinyint(1) NOT NULL DEFAULT 1,
  `category_id` int(10) unsigned NOT NULL,
  `achievement_expiry` int(10) unsigned DEFAULT NULL,
  `goal_expiry` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `animate` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_achievement_badge_category_id_idx` (`category_id`),
  KEY `fk_achievement_badge_created_by_id_idx` (`created_by`),
  KEY `fk_achievement_badge_updated_by_idx` (`updated_by`),
  KEY `fk_achievement_badge_image_hash` (`image_hash`),
  CONSTRAINT `fk_achievement_badge_category_id` FOREIGN KEY (`category_id`) REFERENCES `badge_category` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_achievement_badge_created_by_id` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_achievement_badge_image_hash` FOREIGN KEY (`image_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_achievement_badge_updated_by_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `badge_category`
--

DROP TABLE IF EXISTS `badge_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_achievement_badge_category_created_by_id` (`created_by`),
  CONSTRAINT `fk_achievement_badge_category_created_by_id` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `badge_user`
--

DROP TABLE IF EXISTS `badge_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` int(10) unsigned NOT NULL,
  `badge_id` int(10) unsigned NOT NULL,
  `status` enum('goal','achievement') NOT NULL,
  `reason` text DEFAULT NULL,
  `reasoned_by` int(10) unsigned DEFAULT NULL,
  `reasoned_at` datetime DEFAULT NULL,
  `awarded_by` int(10) unsigned NOT NULL,
  `awarded_at` datetime NOT NULL,
  `expires_at` date DEFAULT NULL,
  `viewed_on` datetime DEFAULT NULL,
  `award_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_badge_user_recipient_id_idx` (`recipient_id`),
  KEY `fk_badge_user_badge_id_idx` (`badge_id`),
  KEY `fk_badge_user_awarded_by_id_idx` (`awarded_by`),
  CONSTRAINT `fk_badge_user_awarded_by_id` FOREIGN KEY (`awarded_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_badge_user_badge_id` FOREIGN KEY (`badge_id`) REFERENCES `badge` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_badge_user_recipient_id` FOREIGN KEY (`recipient_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `badge_year_level`
--

DROP TABLE IF EXISTS `badge_year_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge_year_level` (
  `badge_id` int(10) unsigned NOT NULL,
  `year_level_id` int(10) unsigned NOT NULL,
  KEY `fk_badge_year_level_badge_id` (`badge_id`),
  KEY `fk_badge_year_level_year_level_id` (`year_level_id`),
  CONSTRAINT `fk_badge_year_level_badge_id` FOREIGN KEY (`badge_id`) REFERENCES `badge` (`id`),
  CONSTRAINT `fk_badge_year_level_year_level_id` FOREIGN KEY (`year_level_id`) REFERENCES `lu_year_level` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog`
--

DROP TABLE IF EXISTS `blog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` int(6) unsigned NOT NULL,
  `owner_id` int(6) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `heading` varchar(100) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blog_category1` (`folder_id`),
  KEY `fk_blog_user1` (`owner_id`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `fk_blog_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_blog_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_comment`
--

DROP TABLE IF EXISTS `blog_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_comment` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `blog_id` int(6) unsigned NOT NULL,
  `owner_id` int(6) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `body` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_blogComment_blog1` (`blog_id`),
  KEY `fk_blogComment_user1` (`owner_id`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `fk_blog_comment_blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_blog_comment_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_image`
--

DROP TABLE IF EXISTS `blog_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_image` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blog_id` int(11) unsigned NOT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_blog_images` (`blog_id`,`hash`),
  KEY `fk_blog_image_blog_id_idx` (`blog_id`),
  KEY `fk_blog_image_storage_hash_idx` (`hash`),
  CONSTRAINT `fk_blog_image_blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_blog_image_storage_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) unsigned NOT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`),
  CONSTRAINT `fk_bookmarks_user_id` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar_connector`
--

DROP TABLE IF EXISTS `calendar_connector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_connector` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(255) DEFAULT NULL,
  `connector_class` varchar(255) DEFAULT NULL,
  `config` text NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT 'Whether this config is active or not',
  `failed_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `status_idx` (`status`),
  KEY `connector_idx` (`connector_class`(191)),
  KEY `type_idx` (`event_type`(191))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `canvas_import_log`
--

DROP TABLE IF EXISTS `canvas_import_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `canvas_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` varchar(255) NOT NULL,
  `script` varchar(255) NOT NULL,
  `start_at` datetime NOT NULL,
  `finish_at` datetime DEFAULT NULL,
  `status` enum('running','complete','error') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat`
--

DROP TABLE IF EXISTS `chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(20) DEFAULT NULL,
  `time` datetime NOT NULL,
  `body` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_chat_category1` (`category_id`),
  KEY `fk_chat_user1` (`user_id`),
  CONSTRAINT `fk_chat_folder_id` FOREIGN KEY (`category_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_chat_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_poll`
--

DROP TABLE IF EXISTS `chat_poll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_poll` (
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`category_id`),
  KEY `fk_chat_poll_category1` (`category_id`),
  KEY `fk_chat_poll_user1` (`user_id`),
  KEY `chat_poll_time_index` (`time`),
  CONSTRAINT `fk_chat_poll_folder_id` FOREIGN KEY (`category_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_chat_poll_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clipboard_import_log`
--

DROP TABLE IF EXISTS `clipboard_import_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clipboard_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` varchar(255) NOT NULL,
  `script` varchar(255) NOT NULL,
  `start_at` datetime NOT NULL,
  `finish_at` datetime DEFAULT NULL,
  `status` enum('running','complete','error') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `finish_at_idx` (`finish_at`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cmsLog`
--

DROP TABLE IF EXISTS `cmsLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cmsLog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object` enum('folder','file','link','content','myfile','myfolder') DEFAULT NULL,
  `objectId` int(10) unsigned NOT NULL DEFAULT 0,
  `action` enum('create','modify','delete','access','move') DEFAULT NULL,
  `user` int(10) unsigned NOT NULL DEFAULT 0,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `objectId` (`objectId`),
  KEY `time` (`time`),
  KEY `object` (`object`),
  KEY `action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_article`
--

DROP TABLE IF EXISTS `comms_news_article`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `blurb` text DEFAULT NULL,
  `author_id` int(11) unsigned NOT NULL,
  `sticky` tinyint(1) NOT NULL DEFAULT 0,
  `feature_image_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `discussion_thread_id` int(11) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `accepted_by` int(11) unsigned DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `rejected_by` int(11) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_reason` text DEFAULT NULL,
  `publish_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_comms_news_article_rejected_by` (`rejected_by`),
  KEY `idx_comms_news_article_submitted_at` (`submitted_at`),
  KEY `idx_comms_news_article_accepted_at` (`accepted_at`),
  KEY `idx_comms_news_article_rejected_at` (`rejected_at`),
  KEY `idx_comms_news_article_publish_at` (`publish_at`),
  KEY `idx_comms_news_article_expires_at` (`expires_at`),
  KEY `idx_comms_news_article_created_at` (`created_at`),
  KEY `idx_comms_news_article_updated_at` (`updated_at`),
  KEY `idx_comms_news_article_deleted_at` (`deleted_at`),
  KEY `fk_comms_news_article_author_id` (`author_id`),
  KEY `fk_comms_news_article_accepted_by` (`accepted_by`),
  KEY `fk_comms_news_article_featured_image_hash` (`feature_image_hash`),
  KEY `fk_comms_news_article_discussion_thread_id` (`discussion_thread_id`),
  CONSTRAINT `fk_comms_news_article_accepted_by` FOREIGN KEY (`accepted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_article_author_id` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_article_discussion_thread_id` FOREIGN KEY (`discussion_thread_id`) REFERENCES `discussion_thread` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_article_featured_image_hash` FOREIGN KEY (`feature_image_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_article_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_article_file`
--

DROP TABLE IF EXISTS `comms_news_article_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_article_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `storage_id` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `article_id` int(11) NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `storage_id` (`storage_id`),
  CONSTRAINT `fk_comms_news_article_file` FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_article_file_storage` FOREIGN KEY (`storage_id`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_article_source`
--

DROP TABLE IF EXISTS `comms_news_article_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_article_source` (
  `article_id` int(11) NOT NULL,
  `source_name` varchar(255) NOT NULL,
  `guid` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  UNIQUE KEY `article_id_UNIQUE` (`article_id`),
  KEY `source_name` (`source_name`),
  KEY `guid` (`guid`),
  CONSTRAINT `fk_article_id_comms_news_article_id` FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_article_statistics`
--

DROP TABLE IF EXISTS `comms_news_article_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_article_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `type` enum('open') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `referrer` enum('archive','dashboard','email','homepage','index','rss','saved','search') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `role_type` enum('staff','parent','student','guest') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `occurs_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cnas_article_id_user_id_type_referrer` (`article_id`,`user_id`,`type`,`referrer`,`role_type`,`role_id`),
  KEY `fk_comms_news_statistics_user_id` (`user_id`),
  KEY `comms_news_article_statistics_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_comms_news_statistics_article_id` FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_statistics_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_article_statistics_cumulative`
--

DROP TABLE IF EXISTS `comms_news_article_statistics_cumulative`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_article_statistics_cumulative` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `article_id` int(11) NOT NULL,
  `type` enum('open') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `referrer` enum('archive','dashboard','email','homepage','index','rss','saved','search') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `role_type` enum('staff','parent','student','guest') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `num` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cnasc_date_article_id` (`date`,`article_id`,`type`,`referrer`,`role_type`,`role_id`),
  KEY `fk_comms_news_statistics_cumulative_article_id` (`article_id`),
  CONSTRAINT `fk_comms_news_statistics_cumulative_article_id` FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_article_topic`
--

DROP TABLE IF EXISTS `comms_news_article_topic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_article_topic` (
  `article_id` int(11) NOT NULL,
  `topic_id` int(11) unsigned NOT NULL,
  KEY `fk_comms_news_article_article_id` (`article_id`),
  KEY `fk_comms_news_article_topic_id` (`topic_id`),
  CONSTRAINT `fk_comms_news_article_article_id` FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_article_topic_id` FOREIGN KEY (`topic_id`) REFERENCES `newsTopic` (`newsTopicID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_audience`
--

DROP TABLE IF EXISTS `comms_news_audience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_audience` (
  `comms_news_article_id` int(11) DEFAULT NULL,
  `entity_type` enum('composite','user','super','campus','role','role_type','school_house','res_house','year','service','folder','fixturesteam') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `entity_value` varchar(32) DEFAULT NULL,
  `relationship` enum('self','parents_of','teachers_of') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  UNIQUE KEY `idx_comms_news_audience_article_type_value_relationship` (`comms_news_article_id`,`entity_type`,`entity_value`,`relationship`),
  KEY `fk_comms_news_audience_comms_news_article_id` (`comms_news_article_id`),
  KEY `idx_comms_news_audience_type` (`entity_type`),
  KEY `idx_comms_news_audience_value` (`entity_value`),
  KEY `idx_comms_news_audience_relationship` (`relationship`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_audience_complex`
--

DROP TABLE IF EXISTS `comms_news_audience_complex`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_audience_complex` (
  `comms_news_article_id` int(11) NOT NULL,
  `entity_type_1` enum('user','super','campus','role','role_type','school_house','res_house','year','service','folder','fixturesteam') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `entity_value_1` varchar(32) DEFAULT NULL,
  `entity_type_2` enum('user','super','campus','role','role_type','school_house','res_house','year','service','folder','fixturesteam') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `entity_value_2` varchar(32) DEFAULT NULL,
  UNIQUE KEY `idx_comms_news_audience_complex_t1_v1_t2_v2` (`comms_news_article_id`,`entity_type_1`,`entity_value_1`,`entity_type_2`,`entity_value_2`),
  KEY `idx_comms_news_audience_complex_type_1` (`entity_type_1`),
  KEY `idx_comms_news_audience_complex_value_1` (`entity_value_1`),
  KEY `idx_comms_news_audience_complex_type_2` (`entity_type_2`),
  KEY `idx_comms_news_audience_complex_value_2` (`entity_value_2`),
  CONSTRAINT `fk_comms_news_audience_child_comms_news_audience_id` FOREIGN KEY (`comms_news_article_id`) REFERENCES `comms_news_article` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comms_news_saveforlater`
--

DROP TABLE IF EXISTS `comms_news_saveforlater`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comms_news_saveforlater` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `article_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_comms_news_saveforlater_user_id_article_id` (`user_id`,`article_id`),
  KEY `fk_comms_news_saveforlater_article_id` (`article_id`),
  KEY `comms_news_saveforlater_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_comms_news_saveforlater_article_id` FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_comms_news_saveforlater_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `component_config_definition`
--

DROP TABLE IF EXISTS `component_config_definition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `component_config_definition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_definition_id` int(10) unsigned NOT NULL,
  `key` varchar(32) DEFAULT NULL,
  `default_value` varchar(128) DEFAULT NULL,
  `label` varchar(64) DEFAULT NULL,
  `description` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `component_definition_id` (`component_definition_id`,`key`),
  KEY `fk_dashboard_component_option_default_dashboard_component1` (`component_definition_id`),
  CONSTRAINT `fk_component_config_definition_definition_id` FOREIGN KEY (`component_definition_id`) REFERENCES `component_definition` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=421 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `component_config_instance`
--

DROP TABLE IF EXISTS `component_config_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `component_config_instance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_config_definition_id` int(10) unsigned NOT NULL,
  `component_instance_id` int(10) unsigned NOT NULL,
  `value` varchar(2048) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `component_config_definition` (`component_config_definition_id`,`component_instance_id`),
  KEY `fk_dashboard_component_option_dashboard_component_instance1` (`component_config_definition_id`),
  KEY `component_instance_id` (`component_instance_id`),
  KEY `component_config_instance_value_index` (`value`(768)),
  CONSTRAINT `fk_component_config_instance_component_config_definition_id` FOREIGN KEY (`component_config_definition_id`) REFERENCES `component_config_definition` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_component_config_instance_component_instance_id` FOREIGN KEY (`component_instance_id`) REFERENCES `component_instance` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9553 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `component_definition`
--

DROP TABLE IF EXISTS `component_definition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `component_definition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  `class` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `description` varchar(512) DEFAULT NULL,
  `module` enum('dashboard','homepage','eportfolio') DEFAULT NULL,
  `component_category` int(10) unsigned DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `visible` enum('true','false') DEFAULT NULL,
  `multiple` enum('false','true') DEFAULT NULL,
  `msg_namespace_slug` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `msg_namespace_slug_suffix` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `config_copy` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class` (`class`),
  KEY `module` (`module`),
  KEY `msg_namespace_slug` (`msg_namespace_slug`),
  CONSTRAINT `component_definition_ibfk_1` FOREIGN KEY (`msg_namespace_slug`) REFERENCES `msg_namespace` (`slug`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `component_instance`
--

DROP TABLE IF EXISTS `component_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `component_instance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `component_definition_id` int(10) unsigned NOT NULL,
  `module` enum('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') DEFAULT NULL,
  `identifier` int(10) unsigned NOT NULL COMMENT 'Foreign ID is used to reference the context of the instance. (Polymorphic)',
  `position` enum('top','left','right') DEFAULT NULL,
  `sequence` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_component_instance_dashboard_component1` (`component_definition_id`),
  KEY `fk_dashboard_component_instance_groups1` (`identifier`),
  KEY `module` (`module`),
  KEY `position` (`position`),
  CONSTRAINT `fk_component_instance_component_definition_id` FOREIGN KEY (`component_definition_id`) REFERENCES `component_definition` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4896 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config` (
  `configID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `field_id` int(11) NOT NULL,
  `options` varchar(300) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `editable` enum('true','false') DEFAULT NULL,
  `hidden` enum('true','false') DEFAULT NULL,
  `required` enum('false','true') DEFAULT NULL,
  `module` enum('settings','comms','custom','oauth','schoolData','lti','report-export','feature-tours','ai-services','differentiation-profiles') DEFAULT 'settings',
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`configID`),
  UNIQUE KEY `configName` (`name`),
  KEY `config_field_id` (`field_id`),
  KEY `config_type_id` (`type_id`),
  CONSTRAINT `fk_config_field_id` FOREIGN KEY (`field_id`) REFERENCES `lu_config_field` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_config_type_id` FOREIGN KEY (`type_id`) REFERENCES `lu_config_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=772 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courseTemplates`
--

DROP TABLE IF EXISTS `courseTemplates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `courseTemplates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `courseTemplateId` int(11) NOT NULL,
  `unitTemplateId` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_builder_config`
--

DROP TABLE IF EXISTS `course_builder_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_builder_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `permission` int(11) DEFAULT NULL,
  `page` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_implementation`
--

DROP TABLE IF EXISTS `course_implementation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_implementation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` int(10) unsigned NOT NULL,
  `course_specification_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_course_implementation_course_specification_id_idx` (`course_specification_id`),
  KEY `deleted_at` (`deleted_at`),
  KEY `folder_id` (`folder_id`),
  CONSTRAINT `course_implementation_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_course_implementation_course_specification_id` FOREIGN KEY (`course_specification_id`) REFERENCES `course_specification` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_specification`
--

DROP TABLE IF EXISTS `course_specification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_specification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` int(10) unsigned NOT NULL,
  `subject_code` varchar(20) NOT NULL DEFAULT '',
  `uuid` varchar(36) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `template_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_id_UNIQUE` (`folder_id`),
  UNIQUE KEY `uuid_UNIQUE` (`uuid`),
  KEY `course_specification_courseTemplates_id_fk` (`template_id`),
  CONSTRAINT `course_specification_courseTemplates_id_fk` FOREIGN KEY (`template_id`) REFERENCES `courseTemplates` (`id`),
  CONSTRAINT `fk_course_specification_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_unit_implementation`
--

DROP TABLE IF EXISTS `course_unit_implementation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_unit_implementation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `course_implementation_id` int(11) unsigned NOT NULL,
  `course_unit_specification_id` int(11) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `open_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`),
  KEY `course_unit_implementation_open_date` (`open_date`),
  KEY `course_unit_implementation_due_date` (`due_date`),
  KEY `course_unit_implementation_deleted_at` (`deleted_at`),
  KEY `course_implementation_id` (`course_implementation_id`),
  KEY `fk_course_unit_implementation_course_unit_specification_id_idx` (`course_unit_specification_id`),
  CONSTRAINT `fk_course_unit_implementation_course_implementation_id` FOREIGN KEY (`course_implementation_id`) REFERENCES `course_implementation` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_course_unit_implementation_course_unit_specification_id` FOREIGN KEY (`course_unit_specification_id`) REFERENCES `course_unit_specification` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_unit_specification`
--

DROP TABLE IF EXISTS `course_unit_specification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_unit_specification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` int(10) unsigned NOT NULL,
  `course_specification_id` int(10) unsigned NOT NULL,
  `uuid` varchar(36) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_id_UNIQUE` (`folder_id`),
  UNIQUE KEY `uuid_UNIQUE` (`uuid`),
  KEY `fk_course_unit_course_specification_id_idx` (`course_specification_id`),
  CONSTRAINT `fk_course_unit_course_specification_id` FOREIGN KEY (`course_specification_id`) REFERENCES `course_specification` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_badge`
--

DROP TABLE IF EXISTS `curriculum_badge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_badge` (
  `badge_id` int(10) unsigned NOT NULL,
  `curriculum_node_id` int(11) NOT NULL,
  UNIQUE KEY `curriculum_badge_UNIQUE` (`badge_id`,`curriculum_node_id`),
  KEY `fk_curriculum_achievement_curriculum_node_id` (`curriculum_node_id`),
  KEY `fk_curriculum_achievement_badge_id` (`badge_id`),
  CONSTRAINT `fk_curriculum_achievement_badge_id` FOREIGN KEY (`badge_id`) REFERENCES `badge` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_curriculum_achievement_curriculum_id` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_moment`
--

DROP TABLE IF EXISTS `curriculum_moment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_moment` (
  `curriculum_node_id` int(11) NOT NULL,
  `moment_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`curriculum_node_id`,`moment_id`),
  KEY `fk_curriculum_evidence_evidence_id` (`moment_id`),
  CONSTRAINT `fk_curriculum_evidence_curriculum_node_id` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_curriculum_evidence_evidence_id` FOREIGN KEY (`moment_id`) REFERENCES `moment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_node`
--

DROP TABLE IF EXISTS `curriculum_node`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_node` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curriculum_node_parent_id` int(11) DEFAULT NULL,
  `name` varchar(2048) DEFAULT NULL,
  `code` varchar(64) NOT NULL DEFAULT '',
  `reference` varchar(2048) DEFAULT NULL,
  `order` tinyint(4) NOT NULL,
  `description` text NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `uuid` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid_UNIQUE` (`uuid`),
  KEY `curriculum_node_ibfk_2` (`curriculum_node_parent_id`),
  KEY `deleted` (`deleted_at`),
  KEY `idx_curriculum_node_code` (`code`),
  CONSTRAINT `curriculum_node_ibfk_2` FOREIGN KEY (`curriculum_node_parent_id`) REFERENCES `curriculum_node` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=308 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_node_for_assessment`
--

DROP TABLE IF EXISTS `curriculum_node_for_assessment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_node_for_assessment` (
  `curriculum_node_id` int(11) NOT NULL,
  `assessment_id` int(11) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_assessment_unique` (`curriculum_node_id`,`assessment_id`),
  KEY `curriculum_node_for_assessment_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_assessment_assessment_id` (`assessment_id`),
  CONSTRAINT `fk_curriculum_node` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_curriculum_node_for_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_node_for_category`
--

DROP TABLE IF EXISTS `curriculum_node_for_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_node_for_category` (
  `curriculum_node_id` int(11) NOT NULL,
  `category_id` int(5) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_category_unique` (`curriculum_node_id`,`category_id`),
  KEY `curriculum_node_for_category_curriculum_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_category_category_id` (`category_id`),
  CONSTRAINT `curriculum_node_for_category_ibfk_1` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_node_for_rubric_capability`
--

DROP TABLE IF EXISTS `curriculum_node_for_rubric_capability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_node_for_rubric_capability` (
  `curriculum_node_id` int(11) NOT NULL,
  `rubric_capability_id` int(11) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_rubric_group_unique` (`curriculum_node_id`,`rubric_capability_id`),
  KEY `curriculum_node_for_rubric_group_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_rubric_group_rubric_group_id` (`rubric_capability_id`),
  CONSTRAINT `fk_curriculum_node_for_rubric_group_curriculum_node_id` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_curriculum_node_for_rubric_group_rubric_group_id` FOREIGN KEY (`rubric_capability_id`) REFERENCES `rubric_capability` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `curriculum_node_for_rubric_indicator`
--

DROP TABLE IF EXISTS `curriculum_node_for_rubric_indicator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_node_for_rubric_indicator` (
  `curriculum_node_id` int(11) NOT NULL,
  `rubric_indicator_id` int(11) unsigned NOT NULL,
  UNIQUE KEY `curriculum_node_for_rubric_criterion_unique` (`curriculum_node_id`,`rubric_indicator_id`),
  KEY `curriculum_node_for_rubric_criterion_node_id` (`curriculum_node_id`),
  KEY `curriculum_node_for_rubric_criterion_rubric_criterion_id` (`rubric_indicator_id`),
  CONSTRAINT `fk_curriculum_node_for_rubric_criterion_curriculum_node_id` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_curriculum_node_for_rubric_criterion_rubric_criterion_id` FOREIGN KEY (`rubric_indicator_id`) REFERENCES `rubric_indicator` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dailyquote_config`
--

DROP TABLE IF EXISTS `dailyquote_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dailyquote_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dailyquote_data`
--

DROP TABLE IF EXISTS `dailyquote_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dailyquote_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` text DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent`
--

DROP TABLE IF EXISTS `dialog_agent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `scenario_id` int(11) NOT NULL,
  `language` varchar(255) DEFAULT NULL,
  `helpfulness` enum('exploratory','guided','directed') DEFAULT NULL,
  `use_diff_profile` int(11) DEFAULT NULL,
  `opening_message` text DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_da_scenario_idx` (`scenario_id`),
  KEY `fk_da_assessment_idx` (`assessment_id`),
  KEY `fk_da_createdby_idx` (`created_by`),
  KEY `fk_da_updatedby_idx` (`updated_by`),
  KEY `fk_da_deletedby_idx` (`deleted_by`),
  CONSTRAINT `fk_da_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_da_createdby` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_da_deletedby` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_da_scenario` FOREIGN KEY (`scenario_id`) REFERENCES `dialog_agent_scenario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_da_updatedby` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_feedback`
--

DROP TABLE IF EXISTS `dialog_agent_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dialog_agent_id` int(11) NOT NULL,
  `response_id` int(11) NOT NULL,
  `learner_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_daf_dialog_agent_idx` (`dialog_agent_id`),
  KEY `fk_daf_response_idx` (`response_id`),
  KEY `fk_daf_learner_id` (`learner_id`),
  CONSTRAINT `fk_daf_dialog_agent` FOREIGN KEY (`dialog_agent_id`) REFERENCES `dialog_agent` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_daf_learner_id` FOREIGN KEY (`learner_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_daf_response` FOREIGN KEY (`response_id`) REFERENCES `dialog_agent_response` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_feedback_criterion`
--

DROP TABLE IF EXISTS `dialog_agent_feedback_criterion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_feedback_criterion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `scenario_criterion_id` int(11) NOT NULL,
  `body` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dafc_feedback_idx` (`feedback_id`),
  KEY `fk_dafc_scenario_criterion_idx` (`scenario_criterion_id`),
  CONSTRAINT `fk_dafc_feedback` FOREIGN KEY (`feedback_id`) REFERENCES `dialog_agent_feedback` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dafc_scenario_criterion` FOREIGN KEY (`scenario_criterion_id`) REFERENCES `dialog_agent_scenario_criterion` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_response`
--

DROP TABLE IF EXISTS `dialog_agent_response`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_response` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dialog_agent_id` int(11) NOT NULL,
  `learner_id` int(10) unsigned NOT NULL,
  `authoritative` tinyint(1) DEFAULT NULL,
  `session_id` char(36) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `assistance_requested_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dar_dialog_agent_idx` (`dialog_agent_id`),
  KEY `fk_dar_learner_idx` (`learner_id`),
  KEY `fk_dar_session_idx` (`session_id`),
  KEY `fk_dar_submitted_by_idx` (`submitted_by`),
  KEY `fk_dar_createdby_idx` (`created_by`),
  KEY `fk_dar_updatedby_idx` (`updated_by`),
  KEY `fk_dar_deletedby_idx` (`deleted_by`),
  CONSTRAINT `fk_dar_createdby` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dar_deletedby` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dar_dialog_agent` FOREIGN KEY (`dialog_agent_id`) REFERENCES `dialog_agent` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dar_learner` FOREIGN KEY (`learner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dar_session` FOREIGN KEY (`session_id`) REFERENCES `openai_log` (`session_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dar_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dar_updatedby` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_response_message`
--

DROP TABLE IF EXISTS `dialog_agent_response_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_response_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dialog_agent_response_id` int(11) NOT NULL,
  `ai_response_id` varchar(255) DEFAULT NULL,
  `role` enum('user','assistant') NOT NULL,
  `status` enum('ok','pending','error','guardrail_flagged') NOT NULL,
  `content` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dialog_agent_response_message_dialog_agent_response_id_fk` (`dialog_agent_response_id`),
  KEY `dialog_agent_response_message_ai_response_id_index` (`ai_response_id`),
  CONSTRAINT `dialog_agent_response_message_dialog_agent_response_id_fk` FOREIGN KEY (`dialog_agent_response_id`) REFERENCES `dialog_agent_response` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_scenario`
--

DROP TABLE IF EXISTS `dialog_agent_scenario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_scenario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `prompt_template` text DEFAULT NULL,
  `default_rules` text NOT NULL,
  `type` enum('system','custom') NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_scenario_criterion`
--

DROP TABLE IF EXISTS `dialog_agent_scenario_criterion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_scenario_criterion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scenario_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dasc_dialog_agent_scenario_idx` (`scenario_id`),
  CONSTRAINT `fk_dasc_dialog_agent_scenario` FOREIGN KEY (`scenario_id`) REFERENCES `dialog_agent_scenario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_scenario_field`
--

DROP TABLE IF EXISTS `dialog_agent_scenario_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_scenario_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scenario_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `type` enum('textarea','switch','dropdown') NOT NULL,
  `meta` varchar(255) DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `sequence` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_das_field_scenario_idx` (`scenario_id`),
  CONSTRAINT `fk_das_field_scenario` FOREIGN KEY (`scenario_id`) REFERENCES `dialog_agent_scenario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dialog_agent_scenario_field_context`
--

DROP TABLE IF EXISTS `dialog_agent_scenario_field_context`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dialog_agent_scenario_field_context` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dialog_agent_id` int(11) NOT NULL,
  `scenario_field_id` int(11) NOT NULL,
  `body` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dasfc_dasf_idx` (`scenario_field_id`),
  KEY `fk_dasfc_dialog_agent_idx` (`dialog_agent_id`),
  CONSTRAINT `fk_dasfc_dasf` FOREIGN KEY (`scenario_field_id`) REFERENCES `dialog_agent_scenario_field` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_dasfc_dialog_agent` FOREIGN KEY (`dialog_agent_id`) REFERENCES `dialog_agent` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `differentiation_profiles`
--

DROP TABLE IF EXISTS `differentiation_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `differentiation_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL,
  `adjustment_category` text DEFAULT NULL,
  `adjustment_level` text DEFAULT NULL,
  `overview` text DEFAULT NULL,
  `interests_strengths` text DEFAULT NULL,
  `needs_challenges` text DEFAULT NULL,
  `strategies_adjustments` text DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_differentiation_profiles_created_by` (`created_by`),
  KEY `idx_differentiation_profiles_updated_by` (`updated_by`),
  CONSTRAINT `fk_differentiation_profiles_created_by_user_id` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_differentiation_profiles_student_id` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_differentiation_profiles_updated_by_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `differentiation_profiles_adjustment_category`
--

DROP TABLE IF EXISTS `differentiation_profiles_adjustment_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `differentiation_profiles_adjustment_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sequence` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `differentiation_profiles_adjustment_level`
--

DROP TABLE IF EXISTS `differentiation_profiles_adjustment_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `differentiation_profiles_adjustment_level` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sequence` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `differentiation_profiles_config`
--

DROP TABLE IF EXISTS `differentiation_profiles_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `differentiation_profiles_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `title` varchar(100) NOT NULL,
  `terminology` varchar(100) DEFAULT NULL,
  `description` varchar(256) DEFAULT NULL,
  `is_enabled` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `module` varchar(50) NOT NULL,
  `sequence` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_differentiation_profiles_config_is_enabled` (`is_enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `discussion_thread`
--

DROP TABLE IF EXISTS `discussion_thread`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discussion_thread` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `open` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `discussion_thread_comment`
--

DROP TABLE IF EXISTS `discussion_thread_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discussion_thread_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `discussion_thread_id` int(11) unsigned NOT NULL,
  `author_id` int(11) unsigned NOT NULL,
  `content` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_discussion_thread_comment_discussion_thread_id` (`discussion_thread_id`),
  KEY `fk_discussion_thread_comment_author_id` (`author_id`),
  CONSTRAINT `fk_discussion_thread_comment_author_id` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_discussion_thread_comment_discussion_thread_id` FOREIGN KEY (`discussion_thread_id`) REFERENCES `discussion_thread` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dropbox`
--

DROP TABLE IF EXISTS `dropbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dropbox` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT 0,
  `owner` int(4) unsigned NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `fk_dropbox_user1` (`owner`),
  KEY `deleted_at` (`deleted_at`),
  KEY `fk_dropbox_storage_hash_idx` (`hash`),
  CONSTRAINT `fk_dropbox_storage_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `editor_template`
--

DROP TABLE IF EXISTS `editor_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `editor_template` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category_id`),
  KEY `creator` (`created_by`),
  KEY `updater` (`updated_by`),
  KEY `deleter` (`deleted_by`),
  CONSTRAINT `fk_editor_template_category` FOREIGN KEY (`category_id`) REFERENCES `editor_template_category` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_editor_template_creator` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_editor_template_deleter` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_editor_template_updater` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `editor_template_category`
--

DROP TABLE IF EXISTS `editor_template_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `editor_template_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_dkim`
--

DROP TABLE IF EXISTS `email_dkim`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_dkim` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(1024) NOT NULL,
  `public` text CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `private` text CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_activitylog`
--

DROP TABLE IF EXISTS `eotc_activitylog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_activitylog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actioner_id` int(11) DEFAULT NULL,
  `metadata_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  KEY `id` (`id`),
  KEY `actioner_id` (`actioner_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_config`
--

DROP TABLE IF EXISTS `eotc_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_event`
--

DROP TABLE IF EXISTS `eotc_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(6) DEFAULT NULL,
  `author_id` int(6) unsigned NOT NULL,
  `teacher_in_charge_id` int(6) unsigned NOT NULL,
  `type` int(6) unsigned NOT NULL,
  `title` varchar(250) DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `location` varchar(250) DEFAULT NULL,
  `allday` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `start_datetime` (`start_datetime`),
  KEY `end_datetime` (`end_datetime`),
  KEY `series_id` (`series_id`),
  KEY `author_id` (`author_id`),
  KEY `teacher_in_charge_id` (`teacher_in_charge_id`),
  KEY `fk_eotc_event_type_idx` (`type`),
  CONSTRAINT `fk_eotc_event_author_id` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eotc_event_teacher_in_charge` FOREIGN KEY (`teacher_in_charge_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eotc_event_type` FOREIGN KEY (`type`) REFERENCES `luEventType` (`eventTypeID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_event_campus`
--

DROP TABLE IF EXISTS `eotc_event_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_event_campus` (
  `event_id` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  KEY `event_id` (`event_id`),
  KEY `campus_id` (`campus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_event_metadata`
--

DROP TABLE IF EXISTS `eotc_event_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_event_metadata` (
  `event_id` int(11) DEFAULT NULL,
  `metadata_id` int(11) DEFAULT NULL,
  UNIQUE KEY `event_metadata` (`event_id`,`metadata_id`),
  KEY `event_id` (`event_id`),
  KEY `metadata_id` (`metadata_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_event_role`
--

DROP TABLE IF EXISTS `eotc_event_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_event_role` (
  `event_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  UNIQUE KEY `event_id_role_id` (`event_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_metadata`
--

DROP TABLE IF EXISTS `eotc_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type_id` int(11) DEFAULT NULL COMMENT 'FK for luEventType',
  `status_id` int(11) DEFAULT NULL COMMENT 'FK for eotc_status',
  `rams_required` tinyint(1) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `permission_slip_required` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_type_id` (`event_type_id`),
  KEY `status_id` (`status_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_metadata_file`
--

DROP TABLE IF EXISTS `eotc_metadata_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_metadata_file` (
  `metadata_id` int(11) DEFAULT NULL,
  `storage_id` int(11) unsigned DEFAULT NULL,
  UNIQUE KEY `metadata_storage` (`metadata_id`,`storage_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `storage_id` (`storage_id`),
  CONSTRAINT `fk_eotc_metadata_file_storage` FOREIGN KEY (`storage_id`) REFERENCES `storage` (`storage_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_metadata_resourcebooking`
--

DROP TABLE IF EXISTS `eotc_metadata_resourcebooking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_metadata_resourcebooking` (
  `metadata_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  KEY `metadata_id` (`metadata_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_metadata_staff`
--

DROP TABLE IF EXISTS `eotc_metadata_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_metadata_staff` (
  `metadata_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  UNIQUE KEY `metadata_staff` (`metadata_id`,`user_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_metadata_student`
--

DROP TABLE IF EXISTS `eotc_metadata_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_metadata_student` (
  `metadata_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  UNIQUE KEY `metadata_student` (`metadata_id`,`user_id`),
  KEY `metadata_id` (`metadata_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_permission`
--

DROP TABLE IF EXISTS `eotc_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `is_subscribed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `campus_id` (`campus_id`),
  KEY `status_id` (`status_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eotc_status`
--

DROP TABLE IF EXISTS `eotc_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eotc_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolioTemplate`
--

DROP TABLE IF EXISTS `eportfolioTemplate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolioTemplate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `layout` smallint(6) DEFAULT NULL,
  `left_column_width` float DEFAULT NULL,
  `right_column_width` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_advancedlist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_advancedlist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_advancedlist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `imagehash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eportfolio_component_advancedlist_item_component_instance_fk` (`component_instance_id`),
  KEY `eportfolio_component_advancedlist_item_storage_hash_fk` (`imagehash`),
  CONSTRAINT `eportfolio_component_advancedlist_item_component_instance_fk` FOREIGN KEY (`component_instance_id`) REFERENCES `component_instance` (`id`),
  CONSTRAINT `eportfolio_component_advancedlist_item_storage_hash_fk` FOREIGN KEY (`imagehash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_basiclist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_basiclist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_basiclist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(10) unsigned NOT NULL,
  `content` text DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eportfolio_component_basiclist_item_component_instance_fk` (`component_instance_id`),
  CONSTRAINT `eportfolio_component_basiclist_item_component_instance_fk` FOREIGN KEY (`component_instance_id`) REFERENCES `component_instance` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_blog`
--

DROP TABLE IF EXISTS `eportfolio_component_blog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_blog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `owner_id` int(11) unsigned NOT NULL,
  `heading` varchar(255) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_component_blog_page_id_idx` (`page_id`),
  KEY `fk_eportfolio_component_blog_owner_id_idx` (`owner_id`),
  CONSTRAINT `fk_eportfolio_component_blog_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eportfolio_component_blog_page_id` FOREIGN KEY (`page_id`) REFERENCES `eportfolio_page` (`page_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_blog_comment`
--

DROP TABLE IF EXISTS `eportfolio_component_blog_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_blog_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blog_id` int(11) NOT NULL,
  `owner_id` int(11) unsigned NOT NULL,
  `body` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_component_blog_comment_blog_id_idx` (`blog_id`),
  KEY `fk_eportfolio_component_blog_comment_owner_id_idx` (`owner_id`),
  CONSTRAINT `fk_eportfolio_component_blog_comment_blog_id` FOREIGN KEY (`blog_id`) REFERENCES `eportfolio_component_blog` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eportfolio_component_blog_comment_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_blog_image`
--

DROP TABLE IF EXISTS `eportfolio_component_blog_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_blog_image` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blog_id` int(11) NOT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_eportfolio_blog_images` (`blog_id`,`hash`),
  KEY `fk_eportfolio_component_blog_image_blog_id_idx` (`blog_id`),
  KEY `fk_eportfolio_component_blog_image_hash_idx` (`hash`),
  CONSTRAINT `fk_eportfolio_component_blog_image_blog_id` FOREIGN KEY (`blog_id`) REFERENCES `eportfolio_component_blog` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eportfolio_component_blog_image_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_filelist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_filelist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_filelist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) DEFAULT NULL,
  `file_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_component_filelist_page_id` (`page_id`),
  KEY `fk_eportfolio_component_filelist_file_id` (`file_id`),
  CONSTRAINT `fk_eportfolio_component_filelist_file_id` FOREIGN KEY (`file_id`) REFERENCES `my_file` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eportfolio_component_filelist_page_id` FOREIGN KEY (`page_id`) REFERENCES `eportfolio_page` (`page_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_linklist_item`
--

DROP TABLE IF EXISTS `eportfolio_component_linklist_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_linklist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `sequence` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eportfolio_component_linklist_item_component_instance_fk` (`component_instance_id`),
  CONSTRAINT `eportfolio_component_linklist_item_component_instance_fk` FOREIGN KEY (`component_instance_id`) REFERENCES `component_instance` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_component_textbox_item`
--

DROP TABLE IF EXISTS `eportfolio_component_textbox_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_component_textbox_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `component_instance_id` int(10) unsigned NOT NULL,
  `content` text DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eportfolio_component_textbox_item_component_instance_fk` (`component_instance_id`),
  CONSTRAINT `eportfolio_component_textbox_item_component_instance_fk` FOREIGN KEY (`component_instance_id`) REFERENCES `component_instance` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_coverphoto`
--

DROP TABLE IF EXISTS `eportfolio_coverphoto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_coverphoto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL,
  `imagehash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_coverphoto_user_id_idx` (`uid`),
  KEY `fk_eportfolio_coverphoto_hash_idx` (`imagehash`),
  CONSTRAINT `fk_eportfolio_coverphoto_hash` FOREIGN KEY (`imagehash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_eportfolio_coverphoto_user_id` FOREIGN KEY (`uid`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_import`
--

DROP TABLE IF EXISTS `eportfolio_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_import` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_import_log`
--

DROP TABLE IF EXISTS `eportfolio_import_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_id` int(11) DEFAULT NULL,
  `result` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_log`
--

DROP TABLE IF EXISTS `eportfolio_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT 0,
  `uid` int(6) NOT NULL DEFAULT 0,
  `hitcount` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`user`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_page`
--

DROP TABLE IF EXISTS `eportfolio_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_page` (
  `page_id` int(11) NOT NULL AUTO_INCREMENT,
  `page_title` varchar(255) DEFAULT NULL,
  `uid` int(11) unsigned DEFAULT NULL,
  `template` int(11) DEFAULT NULL,
  `left_column_width` float DEFAULT NULL,
  `right_column_width` float DEFAULT NULL,
  `left_head_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_head_bg` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_bg_primary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_bg_secondary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_link` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_hover` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_head_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_head_bg` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_bg_primary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_bg_secondary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_link` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_hover` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_head_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_head_bg` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_bg_primary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_bg_secondary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_link` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_hover` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`page_id`),
  KEY `deleted` (`deleted_at`),
  KEY `fk_eportfolio_page_user_id_idx` (`uid`),
  CONSTRAINT `fk_eportfolio_page_user_id` FOREIGN KEY (`uid`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_permission`
--

DROP TABLE IF EXISTS `eportfolio_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `type` enum('parent','group','student') DEFAULT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_eportfolio_permission_page_id_idx` (`page_id`),
  CONSTRAINT `fk_eportfolio_permission_page_id` FOREIGN KEY (`page_id`) REFERENCES `eportfolio_page` (`page_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eportfolio_showcase_item`
--

DROP TABLE IF EXISTS `eportfolio_showcase_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eportfolio_showcase_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_box_id` int(6) unsigned NOT NULL,
  `uid` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `showcase_unique_idx` (`submission_box_id`,`uid`),
  KEY `uid_idx` (`uid`),
  CONSTRAINT `eportfolio_showcase_item_submission_box_id_fk` FOREIGN KEY (`submission_box_id`) REFERENCES `submission_box` (`id`),
  CONSTRAINT `eportfolio_showcase_item_user_id_fk` FOREIGN KEY (`uid`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `error`
--

DROP TABLE IF EXISTS `error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `error` (
  `id` int(3) unsigned DEFAULT NULL,
  `message` text DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `etherpad_pad`
--

DROP TABLE IF EXISTS `etherpad_pad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `etherpad_pad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pad_id` varchar(255) DEFAULT NULL,
  `environment` varchar(255) DEFAULT NULL,
  `homepage_id` int(11) unsigned NOT NULL COMMENT 'The homepage this pad started in',
  `name` varchar(255) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `lock` tinyint(1) DEFAULT 0 COMMENT 'Whether or not this pad is locked',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`),
  KEY `pad_id` (`pad_id`(191)),
  KEY `homepage_id` (`homepage_id`),
  CONSTRAINT `fk_etherpad_pad_folder_id` FOREIGN KEY (`homepage_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event`
--

DROP TABLE IF EXISTS `event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` int(6) unsigned DEFAULT NULL,
  `owner` int(6) unsigned NOT NULL,
  `author_id` int(6) unsigned DEFAULT NULL,
  `access` tinyint(1) NOT NULL DEFAULT 0,
  `type` int(10) unsigned DEFAULT NULL,
  `variant` enum('event','task','booking') NOT NULL DEFAULT 'event',
  `title` varchar(250) NOT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `allday` tinyint(1) DEFAULT 0,
  `detail` mediumtext NOT NULL,
  `location` varchar(250) NOT NULL,
  `max_attendees` int(11) DEFAULT NULL,
  `attendance` tinyint(1) DEFAULT NULL,
  `show_attendees` tinyint(1) DEFAULT 1,
  `completed` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'For marking Tasks as complete or not.',
  `publish_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`),
  KEY `start_datetime` (`start_datetime`),
  KEY `end_datetime` (`end_datetime`),
  KEY `series_id` (`series_id`),
  KEY `author_id` (`author_id`),
  KEY `access` (`access`),
  KEY `fk_event_type_id_idx` (`type`),
  CONSTRAINT `fk_event_author_id` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_owner_id` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_type_id` FOREIGN KEY (`type`) REFERENCES `luEventType` (`eventTypeID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2632 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_attendance`
--

DROP TABLE IF EXISTS `event_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `status` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_attendance_event_user` (`event_id`,`user_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_event_attendance_event_id` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_attendance_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_campus`
--

DROP TABLE IF EXISTS `event_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_campus` (
  `event_id` int(11) unsigned NOT NULL,
  `campus_id` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`event_id`,`campus_id`),
  KEY `event_id` (`event_id`),
  KEY `campus_id` (`campus_id`),
  CONSTRAINT `fk_event_campus_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_campus_event_id` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_folder`
--

DROP TABLE IF EXISTS `event_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_folder` (
  `event_id` int(11) unsigned DEFAULT NULL,
  `folder_id` int(11) unsigned DEFAULT NULL,
  UNIQUE KEY `event_folder_id` (`event_id`,`folder_id`),
  KEY `event_id` (`event_id`),
  KEY `folder_id` (`folder_id`),
  CONSTRAINT `fk_event_folder_event_id` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_folder_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_role`
--

DROP TABLE IF EXISTS `event_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_role` (
  `event_id` int(11) unsigned NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`event_id`,`role_id`),
  KEY `event_id` (`event_id`),
  KEY `group_id` (`role_id`),
  CONSTRAINT `fk_event_group_event_id` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_group_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_source`
--

DROP TABLE IF EXISTS `event_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_source` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(6) unsigned NOT NULL,
  `source_name` varchar(255) NOT NULL,
  `guid` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_source_edval_guid` (`event_id`,`source_name`,`guid`),
  CONSTRAINT `fk_event_source_event_id_event_event_id` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_user`
--

DROP TABLE IF EXISTS `event_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_user` (
  `event_id` int(11) unsigned DEFAULT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  UNIQUE KEY `event_user_id` (`event_id`,`user_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_event_user_event_id` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_user_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `file`
--

DROP TABLE IF EXISTS `file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `file` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned DEFAULT NULL,
  `owner` int(4) unsigned NOT NULL DEFAULT 0,
  `realname` varchar(255) DEFAULT NULL,
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified_by` int(4) unsigned DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `hidden` tinyint(1) NOT NULL DEFAULT 0,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `hash` (`hash`),
  KEY `fk_file_modified_by` (`modified_by`),
  CONSTRAINT `fk_file_folder_id` FOREIGN KEY (`category`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_file_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_file_owner_id` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_file_storage_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2766 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `filter`
--

DROP TABLE IF EXISTS `filter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `filter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity` varchar(127) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `class` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_filter_entity` (`entity`),
  KEY `idx_filter_class` (`class`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `filter_default`
--

DROP TABLE IF EXISTS `filter_default`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `filter_default` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filter_option_id` int(11) NOT NULL,
  `role_type` enum('staff','parent','student','guest') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `selected` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_filter_default_filter_option_id_role_type` (`filter_option_id`,`role_type`),
  KEY `idx_filter_default_filter_option_id` (`filter_option_id`),
  KEY `idx_filter_default_role_type` (`role_type`),
  CONSTRAINT `fk_filter_default_filter_option_id` FOREIGN KEY (`filter_option_id`) REFERENCES `filter_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `filter_option`
--

DROP TABLE IF EXISTS `filter_option`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `filter_option` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filter_page_id` int(11) NOT NULL,
  `key` varchar(127) NOT NULL,
  `available` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_filter_option_filter_page_id_key` (`filter_page_id`,`key`),
  KEY `idx_filter_option_filter_page_id` (`filter_page_id`),
  KEY `idx_filter_option_key` (`key`),
  CONSTRAINT `fk_filter_option_filter_page_id` FOREIGN KEY (`filter_page_id`) REFERENCES `filter_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `filter_page`
--

DROP TABLE IF EXISTS `filter_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `filter_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filter_id` int(11) NOT NULL,
  `page` enum('pending','grades','classMarkbook','analytics','dueWorkCal','courseMarkbook','rubricMarkbook','assessmentCal','badgeList','awardList','classPastoral','differentiationProfile','customisationRecord') DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL,
  `user_configurable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_filter_page_filter_id_page` (`filter_id`,`page`),
  KEY `idx_filter_option_filter_id` (`filter_id`),
  KEY `idx_filter_option_page` (`page`),
  CONSTRAINT `fk_filter_option_filter_id` FOREIGN KEY (`filter_id`) REFERENCES `filter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `filter_selection`
--

DROP TABLE IF EXISTS `filter_selection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `filter_selection` (
  `filter_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `page` enum('pending','grades','classMarkbook','analytics','dueWorkCal','courseMarkbook','rubricMarkbook','assessmentCal','badgeList','awardList','classPastoral','differentiationProfile','customisationRecord') DEFAULT NULL,
  `key` varchar(127) NOT NULL,
  `selected` tinyint(1) DEFAULT NULL,
  UNIQUE KEY `unique_filter_selection_filter_id_user_id_page_key` (`filter_id`,`user_id`,`page`,`key`),
  KEY `idx_filter_selection_filter_id` (`filter_id`),
  KEY `idx_filter_selection_user_id` (`user_id`),
  KEY `idx_filter_selection_page` (`page`),
  KEY `idx_filter_selection_key` (`key`),
  CONSTRAINT `fk_filter_selection_filter_id` FOREIGN KEY (`filter_id`) REFERENCES `filter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_filter_selection_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folder`
--

DROP TABLE IF EXISTS `folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `folder` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `parent` int(5) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(4) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(4) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(4) unsigned DEFAULT NULL,
  `inherit` tinyint(4) DEFAULT 0,
  `image_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `colour` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `deleted` (`deleted_at`),
  KEY `fk_folder_image_hash_idx` (`image_hash`),
  KEY `fk_folder_created_by` (`created_by`),
  KEY `fk_folder_updated_by` (`updated_by`),
  KEY `fk_folder_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_folder_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_folder_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_folder_image_hash` FOREIGN KEY (`image_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_folder_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=323 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci CHECKSUM=1 DELAY_KEY_WRITE=1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folder_code`
--

DROP TABLE IF EXISTS `folder_code`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `folder_code` (
  `folder_id` int(11) unsigned NOT NULL,
  `code` varchar(64) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  UNIQUE KEY `categoryIDcode` (`folder_id`,`code`),
  KEY `fk_categoryCode_category1` (`folder_id`),
  KEY `code` (`code`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `fk_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folder_year_level`
--

DROP TABLE IF EXISTS `folder_year_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `folder_year_level` (
  `folder_id` int(10) unsigned NOT NULL,
  `year_level_id` int(10) unsigned NOT NULL,
  KEY `fk_folder_year_level_folder_id` (`folder_id`),
  KEY `fk_folder_year_level_year_level_id` (`year_level_id`),
  CONSTRAINT `fk_folder_year_level_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`),
  CONSTRAINT `fk_folder_year_level_year_level_id` FOREIGN KEY (`year_level_id`) REFERENCES `lu_year_level` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forumPost`
--

DROP TABLE IF EXISTS `forumPost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forumPost` (
  `postID` int(11) NOT NULL AUTO_INCREMENT,
  `threadID` int(11) NOT NULL,
  `parentID` int(11) DEFAULT NULL,
  `userID` int(11) unsigned NOT NULL,
  `date` datetime DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `censored` enum('true','false') DEFAULT NULL,
  PRIMARY KEY (`postID`),
  KEY `threadID` (`threadID`),
  KEY `userID` (`userID`),
  KEY `parentID` (`parentID`),
  CONSTRAINT `fk_forumPost_thread_id` FOREIGN KEY (`threadID`) REFERENCES `forumThread` (`threadID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_forumPost_user_id` FOREIGN KEY (`userID`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forumThread`
--

DROP TABLE IF EXISTS `forumThread`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forumThread` (
  `threadID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) unsigned NOT NULL,
  `userID` int(11) unsigned NOT NULL,
  `title` varchar(250) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `locked` enum('true','false') DEFAULT NULL,
  `views` int(11) NOT NULL DEFAULT 0,
  `last_post_id` int(11) DEFAULT NULL,
  `last_post_date` datetime DEFAULT NULL,
  `last_post_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`threadID`),
  KEY `categoryID` (`categoryID`),
  KEY `userID` (`userID`),
  CONSTRAINT `fk_forumThread_folder_id` FOREIGN KEY (`categoryID`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_forumThread_user_id` FOREIGN KEY (`userID`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `google_file`
--

DROP TABLE IF EXISTS `google_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `google_id` varchar(100) DEFAULT NULL,
  `name` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `folder_id` int(5) unsigned NOT NULL,
  `edit_level` int(2) unsigned DEFAULT NULL,
  `comment_level` int(2) unsigned DEFAULT NULL,
  `owner_id` int(4) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `google_id` (`google_id`),
  KEY `folder_id` (`folder_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `fk_google_file_folder` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`),
  CONSTRAINT `fk_google_file_owner` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `group_type` enum('guest','student','parent','staff') NOT NULL,
  `can_change_password` enum('true','false') NOT NULL,
  `view_student_profile` tinyint(1) DEFAULT 0,
  `view_student_grades` tinyint(1) DEFAULT 0,
  `view_student_calendar` tinyint(1) DEFAULT 0,
  `view_parent_profile` tinyint(1) DEFAULT 0,
  `access_eportfolio` tinyint(1) DEFAULT 0,
  `access_pastoral` tinyint(1) DEFAULT 0,
  `create_badges` tinyint(1) DEFAULT 0,
  `access_evidence` tinyint(1) DEFAULT 0,
  `view_parent_directory` tinyint(1) DEFAULT 0,
  `view_staff_directory` tinyint(1) DEFAULT 0,
  `digest_schedule` varchar(24) DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_type_idx` (`group_type`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `fk_groups_user_id` (`updated_by`),
  CONSTRAINT `fk_groups_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homepageComponentCategory`
--

DROP TABLE IF EXISTS `homepageComponentCategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepageComponentCategory` (
  `homepageComponentCategoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`homepageComponentCategoryID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homepageTemplate`
--

DROP TABLE IF EXISTS `homepageTemplate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepageTemplate` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `left_column_width` float DEFAULT 69.9,
  `right_column_width` float DEFAULT 29.9,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homepage_config`
--

DROP TABLE IF EXISTS `homepage_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepage_config` (
  `cid` int(6) unsigned NOT NULL DEFAULT 0,
  `template` int(11) unsigned NOT NULL DEFAULT 0,
  `left_column_width` float DEFAULT 29.5,
  `right_column_width` float DEFAULT 79.5,
  `left_head_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_head_bg` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_bg_primary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_bg_secondary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_link` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `left_hover` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_head_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_head_bg` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_bg_primary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_bg_secondary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_link` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `right_hover` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_head_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_head_bg` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_font` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_bg_primary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_bg_secondary` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_link` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `top_hover` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`cid`),
  CONSTRAINT `fk_homepage_config_folder_id` FOREIGN KEY (`cid`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homepage_log`
--

DROP TABLE IF EXISTS `homepage_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepage_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_id` int(6) unsigned NOT NULL,
  `folder_id` int(6) unsigned NOT NULL,
  `hitcount` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`user_id`,`folder_id`),
  KEY `cid` (`folder_id`),
  CONSTRAINT `fk_homepage_log_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_homepage_log_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homepage_visits`
--

DROP TABLE IF EXISTS `homepage_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepage_visits` (
  `date` date NOT NULL,
  `user_group_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `hitcount` int(10) unsigned DEFAULT 1,
  PRIMARY KEY (`date`,`user_group_id`,`category_id`),
  KEY `fk_homepage_visits_folder_id_idx` (`category_id`),
  KEY `fk_homepage_visits_role_id_idx` (`user_group_id`),
  CONSTRAINT `fk_homepage_visits_folder_id` FOREIGN KEY (`category_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_homepage_visits_role_id` FOREIGN KEY (`user_group_id`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `house`
--

DROP TABLE IF EXISTS `house`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `house` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `house` varchar(100) DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_house_user_id` (`updated_by`),
  CONSTRAINT `fk_house_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `innodb_ft_stopword`
--

DROP TABLE IF EXISTS `innodb_ft_stopword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `innodb_ft_stopword` (
  `value` varchar(18) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kami_document`
--

DROP TABLE IF EXISTS `kami_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kami_document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('annotation','worksheet_template','worksheet') NOT NULL,
  `student_submission_file_id` int(11) unsigned DEFAULT NULL,
  `worksheet_template_submission_box_id` int(10) unsigned DEFAULT NULL,
  `kami_document_id` char(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `job_reference` varchar(255) DEFAULT NULL,
  `poll_count` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('queued','uploading','failed','processing','done','error','pending') NOT NULL,
  `error_message` varchar(2048) DEFAULT NULL,
  `kami_created_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_student_submission_file_kami_student_submission_file_id` (`student_submission_file_id`),
  UNIQUE KEY `idx_student_submission_file_kami_kami_document_id` (`kami_document_id`),
  KEY `kami_document_submission_box_id_fk` (`worksheet_template_submission_box_id`),
  CONSTRAINT `fk_kami_student_submission_file_id` FOREIGN KEY (`student_submission_file_id`) REFERENCES `student_submission_file` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `kami_document_submission_box_id_fk` FOREIGN KEY (`worksheet_template_submission_box_id`) REFERENCES `submission_box` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_plan`
--

DROP TABLE IF EXISTS `lesson_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_plan_template_id` int(11) NOT NULL,
  `assessment_id` int(10) unsigned NOT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `visibility` enum('student','staff') NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lesson_plan_lesson_plan_template_idx` (`lesson_plan_template_id`),
  KEY `fk_lesson_plan_assessment` (`assessment_id`),
  CONSTRAINT `fk_lesson_plan_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`),
  CONSTRAINT `fk_lesson_plan_lesson_plan_template` FOREIGN KEY (`lesson_plan_template_id`) REFERENCES `lesson_plan_template` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_plan_assessment`
--

DROP TABLE IF EXISTS `lesson_plan_assessment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plan_assessment` (
  `lesson_plan_id` int(11) NOT NULL,
  `assessment_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `assessment_id_lesson_plan_id_index` (`assessment_id`,`lesson_plan_id`),
  KEY `lesson_plan_id` (`lesson_plan_id`),
  KEY `assessment_id` (`assessment_id`),
  CONSTRAINT `fk_lesson_plan` FOREIGN KEY (`lesson_plan_id`) REFERENCES `lesson_plan` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lesson_plan_activity` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_plan_field`
--

DROP TABLE IF EXISTS `lesson_plan_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plan_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_plan_id` int(11) NOT NULL,
  `lesson_plan_template_field_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `description` text DEFAULT NULL,
  `visibility` enum('student','staff') NOT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lesson_plan_field_lesson_plan_idx` (`lesson_plan_id`),
  KEY `fk_lesson_plan_field_lesson_plan_template_field_idx` (`lesson_plan_template_field_id`),
  CONSTRAINT `fk_lesson_plan_field_lesson_plan` FOREIGN KEY (`lesson_plan_id`) REFERENCES `lesson_plan` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lesson_plan_field_lesson_plan_template_field` FOREIGN KEY (`lesson_plan_template_field_id`) REFERENCES `lesson_plan_template_field` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_plan_file`
--

DROP TABLE IF EXISTS `lesson_plan_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plan_file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_plan_id` int(11) NOT NULL,
  `storage_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_plan_id` (`lesson_plan_id`),
  KEY `fk_lesson_plan_file` (`storage_hash`),
  CONSTRAINT `fk_lesson_plan_file` FOREIGN KEY (`storage_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lesson_plan_file_lesson_plan` FOREIGN KEY (`lesson_plan_id`) REFERENCES `lesson_plan` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_plan_template`
--

DROP TABLE IF EXISTS `lesson_plan_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plan_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `enabled` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `enabled` (`enabled`),
  KEY `creator` (`created_by`),
  KEY `updater` (`updated_by`),
  KEY `deleter` (`deleted_by`),
  CONSTRAINT `fk_lesson_plan_template_creator` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lesson_plan_template_deleter` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lesson_plan_template_updater` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_plan_template_field`
--

DROP TABLE IF EXISTS `lesson_plan_template_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plan_template_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_plan_template_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `description` text DEFAULT NULL,
  `visibility` enum('student','staff') NOT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lesson_plan_template_idx` (`lesson_plan_template_id`),
  CONSTRAINT `fk_lesson_plan_template` FOREIGN KEY (`lesson_plan_template_id`) REFERENCES `lesson_plan_template` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `link`
--

DROP TABLE IF EXISTS `link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `link` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `category` int(5) unsigned NOT NULL DEFAULT 0,
  `owner` int(5) unsigned NOT NULL DEFAULT 0,
  `url` varchar(2048) DEFAULT NULL,
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` datetime DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `hidden` tinyint(1) NOT NULL,
  `target` enum('','_blank') DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `deleted` (`deleted_at`),
  KEY `owner` (`owner`),
  CONSTRAINT `fk_link_folder_id` FOREIGN KEY (`category`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_link_owner_id` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `localisation`
--

DROP TABLE IF EXISTS `localisation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `localisation` (
  `term` varchar(128) NOT NULL,
  `title` varchar(256) DEFAULT NULL,
  `singular_form` varchar(256) DEFAULT NULL,
  `plural_form` varchar(256) DEFAULT NULL,
  `capitalise_first` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `indefinite_article` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_log`
--

DROP TABLE IF EXISTS `login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_log` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip` varchar(39) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL COMMENT 'coverage for both ipv4 and ipv6 addresses',
  `login` tinyint(1) unsigned DEFAULT NULL,
  `logout` tinyint(1) unsigned DEFAULT NULL,
  `jwt_id` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `identifier` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lti_assessment`
--

DROP TABLE IF EXISTS `lti_assessment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lti_assessment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `guid` uuid DEFAULT NULL,
  `assessment_id` int(10) unsigned NOT NULL,
  `lti_provider_id` int(11) DEFAULT NULL,
  `lti_assessment_url` varchar(2048) DEFAULT NULL,
  `lti_assessment_title` varchar(2048) DEFAULT NULL,
  `lti_assessment_text` varchar(2048) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `line_item_resource_id` varchar(2048) DEFAULT NULL,
  `line_item_score_maximum` float DEFAULT NULL,
  `mark_visibility` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lti_assessment_lti_provider_id_fk` (`lti_provider_id`),
  KEY `lti_assessment_assessment_id_fk` (`assessment_id`),
  KEY `lti_assessment_guid_index` (`guid`),
  CONSTRAINT `lti_assessment_assessment_id_fk` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`),
  CONSTRAINT `lti_assessment_lti_provider_id_fk` FOREIGN KEY (`lti_provider_id`) REFERENCES `lti_provider` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lti_assessment_score`
--

DROP TABLE IF EXISTS `lti_assessment_score`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lti_assessment_score` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lti_assessment_id` int(10) unsigned NOT NULL,
  `lti_line_item_id` int(10) unsigned DEFAULT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `marking_user_id` int(10) unsigned DEFAULT NULL,
  `score_given` float DEFAULT NULL,
  `score_maximum` float DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  `activity_progress` enum('Initialized','Started','InProgress','Submitted','Completed') NOT NULL,
  `grading_progress` enum('NotReady','Failed','Pending','PendingManual','FullyGraded') NOT NULL,
  `is_response` tinyint(1) NOT NULL DEFAULT 0,
  `is_feedback` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lti_assessment_score_lti_assessment_id_fk` (`lti_assessment_id`),
  KEY `lti_assessment_score_student_id_fk` (`student_id`),
  KEY `lti_assessment_score_marking_user_id_fk` (`marking_user_id`),
  KEY `lti_assessment_score_lti_line_item_id_fk` (`lti_line_item_id`),
  CONSTRAINT `lti_assessment_score_lti_assessment_id_fk` FOREIGN KEY (`lti_assessment_id`) REFERENCES `lti_assessment` (`id`),
  CONSTRAINT `lti_assessment_score_lti_line_item_id_fk` FOREIGN KEY (`lti_line_item_id`) REFERENCES `lti_line_item` (`id`),
  CONSTRAINT `lti_assessment_score_marking_user_id_fk` FOREIGN KEY (`marking_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `lti_assessment_score_student_id_fk` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lti_line_item`
--

DROP TABLE IF EXISTS `lti_line_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lti_line_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lti_assessment_id` int(10) unsigned DEFAULT NULL,
  `lti_assessment_guid` uuid DEFAULT NULL,
  `identifier_url` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `score_maximum` float unsigned DEFAULT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `resource_link_id` varchar(255) DEFAULT NULL,
  `tag` varchar(255) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lti_line_item_lti_assessment_id_fk` (`lti_assessment_id`),
  KEY `lti_line_item_lti_assessment_guid_index` (`lti_assessment_guid`),
  KEY `lti_line_item_identifier_url_index` (`identifier_url`),
  CONSTRAINT `lti_line_item_lti_assessment_id_fk` FOREIGN KEY (`lti_assessment_id`) REFERENCES `lti_assessment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lti_provider`
--

DROP TABLE IF EXISTS `lti_provider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lti_provider` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `lti_version` enum('1.1','1.3') NOT NULL,
  `client_id` varchar(2048) DEFAULT NULL,
  `deployment_id` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `audience` varchar(2048) DEFAULT NULL,
  `oidc_initiation_url` varchar(2048) DEFAULT NULL,
  `launch_url` varchar(2048) DEFAULT NULL,
  `deep_linking_url` varchar(2048) DEFAULT NULL,
  `jwks_url` varchar(2048) DEFAULT NULL,
  `public_key` text DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `secret` varchar(255) DEFAULT NULL,
  `xml` varchar(2048) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `custom` text DEFAULT NULL,
  `target` enum('iframe','window') NOT NULL DEFAULT 'iframe',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `launch_type` enum('LTI_LAUNCH_TYPE_BASIC','LTI_LAUNCH_TYPE_PICKER') NOT NULL DEFAULT 'LTI_LAUNCH_TYPE_BASIC',
  `available_for_component` tinyint(1) DEFAULT 1,
  `available_for_assessment` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lti_provider_redirect_url`
--

DROP TABLE IF EXISTS `lti_provider_redirect_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lti_provider_redirect_url` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lti_provider_id` int(11) NOT NULL,
  `redirect_url` varchar(2048) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lti_provider_redirect_url_lti_provider_id_fk` (`lti_provider_id`),
  CONSTRAINT `lti_provider_redirect_url_lti_provider_id_fk` FOREIGN KEY (`lti_provider_id`) REFERENCES `lti_provider` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `luCampus`
--

DROP TABLE IF EXISTS `luCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `luCampus` (
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `ext_timetable_code` varchar(255) DEFAULT NULL,
  `timetable_day` tinyint(4) DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_luCampus_user_id` (`updated_by`),
  CONSTRAINT `fk_luCampus_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `luEventType`
--

DROP TABLE IF EXISTS `luEventType`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `luEventType` (
  `eventTypeID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventType` varchar(100) NOT NULL,
  `eventColour` varchar(10) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`eventTypeID`),
  KEY `event_type_idx` (`eventType`),
  KEY `fk_luEventType_user_id` (`updated_by`),
  CONSTRAINT `fk_luEventType_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `luResidentialHouse`
--

DROP TABLE IF EXISTS `luResidentialHouse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `luResidentialHouse` (
  `id` int(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_luResidentialHouse_user_id` (`updated_by`),
  CONSTRAINT `fk_luResidentialHouse_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `luTitle`
--

DROP TABLE IF EXISTS `luTitle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `luTitle` (
  `titleID` int(11) NOT NULL AUTO_INCREMENT,
  `titleName` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`titleID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_config_field`
--

DROP TABLE IF EXISTS `lu_config_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_config_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lu_config_field_field` (`field`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_config_type`
--

DROP TABLE IF EXISTS `lu_config_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_config_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(40) DEFAULT NULL,
  `sequence` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lu_config_type_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_moment_tag`
--

DROP TABLE IF EXISTS `lu_moment_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_moment_tag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) DEFAULT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_pastoral_action`
--

DROP TABLE IF EXISTS `lu_pastoral_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_pastoral_action` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `external_code` varchar(50) DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_pastoral_document_classification`
--

DROP TABLE IF EXISTS `lu_pastoral_document_classification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_pastoral_document_classification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `external_code` varchar(50) DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lu_pastoral_document_classification_user_id` (`updated_by`),
  CONSTRAINT `fk_lu_pastoral_document_classification_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_pastoral_severity`
--

DROP TABLE IF EXISTS `lu_pastoral_severity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_pastoral_severity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `dashboard` tinyint(1) NOT NULL DEFAULT 0,
  `color` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lu_pastoral_severity_user_id` (`updated_by`),
  CONSTRAINT `fk_lu_pastoral_severity_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_pastoral_subtype`
--

DROP TABLE IF EXISTS `lu_pastoral_subtype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_pastoral_subtype` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `template` mediumtext DEFAULT NULL,
  `color` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `external_code` varchar(50) DEFAULT NULL,
  `visibility_locked` tinyint(1) DEFAULT 0,
  `sentiment` enum('positive','neutral','negative') NOT NULL DEFAULT 'neutral',
  `default_points` float DEFAULT NULL,
  `default_expiry` varchar(255) DEFAULT NULL,
  `contribute_differentiation_profile` tinyint(1) NOT NULL DEFAULT 0,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  `template_private` mediumtext DEFAULT NULL,
  `entry_tip` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lu_pastoral_category_pastoral_type_id_idx` (`pastoral_type_id`),
  KEY `fk_lu_pastoral_subtype_user_id` (`updated_by`),
  CONSTRAINT `fk_lu_pastoral_category_pastoral_type_id` FOREIGN KEY (`pastoral_type_id`) REFERENCES `lu_pastoral_type` (`id`),
  CONSTRAINT `fk_lu_pastoral_subtype_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_pastoral_tag`
--

DROP TABLE IF EXISTS `lu_pastoral_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_pastoral_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_pastoral_type`
--

DROP TABLE IF EXISTS `lu_pastoral_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_pastoral_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_document_classification_id` int(11) DEFAULT NULL,
  `pastoral_document_classification_confidential_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `template` mediumtext DEFAULT NULL,
  `color` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `external_code` varchar(50) DEFAULT NULL,
  `visibility_locked` tinyint(1) DEFAULT 0,
  `sentiment` enum('positive','neutral','negative') NOT NULL DEFAULT 'neutral',
  `default_points` float DEFAULT NULL,
  `default_expiry` varchar(255) DEFAULT NULL,
  `contribute_differentiation_profile` tinyint(1) NOT NULL DEFAULT 0,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  `template_private` mediumtext DEFAULT NULL,
  `entry_tip` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lu_pastoral_type_pastoral_document_classification_id_idx` (`pastoral_document_classification_id`),
  KEY `fk_lu_pastoral_type_user_id` (`updated_by`),
  KEY `fk_lu_pastoral_type_pastoral_document_classification_conf_id` (`pastoral_document_classification_confidential_id`),
  CONSTRAINT `fk_lu_pastoral_type_pastoral_document_classification_conf_id` FOREIGN KEY (`pastoral_document_classification_confidential_id`) REFERENCES `lu_pastoral_document_classification` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lu_pastoral_type_pastoral_document_classification_id` FOREIGN KEY (`pastoral_document_classification_id`) REFERENCES `lu_pastoral_document_classification` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_lu_pastoral_type_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_qualification`
--

DROP TABLE IF EXISTS `lu_qualification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_qualification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `system_purpose` text DEFAULT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_sequence` (`sequence`),
  KEY `idx_system_purpose` (`system_purpose`(1024))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_resource_booking_status`
--

DROP TABLE IF EXISTS `lu_resource_booking_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_resource_booking_status` (
  `id` tinyint(4) NOT NULL,
  `name` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_resource_moderation_type`
--

DROP TABLE IF EXISTS `lu_resource_moderation_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_resource_moderation_type` (
  `id` tinyint(4) NOT NULL,
  `name` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_service`
--

DROP TABLE IF EXISTS `lu_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_service` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_skin_config_category`
--

DROP TABLE IF EXISTS `lu_skin_config_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_skin_config_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_skin_config_subcategory`
--

DROP TABLE IF EXISTS `lu_skin_config_subcategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_skin_config_subcategory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_skin_config_category_category_id` (`category_id`),
  CONSTRAINT `fk_skin_config_category_category` FOREIGN KEY (`category_id`) REFERENCES `lu_skin_config_category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_timetable_colour`
--

DROP TABLE IF EXISTS `lu_timetable_colour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_timetable_colour` (
  `timetable_colour_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hex_colour` varchar(8) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`timetable_colour_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_work_type`
--

DROP TABLE IF EXISTS `lu_work_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_work_type` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) DEFAULT NULL,
  `external_result_type` varchar(50) DEFAULT NULL,
  `show_on_grades_page` tinyint(1) DEFAULT NULL,
  `staff_or_instructor_visible_only` tinyint(1) NOT NULL,
  `is_visible_to_parents` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_visible_to_students` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_project` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_due_work` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_task` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_quiz` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_lti` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_lesson_plan` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `permitted_dialog_agent` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lu_work_type_user_id` (`updated_by`),
  CONSTRAINT `fk_lu_work_type_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lu_year_level`
--

DROP TABLE IF EXISTS `lu_year_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lu_year_level` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `overall_grade` int(11) DEFAULT NULL,
  `show_overall_grade` tinyint(1) DEFAULT 1,
  `display_grade_page` tinyint(1) DEFAULT 1,
  `display_assessment_grade` tinyint(1) DEFAULT 1,
  `display_rubric_grade` tinyint(1) DEFAULT 1,
  `display_capability_grade` tinyint(1) DEFAULT 1,
  `display_progression_grade` tinyint(1) DEFAULT 1,
  `show_rubric_points` tinyint(1) DEFAULT 1,
  `lms_cohort_comparison` tinyint(1) NOT NULL DEFAULT 0,
  `learning_moments_enabled` tinyint(1) DEFAULT 1,
  `qrcode_enabled` tinyint(1) DEFAULT 0,
  `birthday_celebration_enabled` tinyint(1) DEFAULT 0,
  `learning_moments_moderation` tinyint(1) DEFAULT 0,
  `sequence` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_icons`
--

DROP TABLE IF EXISTS `menu_icons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_icons` (
  `name` varchar(100) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_side`
--

DROP TABLE IF EXISTS `menu_side`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_side` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `target` varchar(200) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=295 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_side_campus`
--

DROP TABLE IF EXISTS `menu_side_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_side_campus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_side_id` int(11) NOT NULL,
  `campus_id` tinyint(2) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_side_campus_menu_side_id_fk` (`menu_side_id`),
  KEY `fk_menu_side_campus_campus_id_idx` (`campus_id`),
  CONSTRAINT `fk_menu_side_campus_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `menu_side_campus_menu_side_id_fk` FOREIGN KEY (`menu_side_id`) REFERENCES `menu_side` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_side_role`
--

DROP TABLE IF EXISTS `menu_side_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_side_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_side_id` int(11) NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_side_role_menu_side_id_fk` (`menu_side_id`),
  KEY `fk_menu_side_role_role_id_idx` (`role_id`),
  CONSTRAINT `fk_menu_side_role_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `menu_side_role_menu_side_id_fk` FOREIGN KEY (`menu_side_id`) REFERENCES `menu_side` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_side_section`
--

DROP TABLE IF EXISTS `menu_side_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_side_section` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section` enum('menu','groups','subjects','children','bookmarks') DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `visible` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_top`
--

DROP TABLE IF EXISTS `menu_top`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_top` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sort_order` int(11) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `text` varchar(200) DEFAULT NULL,
  `token` varchar(200) DEFAULT NULL,
  `icon` varchar(200) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `target` varchar(200) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `plan_hide` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_top_campus`
--

DROP TABLE IF EXISTS `menu_top_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_top_campus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_top_id` int(11) NOT NULL,
  `campus_id` tinyint(2) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_top_campus_menu_top_id_fk` (`menu_top_id`),
  KEY `fk_menu_top_campus_campus_id_idx` (`campus_id`),
  CONSTRAINT `fk_menu_top_campus_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `menu_top_campus_menu_top_id_fk` FOREIGN KEY (`menu_top_id`) REFERENCES `menu_top` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_top_role`
--

DROP TABLE IF EXISTS `menu_top_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_top_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_top_id` int(11) NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_top_role_menu_top_id_fk` (`menu_top_id`),
  KEY `fk_menu_top_role_role_id_idx` (`role_id`),
  CONSTRAINT `fk_menu_top_role_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `menu_top_role_menu_top_id_fk` FOREIGN KEY (`menu_top_id`) REFERENCES `menu_top` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mobile_app`
--

DROP TABLE IF EXISTS `mobile_app`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mobile_app` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_identifier` varchar(128) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `provider` enum('APNS','GCM','Digistorm','Firebase','TassOrbit') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `key` text NOT NULL,
  `public_configuration` text DEFAULT NULL,
  `web_push_public_key` varchar(512) DEFAULT NULL,
  `cert_hash` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_identifier` (`app_identifier`,`provider`),
  KEY `cert_hash` (`cert_hash`),
  CONSTRAINT `mobile_app_ibfk_1` FOREIGN KEY (`cert_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mobile_app_registry`
--

DROP TABLE IF EXISTS `mobile_app_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mobile_app_registry` (
  `user_id` int(10) unsigned NOT NULL,
  `app_id` int(10) unsigned NOT NULL,
  `app_token` varchar(4096) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `device_id` varchar(256) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `touched_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`app_token`(255),`app_id`),
  KEY `app_token` (`app_token`(255)),
  KEY `user_id` (`user_id`),
  KEY `mobile_app_registry_app_id` (`app_id`),
  CONSTRAINT `mobile_app_registry_app_id` FOREIGN KEY (`app_id`) REFERENCES `mobile_app` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `mobile_app_registry_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `moment`
--

DROP TABLE IF EXISTS `moment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `moment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `body` mediumtext DEFAULT NULL,
  `discussion_thread_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_evidence_discussion_thread_id_idx` (`discussion_thread_id`),
  KEY `fk_evidence_user_id_idx` (`user_id`),
  KEY `fk_evidence_created_by_idx` (`created_by`),
  KEY `fk_evidence_published_by` (`published_by`),
  KEY `fk_evidence_rejected_by` (`rejected_by`),
  KEY `fk_evidence_submitted_by` (`submitted_by`),
  CONSTRAINT `fk_evidence_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_evidence_discussion_thread_id` FOREIGN KEY (`discussion_thread_id`) REFERENCES `discussion_thread` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_evidence_published_by` FOREIGN KEY (`published_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_evidence_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_evidence_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_evidence_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `moment_evidence`
--

DROP TABLE IF EXISTS `moment_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `moment_evidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moment_id` int(10) unsigned NOT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `moment_evidence_hash_index` (`hash`),
  KEY `moment_evidence_moment_id_index` (`moment_id`),
  CONSTRAINT `moment_evidence_moment_id_fk` FOREIGN KEY (`moment_id`) REFERENCES `moment` (`id`),
  CONSTRAINT `moment_evidence_storage_hash_fk` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `moment_folder`
--

DROP TABLE IF EXISTS `moment_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `moment_folder` (
  `moment_id` int(10) unsigned NOT NULL,
  `folder_id` int(10) unsigned NOT NULL,
  KEY `moment_folder_folder_id_fk` (`folder_id`),
  KEY `moment_folder_moment_id_folder_id_index` (`moment_id`,`folder_id`),
  CONSTRAINT `moment_folder_folder_id_fk` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`),
  CONSTRAINT `moment_folder_moment_id_fk` FOREIGN KEY (`moment_id`) REFERENCES `moment` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `moment_tag`
--

DROP TABLE IF EXISTS `moment_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `moment_tag` (
  `moment_id` int(10) unsigned NOT NULL,
  `tag_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`moment_id`,`tag_id`),
  KEY `fk_evidence_tag_tag_id` (`tag_id`),
  CONSTRAINT `fk_evidence_tag_evidence_id` FOREIGN KEY (`moment_id`) REFERENCES `moment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_evidence_tag_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `lu_moment_tag` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_namespace`
--

DROP TABLE IF EXISTS `msg_namespace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `msg_namespace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `label` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_redirect`
--

DROP TABLE IF EXISTS `msg_redirect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `msg_redirect` (
  `from` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `to` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  UNIQUE KEY `from_2` (`from`,`to`),
  KEY `from` (`from`),
  KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table is ascii on purpose, route data is always ascii.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_reroute`
--

DROP TABLE IF EXISTS `msg_reroute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `msg_reroute` (
  `user_id` int(10) unsigned NOT NULL,
  `msg_source_id` int(10) unsigned NOT NULL,
  `delivery` set('MU','DI','IN','MO','EM') DEFAULT NULL,
  PRIMARY KEY (`user_id`,`msg_source_id`),
  KEY `delivery` (`delivery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_reroute_override`
--

DROP TABLE IF EXISTS `msg_reroute_override`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `msg_reroute_override` (
  `user_id` int(10) unsigned NOT NULL,
  `msg_route_key` varchar(64) NOT NULL,
  `delivery` set('MU','DI','IN','MO','EM') DEFAULT NULL,
  PRIMARY KEY (`user_id`,`msg_route_key`),
  KEY `user_id` (`user_id`),
  KEY `delivery` (`delivery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_route_perm`
--

DROP TABLE IF EXISTS `msg_route_perm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `msg_route_perm` (
  `msg_source_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `mask` set('MU','DI','IN','MO','EM') DEFAULT NULL,
  `role_default` set('MU','DI','IN','MO','EM') DEFAULT NULL,
  PRIMARY KEY (`msg_source_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `mask` (`mask`),
  KEY `role_default` (`role_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_source`
--

DROP TABLE IF EXISTS `msg_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `msg_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `namespace` int(10) NOT NULL,
  `key` varchar(64) DEFAULT NULL,
  `role_type` enum('student','parent','staff') DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `namespace` (`namespace`,`key`,`role_type`),
  KEY `namespace_2` (`namespace`,`key`),
  KEY `key` (`key`),
  KEY `namespace_3` (`namespace`),
  KEY `role_type` (`role_type`)
) ENGINE=InnoDB AUTO_INCREMENT=194 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mv_assessment_relation`
--

DROP TABLE IF EXISTS `mv_assessment_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mv_assessment_relation` (
  `assessment_id` int(10) unsigned NOT NULL,
  `folder_id` int(10) unsigned NOT NULL,
  `project_assessment_id` int(10) unsigned DEFAULT NULL,
  `course_implementation_id` int(10) unsigned DEFAULT NULL,
  `course_unit_implementation_id` int(10) unsigned DEFAULT NULL,
  `course_specification_id` int(10) unsigned DEFAULT NULL,
  `course_unit_specification_id` int(10) unsigned DEFAULT NULL,
  KEY `mv_assessment_relation_assessment_id_fk` (`assessment_id`),
  KEY `mv_assessment_relation_course_implementation_id_fk` (`course_implementation_id`),
  KEY `mv_assessment_relation_course_specification_id_fk` (`course_specification_id`),
  KEY `mv_assessment_relation_course_unit_implementation_id_fk` (`course_unit_implementation_id`),
  KEY `mv_assessment_relation_course_unit_specification_id_fk` (`course_unit_specification_id`),
  KEY `mv_assessment_relation_folder_id_fk` (`folder_id`),
  KEY `mv_assessment_relation_project_id_fk` (`project_assessment_id`),
  CONSTRAINT `mv_assessment_relation_assessment_id_fk` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mv_assessment_relation_course_implementation_id_fk` FOREIGN KEY (`course_implementation_id`) REFERENCES `course_implementation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mv_assessment_relation_course_specification_id_fk` FOREIGN KEY (`course_specification_id`) REFERENCES `course_specification` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mv_assessment_relation_course_unit_implementation_id_fk` FOREIGN KEY (`course_unit_implementation_id`) REFERENCES `course_unit_implementation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mv_assessment_relation_course_unit_specification_id_fk` FOREIGN KEY (`course_unit_specification_id`) REFERENCES `course_unit_specification` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mv_assessment_relation_folder_id_fk` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mv_assessment_relation_project_id_fk` FOREIGN KEY (`project_assessment_id`) REFERENCES `project` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `my_file`
--

DROP TABLE IF EXISTS `my_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `my_file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent` int(11) DEFAULT NULL,
  `_parent` int(11) NOT NULL DEFAULT 0,
  `owner` int(10) unsigned DEFAULT NULL,
  `_owner` int(11) NOT NULL DEFAULT 0,
  `realname` tinytext DEFAULT NULL,
  `submitted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `description` longtext DEFAULT NULL,
  `published` datetime DEFAULT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `parent` (`parent`),
  KEY `owner` (`owner`),
  KEY `hash` (`hash`),
  CONSTRAINT `fk_myFile_storage_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `myFile_myFolder_id_fk` FOREIGN KEY (`parent`) REFERENCES `my_folder` (`id`),
  CONSTRAINT `myFile_user_id_fk` FOREIGN KEY (`owner`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `my_folder`
--

DROP TABLE IF EXISTS `my_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `my_folder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) unsigned NOT NULL DEFAULT 0,
  `parent` int(11) DEFAULT NULL,
  `_parent` int(11) NOT NULL DEFAULT 0,
  `name` mediumtext DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `parent` (`parent`),
  KEY `owner` (`owner`),
  CONSTRAINT `fk_myFolder_owner_id` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `myFolder_myFolder_id_fk` FOREIGN KEY (`parent`) REFERENCES `my_folder` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `newsTopic`
--

DROP TABLE IF EXISTS `newsTopic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsTopic` (
  `newsTopicID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `updated_by` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`newsTopicID`),
  UNIQUE KEY `idx_newsTopic_slug` (`slug`),
  KEY `fk_newsTopic_id` (`updated_by`),
  CONSTRAINT `fk_newsTopic_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oidc_relying_party`
--

DROP TABLE IF EXISTS `oidc_relying_party`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oidc_relying_party` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `client_secret_hash` varchar(100) NOT NULL,
  `redirect_uri` varchar(250) NOT NULL,
  `back_channel_logout_uri` varchar(250) DEFAULT NULL,
  `client_credentials_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oidc_client_client_id` (`client_id`),
  KEY `oidc_relying_party_user_id_fk` (`client_credentials_user_id`),
  CONSTRAINT `oidc_relying_party_user_id_fk` FOREIGN KEY (`client_credentials_user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oidc_relying_party_grant`
--

DROP TABLE IF EXISTS `oidc_relying_party_grant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oidc_relying_party_grant` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `oidc_relying_party_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oidc_relying_party_grant_oidc_relying_party_id_fk` (`oidc_relying_party_id`),
  KEY `oidc_relying_party_grant_user_id_fk` (`user_id`),
  CONSTRAINT `oidc_relying_party_grant_oidc_relying_party_id_fk` FOREIGN KEY (`oidc_relying_party_id`) REFERENCES `oidc_relying_party` (`id`),
  CONSTRAINT `oidc_relying_party_grant_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oidc_relying_party_grant_scope`
--

DROP TABLE IF EXISTS `oidc_relying_party_grant_scope`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oidc_relying_party_grant_scope` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `oidc_relying_party_grant_id` int(10) unsigned NOT NULL,
  `scope` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oidc_relying_party_grant_scope_uindex` (`oidc_relying_party_grant_id`,`scope`),
  CONSTRAINT `oidc_relying_party_grant_scope_oidc_relying_party_grant_id_fk` FOREIGN KEY (`oidc_relying_party_grant_id`) REFERENCES `oidc_relying_party_grant` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `openai_log`
--

DROP TABLE IF EXISTS `openai_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `openai_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `session_id` char(36) DEFAULT NULL,
  `prompt` text NOT NULL,
  `input_tokens` int(11) DEFAULT NULL,
  `output_tokens` int(11) DEFAULT NULL,
  `input_text` text DEFAULT NULL,
  `output_text` text DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `feature` enum('chat','lessonplan','image','differentiation','dialogagent','learningactivitycustomisation') DEFAULT NULL,
  `log_format` enum('agent_chat','guardrail') DEFAULT NULL,
  `response` mediumtext DEFAULT NULL,
  `return_code` int(11) DEFAULT NULL,
  `error_message` varchar(1024) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `openai_log_user_id_fk` (`user_id`),
  KEY `openai_log_session` (`session_id`),
  CONSTRAINT `openai_log_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `package`
--

DROP TABLE IF EXISTS `package`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(250) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(250) NOT NULL,
  `installed` tinyint(1) DEFAULT NULL,
  `hidden` enum('true','false') DEFAULT 'false',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contains packages that are available for install';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `participant`
--

DROP TABLE IF EXISTS `participant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `participant` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `participant_plan_id` int(10) unsigned NOT NULL,
  `user_id` int(4) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `excluded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_participant_plan_id_user_id` (`participant_plan_id`,`user_id`),
  KEY `participant_user_id_fk` (`user_id`),
  CONSTRAINT `participant_participant_plan_id_fk` FOREIGN KEY (`participant_plan_id`) REFERENCES `participant_plan` (`id`),
  CONSTRAINT `participant_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=469 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `participant_plan`
--

DROP TABLE IF EXISTS `participant_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `participant_plan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `is_dynamic` tinyint(4) NOT NULL DEFAULT 0,
  `name` varchar(256) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `publish_date` datetime DEFAULT NULL,
  `open_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_plan_assessment_id_fk` (`assessment_id`),
  KEY `participant_plan_duedate_index` (`due_date`),
  CONSTRAINT `participant_plan_assessment_id_fk` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=583 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral`
--

DROP TABLE IF EXISTS `pastoral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `owner_id` int(11) unsigned NOT NULL,
  `severity_id` int(11) DEFAULT NULL,
  `student_id` int(11) unsigned NOT NULL,
  `type_id` int(11) NOT NULL,
  `subtype_id` int(11) DEFAULT NULL,
  `external_id` int(11) DEFAULT NULL,
  `heading` varchar(255) NOT NULL,
  `body_private` mediumtext NOT NULL,
  `body_public` mediumtext DEFAULT NULL,
  `points` float DEFAULT 0,
  `modified_by_user_id` int(11) unsigned NOT NULL,
  `occurred_on` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_severity` (`severity_id`),
  KEY `fk_type` (`type_id`),
  KEY `fk_student` (`student_id`),
  KEY `fk_pastoral_owner_id_idx` (`owner_id`),
  KEY `fk_pastoral_category_id_idx` (`subtype_id`),
  KEY `fk_pastoral_modified_by_user_id` (`modified_by_user_id`),
  KEY `pastoral_deleted_at_index` (`deleted_at`),
  KEY `fk_pastoral_parent_id` (`parent_id`),
  CONSTRAINT `fk_pastoral_category_id` FOREIGN KEY (`subtype_id`) REFERENCES `lu_pastoral_subtype` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_modified_by_user_id` FOREIGN KEY (`modified_by_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_pastoral_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_pastoral_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `pastoral` (`id`),
  CONSTRAINT `fk_pastoral_severity` FOREIGN KEY (`severity_id`) REFERENCES `lu_pastoral_severity` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_student_id` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_type` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_access`
--

DROP TABLE IF EXISTS `pastoral_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `role_id` int(11) unsigned DEFAULT NULL,
  `role_type` enum('guest','student','parent','staff') DEFAULT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `access` int(11) DEFAULT 0,
  `match_campus` tinyint(1) DEFAULT 0,
  `comments_allowed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  KEY `fk_pastoral_access_role_id_idx` (`role_id`),
  KEY `fk_pastoral_access_user_id_idx` (`user_id`),
  KEY `fk_pastoral_access_group_id_idx` (`group_id`),
  KEY `pastoral_access_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_pastoral_access` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_access_group_id` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_access_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_access_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_attachment`
--

DROP TABLE IF EXISTS `pastoral_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `private` int(11) DEFAULT 0,
  `external_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  KEY `fk_pastoral_attachment_hash` (`hash`),
  CONSTRAINT `fk_pastoral_attachment` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_attachment_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_audience`
--

DROP TABLE IF EXISTS `pastoral_audience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_audience` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) DEFAULT NULL,
  `subtype_id` int(11) DEFAULT NULL,
  `access` int(11) NOT NULL,
  `match_campus` tinyint(1) DEFAULT 0,
  `comments_allowed` tinyint(1) DEFAULT 0,
  `user_id` int(11) unsigned DEFAULT NULL,
  `role_id` int(11) unsigned DEFAULT NULL,
  `role_type` enum('guest','student','parent','staff') DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pastoral_audience_type_id` (`type_id`),
  KEY `pastoral_audience_subtype_id` (`subtype_id`),
  KEY `pastoral_audience_user_id` (`user_id`),
  KEY `pastoral_audience_role_id` (`role_id`),
  KEY `pastoral_audience_group_id` (`group_id`),
  CONSTRAINT `pastoral_audience_group_id` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`),
  CONSTRAINT `pastoral_audience_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `pastoral_audience_subtype_id` FOREIGN KEY (`subtype_id`) REFERENCES `lu_pastoral_subtype` (`id`),
  CONSTRAINT `pastoral_audience_type_id` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`),
  CONSTRAINT `pastoral_audience_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_audit`
--

DROP TABLE IF EXISTS `pastoral_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `report_id` int(11) DEFAULT NULL,
  `pastoral_id` int(11) DEFAULT NULL,
  `search` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_bulk_import`
--

DROP TABLE IF EXISTS `pastoral_bulk_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_bulk_import` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(10) unsigned NOT NULL,
  `zip_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `file_path_template` varchar(255) NOT NULL,
  `severity_id` int(11) DEFAULT NULL,
  `type_id` int(11) NOT NULL,
  `subtype_id` int(11) DEFAULT NULL,
  `heading` varchar(255) NOT NULL,
  `body_public` mediumtext NOT NULL,
  `points` float DEFAULT 0,
  `expires_at` date DEFAULT NULL,
  `imported_at` datetime DEFAULT NULL,
  `imported_by` int(10) unsigned DEFAULT NULL,
  `import_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`import_log`)),
  `undone_at` datetime DEFAULT NULL,
  `undone_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pastoral_bulk_import_imported_at_index` (`imported_at`),
  KEY `pastoral_bulk_import_deleted_at_index` (`deleted_at`),
  KEY `fk_pastoral_bulk_import_owner_id` (`owner_id`),
  KEY `fk_pastoral_bulk_import_zip_hash` (`zip_hash`),
  KEY `fk_pastoral_bulk_import_severity` (`severity_id`),
  KEY `fk_pastoral_bulk_import_type` (`type_id`),
  KEY `fk_pastoral_bulk_import_subtype` (`subtype_id`),
  KEY `fk_pastoral_bulk_import_imported_by` (`imported_by`),
  KEY `fk_pastoral_bulk_import_undone_by` (`undone_by`),
  CONSTRAINT `fk_pastoral_bulk_import_imported_by` FOREIGN KEY (`imported_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_severity` FOREIGN KEY (`severity_id`) REFERENCES `lu_pastoral_severity` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_subtype` FOREIGN KEY (`subtype_id`) REFERENCES `lu_pastoral_subtype` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_type` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_undone_by` FOREIGN KEY (`undone_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_zip_hash` FOREIGN KEY (`zip_hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_bulk_import_access`
--

DROP TABLE IF EXISTS `pastoral_bulk_import_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_bulk_import_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_bulk_import_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `role_type` enum('guest','student','parent','staff') DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `access` int(11) DEFAULT 0,
  `match_campus` tinyint(4) DEFAULT 0,
  `comments_allowed` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pastoral_bulk_import_access_deleted_at_index` (`deleted_at`),
  KEY `fk_pastoral_bulk_import_access_group_id` (`group_id`),
  KEY `fk_pastoral_bulk_import_access_role_id` (`role_id`),
  KEY `fk_pastoral_bulk_import_user_id` (`user_id`),
  KEY `fk_pastoral_bulk_import_access_pastoral_bulk_import_id` (`pastoral_bulk_import_id`),
  CONSTRAINT `fk_pastoral_bulk_import_access_group_id` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_access_pastoral_bulk_import_id` FOREIGN KEY (`pastoral_bulk_import_id`) REFERENCES `pastoral_bulk_import` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_access_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_bulk_import_tags`
--

DROP TABLE IF EXISTS `pastoral_bulk_import_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_bulk_import_tags` (
  `pastoral_bulk_import_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  UNIQUE KEY `unique_pastoral_bulk_import_tag_idx` (`pastoral_bulk_import_id`,`tag_id`),
  KEY `fk_pastoral_bulk_import_tags_tag` (`tag_id`),
  CONSTRAINT `fk_pastoral_bulk_import_tags_pastoral` FOREIGN KEY (`pastoral_bulk_import_id`) REFERENCES `pastoral_bulk_import` (`id`),
  CONSTRAINT `fk_pastoral_bulk_import_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `lu_pastoral_tag` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_comment`
--

DROP TABLE IF EXISTS `pastoral_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `owner_id` int(11) unsigned NOT NULL,
  `body` text NOT NULL,
  `external_code` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `private` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  KEY `fk_pastoral_comment_owner_id_idx` (`owner_id`),
  KEY `pastoral_comment_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_pastoral_comment` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_comment_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_group`
--

DROP TABLE IF EXISTS `pastoral_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_group_user_id_idx` (`user_id`),
  CONSTRAINT `fk_pastoral_group_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_group_member`
--

DROP TABLE IF EXISTS `pastoral_group_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_group_member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `group_id` int(11) NOT NULL,
  `author_id` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_group_member_group_id_idx` (`group_id`),
  KEY `fk_pastoral_group_member_user_id_idx` (`user_id`),
  KEY `fk_pastoral_group_member_author_id_idx` (`author_id`),
  CONSTRAINT `fk_pastoral_group_member_author_id` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_group_member_group_id` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_group_member_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_import_log`
--

DROP TABLE IF EXISTS `pastoral_import_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) DEFAULT NULL,
  `import_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_name` varchar(500) DEFAULT NULL,
  `creator_name` varchar(500) DEFAULT NULL,
  `heading` varchar(500) DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_record_action`
--

DROP TABLE IF EXISTS `pastoral_record_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_record_action` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) DEFAULT NULL,
  `action_id` int(11) NOT NULL,
  `external_id` int(11) DEFAULT NULL,
  `completed` tinyint(1) DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_id_idx` (`pastoral_id`),
  KEY `fk_action_id_idx` (`action_id`),
  KEY `fk_pastoral_record_action_updated_by` (`updated_by`),
  CONSTRAINT `fk_pastoral_record_action_action_id` FOREIGN KEY (`action_id`) REFERENCES `lu_pastoral_action` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_record_action_pastoral_id` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_record_action_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_report`
--

DROP TABLE IF EXISTS `pastoral_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(4) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uses_relative_date` tinyint(1) NOT NULL DEFAULT 0,
  `relative_date_string` varchar(255) DEFAULT NULL,
  `before_date` datetime DEFAULT NULL,
  `after_date` datetime DEFAULT NULL,
  `include_archived_records` tinyint(4) DEFAULT 0,
  `severity_id` int(11) DEFAULT NULL,
  `involved` tinyint(4) DEFAULT NULL,
  `types` text DEFAULT NULL,
  `sub_types` text DEFAULT NULL,
  `authors` text DEFAULT NULL,
  `students` text DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `order` text DEFAULT NULL,
  `years` text DEFAULT NULL,
  `summary_tables` text DEFAULT NULL,
  `include_students_without_year` tinyint(1) NOT NULL DEFAULT 0,
  `houses` text DEFAULT NULL,
  `campuses` text DEFAULT NULL,
  `folders` text DEFAULT NULL,
  `actions` text DEFAULT NULL,
  `action_status` enum('pending','completed') CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `include_records_without_action` tinyint(1) NOT NULL DEFAULT 0,
  `include_students_without_house` tinyint(1) NOT NULL DEFAULT 0,
  `notification_enabled` tinyint(4) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pastoral_report_user_id_fk` (`owner_id`),
  CONSTRAINT `pastoral_report_user_id_fk` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_report_access`
--

DROP TABLE IF EXISTS `pastoral_report_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_report_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_report_id` int(11) NOT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `access` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_report_access_pastoral_report_id` (`pastoral_report_id`),
  KEY `fk_pastoral_report_access_user_id_idx` (`user_id`),
  KEY `fk_pastoral_report_access_group_id_idx` (`group_id`),
  CONSTRAINT `fk_pastoral_report_access` FOREIGN KEY (`pastoral_report_id`) REFERENCES `pastoral_report` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_report_access_group_id` FOREIGN KEY (`group_id`) REFERENCES `pastoral_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_report_access_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_report_dashboard`
--

DROP TABLE IF EXISTS `pastoral_report_dashboard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_report_dashboard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(4) unsigned NOT NULL,
  `report_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pastoral_report_dashboard_pastoral_report_id_fk` (`report_id`),
  KEY `pastoral_report_dashboard_user_id_fk` (`user_id`),
  CONSTRAINT `pastoral_report_dashboard_pastoral_report_id_fk` FOREIGN KEY (`report_id`) REFERENCES `pastoral_report` (`id`),
  CONSTRAINT `pastoral_report_dashboard_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_revision`
--

DROP TABLE IF EXISTS `pastoral_revision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_revision` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `root_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `owner_id` int(4) unsigned NOT NULL,
  `severity_id` int(11) DEFAULT NULL,
  `student_id` int(11) unsigned NOT NULL,
  `type_id` int(11) NOT NULL,
  `subtype_id` int(11) DEFAULT NULL,
  `external_id` int(11) DEFAULT NULL,
  `heading` varchar(255) NOT NULL,
  `body_private` mediumtext NOT NULL,
  `body_public` mediumtext DEFAULT NULL,
  `points` float DEFAULT 0,
  `modified_by_user_id` int(11) unsigned NOT NULL,
  `occurred_on` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_revision_severity` (`severity_id`),
  KEY `fk_revision_student` (`student_id`),
  KEY `fk_revision_type` (`type_id`),
  KEY `fk_pastoral_revision_pastoral_id` (`root_id`),
  KEY `fk_pastoral_revision_owner_id` (`owner_id`),
  KEY `fk_pastoral_revision_category_id` (`subtype_id`),
  KEY `fk_pastoral_revision_modified_by_user_id` (`modified_by_user_id`),
  KEY `pastoral_revision_deleted_at_index` (`deleted_at`),
  KEY `fk_pastoral_revision_parent_id` (`parent_id`),
  CONSTRAINT `fk_pastoral_revision_category_id` FOREIGN KEY (`subtype_id`) REFERENCES `lu_pastoral_subtype` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_revision_modified_by_user_id` FOREIGN KEY (`modified_by_user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_revision_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_revision_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `pastoral` (`id`),
  CONSTRAINT `fk_pastoral_revision_pastoral_id` FOREIGN KEY (`root_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_revision_severity` FOREIGN KEY (`severity_id`) REFERENCES `lu_pastoral_severity` (`id`),
  CONSTRAINT `fk_pastoral_revision_student_id` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_revision_type` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_tags`
--

DROP TABLE IF EXISTS `pastoral_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_tags` (
  `pastoral_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  UNIQUE KEY `unique_pastoral_tag_idx` (`pastoral_id`,`tag_id`),
  KEY `fk_pastoral_tags_tag` (`tag_id`),
  CONSTRAINT `fk_pastoral_tags_pastoral` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `lu_pastoral_tag` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_type_action`
--

DROP TABLE IF EXISTS `pastoral_type_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_type_action` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) DEFAULT NULL,
  `subtype_id` int(11) DEFAULT NULL,
  `action_id` int(11) NOT NULL,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_type_id_idx` (`type_id`),
  KEY `fk_subtype_id_idx` (`subtype_id`),
  KEY `fk_action_id_idx` (`action_id`),
  CONSTRAINT `fk_pastoral_type_action_action_id` FOREIGN KEY (`action_id`) REFERENCES `lu_pastoral_action` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_type_action_subtype_id` FOREIGN KEY (`subtype_id`) REFERENCES `lu_pastoral_subtype` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_type_action_type_id` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pastoral_upload_queue`
--

DROP TABLE IF EXISTS `pastoral_upload_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pastoral_upload_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `student_external_id` int(11) DEFAULT NULL,
  `external_id` int(11) DEFAULT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `pastoral_attachment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral_upload_queue_pastoral_id` (`pastoral_id`),
  KEY `fk_pastoral_upload_queue_hash` (`hash`),
  KEY `fk_pastoral_attachment_id` (`pastoral_attachment_id`),
  CONSTRAINT `fk_pastoral_attachment_id` FOREIGN KEY (`pastoral_attachment_id`) REFERENCES `pastoral_attachment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_upload_queue_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_upload_queue_pastoral_id` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `perms`
--

DROP TABLE IF EXISTS `perms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perms` (
  `cid` int(5) unsigned NOT NULL DEFAULT 0,
  `gid` int(11) unsigned NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cid`,`gid`),
  KEY `type` (`type`),
  KEY `fk_perms_role_id_idx` (`gid`),
  CONSTRAINT `fk_perms_folder_id` FOREIGN KEY (`cid`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_perms_role_id` FOREIGN KEY (`gid`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plagscan_document`
--

DROP TABLE IF EXISTS `plagscan_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plagscan_document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_submission_file_id` int(11) unsigned NOT NULL,
  `plagscan_id` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `score` float unsigned DEFAULT NULL,
  `storage_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `interactive_url` varchar(255) DEFAULT NULL,
  `privatised_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`),
  KEY `file_id` (`student_submission_file_id`),
  KEY `plagscan_id` (`plagscan_id`),
  KEY `fk_storage_hash_storage_hash` (`storage_hash`),
  KEY `plagscan_document_status` (`status`),
  CONSTRAINT `fk_plagscan_document_student_submission_file_id` FOREIGN KEY (`student_submission_file_id`) REFERENCES `student_submission_file` (`id`),
  CONSTRAINT `fk_storage_hash_storage_hash` FOREIGN KEY (`storage_hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poll`
--

DROP TABLE IF EXISTS `poll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` int(11) unsigned NOT NULL,
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
  `result_0` int(11) NOT NULL DEFAULT 0,
  `result_1` int(11) NOT NULL DEFAULT 0,
  `result_2` int(11) NOT NULL DEFAULT 0,
  `result_3` int(11) NOT NULL DEFAULT 0,
  `result_4` int(11) NOT NULL DEFAULT 0,
  `result_5` int(11) NOT NULL DEFAULT 0,
  `result_6` int(11) NOT NULL DEFAULT 0,
  `result_7` int(11) NOT NULL DEFAULT 0,
  `result_8` int(11) NOT NULL DEFAULT 0,
  `result_9` int(11) NOT NULL DEFAULT 0,
  `frequency` enum('once','hourly','daily') DEFAULT NULL,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `close` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categoryID` (`folder_id`),
  KEY `created_by` (`created_by`),
  KEY `close` (`close`),
  CONSTRAINT `fk_poll_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_poll_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pollLog`
--

DROP TABLE IF EXISTS `pollLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pollLog` (
  `poll_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  KEY `pollID` (`poll_id`,`user_id`),
  KEY `fk_pollLog_user_id_idx` (`user_id`),
  CONSTRAINT `fk_pollLog_poll_id` FOREIGN KEY (`poll_id`) REFERENCES `poll` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pollLog_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_button`
--

DROP TABLE IF EXISTS `profile_button`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_button` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `roletype` enum('student','staff','parent') DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'custom',
  `sort_order` int(11) DEFAULT NULL,
  `self_enabled` int(11) NOT NULL DEFAULT 0,
  `title` varchar(200) DEFAULT NULL,
  `text` varchar(200) DEFAULT NULL,
  `icon` varchar(200) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `css` varchar(200) DEFAULT NULL,
  `target` varchar(200) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_button_roletype_index` (`roletype`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_button_campus_availability`
--

DROP TABLE IF EXISTS `profile_button_campus_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_button_campus_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_button_id` int(11) NOT NULL,
  `campus_id` tinyint(2) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_button_campus_availability_profile_button_id_fk` (`profile_button_id`),
  KEY `fk_profile_button_campus_availability_campus_id_idx` (`campus_id`),
  CONSTRAINT `fk_profile_button_campus_availability_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`),
  CONSTRAINT `profile_button_campus_availability_profile_button_id_fk` FOREIGN KEY (`profile_button_id`) REFERENCES `profile_button` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_button_campus_visibility`
--

DROP TABLE IF EXISTS `profile_button_campus_visibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_button_campus_visibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_button_id` int(11) NOT NULL,
  `campus_id` tinyint(2) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_button_campus_visibility_profile_button_id_fk` (`profile_button_id`),
  KEY `fk_profile_button_campus_visibility_campus_id_idx` (`campus_id`),
  CONSTRAINT `fk_profile_button_campus_visibility_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`),
  CONSTRAINT `profile_button_campus_visibility_profile_button_id_fk` FOREIGN KEY (`profile_button_id`) REFERENCES `profile_button` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_button_role_availability`
--

DROP TABLE IF EXISTS `profile_button_role_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_button_role_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_button_id` int(11) NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_button_role_availability_profile_button_id_fk` (`profile_button_id`),
  KEY `fk_profile_button_role_availability_role_id_idx` (`role_id`),
  CONSTRAINT `fk_profile_button_role_availability_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `profile_button_role_availability_profile_button_id_fk` FOREIGN KEY (`profile_button_id`) REFERENCES `profile_button` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_button_role_visibility`
--

DROP TABLE IF EXISTS `profile_button_role_visibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_button_role_visibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_button_id` int(11) NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_button_role_visibility_profile_button_id_fk` (`profile_button_id`),
  KEY `fk_profile_button_role_visibility_role_id_idx` (`role_id`),
  CONSTRAINT `fk_profile_button_role_visibility_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `profile_button_role_visibility_profile_button_id_fk` FOREIGN KEY (`profile_button_id`) REFERENCES `profile_button` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_field`
--

DROP TABLE IF EXISTS `profile_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `roletype` enum('staff','student','parent') NOT NULL,
  `field` varchar(255) NOT NULL,
  `display_as` varchar(255) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `enabled` tinyint(4) NOT NULL,
  `term` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `css` varchar(255) DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `options_default` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_default`)),
  `is_external` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roletype_field` (`roletype`,`field`),
  KEY `roletype` (`roletype`),
  KEY `field` (`field`),
  KEY `sequence` (`sequence`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_field_campus_availability`
--

DROP TABLE IF EXISTS `profile_field_campus_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_field_campus_availability` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_field_id` int(10) unsigned NOT NULL,
  `campus_id` tinyint(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_field_profile_field_campus_availability` (`profile_field_id`),
  KEY `fk_profile_field_campus_availability_campus_id_idx` (`campus_id`),
  CONSTRAINT `fk_profile_field_campus_availability_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_field_roletype_visibility`
--

DROP TABLE IF EXISTS `profile_field_roletype_visibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_field_roletype_visibility` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_field_id` int(10) unsigned NOT NULL,
  `roletype` enum('staff','student','parent','self') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_field_profile_field_roletype_visibility` (`profile_field_id`),
  KEY `fk_profile_field_roletype_visibility_idx` (`roletype`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_tab`
--

DROP TABLE IF EXISTS `profile_tab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_tab` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `roletype` enum('staff','student','parent') NOT NULL,
  `tab` varchar(255) NOT NULL,
  `display_as` varchar(255) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roletype_tab` (`roletype`,`tab`),
  KEY `roletype` (`roletype`),
  KEY `tab` (`tab`),
  KEY `sequence` (`sequence`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_tab_campus_availability`
--

DROP TABLE IF EXISTS `profile_tab_campus_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_tab_campus_availability` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_tab_id` int(10) unsigned NOT NULL,
  `campus_id` tinyint(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_tab_profile_tab_campus_availability` (`profile_tab_id`),
  KEY `fk_profile_tab_campus_availability_campus_id_idx` (`campus_id`),
  CONSTRAINT `fk_profile_tab_campus_availability_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `luCampus` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_tab_roletype_visibility`
--

DROP TABLE IF EXISTS `profile_tab_roletype_visibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_tab_roletype_visibility` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_tab_id` int(10) unsigned NOT NULL,
  `roletype` enum('staff','student','parent','self') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_tab_profile_tab_roletype_visibility` (`profile_tab_id`),
  KEY `fk_profile_tab_roletype_visibility_idx` (`roletype`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project`
--

DROP TABLE IF EXISTS `project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) unsigned NOT NULL,
  `assessment_id` int(10) unsigned NOT NULL,
  `mark_type_id` int(11) NOT NULL,
  `grade_group_id` int(11) DEFAULT NULL,
  `summator` enum('min','max','avg','weighted') DEFAULT NULL,
  `require_all_activities` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `mark_visibility` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_project_assessment_id_idx` (`assessment_id`),
  KEY `fk_project_owner_id_idx` (`owner_id`),
  KEY `fk_project_grade_group_id_idx` (`grade_group_id`),
  KEY `fk_project_mark_type_id_idx` (`mark_type_id`),
  CONSTRAINT `fk_project_course_unit_project_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_project_grade_group_id` FOREIGN KEY (`grade_group_id`) REFERENCES `submission_grade_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_project_mark_type_id` FOREIGN KEY (`mark_type_id`) REFERENCES `submission_mark_type` (`mark_type_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_project_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_assessment`
--

DROP TABLE IF EXISTS `project_assessment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_assessment` (
  `project_id` int(10) unsigned NOT NULL,
  `assessment_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`assessment_id`,`project_id`),
  KEY `fk_project_assessment_project_id_idx` (`project_id`),
  KEY `fk_project_assessment_assessment_id_idx` (`assessment_id`),
  CONSTRAINT `fk_project_assessment_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_project_assessment_project_id_idx` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_result`
--

DROP TABLE IF EXISTS `project_result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_result` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `user_id` int(4) unsigned NOT NULL,
  `mark` varchar(64) NOT NULL,
  `norm_mark` decimal(14,11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_project_result_assessment_id_user_id` (`assessment_id`,`user_id`),
  CONSTRAINT `fk_project_result_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`),
  CONSTRAINT `fk_project_result_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pronoun`
--

DROP TABLE IF EXISTS `pronoun`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pronoun` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subjective` varchar(250) DEFAULT NULL,
  `objective` varchar(250) DEFAULT NULL,
  `possessive` varchar(250) DEFAULT NULL,
  `possessive_adjective` varchar(250) DEFAULT 'their',
  `reflexive` varchar(250) DEFAULT 'themselves',
  `sequence` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quiz_feedback`
--

DROP TABLE IF EXISTS `quiz_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `comment` mediumtext DEFAULT NULL,
  `learnosity_reference` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reportcard_comment`
--

DROP TABLE IF EXISTS `reportcard_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reportcard_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `commenter_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `body` text DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `student_visibility` tinyint(1) NOT NULL DEFAULT 1,
  `parent_visibility` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`,`student_id`),
  KEY `created` (`created`),
  KEY `deleted` (`deleted_at`),
  KEY `student_id` (`student_id`),
  KEY `student_visibility` (`student_visibility`),
  KEY `parent_visibility` (`parent_visibility`),
  KEY `fk_reportcard_comment_commenter_id_idx` (`commenter_id`),
  CONSTRAINT `fk_reportcard_comment_commenter_id` FOREIGN KEY (`commenter_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_reportcard_comment_folder_id` FOREIGN KEY (`category_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_reportcard_comment_student_id` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reporting`
--

DROP TABLE IF EXISTS `reporting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `instructor_mark_type` int(11) DEFAULT NULL,
  `mark_type` int(11) NOT NULL,
  `instructor_grade_group_id` int(11) DEFAULT NULL,
  `grade_group_id` int(11) DEFAULT NULL,
  `instructor_max_mark` decimal(14,11) DEFAULT NULL,
  `max_mark` decimal(14,11) DEFAULT NULL,
  `mark_visibility` tinyint(4) DEFAULT NULL,
  `feedback_comments_enable` tinyint(4) DEFAULT NULL COMMENT 'a toggle: if enabled, staff can write comments in their reporting feedbacks',
  `supporting_data_enable` tinyint(4) DEFAULT NULL COMMENT 'Supporting Data section is shown',
  `supporting_data_range` enum('current_academic_period','previous_academic_period','current_and_previous_academic_period','current_academic_year','current_and_previous_academic_year') DEFAULT NULL COMMENT 'These translate into from/to date range for supporting data',
  `supporting_data_folders` enum('current_only','any_folders') DEFAULT NULL COMMENT 'corresponds to "the students in my folder specifically", or "any folder where my students had a published feedback"',
  `supporting_data_markbook_enable` tinyint(4) DEFAULT NULL COMMENT 'a toggle: if enabled, the minimal mega markbook is generated',
  `created_at` datetime NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_r_assessment_idx` (`assessment_id`),
  KEY `fk_r_createdby_idx` (`created_by`),
  KEY `fk_r_updatedby_idx` (`updated_by`),
  KEY `fk_r_deletedby_idx` (`deleted_by`),
  CONSTRAINT `fk_r_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_r_createdby` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_r_deletedby` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_r_updatedby` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reporting_curriculum_node`
--

DROP TABLE IF EXISTS `reporting_curriculum_node`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporting_curriculum_node` (
  `reporting_id` int(11) NOT NULL,
  `curriculum_node_id` int(11) NOT NULL,
  UNIQUE KEY `fk_rc_unique` (`reporting_id`,`curriculum_node_id`),
  KEY `fk_rcn_reporting_id_idx` (`reporting_id`),
  KEY `fk_rcn_cni_curriculum_node_idx` (`curriculum_node_id`),
  CONSTRAINT `fk_rcn_cni_curriculum_node` FOREIGN KEY (`curriculum_node_id`) REFERENCES `curriculum_node` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rcn_reporting_id` FOREIGN KEY (`reporting_id`) REFERENCES `reporting` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reporting_feedback`
--

DROP TABLE IF EXISTS `reporting_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporting_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reporting_id` int(11) NOT NULL,
  `learner_id` int(10) unsigned NOT NULL,
  `instructor_id` int(10) unsigned NOT NULL,
  `norm_mark` decimal(14,11) DEFAULT NULL,
  `mark` varchar(255) DEFAULT NULL,
  `instructor_mark` varchar(255) DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rf_reporting_idx` (`reporting_id`),
  KEY `fk_rf_learner_idx` (`learner_id`),
  KEY `fk_rf_instructor_idx` (`instructor_id`),
  KEY `fk_rf_publisher_idx` (`published_by`),
  CONSTRAINT `fk_rf_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rf_learner` FOREIGN KEY (`learner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rf_publisher` FOREIGN KEY (`published_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rf_reporting` FOREIGN KEY (`reporting_id`) REFERENCES `reporting` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reporting_work_type`
--

DROP TABLE IF EXISTS `reporting_work_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporting_work_type` (
  `reporting_id` int(11) NOT NULL,
  `work_type_id` int(11) NOT NULL,
  UNIQUE KEY `fk_aw_unique` (`reporting_id`,`work_type_id`),
  KEY `fk_rwt_reporting_id_idx` (`reporting_id`),
  KEY `fk_rwt_wti_work_type_idx` (`work_type_id`),
  CONSTRAINT `fk_rwt_reporting_id` FOREIGN KEY (`reporting_id`) REFERENCES `reporting` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rwt_wti_work_type` FOREIGN KEY (`work_type_id`) REFERENCES `lu_work_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource`
--

DROP TABLE IF EXISTS `resource`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `resource_category_id` int(11) NOT NULL,
  `resource_moderation_type_id` tinyint(4) NOT NULL DEFAULT 1,
  `min_lead_time` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `min_reservation_time` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `max_reservation_time` mediumint(8) unsigned NOT NULL DEFAULT 86400,
  `current_term_restriction` tinyint(1) NOT NULL DEFAULT 0,
  `allow_clash_bookings` tinyint(1) NOT NULL DEFAULT 0,
  `capacity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `storage_image_id` int(11) DEFAULT NULL,
  `image_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `room_code` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_resource_category_id` (`resource_category_id`),
  KEY `resource_resource_moderation_type_id` (`resource_moderation_type_id`),
  KEY `resource_storage_image_id` (`storage_image_id`),
  KEY `resource_room_code` (`room_code`),
  KEY `deleted` (`deleted_at`),
  KEY `image_hash` (`image_hash`),
  CONSTRAINT `fk_resource_image_hash` FOREIGN KEY (`image_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `resource_resource_category_id` FOREIGN KEY (`resource_category_id`) REFERENCES `resource_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resource_resource_moderation_type_id` FOREIGN KEY (`resource_moderation_type_id`) REFERENCES `lu_resource_moderation_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource_booking`
--

DROP TABLE IF EXISTS `resource_booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_booking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource_category`
--

DROP TABLE IF EXISTS `resource_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource_moderator`
--

DROP TABLE IF EXISTS `resource_moderator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_moderator` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `moderator_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_id` (`resource_id`,`moderator_id`),
  KEY `resource_moderator_resource_id` (`resource_id`),
  KEY `resource_moderator_moderator_id` (`moderator_id`),
  CONSTRAINT `resource_moderator_resource_id` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource_relation`
--

DROP TABLE IF EXISTS `resource_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_relation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `extra_resource_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_id` (`resource_id`,`extra_resource_id`),
  KEY `resource_relation_resource_id` (`resource_id`),
  KEY `resource_relation_extra_resource_id` (`extra_resource_id`),
  CONSTRAINT `resource_relation_extra_resource_id` FOREIGN KEY (`extra_resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resource_relation_resource_id` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource_role_permission`
--

DROP TABLE IF EXISTS `resource_role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_role_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `role_id` int(4) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Are users in this role allowed to book this resource?',
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_role_unique` (`resource_id`,`role_id`),
  KEY `resource_role_role` (`role_id`),
  CONSTRAINT `resource_role_resource` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=337 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_capability`
--

DROP TABLE IF EXISTS `rubric_capability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_capability` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1000) DEFAULT NULL,
  `parent_id` int(11) NOT NULL,
  `parent_type` enum('submission','category','curriculum') DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`),
  KEY `parent_id` (`parent_id`,`parent_type`)
) ENGINE=InnoDB AUTO_INCREMENT=262 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_capability_mark`
--

DROP TABLE IF EXISTS `rubric_capability_mark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_capability_mark` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `return_id` int(11) unsigned NOT NULL,
  `rubric_capability_id` int(11) unsigned NOT NULL,
  `human_readable_value` varchar(64) DEFAULT NULL,
  `value` decimal(14,11) unsigned DEFAULT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `rubric_group_id` (`rubric_capability_id`),
  KEY `return_id` (`return_id`),
  CONSTRAINT `fk_rubric_group_mark_return_id` FOREIGN KEY (`return_id`) REFERENCES `submission_return` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rubric_group_mark_rubric_group_id` FOREIGN KEY (`rubric_capability_id`) REFERENCES `rubric_capability` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_capability_usage`
--

DROP TABLE IF EXISTS `rubric_capability_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_capability_usage` (
  `rubric_capability_id` int(11) unsigned NOT NULL,
  `submission_box_id` int(11) unsigned NOT NULL,
  `max_value` decimal(14,11) unsigned DEFAULT NULL,
  `sequence` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`rubric_capability_id`,`submission_box_id`),
  KEY `rubric_group_id` (`rubric_capability_id`),
  KEY `submission_box_id` (`submission_box_id`),
  CONSTRAINT `fk_rubric_group_usage_box_id` FOREIGN KEY (`submission_box_id`) REFERENCES `submission_box` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rubric_group_usage_rubric_group_id` FOREIGN KEY (`rubric_capability_id`) REFERENCES `rubric_capability` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_descriptor`
--

DROP TABLE IF EXISTS `rubric_descriptor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_descriptor` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rubric_indicator_id` int(11) unsigned NOT NULL,
  `level_id` int(11) unsigned NOT NULL,
  `max_value` decimal(14,11) unsigned DEFAULT NULL,
  `description` varchar(1024) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `criterion_id` (`rubric_indicator_id`),
  KEY `level_id` (`level_id`),
  KEY `deleted_at` (`deleted_at`),
  CONSTRAINT `fk_rubric_descriptor_criterion_id` FOREIGN KEY (`rubric_indicator_id`) REFERENCES `rubric_indicator` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rubric_descriptor_level_id` FOREIGN KEY (`level_id`) REFERENCES `rubric_level` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4082 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_indicator`
--

DROP TABLE IF EXISTS `rubric_indicator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_indicator` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rubric_capability_id` int(11) unsigned NOT NULL,
  `name` varchar(1000) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`),
  KEY `rubric_criterion_rubric_group_idx` (`rubric_capability_id`),
  CONSTRAINT `rubric_criterion_rubric_group` FOREIGN KEY (`rubric_capability_id`) REFERENCES `rubric_capability` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=485 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_indicator_mark`
--

DROP TABLE IF EXISTS `rubric_indicator_mark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_indicator_mark` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rubric_capability_mark_id` int(11) unsigned NOT NULL,
  `descriptor_id` int(11) unsigned DEFAULT NULL,
  `value` decimal(14,11) unsigned DEFAULT NULL,
  `valid` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `valid` (`valid`),
  KEY `descriptor_id` (`descriptor_id`),
  KEY `rubric_descriptor_mark_rubric_group_mark_id` (`rubric_capability_mark_id`),
  CONSTRAINT `fk_rubric_descriptor_mark_rubric_group_mark_id` FOREIGN KEY (`rubric_capability_mark_id`) REFERENCES `rubric_capability_mark` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rubric_mark_descriptor_id` FOREIGN KEY (`descriptor_id`) REFERENCES `rubric_descriptor` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2586 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_indicator_usage`
--

DROP TABLE IF EXISTS `rubric_indicator_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_indicator_usage` (
  `rubric_indicator_id` int(11) unsigned NOT NULL,
  `submission_box_id` int(11) unsigned NOT NULL,
  `max_value` decimal(14,11) unsigned DEFAULT NULL,
  `sequence` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`rubric_indicator_id`,`submission_box_id`),
  KEY `criterion_id` (`rubric_indicator_id`),
  KEY `submission_box_id` (`submission_box_id`),
  CONSTRAINT `fk_rubric_usage_box_id` FOREIGN KEY (`submission_box_id`) REFERENCES `submission_box` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_rubric_usage_criterion_id` FOREIGN KEY (`rubric_indicator_id`) REFERENCES `rubric_indicator` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_level`
--

DROP TABLE IF EXISTS `rubric_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_level` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `box_id` int(11) unsigned NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `sequence` int(11) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box_id` (`box_id`),
  KEY `sequence` (`sequence`),
  KEY `deleted_at` (`deleted_at`),
  CONSTRAINT `fk_rubric_level_box_id` FOREIGN KEY (`box_id`) REFERENCES `submission_box` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=959 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubric_result`
--

DROP TABLE IF EXISTS `rubric_result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubric_result` (
  `resultset_id` int(11) NOT NULL,
  `value` int(11) NOT NULL,
  `label` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`resultset_id`,`value`),
  KEY `resultset_id` (`resultset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `search_index`
--

DROP TABLE IF EXISTS `search_index`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_index` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL,
  `identifier` varchar(64) NOT NULL,
  `title` varchar(1000) NOT NULL,
  `date` datetime DEFAULT NULL,
  `is_relevance_date_affected` tinyint(1) NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `folder_id` int(10) unsigned DEFAULT NULL,
  `fulltext_index` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `search_index_pk` (`type`,`identifier`),
  KEY `search_index_user_id_fk` (`created_by`),
  KEY `search_index_folder_id_fk` (`folder_id`),
  FULLTEXT KEY `search_index_fulltext_index_index` (`fulltext_index`),
  FULLTEXT KEY `search_index_title_index` (`title`),
  CONSTRAINT `search_index_folder_id_fk` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `search_index_user_id_fk` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9238 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_class`
--

DROP TABLE IF EXISTS `sis_class`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(256) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_class_period`
--

DROP TABLE IF EXISTS `sis_class_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_class_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `teacher_external_id` varchar(64) DEFAULT NULL,
  `room` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_period_class_id` (`class_id`),
  KEY `class_period_period_id` (`period_id`),
  KEY `class_period_teacher_external_id` (`teacher_external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_class_user`
--

DROP TABLE IF EXISTS `sis_class_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_class_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `external_id` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_user_involvement` (`class_id`,`external_id`),
  KEY `class_user_class_id` (`class_id`),
  KEY `class_user_external_id` (`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_class_user_overrides`
--

DROP TABLE IF EXISTS `sis_class_user_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_class_user_overrides` (
  `event_id` int(11) NOT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `action` enum('clear','clear_staff','add','subtract') DEFAULT 'add',
  `sequence` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  KEY `action` (`action`),
  KEY `external_id` (`external_id`),
  KEY `fk_sis_class_user_overrides_idx` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_event`
--

DROP TABLE IF EXISTS `sis_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `day` varchar(255) DEFAULT NULL,
  `period` varchar(255) DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `finish_at` datetime NOT NULL,
  `allday` tinyint(1) NOT NULL DEFAULT 0,
  `type` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `classification` enum('event','timetable') NOT NULL DEFAULT 'event',
  `guid` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `classification` (`classification`),
  KEY `code` (`code`),
  KEY `location` (`location`),
  KEY `source` (`source`),
  KEY `guid` (`guid`),
  KEY `type` (`type`),
  KEY `start_at_idx` (`start_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_event_campus`
--

DROP TABLE IF EXISTS `sis_event_campus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_event_campus` (
  `event_id` int(11) NOT NULL,
  `campus_name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  KEY `campus_name` (`campus_name`),
  KEY `fk_sis_event_campus_idx` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_event_roletype`
--

DROP TABLE IF EXISTS `sis_event_roletype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_event_roletype` (
  `event_id` int(11) NOT NULL,
  `roletype` enum('staff','student','parent') NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  KEY `roletype` (`roletype`),
  KEY `fk_sis_event_roletype_idx` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_event_user`
--

DROP TABLE IF EXISTS `sis_event_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_event_user` (
  `event_id` int(11) NOT NULL,
  `external_id` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  KEY `external_id` (`external_id`),
  KEY `fk_sis_event_user_idx` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_import_log`
--

DROP TABLE IF EXISTS `sis_import_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` varchar(255) NOT NULL,
  `script` varchar(255) NOT NULL,
  `start_at` datetime NOT NULL,
  `finish_at` datetime DEFAULT NULL,
  `status` enum('running','complete','error') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_import_queue`
--

DROP TABLE IF EXISTS `sis_import_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_import_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `strats` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_timetable_cycle`
--

DROP TABLE IF EXISTS `sis_timetable_cycle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_timetable_cycle` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `cycle_identifier` varchar(255) DEFAULT NULL,
  `cycle_length` int(11) unsigned DEFAULT NULL,
  `day_length` int(10) unsigned DEFAULT NULL,
  `start` date DEFAULT NULL,
  `finish` date DEFAULT NULL,
  `start_day` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_timetable_period`
--

DROP TABLE IF EXISTS `sis_timetable_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_timetable_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `title` varchar(64) DEFAULT NULL,
  `day` int(11) NOT NULL,
  `sequence` int(11) NOT NULL,
  `start` time DEFAULT NULL,
  `finish` time DEFAULT NULL,
  `usable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day` (`day`,`sequence`,`cycle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_user_profile`
--

DROP TABLE IF EXISTS `sis_user_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_user_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(64) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `preferred_firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `pronoun` varchar(255) DEFAULT NULL,
  `portrait_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_external_id` (`external_id`),
  KEY `sis_user_profile_storage_hash_fk` (`portrait_hash`),
  CONSTRAINT `sis_user_profile_storage_hash_fk` FOREIGN KEY (`portrait_hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_user_profile_parent`
--

DROP TABLE IF EXISTS `sis_user_profile_parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_user_profile_parent` (
  `sis_user_profile_id` int(10) unsigned NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(45) DEFAULT NULL,
  `phone_home` varchar(45) DEFAULT NULL,
  `phone_work` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`sis_user_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_user_profile_relation`
--

DROP TABLE IF EXISTS `sis_user_profile_relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_user_profile_relation` (
  `child_profile_id` int(10) unsigned NOT NULL,
  `user_profile_id` int(10) unsigned NOT NULL,
  `type` varchar(55) NOT NULL,
  UNIQUE KEY `sis_unique_relation` (`child_profile_id`,`user_profile_id`),
  KEY `fk_sis_profile_relation_1_idx` (`child_profile_id`),
  KEY `fk_sis_profile_relation_2_idx` (`user_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_user_profile_staff`
--

DROP TABLE IF EXISTS `sis_user_profile_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_user_profile_staff` (
  `sis_user_profile_id` int(10) unsigned NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `campus_name` varchar(255) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `position_title` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL,
  `room` varchar(255) DEFAULT NULL,
  `house_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`sis_user_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sis_user_profile_student`
--

DROP TABLE IF EXISTS `sis_user_profile_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sis_user_profile_student` (
  `sis_user_profile_id` int(10) unsigned NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `mobile` varchar(45) DEFAULT NULL,
  `emergency_contact_name_1` varchar(255) DEFAULT NULL,
  `emergency_contact_phone_1` varchar(255) DEFAULT NULL,
  `emergency_contact_phone_alt_1` varchar(255) DEFAULT NULL,
  `emergency_contact_name_2` varchar(255) DEFAULT NULL,
  `emergency_contact_phone_2` varchar(255) DEFAULT NULL,
  `emergency_contact_phone_alt_2` varchar(255) DEFAULT NULL,
  `emergency_contact_name_3` varchar(255) DEFAULT NULL,
  `emergency_contact_phone_3` varchar(255) DEFAULT NULL,
  `emergency_contact_phone_alt_3` varchar(255) DEFAULT NULL,
  `campus_name` varchar(255) DEFAULT NULL,
  `house_name` varchar(255) DEFAULT NULL,
  `head_of_house_id` varchar(64) DEFAULT NULL,
  `head_of_house_name` varchar(255) DEFAULT NULL,
  `form_name` varchar(255) DEFAULT NULL,
  `form_teacher_id` varchar(64) DEFAULT NULL,
  `form_teacher_name` varchar(255) DEFAULT NULL,
  `tutor_group` varchar(255) DEFAULT NULL,
  `tutor_teacher_id` varchar(64) DEFAULT NULL,
  `tutor_teacher_name` varchar(255) DEFAULT NULL,
  `year_level` varchar(255) DEFAULT NULL,
  `year_level_coordinator_id` varchar(64) DEFAULT NULL,
  `year_level_coordinator_name` varchar(255) DEFAULT NULL,
  `studies_code` varchar(45) DEFAULT NULL,
  `alert_medical` text DEFAULT NULL,
  `alert_comment` varchar(1024) DEFAULT NULL,
  `alert_message` varchar(1024) DEFAULT NULL,
  `alert_legal` varchar(1024) DEFAULT NULL,
  `enrolment_date` date DEFAULT NULL,
  `leaving_date` date DEFAULT NULL,
  `ib` tinyint(1) DEFAULT NULL,
  `individual_learning_plans` tinyint(1) DEFAULT NULL,
  `esl` tinyint(1) DEFAULT NULL,
  `special_needs` tinyint(1) DEFAULT NULL,
  `boarding_house_name` varchar(255) DEFAULT NULL,
  `boarding_status` tinyint(1) DEFAULT NULL,
  `photo_redacted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`sis_user_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_access`
--

DROP TABLE IF EXISTS `site_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_access` (
  `site_school_code` varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `access_token` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  PRIMARY KEY (`site_school_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skin_config_category`
--

DROP TABLE IF EXISTS `skin_config_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skin_config_category` (
  `configID` int(10) unsigned DEFAULT NULL,
  `subcategory_id` int(11) NOT NULL,
  KEY `idx_skin_config_category_configID` (`configID`),
  KEY `idx_skin_config_category_subcategoryId` (`subcategory_id`),
  CONSTRAINT `fk_skin_config_category_config` FOREIGN KEY (`configID`) REFERENCES `config` (`configID`),
  CONSTRAINT `fk_skin_config_category_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `lu_skin_config_subcategory` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `socialstream_post`
--

DROP TABLE IF EXISTS `socialstream_post`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `socialstream_post` (
  `post_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `root_id` int(11) unsigned DEFAULT NULL,
  `owner_id` int(11) unsigned NOT NULL,
  `homepage_id` int(11) unsigned DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `mimetype` varchar(255) DEFAULT NULL,
  `cache_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `thumbnail` varchar(2048) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `external_title` varchar(255) DEFAULT NULL,
  `external_body` text DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  KEY `deleted` (`deleted_at`),
  KEY `owner` (`owner_id`),
  KEY `homepage` (`homepage_id`),
  KEY `cache_hash` (`cache_hash`),
  KEY `root_id` (`root_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_socialstream_post_folder_id` FOREIGN KEY (`homepage_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_socialstream_post_image_hash` FOREIGN KEY (`cache_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_socialstream_post_user_id` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_coach`
--

DROP TABLE IF EXISTS `sportsfixtures_coach`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_coach` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sportsfixtures_coach__name` (`name`(191)),
  KEY `sportsfixtures_coach__user` (`user_id`),
  CONSTRAINT `fk_sportsfixtures_coach_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_division`
--

DROP TABLE IF EXISTS `sportsfixtures_division`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_division` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sport_id` int(11) DEFAULT NULL,
  `season_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_id` (`sport_id`),
  KEY `season_id` (`season_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `external_id_idx` (`external_id`),
  CONSTRAINT `sportsfixtures_division_ibfk_1` FOREIGN KEY (`sport_id`) REFERENCES `sportsfixtures_sport` (`id`),
  CONSTRAINT `sportsfixtures_division_ibfk_2` FOREIGN KEY (`season_id`) REFERENCES `sportsfixtures_season` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_division_user`
--

DROP TABLE IF EXISTS `sportsfixtures_division_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_division_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `division_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sportsfixtures_division_user__division_user` (`division_id`,`user_id`),
  KEY `fk_sportsfixtures_division_user__division_idx` (`division_id`),
  KEY `sportsfixtures_division_user__user` (`user_id`),
  CONSTRAINT `fk_sportsfixtures_division_user__division` FOREIGN KEY (`division_id`) REFERENCES `sportsfixtures_division` (`id`),
  CONSTRAINT `fk_sportsfixtures_division_user_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_import`
--

DROP TABLE IF EXISTS `sportsfixtures_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_import` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(50) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_import_errors`
--

DROP TABLE IF EXISTS `sportsfixtures_import_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_import_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `type` enum('success','error') DEFAULT NULL,
  `line` int(11) DEFAULT NULL,
  `column` int(11) DEFAULT NULL,
  `result` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sportsfixtures_import_errors_storage_hash_fk` (`hash`),
  CONSTRAINT `sportsfixtures_import_errors_storage_hash_fk` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_match`
--

DROP TABLE IF EXISTS `sportsfixtures_match`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_match` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `division_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `home_team_id` int(11) DEFAULT NULL,
  `away_team_id` int(11) DEFAULT NULL,
  `home_school_id` int(11) DEFAULT NULL,
  `away_school_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `finish_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `division_id` (`division_id`),
  KEY `season_id` (`season_id`),
  KEY `venue_id` (`venue_id`),
  KEY `home_team_id` (`home_team_id`),
  KEY `away_team_id` (`away_team_id`),
  KEY `fk_sportsfixtures_match_away_school_idx` (`away_school_id`),
  KEY `fk_sportsfixtures_match_home_school_idx` (`home_school_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `external_id_idx` (`external_id`),
  CONSTRAINT `fk_sportsfixtures_match_away_school` FOREIGN KEY (`away_school_id`) REFERENCES `sportsfixtures_school` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_match_home_school` FOREIGN KEY (`home_school_id`) REFERENCES `sportsfixtures_school` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `sportsfixtures_match_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `sportsfixtures_division` (`id`),
  CONSTRAINT `sportsfixtures_match_ibfk_2` FOREIGN KEY (`season_id`) REFERENCES `sportsfixtures_season` (`id`),
  CONSTRAINT `sportsfixtures_match_ibfk_3` FOREIGN KEY (`venue_id`) REFERENCES `sportsfixtures_venue` (`id`),
  CONSTRAINT `sportsfixtures_match_ibfk_4` FOREIGN KEY (`home_team_id`) REFERENCES `sportsfixtures_team` (`id`),
  CONSTRAINT `sportsfixtures_match_ibfk_5` FOREIGN KEY (`away_team_id`) REFERENCES `sportsfixtures_team` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_match_itineraryitem`
--

DROP TABLE IF EXISTS `sportsfixtures_match_itineraryitem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_match_itineraryitem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_id` (`match_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  CONSTRAINT `sportsfixtures_match_itineraryitem_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `sportsfixtures_match` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_position`
--

DROP TABLE IF EXISTS `sportsfixtures_position`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_position` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sport_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_id` (`sport_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `external_id_idx` (`external_id`),
  CONSTRAINT `sportsfixtures_position_ibfk_1` FOREIGN KEY (`sport_id`) REFERENCES `sportsfixtures_sport` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_result`
--

DROP TABLE IF EXISTS `sportsfixtures_result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_result` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `status_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_id` (`match_id`),
  KEY `status_id` (`status_id`),
  KEY `fk_sportsfixtures_result_team_idx` (`team_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  CONSTRAINT `fk_sportsfixtures_result_team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `sportsfixtures_result_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `sportsfixtures_match` (`id`),
  CONSTRAINT `sportsfixtures_result_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `sportsfixtures_status` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_school`
--

DROP TABLE IF EXISTS `sportsfixtures_school`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at_idx` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_season`
--

DROP TABLE IF EXISTS `sportsfixtures_season`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_season` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `start` date NOT NULL,
  `end` date NOT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `archived_at_idx` (`archived_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_sport`
--

DROP TABLE IF EXISTS `sportsfixtures_sport`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_sport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `external_id_idx` (`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_status`
--

DROP TABLE IF EXISTS `sportsfixtures_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team`
--

DROP TABLE IF EXISTS `sportsfixtures_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `division_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `division_id` (`division_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `external_id_idx` (`external_id`),
  CONSTRAINT `sportsfixtures_team_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `sportsfixtures_division` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team_coach`
--

DROP TABLE IF EXISTS `sportsfixtures_team_coach`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team_coach` (
  `team_id` int(11) NOT NULL,
  `coach_id` int(11) NOT NULL,
  KEY `fk_sportsfixtures_team_coach_1_idx` (`team_id`),
  KEY `fk_sportsfixtures_team_coach_2_idx` (`coach_id`),
  CONSTRAINT `fk_sportsfixtures_team_coach__coach` FOREIGN KEY (`coach_id`) REFERENCES `sportsfixtures_coach` (`id`),
  CONSTRAINT `fk_sportsfixtures_team_coach__team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team_participantcoach`
--

DROP TABLE IF EXISTS `sportsfixtures_team_participantcoach`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team_participantcoach` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sportsfixtures_team_participantcoach_match_idx` (`match_id`),
  KEY `fk_sportsfixtures_team_participantcoach_team_idx` (`team_id`),
  KEY `fk_sportsfixtures_team_participantcoach_user_idx` (`user_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  CONSTRAINT `fk_sportsfixtures_team_participantcoach_match` FOREIGN KEY (`match_id`) REFERENCES `sportsfixtures_match` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_team_participantcoach_team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_team_participantcoach_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team_participantposition`
--

DROP TABLE IF EXISTS `sportsfixtures_team_participantposition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team_participantposition` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sportsfixtures_team_participantposition_match_idx` (`match_id`),
  KEY `fk_sportsfixtures_team_participantposition_user_idx` (`user_id`),
  KEY `fk_sportsfixtures_team_participantposition_team_idx` (`team_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  CONSTRAINT `fk_sportsfixtures_team_participantposition_match` FOREIGN KEY (`match_id`) REFERENCES `sportsfixtures_match` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_team_participantposition_team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_team_participantposition_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team_participantuser`
--

DROP TABLE IF EXISTS `sportsfixtures_team_participantuser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team_participantuser` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `sequence` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_player_idx` (`match_id`,`team_id`,`user_id`),
  KEY `fk_sportsfixtures_team_participantuser_match_idx` (`match_id`),
  KEY `fk_sportsfixtures_team_participantuser_team_idx` (`team_id`),
  KEY `fk_sportsfixtures_team_participantuser_user_idx` (`user_id`),
  KEY `deleted_at_idx` (`deleted_at`),
  CONSTRAINT `fk_sportsfixtures_team_participantuser_match` FOREIGN KEY (`match_id`) REFERENCES `sportsfixtures_match` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_team_participantuser_team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportsfixtures_team_participantuser_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team_position`
--

DROP TABLE IF EXISTS `sportsfixtures_team_position`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team_position` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `position_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sportsfixtures_team_position__team_user_position` (`team_id`,`user_id`,`position_id`),
  KEY `fk_sportsfixtures_team_position__team_idx` (`team_id`),
  KEY `fk_sportsfixtures_team_position__position_idx` (`position_id`),
  KEY `sportsfixtures_team_position__user` (`user_id`),
  CONSTRAINT `fk_sportsfixtures_team_position__team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`),
  CONSTRAINT `fk_sportsfixtures_team_position_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `sportsfixtures_team_position_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `sportsfixtures_position` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_team_user`
--

DROP TABLE IF EXISTS `sportsfixtures_team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_team_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sportsfixtures_team_user__team_user` (`team_id`,`user_id`),
  KEY `fk_sportsfixtures_team_user__team_idx` (`team_id`),
  KEY `sportsfixtures_team_user__user` (`user_id`),
  CONSTRAINT `fk_sportsfixtures_team_user__team` FOREIGN KEY (`team_id`) REFERENCES `sportsfixtures_team` (`id`),
  CONSTRAINT `fk_sportsfixtures_team_user_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sportsfixtures_venue`
--

DROP TABLE IF EXISTS `sportsfixtures_venue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sportsfixtures_venue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `latitude` decimal(9,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_at_idx` (`deleted_at`),
  KEY `external_id_idx` (`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `storage`
--

DROP TABLE IF EXISTS `storage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storage` (
  `storage_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(250) DEFAULT NULL,
  `ext` varchar(16) DEFAULT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `filesize` bigint(20) NOT NULL DEFAULT 0,
  `uploaded_on` datetime NOT NULL,
  `_last_accessed` datetime DEFAULT NULL,
  `last_gc_at` datetime DEFAULT NULL,
  `public` tinyint(1) NOT NULL DEFAULT 0,
  `expired_at` datetime DEFAULT NULL,
  PRIMARY KEY (`storage_id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `deleted` (`expired_at`),
  KEY `ext` (`ext`),
  KEY `uploaded_on` (`uploaded_on`)
) ENGINE=InnoDB AUTO_INCREMENT=3417 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `storage_transform`
--

DROP TABLE IF EXISTS `storage_transform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storage_transform` (
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `transform` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `size` int(11) DEFAULT NULL,
  `state` enum('processing','done','failed','error') DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  `format` enum('jpg','gif','png','webp') DEFAULT NULL,
  UNIQUE KEY `unique_hash_transform` (`hash`,`transform`),
  KEY `index_hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_submission`
--

DROP TABLE IF EXISTS `student_submission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_submission` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `submitted_by` int(4) unsigned NOT NULL COMMENT 'User who performed create action',
  `owner` int(4) unsigned NOT NULL DEFAULT 0,
  `box_id` int(4) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`),
  KEY `student` (`owner`),
  KEY `deleted_at` (`deleted_at`),
  KEY `submitted_by` (`submitted_by`),
  CONSTRAINT `fk_student_submission_box_id` FOREIGN KEY (`box_id`) REFERENCES `submission_box` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_student_submission_owner` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_student_submission_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_submission_file`
--

DROP TABLE IF EXISTS `student_submission_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_submission_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_submission_id` int(5) unsigned NOT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `editable_inline` tinyint(1) DEFAULT 0,
  `is_worksheet` tinyint(1) DEFAULT 0,
  `uuid` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_submission_file_student_submission` (`student_submission_id`),
  KEY `student_submission_file_hash` (`hash`),
  KEY `deleted_at` (`deleted_at`),
  KEY `uuid` (`uuid`(191)),
  CONSTRAINT `fk_student_submission_file_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`),
  CONSTRAINT `fk_student_submission_file_student_submission` FOREIGN KEY (`student_submission_id`) REFERENCES `student_submission` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_box`
--

DROP TABLE IF EXISTS `submission_box`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_box` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `owner` int(11) unsigned NOT NULL,
  `accept_submission` tinyint(1) NOT NULL DEFAULT 0,
  `multi_submission` tinyint(1) NOT NULL DEFAULT 0,
  `enable_worksheet` tinyint(1) NOT NULL DEFAULT 0,
  `worksheet_template_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `enable_offline` tinyint(1) NOT NULL DEFAULT 0,
  `enable_text` tinyint(1) NOT NULL DEFAULT 0,
  `enable_files` tinyint(1) NOT NULL DEFAULT 0,
  `enable_policy` tinyint(1) NOT NULL DEFAULT 0,
  `mark_type` int(11) NOT NULL,
  `instructor_mark_type` int(11) NOT NULL,
  `submission_grade_group_id` int(11) DEFAULT NULL,
  `submission_instructor_grade_group_id` int(11) DEFAULT NULL,
  `max_mark` decimal(14,11) DEFAULT NULL,
  `instructor_max_mark` decimal(14,11) DEFAULT NULL,
  `mark_visibility` tinyint(1) NOT NULL DEFAULT 0,
  `skip_unsubmitted` int(1) NOT NULL DEFAULT 0 COMMENT 'Skip students who haven''t submitted Due Work, in the People Selector',
  `use_rubric` int(1) unsigned NOT NULL DEFAULT 0,
  `rubric_capability_mark_type_id` int(11) DEFAULT NULL,
  `rubric_capability_grade_group_id` int(11) DEFAULT NULL,
  `rubric_mark_range` int(1) unsigned NOT NULL DEFAULT 0,
  `self_reviewability` enum('none','reflect-only','full') NOT NULL,
  `similarity_check_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `external_id` varchar(32) DEFAULT NULL COMMENT 'External-system specific identifier',
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_submission_box_assessment_id_idx` (`assessment_id`),
  KEY `owner` (`owner`),
  KEY `submission_grade_group_id` (`submission_grade_group_id`),
  KEY `fk_submission_box_mark_type` (`mark_type`),
  KEY `idx_submission_box_external_id` (`external_id`),
  KEY `fk_rubric_capability_mark_type` (`rubric_capability_mark_type_id`),
  KEY `fk_rubric_capability_grade_group` (`rubric_capability_grade_group_id`),
  KEY `submission_box_storage_hash_fk` (`worksheet_template_hash`),
  KEY `fk_submission_box_instructor_mark_type` (`instructor_mark_type`),
  KEY `fk_submission_box_submission_instructor_grade_group_id` (`submission_instructor_grade_group_id`),
  CONSTRAINT `fk_rubric_capability_grade_group` FOREIGN KEY (`rubric_capability_grade_group_id`) REFERENCES `submission_grade_group` (`id`),
  CONSTRAINT `fk_rubric_capability_mark_type` FOREIGN KEY (`rubric_capability_mark_type_id`) REFERENCES `submission_mark_type` (`mark_type_id`),
  CONSTRAINT `fk_submission_box_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_box_instructor_mark_type` FOREIGN KEY (`instructor_mark_type`) REFERENCES `submission_mark_type` (`mark_type_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_box_mark_type` FOREIGN KEY (`mark_type`) REFERENCES `submission_mark_type` (`mark_type_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_box_owner` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_box_submission_grade_group_id` FOREIGN KEY (`submission_grade_group_id`) REFERENCES `submission_grade_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_box_submission_instructor_grade_group_id` FOREIGN KEY (`submission_instructor_grade_group_id`) REFERENCES `submission_grade_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `submission_box_storage_hash_fk` FOREIGN KEY (`worksheet_template_hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=305 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_box_file`
--

DROP TABLE IF EXISTS `submission_box_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_box_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `storage_id` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `box_id` int(10) unsigned NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_id_2` (`storage_id`,`box_id`),
  KEY `storage_id` (`storage_id`),
  KEY `box_id` (`box_id`),
  CONSTRAINT `fk_submission_box_file_box_id` FOREIGN KEY (`box_id`) REFERENCES `submission_box` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_box_file_storage_hash` FOREIGN KEY (`storage_id`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_errors`
--

DROP TABLE IF EXISTS `submission_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_errors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) DEFAULT NULL,
  `display_as` varchar(64) DEFAULT NULL,
  `normalise_as` decimal(10,0) unsigned DEFAULT NULL,
  `sequence` int(11) unsigned NOT NULL,
  `updated_by` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_submission_errors_user_id` (`updated_by`),
  KEY `code` (`code`),
  CONSTRAINT `fk_submission_errors_user_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_grade`
--

DROP TABLE IF EXISTS `submission_grade`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_grade` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_grade_group_id` int(11) NOT NULL,
  `lower_bound` decimal(5,2) unsigned NOT NULL,
  `raw_grade` decimal(5,2) unsigned NOT NULL,
  `caption` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`grade_id`),
  KEY `submission_grade_group_id` (`submission_grade_group_id`),
  KEY `fk_submission_grade_created_by_id_idx` (`created_by`),
  KEY `fk_submission_grade_updated_by_id_idx` (`updated_by`),
  CONSTRAINT `fk_submission_grade_created_by_id` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_submission_grade_submission_grade_group_id` FOREIGN KEY (`submission_grade_group_id`) REFERENCES `submission_grade_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_grade_updated_by_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_grade_group`
--

DROP TABLE IF EXISTS `submission_grade_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_grade_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_submission_grade_group_created_by_id_idx` (`created_by`),
  KEY `fk_submission_grade_group_updated_by_id_idx` (`updated_by`),
  CONSTRAINT `fk_submission_grade_group_created_by_id` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_submission_grade_group_updated_by_id` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_log`
--

DROP TABLE IF EXISTS `submission_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_log` (
  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user` int(6) NOT NULL DEFAULT 0,
  `page` tinyint(4) NOT NULL DEFAULT 0,
  `sid` int(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user`,`page`,`sid`),
  KEY `user` (`user`),
  KEY `page` (`page`),
  KEY `sid` (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_mark_type`
--

DROP TABLE IF EXISTS `submission_mark_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_mark_type` (
  `mark_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_name` varchar(32) DEFAULT NULL,
  `slug` varchar(16) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `enabled` int(1) DEFAULT 1,
  PRIMARY KEY (`mark_type_id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_return`
--

DROP TABLE IF EXISTS `submission_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_return` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `box_id` int(11) unsigned NOT NULL,
  `marker` int(10) unsigned NOT NULL,
  `owner` int(10) unsigned NOT NULL,
  `review_route` enum('instructor','self-reflection','self-grading') NOT NULL,
  `student_submission_id` int(5) DEFAULT NULL,
  `comment` mediumtext DEFAULT NULL,
  `mark` varchar(64) DEFAULT NULL,
  `instructor_mark` varchar(64) DEFAULT NULL,
  `norm_mark` decimal(14,11) DEFAULT NULL,
  `resubmission_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `published_by` int(10) unsigned NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box` (`box_id`),
  KEY `owner` (`owner`),
  KEY `marker` (`marker`),
  KEY `returned` (`created_at`),
  KEY `student_submission_id` (`student_submission_id`),
  KEY `deleted_at` (`deleted_at`),
  KEY `idx_submission_return_published_at` (`published_at`),
  KEY `fk_submission_return_publisher` (`published_by`),
  CONSTRAINT `fk_submission_return_box_id` FOREIGN KEY (`box_id`) REFERENCES `submission_box` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_return_marker` FOREIGN KEY (`marker`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_return_owner` FOREIGN KEY (`owner`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_submission_return_publisher` FOREIGN KEY (`published_by`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_return_file`
--

DROP TABLE IF EXISTS `submission_return_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_return_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `submission_return_id` int(11) unsigned NOT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `uuid` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `is_worksheet` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_submission_return_file_hash` (`hash`),
  KEY `deleted_at` (`deleted_at`),
  KEY `submission_return_id` (`submission_return_id`),
  KEY `uuid` (`uuid`(191)),
  CONSTRAINT `fk_submission_return_file_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `submission_return_file_return_id` FOREIGN KEY (`submission_return_id`) REFERENCES `submission_return` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `summative_report`
--

DROP TABLE IF EXISTS `summative_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `summative_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `introduction` longtext DEFAULT NULL,
  `logo_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `options` longtext DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `file_name` varchar(255) DEFAULT '[externalId]/Semester Report - [firstname] [surname]',
  `footer` varchar(255) DEFAULT '[reportTitle] - [firstname] [surname]',
  PRIMARY KEY (`id`),
  KEY `summative_report_logo_hash_fk` (`logo_hash`),
  CONSTRAINT `summative_report_logo_hash_fk` FOREIGN KEY (`logo_hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `superusers`
--

DROP TABLE IF EXISTS `superusers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `superusers` (
  `id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_superusers_user_id` FOREIGN KEY (`id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task`
--

DROP TABLE IF EXISTS `task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) unsigned NOT NULL,
  `link` varchar(2048) DEFAULT NULL,
  `requires_confirmation` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_task_assessment_id_idx` (`assessment_id`),
  KEY `task_user_id` (`user_id`),
  KEY `task_deleted_at` (`deleted_at`),
  KEY `deleted` (`deleted_at`),
  CONSTRAINT `fk_task_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=472 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_completion`
--

DROP TABLE IF EXISTS `task_completion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_completion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `student_id` int(11) unsigned NOT NULL,
  `marking_user_id` int(11) unsigned NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `completion_status` int(11) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_instance_completion_student_id` (`student_id`),
  KEY `task_instance_completion_marking_user_id` (`marking_user_id`),
  KEY `task_id` (`task_id`),
  KEY `deleted_at` (`deleted_at`),
  KEY `completion_date` (`completion_date`),
  CONSTRAINT `task_completion_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `task_completion_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`),
  CONSTRAINT `task_completion_ibfk_3` FOREIGN KEY (`marking_user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=338 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_file`
--

DROP TABLE IF EXISTS `task_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `storage_id` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `task_id` int(11) NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `storage_id` (`storage_id`),
  CONSTRAINT `fk_task_file` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_task_file_storage` FOREIGN KEY (`storage_id`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `term`
--

DROP TABLE IF EXISTS `term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `term` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT NULL,
  `code` varchar(16) DEFAULT NULL,
  `start` date NOT NULL,
  `display_finish` date NOT NULL,
  `actual_finish` date DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `title` (`title`),
  KEY `start` (`start`),
  KEY `finish` (`display_finish`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `term_year_level`
--

DROP TABLE IF EXISTS `term_year_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `term_year_level` (
  `term_id` int(11) NOT NULL,
  `year_level_id` int(10) unsigned NOT NULL,
  KEY `fk_term_year_level_term_id` (`term_id`),
  KEY `fk_term_year_level_year_level_id` (`year_level_id`),
  CONSTRAINT `fk_term_year_level_term_id` FOREIGN KEY (`term_id`) REFERENCES `term` (`id`),
  CONSTRAINT `fk_term_year_level_year_level_id` FOREIGN KEY (`year_level_id`) REFERENCES `lu_year_level` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `textbox`
--

DROP TABLE IF EXISTS `textbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `textbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_id` int(11) unsigned DEFAULT NULL,
  `heading` varchar(100) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(4) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(4) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(4) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categoryID` (`folder_id`),
  KEY `deleted` (`deleted_at`),
  KEY `fk_textBox_created_by` (`created_by`),
  KEY `fk_textBox_updated_by` (`updated_by`),
  KEY `fk_textBox_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_textBox_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_textBox_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_textBox_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_textBox_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tile`
--

DROP TABLE IF EXISTS `tile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tile` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tile_layout_id` int(11) unsigned NOT NULL,
  `title_text` varchar(250) DEFAULT NULL,
  `title_text_min_size` mediumint(3) NOT NULL DEFAULT 75 COMMENT 'Min text size as a % of default font size',
  `title_text_max_size` mediumint(3) NOT NULL DEFAULT 175 COMMENT 'Max text size as a % of default font size',
  `title_color` varchar(30) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `title_background_color` varchar(30) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `title_position` tinyint(1) NOT NULL COMMENT '1: top, 2: bottom',
  `link` varchar(2048) DEFAULT NULL,
  `link_target` varchar(10) DEFAULT NULL,
  `background_image_id` int(11) DEFAULT NULL,
  `background_image_contained` tinyint(1) NOT NULL DEFAULT 0,
  `background_image_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `background_color` varchar(30) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `background_position` tinyint(1) NOT NULL DEFAULT 5,
  `hover_color` varchar(30) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `background_image_id` (`background_image_id`),
  KEY `tile_tile_layout` (`tile_layout_id`),
  KEY `deleted` (`deleted_at`),
  KEY `background_image_hash` (`background_image_hash`),
  CONSTRAINT `fk_tile_background_image_hash` FOREIGN KEY (`background_image_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `tile_tile_layout` FOREIGN KEY (`tile_layout_id`) REFERENCES `tile_layout` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tile_layout`
--

DROP TABLE IF EXISTS `tile_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tile_layout` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `heading` varchar(2048) NOT NULL,
  `layout_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: grid, 2: column',
  `grid_width` int(4) NOT NULL DEFAULT 0,
  `grid_padding` tinyint(4) NOT NULL DEFAULT 0,
  `column_count` tinyint(1) NOT NULL DEFAULT 1,
  `column_padding` tinyint(4) NOT NULL DEFAULT 0,
  `aspect_ratio_x` tinyint(2) NOT NULL DEFAULT 16,
  `aspect_ratio_y` tinyint(2) NOT NULL DEFAULT 9,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tile_layout_for_dashboard`
--

DROP TABLE IF EXISTS `tile_layout_for_dashboard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tile_layout_for_dashboard` (
  `tile_layout_id` int(11) unsigned NOT NULL,
  `role_id` int(4) unsigned NOT NULL,
  UNIQUE KEY `tile_layout_for_dashboard_unique` (`tile_layout_id`,`role_id`),
  KEY `fk_tile_layout_for_dashboard_tile_layout_id` (`tile_layout_id`),
  KEY `fk_tile_layout_for_dashboard_role_id` (`role_id`),
  CONSTRAINT `fk_tile_layout_for_dashboard_role_id` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tile_layout_for_dashboard_tile_layout_id` FOREIGN KEY (`tile_layout_id`) REFERENCES `tile_layout` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tile_layout_for_folder`
--

DROP TABLE IF EXISTS `tile_layout_for_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tile_layout_for_folder` (
  `tile_layout_id` int(11) unsigned NOT NULL,
  `folder_id` int(11) unsigned NOT NULL,
  UNIQUE KEY `tile_layout_for_folder_unique` (`tile_layout_id`,`folder_id`),
  KEY `fk_tile_layout_for_folder_tile_layout_id` (`tile_layout_id`),
  KEY `fk_tile_layout_for_folder_folder_id` (`folder_id`),
  CONSTRAINT `fk_tile_layout_for_folder_folder_id` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tile_layout_for_folder_tile_layout_id` FOREIGN KEY (`tile_layout_id`) REFERENCES `tile_layout` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tile_layout_for_homepage_template`
--

DROP TABLE IF EXISTS `tile_layout_for_homepage_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tile_layout_for_homepage_template` (
  `tile_layout_id` int(11) unsigned NOT NULL,
  `homepage_template_id` int(11) unsigned NOT NULL,
  UNIQUE KEY `tile_layout_for_homepage_template_unique` (`tile_layout_id`,`homepage_template_id`),
  KEY `fk_tile_layout_for_homepage_template_tile_layout_id` (`tile_layout_id`),
  KEY `fk_tile_layout_for_homepage_template_homepage_template_id` (`homepage_template_id`),
  CONSTRAINT `fk_tile_layout_for_homepage_template_homepage_template_id` FOREIGN KEY (`homepage_template_id`) REFERENCES `homepageTemplate` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tile_layout_for_homepage_template_tile_layout_id` FOREIGN KEY (`tile_layout_id`) REFERENCES `tile_layout` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour`
--

DROP TABLE IF EXISTS `tour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour` (
  `uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `first_step_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `title` varchar(512) NOT NULL,
  `author_user_id` int(4) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `active` int(1) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `tour_author_user_id_idx` (`author_user_id`),
  KEY `tour_step_uuid_idx` (`first_step_uuid`),
  CONSTRAINT `fk_tour_author_user_id` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_audience_role`
--

DROP TABLE IF EXISTS `tour_audience_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_audience_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moderator_user_id` int(4) unsigned NOT NULL,
  `tour_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `role_id` int(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tour_audience_role_tour_uuid_fk` (`tour_uuid`),
  KEY `tour_audience_role_moderator_user_id_idx` (`moderator_user_id`),
  KEY `tour_audience_role_tour_uuid_idx` (`moderator_user_id`),
  KEY `tour_audience_role_role_id_idx` (`role_id`),
  CONSTRAINT `tour_audience_role_moderator_user_id_fk` FOREIGN KEY (`moderator_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_audience_role_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `tour_audience_role_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_status`
--

DROP TABLE IF EXISTS `tour_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_status` (
  `user_id` int(4) unsigned NOT NULL,
  `tour_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `status` enum('started','dismissed','completed','disabled') NOT NULL,
  `created_at` datetime NOT NULL,
  UNIQUE KEY `user_tour_status` (`user_id`,`tour_uuid`),
  KEY `tour_status_tour_uuid_fk` (`tour_uuid`),
  CONSTRAINT `tour_status_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`),
  CONSTRAINT `tour_status_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_step`
--

DROP TABLE IF EXISTS `tour_step`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_step` (
  `uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `tour_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `next_step_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `author_user_id` int(4) unsigned DEFAULT NULL,
  `type` enum('tooltip','modal') DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `tour_step_tour_uuid_idx` (`tour_uuid`),
  KEY `tour_step_author_user_id_idx` (`author_user_id`),
  KEY `tour_step_tour_next_step_uuid_idx` (`next_step_uuid`),
  CONSTRAINT `tour_step_author_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_step_tour_id_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`),
  CONSTRAINT `tour_step_tour_next_step_uuid_fk` FOREIGN KEY (`next_step_uuid`) REFERENCES `tour_step` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_step_event`
--

DROP TABLE IF EXISTS `tour_step_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_step_event` (
  `uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `tour_step_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `selector` varchar(255) NOT NULL,
  `event` varchar(255) NOT NULL,
  `insertion_point` enum('open','close') NOT NULL,
  `order` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `tour_step_event_tour_step_uuid_fk` (`tour_step_uuid`),
  CONSTRAINT `tour_step_event_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_step_modal`
--

DROP TABLE IF EXISTS `tour_step_modal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_step_modal` (
  `uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `tour_step_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `css_class` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `tour_step_modal_tour_step_uuid_fk` (`tour_step_uuid`),
  CONSTRAINT `tour_step_modal_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_step_tooltip`
--

DROP TABLE IF EXISTS `tour_step_tooltip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_step_tooltip` (
  `uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `tour_step_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `body` text DEFAULT NULL,
  `selector` varchar(255) NOT NULL,
  `css_class` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `tour_step_tooltip_tour_step_uuid_idx` (`tour_step_uuid`),
  KEY `tour_step_event_tour_step_uuid_idx` (`tour_step_uuid`),
  CONSTRAINT `tour_step_tooltip_tour_step_uuid_fk` FOREIGN KEY (`tour_step_uuid`) REFERENCES `tour_step` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tour_trigger`
--

DROP TABLE IF EXISTS `tour_trigger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_trigger` (
  `uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `author_user_id` int(4) unsigned NOT NULL,
  `tour_uuid` varchar(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `selector` varchar(255) NOT NULL,
  `event` varchar(255) NOT NULL,
  `url_regex` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `tour_trigger_user_id_idx` (`author_user_id`),
  KEY `tour_trigger_tour_uuid_idx` (`tour_uuid`),
  CONSTRAINT `tour_trigger_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`),
  CONSTRAINT `tour_trigger_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turnitin_group`
--

DROP TABLE IF EXISTS `turnitin_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `turnitin_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('pending','group','group_attachment','uploading','complete','error') NOT NULL,
  `error_code` varchar(255) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `submission_box_id` int(10) unsigned NOT NULL,
  `group_id` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id_idx` (`group_id`),
  KEY `turnitin_status_idx` (`status`),
  KEY `fk_turnitin_submission_box_id` (`submission_box_id`),
  CONSTRAINT `fk_turnitin_submission_box_id` FOREIGN KEY (`submission_box_id`) REFERENCES `submission_box` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turnitin_group_attachment`
--

DROP TABLE IF EXISTS `turnitin_group_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `turnitin_group_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('pending','uploading','complete','error') NOT NULL,
  `error_code` varchar(255) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `submission_box_file_id` int(10) unsigned NOT NULL,
  `turnitin_group_id` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `turnitin_group_attachment_id` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turnitin_group_attachment_id_idx` (`turnitin_group_attachment_id`),
  KEY `turnitin_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turnitin_report`
--

DROP TABLE IF EXISTS `turnitin_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `turnitin_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('pending','group','submission','uploading','loaded','prepared','analysing','indexing','complete','error') NOT NULL,
  `error_code` varchar(255) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `student_submission_file_id` int(10) unsigned NOT NULL,
  `turnitin_submission_id` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `overall_match_percentage` decimal(10,2) DEFAULT NULL,
  `internet_match_percentage` decimal(10,2) DEFAULT NULL,
  `publication_match_percentage` decimal(10,2) DEFAULT NULL,
  `submitted_works_match_percentage` decimal(10,2) DEFAULT NULL,
  `historical` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turnitin_submission_id_idx` (`turnitin_submission_id`),
  KEY `turnitin_status_idx` (`status`),
  KEY `fk_turnitin_subfile_id` (`student_submission_file_id`),
  CONSTRAINT `fk_turnitin_subfile_id` FOREIGN KEY (`student_submission_file_id`) REFERENCES `student_submission_file` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_condition`
--

DROP TABLE IF EXISTS `ups_condition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_condition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` int(10) unsigned NOT NULL,
  `condition_type_id` int(10) unsigned NOT NULL,
  `arguments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`arguments`)),
  PRIMARY KEY (`id`),
  KEY `rule_id` (`rule_id`),
  KEY `condition_type_id` (`condition_type_id`),
  CONSTRAINT `ups_condition_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `ups_rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ups_condition_ibfk_2` FOREIGN KEY (`condition_type_id`) REFERENCES `ups_condition_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_condition_type`
--

DROP TABLE IF EXISTS `ups_condition_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_condition_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_config`
--

DROP TABLE IF EXISTS `ups_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_config` (
  `role_type` enum('student','parent','staff') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`role_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_custom_hydrator`
--

DROP TABLE IF EXISTS `ups_custom_hydrator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_custom_hydrator` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class` varchar(256) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `role_type` enum('student','parent','staff') DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_type_3` (`role_type`,`sequence`),
  UNIQUE KEY `role_type_4` (`role_type`,`class`),
  KEY `class` (`class`),
  KEY `role_type` (`role_type`),
  KEY `role_type_2` (`role_type`,`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_effect`
--

DROP TABLE IF EXISTS `ups_effect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_effect` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` int(10) unsigned NOT NULL,
  `user_property` varchar(64) DEFAULT NULL,
  `effect_type_id` int(10) unsigned NOT NULL,
  `arguments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`arguments`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_id_2` (`rule_id`,`user_property`),
  KEY `rule_id` (`rule_id`),
  KEY `effect_type_id` (`effect_type_id`),
  CONSTRAINT `ups_effect_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `ups_rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ups_effect_ibfk_2` FOREIGN KEY (`effect_type_id`) REFERENCES `ups_effect_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_effect_type`
--

DROP TABLE IF EXISTS `ups_effect_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_effect_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_log`
--

DROP TABLE IF EXISTS `ups_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_type` enum('student','staff','parent') DEFAULT NULL,
  `dry` tinyint(1) NOT NULL,
  `users_created` int(10) unsigned NOT NULL,
  `users_updated` int(10) unsigned NOT NULL,
  `users_skipped` int(10) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ups_log_storage_hash_fk` (`hash`),
  CONSTRAINT `ups_log_storage_hash_fk` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ups_rule`
--

DROP TABLE IF EXISTS `ups_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ups_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_type` enum('student','parent','staff') DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sequence` int(10) unsigned NOT NULL,
  `name` varchar(256) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_type` (`role_type`),
  KEY `role_type_2` (`role_type`,`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `group_id` int(4) unsigned NOT NULL DEFAULT 0,
  `title` varchar(20) DEFAULT NULL,
  `pronoun` int(11) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `preferred_name` varchar(255) DEFAULT NULL,
  `chosen_name` varchar(255) GENERATED ALWAYS AS (if(coalesce(`preferred_name`,'') = '',`firstname`,`preferred_name`)) VIRTUAL,
  `lastname` varchar(255) DEFAULT NULL,
  `synergy_id` varchar(64) DEFAULT NULL,
  `year_id` int(10) unsigned DEFAULT NULL,
  `house` int(11) unsigned DEFAULT NULL,
  `residential_house_id` int(11) unsigned DEFAULT NULL,
  `local_email` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `alt_email` varchar(255) DEFAULT NULL,
  `mail_handler` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `portrait_hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `key` varchar(40) DEFAULT NULL,
  `eportfolio_enabled` int(8) NOT NULL DEFAULT 0,
  `calendar_view` enum('month','agendaWeek','agendaDay','list') NOT NULL DEFAULT 'month',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is this account active?',
  `hide_contacts` tinyint(1) DEFAULT 0,
  `hide_timetable` tinyint(1) DEFAULT 0,
  `hide_sidenav` tinyint(1) DEFAULT 0,
  `hide_notifications_tray` tinyint(1) DEFAULT 0 COMMENT 'Is the notifications tray hidden?',
  `position_title` varchar(255) DEFAULT NULL,
  `display_grade_graph` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  `ib_candidate_number` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `synergy` (`synergy_id`),
  KEY `username` (`username`),
  KEY `groupind` (`group_id`),
  KEY `deleted_at` (`deleted_at`),
  KEY `fk_user_residential_house_id_idx` (`residential_house_id`),
  KEY `fk_user_house_id_idx` (`house`),
  KEY `fk_user_portrait_hash` (`portrait_hash`),
  KEY `fk_user_year_id_idx` (`year_id`),
  KEY `fk_user_pronoun_id` (`pronoun`),
  KEY `user_email` (`alt_email`),
  KEY `user_key` (`key`),
  CONSTRAINT `fk_user_house_id` FOREIGN KEY (`house`) REFERENCES `house` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_portrait_hash` FOREIGN KEY (`portrait_hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_pronoun_id` FOREIGN KEY (`pronoun`) REFERENCES `pronoun` (`id`),
  CONSTRAINT `fk_user_residential_house_id` FOREIGN KEY (`residential_house_id`) REFERENCES `luResidentialHouse` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_role_id` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_year_id` FOREIGN KEY (`year_id`) REFERENCES `lu_year_level` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userAccess`
--

DROP TABLE IF EXISTS `userAccess`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userAccess` (
  `uid` int(11) unsigned NOT NULL,
  `service` int(11) unsigned NOT NULL,
  `sid` int(11) NOT NULL DEFAULT 0,
  `type` smallint(6) DEFAULT NULL,
  `favourite` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`service`,`sid`,`uid`),
  KEY `uid` (`uid`),
  KEY `sid` (`sid`),
  KEY `service` (`service`),
  KEY `idx_userAccess_type` (`type`),
  CONSTRAINT `fk_userAccess_service_id` FOREIGN KEY (`service`) REFERENCES `lu_service` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_userAccess_user_id` FOREIGN KEY (`uid`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userCampus`
--

DROP TABLE IF EXISTS `userCampus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userCampus` (
  `uid` int(3) unsigned NOT NULL DEFAULT 0,
  `campus` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `defaultCampus` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`uid`,`campus`),
  KEY `uid` (`uid`),
  KEY `campus` (`campus`),
  CONSTRAINT `fk_userCampus_campus_id` FOREIGN KEY (`campus`) REFERENCES `luCampus` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_userCampus_user_id` FOREIGN KEY (`uid`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userGroups`
--

DROP TABLE IF EXISTS `userGroups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userGroups` (
  `cid` int(6) unsigned NOT NULL,
  `type` tinyint(2) NOT NULL,
  `force_follow` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`cid`),
  CONSTRAINT `fk_userGroups_folder_id` FOREIGN KEY (`cid`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_activity_log`
--

DROP TABLE IF EXISTS `user_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `date` datetime NOT NULL,
  `ip` varchar(15) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `event` varchar(20) DEFAULT NULL,
  `message` varchar(250) DEFAULT NULL,
  `level` enum('error','warn','info','debug','trace') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_assume_log`
--

DROP TABLE IF EXISTS `user_assume_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_assume_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(60) DEFAULT NULL,
  `assumed` varchar(255) DEFAULT NULL,
  `ip` varchar(39) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_jwt_id`
--

DROP TABLE IF EXISTS `user_jwt_id`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_jwt_id` (
  `jwt_id` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `user_id` int(4) unsigned NOT NULL,
  `type` enum('api','qrcode','apitest') DEFAULT NULL,
  `expiry` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(4) unsigned DEFAULT NULL,
  `updated_by` int(4) unsigned DEFAULT NULL,
  `deleted_by` int(4) unsigned DEFAULT NULL,
  PRIMARY KEY (`jwt_id`),
  KEY `fk_user_jwt_id_user_id` (`user_id`),
  KEY `fk_user_jwt_id_created_by` (`created_by`),
  KEY `fk_user_jwt_id_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_user_jwt_id_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_user_jwt_id_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_user_jwt_id_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_language`
--

DROP TABLE IF EXISTS `user_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_language` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(4) unsigned NOT NULL,
  `language` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_language_user_id` (`user_id`),
  CONSTRAINT `fk_user_language_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_link`
--

DROP TABLE IF EXISTS `user_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_link` (
  `user_id` int(10) unsigned NOT NULL,
  `linked_user_id` int(10) unsigned NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`linked_user_id`),
  KEY `user_id` (`user_id`),
  KEY `linked_user_id` (`linked_user_id`),
  CONSTRAINT `fk_user_link_linked_user_id` FOREIGN KEY (`linked_user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_link_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_nationality`
--

DROP TABLE IF EXISTS `user_nationality`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_nationality` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(4) unsigned NOT NULL,
  `nationality` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_nationality_user_id` (`user_id`),
  CONSTRAINT `fk_user_nationality_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_password_reset_request`
--

DROP TABLE IF EXISTS `user_password_reset_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_password_reset_request` (
  `token` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `expiry` datetime NOT NULL,
  `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `reset_time` datetime DEFAULT NULL,
  PRIMARY KEY (`token`),
  KEY `fk_user_password_reset_request_user_id_idx` (`user_id`),
  CONSTRAINT `fk_user_password_reset_request_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_qualification`
--

DROP TABLE IF EXISTS `user_qualification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_qualification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `qualification_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_qualification` (`user_id`,`qualification_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_qualification_id` (`qualification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_assessment_feedback`
--

DROP TABLE IF EXISTS `v_assessment_feedback`;
/*!50001 DROP VIEW IF EXISTS `v_assessment_feedback`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_assessment_feedback` AS SELECT
 1 AS `assessment_id`,
  1 AS `feedback_table`,
  1 AS `feedback_id`,
  1 AS `student_id`,
  1 AS `review_route`,
  1 AS `published_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_assessment_participation`
--

DROP TABLE IF EXISTS `v_assessment_participation`;
/*!50001 DROP VIEW IF EXISTS `v_assessment_participation`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_assessment_participation` AS SELECT
 1 AS `assessment_id`,
  1 AS `assessment_deleted_at`,
  1 AS `student_id`,
  1 AS `folder_id`,
  1 AS `is_dynamic`,
  1 AS `description`,
  1 AS `publish_date`,
  1 AS `open_date`,
  1 AS `due_date`,
  1 AS `close_date`,
  1 AS `excluded_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_assessment_relation`
--

DROP TABLE IF EXISTS `v_assessment_relation`;
/*!50001 DROP VIEW IF EXISTS `v_assessment_relation`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_assessment_relation` AS SELECT
 1 AS `assessment_id`,
  1 AS `folder_id`,
  1 AS `project_assessment_id`,
  1 AS `course_implementation_id`,
  1 AS `course_unit_implementation_id`,
  1 AS `course_specification_id`,
  1 AS `course_unit_specification_id` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_assessment_response`
--

DROP TABLE IF EXISTS `v_assessment_response`;
/*!50001 DROP VIEW IF EXISTS `v_assessment_response`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_assessment_response` AS SELECT
 1 AS `assessment_id`,
  1 AS `response_table`,
  1 AS `response_id`,
  1 AS `student_id`,
  1 AS `submitted_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_most_recent_feedback`
--

DROP TABLE IF EXISTS `v_most_recent_feedback`;
/*!50001 DROP VIEW IF EXISTS `v_most_recent_feedback`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_most_recent_feedback` AS SELECT
 1 AS `assessment_id`,
  1 AS `feedback_table`,
  1 AS `feedback_id`,
  1 AS `student_id`,
  1 AS `review_route`,
  1 AS `published_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_most_recent_rubric_mark`
--

DROP TABLE IF EXISTS `v_most_recent_rubric_mark`;
/*!50001 DROP VIEW IF EXISTS `v_most_recent_rubric_mark`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_most_recent_rubric_mark` AS SELECT
 1 AS `assessment_id`,
  1 AS `capability_mark_type_id`,
  1 AS `capability_grade_group_id`,
  1 AS `student_id`,
  1 AS `marker_id`,
  1 AS `feedback_id`,
  1 AS `feedback_date`,
  1 AS `updated_date`,
  1 AS `feedback_type`,
  1 AS `capability_id`,
  1 AS `capability_name`,
  1 AS `capability_max_value`,
  1 AS `capability_mark`,
  1 AS `capability_human_readable_mark`,
  1 AS `indicator_id`,
  1 AS `indicator_name`,
  1 AS `indicator_max_value`,
  1 AS `indicator_mark`,
  1 AS `descriptor_id`,
  1 AS `descriptor_name` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_rubric`
--

DROP TABLE IF EXISTS `v_rubric`;
/*!50001 DROP VIEW IF EXISTS `v_rubric`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_rubric` AS SELECT
 1 AS `assessment_id`,
  1 AS `assessment_title`,
  1 AS `assessment_mark_type`,
  1 AS `assessment_max_mark`,
  1 AS `assessment_grade_group_id`,
  1 AS `assessment_weight`,
  1 AS `capability_id`,
  1 AS `capability_name`,
  1 AS `capability_max_value`,
  1 AS `capability_sequence`,
  1 AS `capability_mark_type_id`,
  1 AS `capability_grade_group_id`,
  1 AS `indicator_id`,
  1 AS `indicator_name`,
  1 AS `indicator_sequence`,
  1 AS `indicator_max_value` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_rubric_capability_mark`
--

DROP TABLE IF EXISTS `v_rubric_capability_mark`;
/*!50001 DROP VIEW IF EXISTS `v_rubric_capability_mark`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_rubric_capability_mark` AS SELECT
 1 AS `assessment_id`,
  1 AS `student_id`,
  1 AS `marker_id`,
  1 AS `feedback_id`,
  1 AS `feedback_date`,
  1 AS `feedback_type`,
  1 AS `capability_id`,
  1 AS `capability_name`,
  1 AS `capability_max_value`,
  1 AS `capability_mark` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_rubric_indicator_mark`
--

DROP TABLE IF EXISTS `v_rubric_indicator_mark`;
/*!50001 DROP VIEW IF EXISTS `v_rubric_indicator_mark`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_rubric_indicator_mark` AS SELECT
 1 AS `assessment_id`,
  1 AS `student_id`,
  1 AS `marker_id`,
  1 AS `feedback_id`,
  1 AS `feedback_date`,
  1 AS `feedback_type`,
  1 AS `capability_id`,
  1 AS `indicator_id`,
  1 AS `indicator_name`,
  1 AS `indicator_max_value`,
  1 AS `indicator_mark`,
  1 AS `descriptor_name` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_rubric_indicator_mark_extend`
--

DROP TABLE IF EXISTS `v_rubric_indicator_mark_extend`;
/*!50001 DROP VIEW IF EXISTS `v_rubric_indicator_mark_extend`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_rubric_indicator_mark_extend` AS SELECT
 1 AS `rubric_capability_mark_id`,
  1 AS `descriptor_id`,
  1 AS `rubric_indicator_id`,
  1 AS `value`,
  1 AS `valid`,
  1 AS `descriptor_name` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_rubric_mark`
--

DROP TABLE IF EXISTS `v_rubric_mark`;
/*!50001 DROP VIEW IF EXISTS `v_rubric_mark`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_rubric_mark` AS SELECT
 1 AS `assessment_id`,
  1 AS `capability_mark_type_id`,
  1 AS `capability_grade_group_id`,
  1 AS `student_id`,
  1 AS `marker_id`,
  1 AS `feedback_id`,
  1 AS `feedback_date`,
  1 AS `feedback_type`,
  1 AS `capability_id`,
  1 AS `capability_name`,
  1 AS `capability_max_value`,
  1 AS `capability_mark`,
  1 AS `capability_human_readable_mark`,
  1 AS `indicator_id`,
  1 AS `indicator_name`,
  1 AS `indicator_max_value`,
  1 AS `indicator_mark`,
  1 AS `descriptor_id`,
  1 AS `descriptor_name` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `video_queue`
--

DROP TABLE IF EXISTS `video_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `video_queue` (
  `video_queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `type` enum('sbfs','storage','submission','myfile') DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL COMMENT 'The user who uploaded this media, if known',
  `status` enum('pending','processing','completed','failed') DEFAULT NULL,
  `message` text DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `createdOn` datetime NOT NULL,
  `modifiedOn` datetime NOT NULL,
  PRIMARY KEY (`video_queue_id`),
  KEY `hash` (`hash`),
  KEY `status` (`status`),
  KEY `fk_video_queue_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_video_queue_storage_hash` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_video_queue_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webdav`
--

DROP TABLE IF EXISTS `webdav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webdav` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uri` varchar(1024) DEFAULT NULL,
  `path` varchar(1024) DEFAULT NULL,
  `username_prefix` varchar(100) DEFAULT NULL,
  `username_suffix` varchar(100) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `role_type` enum('guest','student','parent','staff') DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki`
--

DROP TABLE IF EXISTS `wiki`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki` (
  `wikiID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `categoryID` int(11) unsigned DEFAULT NULL,
  `latestRevision` int(11) unsigned DEFAULT NULL COMMENT 'Nullable to enable insertion of wiki before first revision',
  `slug` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `_deleted` enum('true','false') DEFAULT NULL,
  `locked` enum('true','false') DEFAULT NULL,
  PRIMARY KEY (`wikiID`),
  KEY `category_slug` (`categoryID`,`slug`(191)),
  KEY `categoryID` (`categoryID`),
  KEY `fk_wiki_latestRevision_wikiRevision_revisionID` (`latestRevision`),
  KEY `deleted_at` (`deleted_at`),
  CONSTRAINT `fk_wiki_folder_id` FOREIGN KEY (`categoryID`) REFERENCES `folder` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_wiki_latestRevision_wikiRevision_revisionID` FOREIGN KEY (`latestRevision`) REFERENCES `wikiRevision` (`revisionID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wikiRevision`
--

DROP TABLE IF EXISTS `wikiRevision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wikiRevision` (
  `revisionID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `wikiID` int(11) unsigned NOT NULL,
  `heading` varchar(200) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `userID` int(11) unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`revisionID`),
  KEY `wikiID` (`wikiID`),
  KEY `userID` (`userID`),
  CONSTRAINT `fk_wikiRevision_user_id` FOREIGN KEY (`userID`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_wikiRevision_wiki_id` FOREIGN KEY (`wikiID`) REFERENCES `wiki` (`wikiID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `v_assessment_feedback`
--

/*!50001 DROP VIEW IF EXISTS `v_assessment_feedback`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_assessment_feedback` AS (select `sb`.`assessment_id` AS `assessment_id`,'submission_return' AS `feedback_table`,`sr`.`id` AS `feedback_id`,`sr`.`owner` AS `student_id`,`sr`.`review_route` AS `review_route`,`sr`.`published_at` AS `published_at` from (`submission_return` `sr` join `submission_box` `sb` on(`sr`.`box_id` = `sb`.`id`)) where `sr`.`deleted_at` is null) union (select `ass`.`assessment_id` AS `assessment_id`,'assess_attempt' AS `feedback_table`,`aa`.`attempt_id` AS `feedback_id`,`aa`.`user_id` AS `student_id`,'instructor' AS `review_route`,`aa`.`reviewed_at` AS `published_at` from (`assess_attempt` `aa` join `assess` `ass` on(`aa`.`assess_id` = `ass`.`assess_id`)) where `aa`.`deleted_at` is null and `aa`.`reviewed_at` is not null and `ass`.`assessment_id` is not null) union (select `t`.`assessment_id` AS `assessment_id`,'task_completion' AS `feedback_table`,`tc`.`id` AS `feedback_id`,`tc`.`student_id` AS `student_id`,'instructor' AS `review_route`,`tc`.`completion_date` AS `published_at` from (`task_completion` `tc` join `task` `t` on(`tc`.`task_id` = `t`.`id`)) where `tc`.`deleted_at` is null and `tc`.`completion_date` is not null and `tc`.`completion_status` = 3) union (select `la`.`assessment_id` AS `assessment_id`,'lti_assessment_score' AS `feedback_table`,`sc`.`id` AS `feedback_id`,`sc`.`student_id` AS `student_id`,'instructor' AS `review_route`,`sc`.`created_at` AS `published_at` from (`lti_assessment_score` `sc` join `lti_assessment` `la` on(`sc`.`lti_assessment_id` = `la`.`id`)) where `sc`.`deleted_at` is null and `sc`.`is_feedback` = 1) union (select `reporting`.`assessment_id` AS `assessment_id`,'reporting_feedback' AS `feedback_table`,`reporting_feedback`.`id` AS `feedback_id`,`reporting_feedback`.`learner_id` AS `student_id`,'instructor' AS `review_route`,`reporting_feedback`.`published_at` AS `published_at` from (`reporting_feedback` join `reporting` on(`reporting_feedback`.`reporting_id` = `reporting`.`id`)) where `reporting_feedback`.`deleted_at` is null) order by `assessment_id`,`student_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_assessment_participation`
--

/*!50001 DROP VIEW IF EXISTS `v_assessment_participation`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_assessment_participation` AS select `a`.`id` AS `assessment_id`,`a`.`deleted_at` AS `assessment_deleted_at`,`p`.`user_id` AS `student_id`,`var`.`folder_id` AS `folder_id`,`pp`.`is_dynamic` AS `is_dynamic`,coalesce(`pp`.`description`,`a`.`description`) AS `description`,coalesce(`pp`.`publish_date`,`a`.`publish_date`) AS `publish_date`,coalesce(`pp`.`open_date`,`a`.`open_date`) AS `open_date`,coalesce(`pp`.`due_date`,`a`.`due_date`) AS `due_date`,coalesce(`pp`.`close_date`,`a`.`close_date`) AS `close_date`,`p`.`excluded_at` AS `excluded_at` from (((`assessment` `a` join `mv_assessment_relation` `var` on(`a`.`id` = `var`.`assessment_id` and `var`.`course_specification_id` is null and `var`.`course_unit_specification_id` is null)) join `participant_plan` `pp` on(`a`.`id` = `pp`.`assessment_id` and `pp`.`deleted_at` is null)) join `participant` `p` on(`pp`.`id` = `p`.`participant_plan_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_assessment_relation`
--

/*!50001 DROP VIEW IF EXISTS `v_assessment_relation`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_assessment_relation` AS select `a`.`id` AS `assessment_id`,`f`.`id` AS `folder_id`,NULL AS `project_assessment_id`,NULL AS `course_implementation_id`,NULL AS `course_unit_implementation_id`,NULL AS `course_specification_id`,NULL AS `course_unit_specification_id` from ((`assessment` `a` join `assessment_folder` `af` on(`a`.`id` = `af`.`assessment_id`)) join `folder` `f` on(`af`.`folder_id` = `f`.`id`)) union select `a`.`id` AS `assessment_id`,`f`.`id` AS `folder_id`,NULL AS `project_assessment_id`,`ci`.`id` AS `course_implementation_id`,`cui`.`id` AS `course_unit_implementation_id`,NULL AS `course_specification_id`,NULL AS `course_unit_specification_id` from ((((`assessment` `a` join `assessment_course_unit_implementation` `acui` on(`a`.`id` = `acui`.`assessment_id`)) join `course_unit_implementation` `cui` on(`acui`.`course_unit_implementation_id` = `cui`.`id`)) join `course_implementation` `ci` on(`cui`.`course_implementation_id` = `ci`.`id`)) join `folder` `f` on(`ci`.`folder_id` = `f`.`id`)) union select `a`.`id` AS `assessment_id`,`f`.`id` AS `folder_id`,NULL AS `project_assessment_id`,NULL AS `course_implementation_id`,NULL AS `course_unit_implementation_id`,`cs`.`id` AS `course_specification_id`,`cus`.`id` AS `course_unit_specification_id` from ((((`assessment` `a` join `assessment_course_unit_specification` `acus` on(`a`.`id` = `acus`.`assessment_id`)) join `course_unit_specification` `cus` on(`acus`.`course_unit_specification_id` = `cus`.`id`)) join `course_specification` `cs` on(`cus`.`course_specification_id` = `cs`.`id`)) join `folder` `f` on(`cs`.`folder_id` = `f`.`id`)) union select `a`.`id` AS `assessment_id`,`f`.`id` AS `folder_id`,`p`.`id` AS `project_assessment_id`,NULL AS `course_implementation_id`,NULL AS `course_unit_implementation_id`,NULL AS `course_specification_id`,NULL AS `course_unit_specification_id` from ((((`assessment` `a` join `project_assessment` `pa` on(`a`.`id` = `pa`.`assessment_id`)) join `project` `p` on(`pa`.`project_id` = `p`.`id`)) join `assessment_folder` `af` on(`p`.`assessment_id` = `af`.`assessment_id`)) join `folder` `f` on(`af`.`folder_id` = `f`.`id`)) union select `a`.`id` AS `assessment_id`,`f`.`id` AS `folder_id`,`p`.`id` AS `project_assessment_id`,`ci`.`id` AS `course_implementation_id`,`cui`.`id` AS `course_unit_implementation_id`,NULL AS `course_specification_id`,NULL AS `course_unit_specification_id` from ((((((`assessment` `a` join `project_assessment` `pa` on(`a`.`id` = `pa`.`assessment_id`)) join `project` `p` on(`pa`.`project_id` = `p`.`id`)) join `assessment_course_unit_implementation` `acui` on(`p`.`assessment_id` = `acui`.`assessment_id`)) join `course_unit_implementation` `cui` on(`acui`.`course_unit_implementation_id` = `cui`.`id`)) join `course_implementation` `ci` on(`cui`.`course_implementation_id` = `ci`.`id`)) join `folder` `f` on(`ci`.`folder_id` = `f`.`id`)) union select `a`.`id` AS `assessment_id`,`f`.`id` AS `folder_id`,`p`.`id` AS `project_assessment_id`,NULL AS `course_implementation_id`,NULL AS `course_unit_implementation_id`,`cs`.`id` AS `course_specification_id`,`cus`.`id` AS `course_unit_specification_id` from ((((((`assessment` `a` join `project_assessment` `pa` on(`a`.`id` = `pa`.`assessment_id`)) join `project` `p` on(`pa`.`project_id` = `p`.`id`)) join `assessment_course_unit_specification` `acus` on(`p`.`assessment_id` = `acus`.`assessment_id`)) join `course_unit_specification` `cus` on(`acus`.`course_unit_specification_id` = `cus`.`id`)) join `course_specification` `cs` on(`cus`.`course_specification_id` = `cs`.`id`)) join `folder` `f` on(`cs`.`folder_id` = `f`.`id`)) order by `assessment_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_assessment_response`
--

/*!50001 DROP VIEW IF EXISTS `v_assessment_response`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_assessment_response` AS (select `sb`.`assessment_id` AS `assessment_id`,'student_submission' AS `response_table`,`ss`.`id` AS `response_id`,`ss`.`owner` AS `student_id`,`ss`.`submitted_at` AS `submitted_at` from (`student_submission` `ss` join `submission_box` `sb` on(`ss`.`box_id` = `sb`.`id`)) where `ss`.`deleted_at` is null) union (select `ass`.`assessment_id` AS `assessment_id`,'assess_attempt' AS `response_table`,`aa`.`attempt_id` AS `response_id`,`aa`.`user_id` AS `student_id`,`aa`.`finish` AS `submitted_at` from (`assess_attempt` `aa` join `assess` `ass` on(`aa`.`assess_id` = `ass`.`assess_id`)) where `aa`.`deleted_at` is null and `ass`.`assessment_id` is not null) union (select `t`.`assessment_id` AS `assessment_id`,'task_completion' AS `response_table`,`tc`.`id` AS `response_id`,`tc`.`student_id` AS `student_id`,`tc`.`completion_date` AS `submitted_at` from (`task_completion` `tc` join `task` `t` on(`tc`.`task_id` = `t`.`id`)) where `tc`.`deleted_at` is null and `tc`.`completion_date` is not null and `tc`.`completion_status` = 2) union (select `la`.`assessment_id` AS `assessment_id`,'lts_assessment_score' AS `response_table`,`sc`.`id` AS `response_id`,`sc`.`student_id` AS `student_id`,`sc`.`created_at` AS `submitted_at` from (`lti_assessment_score` `sc` join `lti_assessment` `la` on(`sc`.`lti_assessment_id` = `la`.`id`)) where `sc`.`deleted_at` is null and `sc`.`is_response` = 1) order by `assessment_id`,`student_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_most_recent_feedback`
--

/*!50001 DROP VIEW IF EXISTS `v_most_recent_feedback`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_most_recent_feedback` AS select `sb`.`assessment_id` AS `assessment_id`,'submission_return' AS `feedback_table`,max(`sr1`.`id`) AS `feedback_id`,`sr1`.`owner` AS `student_id`,`sr1`.`review_route` AS `review_route`,`sr1`.`published_at` AS `published_at` from ((`submission_return` `sr1` join (select `submission_return`.`box_id` AS `box_id`,`submission_return`.`owner` AS `owner`,`submission_return`.`review_route` AS `review_route`,max(`submission_return`.`published_at`) AS `max_published_at` from `submission_return` where `submission_return`.`deleted_at` is null and `submission_return`.`published_at` is not null group by `submission_return`.`box_id`,`submission_return`.`owner`,`submission_return`.`review_route`) `t1` on(`t1`.`box_id` = `sr1`.`box_id` and `t1`.`owner` = `sr1`.`owner` and `t1`.`review_route` = `sr1`.`review_route` and `sr1`.`published_at` = `t1`.`max_published_at`)) join `submission_box` `sb` on(`sr1`.`box_id` = `sb`.`id`)) group by `sb`.`assessment_id`,`sr1`.`owner`,`sr1`.`review_route` union select `a`.`assessment_id` AS `assessment_id`,'assess_attempt' AS `feedback_table`,max(`aa1`.`attempt_id`) AS `feedback_id`,`aa1`.`user_id` AS `student_id`,'instructor' AS `review_route`,`aa1`.`reviewed_at` AS `published_at` from ((`assess_attempt` `aa1` join (select `assess_attempt`.`assess_id` AS `assess_id`,`assess_attempt`.`user_id` AS `user_id`,max(`assess_attempt`.`reviewed_at`) AS `max_reviewed_at` from `assess_attempt` where `assess_attempt`.`reviewed_at` is not null and `assess_attempt`.`deleted_at` is null group by `assess_attempt`.`assess_id`,`assess_attempt`.`user_id`) `t1` on(`t1`.`assess_id` = `aa1`.`assess_id` and `t1`.`user_id` = `aa1`.`user_id` and `aa1`.`reviewed_at` = `t1`.`max_reviewed_at`)) join `assess` `a` on(`aa1`.`assess_id` = `a`.`assess_id`)) group by `a`.`assessment_id`,`aa1`.`user_id` union select `t`.`assessment_id` AS `assessment_id`,'task_completion' AS `feedback_table`,max(`tc`.`id`) AS `feedback_id`,`tc`.`student_id` AS `student_id`,'instructor' AS `review_route`,`tc`.`completion_date` AS `published_at` from ((`task_completion` `tc` join (select `task_completion`.`task_id` AS `task_id`,`task_completion`.`student_id` AS `student_id`,max(`task_completion`.`completion_date`) AS `max_completion_date` from `task_completion` where `task_completion`.`completion_status` = 3 and `task_completion`.`deleted_at` is null group by `task_completion`.`task_id`,`task_completion`.`student_id`) `t1` on(`tc`.`task_id` = `t1`.`task_id` and `tc`.`student_id` = `t1`.`student_id` and `tc`.`completion_date` = `t1`.`max_completion_date`)) join `task` `t` on(`t`.`id` = `tc`.`task_id`)) group by `t`.`assessment_id`,`tc`.`student_id` union select `la`.`assessment_id` AS `assessment_id`,'lti_assessment_score' AS `feedback_table`,max(`las`.`id`) AS `feedback_id`,`las`.`student_id` AS `student_id`,'instructor' AS `review_route`,`las`.`created_at` AS `published_at` from ((`lti_assessment_score` `las` join (select `lti_assessment_score`.`lti_assessment_id` AS `lti_assessment_id`,`lti_assessment_score`.`student_id` AS `student_id`,max(`lti_assessment_score`.`created_at`) AS `max_created_at` from `lti_assessment_score` where `lti_assessment_score`.`deleted_at` is null and `lti_assessment_score`.`is_feedback` = 1) `t1` on(`las`.`lti_assessment_id` = `t1`.`lti_assessment_id` and `las`.`student_id` = `t1`.`student_id` and `las`.`created_at` = `t1`.`max_created_at`)) join `lti_assessment` `la` on(`las`.`lti_assessment_id` = `la`.`id`)) group by `la`.`assessment_id`,`las`.`student_id` union select `reporting`.`assessment_id` AS `assessment_id`,'reporting_feedback' AS `feedback_table`,max(`reporting_feedback`.`id`) AS `feedback_id`,`reporting_feedback`.`learner_id` AS `student_id`,'instructor' AS `review_route`,`reporting_feedback`.`published_at` AS `published_at` from ((`reporting_feedback` join (select `reporting_feedback`.`reporting_id` AS `reporting_id`,`reporting_feedback`.`learner_id` AS `student_id`,max(`reporting_feedback`.`published_at`) AS `max_published_at` from `reporting_feedback` where `reporting_feedback`.`deleted_at` is null group by `reporting_feedback`.`reporting_id`,`reporting_feedback`.`learner_id`) `t1` on(`reporting_feedback`.`reporting_id` = `t1`.`reporting_id` and `reporting_feedback`.`learner_id` = `t1`.`student_id` and `reporting_feedback`.`created_at` = `t1`.`max_published_at`)) join `reporting` on(`reporting_feedback`.`reporting_id` = `reporting`.`id`)) group by `reporting`.`assessment_id`,`reporting_feedback`.`learner_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_most_recent_rubric_mark`
--

/*!50001 DROP VIEW IF EXISTS `v_most_recent_rubric_mark`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_most_recent_rubric_mark` AS select `sb`.`assessment_id` AS `assessment_id`,`vr`.`capability_mark_type_id` AS `capability_mark_type_id`,`vr`.`capability_grade_group_id` AS `capability_grade_group_id`,`sr`.`owner` AS `student_id`,`sr`.`marker` AS `marker_id`,`sr`.`id` AS `feedback_id`,`sr`.`published_at` AS `feedback_date`,`sr`.`updated_at` AS `updated_date`,`sr`.`review_route` AS `feedback_type`,`vr`.`capability_id` AS `capability_id`,`vr`.`capability_name` AS `capability_name`,`vr`.`capability_max_value` AS `capability_max_value`,if(`rcm`.`valid`,`rcm`.`value`,NULL) AS `capability_mark`,if(`rcm`.`valid`,`rcm`.`human_readable_value`,NULL) AS `capability_human_readable_mark`,`vr`.`indicator_id` AS `indicator_id`,`vr`.`indicator_name` AS `indicator_name`,`vr`.`indicator_max_value` AS `indicator_max_value`,if(`rim`.`valid`,`rim`.`value`,NULL) AS `indicator_mark`,`rim`.`descriptor_id` AS `descriptor_id`,`rim`.`descriptor_name` AS `descriptor_name` from ((((`v_rubric` `vr` join `submission_box` `sb` on(`vr`.`assessment_id` = `sb`.`assessment_id`)) join `submission_return` `sr` on(`sb`.`id` = `sr`.`box_id`)) join `rubric_capability_mark` `rcm` on(`rcm`.`return_id` = `sr`.`id` and `rcm`.`rubric_capability_id` = `vr`.`capability_id`)) left join `v_rubric_indicator_mark_extend` `rim` on(`rim`.`rubric_indicator_id` = `vr`.`indicator_id` and `rcm`.`id` = `rim`.`rubric_capability_mark_id`)) where `sr`.`deleted_at` is null and (`rcm`.`valid` is true or `rim`.`valid` is true) order by `sb`.`assessment_id`,`sr`.`owner`,`sr`.`updated_at`,`vr`.`capability_sequence`,`vr`.`capability_id`,`vr`.`indicator_sequence`,`vr`.`indicator_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_rubric`
--

/*!50001 DROP VIEW IF EXISTS `v_rubric`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rubric` AS select `a`.`id` AS `assessment_id`,`a`.`title` AS `assessment_title`,`sb`.`mark_type` AS `assessment_mark_type`,`sb`.`max_mark` AS `assessment_max_mark`,`sb`.`submission_grade_group_id` AS `assessment_grade_group_id`,`a`.`weight` AS `assessment_weight`,`rc`.`id` AS `capability_id`,`rc`.`name` AS `capability_name`,`rcu`.`max_value` AS `capability_max_value`,`rcu`.`sequence` AS `capability_sequence`,`sb`.`rubric_capability_mark_type_id` AS `capability_mark_type_id`,`sb`.`rubric_capability_grade_group_id` AS `capability_grade_group_id`,`ri`.`id` AS `indicator_id`,`ri`.`name` AS `indicator_name`,`riu`.`sequence` AS `indicator_sequence`,`riu`.`max_value` AS `indicator_max_value` from (((((`assessment` `a` join `submission_box` `sb` on(`a`.`id` = `sb`.`assessment_id`)) join `rubric_capability_usage` `rcu` on(`sb`.`id` = `rcu`.`submission_box_id`)) join `rubric_capability` `rc` on(`rcu`.`rubric_capability_id` = `rc`.`id`)) join `rubric_indicator_usage` `riu` on(`sb`.`id` = `riu`.`submission_box_id`)) join `rubric_indicator` `ri` on(`riu`.`rubric_indicator_id` = `ri`.`id` and `ri`.`rubric_capability_id` = `rc`.`id`)) where `a`.`deleted_at` is null and `sb`.`use_rubric` is true and `rc`.`deleted_at` is null and `ri`.`deleted_at` is null order by `a`.`id`,`rcu`.`sequence`,`rc`.`id`,`riu`.`sequence`,`ri`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_rubric_capability_mark`
--

/*!50001 DROP VIEW IF EXISTS `v_rubric_capability_mark`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rubric_capability_mark` AS select distinct `v_rubric_mark`.`assessment_id` AS `assessment_id`,`v_rubric_mark`.`student_id` AS `student_id`,`v_rubric_mark`.`marker_id` AS `marker_id`,`v_rubric_mark`.`feedback_id` AS `feedback_id`,`v_rubric_mark`.`feedback_date` AS `feedback_date`,`v_rubric_mark`.`feedback_type` AS `feedback_type`,`v_rubric_mark`.`capability_id` AS `capability_id`,`v_rubric_mark`.`capability_name` AS `capability_name`,`v_rubric_mark`.`capability_max_value` AS `capability_max_value`,`v_rubric_mark`.`capability_mark` AS `capability_mark` from `v_rubric_mark` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_rubric_indicator_mark`
--

/*!50001 DROP VIEW IF EXISTS `v_rubric_indicator_mark`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rubric_indicator_mark` AS select distinct `v_rubric_mark`.`assessment_id` AS `assessment_id`,`v_rubric_mark`.`student_id` AS `student_id`,`v_rubric_mark`.`marker_id` AS `marker_id`,`v_rubric_mark`.`feedback_id` AS `feedback_id`,`v_rubric_mark`.`feedback_date` AS `feedback_date`,`v_rubric_mark`.`feedback_type` AS `feedback_type`,`v_rubric_mark`.`capability_id` AS `capability_id`,`v_rubric_mark`.`indicator_id` AS `indicator_id`,`v_rubric_mark`.`indicator_name` AS `indicator_name`,`v_rubric_mark`.`indicator_max_value` AS `indicator_max_value`,`v_rubric_mark`.`indicator_mark` AS `indicator_mark`,`v_rubric_mark`.`descriptor_name` AS `descriptor_name` from `v_rubric_mark` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_rubric_indicator_mark_extend`
--

/*!50001 DROP VIEW IF EXISTS `v_rubric_indicator_mark_extend`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rubric_indicator_mark_extend` AS select `rubric_indicator_mark`.`rubric_capability_mark_id` AS `rubric_capability_mark_id`,`rubric_indicator_mark`.`descriptor_id` AS `descriptor_id`,`rd`.`rubric_indicator_id` AS `rubric_indicator_id`,`rubric_indicator_mark`.`value` AS `value`,`rubric_indicator_mark`.`valid` AS `valid`,if(`rubric_indicator_mark`.`valid`,`rd`.`description`,NULL) AS `descriptor_name` from (`rubric_indicator_mark` left join `rubric_descriptor` `rd` on(`rd`.`id` = `rubric_indicator_mark`.`descriptor_id`)) where `rubric_indicator_mark`.`descriptor_id` is not null and `rubric_indicator_mark`.`valid` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_rubric_mark`
--

/*!50001 DROP VIEW IF EXISTS `v_rubric_mark`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`schoolboxdocker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rubric_mark` AS select `sb`.`assessment_id` AS `assessment_id`,`vr`.`capability_mark_type_id` AS `capability_mark_type_id`,`vr`.`capability_grade_group_id` AS `capability_grade_group_id`,`sr`.`owner` AS `student_id`,`sr`.`marker` AS `marker_id`,`sr`.`id` AS `feedback_id`,`sr`.`published_at` AS `feedback_date`,`sr`.`review_route` AS `feedback_type`,`vr`.`capability_id` AS `capability_id`,`vr`.`capability_name` AS `capability_name`,`vr`.`capability_max_value` AS `capability_max_value`,if(`rcm`.`valid`,`rcm`.`value`,NULL) AS `capability_mark`,if(`rcm`.`valid`,`rcm`.`human_readable_value`,NULL) AS `capability_human_readable_mark`,`vr`.`indicator_id` AS `indicator_id`,`vr`.`indicator_name` AS `indicator_name`,`vr`.`indicator_max_value` AS `indicator_max_value`,if(`rim`.`valid`,`rim`.`value`,NULL) AS `indicator_mark`,`rim`.`descriptor_id` AS `descriptor_id`,`rim`.`descriptor_name` AS `descriptor_name` from ((((`v_rubric` `vr` join `submission_box` `sb` on(`vr`.`assessment_id` = `sb`.`assessment_id`)) join `submission_return` `sr` on(`sb`.`id` = `sr`.`box_id`)) join `rubric_capability_mark` `rcm` on(`rcm`.`return_id` = `sr`.`id` and `rcm`.`rubric_capability_id` = `vr`.`capability_id`)) left join `v_rubric_indicator_mark_extend` `rim` on(`rim`.`rubric_indicator_id` = `vr`.`indicator_id` and `rcm`.`id` = `rim`.`rubric_capability_mark_id`)) where `sr`.`deleted_at` is null and `sr`.`published_at` is not null and (`rcm`.`valid` is true or `rim`.`valid` is true) order by `sb`.`assessment_id`,`sr`.`owner`,`sr`.`published_at`,`vr`.`capability_sequence`,`vr`.`capability_id`,`vr`.`indicator_sequence`,`vr`.`indicator_id` */;
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
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-04-15  4:21:34
