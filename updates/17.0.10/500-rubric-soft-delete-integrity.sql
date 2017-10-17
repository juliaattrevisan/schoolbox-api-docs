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


-- Logic, from inside out:
-- * Find all non-deleted rubric levels that either have no descriptors, or no non-deleted descriptors
-- * For these levels, find the latest descriptor deletion date
-- * Update the levels to be soft-deleted at the same time as the latest descriptor deletion
UPDATE rubric_level rl3
JOIN (
    SELECT rl2.id, MAX(rd.deleted_at) AS latest_deletion FROM rubric_level rl2
    JOIN (
        SELECT rl.id FROM rubric_level rl
        LEFT JOIN (
            SELECT id, level_id FROM rubric_descriptor WHERE deleted_at IS NULL
        ) t2 ON t2.level_id = rl.id
        WHERE rl.deleted_at IS NULL AND t2.id IS NULL
    ) t3 ON t3.id = rl2.id
    JOIN rubric_descriptor rd ON rd.level_id = rl2.id
    WHERE rd.deleted_at IS NOT NULL
    GROUP BY rl2.id) t4 ON t4.id = rl3.id
SET rl3.deleted_at = t4.latest_deletion
WHERE rl3.id = t4.id;
