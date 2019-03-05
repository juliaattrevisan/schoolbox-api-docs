SELECT
    student.id      AS student_id,

    marker.id       AS marker_id,

    a.id            AS assessment_id,
#     a.title         AS assessment_title,

    f.id   AS folder_id,
#     f.name AS folder_name,

    sr.id           AS feedback_id,
#     sr.published_at AS feedback_date,
#     sr.review_route AS feedback_type,

    rc.id AS indicator_id,
    rc.name AS indicator_name,
    rdm.value AS indicator_mark,

    rd.id AS descriptor_id,
    rd.description AS descriptor_name,
    rd.max_value AS descriptor_max_value

FROM assessment a

-- folder join: only direct relation
INNER JOIN assessment_folder af ON a.id         = af.assessment_id
INNER JOIN folder            f  ON af.folder_id = f.id

-- return joins
INNER JOIN submission_box    sb      ON a.id      = sb.assessment_id
INNER JOIN submission_return sr      ON sb.id     = sr.box_id
INNER JOIN user              student ON sr.owner  = student.id
INNER JOIN user              marker  ON sr.marker = marker.id

-- rubric capability mark joins
INNER JOIN rubric_group_mark      rgm ON sr.id             = rgm.return_id
LEFT  JOIN rubric_descriptor_mark rdm ON rgm.id            = rdm.rubric_group_mark_id
LEFT  JOIN rubric_descriptor      rd  ON rdm.descriptor_id = rd.id
LEFT  JOIN rubric_criterion       rc  ON rd.criterion_id   = rc.id


UNION


SELECT
    student.id      AS student_id,

    marker.id       AS marker_id,

    a.id            AS assessment_id,
    #     a.title         AS assessment_title,

    f.id   AS folder_id,
    #     f.name AS folder_name,

    sr.id           AS feedback_id,
    #     sr.published_at AS feedback_date,
    #     sr.review_route AS feedback_type,

    rc.id AS indicator_id,
    rc.name AS indicator_name,
    rdm.value AS indicator_mark,

    rd.id AS descriptor_id,
    rd.description AS descriptor_name,
    rd.max_value AS descriptor_max_value

FROM assessment a

-- folder join: via project
INNER JOIN project_assessment pa      ON a.id          = pa.assessment_id
INNER JOIN assessment         project ON pa.project_id = project.id
INNER JOIN assessment_folder  af      ON project.id    = af.assessment_id
INNER JOIN folder             f       ON af.folder_id  = f.id

-- return joins
INNER JOIN submission_box    sb      ON a.id      = sb.assessment_id
INNER JOIN submission_return sr      ON sb.id     = sr.box_id
INNER JOIN user              student ON sr.owner  = student.id
INNER JOIN user              marker  ON sr.marker = marker.id

-- rubric capability mark joins
INNER JOIN rubric_group_mark      rgm ON sr.id             = rgm.return_id
LEFT  JOIN rubric_descriptor_mark rdm ON rgm.id            = rdm.rubric_group_mark_id
LEFT  JOIN rubric_descriptor      rd  ON rdm.descriptor_id = rd.id
LEFT  JOIN rubric_criterion       rc  ON rd.criterion_id   = rc.id


UNION


SELECT
    student.id      AS student_id,

    marker.id       AS marker_id,

    a.id            AS assessment_id,
    #     a.title         AS assessment_title,

    f.id   AS folder_id,
    #     f.name AS folder_name,

    sr.id           AS feedback_id,
    #     sr.published_at AS feedback_date,
    #     sr.review_route AS feedback_type,

    rc.id AS indicator_id,
    rc.name AS indicator_name,
    rdm.value AS indicator_mark,

    rd.id AS descriptor_id,
    rd.description AS descriptor_name,
    rd.max_value AS descriptor_max_value

FROM assessment a

-- folder join: via course unit impl
INNER JOIN assessment_course_unit_implementation acui on a.id = acui.assessment_id
INNER JOIN course_unit_implementation cui on acui.course_unit_implementation_id = cui.id
INNER JOIN course_implementation ci on cui.course_implementation_id = ci.id
INNER JOIN folder f on ci.folder_id = f.id

-- return joins
INNER JOIN submission_box    sb      ON a.id      = sb.assessment_id
INNER JOIN submission_return sr      ON sb.id     = sr.box_id
INNER JOIN user              student ON sr.owner  = student.id
INNER JOIN user              marker  ON sr.marker = marker.id

-- rubric capability mark joins
INNER JOIN rubric_group_mark      rgm ON sr.id             = rgm.return_id
LEFT  JOIN rubric_descriptor_mark rdm ON rgm.id            = rdm.rubric_group_mark_id
LEFT  JOIN rubric_descriptor      rd  ON rdm.descriptor_id = rd.id
LEFT  JOIN rubric_criterion       rc  ON rd.criterion_id   = rc.id
;
