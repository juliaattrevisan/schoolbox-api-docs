CREATE TABLE menu_top (
  id INT(11) NOT NULL AUTO_INCREMENT,
  sort_order INT,
  title VARCHAR(200),
  `text` VARCHAR(200),
  token VARCHAR(200),
  icon VARCHAR(200),
  url VARCHAR(2048),
  webmail TINYINT(4) DEFAULT 0,
  target VARCHAR(200),
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

CREATE TABLE menu_top_role (
  id INT(11) NOT NULL AUTO_INCREMENT,
  menu_top_id INT,
  role_id INT,
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

CREATE TABLE menu_top_campus (
  id INT(11) NOT NULL AUTO_INCREMENT,
  menu_top_id INT,
  campus_id INT,
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

CREATE TABLE menu_icons (
  name VARCHAR(100),
  title VARCHAR(200),
PRIMARY KEY (`name`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8mb4;

-- These are the top menu items for Schoolbox

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (1, 1, 'Access all your class information', 'Classes', 'CLASSES', 'teacher', '/learning/classes', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (2, 2, 'Access your due work', 'Submissions', 'SUBMISSION', 'due-work', '/submission/dueWork', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (3, 3, 'View your school timetable', 'Timetable', 'TIMETABLE', 'timetable', '/timetable', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (4, 4, 'School calendar events', 'Calendar', 'CALENDAR', 'calendar', '/calendar/week', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (5, 5, 'Current school news', 'News', 'NEWS', 'news', '/news', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at) VALUES  (6, 6, 'Access your messages here', 'Webmail', null, 'email', (SELECT `value` FROM `config` WHERE `name` = 'mail_webmail'), '_blank', 1, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (7, 7, 'Grades', 'Grades', 'GRADE', 'approve', '/submission/allWork', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (8, 8, 'View and build new courses', 'Courses', 'COURSE', 'course', '/course/list', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (9, 9, 'Browse resources and homepage', 'Resources', 'RESOURCES', 'folder', '/resources/', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (10, 10, 'Access your eportfolio area', 'ePortfolio', 'EPORTFOLIO', 'eportfolio', '/eportfolio/[ID]/profile', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (11, 11, 'Access your files', 'My Files', 'MYFILES', 'files', '/my-files/', null, null);
INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (12, 12, 'Access your school network files', 'Drives', 'NETWORK', 'network', '/drives/', null, null);

INSERT INTO menu_icons (name, title) VALUES ('office-365', 'Office 365');
INSERT INTO menu_icons (name, title) VALUES ('google-drive', 'Google Drive');
INSERT INTO menu_icons (name, title) VALUES ('phone', 'Telephone');
INSERT INTO menu_icons (name, title) VALUES ('sport', 'Sport');
INSERT INTO menu_icons (name, title) VALUES ('group', 'People');
INSERT INTO menu_icons (name, title) VALUES ('user', 'Person');
INSERT INTO menu_icons (name, title) VALUES ('help', 'Help');
INSERT INTO menu_icons (name, title) VALUES ('showcase', 'Trophy');
INSERT INTO menu_icons (name, title) VALUES ('locked', 'Padlock');
INSERT INTO menu_icons (name, title) VALUES ('venue', 'Location');
INSERT INTO menu_icons (name, title) VALUES ('rain', 'Rain Cloud');
INSERT INTO menu_icons (name, title) VALUES ('clear', 'Sun');
INSERT INTO menu_icons (name, title) VALUES ('cloudy', 'Cloud');
INSERT INTO menu_icons (name, title) VALUES ('timetable', 'Clock');
INSERT INTO menu_icons (name, title) VALUES ('due-work', 'Tray');
INSERT INTO menu_icons (name, title) VALUES ('teacher', 'Graduation Cap');
INSERT INTO menu_icons (name, title) VALUES ('calendar', 'Calendar');
INSERT INTO menu_icons (name, title) VALUES ('news', 'Newspaper');
INSERT INTO menu_icons (name, title) VALUES ('email', 'Envelope');
INSERT INTO menu_icons (name, title) VALUES ('approve', 'Tick');
INSERT INTO menu_icons (name, title) VALUES ('course', 'Course');
INSERT INTO menu_icons (name, title) VALUES ('folder', 'Folder');
INSERT INTO menu_icons (name, title) VALUES ('eportfolio', 'Briefcase');
INSERT INTO menu_icons (name, title) VALUES ('files', 'Papers');
INSERT INTO menu_icons (name, title) VALUES ('network', 'Network Drive');

INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 1, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 2, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 3, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 4, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 5, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 6, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 7, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 8, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 9, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 10, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 11, id FROM luCampus;
INSERT INTO menu_top_campus(menu_top_id, campus_id) SELECT 12, id FROM luCampus;

INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 1, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 2, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 3, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 4, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 5, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 6, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 7, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 8, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 9, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 10, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 11, id FROM groups;
INSERT INTO menu_top_role(menu_top_id, role_id) SELECT 12, id FROM groups;

SET FOREIGN_KEY_CHECKS  = 0;

ALTER TABLE menu_top_campus ADD CONSTRAINT menu_top_campus_menu_top_id_fk FOREIGN KEY (menu_top_id) REFERENCES menu_top (id);
ALTER TABLE menu_top_role ADD CONSTRAINT menu_top_role_menu_top_id_fk FOREIGN KEY (menu_top_id) REFERENCES menu_top (id);

SET FOREIGN_KEY_CHECKS  = 1;

-- School specific customisations

-- Shore school staff/student webmail

UPDATE menu_top SET url = 'https://cassandra.student.shore.nsw.edu.au/exchange/'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('shore') AND id = 6;

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at)
  SELECT 13, 6, 'Access your messages here', 'Webmail', null, 'email', 'https://owa.shore.nsw.edu.au/owa', '_blank', 1, null
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'shore';

DELETE FROM menu_top_role WHERE menu_top_id = 6 AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'shore';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 6, id FROM groups WHERE group_type = 'student' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'shore';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 13, id FROM groups WHERE group_type = 'staff' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'shore';

-- Moriah school staff/student webmail

UPDATE menu_top SET url = 'http://www.google.com/a/student.moriah.nsw.edu.au/'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('moriah') AND id = 6;

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at)
  SELECT 13, 6, 'Access your messages here', 'Webmail', null, 'email', 'https://mail.moriah.nsw.edu.au/owa', '_blank', 1, null
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'moriah';

DELETE FROM menu_top_role WHERE menu_top_id = 6 AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'moriah';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 6, id FROM groups WHERE group_type = 'student' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'moriah';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 13, id FROM groups WHERE group_type = 'staff' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'moriah';

-- Scotch Oakburn school staff/student webmail

UPDATE menu_top SET url = 'https://www.outlook.com/my.soc.tas.edu.au'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('oakburn') AND id = 6;

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at)
  SELECT 13, 6, 'Access your messages here', 'Webmail', null, 'email', 'https://mail.soc.tas.edu.au/owa/', '_blank', 1, null
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'oakburn';

DELETE FROM menu_top_role WHERE menu_top_id = 6 AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'oakburn';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 6, id FROM groups WHERE group_type = 'student' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'oakburn';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 13, id FROM groups WHERE group_type = 'staff' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'oakburn';

-- TIGS school staff/student webmail

UPDATE menu_top SET url = 'https://mail.google.com/a/tigs.com.au'
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') IN ('tigs') AND id = 6;

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at)
  SELECT 13, 6, 'Access your messages here', 'Webmail', null, 'email', 'https://webmail.tigs.nsw.edu.au/owa', '_blank', 1, null
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'tigs';

DELETE FROM menu_top_role WHERE menu_top_id = 6 AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'tigs';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 6, id FROM groups WHERE group_type = 'student' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'tigs';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 13, id FROM groups WHERE group_type = 'staff' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'tigs';

-- Peninsula school student webmail

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at)
  SELECT 13, 6, 'Access your messages here', 'Webmail', null, 'email', 'https://outlook.com/owa/tps.vic.edu.au', '_blank', 1, null
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'peninsula';

DELETE FROM menu_top_role WHERE menu_top_id = 6 AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'peninsula';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 6, id FROM groups WHERE group_type = 'staff' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'peninsula';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 13, id FROM groups WHERE group_type = 'student' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'peninsula';

-- Demo webmail test

INSERT INTO menu_top (id, sort_order, title, text, token, icon, url, target, webmail, deleted_at)
  SELECT 13, 6, 'Access your messages here', 'Webmail', null, 'email', 'https://outlook.com/owa/tps.vic.edu.au', '_blank', 1, null
WHERE (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'demo';

DELETE FROM menu_top_role WHERE menu_top_id = 6 AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'demo';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 6, id FROM groups WHERE group_type = 'staff' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'demo';
INSERT INTO menu_top_role (menu_top_id, role_id) SELECT 13, id FROM groups WHERE group_type = 'student' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'demo';
