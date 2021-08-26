CREATE OR REPLACE VIEW v_rubric_mark AS
SELECT
    sb.assessment_id                              AS assessment_id,
    vr.capability_mark_type_id                    AS capability_mark_type_id,
    vr.capability_grade_group_id                  AS capability_grade_group_id,
    sr.owner                                      AS student_id,
    sr.marker                                     AS marker_id,
    sr.id                                         AS feedback_id,
    sr.published_at                               AS feedback_date,
    sr.review_route                               AS feedback_type,
    vr.capability_id                              AS capability_id,
    vr.capability_name                            AS capability_name,
    vr.capability_max_value                       AS capability_max_value,
    if(rcm.valid, rcm.value, NULL)                AS capability_mark,
    if(rcm.valid, rcm.human_readable_value, NULL) AS capability_human_readable_mark,
    vr.indicator_id                               AS indicator_id,
    vr.indicator_name                             AS indicator_name,
    vr.indicator_max_value                        AS indicator_max_value,
    if(rim.valid, rim.value, NULL)                AS indicator_mark,
    rim.descriptor_name                           AS descriptor_name
FROM       v_rubric                       vr
INNER JOIN submission_box                 sb  ON vr.assessment_id = sb.assessment_id
INNER JOIN submission_return              sr  ON sb.id = sr.box_id
INNER JOIN rubric_capability_mark         rcm ON rcm.return_id = sr.id AND rcm.rubric_capability_id = vr.capability_id
LEFT  JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_indicator_id = vr.indicator_id AND rcm.id = rim.rubric_capability_mark_id
WHERE sr.deleted_at IS NULL
  AND sr.published_at IS NOT NULL
  AND (rcm.valid IS TRUE OR rim.valid IS TRUE)
ORDER BY
    sb.assessment_id,
    sr.owner,
    sr.published_at,
    vr.capability_sequence,
    vr.capability_id,
    vr.indicator_sequence,
    vr.indicator_id;
