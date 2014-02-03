INSERT INTO `error` (`id`, `message`) VALUES ('160', 'This event could not be found.');

/* Event metadata, for eotc events
 */
CREATE TABLE `eotc_metadata`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type_id` INT(11) COMMENT 'FK for luEventType', `status_id` INT(11) COMMENT 'FK for eotc_status', `rams_required` ENUM('true','false') DEFAULT 'false', `notes` TEXT COMMENT 'iAdditional notes', `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );