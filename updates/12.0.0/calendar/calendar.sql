/* Add Publish Date column for calendar events
 */
ALTER TABLE `event` ADD COLUMN `publish_date` DATETIME NULL AFTER `attendance`;