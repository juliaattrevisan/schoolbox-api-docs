ALTER TABLE `category` RENAME TO  `folder` ;
ALTER TABLE `folder` DROP INDEX `fulltext` ;
ALTER TABLE `folder` ROW_FORMAT = Default ;
ALTER TABLE `folder` ENGINE = InnoDB;
ALTER TABLE `categoryCode` RENAME TO  `folder_code` ;
ALTER TABLE `folder_code` ENGINE = InnoDB;
ALTER TABLE `folder_code`
CHANGE COLUMN `categoryID` `folder_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `folder_code`
ADD CONSTRAINT `fk_folder_id`
  FOREIGN KEY (`folder_id`)
  REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

CREATE TABLE `course_specification` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `folder_id_UNIQUE` (`folder_id` ASC),
  CONSTRAINT `fk_course_specification_folder_id`
    FOREIGN KEY (`folder_id`)
    REFERENCES `folder` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

CREATE TABLE `course_unit_specification` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id` INT UNSIGNED NOT NULL,
  `course_specification_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `folder_id_UNIQUE` (`folder_id` ASC),
  INDEX `fk_course_unit_course_specification_id_idx` (`course_specification_id` ASC),
  CONSTRAINT `fk_course_unit_course_specification_id`
    FOREIGN KEY (`course_specification_id`)
    REFERENCES `course_specification` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

CREATE TABLE `course_implementation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id` INT UNSIGNED NOT NULL,
  `course_specification_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `folder_id_UNIQUE` (`folder_id` ASC),
  INDEX `fk_course_implementation_course_specification_id_idx` (`course_specification_id` ASC),
  CONSTRAINT `fk_course_implementation_course_specification_id`
  FOREIGN KEY (`course_specification_id`)
  REFERENCES `course_specification` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

ALTER TABLE `course_unit_instance`
ADD COLUMN `updated_at` DATETIME NULL AFTER `created_at`,
CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;

UPDATE `course_unit_instance` SET `updated_at` = `created_at`;

-- Add FK relation to this table once that table is populated
ALTER TABLE `course_unit_instance`
  ADD COLUMN `course_implementation_id` INT(11) NOT NULL AFTER `id`,
  ADD COLUMN `course_unit_specification_id` INT(11) NOT NULL AFTER `course_implementation_id`;

ALTER TABLE `course_unit_instance` RENAME TO `course_unit_implementation`;

ALTER TABLE `course_unit_implementation`
  DROP INDEX `course_unit_instance_component_id` ,
  ADD INDEX `course_unit_implementation_component_id` (`component_id` ASC),
  DROP INDEX `course_unit_instance_unit_category_id` ,
  ADD INDEX `course_unit_implementation_unit_category_id` (`unit_category_id` ASC),
  DROP INDEX `course_unit_instance_open_date` ,
  ADD INDEX `course_unit_implementation_open_date` (`open_date` ASC),
  DROP INDEX `course_unit_instance_due_date` ,
  ADD INDEX `course_unit_implementation_due_date` (`due_date` ASC),
  DROP INDEX `course_unit_instance_deleted_at` ,
  ADD INDEX `course_unit_implementation_deleted_at` (`deleted_at` ASC),
  DROP INDEX `course_unit_instance_category_id` ,
  ADD INDEX `course_unit_implementation_category_id` (`category_id` ASC);



-- Assessments and their relations

CREATE TABLE `assessment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(250) NOT NULL,
  `description` TEXT NULL,
  `publish_date` DATETIME NULL,
  `open_date` DATETIME NULL,
  `due_date` DATETIME NULL,
  `weight` INT(11) NOT NULL DEFAULT 0,
  `sequence` TINYINT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;


CREATE TABLE `assessment_folder` (
  `assessment_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NOT NULL,
  INDEX `fk_assessment_folder_folder_id_idx` (`folder_id` ASC),
  INDEX `fk_assessment_folder_assessment_id_idx` (`assessment_id` ASC),
  CONSTRAINT `fk_assessment_folder_folder_id`
    FOREIGN KEY (`folder_id`)
    REFERENCES `folder` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_assessment_folder_assessment_id`
    FOREIGN KEY (`assessment_id`)
    REFERENCES `assessment` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


CREATE TABLE `assessment_course_unit_implementation` (
  `assessment_id` INT UNSIGNED NOT NULL,
  `course_unit_implementation_id` INT UNSIGNED NOT NULL,
  INDEX `fk_assessment_course_unit_implementation_assessment_id_idx` (`assessment_id` ASC),
  INDEX `fk_assessment_course_unit_implementation_cui_id_idx` (`course_unit_implementation_id` ASC),
  CONSTRAINT `fk_assessment_course_unit_implementation_assessment_id`
    FOREIGN KEY (`assessment_id`)
    REFERENCES `assessment` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
 CONSTRAINT `fk_assessment_course_unit_implementation_cui_id`
   FOREIGN KEY (`course_unit_implementation_id`)
   REFERENCES `course_unit_implementation` (`id`)
   ON DELETE NO ACTION
   ON UPDATE NO ACTION)
ENGINE = InnoDB;


CREATE TABLE `assessment_course_unit_specification` (
  `assessment_id` INT UNSIGNED NOT NULL,
  `course_unit_specification_id` INT UNSIGNED NOT NULL,
  INDEX `fk_assessment_course_unit_specification_assessment_id_idx` (`assessment_id` ASC),
  INDEX `fk_assessment_course_unit_specification_cus_id_idx` (`course_unit_specification_id` ASC),
  CONSTRAINT `fk_assessment_course_unit_specification_assessment_id`
    FOREIGN KEY (`assessment_id`)
    REFERENCES `assessment` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_assessment_course_unit_specification_cus_id`
    FOREIGN KEY (`course_unit_specification_id`)
    REFERENCES `course_unit_specification` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- New and existing assessment types
-- TODO once conversions are completed, add FK constraints to these tables

ALTER TABLE `assess`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `category_id`;

ALTER TABLE `task`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `id`;

ALTER TABLE `task_instance`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `id`;

ALTER TABLE `submission_box`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `id`;

-- New component configuration type for a course outline component's Imported Courses
INSERT INTO `component_config_definition` (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id FROM component_definition WHERE class = 'Component_Homepage_CourseOutlineController' LIMIT 1),
  'importedCourse',
  '',
  'Imported Course',
  'The Imported Course that is displayed in this component'
);

-- The new project assessment type
CREATE TABLE `project` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(250) NOT NULL,
  `description` TEXT NULL,
  `weight` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `fk_project_assessment_id_idx` (`assessment_id` ASC),
  CONSTRAINT `fk_project_course_unit_project_id`
  FOREIGN KEY (`assessment_id`)
  REFERENCES `assessment` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
  ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8
  COLLATE = utf8_general_ci;
