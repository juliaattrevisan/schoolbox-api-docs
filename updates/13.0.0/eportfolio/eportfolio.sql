/* Clone out the homepageTemplate
 */
CREATE TABLE `eportfolioTemplate`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `layout` SMALLINT(6), `left_column_width` FLOAT, `right_column_width` FLOAT, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Allow eportfolio_template in enum as they are synonymous with homepages
 */
ALTER TABLE `component_instance` CHANGE `module` `module` ENUM('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') CHARSET utf8 COLLATE utf8_general_ci NULL;

/* A list of component categories
 */
CREATE TABLE `eportfolioComponentCategory`( `eportfolioComponentCategoryID` INT(10) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`eportfolioComponentCategoryID`) );

/* Remove old tables
 */
DROP TABLE eportfolio_blog;
DROP TABLE eportfolio_blogComment;
DROP TABLE eportfolio_comp;
TRUNCATE eportfolio_comp_location;
TRUNCATE eportfolio_config;
DROP TABLE eportfolio_link;
DROP TABLE eportfolio_log;
DROP TABLE eportfolio_objective_award;
DROP TABLE eportfolio_objective_coverletter;
DROP TABLE eportfolio_objective_educationhistory;
DROP TABLE eportfolio_objective_employmenthistory;
DROP TABLE eportfolio_objective_goal;
DROP TABLE eportfolio_objective_interest;
DROP TABLE eportfolio_objective_skill;
DROP TABLE eportfolio_privateNotes;
DROP TABLE eportfolio_textBox;

/* Alter component_instance to allow eportfolio_template as an enum value
 */
ALTER TABLE `component_instance` CHANGE `module` `module` ENUM('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') CHARSET utf8 COLLATE utf8_general_ci NULL;

/* Repurpose eportfolio_config to contain information about pages
 */
ALTER TABLE `eportfolio_config` ADD COLUMN `page_id` INT(11) NOT NULL AUTO_INCREMENT FIRST, ADD COLUMN `page_title` VARCHAR(255) NULL AFTER `page_id`, CHANGE `uid` `uid` INT(6) DEFAULT 0 NOT NULL, CHANGE `template` `template` TINYINT(1) DEFAULT 0 NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (`page_id`);

/* Add permissions relation table
 */
CREATE TABLE `eportfolio_permission`( `id` INT(11) NOT NULL AUTO_INCREMENT, `page_id` INT(11) NOT NULL, `type` ENUM('parent','group','student') NOT NULL DEFAULT 'student', `value` INT(11) NOT NULL, PRIMARY KEY (`id`) );

/* Components tables
 */

/* Add list item table
 */
CREATE TABLE `eportfolio_component_list_item`( `list_item_id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `content` TEXT, `sequence` INT(11), PRIMARY KEY (`list_item_id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;
ALTER TABLE `eportfolio_component_list_item` CHANGE `list_item_id` `id` INT(11) NOT NULL AUTO_INCREMENT;

/* Add advanced list item table
 */
CREATE TABLE `eportfolio_component_advancedlist_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `title` VARCHAR(255), `content` TEXT, `imagehash` VARCHAR(255), `sequence` INT(11), PRIMARY KEY (`id`) );

/* Add link list item table
 */
CREATE TABLE `eportfolio_component_linklist_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `title` VARCHAR(255), `content` TEXT, `sequence` INT(1), PRIMARY KEY (`id`) );

/* Add textbox item table
 */
CREATE TABLE `eportfolio_component_textbox_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `content` TEXT, `sequence` INT(11), PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Remove excess tables again
 */
DROP TABLE eportfolio_comp_category;
DROP TABLE eportfolio_comp_location;
DROP TABLE eportfolioComponentCategory;
DROP TABLE eportfolioTemplateLayout;

/* Add showcased work table
 */
CREATE TABLE `eportfolio_showcase_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `submission_return_id` INT(11), `uid` INT(11), PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;
ALTER TABLE `eportfolio_showcase_item` ADD COLUMN `submission_box_id` INT(11) NULL AFTER `id`;
ALTER TABLE `eportfolio_showcase_item` CHANGE `submission_box_id` `submission_id` INT(11) NULL;
ALTER TABLE `schoolbox`.`eportfolio_showcase_item` DROP COLUMN `submission_return_id`, CHANGE `submission_id` `submission_box_id` INT(11) NULL;