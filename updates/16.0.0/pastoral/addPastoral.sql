CREATE TABLE `pastoral` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `parent_id` INT NULL COMMENT '',
  `pastoral_id` INT NULL COMMENT '',
  `owner_id` INT NULL COMMENT '',
  `severity_id` INT NOT NULL COMMENT '',
  `student_id` INT NOT NULL COMMENT '',
  `type_id` INT NULL COMMENT '',
  `external_id` INT NULL COMMENT '',
  `private` TINYINT NULL DEFAULT 0 COMMENT '',
  `heading` VARCHAR(255) NULL COMMENT '',
  `body` TEXT NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `updated_at` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `pastoral_access` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NOT NULL COMMENT '',
  `role_id` INT NULL COMMENT '',
  `user_id` INT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `pastoral_tags` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NOT NULL COMMENT '',
  `name` VARCHAR(255) NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `pastoral_comment` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NOT NULL COMMENT '',
  `owner_id` INT NULL COMMENT '',
  `body` TEXT NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `updated_at` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `pastoral_attachment` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `pastoral_id` INT NULL COMMENT '',
  `hash` VARCHAR(50) NULL COMMENT '',
  `created_at` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `updated_at` DATETIME NULL DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`)  COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `luPastoralType` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NULL COMMENT '',
  `external_code` VARCHAR(50) NULL COMMENT '',
  `external_id` INT NULL DEFAULT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `luPastoralSeverity` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NULL COMMENT '',
  `order` INT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

INSERT INTO `luPastoralType` (`name`) VALUES ('Academic');
INSERT INTO `luPastoralType` (`name`) VALUES ('Attendance');
INSERT INTO `luPastoralType` (`name`) VALUES ('Medical');
INSERT INTO `luPastoralType` (`name`) VALUES ('Bullying');
INSERT INTO `luPastoralType` (`name`) VALUES ('Discipline');

INSERT INTO `luPastoralSeverity` (`name`, `order`) VALUES ('Low', 1);
INSERT INTO `luPastoralSeverity` (`name`, `order`) VALUES ('Medium', 2);
INSERT INTO `luPastoralSeverity` (`name`, `order`) VALUES ('High', 3);
INSERT INTO `luPastoralSeverity` (`name`, `order`) VALUES ('Critical', 4);

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('external_pastoral', '0', 'External Pastoral Syncronization', 'Enables pastoral comments to be automatically written back into the Synergetic.', 3, '', 7, 'true', 'false', 'false');
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('pastoral_enabled', '0', 'Enable Pastoral Care Module', 'Is the pastoral care module enabled?', 3, '', 11, 'false', 'false', 'false');

-- ALTER TABLE `user` ADD `pastoral_moderator` TINYINT(1) DEFAULT 0;

INSERT INTO `luServices` (`id`, `name`) VALUES (9, 'Pastoral Moderator');
