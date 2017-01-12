-- Previously, the soft-delete column for rubric_descriptor was valid:int
-- This change upgrades the field to a timestamp for greater audit ability
ALTER TABLE rubric_descriptor
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL,
    ADD INDEX `deleted_at` (`deleted_at`);

-- Back-fill with UNIX EPOCH
UPDATE rubric_descriptor
SET deleted_at = '1970-01-01 00:00:00'
WHERE valid = 0;

-- DROP old soft-delete field
ALTER TABLE rubric_descriptor
  DROP COLUMN `valid`;
