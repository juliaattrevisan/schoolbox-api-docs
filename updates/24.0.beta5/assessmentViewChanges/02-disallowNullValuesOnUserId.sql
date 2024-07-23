DELETE FROM assessment_view WHERE user_id IS NULL;
ALTER TABLE assessment_view MODIFY COLUMN user_id int(4) unsigned NOT NULL COMMENT 'The user who viewed the assessment.';
