ALTER TABLE `luCampus`
 CHANGE `id` `id` TINYINT(4) UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
 CHANGE `name` `name` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL AFTER `id`,
 ADD COLUMN `ext_timetable_code` VARCHAR(20) NULL AFTER `name`,
 ADD COLUMN `timetable_day` TINYINT NULL AFTER `ext_timetable_code`;
