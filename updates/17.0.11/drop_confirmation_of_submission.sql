UPDATE submission_return
SET deleted_at = now()
WHERE is_confirmation_of_submission = 1;

ALTER TABLE submission_return
DROP COLUMN is_confirmation_of_submission;