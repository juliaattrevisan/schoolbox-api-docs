DELETE FROM assessment_view WHERE last_visit IS NULL;
ALTER TABLE assessment_view MODIFY COLUMN last_visit datetime NOT NULL COMMENT 'The time the view occurred.  Only the most recent view is stored.';
