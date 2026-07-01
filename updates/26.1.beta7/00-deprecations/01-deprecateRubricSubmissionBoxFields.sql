-- rubric capability mark type is now part of rubric_instance

ALTER TABLE `submission_box` DROP FOREIGN KEY `fk_rubric_capability_mark_type`;
ALTER TABLE `submission_box` DROP KEY `fk_rubric_capability_mark_type`;
ALTER TABLE `submission_box` RENAME COLUMN `rubric_capability_mark_type_id` TO `_rubric_capability_mark_type_id`;

-- rubric capability grade group is now part of rubric_instance

ALTER TABLE `submission_box` DROP FOREIGN KEY `fk_rubric_capability_grade_group`;
ALTER TABLE `submission_box` DROP KEY `fk_rubric_capability_grade_group`;
ALTER TABLE `submission_box` RENAME COLUMN `rubric_capability_grade_group_id` TO `_rubric_capability_grade_group_id`;

-- rubric is now derived from the count of its rubric instances

ALTER TABLE `submission_box` RENAME COLUMN `use_rubric` TO `_use_rubric`;
