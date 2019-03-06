-- Specific views for capability and indicator marks only.
CREATE OR REPLACE VIEW v_rubric_capability_mark AS
SELECT DISTINCT
    -- Assessment fields
    assessment_id,

    -- Feedback fields
    student_id,
    marker_id,
    feedback_id,
    feedback_date,
    feedback_type,

    -- Rubric capability fields
    capability_id,
    capability_name,
    capability_sequence,
    capability_mark
FROM v_rubric_mark;


CREATE OR REPLACE VIEW v_rubric_indicator_mark AS
SELECT DISTINCT
    -- Assessment fields
    assessment_id,

    -- Feedback fields
    student_id,
    marker_id,
    feedback_id,
    feedback_date,
    feedback_type,

    -- Rubric capability fields:
    -- only what is required to disambiguate where the indicator lives
    capability_id,

    -- Rubric indicator fields
    indicator_id,
    indicator_name,
    indicator_sequence,
    indicator_mark,

    -- Rubric descriptor fields
    descriptor_id,
    descriptor_name,
    descriptor_max_value
FROM v_rubric_mark;
