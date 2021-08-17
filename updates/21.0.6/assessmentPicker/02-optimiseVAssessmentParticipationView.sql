-- A view for querying custom assessment participation.
-- Filters out `v_assessment_relation` records with a course spec, as they
-- can't possibly have participation.
CREATE OR REPLACE VIEW v_assessment_participation AS
SELECT
    a.id         AS assessment_id,
    a.deleted_at AS assessment_deleted_at,
    p.user_id    AS student_id,
    var.folder_id,
    pp.is_dynamic,
    COALESCE(pp.description, a.description)   AS description,
    COALESCE(pp.publish_date, a.publish_date) AS publish_date,
    COALESCE(pp.open_date, a.open_date)       AS open_date,
    COALESCE(pp.due_date, a.due_date)         AS due_date,
    COALESCE(pp.close_date, a.close_date)     AS close_date,
    p.excluded_at
FROM assessment a
INNER JOIN v_assessment_relation var ON a.id  = var.assessment_id AND var.course_specification_id IS NULL AND var.course_unit_specification_id IS NULL
INNER JOIN participant_plan      pp  ON a.id  = pp.assessment_id AND pp.deleted_at IS NULL
INNER JOIN participant           p   ON pp.id = p.participant_plan_id;
