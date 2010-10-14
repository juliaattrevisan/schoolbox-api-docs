
/* Language translation
 * Image scroller
 */
INSERT INTO homepage_comp (size, method, name, description, filename) VALUES (1, 'outputTranslate', 'Language Translator', 'Translates text and webpages into another language', 'translate.html');
INSERT INTO homepage_comp (size, method, NAME, description, filename, config) VALUES (2, 'outputScroller', 'Image Slideshow', 'Automatically fades between images on your homepage', 'scroller.html', 'a:1:{s:5:"pixel";i:400;}');

/* ePortfolio
 * NB: Include schema of all tables with eportfolio_ prefix.
 */
INSERT INTO error (id, message) VALUES (100, 'Your e-portfolio has not been enabled.');
INSERT INTO error (id, message) VALUES (106, 'You do not have permission to modify or delete this link.');
INSERT INTO error (id, message) VALUES (107, 'You do not have permission to modify this blog.');
INSERT INTO error (id, message) VALUES (108, 'You do not have permission to leave comments on this blog.');
INSERT INTO error (id, message) VALUES (109, 'You do not have permission to publish this file.');
INSERT INTO error (id, message) VALUES (110, 'You do not have permission to modify these notes.');
INSERT INTO error (id, message) VALUES (111, 'There are no blogs in this archive.');


/* Assess
 * NB: Include schema of all tables with assess prefix.
 */
INSERT INTO homepage_comp (size, method, `name`, description, filename, config, invisible) VALUES (1, 'outputAssess', 'Online Assessment', 'Online tests and quizzes.', 'assess.html', '', 'false');

INSERT INTO file_type (id, name) VALUES (11, 'Online Assessment');

INSERT INTO error (id, message) VALUES (112, 'This assessment no longer exists.');
INSERT INTO error (id, message) VALUES (113, 'You have not answered all questions; please go back and submit them.');
INSERT INTO error (id, message) VALUES (114, 'You have not selected an attempt to review.');
INSERT INTO error (id, message) VALUES (115, 'This section no longer exists.');
INSERT INTO error (id, message) VALUES (116, 'Unspecified question type.');
INSERT INTO error (id, message) VALUES (117, 'Please choose a group for this assessment.');
INSERT INTO error (id, message) VALUES (118, 'This attempt no longer exists.');
INSERT INTO error (id, message) VALUES (119, 'This question no longer exists.');
INSERT INTO error (id, message) VALUES (120, 'This online assessment has expired.');
INSERT INTO error (id, message) VALUES (121, 'You have already attempted this online assessment and cannot re-sit it.');
INSERT INTO error (id, message) VALUES (122, 'You have run out of time to complete this online assessment and cannot re-sit it.');
INSERT INTO error (id, message) VALUES (123, 'This attempt has not been recorded.');
INSERT INTO error (id, message) VALUES (124, 'You are the owner of this test and therefore cannot attempt it.');
INSERT INTO error (id, message) VALUES (125, 'Only the test owner can view this page.');
INSERT INTO error (id, message) VALUES (126, 'Only teachers can create and modify tests.');
INSERT INTO error (id, message) VALUES (127, 'Only the test owner can review this test.');
INSERT INTO error (id, message) VALUES (128, 'This attempt no longer exists.');
INSERT INTO error (id, message) VALUES (129, 'You are not allowed to view this student\s test.');
INSERT INTO error (id, message) VALUES (130, 'Only teachers can create and modify their test contents.');
INSERT INTO error (id, message) VALUES (131, 'Question could not be saved.');
INSERT INTO error (id, message) VALUES (132, 'Answer could not be saved.');
INSERT INTO error (id, message) VALUES (133, 'Could not load question: unknown type.');
INSERT INTO error (id, message) VALUES (134, 'No import file found.');



ALTER TABLE submission_return CHANGE comment comment TEXT NULL ;



/* Create table in target */
CREATE TABLE `assess`(
	`assess_id` int(11) NOT NULL  auto_increment ,
	`category_id` int(11) NULL  ,
	`user_id` int(11) NOT NULL  ,
	`type` int(11) NULL  DEFAULT '11' ,
	`title` varchar(50) COLLATE utf8_general_ci NOT NULL  ,
	`description` text COLLATE utf8_general_ci NOT NULL  ,
	`start` datetime NOT NULL  ,
	`finish` datetime NOT NULL  ,
	`duration` int(11) NOT NULL  DEFAULT '0' COMMENT 'in minutes' ,
	`allow_resit` int(1) NOT NULL  DEFAULT '0' ,
	`created_at` datetime NULL  ,
	`deleted_at` datetime NULL  ,
	PRIMARY KEY (`assess_id`) ,
	UNIQUE KEY `id`(`assess_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `assess_answer`(
	`answer_id` int(11) NOT NULL  auto_increment ,
	`question_id` int(11) NOT NULL  ,
	`sequence` int(11) NULL  ,
	`answer` text COLLATE utf8_general_ci NULL  ,
	`deleted_at` datetime NULL  ,
	PRIMARY KEY (`answer_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `assess_attempt`(
	`attempt_id` int(11) NOT NULL  auto_increment ,
	`assess_id` int(11) NOT NULL  ,
	`user_id` int(11) NOT NULL  ,
	`start` datetime NOT NULL  ,
	`finish` datetime NOT NULL  ,
	`reviewed_at` datetime NULL  ,
	PRIMARY KEY (`attempt_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `assess_luQuestionType`(
	`type_id` int(8) NOT NULL  ,
	`filename` varchar(255) COLLATE utf8_general_ci NULL  ,
	`description` varchar(255) COLLATE utf8_general_ci NULL  ,
	`automated_marking` enum('true','false') COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`type_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


insert  into `assess_luQuestionType`(`type_id`,`filename`,`description`,`automated_marking`) values (1,'short','Short Answer','false');
insert  into `assess_luQuestionType`(`type_id`,`filename`,`description`,`automated_marking`) values (2,'multi','Multiple Choice','true');
insert  into `assess_luQuestionType`(`type_id`,`filename`,`description`,`automated_marking`) values (3,'fillgap','Cloze Exercise','true');
insert  into `assess_luQuestionType`(`type_id`,`filename`,`description`,`automated_marking`) values (4,'match','Matching Pairs','true');

/* Create table in target */
CREATE TABLE `assess_question`(
	`question_id` int(11) NOT NULL  auto_increment ,
	`section_id` int(11) NOT NULL  ,
	`sequence` int(11) NOT NULL  ,
	`type_id` int(11) NOT NULL  ,
	`marks` int(11) NULL  ,
	`question` text COLLATE utf8_general_ci NOT NULL  ,
	`deleted_at` datetime NULL  ,
	PRIMARY KEY (`question_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `assess_response`(
	`response_id` int(11) NOT NULL  auto_increment ,
	`attempt_id` int(11) NOT NULL  ,
	`section_id` int(11) NULL  ,
	`question_id` int(11) NOT NULL  ,
	`response` text COLLATE utf8_general_ci NULL  ,
	`comment` text COLLATE utf8_general_ci NOT NULL  ,
	`mark` float NULL  DEFAULT '0' ,
	`automarked_already` enum('true','false') COLLATE utf8_general_ci NULL  DEFAULT 'false' ,
	`updated_at` datetime NULL  ,
	PRIMARY KEY (`response_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `assess_section`(
	`section_id` int(11) NOT NULL  auto_increment ,
	`assess_id` int(11) NOT NULL  ,
	`sequence` int(11) NOT NULL  DEFAULT '0' ,
	`title` varchar(50) COLLATE utf8_general_ci NOT NULL  ,
	`description` text COLLATE utf8_general_ci NOT NULL  ,
	`random` int(11) NOT NULL  DEFAULT '0' ,
	`pool` int(11) NOT NULL  DEFAULT '0' ,
	`deleted_at` datetime NULL  ,
	PRIMARY KEY (`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_blog`(
	`id` int(6) unsigned NOT NULL  auto_increment ,
	`owner` int(6) unsigned NOT NULL  ,
	`created` datetime NOT NULL  ,
	`deleted` datetime NULL  ,
	`heading` varchar(50) COLLATE utf8_general_ci NOT NULL  ,
	`body` mediumtext COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`id`) ,
	FULLTEXT KEY `fulltext`(`heading`,`body`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_blogComment`(
	`id` int(6) unsigned NOT NULL  auto_increment ,
	`bid` int(6) unsigned NOT NULL  ,
	`owner` int(6) unsigned NOT NULL  ,
	`created` datetime NOT NULL  ,
	`deleted` datetime NULL  ,
	`body` mediumtext COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_comp`(
	`id` int(11) unsigned NOT NULL  auto_increment ,
	`size` tinyint(3) unsigned NOT NULL  DEFAULT '0' ,
	`method` varchar(18) COLLATE utf8_general_ci NULL  ,
	`name` varchar(20) COLLATE utf8_general_ci NULL  ,
	`description` mediumtext COLLATE utf8_general_ci NULL  ,
	`filename` varchar(22) COLLATE utf8_general_ci NULL  ,
	`config` mediumtext COLLATE utf8_general_ci NOT NULL  ,
	`invisible` enum('true','false') COLLATE utf8_general_ci NOT NULL  DEFAULT 'false' ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_comp_location`(
	`uid` int(6) unsigned NOT NULL  DEFAULT '0' ,
	`position` tinyint(1) unsigned NOT NULL  DEFAULT '0' ,
	`column` tinyint(3) unsigned NOT NULL  DEFAULT '1' ,
	`component` int(11) unsigned NOT NULL  DEFAULT '0' ,
	`config` mediumtext COLLATE utf8_general_ci NOT NULL  ,
	KEY `cid`(`uid`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_config`(
	`uid` int(6) unsigned NOT NULL  DEFAULT '0' ,
	`template` tinyint(1) unsigned NOT NULL  DEFAULT '0' ,
	`left_head_font` char(7) COLLATE utf8_general_ci NULL  ,
	`left_head_bg` char(7) COLLATE utf8_general_ci NULL  ,
	`left_font` char(7) COLLATE utf8_general_ci NULL  ,
	`left_bg_primary` char(7) COLLATE utf8_general_ci NULL  ,
	`left_bg_secondary` char(7) COLLATE utf8_general_ci NULL  ,
	`left_link` char(7) COLLATE utf8_general_ci NULL  ,
	`left_hover` char(7) COLLATE utf8_general_ci NULL  ,
	`right_head_font` char(7) COLLATE utf8_general_ci NULL  ,
	`right_head_bg` char(7) COLLATE utf8_general_ci NULL  ,
	`right_font` char(7) COLLATE utf8_general_ci NULL  ,
	`right_bg_primary` char(6) COLLATE utf8_general_ci NULL  ,
	`right_bg_secondary` char(7) COLLATE utf8_general_ci NULL  ,
	`right_link` char(7) COLLATE utf8_general_ci NULL  ,
	`right_hover` char(7) COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_link`(
	`id` int(5) unsigned NOT NULL  auto_increment ,
	`owner` int(5) unsigned NOT NULL  DEFAULT '0' ,
	`url` varchar(255) COLLATE utf8_general_ci NOT NULL  DEFAULT '' ,
	`submitted` datetime NOT NULL  DEFAULT '0000-00-00 00:00:00' ,
	`modified` datetime NULL  ,
	`description` varchar(255) COLLATE utf8_general_ci NULL  ,
	`comment` mediumtext COLLATE utf8_general_ci NULL  ,
	`hidden` tinyint(1) NOT NULL  ,
	PRIMARY KEY (`id`) ,
	FULLTEXT KEY `fulltext`(`url`,`description`,`comment`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_log`(
	`datetime` datetime NOT NULL  DEFAULT '0000-00-00 00:00:00' ,
	`user` int(6) NOT NULL  DEFAULT '0' ,
	`uid` int(6) NOT NULL  DEFAULT '0' ,
	`hitcount` int(10) unsigned NULL  DEFAULT '0' ,
	PRIMARY KEY (`user`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_privateNotes`(
	`uid` int(8) NULL  ,
	`privateNotes` text COLLATE utf8_general_ci NULL
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolio_textBox`(
	`textBoxID` int(11) NOT NULL  auto_increment ,
	`userID` int(11) NULL  ,
	`heading` varchar(100) COLLATE utf8_general_ci NULL  ,
	`body` text COLLATE utf8_general_ci NULL  ,
	`deleted` enum('true','false') COLLATE utf8_general_ci NOT NULL  DEFAULT 'false' ,
	PRIMARY KEY (`textBoxID`) ,
	KEY `categoryID`(`userID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `eportfolioTemplateLayout`(
	`templateID` int(11) NULL  ,
	`componentId` int(11) NULL  ,
	`column` smallint(6) NULL  ,
	`position` smallint(6) NULL  ,
	UNIQUE KEY `table`(`templateID`,`componentId`,`column`,`position`) ,
	KEY `homepageID`(`templateID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Alter table in target */
ALTER TABLE `myFile`
	ADD COLUMN `published` datetime   NULL after `deleted`, COMMENT='';

/* Alter table in target */
ALTER TABLE `user`
	ADD COLUMN `eportfolio_enabled` int(8)   NOT NULL DEFAULT '0' after `key`, COMMENT='';

/* Create table in target */
CREATE TABLE `wikiOpens`(
	`openID` int(8) NOT NULL  auto_increment ,
	`wikiID` int(8) NULL  ,
	`userID` int(8) NULL  ,
	`lastopened` datetime NULL  ,
	PRIMARY KEY (`openID`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';