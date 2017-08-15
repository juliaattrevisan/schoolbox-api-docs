-- This should not allow NULLs
UPDATE pastoral_tags
SET created_at = '0000-00-00 00:00:00'
WHERE created_at IS NULL;

ALTER TABLE pastoral_tags
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;
