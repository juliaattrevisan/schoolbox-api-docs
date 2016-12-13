-- Even though Projects inherit behaviour from the Assessment table, which has a 'deletedAt' field,
-- the mappers expect this field on each table that is soft-deletable
ALTER TABLE project
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL;
