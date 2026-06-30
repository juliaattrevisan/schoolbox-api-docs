-- rubric capability grade group is now part of rubric_instance

ALTER TABLE `submission_box` DROP FOREIGN KEY `fk_rubric_capability_grade_group`;
ALTER TABLE `submission_box` DROP KEY `fk_rubric_capability_grade_group`;
ALTER TABLE `submission_box` RENAME COLUMN `rubric_capability_grade_group_id` TO `_rubric_capability_grade_group_id`;
