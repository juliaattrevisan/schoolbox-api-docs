INSERT INTO filestore
SELECT
  storage_id AS id,
  `hash`,
  REVERSE(LEFT(REVERSE(filename), LOCATE('.', REVERSE(filename)) - 1)) AS ext,
  filesize AS size
FROM `storage`