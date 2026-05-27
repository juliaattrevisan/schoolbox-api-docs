-- Rubric usages are now found in multiple places, not just submission_box, so to avoid some kind of crazy mega query
-- knowing that the student_id and marker_id columns are different between learning activities
--
-- I guess that means it's time for unions, for now.
--
-- Note that reporting_feedback doesn't mention review_route: it's implied by usage that it's always 'instructor'
-- since students can't self-assess themselves in any way.

CREATE OR REPLACE VIEW v_rubric_mark AS
SELECT *
FROM
    (
        SELECT
            vr.assessment_id AS assessment_id,
            rcm.feedback_id AS feedback_id,
            rcm.feedback_table AS feedback_table,
            sr.owner AS student_id,
            sr.marker AS marker_id,
            sr.published_at AS feedback_date,
            sr.review_route AS feedback_type,
            vr.rubric_instance_id AS rubric_instance_id,
            vr.capability_mark_type_id AS capability_mark_type_id,
            vr.capability_grade_group_id AS capability_grade_group_id,
            vr.capability_id AS capability_id,
            vr.capability_name AS capability_name,
            vr.capability_max_value AS capability_max_value,
            IF (rcm.valid, rcm.value, NULL) AS capability_mark,
            IF (rcm.valid, rcm.human_readable_value, NULL) AS capability_human_readable_mark,
            vr.capability_sequence AS capability_sequence,
            vr.indicator_id AS indicator_id,
            vr.indicator_name AS indicator_name,
            vr.indicator_max_value AS indicator_max_value,
            IF (rim.valid, rim.value, NULL) AS indicator_mark,
            vr.indicator_sequence AS indicator_sequence,
            rim.descriptor_id AS descriptor_id,
            rim.descriptor_name AS descriptor_name
        FROM v_rubric vr
        INNER JOIN submission_box sb ON vr.assessment_id = sb.assessment_id
        INNER JOIN submission_return sr ON sb.id = sr.box_id
        INNER JOIN rubric_instance ON vr.rubric_instance_id = rubric_instance.id
        LEFT JOIN rubric_capability_mark rcm ON vr.rubric_instance_id = rcm.rubric_instance_id
        LEFT JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_capability_mark_id = rcm.id
        WHERE sr.deleted_at IS NULL
          AND sr.published_at IS NOT NULL
          AND (rcm.valid IS TRUE OR rim.valid IS TRUE)

        UNION

        SELECT
            vr.assessment_id AS assessment_id,
            rcm.feedback_id AS feedback_id,
            rcm.feedback_table AS feedback_table,
            rf.learner_id AS student_id,
            rf.instructor_id AS marker_id,
            rf.published_at AS feedback_date,
            'instructor' AS feedback_type,
            vr.rubric_instance_id AS rubric_instance_id,
            vr.capability_mark_type_id AS capability_mark_type_id,
            vr.capability_grade_group_id AS capability_grade_group_id,
            vr.capability_id AS capability_id,
            vr.capability_name AS capability_name,
            vr.capability_max_value AS capability_max_value,
            IF (rcm.valid, rcm.value, NULL) AS capability_mark,
            IF (rcm.valid, rcm.human_readable_value, NULL) AS capability_human_readable_mark,
            vr.capability_sequence AS capability_sequence,
            vr.indicator_id AS indicator_id,
            vr.indicator_name AS indicator_name,
            vr.indicator_max_value AS indicator_max_value,
            IF (rim.valid, rim.value, NULL) AS indicator_mark,
            vr.indicator_sequence AS indicator_sequence,
            rim.descriptor_id AS descriptor_id,
            rim.descriptor_name AS descriptor_name
        FROM v_rubric vr
        INNER JOIN reporting r ON vr.assessment_id = r.assessment_id
        INNER JOIN reporting_feedback rf ON r.id = rf.reporting_id
        INNER JOIN rubric_instance ON vr.rubric_instance_id = rubric_instance.id
        LEFT JOIN rubric_capability_mark rcm ON vr.rubric_instance_id = rcm.rubric_instance_id
        LEFT JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_capability_mark_id = rcm.id
        WHERE rf.deleted_at IS NULL
          AND rf.published_at IS NOT NULL
          AND (rcm.valid IS TRUE OR rim.valid IS TRUE)
) rubricSummary
ORDER BY assessment_id, student_id, feedback_date, capability_sequence, capability_id, indicator_sequence, indicator_id
