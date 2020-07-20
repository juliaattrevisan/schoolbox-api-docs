-- Redefine the rubric views so that they reference the new tables.
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
    rg.id                               AS capability_id,
    rg.name                             AS capability_name,
    rgu.max_value                       AS capability_max_value,
    rgu.sequence                        AS capability_sequence,
    sb.rubric_mark_per_capability       AS capability_mark_type_id,
    sb.rubric_capability_grade_group_id AS capability_grade_group_id,

    -- Rubric indicator fields
    rc.id                        AS indicator_id,
    rc.name                      AS indicator_name,
    rcu.sequence                 AS indicator_sequence,
    MAX(rd.max_value)            AS indicator_max_value

FROM assessment a

         -- Due work joins
         INNER JOIN submission_box          sb  ON a.id = sb.assessment_id
         INNER JOIN rubric_level            rl  ON sb.id = rl.box_id

    -- Rubric indicator, descriptor joins
    -- Join through rubric_criterion_usage as I only care about indicators actually in use
         INNER JOIN rubric_capability_usage rgu ON sb.id                    = rgu.submission_box_id
         INNER JOIN rubric_capability       rg  ON rgu.rubric_capability_id = rg.id

         INNER JOIN rubric_indicator_usage  rcu ON sb.id                    = rcu.submission_box_id
         INNER JOIN rubric_indicator        rc  ON rcu.rubric_indicator_id  = rc.id AND
                                                   rc.rubric_capability_id  = rg.id

         INNER JOIN rubric_descriptor       rd  ON  rd.rubric_indicator_id  = rc.id AND
                                                    rd.level_id             = rl.id

-- Only valid rubric marks
WHERE
    a.deleted_at  IS NULL AND
    sb.use_rubric IS TRUE AND
    rg.deleted_at IS NULL AND
    rc.deleted_at IS NULL AND
    rd.deleted_at IS NULL

GROUP BY sb.id, rc.id

ORDER BY
    a.id            ASC,
    rgu.sequence    ASC,
    rg.id           ASC,
    rcu.sequence    ASC,
    rc.id           ASC
;


CREATE OR REPLACE VIEW v_rubric_mark AS

SELECT
    -- Assessment fields
    sb.assessment_id                     AS assessment_id,
    vr.capability_mark_type_id           AS capability_mark_type_id,

    -- Feedback fields
    student.id                           AS student_id,
    marker.id                            AS marker_id,
    sr.id                                AS feedback_id,
    sr.published_at                      AS feedback_date,
    sr.review_route                      AS feedback_type,

    -- Rubric capability mark fields
    vr.capability_id                     AS capability_id,
    vr.capability_name                   AS capability_name,
    vr.capability_max_value              AS capability_max_value,
    IF (rgm.valid, rgm.value, NULL)      AS capability_mark,

    -- Rubric indicator mark fields
    vr.indicator_id                      AS indicator_id,
    vr.indicator_name                    AS indicator_name,
    vr.indicator_max_value               AS indicator_max_value,
    IF (rdm.valid, rdm.value, NULL)      AS indicator_mark,

    -- Rubric descriptor fields
    -- (Not a valid descriptor mark? Don't select it)
    IF (rdm.valid, rd.description, NULL) AS descriptor_name

FROM rubric_indicator_mark rdm
         INNER JOIN rubric_descriptor      rd      ON rdm.descriptor_id             = rd.id
         INNER JOIN rubric_capability_mark rgm     ON rdm.rubric_capability_mark_id = rgm.id

         INNER JOIN submission_return      sr      ON rgm.return_id                 = sr.id
         INNER JOIN user                   student ON sr.owner                      = student.id
         INNER JOIN user                   marker  ON sr.marker                     = marker.id

         INNER JOIN submission_box         sb      ON sr.box_id                     = sb.id
         INNER JOIN v_rubric               vr      ON vr.assessment_id              = sb.assessment_id AND
                                                      rd.rubric_indicator_id        = vr.indicator_id

-- Only valid published rubric
WHERE
    sr.deleted_at IS NULL AND
    sr.published_at IS NOT NULL AND
    (rgm.valid IS TRUE OR rdm.valid IS TRUE)

ORDER BY
    sb.assessment_id ASC,
    student.id       ASC,
    sr.published_at  ASC,
    vr.capability_sequence ASC,
    vr.capability_id ASC,
    vr.indicator_sequence ASC,
    vr.indicator_id ASC
;
