-- Add schema for knowing which fields are available for each role type's profile screen

CREATE TABLE `profile_field` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `roletype` ENUM('staff', 'student', 'parent') NOT NULL,
    `field` VARCHAR(255) NOT NULL,
    `display_as` VARCHAR(255) NOT NULL,
    `sequence` INT UNSIGNED NULL,
    `enabled` TINYINT NOT NULL,
PRIMARY KEY (`id`));
