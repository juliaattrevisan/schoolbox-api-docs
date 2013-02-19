/* CalendarConnector table for configuring the sources which constitute your calendar
 */
 CREATE TABLE `calendar_connector`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type` VARCHAR(255), `connector_class` VARCHAR(255), `config` TEXT, PRIMARY KEY (`id`) );