-- Add schema for knowing which fields are available for each role type's profile screen

CREATE TABLE `profile_field` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `roletype` enum('staff','student','parent') NOT NULL,
    `field` varchar(255) NOT NULL,
    `display_as` varchar(255) NOT NULL,
    `sequence` int(10) unsigned NOT NULL,
    `enabled` tinyint(4) NOT NULL,
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
(1,'student','positionTitle','Position Title',1,1),
(2,'student','email','Email',2,1),
(3,'student','externalId','Student ID',3,1),
(4,'student','StudentBirthDate','Date of Birth',4,1),
(5,'student','formTeacher','Class Teacher',5,1),
(6,'student','StudentYearLevel','Year Level',6,1),
(7,'student','tutorTeacher','Tutor',7,1),
(8,'student','coordTeacher','Year Level Coordinator',8,1),
(9,'student','StudentHouseDescription','House',9,1),
(10,'student','StudentTutorDescription','Tutor Group',10,1),
(11,'student','hohTeacher','Housemaster',11,1),
(12,'student','StudentForm','Class',12,1),
(13,'student','boarderType','Student Type',13,1),
(14,'student','StudentStudiesCode','Studies Code',14,1),
(15,'student','pastoralCounts','Pastoral Flags',15,1),
(16,'parent','positionTitle','Position Title',1,1),
(17,'parent','email','Email',2,1),
(18,'parent','HomePhone','Home Phone',3,1),
(19,'parent','MobilePhone','Mobile Phone',4,1),
(20,'parent','WorkPhone','Work Phone',5,1),
(21,'parent','role','Role',6,1),
(22,'parent','spouse','Partner',7,1),
(23,'staff','positionTitle','Position Title',1,1),
(24,'staff','email','Email',2,1),
(25,'staff','StaffDepartment','Department',3,1),
(26,'staff','StaffCategoryDescription','Type',4,1),
(27,'staff','StaffBuilding','Building',5,1),
(28,'staff','StaffRoom','Room',6,1),
(29,'staff','StaffExtension','Extension',7,1),
(30,'staff','StaffCampusDescription','Campus',8,1);
