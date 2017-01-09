-- Delete usage if:
-- * usage.criterion does not exist, or
-- * usage.submission_box does not exist
-- Without a criterion or submission_box the record is useless
DELETE FROM rubric_usage WHERE (criterion_id, submission_box_id) IN (
  SELECT criterion_id, submission_box_id FROM (
      SELECT ru.criterion_id, ru.submission_box_id
      FROM rubric_usage ru
      LEFT JOIN rubric_criterion rc ON rc.id = ru.criterion_id
      LEFT JOIN submission_box sb ON sb.id = ru.submission_box_id
      WHERE (rc.id IS NULL OR sb.id IS NULL)
    ) t
);

-- If a Usage has no Descriptors, delete the Usage
-- Inner SELECT finds Usages without any descriptors
DELETE FROM rubric_usage WHERE (criterion_id, submission_box_id) IN (
  SELECT * FROM (
    SELECT ru.criterion_id, ru.submission_box_id
    FROM rubric_usage ru
      LEFT JOIN rubric_descriptor rd ON rd.criterion_id = ru.criterion_id
      LEFT JOIN rubric_level      rl ON rl.id           = rd.level_id
    WHERE rl.box_id = ru.submission_box_id
    AND rd.id IS NULL
  ) t
);
