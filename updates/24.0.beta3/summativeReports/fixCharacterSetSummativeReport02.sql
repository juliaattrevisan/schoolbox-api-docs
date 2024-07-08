-- Re-run the summative report updates from 23.1.19.
-- This set of updates is idempotent if the FK constraint is not re-added.

UPDATE summative_report
SET logo_hash = NULL
WHERE logo_hash NOT IN (
    SELECT hash
    FROM storage
);

ALTER TABLE summative_report ADD CONSTRAINT summative_report_logo_hash_fk FOREIGN KEY IF NOT EXISTS (logo_hash) REFERENCES storage (hash);
