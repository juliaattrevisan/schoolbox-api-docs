CREATE TABLE `schoolbox`.`pastoral` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `owner_id` INT NULL COMMENT '',
  `severity_id` INT NOT NULL COMMENT '',
  `student_id` INT NOT NULL COMMENT '',
  `type_id` INT NULL COMMENT '',
  `external_id` INT NULL COMMENT '',
  `private` TINYINT NULL DEFAULT 0 COMMENT '',
  `heading` VARCHAR(255) NULL COMMENT '',
  `body` TEXT NULL COMMENT '',
  `datetime_posted` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `datetime_modified` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '');

CREATE TABLE `schoolbox`.`pastoral_access` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NOT NULL COMMENT '',
  `role_id` INT NULL COMMENT '',
  `user_id` INT NULL COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '');

CREATE TABLE `schoolbox`.`pastoral_tags` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NOT NULL COMMENT '',
  `name` VARCHAR(255) NULL COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '');

CREATE TABLE `schoolbox`.`pastoral_comment` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NOT NULL COMMENT '',
  `owner_id` INT NULL COMMENT '',
  `body` TEXT NULL COMMENT '',
  `datetime_posted` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `datetime_modified` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '');

CREATE TABLE `schoolbox`.`pastoral_attachment` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NULL COMMENT '',
  `hash` VARCHAR(50) NULL COMMENT '',
  `datetime_posted` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `datetime_modified` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '');

CREATE TABLE `schoolbox`.`luPastoralType` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NULL COMMENT '',
  `external_code` VARCHAR(50) NULL COMMENT '',
  `external_id` INT NULL DEFAULT NULL COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT ''
);

CREATE TABLE `schoolbox`.`luPastoralSeverity` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NULL COMMENT '',
  `order` INT NULL COMMENT '',
  `datetime_deleted` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT ''
);

INSERT INTO `schoolbox`.`luPastoralType` (`name`) VALUES ('Academic');
INSERT INTO `schoolbox`.`luPastoralType` (`name`) VALUES ('Attendance');
INSERT INTO `schoolbox`.`luPastoralType` (`name`) VALUES ('Medical');
INSERT INTO `schoolbox`.`luPastoralType` (`name`) VALUES ('Bullying');

INSERT INTO `schoolbox`.`luPastoralSeverity` (`name`, `order`) VALUES ('Low', 1);
INSERT INTO `schoolbox`.`luPastoralSeverity` (`name`, `order`) VALUES ('Medium', 2);
INSERT INTO `schoolbox`.`luPastoralSeverity` (`name`, `order`) VALUES ('High', 3);
INSERT INTO `schoolbox`.`luPastoralSeverity` (`name`, `order`) VALUES ('Critical', 4);

INSERT INTO schoolbox.config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('external_pastoral', '0', 'External Pastoral Syncronization', 'Enables pastoral comments to be automatically written back into the Synergetic.', 3, '', 7, 'true', 'false', 'false');
INSERT INTO schoolbox.config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('pastoral_enabled', '0', 'Enable Pastoral Care Module', 'Is the pastoral care module enabled?', 3, '', 11, 'false', 'false', 'false');

ALTER TABLE `user` ADD `pastoral_moderator` TINYINT(1) DEFAULT 0;