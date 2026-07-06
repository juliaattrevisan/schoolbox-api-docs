-- Expose rubric_instance.title and rubric_instance.weight so the Assessment API can return a set
-- of titled, weighted rubrics per assessment (SA-22780). Otherwise identical to the beta4 definition.

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
            rubric_instance.title AS rubric_instance_title,
            rubric_instance.weight AS rubric_instance_weight,
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
            AND ri.rubric_capability_id = rc.id
            AND rubric_instance.deleted_at IS NULL

        UNION

        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            r.mark_type AS assessment_mark_type,
            r.max_mark AS assessment_max_mark,
            r.grade_group_id AS assessment_grade_group_id,
            a.weight AS assessment_weight,
            rubric_instance.id AS rubric_instance_id,
            rubric_instance.title AS rubric_instance_title,
            rubric_instance.weight AS rubric_instance_weight,
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
            AND ri.rubric_capability_id = rc.id
            AND rubric_instance.deleted_at IS NULL
    ) rubricSummary
ORDER BY assessment_id, rubric_instance_id, capability_sequence, capability_id, indicator_sequence, indicator_id;
