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
    `roletype` ENUM('staff', 'student', 'parent') NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `profile_field_profile_field_roletype_visibility` (`profile_field_id` ASC),
    INDEX `fk_profile_field_roletype_visibility_idx` (`roletype` ASC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Preload some fields

INSERT INTO `profile_field` VALUES
(1,'student','StudentSpecialNeedsFlag','Special Needs',1,1,'specialNeeds'),
(2,'student','EnglishSecondFlag','EAL/ESL',2,1,null),
(3,'student','StudentMedicalAlert','Medical Note',3,1,null),
(4,'student','AlertMessages','Alerts',4,1,null),
(5,'student','countIndividualEducationPlans','Individual Education Plan',5,1,null),
(6,'student','Comment','Comment',6,1,null),
(7,'student','positionTitle','Position Title',7,1,null),
(8,'student','pronoun','Pronoun',8,1,null),
(9,'student','email','Email',9,1,null),
(10,'student','externalId','Student ID',10,1,null),
(11,'student','role','Role',11,1,null),
(12,'student','StudentBirthDate','Date of Birth',12,1,null),
(13,'student','formTeacher','Class Teacher',13,1,null),
(14,'student','StudentYearLevel','Year Level',14,1,'yearLevel'),
(15,'student','tutorTeacher','Tutor',15,1,'tutor'),
(16,'student','coordTeacher','Year Level Coordinator',16,1,'yearLevelCoordinator'),
(17,'student','StudentHouseDescription','House',17,1,null),
(18,'student','StudentTutorDescription','Tutor Group',18,1,'tutorGroup'),
(19,'student','hohTeacher','Housemaster',19,1,'headOfHouse'),
(20,'student','residentialHouse','Residential House',20,1,null),
(21,'student','campusCollection','Campus',21,1,null),
(22,'student','StudentForm','Class',22,1,null),
(23,'student','boarderType','Student Type',23,1,null),
(24,'student','StudentEnrolmentDate','Enrolment Date',24,1,null),
(25,'student','StudentStudiesCode','Studies Code',25,1,'studies_code'),
(26,'student','pastoralCounts','Pastoral Flags',26,1,null),
(27,'parent','externalId','External ID',1,1,null),
(28,'parent','positionTitle','Position Title',2,1,null),
(29,'parent','pronoun','Pronoun',3,1,null),
(30,'parent','email','Email',4,1,null),
(31,'parent','campusCollection','Campus',5,1,null),
(32,'parent','role','Role',6,1,null),
(33,'parent','HomePhone','Home Phone',7,1,null),
(34,'parent','MobilePhone','Mobile Phone',8,1,null),
(35,'parent','WorkPhone','Work Phone',9,1,null),
(36,'parent','SpouseEmail','Partner Email',10,1,null),
(37,'parent','SpouseMobilePhone','Partner Mobile Phone',11,1,null),
(38,'parent','spouseName','Partner Name',12,1,null),
(39,'staff','externalId','External ID',1,1,null),
(40,'staff','positionTitle','Position Title',2,1,null),
(41,'staff','pronoun','Pronoun',3,1,null),
(42,'staff','email','Email',4,1,null),
(43,'staff','role','Role',5,1,null),
(44,'staff','StaffDepartment','Department',6,1,null),
(45,'staff','StaffCategoryDescription','Type',7,1,null),
(46,'staff','StaffBuilding','Building',8,1,null),
(47,'staff','StaffRoom','Room',9,1,null),
(48,'staff','StaffExtension','Extension',10,1,null),
(49,'staff','StaffCampusDescription','Campus',11,1,null),
(50,'staff','schoolHouse','House',12,1,null),
(51,'staff','residentialHouse','Residential House',13,1,null);
