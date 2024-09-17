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
(7,'student','positionTitle','Position Title',5,1,null),
(8,'student','email','Email',6,1,null),
(9,'student','externalId','Student ID',7,1,null),
(10,'student','StudentBirthDate','Date of Birth',8,1,null),
(11,'student','formTeacher','Class Teacher',9,1,null),
(12,'student','StudentYearLevel','Year Level',10,1,'yearLevel'),
(13,'student','tutorTeacher','Tutor',11,1,'tutor'),
(14,'student','coordTeacher','Year Level Coordinator',12,1,'yearLevelCoordinator'),
(15,'student','StudentHouseDescription','House',13,1,null),
(16,'student','StudentTutorDescription','Tutor Group',14,1,'tutorGroup'),
(17,'student','hohTeacher','Housemaster',15,1,'headOfHouse'),
(18,'student','StudentForm','Class',16,1,null),
(19,'student','boarderType','Student Type',17,1,null),
(20,'student','StudentStudiesCode','Studies Code',18,1,'studies_code'),
(21,'student','pastoralCounts','Pastoral Flags',19,1,null),
(22,'parent','positionTitle','Position Title',1,1,null),
(23,'parent','email','Email',2,1,null),
(24,'parent','HomePhone','Home Phone',3,1,null),
(25,'parent','MobilePhone','Mobile Phone',4,1,null),
(26,'parent','WorkPhone','Work Phone',5,1,null),
(27,'parent','role','Role',6,1,null),
(28,'parent','spouse','Partner',7,1,null),
(29,'staff','positionTitle','Position Title',1,1,null),
(30,'staff','email','Email',2,1,null),
(31,'staff','StaffDepartment','Department',3,1,null),
(32,'staff','StaffCategoryDescription','Type',4,1,null),
(33,'staff','StaffBuilding','Building',5,1,null),
(34,'staff','StaffRoom','Room',6,1,null),
(35,'staff','StaffExtension','Extension',7,1,null),
(36,'staff','StaffCampusDescription','Campus',8,1,null);
