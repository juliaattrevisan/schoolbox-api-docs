-- Delete orphan descriptors to enable foreign keys

-- This should do nothing, as we have already repaired this link in a 2xx script.
DELETE FROM rubric_descriptor
WHERE id IN (
  SELECT * FROM (
    SELECT rd.id FROM rubric_descriptor rd
    LEFT JOIN rubric_level rl ON rl.id = rd.level_id
    WHERE rl.id IS NULL
  ) t
);

-- This will probably clean up a small amount of corrupt data
-- Testing indicates 12 / 704022 descriptors deleted on SEDA
DELETE FROM rubric_descriptor
WHERE id IN (
  SELECT * FROM (
    SELECT rd.id FROM rubric_descriptor rd
    LEFT JOIN rubric_criterion rc ON rc.id = rd.criterion_id
    WHERE rc.id IS NULL
  ) t
);






-- Delete descriptors and levels if they are linked (both directly and indirectly via `rubric_level`) to sub boxes that don't exist
-- These *should* be safe to delete, they are logically the result of deleting a sub box prior to intro of soft-deletion.
CREATE TABLE temp_descriptors_and_levels(
  rd_id INT(11) UNSIGNED,
  rl_id INT(11) UNSIGNED
);
-- Get all descriptors and levels that are both linked to the same sub box, but the sub box doesn't exist.
INSERT INTO temp_descriptors_and_levels (
  SELECT rd.id, rl.id
  FROM `rubric_descriptor` rd
  JOIN `rubric_level` rl ON rl.id = rd.level_id
  WHERE rd.submission_box_id = rl.box_id
  AND rd.submission_box_id NOT IN (SELECT DISTINCT id FROM submission_box)
);
-- Delete orphan descriptors
DELETE FROM `rubric_descriptor` WHERE `id` IN (
  SELECT DISTINCT rd_id FROM temp_descriptors_and_levels
);
-- Delete orphan levels
DELETE FROM `rubric_level` WHERE `id` IN (
  SELECT DISTINCT rl_id FROM temp_descriptors_and_levels
);
-- Drop temp table
DROP TABLE temp_descriptors_and_levels;