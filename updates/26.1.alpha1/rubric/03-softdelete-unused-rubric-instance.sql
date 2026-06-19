-- We previously seeded rubric_instances for every submission box's rubric, even if its use_rubric was zero
-- That's because rubric_capability_usage still contains usages of rubric parts even if use_rubric is zero
-- and is an integral part of a cmposite primary key: it needs rubric instances even if they'll never be used again.
--
-- This sets the soft-deleted flag for rubric_instances whose submission boxes had use_rubric = 0, to fulfil the
-- composite primary key requirements, while retaining the spirit of use_rubric = 0.

UPDATE rubric_instance
INNER JOIN submission_box sb ON sb.assessment_id = rubric_instance.assessment_id
SET rubric_instance.deleted_at = NOW()
WHERE sb.use_rubric = 0;
