-- rubric capability mark type is now part of rubric_instance

ALTER TABLE `submission_box` DROP FOREIGN KEY `fk_rubric_capability_mark_type`;
ALTER TABLE `submission_box` DROP KEY `fk_rubric_capability_mark_type`;
ALTER TABLE `submission_box` RENAME COLUMN `rubric_capability_mark_type_id` TO `_rubric_capability_mark_type_id`;
