CREATE OR REPLACE VIEW v_rubric_mark AS

  SELECT
    -- Assessment fields
    sb.assessment_id                     AS assessment_id,
    vr.assessment_mark_per_capability    AS assessment_mark_per_capability,

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

  FROM rubric_descriptor_mark rdm
  INNER JOIN rubric_descriptor rd      ON rdm.descriptor_id        = rd.id
  INNER JOIN rubric_group_mark rgm     ON rdm.rubric_group_mark_id = rgm.id

  INNER JOIN submission_return sr      ON rgm.return_id            = sr.id
  INNER JOIN user              student ON sr.owner                 = student.id
  INNER JOIN user              marker  ON sr.marker                = marker.id

  INNER JOIN submission_box sb         ON sr.box_id                = sb.id
  INNER JOIN v_rubric vr               ON vr.assessment_id         = sb.assessment_id AND
                                          rd.criterion_id          = vr.indicator_id

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
