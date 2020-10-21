-- In this release we start to rely on the size column in this table. But we may have incorrect values
-- due to a bug that existed between 20.0.12 and 20.1.0.
-- We need to therefore just truncate this table, and let it fill up again naturally.
-- Or we can run the tool in the future, but only on 20.1.0+ codebases.
DELETE FROM storage_transform;
