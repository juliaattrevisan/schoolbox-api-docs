/* Clone out the homepageTemplate
 */
CREATE TABLE `eportfolioTemplate`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `layout` SMALLINT(6), `left_column_width` FLOAT, `right_column_width` FLOAT, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Allow eportfolio_template in enum as they are synonymous with homepages
 */
ALTER TABLE `component_instance` CHANGE `module` `module` ENUM('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') CHARSET utf8 COLLATE utf8_general_ci NULL;

/* Remove old tables
 */
-- DROP TABLE eportfolio_blog;
-- DROP TABLE eportfolio_blogComment;
-- DROP TABLE eportfolio_comp;
-- TRUNCATE eportfolio_comp_location;
-- TRUNCATE eportfolio_config;
-- DROP TABLE eportfolio_link;
-- DROP TABLE eportfolio_log;
-- DROP TABLE eportfolio_objective_award;
-- DROP TABLE eportfolio_objective_coverletter;
-- DROP TABLE eportfolio_objective_educationhistory;
-- DROP TABLE eportfolio_objective_employmenthistory;
-- DROP TABLE eportfolio_objective_goal;
-- DROP TABLE eportfolio_objective_interest;
-- DROP TABLE eportfolio_objective_skill;
-- DROP TABLE eportfolio_privateNotes;
-- DROP TABLE eportfolio_textBox;
-- DROP TABLE eportfolio_comp_category;
-- DROP TABLE eportfolio_comp_location;
-- DROP TABLE eportfolioComponentCategory;
-- DROP TABLE eportfolioTemplateLayout;

/* Alter component_instance to allow eportfolio_template as an enum value
 */
ALTER TABLE `component_instance` CHANGE `module` `module` ENUM('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') CHARSET utf8 COLLATE utf8_general_ci NULL;

/* Add page table
 */
CREATE TABLE `eportfolio_page`( `page_id` INT(11) NOT NULL AUTO_INCREMENT, `page_title` VARCHAR(255), `uid` INT(11), `template` INT(11), `left_column_width` FLOAT, `right_column_width` FLOAT, `left_head_font` VARCHAR(7), `left_head_bg` VARCHAR(7), `left_font` VARCHAR(7), `left_bg_primary` VARCHAR(7), `left_bg_secondary` VARCHAR(7), `left_link` VARCHAR(7), `left_hover` VARCHAR(7), `right_head_font` VARCHAR(7), `right_head_bg` VARCHAR(7), `right_font` VARCHAR(7), `right_bg_primary` VARCHAR(7), `right_bg_secondary` VARCHAR(7), `right_link` VARCHAR(7), `right_hover` VARCHAR(7), `top_head_font` VARCHAR(7), `top_head_bg` VARCHAR(7), `top_font` VARCHAR(7), `top_bg_primary` VARCHAR(7), `top_bg_secondary` VARCHAR(7), `top_link` VARCHAR(7), `top_hover` VARCHAR(7), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`page_id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add permissions relation table
 */
CREATE TABLE `eportfolio_permission`( `id` INT(11) NOT NULL AUTO_INCREMENT, `page_id` INT(11) NOT NULL, `type` ENUM('parent','group','student') NOT NULL DEFAULT 'student', `value` INT(11) NOT NULL, PRIMARY KEY (`id`) );

/* Add error for nonexisting eportfolio pages
 */
INSERT INTO `error` (`id`, `message`) VALUES ('156', 'This page does not exist.');

/* Components tables
 */

/* Add list item table
 */
CREATE TABLE `eportfolio_component_basiclist_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `content` TEXT, `sequence` INT(11), PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add advanced list item table
 */
CREATE TABLE `eportfolio_component_advancedlist_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `title` VARCHAR(255), `content` TEXT, `imagehash` VARCHAR(255), `sequence` INT(11), PRIMARY KEY (`id`) );

/* Add link list item table
 */
CREATE TABLE `eportfolio_component_linklist_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `title` VARCHAR(255), `content` TEXT, `sequence` INT(1), PRIMARY KEY (`id`) );

/* Add textbox item table
 */
CREATE TABLE `eportfolio_component_textbox_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `component_instance_id` INT(11) NOT NULL, `content` TEXT, `sequence` INT(11), PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add showcased work table
 */
CREATE TABLE `eportfolio_showcase_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `submission_box_id` INT(11), `uid` INT(11), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add file list item table
 */
CREATE TABLE `eportfolio_component_filelist_item`( `id` INT(11) NOT NULL AUTO_INCREMENT, `page_id` INT(11), `file_id` INT(11), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add blog table
 */
CREATE TABLE `eportfolio_component_blog`( `id` INT(11) NOT NULL AUTO_INCREMENT, `page_id` INT(11), `uid` INT(11), `title` VARCHAR(255), `content` TEXT, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add blog comments table
 */
CREATE TABLE `eportfolio_component_blogcomment`( `id` INT(11) NOT NULL AUTO_INCREMENT, `blog_id` INT(11), `uid` INT(11), `content` VARCHAR(255), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Add cover photo table
 */
CREATE TABLE `eportfolio_coverphoto`( `id` INT(11) NOT NULL AUTO_INCREMENT, `uid` INT(11), `imagehash` VARCHAR(255), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

