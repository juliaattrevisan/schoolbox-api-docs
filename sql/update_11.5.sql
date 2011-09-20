UPDATE `config`
SET `value` = '11.5' where `name` = 'site_version';

/* Added availability for questions and assessments/surveys
 */
ALTER TABLE `assess_luQuestionType` ADD COLUMN `availability` ENUM('assess','survey','all') NULL AFTER `automated_marking`;
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='1';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='2';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='3';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='4';

ALTER TABLE `assess` ADD COLUMN `availability` ENUM('assess','survey') DEFAULT 'assess' NULL AFTER `allow_resit`;

/* Survey homepage component
 */
INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( '44','1','outputSurvey','Surveys','Create surveys with multiple questions and graphical summaries','3','survey.html','','false','false');

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
ALTER TABLE `eportfolio_textBox` ADD COLUMN componentLocationID INT(11) NULL ;

/* New category column widths
 */
ALTER TABLE homepage_config ADD COLUMN right_column_width FLOAT DEFAULT 49.5 AFTER template;
ALTER TABLE homepage_config ADD COLUMN left_column_width FLOAT DEFAULT 49.5 AFTER template;

ALTER TABLE eportfolio_config ADD COLUMN right_column_width FLOAT DEFAULT 49.5 AFTER template;
ALTER TABLE eportfolio_config ADD COLUMN left_column_width FLOAT DEFAULT 49.5 AFTER template;

/* Podcast update
 */
UPDATE homepage_comp SET description = "Provides an audio player for podcast feeds" WHERE method = 'outputPodcast';

UPDATE homepage_comp SET `description`='Provides an area for teachers to receive files from students without the need for a submission box and due date' WHERE `method`='outputDropBox';
UPDATE homepage_comp SET `description`='Provide links to interesting, related and helpful resources on Schoolbox or the WWW.' WHERE `method`='outputLinkList';

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

/* Policy
 */
INSERT INTO config (`name`, title, description, field, `type`, editable, required) VALUES ('policy', 'Policy', 'A policy description which appears in the footer of each page.','textarea','Site','true','true');

/* Add new policies for portrait photos */
INSERT INTO config (`name`, title, description, field, `value`, `type`, editable, required) VALUES ('protect_teacher_photo', 'Hide Teacher Photo', 'Tick this box to prevent teacher photos being displayed to student and parents in search results.','boolean','1', 'Resources','true','true');
INSERT INTO config (`name`, title, description, field, `value`, `type`, editable, required) VALUES ('protect_student_photo', 'Hide Student Photo', 'Tick this box to hide student photos from other students','boolean','1', 'Resources','true','true');

/* Added in support for human readable bytes in config */
ALTER TABLE `config` CHANGE `field` `field` ENUM('input','textarea','boolean','select','checkbox','bytes') CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT 'input' NULL ;
UPDATE `config` SET `field`='bytes', description='The maximum size of a single file that can be uploaded. Excepts human formats like 40M and 1GB.' WHERE `name` like 'max_file_size';

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
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveSkill', 'Skills', 'A list of your skills', 2, 'objectiveSkill.html', 'false');

/* Eportfolio Objective: Goals
 */
CREATE TABLE eportfolio_objective_goal (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveGoal', 'Goals', 'A list of your goals', 2, 'objectiveGoal.html', 'false');

/* Eportfolio Objective: Awards
 */
CREATE TABLE eportfolio_objective_award (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveAward', 'Awards', 'A list of your awards', 2, 'objectiveAward.html', 'false');

/* Eportfolio Objective: Cover Letter
 */
CREATE TABLE eportfolio_objective_coverletter (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveCoverLetter', 'Cover Letter', 'A cover letter for your resume', 2, 'objectiveCoverLetter.html', 'false');

/* Eportfolio Objective: Interest
 */
CREATE TABLE eportfolio_objective_interest (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveInterest', 'Interests', 'A list of interests for your resume', 2, 'objectiveInterest.html', 'false');

/* Eportfolio Objective: Employment History
 */
CREATE TABLE eportfolio_objective_employmenthistory (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveEmploymentHistory', 'Employment History', 'An employment history for your resume', 2, 'objectiveEmploymentHistory.html', 'false');

/* Eportfolio Objective: Education History
 */
CREATE TABLE eportfolio_objective_educationhistory (
	id BIGINT(20) NOT NULL AUTO_INCREMENT,
	user_id BIGINT(20),
	component_location_id BIGINT(20),
	body TEXT,
PRIMARY KEY (id));

INSERT INTO eportfolio_comp (size,  method, `name`, description, eportfolioCategoryID, filename, allowMultiple) VALUES (1,  'outputObjectiveEducationHistory', 'Education History', 'An education history for your resume', 2, 'objectiveEducationHistory.html', 'false');


