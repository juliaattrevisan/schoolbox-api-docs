-- These columns were deprecated in 18.0, they should now be removed
ALTER TABLE rubric_mark
    DROP COLUMN _published_at,
    DROP COLUMN _created_at,
    DROP COLUMN _student_id,
    DROP INDEX student_id;
