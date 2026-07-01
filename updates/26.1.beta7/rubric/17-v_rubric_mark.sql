-- Extend v_rubric_mark for the agnostic Assessment feedback API (SA-22780):
--  * expose rubric_instance_title (from v_rubric)
--  * expose descriptor_max_value / descriptor_sequence (LEFT JOIN rubric_descriptor + rubric_level)
-- Note: the reporting arm's 'instructor' literal is left bare on purpose. With the server default
-- collation set to utf8mb4_unicode_ci (see docker/mariadb/config/mariadb.conf.d/10-force-server-charset.cnf,
-- which MUST be applied first), the literal bakes as utf8mb4_unicode_ci and no COLLATE clause is needed.
-- The INNER JOIN to v_rubric_indicator_mark_extend is unchanged (shared with the rubric markbook).

CREATE OR REPLACE VIEW v_rubric_mark AS
SELECT *
FROM
    (
        SELECT DISTINCT
            vr.assessment_id AS assessment_id,
            rcm.feedback_id AS feedback_id,
            rcm.feedback_table AS feedback_table,
            sr.owner AS student_id,
            sr.marker AS marker_id,
            sr.published_at AS feedback_date,
            sr.review_route AS feedback_type,
            vr.rubric_instance_id AS rubric_instance_id,
            vr.rubric_instance_title AS rubric_instance_title,
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
            rim.descriptor_name AS descriptor_name,
            rd.max_value AS descriptor_max_value,
            rl.sequence AS descriptor_sequence
        FROM v_rubric vr
        INNER JOIN submission_box sb ON vr.assessment_id = sb.assessment_id
        INNER JOIN submission_return sr ON sb.id = sr.box_id
        INNER JOIN rubric_capability_mark rcm ON vr.rubric_instance_id = rcm.rubric_instance_id
            AND rcm.rubric_capability_id = vr.capability_id
            AND rcm.feedback_id = sr.id
            AND rcm.feedback_table = 'submission_return'
        INNER JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_capability_mark_id = rcm.id
            AND vr.indicator_id = rim.rubric_indicator_id
        LEFT JOIN rubric_descriptor rd ON rim.descriptor_id = rd.id AND rd.deleted_at IS NULL
        LEFT JOIN rubric_level rl ON rd.level_id = rl.id
            AND vr.rubric_instance_id = rl.rubric_instance_id
            AND rl.deleted_at IS NULL
        WHERE sr.deleted_at IS NULL
          AND sr.published_at IS NOT NULL
          AND (rcm.valid IS TRUE OR rim.valid IS TRUE)

        UNION

        SELECT DISTINCT
            vr.assessment_id AS assessment_id,
            rcm.feedback_id AS feedback_id,
            rcm.feedback_table AS feedback_table,
            rf.learner_id AS student_id,
            rf.instructor_id AS marker_id,
            rf.published_at AS feedback_date,
            'instructor' AS feedback_type,
            vr.rubric_instance_id AS rubric_instance_id,
            vr.rubric_instance_title AS rubric_instance_title,
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
            rim.descriptor_name AS descriptor_name,
            rd.max_value AS descriptor_max_value,
            rl.sequence AS descriptor_sequence
        FROM v_rubric vr
        INNER JOIN reporting r ON vr.assessment_id = r.assessment_id
        INNER JOIN reporting_feedback rf ON r.id = rf.reporting_id
        INNER JOIN rubric_capability_mark rcm ON vr.rubric_instance_id = rcm.rubric_instance_id
            AND rcm.rubric_capability_id = vr.capability_id
            AND rcm.feedback_id = rf.id
            AND rcm.feedback_table = 'reporting_feedback'
        INNER JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_capability_mark_id = rcm.id
            AND vr.indicator_id = rim.rubric_indicator_id
        LEFT JOIN rubric_descriptor rd ON rim.descriptor_id = rd.id AND rd.deleted_at IS NULL
        LEFT JOIN rubric_level rl ON rd.level_id = rl.id
            AND vr.rubric_instance_id = rl.rubric_instance_id
            AND rl.deleted_at IS NULL
        WHERE rf.deleted_at IS NULL
          AND rf.published_at IS NOT NULL
          AND (rcm.valid IS TRUE OR rim.valid IS TRUE)
) rubricSummary
ORDER BY assessment_id, student_id, feedback_date, capability_sequence, capability_id, indicator_sequence, indicator_id;
