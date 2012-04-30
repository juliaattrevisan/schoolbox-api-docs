/* Add Publish Date column for calendar events
 */

/* Alter table in target */
ALTER TABLE `event` ADD COLUMN `attendance` tinyint(1)   NULL after `location`;
ALTER TABLE `event` ADD COLUMN `publish_date` DATETIME NULL AFTER `attendance`;

/* Create table in target */
CREATE TABLE `event_attendance`(
	`id` int(11) NOT NULL  auto_increment ,
	`event_id` int(11) NOT NULL  ,
	`user_id` int(11) NOT NULL  ,
	`status` int(11) NOT NULL  ,
	`intention` int(11) NOT NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='latin1';

/* Alter events table to include series_id for manipulating events by bulk */
ALTER TABLE `event` ADD COLUMN `series_id` INT(6) NULL AFTER `id`;