-- Rubric usages are now found in multiple places, not just submission_box, so to avoid some kind of crazy mega query
-- using conditional left joins (noting that grade group column is different on submission_box compared to everywhere
-- else) we just stack a UNION. I don't think there's an elegant way to address this _except_ for moving mark type,
-- max mark, and grade group id into the schoolbox.assessment table where (ultimately) it probably belongs.
-- But that changes the entire swamp, so I'm not going to do that here.
--
-- I guess that means it's time for unions, for now.

CREATE OR REPLACE VIEW v_rubric AS
SELECT *
FROM
    (
        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            sb.mark_type AS assessment_mark_type,
            sb.max_mark AS assessment_max_mark,
            sb.submission_grade_group_id AS assessment_grade_group_id,
            a.weight AS assessment_weight,
            rubric_instance.id AS rubric_instance_id,
            rubric_instance.rubric_capability_mark_type_id AS capability_mark_type_id,
            rubric_instance.rubric_capability_grade_group_id AS capability_grade_group_id,
            rc.id AS capability_id,
            rc.name AS capability_name,
            rcu.max_value AS capability_max_value,
            rcu.sequence AS capability_sequence,
            ri.id AS indicator_id,
            ri.name AS indicator_name,
            riu.sequence AS indicator_sequence,
            riu.max_value AS indicator_max_value
        FROM assessment a
        INNER JOIN submission_box sb ON a.id = sb.assessment_id
        INNER JOIN rubric_instance ON a.id = rubric_instance.assessment_id
        INNER JOIN rubric_capability_usage rcu ON rcu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_capability rc ON rc.id = rcu.rubric_capability_id
        INNER JOIN rubric_indicator_usage riu ON riu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_indicator ri ON ri.id = riu.rubric_indicator_id
        WHERE a.deleted_at IS NULL
            AND rc.deleted_at IS NULL
            AND ri.deleted_at IS NULL

        UNION

        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            r.mark_type AS assessment_mark_type,
            r.max_mark AS assessment_max_mark,
            r.grade_group_id AS assessment_grade_group_id,
            a.weight AS assessment_weight,
            rubric_instance.id AS rubric_instance_id,
            rubric_instance.rubric_capability_mark_type_id AS capability_mark_type_id,
            rubric_instance.rubric_capability_grade_group_id AS capability_grade_group_id,
            rc.id AS capability_id,
            rc.name AS capability_name,
            rcu.max_value AS capability_max_value,
            rcu.sequence AS capability_sequence,
            ri.id AS indicator_id,
            ri.name AS indicator_name,
            riu.sequence AS indicator_sequence,
            riu.max_value AS indicator_max_value
        FROM assessment a
        INNER JOIN reporting r ON a.id = r.assessment_id
        INNER JOIN rubric_instance ON a.id = rubric_instance.assessment_id
        INNER JOIN rubric_capability_usage rcu ON rcu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_capability rc ON rc.id = rcu.rubric_capability_id
        INNER JOIN rubric_indicator_usage riu ON riu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_indicator ri ON ri.id = riu.rubric_indicator_id
        WHERE a.deleted_at IS NULL
            AND rc.deleted_at IS NULL
            AND ri.deleted_at IS NULL
    ) rubricSummary
ORDER BY assessment_id, rubric_instance_id, capability_sequence, capability_id, indicator_sequence, indicator_id;
