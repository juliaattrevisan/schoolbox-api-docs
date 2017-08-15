-- Code analysis indicates this should not permit NULL
UPDATE pastoral_import_log
  SET result = ''
  WHERE result IS NULL;

ALTER TABLE pastoral_import_log
  CHANGE COLUMN result result VARCHAR(500) NOT NULL;

-- This should not allow NULLs
UPDATE pastoral_import_log
  SET created_at = '0000-00-00 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE pastoral_import_log
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;
