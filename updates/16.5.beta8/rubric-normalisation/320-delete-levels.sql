-- Delete orphan levels to enable foreign keys

DELETE FROM rubric_level
WHERE id IN (
  SELECT * FROM (
   SELECT rl.id
   FROM rubric_level rl LEFT JOIN submission_box sb ON sb.id = rl.box_id
   WHERE sb.id IS NULL
 ) t
);