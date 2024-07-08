UPDATE summative_report
SET logo_hash = NULL
WHERE logo_hash NOT IN (
    SELECT hash
    FROM storage
);

ALTER TABLE summative_report ADD CONSTRAINT summative_report_logo_hash_fk FOREIGN KEY (logo_hash) REFERENCES storage (hash);
