-- Add schema for knowing which fields are available for each role type's profile screen
-- note that Term is nullable to support custom works using a field that has no terminology rows
-- and also to protect ourselves from missing terminology

CREATE TABLE `profile_field` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `roletype` enum('staff','student','parent') NOT NULL,
    `field` varchar(255) NOT NULL,
    `display_as` varchar(255) NOT NULL,
    `sequence` int(10) unsigned NOT NULL,
    `enabled` tinyint(4) NOT NULL,
    `term` varchar(255) NULL,
    `icon` varchar(255) NULL,
    `css` varchar(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roletype_field` (`roletype`,`field`),
    KEY `roletype` (`roletype`),
    KEY `field` (`field`),
    KEY `sequence` (`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This records which Campuses were selected, for each field,
-- to determine which profile pages they "Appear On" (synonymous with "Availability")

CREATE TABLE `profile_field_campus_availability` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profile_field_id` INT UNSIGNED NOT NULL,
    `campus_id` TINYINT(4) UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `profile_field_profile_field_campus_availability` (`profile_field_id` ASC),
    INDEX `fk_profile_field_campus_availability_campus_id_idx` (`campus_id` ASC),
    CONSTRAINT `fk_profile_field_campus_availability_campus_id`
    FOREIGN KEY (`campus_id`)
    REFERENCES `luCampus` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This records when Roletypes were selected, for each field,
-- to determine which viewer Role Types can see a field

CREATE TABLE `profile_field_roletype_visibility` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profile_field_id` INT UNSIGNED NOT NULL,
    `roletype` ENUM('staff', 'student', 'parent', 'self') NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `profile_field_profile_field_roletype_visibility` (`profile_field_id` ASC),
    INDEX `fk_profile_field_roletype_visibility_idx` (`roletype` ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Preload some fields

INSERT INTO `profile_field` VALUES
(1,'student','portrait','Portrait Image',0,1,NULL,NULL,NULL),
(2,'student','EnglishSecondFlag','EAL/ESL',2,1,NULL,'icon-eng-second-lang','highlight'),
(3,'student','StudentMedicalAlert','Medical Note',3,1,NULL,'icon-alert','alert'),
(4,'student','ParentsSeparatedFlag','Parents Separated',4,1,NULL,'icon-info','highlight'),
(5,'student','AlertMessages','Alerts',5,1,NULL,'icon-alert','alert'),
(6,'student','countIndividualEducationPlans','Individual Education Plan',6,1,NULL,'icon-curriculum','highlight'),
(7,'student','Comment','Comment',7,1,NULL,'icon-info','highlight'),
(8,'student','positionTitle','Position Title',8,1,NULL,NULL,NULL),
(9,'student','pronoun','Pronoun',9,1,NULL,NULL,NULL),
(10,'student','email','Email',10,1,NULL,NULL,NULL),
(11,'student','externalId','Student ID',11,1,NULL,NULL,NULL),
(12,'student','role','Role',12,1,NULL,NULL,NULL),
(13,'student','StudentBirthDate','Date of Birth',13,1,NULL,NULL,NULL),
(14,'student','formTeacher','Class Teacher',14,1,NULL,NULL,NULL),
(15,'student','StudentYearLevel','Year Level',15,1,'yearLevel',NULL,NULL),
(16,'student','tutorTeacher','Tutor',16,1,'tutor',NULL,NULL),
(17,'student','coordTeacher','Year Level Coordinator',17,1,'yearLevelCoordinator',NULL,NULL),
(18,'student','StudentHouseDescription','House',18,1,NULL,NULL,NULL),
(19,'student','StudentTutorDescription','Tutor Group',19,1,'tutorGroup',NULL,NULL),
(20,'student','hohTeacher','Housemaster',20,1,'headOfHouse',NULL,NULL),
(21,'student','residentialHouse','Residential House',21,1,NULL,NULL,NULL),
(22,'student','campusCollection','Campus',22,1,NULL,NULL,NULL),
(23,'student','StudentForm','Class',23,1,NULL,NULL,NULL),
(24,'student','boarderType','Student Type',24,1,NULL,NULL,NULL),
(25,'student','StudentGovernmentStudentNumber','Govt. Student Number',25,1,NULL,NULL,NULL),
(26,'student','StudentStudiesCode','Studies Code',26,1,'studies_code',NULL,NULL),
(27,'student','pastoralCounts','Pastoral Flags',27,1,NULL,NULL,NULL),
(28,'parent','portrait','Portrait Image',0,1,NULL,NULL,NULL),
(29,'parent','externalId','External ID',1,1,NULL,NULL,NULL),
(30,'parent','positionTitle','Position Title',2,1,NULL,NULL,NULL),
(31,'parent','pronoun','Pronoun',3,1,NULL,NULL,NULL),
(32,'parent','email','Email',4,1,NULL,NULL,NULL),
(33,'parent','campusCollection','Campus',5,1,NULL,NULL,NULL),
(34,'parent','role','Role',6,1,NULL,NULL,NULL),
(35,'parent','HomePhone','Home Phone',7,1,NULL,NULL,NULL),
(36,'parent','MobilePhone','Mobile Phone',8,1,NULL,NULL,NULL),
(37,'parent','WorkPhone','Work Phone',9,1,NULL,NULL,NULL),
(38,'parent','SpouseEmail','Partner Email',10,1,NULL,NULL,NULL),
(39,'parent','SpouseMobilePhone','Partner Mobile Phone',11,1,NULL,NULL,NULL),
(40,'parent','spouseName','Partner Name',12,1,NULL,NULL,NULL),
(41,'staff','portrait','Portrait Image',0,1,NULL,NULL,NULL),
(42,'staff','externalId','External ID',1,1,NULL,NULL,NULL),
(43,'staff','SchoolStaffCode','Staff Code',14,1,NULL,NULL,NULL),
(44,'staff','positionTitle','Position Title',2,1,NULL,NULL,NULL),
(45,'staff','pronoun','Pronoun',3,1,NULL,NULL,NULL),
(46,'staff','email','Email',4,1,NULL,NULL,NULL),
(47,'staff','role','Role',5,1,NULL,NULL,NULL),
(48,'staff','StaffDepartment','Department',6,1,NULL,NULL,NULL),
(49,'staff','StaffCategoryDescription','Type',7,1,NULL,NULL,NULL),
(50,'staff','StaffBuilding','Building',8,1,NULL,NULL,NULL),
(51,'staff','StaffRoom','Room',9,1,NULL,NULL,NULL),
(52,'staff','StaffExtension','Extension',10,1,NULL,NULL,NULL),
(53,'staff','StaffCampusDescription','Campus',11,1,NULL,NULL,NULL),
(54,'staff','schoolHouse','House',12,1,NULL,NULL,NULL),
(55,'staff','residentialHouse','Residential House',13,1,NULL,NULL,NULL);
