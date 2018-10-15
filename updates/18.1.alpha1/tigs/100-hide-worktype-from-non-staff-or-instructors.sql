-- Create the new column
ALTER TABLE lu_work_type
  ADD COLUMN staff_or_instructor_visible_only BOOL NULL;

-- Set them all to 'false' (default case)
UPDATE lu_work_type
SET staff_or_instructor_visible_only = FALSE;

-- And remove the nullability of this column.
ALTER TABLE lu_work_type
  CHANGE COLUMN staff_or_instructor_visible_only staff_or_instructor_visible_only BOOL NOT NULL;
