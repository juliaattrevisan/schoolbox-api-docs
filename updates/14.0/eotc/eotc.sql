INSERT INTO `error` (`id`, `message`) VALUES ('160', 'This event could not be found.');

/* Event metadata, for eotc events
 */
CREATE TABLE `eotc_metadata`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type_id` INT(11) COMMENT 'FK for luEventType', `status_id` INT(11) COMMENT 'FK for eotc_status', `rams_required` ENUM('true','false') DEFAULT 'false', `notes` TEXT COMMENT 'iAdditional notes', `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );
CREATE TABLE `eotc_event_metadata`( `event_id` INT(11), `metadata_id` INT(11) );

/* Event statuses
 */
CREATE TABLE `eotc_status`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `eotc_status` (`name`) VALUES ('Draft');
INSERT INTO `eotc_status` (`name`) VALUES ('In Principle'); 
INSERT INTO `eotc_status` (`name`) VALUES ('RAMS Approved'); 
INSERT INTO `eotc_status` (`name`) VALUES ('Final Approval'); 
INSERT INTO `eotc_status` (`name`) VALUES ('Rejected'); 