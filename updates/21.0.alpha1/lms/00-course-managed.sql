-- A flag for assessments being course-managed or not
-- This will prevent teachers from getting too creative with assessment details
ALTER TABLE `assessment`
    ADD COLUMN `course_managed` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `default_open_span`;
