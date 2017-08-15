-- Does not inject null for empty strings
-- (validation should prevent empty name, but out of scope for this ticket)
UPDATE lu_pastoral_severity
  SET name = ''
  WHERE name IS NULL;

ALTER TABLE lu_pastoral_severity
  CHANGE COLUMN `name` `name` VARCHAR(100) NOT NULL;

-- Should not permit null
ALTER TABLE lu_pastoral_severity
  CHANGE COLUMN `order` `order` INT(11) NOT NULL;

-- This is a bool flag, not an int.
ALTER TABLE lu_pastoral_severity
  CHANGE COLUMN dashboard dashboard BOOL NOT NULL DEFAULT false AFTER `order`;
