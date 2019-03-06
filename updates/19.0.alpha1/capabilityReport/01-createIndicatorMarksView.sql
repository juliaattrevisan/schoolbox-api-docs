CREATE OR REPLACE VIEW v_rubric_indicator_marks AS

SELECT
    -- Assessment relation fields
    var.assessment_id                 AS assessment_id,
    var.folder_id                     AS folder_id,
    var.project_assessment_id         AS project_assessment_id,
    var.course_implementation_id      AS course_implementation_id,
    var.course_unit_implementation_id AS course_unit_implementation_id,
    var.course_specification_id       AS course_specification_id,
    var.course_unit_specification_id  AS course_unit_specification_id,

    -- Feedback fields
    student.id                        AS student_id,
    marker.id                         AS marker_id,
    sr.id                             AS feedback_id,
    sr.published_at                   AS feedback_date,
    sr.review_route                   AS feedback_type,

    -- Rubric capability fields
    rg.id                             AS capability_id,
    rg.name                           AS capability_name,
    rgm.value                         AS capability_mark,

    -- Rubric indicator fields
    rc.id                             AS indicator_id,
    rc.name                           AS indicator_name,
    rdm.value                         AS indicator_mark,

    -- Rubric descriptor fields
    rd.id                             AS descriptor_id,
    rd.description                    AS descriptor_name,
    rd.max_value                      AS descriptor_max_value
FROM v_assessment_relation var

-- Feedback joins
INNER JOIN submission_box    sb      ON var.assessment_id = sb.assessment_id
INNER JOIN submission_return sr      ON sb.id             = sr.box_id
INNER JOIN user              student ON sr.owner          = student.id
INNER JOIN user              marker  ON sr.marker         = marker.id

-- Rubric indicator, descriptor joins
-- Join through rubric_criterion_usage as I only care about indicators actually in use
INNER JOIN rubric_group_usage     rgu ON  sb.id               = rgu.submission_box_id
INNER JOIN rubric_group           rg  ON  rgu.rubric_group_id = rg.id
INNER JOIN rubric_group_mark rgm      ON  rg.id               = rgm.rubric_group_id
                                      AND sr.id               = rgm.return_id
INNER JOIN rubric_criterion_usage rcu ON  sb.id               = rcu.submission_box_id
INNER JOIN rubric_criterion       rc  ON  rcu.criterion_id    = rc.id
INNER JOIN rubric_descriptor      rd  ON  rc.id               = rd.criterion_id
INNER JOIN rubric_descriptor_mark rdm ON  rd.id               = rdm.descriptor_id
                                      AND rgm.id              = rdm.rubric_group_mark_id

ORDER BY
    var.assessment_id ASC,
    student.id ASC,
    sr.published_at ASC
;
