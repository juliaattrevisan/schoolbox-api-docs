CREATE OR REPLACE VIEW v_rubric_mark AS

  SELECT
    -- Assessment fields
    a.id                         AS assessment_id,
    sb.mark_type                 AS assessment_mark_type,
    sb.max_mark                  AS assessment_max_mark,
    sb.submission_grade_group_id AS assessment_grade_group_id,

    -- Feedback fields
    student.id      AS student_id,
    marker.id       AS marker_id,
    sr.id           AS feedback_id,
    sr.published_at AS feedback_date,
    sr.deleted_at AS feedback_deleted_at,
    sr.review_route AS feedback_type,

    -- Rubric capability fields
    rg.id                           AS capability_id,
    rg.name                         AS capability_name,
    rgu.sequence                    AS capability_sequence,
    rgu.max_value                   AS capability_max_value,
    IF (rgm.valid, rgm.value, NULL) AS capability_mark,

    -- Rubric indicator fields
    rc.id        AS indicator_id,
    rc.name      AS indicator_name,
    rcu.sequence AS indicator_sequence,
    -- Indicator max: must be derived unfortunately.
    -- Can anyone think of a more efficient way to do this?
    (
      -- TODO this does not work when blank descriptors are present
      SELECT MAX(rd2.max_value)
      FROM       rubric_descriptor rd2
        INNER JOIN rubric_level      rl2 ON rd2.level_id = rl2.id
      WHERE      rl2.box_id       = sb.id
                 AND        rd2.criterion_id = rc.id
    )                               AS indicator_max_value,
    IF (rdm.valid, rdm.value, NULL) AS indicator_mark,

    -- Rubric descriptor fields
    -- (Not a valid descriptor mark? Don't select it)
    IF (rdm.valid, rd.id,          NULL) AS descriptor_id,
    IF (rdm.valid, rd.description, NULL) AS descriptor_name,
    IF (rdm.valid, rd.max_value,   NULL) AS descriptor_max_value
  FROM assessment a

    -- Due work joins
    INNER JOIN submission_box sb ON a.id = sb.assessment_id

    -- Feedback joins
    INNER JOIN submission_return sr      ON sb.id     = sr.box_id
    INNER JOIN user              student ON sr.owner  = student.id
    INNER JOIN user              marker  ON sr.marker = marker.id

    -- Rubric indicator, descriptor joins
    -- Join through rubric_criterion_usage as I only care about indicators actually in use
    INNER JOIN rubric_group_usage     rgu ON  sb.id               = rgu.submission_box_id
    INNER JOIN rubric_group           rg  ON  rgu.rubric_group_id = rg.id
    INNER JOIN rubric_group_mark      rgm ON  rg.id               = rgm.rubric_group_id
                                              AND sr.id               = rgm.return_id
    INNER JOIN rubric_criterion_usage rcu ON  sb.id               = rcu.submission_box_id
    INNER JOIN rubric_criterion       rc  ON  rcu.criterion_id    = rc.id
    INNER JOIN rubric_descriptor      rd  ON  rc.id               = rd.criterion_id
    INNER JOIN rubric_descriptor_mark rdm ON  rd.id               = rdm.descriptor_id
                                              AND rgm.id              = rdm.rubric_group_mark_id

  -- Only valid rubric marks
  WHERE (rgm.valid IS TRUE OR rdm.valid IS TRUE)

  ORDER BY
    a.id            ASC,
    student.id      ASC,
    sr.published_at ASC,
    rgu.sequence    ASC,
    rg.id           ASC,
    rcu.sequence    ASC,
    rc.id           ASC
;
