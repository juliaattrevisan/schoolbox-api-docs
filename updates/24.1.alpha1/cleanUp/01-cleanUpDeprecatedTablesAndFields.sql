-- Removing Deprecated Tables
DROP TABLE IF EXISTS `_curriculum_source`;
DROP TABLE IF EXISTS `_instance_registry`;
DROP TABLE IF EXISTS `_leave_request`;
DROP TABLE IF EXISTS `_lu_leave_request_host_type`;
DROP TABLE IF EXISTS `_lu_leave_request_travel_type`;
DROP TABLE IF EXISTS `_pastoral_report_groups`;
DROP TABLE IF EXISTS `_video_queue`;

-- Removing Deprecated Fields
ALTER TABLE `calendar_connector` DROP COLUMN IF EXISTS `_config`;
ALTER TABLE `menu_top` DROP COLUMN IF EXISTS `_webmail`;
ALTER TABLE `pastoral_audit` DROP COLUMN IF EXISTS `_search_result`;
ALTER TABLE `sis_class` DROP COLUMN IF EXISTS `_description`;
ALTER TABLE `summative_report` DROP COLUMN IF EXISTS `_options`;
ALTER TABLE `user` DROP COLUMN IF EXISTS `_boarding_status`;
ALTER TABLE `user` DROP COLUMN IF EXISTS `_webmail`;
ALTER TABLE `assess_attempt` DROP COLUMN IF EXISTS `_mark`;
ALTER TABLE `assess_attempt` DROP COLUMN IF EXISTS `_comment`;
ALTER TABLE `assess` DROP COLUMN IF EXISTS `_duration`;
ALTER TABLE `pastoral` DROP COLUMN IF EXISTS `_parent_comments_allowed`;
ALTER TABLE `pastoral` DROP COLUMN IF EXISTS `_student_comments_allowed`;
ALTER TABLE `pastoral_report` DROP COLUMN IF EXISTS `_include_uncompleted_actions_only`;
ALTER TABLE `project_result` DROP COLUMN IF EXISTS `_project_id`;
ALTER TABLE `project_result` DROP COLUMN IF EXISTS `_marker_id`;
ALTER TABLE `submission_box` DROP COLUMN IF EXISTS `_rubric_capability_mark_type_id`;
ALTER TABLE `submission_box` DROP COLUMN IF EXISTS `_rubric_capability_grade_group_id`;
