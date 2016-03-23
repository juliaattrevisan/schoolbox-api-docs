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

-- Delete any previous created pastoral notification stuff

DELETE FROM msg_route_perm WHERE msg_source_id in (SELECT `id` FROM msg_source WHERE namespace in (SELECT `id` FROM msg_namespace WHERE slug = 'pastoral'));
DELETE FROM msg_source WHERE namespace in (SELECT `id` FROM msg_namespace WHERE slug = 'pastoral');
DELETE FROM msg_namespace WHERE slug = 'pastoral';
DROP PROCEDURE IF EXISTS create_route_perms;

-- Create a stored procedure to create the message route permissions for every role of a specific type (e.g. parent/staff)

CREATE PROCEDURE create_route_perms(IN source INT, IN grouptype VARCHAR(255))
  BEGIN

    DECLARE role_id INT DEFAULT 0;
    DECLARE cursor_finished INT DEFAULT 0;
    DECLARE cursor_roles CURSOR FOR SELECT `id` FROM `groups` WHERE `group_type` = grouptype;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET cursor_finished = 1;

    OPEN cursor_roles;

    role_loop: LOOP

      FETCH cursor_roles INTO role_id;

      IF cursor_finished = 1 THEN
        LEAVE role_loop;
      END IF;

      INSERT INTO schoolbox.msg_route_perm (msg_source_id, role_id, min_level, max_level, default_level, mask, role_default)
      VALUES (source, role_id, null, null, null, 'MU,DI,IN,MO,EM', 'IN');

    END LOOP;

  END;

-- Create notification namespace and get its ID

INSERT INTO msg_namespace (slug, label) VALUES ('pastoral', 'Pastoral Care');
SET @namespace = LAST_INSERT_ID();

-- Parent create message source

INSERT INTO schoolbox.msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'created', 'parent', 'a new pastoral record has been created.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'parent');

-- Parent update message source

INSERT INTO schoolbox.msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'updated', 'parent', 'a new pastoral record has been updated.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'parent');

-- Parent create message source

INSERT INTO schoolbox.msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'created', 'staff', 'a new pastoral record has been created.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'staff');

-- Parent update message source

INSERT INTO schoolbox.msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'updated', 'staff', 'a new pastoral record has been updated.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'staff');

-- Get rid of the stored procedure after everything created as it really serves no purpose anymore

DROP PROCEDURE IF EXISTS create_route_perms;
