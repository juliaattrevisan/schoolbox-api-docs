/* This requires the fullcalendar sql files to be run first
 */
 
/* CalendarConnector table for configuring the sources which constitute your calendar
 */
 CREATE TABLE `calendar_connector`( `id` INT(11) NOT NULL AUTO_INCREMENT, `event_type` VARCHAR(255), `connector_class` VARCHAR(255), `config` TEXT, PRIMARY KEY (`id`) );
 ALTER TABLE `calendar_connector` CHANGE `event_type` `event_type` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL COMMENT 'For human readability only', CHANGE `connector_class` `connector_class` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL COMMENT 'For human readability only';
 ALTER TABLE `calendar_connector` ADD COLUMN `status` TINYINT(1) DEFAULT 1 NULL COMMENT 'Whether this config is active or not' AFTER `config`;


 /* Add calendar cache duration as a config var
  */
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'calendar_api_caching',
  '600',
  'Calendar API update frequency',
  'The amount of time (in seconds) that external Calendars should be cached for before being re-queried.',
  'input',
  NULL,
  '3rd Party Services',
  'false',
  'false',
  'false'
);

/* Force default Schoolbox configs
 */
INSERT INTO `calendar_connector` (event_type, connector_class, config, status)
VALUES
('Event', 'Schoolbox', 'TzozOToiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfU2Nob29sYm94Ijo1OntzOjU6IgAqAGlkIjtpOi0xO3M6MTI6IgAqAGV2ZW50VHlwZSI7czo1OiJFdmVudCI7czoxNzoiACoAY29ubmVjdG9yQ2xhc3MiO3M6OToiU2Nob29sYm94IjtzOjE0OiIAKgBsdUV2ZW50VHlwZSI7czoxOiIxIjtzOjE3OiIAKgBoYXNQZXJtaXNzaW9ucyI7YjowO30=', 1),
('Submission', 'Schoolbox', 'TzozOToiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfU2Nob29sYm94Ijo1OntzOjU6IgAqAGlkIjtpOi0xO3M6MTI6IgAqAGV2ZW50VHlwZSI7czoxMDoiU3VibWlzc2lvbiI7czoxNzoiACoAY29ubmVjdG9yQ2xhc3MiO3M6OToiU2Nob29sYm94IjtzOjE0OiIAKgBsdUV2ZW50VHlwZSI7czoxOiIxIjtzOjE3OiIAKgBoYXNQZXJtaXNzaW9ucyI7YjowO30=', 1);

