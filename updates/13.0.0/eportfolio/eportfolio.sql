/* Clone out the homepageTemplate
 */
CREATE TABLE `eportfolioTemplate`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `layout` SMALLINT(6), `left_column_width` FLOAT, `right_column_width` FLOAT, PRIMARY KEY (`id`) ) CHARSET=utf8 COLLATE=utf8_general_ci;