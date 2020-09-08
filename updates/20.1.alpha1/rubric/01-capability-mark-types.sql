-- Deprecate existing mark_per_capability field
ALTER TABLE `submission_box` CHANGE rubric_mark_per_capability _rubric_mark_per_capability INT(11) UNSIGNED DEFAULT 0 NOT NULL;

ALTER TABLE `submission_box`
    ADD COLUMN `rubric_capability_mark_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER _rubric_mark_per_capability;

-- Add ability to store a Grading Scheme for capabilities
-- Applies if they choose Letter Grade for the capability mark type, which is no longer a bool
ALTER TABLE `submission_box`
    ADD COLUMN `rubric_capability_grade_group_id` INT(11) NULL DEFAULT NULL AFTER _rubric_mark_per_capability;





