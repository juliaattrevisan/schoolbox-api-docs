-- Expand to accommodate long slabs of info
ALTER TABLE `sis_user_profile_student`
    CHANGE COLUMN `alert_medical` `alert_medical` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL;
