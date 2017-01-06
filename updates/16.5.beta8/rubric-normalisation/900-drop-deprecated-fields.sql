# -- Remove redundant fields from 'rubric_mark' table
# ALTER TABLE `rubric_mark`
#   DROP COLUMN `submission_box_id`,
#   DROP COLUMN `criterion_id`,
#   DROP COLUMN `mark`;

# -- Remove redundant fields from 'rubric_descriptor' table
# ALTER TABLE `rubric_descriptor` DROP COLUMN `submission_box_id`;
