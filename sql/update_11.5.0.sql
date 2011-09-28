UPDATE `config`
SET `value` = '11.5.0' where `name` = 'site_version';

/* Added availability for questions and assessments/surveys
 */
ALTER TABLE `assess_luQuestionType` ADD COLUMN `availability` ENUM('assess','survey','all') NULL AFTER `automated_marking`;
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='1';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='2';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='3';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='4';

ALTER TABLE `assess` ADD COLUMN `availability` ENUM('assess','survey') DEFAULT 'assess' NULL AFTER `allow_resit`,
    ADD KEY `fk_assess_category1`(`category_id`),
	ADD KEY `fk_assess_user1`(`user_id`);


ALTER TABLE `assess_answer`
	ADD KEY `fk_assess_answer_assess_question1`(`question_id`);


ALTER TABLE `assess_attempt`
	ADD KEY `fk_assess_attempt_assess1`(`assess_id`),
	ADD KEY `fk_assess_attempt_user1`(`user_id`);


ALTER TABLE `assess_question`
	ADD KEY `fk_assess_question_assess_luQuestionType1`(`type_id`),
	ADD KEY `fk_assess_question_assess_section1`(`section_id`);


ALTER TABLE `assess_response`
	ADD KEY `fk_assess_response_assess_attempt1`(`attempt_id`),
	ADD KEY `fk_assess_response_assess_question1`(`question_id`),
	ADD KEY `fk_assess_response_assess_section1`(`section_id`);


ALTER TABLE `assess_section`
	ADD KEY `fk_assess_section_assess1`(`assess_id`);


ALTER TABLE `blog`
	ADD KEY `fk_blog_category1`(`cid`),
	ADD KEY `fk_blog_user1`(`owner`);


ALTER TABLE `blogComment`
	ADD KEY `fk_blogComment_blog1`(`bid`),
	ADD KEY `fk_blogComment_user1`(`owner`);


ALTER TABLE `categoryCode`
	ADD KEY `fk_categoryCode_category1`(`categoryID`);


ALTER TABLE `chat`
	ADD KEY `fk_chat_category1`(`category_id`),
	ADD KEY `fk_chat_user1`(`user_id`);


ALTER TABLE `chat_poll`
	ADD KEY `fk_chat_poll_category1`(`category_id`),
	ADD KEY `fk_chat_poll_user1`(`user_id`);

ALTER TABLE `dropbox`
	ADD KEY `fk_dropbox_user1`(`owner`);

ALTER TABLE `eportfolio_blog`
	ADD KEY `fk_eportfolio_blog_user1`(`owner`);

ALTER TABLE `eportfolio_blogComment`
	ADD KEY `fk_eportfolio_blogComment_eportfolio_blog1`(`bid`),
	ADD KEY `fk_eportfolio_blogComment_user1`(`owner`);

ALTER TABLE `eportfolio_comp`
	CHANGE `method` `method` varchar(255)  COLLATE utf8_general_ci NULL after `size`,
	CHANGE `filename` `filename` varchar(255)  COLLATE utf8_general_ci NULL after `eportfolioCategoryID`,
	ADD KEY `fk_eportfolio_comp_eportfolio_comp_category1`(`eportfolioCategoryID`);

ALTER TABLE `eportfolio_comp_location`
	ADD KEY `fk_eportfolio_comp_location_eportfolio_comp1`(`component`);

ALTER TABLE `eportfolio_comp` 
	ADD KEY `fk_eportfolio_comp_eportfolio_comp_category1`(`eportfolioCategoryID`);

ALTER TABLE `eportfolio_comp_location`
	ADD KEY `fk_eportfolio_comp_location_eportfolio_comp1`(`component`);

/* Alter table in target */
ALTER TABLE `eportfolioTemplateLayout`
	ADD KEY `fk_eportfolioTemplateLayout_eportfolio_comp1`(`componentId`);

/* Alter table in target */
ALTER TABLE `event`
	ADD FULLTEXT KEY `fulltext`(`note`,`detail`,`location`);

	

/* New welcome page DB stuff. */
CREATE TABLE `component_definition`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`name` varchar(64) COLLATE utf8_general_ci NOT NULL  ,
	`class` varchar(64) COLLATE utf8_general_ci NOT NULL  ,
	`description` varchar(512) COLLATE utf8_general_ci NULL  ,
	`module` enum('dashboard','homepage','eportfolio') COLLATE utf8_general_ci NULL  DEFAULT 'dashboard' ,
	`visible` enum('true','false') COLLATE utf8_general_ci NULL  DEFAULT 'true' ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

insert  into `component_definition`(`id`,`name`,`class`,`description`,`module`,`visible`) values (1,'Timetable','Component_Dashboard_TimetableController',NULL,'dashboard','true'),(2,'Greeting','Component_Dashboard_GreetingController',NULL,'dashboard','true'),(3,'Calendar','Component_Dashboard_CalendarController',NULL,'dashboard','true'),(4,'News','Component_Dashboard_NewsController',NULL,'dashboard','true'),(5,'Weather','Component_Dashboard_WeatherController',NULL,'dashboard','true'),(6,'Activity','Component_Dashboard_ActivityController',NULL,'dashboard','true'),(7,'Child Subject','Component_Dashboard_ChildSubjectController',NULL,'dashboard','true'),(8,'Helpdesk','Component_Dashboard_HelpdeskController',NULL,'dashboard','false'),(9,'Link','Component_Dashboard_LinkController',NULL,'dashboard','true'),(11,'Parent Helpdesk','Component_Dashboard_ParentHelpdeskController',NULL,'dashboard','false'),(13,'RSS','Component_Dashboard_RssController',NULL,'dashboard','true'),(14,'Shortcut','Component_Dashboard_ShortcutController',NULL,'dashboard','false'),(15,'Suggestion','Component_Dashboard_SuggestionController',NULL,'dashboard','false'),(16,'Tutorial','Component_Dashboard_TutorialController',NULL,'dashboard','false'),(17,'Wenona Intranet','Component_Dashboard_WenonaIntranetController',NULL,'dashboard','false');

CREATE TABLE `component_instance`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`component_definition_id` int(10) unsigned NOT NULL  ,
	`module` varchar(128) COLLATE utf8_general_ci NOT NULL  ,
	`identifier` int(10) unsigned NOT NULL  COMMENT 'Foreign ID is used to reference the context of the instance. (Polymorphic)' ,
	`position` varchar(32) COLLATE utf8_general_ci NOT NULL  ,
	`sequence` int(10) unsigned NOT NULL  ,
	PRIMARY KEY (`id`) ,
	KEY `fk_dashboard_component_instance_dashboard_component1`(`component_definition_id`) ,
	KEY `fk_dashboard_component_instance_groups1`(`identifier`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `component_option`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`component_option_default_id` int(10) unsigned NOT NULL  ,
	`instance_id` int(10) unsigned NOT NULL  ,
	`value` text COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`id`) ,
	KEY `fk_dashboard_component_option_dashboard_component_instance1`(`instance_id`) ,
	KEY `fk_component_option_component_option_default1`(`component_option_default_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `component_option_definition`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`component_definition_id` int(10) unsigned NOT NULL  ,
	`key` varchar(32) COLLATE utf8_general_ci NOT NULL  ,
	`type` int(11) NOT NULL  ,
	`value` text COLLATE utf8_general_ci NOT NULL  ,
	`default` text COLLATE utf8_general_ci NULL  ,
	`label` varchar(64) COLLATE utf8_general_ci NULL  ,
	`description` varchar(512) COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`id`) ,
	KEY `fk_dashboard_component_option_default_dashboard_component1`(`component_definition_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';




/* Survey homepage component
 */
INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( '44','1','outputSurvey','Surveys','Create surveys for your homepage visitors with various question types and graphical summaries','3','survey.html','','false','false');

/* Whether or not exported responses for a question have a legend
 * Also applicable for View All Responses for a Survey question
 */
ALTER TABLE assess_luQuestionType ADD COLUMN has_legend ENUM('true', 'false') DEFAULT 'true';
UPDATE assess_luQuestionType SET has_legend='false' WHERE type_id='1';

/* Deprecate Image Simple, rename Image Advanced
 */
UPDATE homepage_comp SET invisible = 'true' where id = 14;
UPDATE homepage_comp SET `name`= 'Image' WHERE id = 15;

/* Error messages for category creation
 */
INSERT INTO error VALUES (136, "Unable to create or modify folder as class code already exists.");

/* Spelling correction.
 */
UPDATE `config` SET `title`='Synchronise Data' WHERE `configID`='57';

/* Eportfolio textbox component
 */
ALTER TABLE `eportfolio_textBox`
	ADD COLUMN `componentLocationID` int(11)   NULL after `userID`,  
	DROP KEY `categoryID`, add KEY `categoryID`(`componentLocationID`);

/* New category column widths
 */
ALTER TABLE homepage_config ADD COLUMN right_column_width FLOAT DEFAULT 79.5 AFTER template;
ALTER TABLE homepage_config ADD COLUMN left_column_width FLOAT DEFAULT 29.5 AFTER template;

UPDATE homepage_config SET left_column_wdith=79.5, right_column_width = 29.5  WHERE homepage_config WHERE template = 1;
UPDATE homepage_config SET left_column_wdith=49.5, right_column_width = 49.5 WHERE homepage_config WHERE template = 2;
UPDATE homepage_config SET left_column_wdith=29.5, right_column_width = 79.5 WHERE homepage_config WHERE template = 4;
UPDATE homepage_comp_location, homepage_config SET `column` = 3 WHERE homepage_comp_location.cid = homepage_config.cid AND homepage_config.template=3

ALTER TABLE eportfolio_config ADD COLUMN right_column_width FLOAT DEFAULT 79.5 AFTER template;
ALTER TABLE eportfolio_config ADD COLUMN left_column_width FLOAT DEFAULT 29.5 AFTER template;

/* Podcast update
 */
UPDATE homepage_comp SET description = 'Provides an embedded audio player to enable the playback of audio from a podcast feed.' WHERE method = 'outputPodcast';

UPDATE homepage_comp SET `description`='Provides an area for teachers to receive files from students without the need for a submission box and due date' WHERE `method`='outputDropBox';
UPDATE homepage_comp SET `description`='Provide links to interesting, related and helpful resources on Schoolbox or the WWW.' WHERE `method`='outputLinkList';
UPDATE homepage_comp SET title='Image Carousel', `description`='Automatically fades through all the images uploaded to this homepage.' WHERE `method`='outputScroller';

/* Optional config fields for validation
 */
ALTER TABLE config ADD COLUMN required ENUM ('false', 'true') DEFAULT 'false';
UPDATE config SET required = 'true';

/* Disable LDAP mandatoryness
 * Disable Webmail URL mandatoryness
 * Disable External DB mandatoryness
 */
UPDATE `config` SET `required`='false' WHERE `configID` IN (13,14,15,16,17,18,19,20,21);
UPDATE `config` SET `required`='false' WHERE `configID` IN (65);
UPDATE `config` SET `required`='false' WHERE `configID` IN (50,51,52,53,54,55,56,57);

/* Update config to remove references to Portal
 */
UPDATE `config` SET `title`='Schoolbox Feedback Address' WHERE `name` like 'mail_support_address';
UPDATE `config` SET `title`='External DB Portal URL' WHERE `name`='external_portal';

/* Group Type
 */
ALTER TABLE `groups` DROP COLUMN `staff`;
ALTER TABLE `groups` ADD COLUMN group_type enum ('guest','student','parent','staff') DEFAULT 'guest';
UPDATE `groups` SET `group_type`='staff' WHERE `id`='1';
UPDATE `groups` SET `group_type`='staff' WHERE `id`='2';
UPDATE `groups` SET `group_type`='student' WHERE `id`='3';
UPDATE `groups` SET `group_type`='parent' WHERE `id`='4';
UPDATE `groups` SET `group_type`='student' WHERE `id`='5';

/* Group Types can change password
 */
ALTER TABLE groups ADD COLUMN can_change_password ENUM ('true', 'false') DEFAULT 'true';

/* Wiki sort options
 */
UPDATE `homepage_comp` SET `config`='a:3:{s:4:\"sort\";s:5:\"Title\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}' WHERE `id`='34';

/* Wiki stub
 */
ALTER TABLE wiki ADD COLUMN stub ENUM('false', 'true') DEFAULT 'false';
UPDATE `homepage_comp` SET `config`='a:3:{s:4:\"sort\";s:7:\"Heading\";s:7:\"sortdir\";s:4:\"Desc\";s:4:\"size\";s:1:\"0\";}' WHERE `id`='34';

/* Policy
 */
INSERT INTO config (`name`, title, description, field, `type`, editable, required) VALUES ('policy', 'Policy', 'A policy description which appears in the footer of each page.','textarea','Site','true','true');

/* Add new policies for portrait photos */
INSERT INTO config (`name`, title, description, field, `value`, `type`, editable, required) VALUES ('protect_teacher_photo', 'Hide Teacher Photo', 'Tick this box to prevent teacher photos being displayed to student and parents in search results.','boolean','1', 'Resources','true','true');
INSERT INTO config (`name`, title, description, field, `value`, `type`, editable, required) VALUES ('protect_student_photo', 'Hide Student Photo', 'Tick this box to hide student photos from other students','boolean','1', 'Resources','true','true');

/* Added in support for human readable bytes in config */
ALTER TABLE `config` CHANGE `field` `field` ENUM('input','textarea','boolean','select','checkbox','bytes') CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT 'input' NULL ;
UPDATE `config` SET `field`='bytes', description='The maximum size of a single file that can be uploaded. Excepts human formats like 40M and 1GB.' WHERE `name` like 'max_file_size';


/* News time */
/* Alter table in target */
ALTER TABLE `news_items`  ADD COLUMN `item_datetime_published` datetime   NOT NULL after `item_datetime_posted`;

ALTER TABLE `subject_news` ADD COLUMN `datetime_published` datetime   NULL after `datetime_posted`; 

/* EPORTFOLIO BITS
 */

/* Lengthen name, it's too short
 */
ALTER TABLE `eportfolio_comp` CHANGE `method` `method` VARCHAR(255);
ALTER TABLE `eportfolio_comp` CHANGE `filename` `filename` VARCHAR(255);

/* Resume grouping
 */
INSERT INTO `eportfolio_comp_category`(`eportfolioComponentCategoryID`,`name`) VALUES ( '2','Resume');

/* Eportfolio Objective: Skills
 */
CREATE TABLE eportfolio_objective_skill (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveSkill', 'Skills', 'A list of your skills for your resume', 2, 'objectiveSkill.html', 'false');

/* Eportfolio Objective: Goals
 */
CREATE TABLE eportfolio_objective_goal (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveGoal', 'Goals', 'A list of your goals for your resume', 2, 'objectiveGoal.html', 'false');

/* Eportfolio Objective: Awards
 */
CREATE TABLE eportfolio_objective_award (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveAward', 'Awards', 'A list of your awards for your resume', 2, 'objectiveAward.html', 'false');

/* Eportfolio Objective: Cover Letter
 */
CREATE TABLE eportfolio_objective_coverletter (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveCoverLetter', 'Cover Letter', 'A cover letter for your resume', 2, 'objectiveCoverLetter.html', 'false');

/* Eportfolio Objective: Interest
 */
CREATE TABLE eportfolio_objective_interest (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveInterest', 'Interests', 'A list of interests for your resume', 2, 'objectiveInterest.html', 'false');

/* Eportfolio Objective: Employment History
 */
CREATE TABLE eportfolio_objective_employmenthistory (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveEmploymentHistory', 'Employment History', 'An employment history for your resume', 2, 'objectiveEmploymentHistory.html', 'false');

/* Eportfolio Objective: Education History
 */
CREATE TABLE eportfolio_objective_educationhistory (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveEducationHistory', 'Education History', 'An education history for your resume', 2, 'objectiveEducationHistory.html', 'false');

/* Resume (Objective Component composite)
 * Uncomment when you need a standard format
 */
-- INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputResumeStandard', 'Standard Resume', 'A standard resume', 2, 'resume/standard.html', 'false');
-- INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputResumeExtended', 'Extended Resume', 'An extended resume', 2, 'resume/extended.html', 'false');

/* Add in 3rd column css support (top area) */
ALTER TABLE `homepage_config`     ADD COLUMN `top_head_font` CHAR(7) NULL AFTER `right_hover`,     ADD COLUMN `top_head_bg` CHAR(7) NULL AFTER `top_head_font`,     ADD COLUMN `top_font` CHAR(7) NULL AFTER `top_head_bg`,     ADD COLUMN `top_bg_primary` CHAR(7) NULL AFTER `top_font`,     ADD COLUMN `top_bg_secondary` CHAR(7) NULL AFTER `top_bg_primary`,     ADD COLUMN `top_link` CHAR(7) NULL AFTER `top_bg_secondary`,     ADD COLUMN `top_hover` CHAR(7) NULL AFTER `top_link`;
ALTER TABLE `eportfolio_config`     ADD COLUMN `top_head_font` CHAR(7) NULL AFTER `right_hover`,     ADD COLUMN `top_head_bg` CHAR(7) NULL AFTER `top_head_font`,     ADD COLUMN `top_font` CHAR(7) NULL AFTER `top_head_bg`,     ADD COLUMN `top_bg_primary` CHAR(7) NULL AFTER `top_font`,     ADD COLUMN `top_bg_secondary` CHAR(7) NULL AFTER `top_bg_primary`,     ADD COLUMN `top_link` CHAR(7) NULL AFTER `top_bg_secondary`,     ADD COLUMN `top_hover` CHAR(7) NULL AFTER `top_link`;


/* Convert default colours to config options */
ALTER TABLE `config` CHANGE `type` `type` enum('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB','Colours')  COLLATE utf8_general_ci NULL after `options`,
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_head_font','013a63','Heading Text','Colour of the heading text on homepage and eportfolio components','input',NULL,'Colours','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_head_bg','cae9b9','Heading Background','Colour of the background of the headings on homepage and eportfolio components','input',NULL,'Colours','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_font','000000','Body Text','Colour of the body text on homepage and eportfolio components','input',NULL,'Colours','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_bg_primary','ffffff','Primary Background','Primary background colour on homepage and eportfolio components','input',NULL,'Colours','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_bg_secondary','faf9f5','Secondary Background','Secondary background colour homepage and eportfolio components','input',NULL,'Colours','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_link','1d9ffc','Link','Colour of links on homepage and eportfolio components','input',NULL,'Colours','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'homepage_colour_hover','ff5a00','Link Hover','Colour of links when your mouse over them on homepage and eportfolio components','input',NULL,'Colours','true','false','false');

/* Added in new category to eportfolio components */
INSERT INTO `eportfolio_comp_category`(`eportfolioComponentCategoryID`,`name`) VALUES ( NULL,'Images');
UPDATE `eportfolio_comp` SET eportfolioCategoryID = (SELECT eportfolioComponentCategoryID FROM `eportfolio_comp_category` WHERE name like 'Images') WHERE method like 'outputImage%';


/* Add in seperation of questionbank */


/* Add in support for new question templates */
ALTER TABLE `portal-dev`.`assess_luQuestionType`     ADD COLUMN `template_dir` VARCHAR(255) NULL AFTER `filename`;

ALTER TABLE `allowedFileExtension`
	CHANGE `extension` `extension` char(12)  COLLATE utf8_general_ci NULL after `fileTypeID`;

CREATE TABLE `assess_question_bank`(
	`question_bank_id` bigint(20) NOT NULL  auto_increment ,
	`question_category_id` bigint(20) NULL  ,
	`type_id` bigint(20) NULL  ,
	`question` text COLLATE utf8_general_ci NULL  ,
	`answer` text COLLATE utf8_general_ci NULL  ,
	`marks` bigint(20) NULL  ,
	`deleted_at` datetime NULL  ,
	PRIMARY KEY (`question_bank_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `assess_question_category`(
	`question_category_id` bigint(20) NOT NULL  auto_increment ,
	`name` varchar(255) COLLATE utf8_general_ci NULL  ,
	`availability` enum('assess','survey') COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`question_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

/* Create table in target */
CREATE TABLE `plagscan_document`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`file_id` int(11) NOT NULL  ,
	`plagscan_id` int(11) NOT NULL  ,
	`status` int(11) NOT NULL  DEFAULT '0' ,
	`score` float unsigned NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

/* Add description to submission box */
ALTER TABLE `submission_box`
	CHANGE `description` `description` varchar(750)  COLLATE utf8_general_ci NOT NULL after `deleted`,
	ADD COLUMN `name` varchar(150)  COLLATE utf8_general_ci NULL after `description`,
	CHANGE `created` `created` datetime   NOT NULL DEFAULT '0000-00-00 00:00:00' after `name`;

ALTER TABLE `submission_return`
	CHANGE `comment` `comment` varchar(255)  COLLATE utf8_general_ci NULL after `owner`,
	CHANGE `submitted` `submitted` datetime   NULL after `deleted`,
	CHANGE `returned` `returned` datetime   NOT NULL after `submitted`;