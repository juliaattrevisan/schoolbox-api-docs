/* CalendarConnector table for configuring the sources which constitute your calendar
 */
 CREATE TABLE `calendar_connector`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type` VARCHAR(255), `connector_class` VARCHAR(255), `config` TEXT, PRIMARY KEY (`id`) );
 ALTER TABLE `calendar_connector` CHANGE `event_type` `event_type` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL COMMENT 'For human readability only', CHANGE `connector_class` `connector_class` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL COMMENT 'For human readability only';
 ALTER TABLE `calendar_connector` ADD COLUMN `status` TINYINT(1) DEFAULT 1 NULL COMMENT 'Whether this config is active or not' AFTER `config`;