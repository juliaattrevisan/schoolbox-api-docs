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