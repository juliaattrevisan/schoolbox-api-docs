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
