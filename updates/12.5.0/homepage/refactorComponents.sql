
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