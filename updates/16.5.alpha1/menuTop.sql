CREATE TABLE menu_top (
  id INT(11) NOT NULL AUTO_INCREMENT,
  sort_order INT,
  title VARCHAR(200),
  `text` VARCHAR(200),
  token VARCHAR(200),
  icon VARCHAR(200),
  url VARCHAR(1000),
  target VARCHAR(200),
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE menu_top_group (
  id INT(11) NOT NULL AUTO_INCREMENT,
  menu_top_id INT,
  group_id INT,
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE menu_top_campus (
  id INT(11) NOT NULL AUTO_INCREMENT,
  menu_top_id INT,
  campus_id INT,
  deleted_at DATETIME,
  PRIMARY KEY (`id`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

CREATE TABLE menu_icons (
  name VARCHAR(200),
  title VARCHAR(200),
PRIMARY KEY (`name`) COMMENT '') ENGINE=InnoDB CHARACTER SET=utf8;

-- These are the top menu items for Schoolbox

INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (1, 'Classes', 'Classes', 'CLASSES', 'teacher', '/learning/classes', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (2, 'Submissions', 'Submissions', 'SUBMISSION', 'due-work', '/submission/dueWork', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (3, 'Timetable', 'Timetable', 'TIMETABLE', 'timetable', '/timetable', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (4, 'Calendar', 'Calendar', 'CALENDAR', 'calendar', '/calendar/week', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (5, 'News', 'News', 'NEWS', 'news', '/news', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (6, 'Webmail', 'Webmail', null, 'email', 'http://www.gmail.com', '_blank', null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (7, 'Grades', 'Grades', 'GRADE', 'approve', '/submission/allWork', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (8, 'Courses', 'Courses', 'COURSE', 'course', '/course/list', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (9, 'Resources', 'Resources', 'RESOURCES', 'folder', '/resources/', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (10, 'ePortfolio', 'ePortfolio', 'EPORTFOLIO', 'eportfolio', '/eportfolio/[ID]/profile', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (11, 'My Files', 'My Files', 'MYFILES', 'files', '/my-files/', null, null);
INSERT INTO menu_top (sort_order, title, text, token, icon, url, target, deleted_at) VALUES  (12, 'Drives', 'Drives', 'NETWORK', 'network', '/drives/', null, null);

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

INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 1, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 2, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 3, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 4, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 5, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 6, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 7, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 8, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 9, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 10, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 11, id FROM groups;
INSERT INTO menu_top_group(menu_top_id, group_id) SELECT 12, id FROM groups;