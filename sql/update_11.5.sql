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
INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( '44','1','outputSurvey','Surveys','Online surveys.','3','survey.html','','false','false');

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
UPDATE homepage_comp SET description = "Subscribe to an audio-only podcast." WHERE method = 'outputPodcast';

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
UPDATE `config` SET `title`='Schoolbox Feedback Address' WHERE `configID`='68';
UPDATE `config` SET `value`='schoolbox@alaress.com.au' WHERE `configID`='68';
UPDATE `config` SET `value`='schoolbox@alaress.com.au' WHERE `configID`='66';
UPDATE `config` SET `title`='External DB Schoolbox URL' WHERE `configID`='56';

/* Group Type
 */
ALTER TABLE groups ADD COLUMN group_type enum ('guest','student','parent','staff') DEFAULT 'guest';
UPDATE `groups` SET `group_type`='staff' WHERE `id`='1';
UPDATE `groups` SET `group_type`='staff' WHERE `id`='2';
UPDATE `groups` SET `group_type`='student' WHERE `id`='3';
UPDATE `groups` SET `group_type`='parent' WHERE `id`='4';
UPDATE `groups` SET `group_type`='student' WHERE `id`='5';

/* Group Types can change password
 */
ALTER TABLE groups ADD COLUMN can_change_password ENUM ('true', 'false') DEFAULT 'true';