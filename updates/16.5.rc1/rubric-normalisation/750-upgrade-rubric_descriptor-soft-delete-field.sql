-- Previously, the soft-delete column for rubric_descriptor was valid:int
-- This change upgrades the field to a timestamp for greater audit ability
ALTER TABLE rubric_descriptor
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL,
    ADD INDEX `deleted_at` (`deleted_at`);

-- Set 'deleted_at' (to UNIT epoch) if:
-- * rubric_descriptor.valid IS FALSE, or
-- * Criterion is soft-deleted, or
-- * Level is soft-deleted
-- * rubric_descriptor.value = null && rubric_descriptor.description is empty, or
UPDATE rubric_descriptor rd
JOIN rubric_criterion rc ON rc.id = rd.criterion_id
JOIN rubric_level rl ON rl.id = rd.level_id
SET rd.deleted_at = '1970-01-01 00:00:00'
WHERE (
  rd.valid IS FALSE OR
  rc.deleted_at IS NOT NULL OR
  rd.deleted_at IS NOT NULL OR
  (rd.value IS NULL AND rd.description = '')
);


-- DROP old soft-delete field
ALTER TABLE rubric_descriptor
  DROP COLUMN `valid`;
