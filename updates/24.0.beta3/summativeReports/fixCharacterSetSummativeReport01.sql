-- Re-run the summative report updates from 23.1.19.
-- This set of updates is idempotent and can thus be re-run.

UPDATE summative_report
SET logo_hash = NULL
WHERE logo_hash REGEXP '[^a-z0-9]+';

ALTER TABLE summative_report MODIFY logo_hash varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL;
