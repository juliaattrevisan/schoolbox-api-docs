-- Add a storage hash column which references the file in which the html is stored
-- This has to be re-migrated into plagscan_document separately
ALTER TABLE `plagscan_document` ADD COLUMN `storage_hash` VARCHAR(255) NULL AFTER `score`;

-- Migrate the data from the migration results back to the plagscan_document table
--
-- We couldn't modify this table during the initial migration so I need to move all
-- storage hashes back onto the plagscan_document table separately. Schoolbox assumes
-- that this storage hash exists on plagscan_document as a result of this branch.
--
-- Can be done cheaply like this, hopefully, so I don't need to use mappers/models
UPDATE plagscan_document p
LEFT JOIN plagscan_document_migration m ON p.id = m.plagscan_document_id
SET p.`storage_hash` = m.`storage_hash`;