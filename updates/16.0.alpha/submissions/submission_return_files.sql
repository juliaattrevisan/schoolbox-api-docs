/* Drop filesize column as it is no longer referenced */
ALTER TABLE `submission_return`
DROP COLUMN `filesize`,
CHANGE COLUMN `filename` `filename` VARCHAR(255) NULL DEFAULT NULL COMMENT '' ;

/* Copy filenames from storage to return table */
UPDATE submission_return SET filename = (
  SELECT filename FROM `storage` WHERE submission_return.hash = storage.hash
)
WHERE `hash` IS NOT NULL
AND (filename IS NULL OR filename = '');
