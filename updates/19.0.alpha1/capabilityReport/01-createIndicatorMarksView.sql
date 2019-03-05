
select
    student.id      AS student_id,

    marker.id       AS marker_id,

    a.id            AS assessment_id,
    a.title         AS assessment_title,

    f.id AS folder_id,
    f.name AS folder_name,

    sr.id           AS feedback_id,
    sr.published_at AS feedback_date,
    sr.review_route AS feedback_type,

#     rg.id     AS capability_id,
#     rg.name   AS capability_name,
#     rgm.value AS capability_mark,

    rc.id AS indicator_id,
    rc.name AS indicator_name,
    rdm.value AS indicator_mark,

    rd.id AS descriptor_id,
    rd.description AS descriptor_name,
    rd.max_value AS descriptor_max_value

from assessment a

         -- folder join: only direct relation
         inner join assessment_folder af on a.id = af.assessment_id
         inner join folder f on af.folder_id = f.id

    -- return joins
         inner join submission_box sb on a.id = sb.assessment_id
         inner join submission_return sr on sb.id = sr.box_id
         inner join user student on sr.owner  = student.id
         inner join user marker  on sr.marker = marker.id
    -- rubric capability mark joins
         INNER JOIN rubric_group_mark      rgm on sr.id = rgm.return_id
#          INNER JOIN rubric_group           rg  on rgm.rubric_group_id = rg.id
         LEFT  JOIN rubric_descriptor_mark rdm on rgm.id = rdm.rubric_group_mark_id
         LEFT  JOIN rubric_descriptor      rd  on rdm.descriptor_id = rd.id
         LEFT  JOIN rubric_criterion       rc  on rd.criterion_id = rc.id
