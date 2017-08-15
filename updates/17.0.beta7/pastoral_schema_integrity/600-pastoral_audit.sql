-- This should not allow NULLs
ALTER TABLE pastoral_audit
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;
