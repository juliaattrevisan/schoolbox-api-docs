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

CREATE TABLE `course` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_course_folder_id_idx` (`folder_id` ASC),
  CONSTRAINT `fk_course_folder_id`
    FOREIGN KEY (`folder_id`)
    REFERENCES `folder` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

CREATE TABLE `course_unit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `component_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `component_id_UNIQUE` (`component_id` ASC),
  INDEX `fk_course_unit_course_id_idx` (`course_id` ASC),
  CONSTRAINT `fk_course_unit_course_id`
    FOREIGN KEY (`course_id`)
    REFERENCES `course` (`folder_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);

ALTER TABLE `course_unit_instance`
CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;

CREATE TABLE `project` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(250) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;


CREATE TABLE `assessment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(250) NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `publish_date` DATETIME NULL,
  `due_date` DATETIME NULL,
  `sequence` TINYINT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_asessment_project_id_idx` (`project_id` ASC),
CONSTRAINT `fk_asessment_project_id`
  FOREIGN KEY (`project_id`)
  REFERENCES `project` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;


CREATE TABLE `project_folder` (
  `project_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NOT NULL,
  INDEX `fk_project_folder_folder_id_idx` (`folder_id` ASC),
  INDEX `fk_project_folder_project_id_idx` (`project_id` ASC),
  CONSTRAINT `fk_project_folder_folder_id`
    FOREIGN KEY (`folder_id`)
    REFERENCES `folder` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_project_folder_project_id`
    FOREIGN KEY (`project_id`)
    REFERENCES `project` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


CREATE TABLE `project_course_unit_instance` (
  `project_id` INT UNSIGNED NOT NULL,
  `course_unit_instance_id` INT UNSIGNED NOT NULL,
  INDEX `fk_project_course_unit_instance_project_id_idx` (`project_id` ASC),
  INDEX `fk_project_course_unit_instance_course_unit_instance_id_idx` (`course_unit_instance_id` ASC),
  CONSTRAINT `fk_project_course_unit_instance_project_id`
    FOREIGN KEY (`project_id`)
    REFERENCES `project` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
 CONSTRAINT `fk_project_course_unit_instance_course_unit_instance_id`
   FOREIGN KEY (`course_unit_instance_id`)
   REFERENCES `course_unit_instance` (`id`)
   ON DELETE NO ACTION
   ON UPDATE NO ACTION)
ENGINE = InnoDB;


CREATE TABLE `project_course_unit` (
  `project_id` INT UNSIGNED NOT NULL,
  `course_unit_id` INT UNSIGNED NOT NULL,
  INDEX `fk_project_course_unit_project_id_idx` (`project_id` ASC),
  INDEX `fk_project_course_unit_course_unit_id_idx` (`course_unit_id` ASC),
  CONSTRAINT `fk_project_course_unit_project_id`
    FOREIGN KEY (`project_id`)
    REFERENCES `project` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_project_course_unit_course_unit_id`
    FOREIGN KEY (`course_unit_id`)
    REFERENCES `course_unit` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


ALTER TABLE `assess`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `category_id`;

ALTER TABLE `task_instance`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `id`;

ALTER TABLE `submission_box`
ADD COLUMN `assessment_id` INT UNSIGNED NOT NULL AFTER `id`;
