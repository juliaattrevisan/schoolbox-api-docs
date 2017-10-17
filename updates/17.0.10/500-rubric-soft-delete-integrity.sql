-- Cascade delete from SubmissionBox to RubricLevel
UPDATE rubric_level rl
  JOIN submission_box sb ON sb.id = rl.box_id
  JOIN assessment a ON a.id = sb.assessment_id
SET rl.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL;


-- Cascade delete from RubricCriterion OR RubricLevel to RubricDescriptor
-- (Need to take the earliest non-null deleted_at value from both parents.)
UPDATE rubric_descriptor rd
  JOIN rubric_criterion rc ON rc.id = rd.criterion_id
  JOIN rubric_level rl ON rl.id = rd.level_id
SET rd.deleted_at =
IF(rl.deleted_at IS NULL,
    rc.deleted_at,
    IF(rc.deleted_at IS NULL,
        rl.deleted_at,
        IF(rl.deleted_at < rc.deleted_at, rl.deleted_at, rc.deleted_at)
   )
)
WHERE rc.deleted_at IS NOT NULL OR rl.deleted_at IS NOT NULL;
