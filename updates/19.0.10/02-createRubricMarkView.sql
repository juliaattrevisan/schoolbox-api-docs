-- This view is to populate rubric_descriptor mark in v_rubric_mark
CREATE OR REPLACE VIEW v_rubric_descriptor_mark_extend AS
  SELECT
    rubric_group_mark_id,
    descriptor_id,
    criterion_id,
    `value`,
    valid,
    -- Rubric descriptor fields
    -- (Not a valid descriptor mark? Don't select it)
    IF (valid, rd.description, NULL) AS descriptor_name
  FROM rubric_descriptor_mark
  LEFT JOIN rubric_descriptor rd ON rd.id = rubric_descriptor_mark.descriptor_id
  WHERE descriptor_id IS NOT NULL AND valid = 1;

CREATE OR REPLACE VIEW v_rubric_mark AS

  SELECT
    -- Assessment fields
    sb.assessment_id                     AS assessment_id,
    vr.assessment_mark_per_capability    AS assessment_mark_per_capability,

    -- Feedback fields
    sr.owner                             AS student_id,
    sr.marker                            AS marker_id,
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
    rdm.descriptor_name                  AS descriptor_name

  FROM v_rubric vr
  INNER JOIN submission_box sb            ON sb.assessment_id = vr.assessment_id
  INNER JOIN submission_return sr         ON sr.box_id = sb.id
  INNER JOIN rubric_group_mark rgm        ON sr.id = rgm.return_id AND vr.capability_id = rgm.rubric_group_id
  LEFT JOIN  v_rubric_descriptor_mark_extend rdm ON rdm.rubric_group_mark_id = rgm.id AND rdm.criterion_id = vr.indicator_id

  -- Only valid published rubric
  WHERE
    sr.deleted_at IS NULL AND
    sr.published_at IS NOT NULL AND
    (rgm.valid IS TRUE OR rdm.valid IS TRUE)

  ORDER BY
    sb.assessment_id       ASC,
    student_id             ASC,
    sr.published_at        ASC,
    vr.capability_sequence ASC,
    vr.capability_id       ASC,
    vr.indicator_sequence  ASC,
    vr.indicator_id        ASC
;
