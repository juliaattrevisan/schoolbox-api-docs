CREATE TABLE topMenu
(
    id INT(3) UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
    txt VARCHAR(150) DEFAULT '0' NOT NULL,
    icon VARCHAR(200) DEFAULT '0',
    url VARCHAR(200) DEFAULT '0',
    target VARCHAR(10) DEFAULT '0',
    sequence TINYINT(3) UNSIGNED
);

CREATE TABLE topMenuCampus
(
    id INT(3) UNSIGNED DEFAULT '0' NOT NULL AUTO_INCREMENT,
    campus INT(3) UNSIGNED DEFAULT '0' NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (campus, id)
);

CREATE TABLE topMenuGroup
(
    id INT(3) UNSIGNED DEFAULT '0' NOT NULL AUTO_INCREMENT,
    gid INT(3) UNSIGNED DEFAULT '0' NOT NULL,
    CONSTRAINT `PRIMARY` PRIMARY KEY (id, gid)
);

INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (1, 'Submissions', 'icon-due-work', '/submission/listSubmission', null, 1);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (2, 'Timetable', 'icon-timetable', '/timetable', null, 2);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (3, 'Calendar', 'icon-calendar', '/calendar/week', null, 3);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (4, 'News', 'icon-news', '/news', null, 4);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (5, 'Webmail', 'icon-email', 'http://gmail.com', '_blank', 5);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (6, 'Resources', 'icon-folder', '/resources', null, 6);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (7, 'My Files', 'icon-files', '/my-files', null, 7);
INSERT INTO topMenu (id, txt, icon, url, target, sequence) VALUES (8, 'Drives', 'icon-network', '/drives', null, 8);