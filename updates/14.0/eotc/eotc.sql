INSERT INTO `error` (`id`, `message`) VALUES ('160', 'This event could not be found.');

/* Event metadata, for eotc events
 */
CREATE TABLE `eotc_metadata`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type_id` INT(11) COMMENT 'FK for luEventType', `status_id` INT(11) COMMENT 'FK for eotc_status', `rams_required` ENUM('true','false') DEFAULT 'false', `notes` TEXT COMMENT 'iAdditional notes', `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );
CREATE TABLE `eotc_event_metadata`( `event_id` INT(11), `metadata_id` INT(11) );
ALTER TABLE `eotc_metadata` CHANGE `rams_required` `rams_required` TINYINT(1) NULL;

/* Event statuses
 */
CREATE TABLE `eotc_status`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `eotc_status` (`name`) VALUES ('Draft');
INSERT INTO `eotc_status` (`name`) VALUES ('In Principle'); 
INSERT INTO `eotc_status` (`name`) VALUES ('RAMS Approved'); 
INSERT INTO `eotc_status` (`name`) VALUES ('Final Approval'); 
INSERT INTO `eotc_status` (`name`) VALUES ('Rejected');

/* Clone out the event table
 */
CREATE TABLE `eotc_event` ( PRIMARY KEY(`id`), FULLTEXT KEY `fulltext`( `note` , `detail` , `location` ))ENGINE=MYISAM COLLATE = utf8_general_ci COMMENT = '' SELECT `id`, `series_id`, `owner`, `author_id`, `access`, `sid`, `priority`, `type`, `note`, `startDate`, `startTime`, `endDate`, `endTime`, `detail`, `location`, `attendance`, `publish_date`, `completed`, `allday` FROM `schoolbox`.`event`;
ALTER TABLE `eotc_event` DROP `owner`; 
ALTER TABLE `eotc_event` DROP `access`; 
ALTER TABLE `eotc_event` DROP `sid`; 
ALTER TABLE `eotc_event` DROP `priority`; 
ALTER TABLE `eotc_event` DROP `attendance`; 
ALTER TABLE `eotc_event` DROP `publish_date`; 
ALTER TABLE `eotc_event` DROP `completed`; 
ALTER TABLE `eotc_event` DROP `allday`;
ALTER TABLE `eotc_event` MODIFY COLUMN id INT(11) AUTO_INCREMENT;

/* Event campus relations
 */
CREATE TABLE `eotc_event_campus`( `event_id` INT(11), `campus_id` INT(11) );