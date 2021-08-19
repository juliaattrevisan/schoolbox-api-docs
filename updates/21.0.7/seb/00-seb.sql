-- A flag for assessments requiring SEB Safe Exam  Browser
ALTER TABLE `assessment`
    ADD COLUMN `seb_enabled` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `course_managed`;