-- Delete orphan levels to enable foreign keys

DELETE FROM rubric_level
WHERE id IN (
  SELECT * FROM (
   SELECT rl.id
   FROM rubric_level rl LEFT JOIN submission_box sb ON sb.id = rl.box_id
   WHERE sb.id IS NULL
 ) t
);

-- If a Level has no Descriptors, delete the Level
DELETE FROM rubric_level
WHERE id IN (
  SELECT * FROM (
    SELECT rl.id FROM rubric_level rl
    LEFT JOIN rubric_descriptor rd ON rd.level_id = rl.id
    WHERE rd.id IS NULL
  ) t
);

