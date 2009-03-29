/* Alter table in target */
/* Alter table in target */
ALTER TABLE `allowedFileExtension` 
	CHANGE `extension` `extension` char(6)  COLLATE utf8_general_ci NULL after `fileTypeID`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `allowedFileTypes` 
	CHANGE `description` `description` varchar(150)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `fileTypeID`, 
	CHANGE `mime` `mime` varchar(100)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `description`, 
	CHANGE `iconFile` `iconFile` varchar(80)  COLLATE utf8_general_ci NOT NULL DEFAULT 'application-x-none.png' after `mime`, 
	CHANGE `inline` `inline` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'true' after `iconFile`, 
	CHANGE `enable` `enable` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'true' after `inline`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `bookmarks` 
	CHANGE `url` `url` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `owner`, 
	CHANGE `description` `description` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `url`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `cache` 
	CHANGE `session_id` `session_id` varchar(40)  COLLATE utf8_general_ci NULL after `user_id`, 
	CHANGE `cache_key` `cache_key` varchar(128)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `session_id`, 
	CHANGE `data` `data` longtext  COLLATE utf8_general_ci NOT NULL after `created`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `category` 
	CHANGE `name` `name` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `id`, 
	CHANGE `code` `code` varchar(20)  COLLATE utf8_general_ci NULL after `teacherid`, COMMENT='', DEFAULT CHARSET='utf8';

/* Create table in target */
CREATE TABLE `categoryCode`(
	`categoryID` int(11) NULL  , 
	`code` varchar(20) COLLATE utf8_general_ci NULL  , 
	UNIQUE KEY `categoryIDcode`(`categoryID`,`code`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Alter table in target */
ALTER TABLE `cmsLog` 
	CHANGE `object` `object` enum('folder','file','link','myfile','myfolder')  COLLATE utf8_general_ci NOT NULL DEFAULT 'folder' after `id`, 
	CHANGE `action` `action` enum('create','modify','delete')  COLLATE utf8_general_ci NOT NULL DEFAULT 'create' after `objectId`, 
	ADD KEY `user`(`user`), COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `dropbox` 
	CHANGE `description` `description` varchar(255)  COLLATE utf8_general_ci NULL after `owner`, 
	CHANGE `realname` `realname` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `description`, 
	CHANGE `mime_type` `mime_type` varchar(40)  COLLATE utf8_general_ci NULL after `file_size`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `duty` 
	CHANGE `user` `user` varchar(100)  COLLATE utf8_general_ci NULL after `dutyTypeID`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `enquiryDestination` 
	CHANGE `email` `email` varchar(80)  COLLATE utf8_general_ci NULL after `enquiryTypeID`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `enquiryType` 
	CHANGE `name` `name` varchar(40)  COLLATE utf8_general_ci NULL DEFAULT '' after `id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `error` 
	CHANGE `message` `message` mediumtext  COLLATE utf8_general_ci NULL after `id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `event` 
	CHANGE `priority` `priority` smallint(1)   NOT NULL DEFAULT '0' after `sid`, 
	CHANGE `note` `note` varchar(250)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `priority`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `eventCampus` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `eventGroup` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `eventMonthly` 
	CHANGE `note` `note` varchar(250)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `endDuration`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `eventWeekly` 
	CHANGE `note` `note` varchar(250)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `endDuration`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `file` 
	CHANGE `realname` `realname` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `owner`, 
	ADD COLUMN `modified` datetime   NULL after `submitted`, 
	CHANGE `description` `description` varchar(255)  COLLATE utf8_general_ci NULL after `modified`, 
	CHANGE `comment` `comment` mediumtext  COLLATE utf8_general_ci NULL after `description`, 
	CHANGE `type` `type` tinyint(4) unsigned   NOT NULL DEFAULT '0' after `comment`, 
	CHANGE `file_size` `file_size` bigint(20)   NULL after `type`, 
	CHANGE `mime_type` `mime_type` varchar(40)  COLLATE utf8_general_ci NULL after `file_size`, 
	CHANGE `deleted` `deleted` tinyint(2) unsigned   NOT NULL DEFAULT '0' after `mime_type`, 
	CHANGE `hidden` `hidden` tinyint(1)   NOT NULL DEFAULT '0' after `deleted`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `file_type` 
	CHANGE `name` `name` varchar(40)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `forum` 
	CHANGE `heading` `heading` varchar(250)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `date`, 
	CHANGE `message` `message` mediumtext  COLLATE utf8_general_ci NULL after `heading`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `groups` 
	CHANGE `name` `name` varchar(20)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `id`, 
	ADD COLUMN `staff` smallint(6)   NULL after `name`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `homepage_colours` 
	CHANGE `name` `name` char(15)  COLLATE utf8_general_ci NOT NULL DEFAULT '0' after `id`, 
	CHANGE `hex` `hex` char(6)  COLLATE utf8_general_ci NOT NULL DEFAULT '0' after `name`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `homepage_comp` 
	CHANGE `method` `method` varchar(18)  COLLATE utf8_general_ci NULL after `size`, 
	CHANGE `name` `name` varchar(20)  COLLATE utf8_general_ci NULL after `method`, 
	CHANGE `description` `description` mediumtext  COLLATE utf8_general_ci NULL after `name`, 
	CHANGE `filename` `filename` varchar(22)  COLLATE utf8_general_ci NULL after `description`, 
	CHANGE `config` `config` mediumtext  COLLATE utf8_general_ci NOT NULL after `filename`, 
	CHANGE `invisible` `invisible` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `config`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `homepage_comp_location` 
	CHANGE `config` `config` mediumtext  COLLATE utf8_general_ci NOT NULL after `component`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `homepage_config` 
	CHANGE COLUMN `large_head_fontcolour` `left_head_font` char(7)  COLLATE utf8_general_ci NULL after `template`, 
	CHANGE COLUMN `large_head_bgcolour` `left_head_bg` char(7)  COLLATE utf8_general_ci NULL after `left_head_font`, 
	CHANGE COLUMN `large_text_fontcolour` `left_font` char(7)  COLLATE utf8_general_ci NULL after `left_head_bg`, 
	CHANGE COLUMN `large_text_bgcolour` `left_bg_primary` char(7)  COLLATE utf8_general_ci NULL after `left_font`, 
	ADD COLUMN `left_bg_secondary` char(7)  COLLATE utf8_general_ci NULL after `left_bg_primary`, 
	ADD COLUMN `left_link` char(7)  COLLATE utf8_general_ci NULL after `left_bg_secondary`, 
	ADD COLUMN `left_hover` char(7)  COLLATE utf8_general_ci NULL after `left_link`, 
	CHANGE COLUMN `small_head_fontcolour` `right_head_font` char(7)  COLLATE utf8_general_ci NULL after `left_hover`, 
	CHANGE COLUMN `small_head_bgcolour` `right_head_bg` char(7)  COLLATE utf8_general_ci NULL after `right_head_font`, 
	CHANGE COLUMN `small_text_fontcolour` `right_font` char(7)  COLLATE utf8_general_ci NULL after `right_head_bg`, 
	CHANGE COLUMN `small_text_bgcolour` `right_bg_primary` char(6)  COLLATE utf8_general_ci NULL after `right_font`, 
	ADD COLUMN `right_bg_secondary` char(7)  COLLATE utf8_general_ci NULL after `right_bg_primary`, 
	ADD COLUMN `right_link` char(7)  COLLATE utf8_general_ci NULL after `right_bg_secondary`, 
	ADD COLUMN `right_hover` char(7)  COLLATE utf8_general_ci NULL after `right_link`, 
	COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `homepage_log` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `homepages` 
	CHANGE `name` `name` varchar(255)  COLLATE utf8_general_ci NULL after `id`, 
	CHANGE `filename` `filename` varchar(255)  COLLATE utf8_general_ci NULL after `name`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `house` 
	CHANGE `house` `house` varchar(20)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `link` 
	CHANGE `url` `url` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `owner`, 
	ADD COLUMN `modified` datetime   NULL after `submitted`, 
	CHANGE `description` `description` varchar(255)  COLLATE utf8_general_ci NULL after `modified`, 
	CHANGE `comment` `comment` mediumtext  COLLATE utf8_general_ci NULL after `description`, 
	CHANGE `hidden` `hidden` tinyint(1)   NOT NULL after `comment`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `login_log` 
	CHANGE `username` `username` varchar(50)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `id`, 
	CHANGE `ip` `ip` varchar(15)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `date`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `luCampus` 
	CHANGE `name` `name` varchar(255)  COLLATE utf8_general_ci NULL first, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `luDuties` 
	CHANGE `name` `name` varchar(50)  COLLATE utf8_general_ci NULL after `dutyTypeID`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `luResourceLocation` 
	CHANGE `name` `name` varchar(50)  COLLATE utf8_general_ci NOT NULL DEFAULT '0' after `id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `luResourceType` 
	CHANGE `name` `name` varchar(50)  COLLATE utf8_general_ci NOT NULL DEFAULT '0' after `id`, 
	CHANGE `type` `type` enum('room','equipment')  COLLATE utf8_general_ci NULL after `name`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `luServices` 
	CHANGE `name` `name` varchar(100)  COLLATE utf8_general_ci NULL after `id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `luTitle` 
	CHANGE `titleName` `titleName` varchar(20)  COLLATE utf8_general_ci NULL after `titleID`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `messages` 
	CHANGE `cache_key` `cache_key` varchar(128)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `del`, 
	CHANGE `subject` `subject` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `uid`, 
	CHANGE `from` `from` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `subject`, 
	CHANGE `to` `to` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `from`, 
	CHANGE `cc` `cc` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `to`, 
	CHANGE `headers` `headers` mediumtext  COLLATE utf8_general_ci NOT NULL after `size`, 
	CHANGE `structure` `structure` mediumtext  COLLATE utf8_general_ci NULL after `headers`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `myFile` 
	CHANGE `realname` `realname` tinytext  COLLATE utf8_general_ci NOT NULL after `owner`, 
	CHANGE `description` `description` longtext  COLLATE utf8_general_ci NOT NULL after `submitted`, 
	CHANGE `mime_type` `mime_type` tinytext  COLLATE utf8_general_ci NOT NULL after `file_size`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `myFolder` 
	CHANGE `name` `name` mediumtext  COLLATE utf8_general_ci NOT NULL after `parent`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `news_items` 
	CHANGE `item_topic_id` `item_topic_id` smallint(5) unsigned   NULL DEFAULT '0' after `item_id`, 
	CHANGE `item_heading` `item_heading` varchar(50)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `item_topic_id`, 
	CHANGE `item_body` `item_body` mediumtext  COLLATE utf8_general_ci NOT NULL after `item_mainpage`, 
	CHANGE `item_link1` `item_link1` varchar(255)  COLLATE utf8_general_ci NULL after `item_body`, 
	CHANGE `item_link2` `item_link2` varchar(255)  COLLATE utf8_general_ci NULL after `item_link1`, 
	CHANGE `item_link3` `item_link3` varchar(255)  COLLATE utf8_general_ci NULL after `item_link2`, 
	CHANGE `item_link4` `item_link4` varchar(255)  COLLATE utf8_general_ci NULL after `item_link3`, 
	CHANGE `item_link5` `item_link5` varchar(255)  COLLATE utf8_general_ci NULL after `item_link4`, 
	CHANGE `item_campus` `item_campus` int(4)   NULL DEFAULT '0' after `item_owner`, 
	ADD COLUMN `item_group` int(4) unsigned   NULL after `item_campus`, 
	DROP KEY `item_datetime_expires`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `newsCampus` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `newsGroup` 
	ADD KEY `group`(`gid`), 
	DROP KEY `groups`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `perms` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `ph_ext` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `poll` 
	CHANGE `title` `title` varchar(120)  COLLATE utf8_general_ci NULL after `categoryID`, 
	CHANGE `question` `question` varchar(250)  COLLATE utf8_general_ci NULL after `title`, 
	CHANGE `option_0` `option_0` varchar(120)  COLLATE utf8_general_ci NULL after `question`, 
	CHANGE `option_1` `option_1` varchar(120)  COLLATE utf8_general_ci NULL after `option_0`, 
	CHANGE `option_2` `option_2` varchar(120)  COLLATE utf8_general_ci NULL after `option_1`, 
	CHANGE `option_3` `option_3` varchar(120)  COLLATE utf8_general_ci NULL after `option_2`, 
	CHANGE `option_4` `option_4` varchar(120)  COLLATE utf8_general_ci NULL after `option_3`, 
	CHANGE `option_5` `option_5` varchar(120)  COLLATE utf8_general_ci NULL after `option_4`, 
	CHANGE `option_6` `option_6` varchar(120)  COLLATE utf8_general_ci NULL after `option_5`, 
	CHANGE `option_7` `option_7` varchar(120)  COLLATE utf8_general_ci NULL after `option_6`, 
	CHANGE `option_8` `option_8` varchar(120)  COLLATE utf8_general_ci NULL after `option_7`, 
	CHANGE `option_9` `option_9` varchar(120)  COLLATE utf8_general_ci NULL after `option_8`, 
	CHANGE `frequency` `frequency` enum('once','hourly','daily')  COLLATE utf8_general_ci NOT NULL DEFAULT 'once' after `result_9`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `pollLog` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `resource` 
	CHANGE `name` `name` varchar(50)  COLLATE utf8_general_ci NULL DEFAULT '0' after `location`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `resourceBooking` 
	CHANGE `note` `note` varchar(250)  COLLATE utf8_general_ci NULL after `end`, 
	CHANGE `recurrence` `recurrence` enum('week','fortnight','month')  COLLATE utf8_general_ci NULL after `year`, 
	CHANGE `type` `type` enum('request','locked')  COLLATE utf8_general_ci NULL after `recurrence`, COMMENT='', DEFAULT CHARSET='utf8';

/* Create table in target */
CREATE TABLE `revision`(
	`revisionID` int(11) NOT NULL  auto_increment , 
	`wikiID` int(11) NULL  , 
	`heading` varchar(200) COLLATE utf8_general_ci NULL  , 
	`body` mediumtext COLLATE utf8_general_ci NULL  , 
	`userID` int(11) NULL  , 
	`created` datetime NULL  , 
	PRIMARY KEY (`revisionID`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Alter table in target */
ALTER TABLE `roomResource` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `sideMenu` 
	CHANGE `txt` `txt` varchar(150)  COLLATE utf8_general_ci NOT NULL DEFAULT '0' after `parent`, 
	CHANGE `url` `url` varchar(200)  COLLATE utf8_general_ci NULL DEFAULT '0' after `txt`, 
	CHANGE `target` `target` varchar(10)  COLLATE utf8_general_ci NULL DEFAULT '0' after `url`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `sideMenuCampus` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `sideMenuGroup` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `student_submission` 
	CHANGE `realname` `realname` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `owner`, 
	CHANGE `mime_type` `mime_type` varchar(20)  COLLATE utf8_general_ci NULL after `box_id`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `subject_news` 
	CHANGE `heading` `heading` varchar(50)  COLLATE utf8_general_ci NULL after `id`, 
	CHANGE `body` `body` mediumtext  COLLATE utf8_general_ci NOT NULL after `datetime_expires`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `submission_box` 
	CHANGE `description` `description` varchar(32)  COLLATE utf8_general_ci NULL after `deleted`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `submission_log` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `submission_return` 
	CHANGE `comment` `comment` varchar(255)  COLLATE utf8_general_ci NULL after `owner`, 
	CHANGE `mark` `mark` varchar(15)  COLLATE utf8_general_ci NULL after `comment`, 
	CHANGE `filename` `filename` varchar(80)  COLLATE utf8_general_ci NULL after `mark`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `superusers` COMMENT='', DEFAULT CHARSET='utf8';

/* Create table in target */
CREATE TABLE `testSummary`(
	`id` int(11) NOT NULL  auto_increment , 
	`title` varchar(50) COLLATE utf8_general_ci NOT NULL  , 
	`type` varchar(15) COLLATE utf8_general_ci NOT NULL  , 
	`grading` varchar(50) COLLATE utf8_general_ci NOT NULL  , 
	`description` varchar(500) COLLATE utf8_general_ci NOT NULL  , 
	`start` datetime NOT NULL  , 
	`finish` datetime NOT NULL  , 
	PRIMARY KEY (`id`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Alter table in target */
ALTER TABLE `uhomepage` 
	CHANGE `education` `education` mediumtext  COLLATE utf8_general_ci NULL after `owner`, 
	CHANGE `personal` `personal` mediumtext  COLLATE utf8_general_ci NULL after `education`, 
	CHANGE `school` `school` mediumtext  COLLATE utf8_general_ci NULL after `personal`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `user` 
	CHANGE `username` `username` varchar(60)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `id`, 
	CHANGE `password` `password` varchar(80)  COLLATE utf8_general_ci NULL after `username`, 
	CHANGE `title` `title` varchar(10)  COLLATE utf8_general_ci NULL after `group_id`, 
	CHANGE `firstname` `firstname` varchar(30)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `title`, 
	CHANGE `lastname` `lastname` varchar(30)  COLLATE utf8_general_ci NOT NULL DEFAULT '' after `firstname`, 
	CHANGE `email_pass` `email_pass` varchar(40)  COLLATE utf8_general_ci NULL after `lastname`, 
	CHANGE `alt_email` `alt_email` varchar(60)  COLLATE utf8_general_ci NULL after `local_email`, 
	ADD COLUMN `hash` varchar(40)  COLLATE utf8_general_ci NULL after `mail_handler`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `userAccess` 
	ADD KEY `sid`(`sid`), 
	ADD KEY `uid`(`uid`), COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `userCampus` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `userGroups` COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `welcomeComponent` 
	CHANGE `name` `name` varchar(100)  COLLATE utf8_general_ci NULL after `id`, 
	CHANGE `filename` `filename` varchar(100)  COLLATE utf8_general_ci NULL DEFAULT '0' after `name`, 
	CHANGE `function` `function` varchar(100)  COLLATE utf8_general_ci NULL DEFAULT '0' after `filename`, COMMENT='', DEFAULT CHARSET='utf8';

/* Alter table in target */
ALTER TABLE `welcomeLayout` COMMENT='', DEFAULT CHARSET='utf8';

/* Create table in target */
CREATE TABLE `wiki`(
	`wikiID` int(11) NOT NULL  auto_increment , 
	`categoryID` int(11) NULL  , 
	`created` datetime NULL  , 
	`deleted` enum('true','false') COLLATE utf8_general_ci NULL  DEFAULT 'false' , 
	PRIMARY KEY (`wikiID`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

/* Create table in target */
CREATE TABLE `homepageTemplate`(
	`id` int(11) NOT NULL  auto_increment , 
	`name` varchar(255) COLLATE utf8_general_ci NULL  , 
	`layout` smallint(6) NULL  DEFAULT '1' , 
	PRIMARY KEY (`id`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create table in target */
CREATE TABLE `homepageTemplateLayout`(
	`templateID` int(11) NULL  , 
	`componentId` int(11) NULL  , 
	`column` smallint(6) NULL  , 
	`position` smallint(6) NULL  , 
	UNIQUE KEY `table`(`templateID`,`componentId`,`column`,`position`) , 
	KEY `homepageID`(`templateID`) 
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

insert into `homepageTemplate` (`id`, `name`, `layout`) values('1','Class','1');
insert into `homepageTemplate` (`id`, `name`, `layout`) values('2','Collab Group','1');
insert into `homepageTemplate` (`id`, `name`, `layout`) values('4','Faculty','1');
insert into `homepageTemplate` (`id`, `name`, `layout`) values('3','Extra-Curricular','1');
insert into `homepageTemplate` (`id`, `name`, `layout`) values('5','Committee','1');
insert into `homepageTemplate` (`id`, `name`, `layout`) values('6','House','1');

insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','2','2','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','3','1','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','8','1','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','9','2','5');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','10','2','6');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','25','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','28','1','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','30','2','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('1','35','2','4');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','3','1','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','4','2','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','5','2','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','8','1','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','10','2','5');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','21','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','34','1','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('2','35','2','4');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','2','2','5');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','3','1','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','4','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','5','2','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','8','1','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','10','2','6');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','21','2','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','23','1','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','33','2','4');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('3','35','1','4');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','2','2','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','3','1','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','8','1','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','10','2','4');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','13','2','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','15','1','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('4','20','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','3','1','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','4','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','5','2','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','8','1','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','10','2','5');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','21','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','34','1','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('5','35','2','4');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','3','1','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','4','2','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','5','2','3');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','8','1','1');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','10','2','5');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','21','2','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','34','1','2');
insert into `homepageTemplateLayout` (`templateID`, `componentId`, `column`, `position`) values('6','35','2','4');

DROP TABLE homepages;

DROP TABLE ph_ext;

UPDATE homepage_comp SET id = 21 WHERE id = 1000;
UPDATE homepage_comp SET id = 22 WHERE id = 1001;
UPDATE homepage_comp SET id = 23 WHERE id = 1002;
UPDATE homepage_comp SET id = 24 WHERE id = 1003;
UPDATE homepage_comp SET id = 25 WHERE id = 1004;
UPDATE homepage_comp SET id = 26 WHERE id = 1005;
UPDATE homepage_comp SET id = 27 WHERE id = 1006;
UPDATE homepage_comp SET id = 28 WHERE id = 1007;
UPDATE homepage_comp SET id = 29 WHERE id = 1008;
UPDATE homepage_comp SET id = 30 WHERE id = 1009;
UPDATE homepage_comp SET id = 31 WHERE id = 1010;
UPDATE homepage_comp SET id = 32 WHERE id = 1011;

UPDATE homepage_comp_location SET component = 21 WHERE component = 1000;
UPDATE homepage_comp_location SET component = 22 WHERE component = 1001;
UPDATE homepage_comp_location SET component = 23 WHERE component = 1002;
UPDATE homepage_comp_location SET component = 24 WHERE component = 1003;
UPDATE homepage_comp_location SET component = 25 WHERE component = 1004;
UPDATE homepage_comp_location SET component = 26 WHERE component = 1005;
UPDATE homepage_comp_location SET component = 27 WHERE component = 1006;
UPDATE homepage_comp_location SET component = 28 WHERE component = 1007;
UPDATE homepage_comp_location SET component = 29 WHERE component = 1008;
UPDATE homepage_comp_location SET component = 30 WHERE component = 1009;
UPDATE homepage_comp_location SET component = 31 WHERE component = 1010;
UPDATE homepage_comp_location SET component = 32 WHERE component = 1011;


UPDATE homepage_comp_location SET component = 2 WHERE component = 6;
UPDATE homepage_comp_location SET component = 3 WHERE component = 7;
UPDATE homepage_comp_location SET component = 16 WHERE component = 11;
UPDATE homepage_comp_location SET component = 17 WHERE component = 12;
UPDATE homepage_comp_location SET component = 15 WHERE component = 14;
UPDATE homepage_comp_location SET component = 21 WHERE component = 22;
UPDATE homepage_comp_location SET component = 26 WHERE component = 27;
UPDATE homepage_comp_location SET component = 28 WHERE component = 29;

UPDATE homepage_comp SET invisible='true' WHERE id IN(6,7,11,12,14,22,27,29,32,31);


UPDATE file SET modified = submitted;
UPDATE link SET modified = submitted;

INSERT INTO categoryCode (categoryID, `code`)
SELECT `id`, `code` FROM category WHERE `code` IS NOT NULL AND `code` != '';
