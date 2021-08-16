-- Populate the max_value column with the value from v_rubric.
-- Next query will update v_rubric so it uses this value.
UPDATE rubric_indicator_usage riu
INNER JOIN submission_box sb ON riu.submission_box_id = sb.id
INNER JOIN v_rubric       vr ON sb.assessment_id = vr.assessment_id AND riu.rubric_indicator_id = vr.indicator_id
SET riu.max_value = vr.indicator_max_value;
