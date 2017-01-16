-- Hard-delete orphan descriptors to enable foreign keys

-- This should do nothing, as we have already repaired this link in a 2xx script.
DELETE FROM rubric_descriptor
WHERE id IN (
  SELECT * FROM (
    SELECT rd.id FROM rubric_descriptor rd
    LEFT JOIN rubric_level rl ON rl.id = rd.level_id
    WHERE rl.id IS NULL
    ORDER BY rd.id
  ) t
);

-- This will probably clean up a small amount of corrupt data
-- Testing indicates 12 / 704022 descriptors deleted on SEDA
DELETE FROM rubric_descriptor
WHERE id IN (
  SELECT * FROM (
    SELECT rd.id
    FROM rubric_descriptor rd
    LEFT JOIN rubric_criterion rc ON rc.id = rd.criterion_id
    WHERE rc.id IS NULL
    ORDER BY rd.id
  ) t
);
