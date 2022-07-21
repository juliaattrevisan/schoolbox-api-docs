-- Projects aren't included in acus
-- Use this view to softdelete all turnitin_groups associated with specs everywhere

UPDATE turnitin_group tg
INNER JOIN submission_box sb on tg.submission_box_id = sb.id
INNER JOIN v_assessment_relation var on sb.assessment_id = var.assessment_id
SET tg.deleted_at = NOW()
WHERE var.course_specification_id IS NOT NULL
AND var.course_unit_specification_id IS NOT NULL;
