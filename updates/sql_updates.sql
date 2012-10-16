-- End of Release 12.0.5

/* Add Countdown homepage component
 */
INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( NULL,'0','outputCountdown','Countdown','Displays a countdown to the specified time.','1','countdown.html','a:3:{s:13:"componentname";s:9:"Countdown";s:4:"time";s:0:"";s:4:"date";s:0:"";}','false','false');
/* Make return work comment a full 'text' field, rather than the 255 chars it is at the moment. */
ALTER TABLE `submission_return` CHANGE `comment` `comment` TEXT CHARSET utf8 COLLATE utf8_general_ci NULL;
-- Additional marktypes
INSERT INTO `submission_mark_type`
  (`hr_name`, `slug`, `comment`)
VALUES
  ('Rubric', 'rubric', 'To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.'),
  ('None', 'none', 'This due work is not marked.');

-- NEW TABLES!
CREATE TABLE `rubric_criterion`(
	`id` int(11) NOT NULL  auto_increment ,
	`grouping` varchar(32) COLLATE latin1_swedish_ci NOT NULL  ,
	`name` varchar(32) COLLATE latin1_swedish_ci NOT NULL  ,
	`parent_id` int(11) NOT NULL  ,
	`parent_type` enum('submission','category','curriculum') COLLATE latin1_swedish_ci NOT NULL  DEFAULT 'category' ,
	PRIMARY KEY (`id`) ,
	UNIQUE KEY `name`(`name`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_descriptor`(
	`criterion_id` int(10) unsigned NOT NULL  ,
	`submission_box_id` int(10) unsigned NOT NULL  ,
	`value` int(10) unsigned NOT NULL  ,
	`description` varchar(127) COLLATE latin1_swedish_ci NULL  ,
	PRIMARY KEY (`criterion_id`,`value`,`submission_box_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_mark`(
	`student_id` int(10) unsigned NOT NULL  ,
	`submission_box_id` int(10) unsigned NOT NULL  ,
	`criterion_id` int(10) unsigned NOT NULL  ,
	`tstamp` timestamp NOT NULL  DEFAULT CURRENT_TIMESTAMP ,
	`mark` int(10) unsigned NOT NULL  ,
	`valid` tinyint(1) NULL  DEFAULT 1 ,
	PRIMARY KEY (`student_id`,`submission_box_id`,`criterion_id`,`tstamp`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_result`(
	`resultset_id` int(11) NOT NULL  ,
	`value` int(11) NOT NULL  ,
	`label` varchar(32) COLLATE latin1_swedish_ci NOT NULL  ,
	PRIMARY KEY (`resultset_id`,`value`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_usage`(
	`criterion_id` int(11) NOT NULL  ,
	`submission_box_id` int(11) NOT NULL  ,
	PRIMARY KEY (`criterion_id`,`submission_box_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

-- Add rubric resultset field to submission box table
ALTER TABLE `submission_box`
	ADD COLUMN `rubric_result_set` int(10) unsigned   NOT NULL DEFAULT 0 after `max_mark`;

-- Upgrade comments to fulltext (might be redundant)
ALTER TABLE `submission_return`
	CHANGE `comment` `comment` text  COLLATE utf8_general_ci NULL after `owner`;


/* Add fields to submission box table */
ALTER TABLE `submission_box`
	CHANGE `accept_submission` `accept_submission` tinyint(1)   NOT NULL DEFAULT '0' after `fid`

	/* these columns have already been deprecated,
	ADD COLUMN `notif_enabled` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `max_mark`,
	ADD COLUMN `notif_sent` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `notif_enabled`
	*/
	;

/* Add global enable/disable to config */
INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`
)
VALUES (
  'due_work_notifications_enabled', '1', 'Due Work Notifications Enabled',
  'If ticked, students can automatically be sent reminder emails regarding upcoming due work.',
  'boolean', 'true:false', 'Email', 'true', 'false', 'false'
);


/* Add 'access' to the `action` enum */
ALTER TABLE `cmsLog` CHANGE `action` `action` ENUM('create','modify','delete','access') CHARSET utf8 COLLATE utf8_general_ci DEFAULT 'create' NOT NULL;
/* Create table for aggregated group homepage hits */
CREATE TABLE `homepage_visits`(
	`date` date NOT NULL  ,
	`user_group_id` int(10) unsigned NOT NULL  ,
	`category_id` int(10) unsigned NOT NULL  ,
	`hitcount` int(10) unsigned NULL  DEFAULT 1 ,
	PRIMARY KEY (`date`,`user_group_id`,`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

/* Create config definition table */
CREATE TABLE `component_config_definition`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`component_definition_id` int(10) unsigned NOT NULL  ,
	`key` varchar(32) COLLATE utf8_general_ci NOT NULL  ,
	`default_value` varchar(128) COLLATE utf8_general_ci NOT NULL  ,
	`label` varchar(64) COLLATE utf8_general_ci NULL  ,
	`description` varchar(512) COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`id`) ,
	UNIQUE KEY `component_definition_id`(`component_definition_id`,`key`) ,
	KEY `fk_dashboard_component_option_default_dashboard_component1`(`component_definition_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Create config instance table */
CREATE TABLE `component_config_instance`(
	`id` int(10) unsigned NOT NULL  auto_increment ,
	`component_config_definition_id` int(10) unsigned NOT NULL  ,
	`component_instance_id` int(10) unsigned NOT NULL  ,
	`value` varchar(128) COLLATE utf8_general_ci NULL  ,
	PRIMARY KEY (`id`) ,
	KEY `fk_dashboard_component_option_dashboard_component_instance1`(`component_config_definition_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';


/* Alter the definition table to allow for homepage components */
ALTER TABLE `component_definition`
	CHANGE `module` `module` enum('dashboard','homepage','eportfolio')  COLLATE utf8_general_ci NULL after `description`,
	ADD COLUMN `component_category` int(10) unsigned   NULL after `module`,
	CHANGE `visible` `visible` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'true' after `component_category`,
	CHANGE `multiple` `multiple` enum('false','true')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `visible`,
	ADD UNIQUE KEY `class`(`class`);

/* Alter the instance table to allow for homepage components */
ALTER TABLE `component_instance`
	CHANGE `module` `module` enum('dashboard','homepage','homepage_template','eportfolio')  COLLATE utf8_general_ci NULL after `component_definition_id`,
	CHANGE `position` `position` enum('top','left','right')  COLLATE utf8_general_ci NOT NULL DEFAULT 'left' after `identifier`,
	CHANGE `sequence` `sequence` int(10)   NOT NULL after `position`;

/* 'Move' surveys from learning to collaboration */
UPDATE homepage_comp SET homepageCategoryID = 2 WHERE method = 'outputSurvey';
DROP TABLE IF EXISTS `rc_cache`;
DROP TABLE IF EXISTS `rc_contactgroupmembers`;
DROP TABLE IF EXISTS `rc_contactgroups`;
DROP TABLE IF EXISTS `rc_contacts`;
DROP TABLE IF EXISTS `rc_identities`;
DROP TABLE IF EXISTS `rc_messages`;
DROP TABLE IF EXISTS `rc_session`;
DROP TABLE IF EXISTS `rc_users`;

DELETE FROM config WHERE name = 'mail_type';
DELETE FROM config WHERE name = 'mail_host';
DELETE FROM config WHERE name = 'mail_host_port';
ALTER TABLE `schoolbox`.`config` CHANGE `options` `options` VARCHAR(300) CHARSET utf8 COLLATE utf8_general_ci NULL;
UPDATE `schoolbox`.`config` SET `options`='Australia/Victoria:Australia/NSW:Australia/Queensland:Australia/West:Australia/South:Pacific/Auckland' WHERE `name`='date_timezone';
/* Allow multiple instances of Forum on homepages.
 * Alternate Source feature allows Forum modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='5';

/* Allow multiple instances of News on homepages.
 * Alternate Source feature allows News modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:2:{s:13:\"componentname\";s:4:\"News\";s:4:\"size\";s:1:\"0\";}' WHERE `id`='8';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='8';
/* Allow multiple instances of Image Gallery on homepages.
 * Alternate Source feature allows Image Gallery modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:3:{s:13:"componentname";s:13:"Image Gallery";s:4:"size";s:1:"0";s:21:"homepage-imagegallery";s:0:"";}' WHERE `id`='23';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='23';
/* Allow multiple instances of Blog on homepages.
 * Alternate Source feature allows Blog modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:4:{s:13:\"componentname\";s:4:\"Blog\";s:4:\"size\";s:1:\"3\";s:13:\"comments_size\";s:1:\"2\";s:13:\"homepage-blog\";s:0:\"\";}' WHERE `id`='39';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='39';
/* Allow multiple instances of Wiki on homepages.
 * Alternate Source feature allows Wiki modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:5:{s:13:"componentname";s:4:"Wiki";s:13:"homepage-wiki";s:0:"";s:4:"sort";s:7:"Heading";s:7:"sortdir";s:4:"Desc";s:4:"size";s:1:"0";}' WHERE `id`='34';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='34';
/* Allow multiple instances of Image Scroller on homepages.
 * Alternate Source feature allows Image Scroller modules from different homepages to accumulate
 */
-- UPDATE `homepage_comp` SET `config`='a:3:{s:13:"componentname";s:13:"Image Gallery";s:4:"size";s:1:"0";s:21:"homepage-imagegallery";s:0:"";}' WHERE `id`='23';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='41';
/* At some point, someone made the year column NOT NULL and it's not in any sql update files?
 * They did it in both stable and dev
 */
ALTER TABLE `user` CHANGE `year` `year` VARCHAR(3) CHARSET utf8 COLLATE utf8_general_ci NULL;
CREATE TABLE `user_assume_log`( `id` INT(11) NOT NULL AUTO_INCREMENT, `user` VARCHAR(255), `assumed` VARCHAR(255), `ip` VARCHAR(255), `created_at` DATETIME, PRIMARY KEY (`id`) );

/* Optional anonymous survey results
 */
ALTER TABLE `assess` ADD COLUMN `anonymous_result` INT(1) DEFAULT 0 NOT NULL AFTER `availability`;

/* Optional email notification whenever someone completes an attempt
 */
ALTER TABLE `assess` ADD COLUMN `notify_author` INT(1) DEFAULT 0 NOT NULL AFTER `anonymous_result`;
/* Set weather module cache directory in config table
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES ('weather_dir', '/var/www/schoolbox/data/cache/weather', 'Weather Cache Location', 'Location of Weather caching', 'input', NULL, 'Paths', 'false', 'false', 'true');

/* Allow Resit now becomes Max Attempts
 */
ALTER TABLE `assess` CHANGE `allow_resit` `max_attempts` INT(1) DEFAULT 0 NOT NULL;

/* Error message for making too many attempts
 */
INSERT INTO `error`(`id`,`message`) VALUES ( '146','You have reached the maximum number of attempts.');
/* MD has deprecated this file, since the system it is editing has been superceded in the same version update */

/* Add students homepage component
 */
-- INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( NULL,'0','outputClassList','Class Students','Output class list','3','students.html','','false','false');

/* Teachers homepage component now has optional avatars
 */
-- UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='30';
-- UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='30';

/* Students homepage component now has optional avatars
 */
-- UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='51';
-- UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='51';

/* Managers list now has optional avatars
 */
-- UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='4';
-- UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='4';

/* Members list  now has optional avatars
 */
-- UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='13';
-- UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='13';

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES ('storage_dir', '/var/www/schoolbox/data/storage', 'Data Store Location', 'Multi purpose storage engine, stores all files in this path', 'input', NULL, 'Paths', 'false', 'false', 'true');

CREATE TABLE `storage`( `storage_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `filename` VARCHAR(250) NOT NULL, `hash` VARCHAR(100) NOT NULL, `filesize` INT NOT NULL, `mime` VARCHAR(100), `uploaded_by` INT NOT NULL, `uploaded_on` DATETIME NOT NULL, `last_accessed` DATETIME, PRIMARY KEY (`storage_id`) );

ALTER TABLE `storage`
  ADD  UNIQUE INDEX `hash` (`hash`);
INSERT INTO `config`
  (`name`, `value`, `title`, `description`, `type`, `required`)
VALUES
  ('minify_dir', '/var/www/schoolbox/data/cache/minify', 'Data Minifier Location', 'Location of minifier cache files', 'Paths', 'true');
UPDATE `config`
SET `description` = 'By default display weather at this location. This value can be formatted as Country/City; latitude,longitude; airport code; or IP address.'
WHERE `name` = 'weather_location';
UPDATE `config` SET `title`='Enable WebDAV connector',`description`='Turns on webDAV, enabling the network drives tab' WHERE `name`='webdav_enable';
CREATE TABLE `lu_timetable_colour`( `timetable_colour_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `hex_colour` VARCHAR(8), PRIMARY KEY (`timetable_colour_id`) );

insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('17','ffe0cc');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('18','ccffcc');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('19','cce0ff');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('20','ffccf5');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('21','fff5cc');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('22','ccffe0');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('23','ccccff');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('24','ffcce0');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('25','f5ffcc');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('26','ccfff5');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('27','e0ccff');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('28','ffcccc');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('29','e0ffcc');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('30','ccf5ff');
insert into `lu_timetable_colour` (`timetable_colour_id`, `hex_colour`) values('31','f5ccff');
ALTER TABLE `news_items` ADD COLUMN `item_image` VARCHAR(100) NULL AFTER `item_group`;
UPDATE `config` SET `description`='Use this to control your automatic sync script, only use this if you have a sync script installed for your service' WHERE `name`='external_sync_data';


INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES
('sync_patterns', '', 'Sync User Types', '', 'input', NULL, 'External DB', 'false', 'false', 'true');


ALTER TABLE `config` CHANGE `type` `type` ENUM('Site','Security','Policy','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave') CHARSET utf8 COLLATE utf8_general_ci NULL;


UPDATE `config` SET `type`='Policy' WHERE `name`='policy';
UPDATE `config` SET `type`='Policy' WHERE `name`='news_edit_other';
UPDATE `config` SET `type`='Policy' WHERE `name`='group_staff_only';
UPDATE `config` SET `type`='Policy' WHERE `name`='protect_teacher_photo';
UPDATE `config` SET `type`='Policy' WHERE `name`='protect_student_photo';
UPDATE `config` SET `type`='Policy' WHERE `name`='news_staff_post_enabled';
UPDATE `config` SET `type`='Policy' WHERE `name`='news_default_expiry';
UPDATE `config` SET `type`='Policy' WHERE `name`='restrict_student_submissions';
UPDATE `config` SET `type`='Policy' WHERE `name`='restrict_parent_directory';
/* create new table for nofication handling */
CREATE TABLE `submission_notification` (
  `box_id` INT UNSIGNED NOT NULL,
  `type` ENUM('upcoming','overdue','returned') NOT NULL,
  `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `sent` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`box_id`, `type`),
  UNIQUE INDEX (`box_id`),
  UNIQUE INDEX (`type`)
) CHARSET=utf8;

/* Error messages
 */
INSERT INTO error (id, message) VALUES
(147, 'Please specify a class code, date and period.'),
(148, 'This class does not exist.'),
(149, 'Please specify a date in the format: yyyy-mm-dd'),
(150, 'Please specify a valid period number.'),
(151, 'You cannot edit attendance records this far in the past.'),
(152, 'You cannot edit attendance records in the future.');



UPDATE `config` SET value='12.5.0' WHERE name='site_version';

-- End of Release 12.5.0

