-- Does not inject null for empty strings
-- (validation should prevent empty name, but out of scope for this ticket)
UPDATE lu_pastoral_tag
  SET name = ''
  WHERE name IS NULL;

ALTER TABLE lu_pastoral_tag
  CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL;

-- This should not allow NULLs
UPDATE lu_pastoral_tag
  SET created_at = '1970-01-01 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE lu_pastoral_tag
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- This should not allow NULLs
UPDATE lu_pastoral_tag
SET updated_at = created_at
WHERE updated_at IS NULL;

ALTER TABLE lu_pastoral_tag
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;
