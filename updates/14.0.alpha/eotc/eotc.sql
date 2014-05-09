INSERT INTO `error` (`id`, `message`) VALUES ('160', 'This event could not be found.');

/* Event metadata, for eotc events
 */
CREATE TABLE `eotc_metadata`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type_id` INT(11) COMMENT 'FK for luEventType', `status_id` INT(11) COMMENT 'FK for eotc_status', `rams_required` TINYINT(1), `comment` TEXT COMMENT 'iAdditional notes', `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );
CREATE TABLE `eotc_event_metadata`( `event_id` INT(11), `metadata_id` INT(11) );

/* Unique index, so I can use insert ignore to prevent dupes
 */
ALTER TABLE `eotc_event_metadata` ADD UNIQUE INDEX `event_metadata` (`event_id`, `metadata_id`);

/* Event statuses
 */
CREATE TABLE `eotc_status`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `eotc_status` (`name`) VALUES ('Initial Application');
INSERT INTO `eotc_status` (`name`) VALUES ('Approved In Principle');
INSERT INTO `eotc_status` (`name`) VALUES ('RAMS Approved');
INSERT INTO `eotc_status` (`name`) VALUES ('RAMS Confirmed');
INSERT INTO `eotc_status` (`name`) VALUES ('Approved');
INSERT INTO `eotc_status` (`name`) VALUES ('Rejected');

/* Clone out the event table
 */
CREATE TABLE `eotc_event` ( PRIMARY KEY(`id`), FULLTEXT KEY `fulltext`( `note` , `detail` , `location` ))ENGINE=MYISAM COLLATE = utf8_general_ci COMMENT = '' SELECT `id`, `series_id`, `owner`, `author_id`, `access`, `sid`, `priority`, `type`, `note`, `startDate`, `startTime`, `endDate`, `endTime`, `detail`, `location`, `attendance`, `publish_date`, `completed`, `allday` FROM `event`;
ALTER TABLE `eotc_event` DROP `owner`; 
ALTER TABLE `eotc_event` DROP `access`; 
ALTER TABLE `eotc_event` DROP `sid`; 
ALTER TABLE `eotc_event` DROP `priority`; 
ALTER TABLE `eotc_event` DROP `attendance`; 
ALTER TABLE `eotc_event` DROP `publish_date`; 
ALTER TABLE `eotc_event` DROP `completed`; 
ALTER TABLE `eotc_event` MODIFY COLUMN id INT(11) AUTO_INCREMENT;
ALTER TABLE `eotc_event` ADD COLUMN `teacher_in_charge_id` INT(11) NULL AFTER `author_id`;
TRUNCATE eotc_event;

/* Event campus relations
 */
CREATE TABLE `eotc_event_campus`( `event_id` INT(11), `campus_id` INT(11) );

/* Event staff relations
 */
CREATE TABLE `eotc_metadata_staff`( `metadata_id` INT(11), `user_id` INT(11) );
ALTER TABLE `eotc_metadata_staff` ADD UNIQUE INDEX `metadata_staff` (`metadata_id`, `user_id`);

/* Event student relations
 */
CREATE TABLE `eotc_metadata_student`( `metadata_id` INT(11), `user_id` INT(11) );
ALTER TABLE `eotc_metadata_student` ADD UNIQUE INDEX `metadata_student` (`metadata_id`, `user_id`);

/* Event file relations
 */
CREATE TABLE `eotc_metadata_file`( `metadata_id` INT(11), `storage_id` INT(11) );
ALTER TABLE `eotc_metadata_file` ADD UNIQUE INDEX `metadata_storage` (`metadata_id`, `storage_id`);
INSERT INTO `error` (`id`, `message`) VALUES ('161', 'Please upload a RAMS file.');

/* Event activity log relations
 */
CREATE TABLE `eotc_activitylog`( `id` INT(11) AUTO_INCREMENT, `actioner_id` INT(11), `metadata_id` INT(11), `message` TEXT, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, KEY(`id`) );

/* Permissions
 */
CREATE TABLE `eotc_permission`( `id` INT(11) NOT NULL AUTO_INCREMENT, `user_id` INT(11), `campus_id` INT(11), `status_id` INT(11), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );

/* EOTC Config
 */
CREATE TABLE `eotc_config`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `value` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('first_reminder', '10');
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('daily_reminder', '3');
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('urgent_threshold', '3');

ALTER TABLE `eotc_config` CHANGE `value` `value` TEXT CHARSET latin1 COLLATE latin1_swedish_ci NULL;
INSERT INTO `eotc_config` (`name`) VALUES ('policy');

/* Unknown event status
 */
INSERT INTO `error` (`id`, `message`) VALUES ('163', 'Unknown event status.');