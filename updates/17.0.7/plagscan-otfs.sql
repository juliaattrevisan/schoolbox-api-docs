-- Add OTFS storage `hash` column to plagscan_document table

ALTER TABLE plagscan_document ADD storage_hash VARCHAR(40) CHARACTER SET ASCII NULL;
ALTER TABLE plagscan_document ADD storage_migrated_at DATETIME NULL;
ALTER TABLE plagscan_document ADD storage_failed_at DATETIME NULL;

