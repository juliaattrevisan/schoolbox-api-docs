-- TODO Move 'user_id' to 'assessment table, then drop column 'user_id'
-- Note that although 'deleted_at' is redundant, we must retain it to ensure soft-deletion for this table
ALTER TABLE task
  DROP COLUMN category_id,
  DROP COLUMN title,
  DROP COLUMN assess_id,
  DROP COLUMN box_id,
  DROP COLUMN `order`,
  DROP COLUMN created_at;

