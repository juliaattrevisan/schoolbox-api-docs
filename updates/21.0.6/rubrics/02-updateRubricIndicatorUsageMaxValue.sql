-- Populate the max_value column with the value which would've been calculated
-- by v_rubric.
-- Next query will update v_rubric so it uses this value.
UPDATE rubric_indicator_usage riu_update
INNER JOIN (
    SELECT sb.id         AS box_id,
           ri.id         AS indicator_id,
           riu.max_value AS indicator_max_value
    FROM assessment a

    -- Due work joins
    INNER JOIN submission_box sb ON a.id = sb.assessment_id
    INNER JOIN rubric_level rl ON sb.id = rl.box_id

    -- Rubric capability, indicator, descriptor joins
    -- Join through rubric_indicator_usage as I only care about indicators actually in use
    INNER JOIN rubric_capability_usage rcu ON sb.id = rcu.submission_box_id
    INNER JOIN rubric_capability rc ON rcu.rubric_capability_id = rc.id

    INNER JOIN rubric_indicator_usage riu ON sb.id = riu.submission_box_id
    INNER JOIN rubric_indicator ri ON riu.rubric_indicator_id = ri.id AND ri.rubric_capability_id = rc.id

    INNER JOIN rubric_descriptor rd ON rd.rubric_indicator_id = ri.id AND rd.level_id = rl.id

    -- Unlike v_rubric, this doesn't exclude deleted assessments, or assessments
    -- which don't use rubrics (as they may still have a rubric).
    -- However, it does exclude deleted capabilities, indicators and descriptors:
    -- these can't be considered part of the indicator value.
    WHERE rc.deleted_at IS NULL
      AND ri.deleted_at IS NULL
      AND rd.deleted_at IS NULL

    GROUP BY sb.id, ri.id
) AS v_rubric_copy ON riu_update.submission_box_id = v_rubric_copy.box_id AND riu_update.rubric_indicator_id = v_rubric_copy.indicator_id
SET riu_update.max_value = v_rubric_copy.indicator_max_value;
