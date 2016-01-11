/* append new fields to submission box object */
ALTER TABLE `submission_box`
  ADD COLUMN `use_rubric` INT(1) UNSIGNED DEFAULT 0  NOT NULL AFTER `skip_unsubmitted`,
  ADD COLUMN `rubric_group_marks` INT(1) UNSIGNED DEFAULT 0  NOT NULL AFTER `use_rubric`;

/* switch over to using the new field and cast the rubric marktype to raw (it will look the same) */
UPDATE submission_box SET
  use_rubric = TRUE,
  mark_type = (
    SELECT mark_type_id FROM submission_mark_type
    WHERE slug = 'raw'
  )
WHERE mark_type = (
  SELECT mark_type_id FROM submission_mark_type
  WHERE slug = 'rubric'
);
