-- Does not inject null for empty strings
-- (validation should prevent empty name, but out of scope for this ticket)
UPDATE lu_pastoral_type
  SET name = CONCAT('Unnamed Type #', id)
  WHERE name IS NULL;

ALTER TABLE lu_pastoral_type
  CHANGE COLUMN `name` `name` VARCHAR(100) NOT NULL;

-- This should not allow NULLs
ALTER TABLE lu_pastoral_type
  CHANGE COLUMN external_id _external_id INT(11) NULL COMMENT 'deprecated in 17.0';
