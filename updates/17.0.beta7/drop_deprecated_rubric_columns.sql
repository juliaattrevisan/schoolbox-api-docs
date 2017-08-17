-- These columns were removed from the Mapper and Model in previous major version
ALTER TABLE rubric_mark
  DROP COLUMN criterion_id,
  DROP COLUMN submission_box_id,
  DROP COLUMN mark;

ALTER TABLE rubric_descriptor
  DROP COLUMN submission_box_id;

ALTER TABLE submission_box
  DROP COLUMN rubric_result_set;
