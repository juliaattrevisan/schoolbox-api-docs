-- Removing Deprecated Tables
DROP TABLE IF EXISTS `_curriculum_source`;
DROP TABLE IF EXISTS `_instance_registry`;
DROP TABLE IF EXISTS `_leave_request`;
DROP TABLE IF EXISTS `_lu_leave_request_host_type`;
DROP TABLE IF EXISTS `_lu_leave_reuqest_travel_type`;
DROP TABLE IF EXISTS `_pastoral_report_groups`;
DROP TABLE IF EXISTS `_video_queue`;

-- Removing Deprecated Fields
ALTER TABLE `calendar_connector` DROP COLUMN `_config`;
ALTER TABLE `menu_top` DROP COLUMN `_webmail`;
ALTER TABLE `pastoral_audit` DROP COLUMN `_search_result`;
ALTER TABLE `sis_class` DROP COLUMN `_description`;
ALTER TABLE `summative_report` DROP COLUMN `_options`; --Check again
ALTER TABLE `user` DROP COLUMN `_boarding_status`;
ALTER TABLE `user` DROP COLUMN `_webmail`;

