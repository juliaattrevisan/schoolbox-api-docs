-- Move column so our CRUD timestamps are at end
-- The code was treating this as string in some places, int in others.
-- It needs to be a string as we cannot assume the SIS will always use numeric identifiers
ALTER TABLE pastoral_comment
  CHANGE COLUMN external_id external_code VARCHAR(64) NULL AFTER body;

-- Code analysis indicates this should not be null
UPDATE pastoral_comment
  SET body = ''
  WHERE body IS NULL;

ALTER TABLE pastoral_comment
  CHANGE COLUMN body body TEXT NOT NULL;

-- This should not allow NULLs
-- Preserve default in case there was some semantic purpose
UPDATE pastoral_comment
  SET created_at = '1970-01-01 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE pastoral_comment
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- This should not allow NULLs
-- Preserve default in case there was some semantic purpose
UPDATE pastoral_comment
  SET updated_at = '1970-01-01 00:00:00'
  WHERE updated_at IS NULL;

ALTER TABLE pastoral_comment
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;
