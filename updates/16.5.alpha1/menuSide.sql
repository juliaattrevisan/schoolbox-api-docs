-- Create new side menu tables

CREATE TABLE menu_side (
  id INT(11) NOT NULL AUTO_INCREMENT,
  parent_id INT(11),
  sort_order INT,
  title VARCHAR(200),
  url VARCHAR(2048),
  target VARCHAR(200),
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

-- CREATE TABLE menu_side_user_options (
--   id INT(11) NOT NULL AUTO_INCREMENT,
--   user_id INT(11),
--   show_bookmarks INT,
--   show_groups INT,
--   show_subjects INT,
--   PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

CREATE TABLE menu_side_role (
  id INT(11) NOT NULL AUTO_INCREMENT,
  menu_side_id INT,
  role_id INT,
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

CREATE TABLE menu_side_campus (
  id INT(11) NOT NULL AUTO_INCREMENT,
  menu_side_id INT,
  campus_id INT,
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

CREATE TABLE menu_side_section (
  id INT(11) NOT NULL AUTO_INCREMENT,
  section ENUM('menu', 'groups', 'subjects', 'children', 'bookmarks'),
  sort_order INT(11),
  `visible` INT(11),
  `title` VARCHAR(100),
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;


-- Transfer from old tables

INSERT INTO menu_side (id, sort_order, title, url, target, parent_id) SELECT id, sequence, txt, url, target, parent FROM sideMenu;
INSERT INTO menu_side_campus (menu_side_id, campus_id) SELECT id, campus FROM sideMenuCampus;
INSERT INTO menu_side_role (menu_side_id, role_id) SELECT id, gid FROM sideMenuGroup;

-- Cater for existing custom templates

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'menu', 1, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') NOT IN ('cis','diocesan','kds','loreto','peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'subjects', 2, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') NOT IN ('cis','diocesan','kds','loreto','peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'children', 3, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') NOT IN ('cis','diocesan','kds','loreto','peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'groups', 4, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') NOT IN ('cis','diocesan','kds','loreto','peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'bookmarks', 5, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') NOT IN ('cis','diocesan','kds','loreto','peninsula');

-- CIS

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'menu', 1, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('cis');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'subjects', 2, 1, 'My Classes'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('cis');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'children', 3, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('cis');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'groups', 4, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('cis');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'bookmarks', 5, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('cis');

-- Diocesan

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'subjects', 1, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('diocesan');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'menu', 2, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('diocesan');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'children', 3, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('diocesan');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'groups', 4, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('diocesan');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'bookmarks', 5, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('diocesan');

-- KDS

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'menu', 1, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('kds');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'subjects', 2, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('kds');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'children', 3, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('kds');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'groups', 4, 1, 'My Pages'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('kds');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'bookmarks', 5, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('kds');

-- Loreto

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'subjects', 1, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('loreto');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'menu', 2, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('loreto');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'children', 3, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('loreto');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'groups', 4, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('loreto');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'bookmarks', 5, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('loreto');

-- Peninsula

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'subjects', 1, 1, 'My Classes'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'children', 2, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'groups', 3, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'menu', 4, 1, 'My Zenith'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('peninsula');

INSERT INTO menu_side_section (section, sort_order, visible, title) SELECT 'bookmarks', 5, 1, ''
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('peninsula');

SET FOREIGN_KEY_CHECKS  = 0;

ALTER TABLE menu_side_campus ADD CONSTRAINT menu_side_campus_menu_side_id_fk FOREIGN KEY (menu_side_id) REFERENCES menu_side (id);
ALTER TABLE menu_side_role ADD CONSTRAINT menu_side_role_menu_side_id_fk FOREIGN KEY (menu_side_id) REFERENCES menu_side (id);

SET FOREIGN_KEY_CHECKS  = 1;
