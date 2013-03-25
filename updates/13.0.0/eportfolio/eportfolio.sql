/* Clone out the homepageTemplate
 */
CREATE TABLE `eportfolioTemplate`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `layout` SMALLINT(6), `left_column_width` FLOAT, `right_column_width` FLOAT, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;

/* Allow eportfolio_template in enum as they are synonymous with homepages
 */
ALTER TABLE `component_instance` CHANGE `module` `module` ENUM('dashboard','homepage','homepage_template','eportfolio','eportfolio_template') CHARSET utf8 COLLATE utf8_general_ci NULL;

/* A list of component categories
 */
CREATE TABLE `eportfolioComponentCategory`( `eportfolioComponentCategoryID` INT(10) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`eportfolioComponentCategoryID`) );