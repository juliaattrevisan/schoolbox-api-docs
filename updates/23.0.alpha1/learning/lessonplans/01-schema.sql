CREATE TABLE `lesson_plan_template` (
`id` INT NOT NULL AUTO_INCREMENT,
`title` VARCHAR(255) NOT NULL,
`enabled` TINYINT NOT NULL DEFAULT 1,
`created_at` DATETIME NOT NULL,
`created_by` INT UNSIGNED NOT NULL,
`updated_at` DATETIME NOT NULL,
`updated_by` INT UNSIGNED NOT NULL,
`deleted_at` DATETIME NULL,
`deleted_by` INT UNSIGNED NULL,
PRIMARY KEY (`id`),
INDEX `enabled` (`enabled` ASC),
INDEX `creator` (`created_by` ASC),
INDEX `updater` (`updated_by` ASC),
INDEX `deleter` (`deleted_by` ASC),
CONSTRAINT `fk_lesson_plan_template_creator`
    FOREIGN KEY (`created_by`)
        REFERENCES `user` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION,
CONSTRAINT `fk_lesson_plan_template_updater`
    FOREIGN KEY (`updated_by`)
        REFERENCES `user` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION,
CONSTRAINT `fk_lesson_plan_template_deleter`
    FOREIGN KEY (`deleted_by`)
        REFERENCES `user` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION
);

CREATE TABLE `lesson_plan_template_field` (
 `id` INT NOT NULL AUTO_INCREMENT,
 `lesson_plan_template_id` INT NOT NULL,
 `title` VARCHAR(255) NOT NULL,
 `body` TEXT NOT NULL,
 `visibility` ENUM('student', 'staff') NOT NULL,
 `sequence` INT NOT NULL,
 `created_at` DATETIME NOT NULL,
 `updated_at` DATETIME NOT NULL,
 `deleted_at` DATETIME NULL,
 PRIMARY KEY (`id`),
 INDEX `fk_lesson_plan_template_idx` (`lesson_plan_template_id` ASC),
 CONSTRAINT `fk_lesson_plan_template`
     FOREIGN KEY (`lesson_plan_template_id`)
         REFERENCES `lesson_plan_template` (`id`)
         ON DELETE NO ACTION
         ON UPDATE NO ACTION);


CREATE TABLE `lesson_plan` (
`id` INT NOT NULL AUTO_INCREMENT,
`lesson_plan_template_id` INT NOT NULL,
`visibility` ENUM('student', 'staff') NOT NULL,
`created_by` INT NOT NULL,
`updated_by` INT NOT NULL,
`deleted_by` INT NULL DEFAULT NULL,
PRIMARY KEY (`id`),
INDEX `fk_lesson_plan_lesson_plan_template_idx` (`lesson_plan_template_id` ASC),
CONSTRAINT `fk_lesson_plan_lesson_plan_template`
    FOREIGN KEY (`lesson_plan_template_id`)
        REFERENCES `lesson_plan_template` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION);


CREATE TABLE `lesson_plan_field` (
`id` INT NOT NULL AUTO_INCREMENT,
`lesson_plan_template_field_id` INT NOT NULL,
`body` TEXT NULL,
`created_at` DATETIME NOT NULL,
`updated_at` DATETIME NOT NULL,
`deleted_at` DATETIME NULL DEFAULT NULL,
PRIMARY KEY (`id`),
INDEX `fk_lesson_plan_field_lesson_plan_template_field_idx` (`lesson_plan_template_field_id` ASC),
CONSTRAINT `fk_lesson_plan_field_lesson_plan_template_field`
    FOREIGN KEY (`lesson_plan_template_field_id`)
        REFERENCES `lesson_plan_template_field` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION
);
