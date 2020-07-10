-- Add ability to store a Grading Scheme for capabilities
-- Applies if they choose Letter Grade for the capability mark type, which is no longer a bool
ALTER TABLE `submission_box`
    ADD COLUMN `rubric_capability_grade_group_id` INT(11) NULL DEFAULT NULL AFTER `rubric_mark_per_capability`;
