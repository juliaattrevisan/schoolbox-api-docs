-- There was a bug which deleted all Responses in an Attempt when the Attempt was reviewed.
-- This script un-soft-deletes all Quiz Responses where the Attempt is not deleted.
-- We could tighten the test to ensure the 'reviewed_at' value on the attempt matches the 'deleted_at' on
-- the Response, but that may fail as they both use NOW(), which could fail to match.
UPDATE assess_response
SET deleted_at = NULL
WHERE attempt_id IN (
  SELECT attempt_id
  FROM assess_attempt
  WHERE deleted_at IS NULL
)
AND deleted_at IS NOT NULL;