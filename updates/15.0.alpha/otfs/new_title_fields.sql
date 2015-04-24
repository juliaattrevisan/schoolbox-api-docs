ALTER TABLE `tile`
  ADD COLUMN `background_image_hash` VARCHAR(40) NULL AFTER `background_image_id`,
  ADD INDEX (`background_image_hash`);

UPDATE `tile` t
LEFT JOIN `storage` s ON s.storage_id = t.background_image_id
SET t.background_image_hash = s.hash;