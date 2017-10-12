-- Add a storage hash column which references the file in which the html is stored
-- This has to be re-migrated into plagscan_document separately
ALTER TABLE `plagscan_document` ADD COLUMN `storage_hash` VARCHAR(100) CHARSET ASCII NULL AFTER `score`;

-- Foreigner key
ALTER TABLE `plagscan_document` ADD CONSTRAINT `fk_storage_hash_storage_hash` FOREIGN KEY (`storage_hash`) REFERENCES `storage`(`hash`);

-- Migrate the data from the migration results back to the plagscan_document table
--
-- We couldn't modify this table during the initial migration so I need to move all
-- storage hashes back onto the plagscan_document table separately. Schoolbox assumes
-- that this storage hash exists on plagscan_document as a result of this branch.
--
-- Can be done cheaply like this, hopefully, so I don't need to use mappers/models
UPDATE plagscan_document p
LEFT JOIN plagscan_document_migration m ON p.id = m.plagscan_document_record_id
SET p.`storage_hash` = m.`storage_hash`;
