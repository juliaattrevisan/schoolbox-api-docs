CREATE TABLE `lu_pastoral_type` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NULL COMMENT '',
  `external_code` VARCHAR(50) NULL COMMENT '',
  `external_id` INT NULL DEFAULT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `lu_pastoral_severity` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `name` VARCHAR(100) NULL COMMENT '',
  `order` INT NULL COMMENT '',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE `pastoral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `pastoral_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `severity_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `external_id` int(11) DEFAULT NULL,
  `private` tinyint(4) DEFAULT '0',
  `heading` varchar(255) DEFAULT NULL,
  `body` text,
  `created_at` datetime DEFAULT '0000-00-00 00:00:00',
  `updated_at` datetime DEFAULT '0000-00-00 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_severity` (`severity_id`),
  KEY `fk_type` (`type_id`),
  KEY `fk_student` (`student_id`),
  KEY `fk_pastoral_id` (`pastoral_id`),
  CONSTRAINT `fk_pastoral_severity` FOREIGN KEY (`severity_id`) REFERENCES `lu_pastoral_severity` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pastoral_type` FOREIGN KEY (`type_id`) REFERENCES `lu_pastoral_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `pastoral_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  CONSTRAINT `fk_pastoral_access` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `pastoral_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  CONSTRAINT `fk_pastoral_tag` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pastoral_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `body` text,
  `created_at` datetime DEFAULT '0000-00-00 00:00:00',
  `updated_at` datetime DEFAULT '0000-00-00 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  CONSTRAINT `fk_pastoral_comment` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `pastoral_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pastoral_id` int(11) DEFAULT NULL,
  `hash` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT '0000-00-00 00:00:00',
  `updated_at` datetime DEFAULT '0000-00-00 00:00:00',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pastoral` (`pastoral_id`),
  CONSTRAINT `fk_pastoral_attachment` FOREIGN KEY (`pastoral_id`) REFERENCES `pastoral` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


INSERT INTO `lu_pastoral_type` (`name`) VALUES ('Academic');
INSERT INTO `lu_pastoral_type` (`name`) VALUES ('Attendance');
INSERT INTO `lu_pastoral_type` (`name`) VALUES ('Medical');
INSERT INTO `lu_pastoral_type` (`name`) VALUES ('Bullying');
INSERT INTO `lu_pastoral_type` (`name`) VALUES ('Discipline');

INSERT INTO `lu_pastoral_severity` (`name`, `order`) VALUES ('Low', 1);
INSERT INTO `lu_pastoral_severity` (`name`, `order`) VALUES ('Medium', 2);
INSERT INTO `lu_pastoral_severity` (`name`, `order`) VALUES ('High', 3);
INSERT INTO `lu_pastoral_severity` (`name`, `order`) VALUES ('Critical', 4);

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('external_pastoral', '0', 'External Pastoral Syncronisation', 'Enables pastoral comments to be automatically written back into the Synergetic.', 3, '', 7, 'true', 'false', 'false');
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('pastoral_enabled', '0', 'Enable Pastoral Care Module', 'Is the pastoral care module enabled?', 3, '', 11, 'false', 'false', 'false');

-- ALTER TABLE `user` ADD `pastoral_moderator` TINYINT(1) DEFAULT 0;

INSERT INTO `luServices` (`id`, `name`) VALUES (9, 'Pastoral Moderator');


INSERT INTO schoolbox.error (id, message) VALUES (168, 'You do no have access to the specified pastoral care record.');
INSERT INTO schoolbox.error (id, message) VALUES (169, 'Access to this module is denied.');
INSERT INTO schoolbox.error (id, message) VALUES (170, 'A requested schoolbox user record could not be found.');
INSERT INTO schoolbox.error (id, message) VALUES (171, 'The requetsed pastoral care record could not be found.');