-- Add ability to store a Grading Scheme for capabilities
-- Applies if they choose Letter Grade for the capability mark type, which is no longer a bool
ALTER TABLE `submission_box`
    ADD COLUMN `rubric_capability_grade_group_id` INT(11) NULL DEFAULT NULL AFTER `rubric_mark_per_capability`;

-- rubric_mark_per_capability is now known as rubric_capability_mark_type_id

ALTER TABLE `submission_box`
    CHANGE COLUMN `rubric_mark_per_capability` `rubric_capability_mark_type_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;
