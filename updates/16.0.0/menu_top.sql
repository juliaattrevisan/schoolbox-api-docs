CREATE TABLE menu_top
(
    id INT(11) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) DEFAULT '0' NOT NULL,
    icon VARCHAR(200) DEFAULT '0',
    url VARCHAR(200) DEFAULT '0',
    target VARCHAR(10) DEFAULT '0',
    sequence TINYINT(3) UNSIGNED
);

CREATE TABLE menu_top_campus
(
    id INT(11) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
    campus INT(11) UNSIGNED DEFAULT '0' NOT NULL
);

CREATE TABLE menu_top_group
(
    id INT(11) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
    gid INT(11) UNSIGNED DEFAULT '0' NOT NULL
);

INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (1, 'Submissions', 'icon-due-work', '/submission/listSubmission', null, 1);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (2, 'Timetable', 'icon-timetable', '/timetable', null, 2);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (3, 'Calendar', 'icon-calendar', '/calendar/week', null, 3);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (4, 'News', 'icon-news', '/news', null, 4);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (5, 'Webmail', 'icon-email', 'http://gmail.com', '_blank', 5);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (6, 'Resources', 'icon-folder', '/resources', null, 6);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (7, 'My Files', 'icon-files', '/my-files', null, 7);
INSERT INTO menu_top (id, title, icon, url, target, sequence) VALUES (8, 'Drives', 'icon-network', '/drives', null, 8);