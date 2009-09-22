
insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`filename`,`config`,`invisible`) values (37,2,'outputAudio','Audio','Lists audio files and allows them to be played on the homepage','audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false');
insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`filename`,`config`,`invisible`) values (38,2,'outputPodcast','Podcast','Subscribe to a podcast and have new music delivered to your homepage when the podcast is updated','podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false');
insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`filename`,`config`,`invisible`) values (39,1,'outputBlog','Blog','Post regular updates to an online web log and allow visitors to comment','blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false');

UPDATE `homepage_comp` SET `config` = 'a:1:{s:6:"pollID";i:0;}' WHERE `id` = 33;
UPDATE `homepage_comp` SET `config` = 'a:5:{s:6:"filter";a:5:{i:0;s:8:"document";i:1;s:5:"audio";i:2;s:5:"video";i:3;s:5:"image";i:4;s:5:"other";}s:13:"filterOptions";a:5:{i:0;s:8:"document";i:1;s:5:"audio";i:2;s:5:"video";i:3;s:5:"image";i:4;s:5:"other";}s:4:"sort";s:4:"Date";s:7:"sortdir";s:4:"Desc";s:4:"size";i:0;}' WHERE `id` = 3;


INSERT INTO `homepageTemplate`(`id`,`name`,`layout`) VALUES ( '7','Blog','1');
INSERT INTO `homepageTemplateLayout`(`templateID`,`componentId`,`column`,`position`) VALUES ( '7','39','1','1');
INSERT INTO `homepageTemplateLayout`(`templateID`,`componentId`,`column`,`position`) VALUES ( '7','33','2','1');
INSERT INTO `homepageTemplateLayout`(`templateID`,`componentId`,`column`,`position`) VALUES ( '7','10','2','3');
INSERT INTO `homepageTemplateLayout`(`templateID`,`componentId`,`column`,`position`) VALUES ( '7','2','2','2');


/* Create table in target */
CREATE TABLE `blog`(
	`id` int(6) unsigned NOT NULL  auto_increment ,
	`cid` int(6) unsigned NOT NULL  ,
	`owner` int(6) unsigned NOT NULL  ,
	`created` datetime NOT NULL  ,
	`deleted` datetime NULL  ,
	`heading` varchar(50) COLLATE utf8_general_ci NOT NULL  ,
	`body` mediumtext COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `blogComment`(
	`id` int(6) unsigned NOT NULL  auto_increment ,
	`bid` int(6) unsigned NOT NULL  ,
	`owner` int(6) unsigned NOT NULL  ,
	`created` datetime NOT NULL  ,
	`deleted` datetime NULL  ,
	`body` mediumtext COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `forumPost`(
	`postID` int(11) NOT NULL  auto_increment ,
	`threadID` int(11) NULL  ,
	`userID` int(11) NULL  ,
	`date` datetime NULL  ,
	`body` text COLLATE utf8_general_ci NULL  ,
	`deleted` enum('true','false') COLLATE utf8_general_ci NOT NULL  DEFAULT 'false' ,
	`modified` datetime NULL  ,
	`censored` enum('true','false') COLLATE utf8_general_ci NOT NULL  DEFAULT 'false' ,
	PRIMARY KEY (`postID`) ,
	KEY `threadID`(`threadID`) ,
	KEY `userID`(`userID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `forumThread`(
	`threadID` int(11) NOT NULL  auto_increment ,
	`categoryID` int(11) NULL  ,
	`userID` int(11) NULL  ,
	`title` varchar(250) COLLATE utf8_general_ci NULL  ,
	`date` datetime NULL  ,
	`deleted` enum('true','false') COLLATE utf8_general_ci NOT NULL  DEFAULT 'false' ,
	`locked` enum('true','false') COLLATE utf8_general_ci NOT NULL  DEFAULT 'false' ,
	`views` int(11) NOT NULL  ,
	`last_post_id` int(11) NULL  ,
	`last_post_date` datetime NULL  ,
	`last_post_user_id` int(11) NULL  ,
	`last_post_user` varchar(250) COLLATE utf8_general_ci NULL  ,
	`tempID`	int(11) NULL,
	PRIMARY KEY (`threadID`) ,
	KEY `categoryID`(`categoryID`) ,
	KEY `userID`(`userID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


INSERT INTO forumThread (categoryID, userID, title, `date`, `deleted`, `tempID`)
SELECT cid, `owner`, heading, `date`, IF(deleted=1,'true','false'), id FROM forum WHERE thread = id ORDER BY `date`;

INSERT INTO forumPost (threadID, userID, `date`, body, deleted)
SELECT t.threadID, `owner`, f.date, `message`, IF(f.deleted=1,'true','false') FROM forum f
LEFT JOIN forumThread t ON t.tempID = f.thread;

UPDATE forumThread t
SET last_post_date = (SELECT `date` FROM forumPost p WHERE p.threadID = t.threadID ORDER BY `date` DESC LIMIT 1),
last_post_id = (SELECT `postID` FROM forumPost p WHERE p.threadID = t.threadID ORDER BY `date` DESC LIMIT 1),
last_post_user_id = (SELECT `userID` FROM forumPost p WHERE p.threadID = t.threadID ORDER BY `date` DESC LIMIT 1),
last_post_user =
(SELECT CONCAT(firstname,' ',lastname) FROM forumPost p, `user` u
 WHERE u.id = p.userID AND p.threadID = t.threadID ORDER BY `date` DESC LIMIT 1 );

/* Alter table in target */
ALTER TABLE `wiki`
	ADD COLUMN `locked` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `deleted`, COMMENT='';

/* Create table in target */
RENAME TABLE `revision` TO `wikiRevision`;
