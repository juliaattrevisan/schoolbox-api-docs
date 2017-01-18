-- Remove redundant fields from 'rubric_mark' table
# ALTER TABLE `rubric_mark`
#   DROP COLUMN `submission_box_id`,
#   DROP COLUMN `criterion_id`,
#   DROP COLUMN `mark`;

-- Remove redundant fields from 'rubric_descriptor' table
# ALTER TABLE `rubric_descriptor`
#   DROP COLUMN `submission_box_id`;

-- MUST ENSURE WE RUN legacy migration script before dropping this.
# ALTER TABLE submission_box
#     DROP COLUMN rubric_result_set;
