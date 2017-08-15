-- This should not allow NULLs
UPDATE pastoral_group_member
  SET created_at = '0000-00-00 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE pastoral_group_member
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;
