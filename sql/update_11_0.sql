/* My links conversion
 */
INSERT IGNORE INTO error(id, message) VALUES (50, "You do not have permission to delete this user's file.");
INSERT IGNORE INTO error(id, message) VALUES (52, "This link is already shared in your ePortfolio.");

INSERT IGNORE INTO luEventType(eventTypeID, eventType, eventColour) VALUES (6, 'Online Assessment', 'fff3b2');
INSERT IGNORE INTO file_type(id, `name`) VALUES (11, 'Online Assessment');

/* Alter table in target */
ALTER TABLE `allowedFileExtension`
	CHANGE `extension` `extension` char(6)  COLLATE utf8_general_ci NULL after `fileTypeID`;

/* Alter table in target */
ALTER TABLE `assess_luQuestionType`
	ADD COLUMN `moodle` varchar(255)  COLLATE utf8_general_ci NULL after `description`,
	CHANGE `automated_marking` `automated_marking` enum('true','false')  COLLATE utf8_general_ci NOT NULL after `moodle`;

/* Allow deleteable attempts
 */
ALTER TABLE `assess_response` ADD COLUMN deleted_at DATETIME;
ALTER TABLE `assess_attempt` ADD COLUMN deleted_at DATETIME;

DROP TABLE chat;

/* Create table in target */
CREATE TABLE `chat`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`category_id` int(10) unsigned NOT NULL  ,
	`user_id` int(10) unsigned NOT NULL  ,
	`type` varchar(20) COLLATE utf8_general_ci NOT NULL  ,
	`time` datetime NOT NULL  ,
	`body` varchar(255) COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

DROP TABLE chat_poll;

/* Create table in target */
CREATE TABLE `chat_poll`(
	`user_id` int(10) unsigned NOT NULL  ,
	`category_id` int(10) unsigned NOT NULL  ,
	`time` datetime NOT NULL  ,
	PRIMARY KEY (`user_id`,`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `event`
	ADD COLUMN `detail` varchar(250)  COLLATE utf8_general_ci NULL after `endTime`,
	ADD COLUMN `location` varchar(250)  COLLATE utf8_general_ci NULL after `detail`;

/* Alter table in target */
ALTER TABLE `homepage_comp`
	ADD COLUMN `homepageCategoryID` int(10) unsigned   NOT NULL after `description`,
	CHANGE `filename` `filename` varchar(22)  COLLATE utf8_general_ci NULL after `homepageCategoryID`,
	CHANGE `config` `config` mediumtext  COLLATE utf8_general_ci NOT NULL after `filename`,
	CHANGE `invisible` `invisible` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `config`,
	ADD COLUMN `allowMultiple` enum('true','false')  COLLATE utf8_general_ci NULL DEFAULT 'false' after `invisible`;

TRUNCATE `homepage_comp`;

insert ignore into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) values (8,1,'outputNews','News','Provides a text area for you to post information, news, blogs and introductions. ',1,'news_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(2,1,'outputLinkList','Links','Provide links to interesting, related and helpful resource on the portal or the WWW.',5,'links_n.htm','a:3:{s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}','false','false'),
(3,1,'outputFileList','Files','List general files that have been uploaded to your homepage. The large version shows both titles and comments.',5,'files_n.htm','a:5:{s:6:\"filter\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:13:\"filterOptions\";a:5:{i:0;s:8:\"document\";i:1;s:5:\"audio\";i:2;s:5:\"video\";i:3;s:5:\"image\";i:4;s:5:\"other\";}s:4:\"sort\";s:4:\"Date\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";i:0;}','false','false'),(4,2,'outputMembers','Group Members','Show the group members associated with your homepage.  This also provides an email all group members link.',2,'members_s.htm','','false','false'),
(5,2,'outputForumList','Forum','Show recent discussion topics going on in your homepages forum.',2,'forum_s.htm','a:2:{s:4:\"sort\";s:4:\"Date\";s:4:\"size\";i:0;}','false','false'),(6,2,'outputLinkList','Small Links','Provide links to interesting, related and helpful resource on the portal or the WWW.',5,'links_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(7,2,'outputFileList','Small Files','List general files that have been uploaded to your homepage. The small version only shows file titles.',5,'files_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(9,2,'initSubject','Class Management','Provides links to class lists, attendances and the mark sheet. This is only visible to teachers, students cannot see this module.',3,'class_manage.htm','','false','false'),
(10,2,'folderManage','Folder Management','Provides links to browse the files, manage group membership and view homepage statistics.',5,'folder_manage.htm','','false','false'),(11,2,'outputAssignList','Small Assignment','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.',3,'assign_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(12,2,'outputHomeworkList','Small Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.',3,'homework_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),
(13,2,'outputManagers','Group Managers','Displays a list of group members with write access to the homepage.  Useful in larger groups to show manages of the group.',2,'staff_s.htm','','false','false'),(14,1,'outputImage','Image Simple','Just displays an image on your page.',4,'image_s.htm','','false','false'),(15,1,'outputImage','Image Advanced','Completely configurable image display, select the image, control the size and link to another location',4,'image_n.htm','a:3:{s:5:\"pixel\";i:200;s:3:\"url\";s:1:\"#\";s:5:\"image\";s:13:\"Preview Image\";}','false','true'),(16,1,'outputAssignList','Assignments','List submission boxes with the type assignment. It shows the due date and allows students to submit from the homepage.',3,'assign_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),
(17,1,'outputHomeworkList','Homework','List submission boxes with the type homework. It shows the due date and allows students to submit from the homepage.',3,'homework_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(20,2,'outputFolderList','Folders','Display a list of subfolders within your homepage.  Also links to the homepages if they are available.',5,'folders_s.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(21,1,'outputEventList','Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(22,2,'outputEventList','Small Calendar','Display upcoming events that are in the calendar for this group.',1,'cal_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),
(23,1,'outputImageGallery','Image Gallery','Show a grid of image thumbnails sourced from the files uploaded onto this homepage. ',4,'gallery.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(24,1,'outputRandImage','Random Image','Display a random image selected from the images on this homepage.',4,'image_s.htm','a:1:{s:5:\"pixel\";i:400;}','false','false'),(25,2,'outputDropBox','File Drop Box','Provides an area for teachers to recieve work via the portal without the need for a submission box.',3,'dropbox.htm','','false','false'),(28,1,'outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.',3,'duework_n.htm','a:1:{s:4:\"size\";i:0;}','false','false'),
(27,2,'outputRSS','Small RSS Feed','Display external RSS site headlines and brief description. The RSS feed can be from any site that provides a feed.',6,'rss_s.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','true','false'),(26,1,'outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.',6,'rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}','false','true'),(30,2,'outputTeachers','Class Teachers','Show a list of teachers responsible for this subject if it has a synergetic subject code.',3,'teacher_s.htm','','false','false'),(29,2,'outputDueWorkList','Small Due Work','Shows a list of all due work and its due date.',3,'duework_s.htm','a:1:{s:4:\"size\";i:0;}','true','false'),
(31,1,'outputCalendarList','Campus Calendar','Show a calendar for the entire campus on your homepage.',1,'cal_n.htm','a:1:{s:4:\"size\";i:0;}','true','false'),(32,0,'outputDuties','Duties',NULL,1,'duties.htm','','true','false'),(33,2,'outputPoll','Poll','Create a poll to determine group members opinion on a given subject.',2,'poll.htm','a:1:{s:6:\"pollID\";i:0;}','false','true'),(34,1,'outputWiki','Wiki','Provides an area for group members to work together on joint documents.',2,'wiki.htm','a:1:{s:4:\"size\";i:0;}','false','false'),(35,1,'outputFeed','Homepage Feed','Provides a running commentary on recent changes to the homepage',6,'feed.htm','a:1:{s:4:\"size\";i:0;}','false','false'),
(36,1,'outputTextBox','Text Box','Use this space to write introductory message or information relating to the content that is displayed',1,'textBox.html','a:1:{s:9:\"textBoxID\";i:0;}','false','true'),(37,1,'outputAudio','Audio','Lists audio files and allows them to be played.',5,'audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false','false'),(38,1,'outputPodcast','Podcast','Subscribe to a podcast ',6,'podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false','true'),(39,1,'outputBlog','Blog','A blog for writing ongoing entries. Also comes with the ability for readers to post comments about your posts.',1,'blog.html','a:2:{s:4:\"size\";i:3;s:13:\"comments_size\";i:2;}','false','false'),
(40,2,'outputTranslate','Language Translator','Translates text and webpages into another language',3,'translate.html','','false','false'),(41,1,'outputScroller','Image Scroller','An image carousel.',4,'scroller.html','a:1:{s:5:\"pixel\";i:400;}','false','false'),(42,1,'outputAssess','Online Assessment','Online tests and quizzes.',3,'assess.html','','false','false'),(43,0,'outputChat','Chat','Allows users who are viewing the same homepage to chat with each other.',2,'chat.html','a:2:{s:5:\"state\";s:3:\"off\";s:17:\"teacher_timestamp\";s:0:\"\";}','false','false');

/* Alter table in target */
ALTER TABLE `homepage_comp_location`
	ADD COLUMN `homepageComponentLocationID` int(10) unsigned   NOT NULL auto_increment first,
	CHANGE `cid` `cid` int(6) unsigned   NOT NULL DEFAULT '0' after `homepageComponentLocationID`,
	CHANGE `position` `position` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `cid`,
	CHANGE `column` `column` tinyint(3) unsigned   NOT NULL DEFAULT '1' after `position`,
	CHANGE `component` `component` int(11) unsigned   NOT NULL DEFAULT '0' after `column`,
	CHANGE `config` `config` mediumtext  COLLATE utf8_general_ci NOT NULL after `component`,
	ADD PRIMARY KEY(`homepageComponentLocationID`);

/* Create table in target */
CREATE TABLE IF NOT EXISTS `homepageComponentCategory`(
	`homepageComponentCategoryID` int(10) unsigned NOT NULL  auto_increment ,
	`name` varchar(250) COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`homepageComponentCategoryID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

insert ignore  into `homepageComponentCategory`(`homepageComponentCategoryID`,`name`) values (1,'Information'),(2,'Collaboration'),(3,'Learning'),(4,'Images'),(5,'Resources'),(6,'Feeds');

ALTER TABLE `file` CHANGE `mime_type` `mime_type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;

UPDATE `file` SET mime_type='application/vnd.openxmlformats-officedocument.wordprocessingml.document' WHERE realname LIKE '%.docx' AND mime_type LIKE 'application/vnd.openxmlformats%';
UPDATE `file` SET mime_type='application/vnd.openxmlformats-officedocument.presentationml.slideshow' WHERE realname LIKE '%.ppsx' AND mime_type LIKE 'application/vnd.openxmlformats%';
UPDATE `file` SET mime_type='application/vnd.openxmlformats-officedocument.presentationml.presentation' WHERE realname LIKE '%.pptx' AND mime_type LIKE 'application/vnd.openxmlformats%';
UPDATE `file` SET mime_type='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' WHERE realname LIKE '%.xlsx' AND mime_type LIKE 'application/vnd.openxmlformats%';


/* Create table in target */
CREATE TABLE IF NOT EXISTS `newsUser`(
	`newsID` int(10) unsigned NOT NULL  ,
	`userID` int(10) unsigned NOT NULL  ,
	PRIMARY KEY (`newsID`,`userID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE IF NOT EXISTS `config`(
	`configID` int(10) unsigned NOT NULL  auto_increment ,
	`name` varchar(80) COLLATE utf8_general_ci NULL  ,
	`value` text COLLATE utf8_general_ci NULL  ,
	`title` varchar(100) COLLATE utf8_general_ci NULL  ,
	`description` varchar(255) COLLATE utf8_general_ci NULL  ,
	`field` enum('input','textarea','boolean','select','checkbox') COLLATE utf8_general_ci NULL  DEFAULT 'input' ,
	`options` varchar(100) COLLATE utf8_general_ci NULL  ,
	`type` enum('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB') COLLATE utf8_general_ci NULL  ,
	`editable` enum('true','false') COLLATE utf8_general_ci NULL  DEFAULT 'false' ,
	`hidden` enum('true','false') COLLATE utf8_general_ci NULL  DEFAULT 'false' ,
	PRIMARY KEY (`configID`) ,
	KEY `configName`(`name`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

ALTER TABLE `eportfolio_comp`
	ADD COLUMN `eportfolioCategoryID` int(10) unsigned   NOT NULL after `description`,
	CHANGE `filename` `filename` varchar(22)  COLLATE utf8_general_ci NULL after `eportfolioCategoryID`,
	CHANGE `config` `config` mediumtext  COLLATE utf8_general_ci NOT NULL after `filename`,
	CHANGE `invisible` `invisible` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `config`,
	ADD COLUMN `allowMultiple` enum('true','false')  COLLATE utf8_general_ci NULL after `invisible`;

/* Create table in target */
CREATE TABLE IF NOT EXISTS `eportfolio_comp_category`(
	`eportfolioComponentCategoryID` int(10) unsigned NOT NULL  auto_increment ,
	`name` varchar(250) COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`eportfolioComponentCategoryID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Alter table in target */
ALTER TABLE `eportfolio_comp_location`
	ADD COLUMN `eportfolioComponentLocationID` int(10) unsigned   NOT NULL auto_increment first,
	CHANGE `uid` `uid` int(6) unsigned   NOT NULL DEFAULT '0' after `eportfolioComponentLocationID`,
	CHANGE `position` `position` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `uid`,
	CHANGE `column` `column` tinyint(3) unsigned   NOT NULL DEFAULT '1' after `position`,
	CHANGE `component` `component` int(11) unsigned   NOT NULL DEFAULT '0' after `column`,
	CHANGE `config` `config` mediumtext  COLLATE utf8_general_ci NOT NULL after `component`,
	ADD PRIMARY KEY(`eportfolioComponentLocationID`);

/* Alter table in target */
ALTER TABLE `file`
	CHANGE `category` `category` int(5)   NULL after `id`,
	CHANGE `mime_type` `mime_type` varchar(100)  COLLATE utf8_general_ci NULL after `file_size`;

ALTER TABLE `submission_box`
	CHANGE `late_date` `late_date` datetime   NULL DEFAULT '0000-00-00 00:00:00' after `date`,
	ADD COLUMN `accept_submission` tinyint(1)   NOT NULL DEFAULT '1' after `fid`,
	CHANGE `deleted` `deleted` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `accept_submission`,
	CHANGE `description` `description` varchar(32)  COLLATE utf8_general_ci NULL after `deleted`,
	CHANGE `created` `created` datetime   NOT NULL DEFAULT '0000-00-00 00:00:00' after `description`;

ALTER TABLE `submission_return`
	CHANGE `comment` `comment` TEXT  COLLATE utf8_general_ci NULL after `owner`;
/* Point the Admin Sidemenu Link at the new Admin system & delete the old subNav Options */
UPDATE sideMenu
set url = '/adminv2/home'
where id = '208' AND txt = 'Admin';
delete from sideMenu
where parent = '208';
