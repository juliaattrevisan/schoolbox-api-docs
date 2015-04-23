ALTER TABLE `resource`
  ADD COLUMN `image_hash` VARCHAR(40) NULL AFTER `storage_image_id`,
  ADD INDEX (`image_hash`);

UPDATE resource r
LEFT JOIN `storage` s ON s.storage_id = r.storage_image_id
SET r.image_hash = s.hash;

