-- Add the image and its FK (well I would, but storage isn't an INNODB table)
ALTER TABLE `resource`
  ADD COLUMN `storage_image_id` int NULL DEFAULT NULL,
  ADD INDEX `resource_storage_image_id` (`storage_image_id`)
;