INSERT INTO `schoolbox`.`config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('date_time', 'g:ia', 'Time Format','Format for displaying times (g=12 hours,G=24 hours, i=minutes, a=meridiem, A=upper case meridiem)', '1', '6', 'true');

INSERT INTO `schoolbox`.`config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('date_local', 'd/m/Y', 'Local Date Format','Traditional local date format structure', '1', '6', 'true');

INSERT INTO `schoolbox`.`config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('date_only', 'jS M', 'Date Only Format','Used when displaying dates in the calendar, where year is not relevant', '1', '6', 'true');


UPDATE `schoolbox`.`config` SET `hidden` = 'true' WHERE `name` = 'date_database';
UPDATE `schoolbox`.`config` SET `hidden` = 'true' WHERE `name` = 'calendar_time_format';
UPDATE `schoolbox`.`config` SET `value` = 'l F jS Y' WHERE `name` = 'date_long';
UPDATE `schoolbox`.`config` SET `value` = 'jS M Y' WHERE `name` = 'date_short';
UPDATE `schoolbox`.`config` SET `hidden` = 'true' WHERE `name` = 'date_notime';