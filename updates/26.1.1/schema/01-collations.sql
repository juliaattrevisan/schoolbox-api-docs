-- Fixes missing collations/charset declarations

ALTER TABLE `user_qualification` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `lu_ib_groups` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `lu_ib_subjects` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `ib_group_subjects` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `user_qualification` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `user_nationality` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `user_language` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `reporting` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `reporting_curriculum_node` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `reporting_work_type` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `reporting_feedback` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `lu_skin_config_category` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `lu_skin_config_subcategory` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE `skin_config_category` CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

