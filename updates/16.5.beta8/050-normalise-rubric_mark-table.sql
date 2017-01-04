-- Remove redundant fields from 'rubric_mark' table
ALTER TABLE `rubric_mark` DROP COLUMN `submission_box_id`;

-- TODO, drop `criterion_id` and `mark` fields

-- TODO add foreign keys and indexes to `student_id` and `descriptor_id`