DELETE FROM assessment_view WHERE assessment_id IS NULL;
ALTER TABLE assessment_view MODIFY COLUMN assessment_id int(10) unsigned NOT NULL;
