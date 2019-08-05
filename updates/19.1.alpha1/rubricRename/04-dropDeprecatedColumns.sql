# These columns are no longer required and can be removed
ALTER TABLE rubric_indicator      DROP COLUMN _grouping;
ALTER TABLE rubric_indicator      DROP COLUMN _parent_id;
ALTER TABLE rubric_indicator      DROP COLUMN _parent_type;
ALTER TABLE rubric_indicator_mark DROP COLUMN _return_id;
