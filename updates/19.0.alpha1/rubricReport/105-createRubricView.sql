-- This VIEW represents an unmarked rubric.
-- A tuple will exist for a rubric, regardless of whether any students have yet been marked
CREATE OR REPLACE VIEW v_rubric AS

SELECT
  -- Assessment fields
  a.id                         AS assessment_id,
  sb.mark_type                 AS assessment_mark_type,
  sb.max_mark                  AS assessment_max_mark,
  sb.submission_grade_group_id AS assessment_grade_group_id,
  sb.rubric_group_marks        AS assessment_mark_per_capability,

  -- Rubric capability fields
  rg.id                        AS capability_id,
  rg.name                      AS capability_name,
  rgu.max_value                AS capability_max_value,
  rgu.sequence                 AS capability_sequence,

  -- Rubric indicator fields
  rc.id                        AS indicator_id,
  rc.name                      AS indicator_name,
  rcu.sequence                 AS indicator_sequence,
  MAX(rd.max_value)            AS indicator_max_value

  FROM assessment a

  -- Due work joins
  INNER JOIN submission_box sb ON a.id = sb.assessment_id

  -- Rubric indicator, descriptor joins
  -- Join through rubric_criterion_usage as I only care about indicators actually in use
  INNER JOIN rubric_group_usage     rgu ON  sb.id               = rgu.submission_box_id
  INNER JOIN rubric_group           rg  ON  rgu.rubric_group_id = rg.id

  INNER JOIN rubric_criterion_usage rcu ON  sb.id               = rcu.submission_box_id
  INNER JOIN rubric_criterion       rc  ON  rcu.criterion_id    = rc.id AND
                                            rc.rubric_group_id  = rg.id

  INNER JOIN rubric_descriptor      rd  ON  rd.criterion_id     = rc.id

  -- Only valid rubric marks
  WHERE
    a.deleted_at IS NULL AND
    rg.deleted_at IS NULL AND
    rc.deleted_at IS NULL AND
    rd.deleted_at IS NULL

  GROUP BY sb.id, rc.id

  ORDER BY
    a.id            ASC,
    rgu.sequence    ASC,
    rg.id           ASC,
    rcu.sequence    ASC,
    rc.id           ASC
;
