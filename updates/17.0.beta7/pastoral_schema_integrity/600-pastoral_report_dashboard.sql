-- This should not allow NULLs
UPDATE pastoral_report_dashboard
SET created_at = '0000-00-00 00:00:00'
WHERE created_at IS NULL;

ALTER TABLE pastoral_report_dashboard
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- This should not allow NULLs
UPDATE pastoral_report_dashboard
SET updated_at = '0000-00-00 00:00:00'
WHERE updated_at IS NULL;

ALTER TABLE pastoral_report_dashboard
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;


