-- Delete descriptors that satisfy ALL the following:
-- * descriptor.box != descriptor.level.box OR descriptor.level does not exist
-- * descriptor.valid IS FALSE
-- * descriptor has no marks referring to it.

-- 929 matches on SEDA
DELETE FROM rubric_descriptor
WHERE id IN (
  SELECT * FROM (
    SELECT rd.id
    FROM rubric_descriptor rd
      LEFT JOIN rubric_level rl ON rl.id            = rd.level_id
      LEFT JOIN rubric_mark rm  ON rm.descriptor_id = rd.id
    WHERE rd.valid IS NOT TRUE
    AND rl.id IS NULL
    GROUP BY rd.id
  ) t
);