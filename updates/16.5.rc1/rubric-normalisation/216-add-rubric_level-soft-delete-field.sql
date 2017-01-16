-- The following tables already have soft-delete fields:
-- * rubric_mark,
-- * rubric_descriptor,
-- * submission_box.
-- Because marks belong to a descriptor, and descriptors belong to a level, and a level belongs to a SubBox,
-- we cannot enforce foreign keys unless all are soft-deletable.
ALTER TABLE `rubric_level`
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL,
  ADD INDEX  `deleted_at` (`deleted_at`);

-- Set levels belonging to soft-deleted assessments to be soft-deleted at the time the assessment was deleted
UPDATE rubric_level rl
JOIN submission_box sb ON sb.id = rl.box_id
JOIN assessment a ON a.id = sb.assessment_id
SET rl.deleted_at = a.deleted_at
WHERE a.deleted_at IS NOT NULL;


-- At this point, any levels with unknown sequences belong to deleted subBoxes.
-- Simply set their sequence to 0 and mark as soft-deleted, then forget about it. These just exist
-- for referential integrity so as to avoid deleting otherwise orphan descriptors and marks.
-- On SEDA this is 267 levels on 69 boes
UPDATE rubric_level rl
JOIN submission_box sb ON sb.id = rl.box_id
SET
  rl.sequence = 0,
  rl.deleted_at = '1970-01-01 00:00:00'
WHERE rl.sequence = -1
AND sb.deleted IS TRUE;

-- And re-disable negative sequences.
ALTER TABLE rubric_level
  CHANGE COLUMN sequence sequence INT(11) UNSIGNED;