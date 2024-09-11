-- Removing Deprecated Tables
DROP TABLE IF EXISTS `_curriculum_source`;
DROP TABLE IF EXISTS `_instance_registry`;
DROP TABLE IF EXISTS `_leave_request`;
DROP TABLE IF EXISTS `_lu_leave_request_host_type`;
DROP TABLE IF EXISTS `_lu_leave_reuqest_travel_type`;
DROP TABLE IF EXISTS `_pastoral_report_groups`;
DROP TABLE IF EXISTS `_video_queue`; -- Check deleteDuplicatedVideoQueue.php

-- Removing Deprecated Fields
ALTER TABLE `calendar_connector` DROP COLUMN `_config`;
ALTER TABLE `menu_top` DROP COLUMN `_webmail`;
ALTER TABLE `pastoral_audit` DROP COLUMN `_search_result`;
ALTER TABLE `sis_class` DROP COLUMN `_description`;
ALTER TABLE `summative_report` DROP COLUMN `_options`; --Check 02-updateOptions
ALTER TABLE `user` DROP COLUMN `_boarding_status`;
ALTER TABLE `user` DROP COLUMN `_webmail`;
ALTER TABLE `assess_attempt` DROP COLUMN `_mark`;
ALTER TABLE `assess_attempt` DROP COLUMN `_comment`;
ALTER TABLE `assess` DROP COLUMN `_duration`;
ALTER TABLE `pastoral` DROP COLUMN `_parent_comments_allowed`;
ALTER TABLE `pastoral` DROP COLUMN `_student_comments_allowed`;
ALTER TABLE `pastoral_report` DROP COLUMN `_include_uncompleted_actions_only`;
ALTER TABLE `project_result` DROP COLUMN `_project_id`;
ALTER TABLE `project_result` DROP COLUMN `_marker_id`;
ALTER TABLE `submission_box` DROP COLUMN `_rubric_capability_mark_type_id`;
ALTER TABLE `submission_box` DROP COLUMN `_rubric_capability_grade_group_id`;
