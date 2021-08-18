-- Redefine rubric view so it references `rubric_indicator_usage.max_value`.
-- This also means that the GROUP BY on the rubric indicator ID can be removed.
CREATE OR REPLACE VIEW v_rubric AS

SELECT
    -- Assessment fields
    a.id                          AS assessment_id,
    a.title                       AS assessment_title,
    sb.mark_type                  AS assessment_mark_type,
    sb.max_mark                   AS assessment_max_mark,
    sb.submission_grade_group_id  AS assessment_grade_group_id,
    a.weight                      AS assessment_weight,

    -- Rubric capability fields
    rc.id                               AS capability_id,
    rc.name                             AS capability_name,
    rcu.max_value                       AS capability_max_value,
    rcu.sequence                        AS capability_sequence,
    sb.rubric_capability_mark_type_id   AS capability_mark_type_id,
    sb.rubric_capability_grade_group_id AS capability_grade_group_id,

    -- Rubric indicator fields
    ri.id                        AS indicator_id,
    ri.name                      AS indicator_name,
    riu.sequence                 AS indicator_sequence,
    riu.max_value                AS indicator_max_value

FROM assessment a

 -- Due work joins
INNER JOIN submission_box          sb  ON a.id = sb.assessment_id
INNER JOIN rubric_level            rl  ON sb.id = rl.box_id

-- Rubric indicator and capability joins
-- Join through rubric_indicator_usage as I only care about indicators actually in use
INNER JOIN rubric_capability_usage rcu ON sb.id                    = rcu.submission_box_id
INNER JOIN rubric_capability       rc  ON rcu.rubric_capability_id = rc.id

INNER JOIN rubric_indicator_usage  riu ON sb.id                    = riu.submission_box_id
INNER JOIN rubric_indicator        ri  ON riu.rubric_indicator_id  = ri.id
                                       AND ri.rubric_capability_id = rc.id

-- Only valid rubric marks
WHERE
    a.deleted_at  IS NULL AND
    sb.use_rubric IS TRUE AND
    rc.deleted_at IS NULL AND
    ri.deleted_at IS NULL

ORDER BY
    a.id            ASC,
    rcu.sequence    ASC,
    rc.id           ASC,
    riu.sequence    ASC,
    ri.id           ASC
;
