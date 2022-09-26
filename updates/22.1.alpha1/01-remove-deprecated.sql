-- Remove deprecated fields and tables from the database
DROP TABLE IF EXISTS `_backup_userGroups_v21_1_9`;
DROP TABLE IF EXISTS `_scheduleq`;
DROP TABLE IF EXISTS `_ups_condition`;
DROP TABLE IF EXISTS `_ups_effect`;
ALTER TABLE `pastoral_revision`
    DROP COLUMN IF EXISTS `_private`;
ALTER TABLE `lu_pastoral_type`
    DROP COLUMN IF EXISTS `_external_id`;
ALTER TABLE `pastoral_audience`
    DROP COLUMN IF EXISTS `_role_id`;
ALTER TABLE `pastoral_audience`
    DROP COLUMN IF EXISTS `_role_type`;
ALTER TABLE `pastoral_report`
    DROP COLUMN IF EXISTS `_years`;
ALTER TABLE `mobile_app_registry`
    DROP COLUMN IF EXISTS `_provider`;
ALTER TABLE `user`
    DROP COLUMN IF EXISTS `_year`;
ALTER TABLE `pastoral`
    DROP COLUMN IF EXISTS `_private`;
ALTER TABLE `lu_pastoral_subtype`
    DROP COLUMN IF EXISTS `_external_id`;
ALTER TABLE `submission_box`
    DROP COLUMN IF EXISTS `_rubric_mark_per_capability`;
ALTER TABLE `ups_effect`
    DROP COLUMN IF EXISTS `_arguments`;
ALTER TABLE `pastoral_access`
    DROP COLUMN IF EXISTS `_role_id`;
ALTER TABLE `pastoral_access`
    DROP COLUMN IF EXISTS `_role_type`;
ALTER TABLE `submission_grade`
    DROP COLUMN IF EXISTS `_lower_bound`;
ALTER TABLE `submission_grade`
    DROP COLUMN IF EXISTS `_raw_grade`;
ALTER TABLE `task`
    DROP COLUMN IF EXISTS `_link`;
