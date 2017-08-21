-- This should not allow NULLs
UPDATE pastoral_access
  SET created_at = '1970-01-01 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE pastoral_access
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;



