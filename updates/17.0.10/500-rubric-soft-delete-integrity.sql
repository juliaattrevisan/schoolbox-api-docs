-- Cascade delete from SubmissionBox to RubricLevel
UPDATE rubric_level rl
  JOIN submission_box sb ON sb.id = rl.box_id
  JOIN assessment a ON a.id = sb.assessment_id
SET rl.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL;
