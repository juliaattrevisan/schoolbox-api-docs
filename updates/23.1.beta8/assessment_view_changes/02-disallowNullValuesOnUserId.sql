DELETE FROM assessment_view WHERE user_id IS NULL;
ALTER TABLE assessment_view MODIFY COLUMN user_id int(10) unsigned NOT NULL;
