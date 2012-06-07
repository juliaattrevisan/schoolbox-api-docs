/* Remember when a student's portrait was last loaded, for caching purposes
 */
ALTER TABLE `user` ADD COLUMN `portrait_updated_at` DATETIME NULL AFTER `eportfolio_enabled`;