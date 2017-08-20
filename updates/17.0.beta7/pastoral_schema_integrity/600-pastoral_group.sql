-- Ensure no NULL group names
UPDATE pastoral_group
SET name = CONCAT('Group #', id)
WHERE name IS NULL;

ALTER TABLE pastoral_group
  CHANGE COLUMN name name VARCHAR(255) NOT NULL;


-- This should not allow NULLs
UPDATE pastoral_group
  SET created_at = '1970-01-01 00:00:00'
  WHERE created_at IS NULL;

ALTER TABLE pastoral_group
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- Don't permit NULL here
UPDATE pastoral_group
  SET updated_at = created_at
  WHERE updated_at IS NULL;

ALTER TABLE pastoral_group
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;
