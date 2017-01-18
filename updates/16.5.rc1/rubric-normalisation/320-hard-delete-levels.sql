-- Hard-delete orphan levels to enable foreign keys

-- If level belongs to a box that has been hard-deleted, hard-delete the level too
DELETE FROM rubric_level
WHERE id IN (
  SELECT * FROM (
   SELECT rl.id
   FROM rubric_level rl LEFT JOIN submission_box sb ON sb.id = rl.box_id
   WHERE sb.id IS NULL
   ORDER BY rl.id
 ) t
);

-- If a Level has no Descriptors, delete the Level
-- On SEDA, this deletes 12313 of 132555 levels.
-- I think this number is high because we have inserted new levels, rather than trying to link
-- to existing levels. Eg, when re-sequencing levels for boxes, I noted that the boxes often had an alternate set
-- of levels, often fully-sequenced, but they had no descriptors.
DELETE FROM rubric_level
WHERE id IN (
  SELECT * FROM (
    SELECT rl.id
    FROM rubric_level rl
    LEFT JOIN rubric_descriptor rd ON rd.level_id = rl.id
    WHERE rd.id IS NULL
    ORDER BY rl.id
  ) t
);

