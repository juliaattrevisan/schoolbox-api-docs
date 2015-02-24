ALTER TABLE `tile`
ADD COLUMN `background_image_contained` BOOL NOT NULL DEFAULT FALSE
AFTER `background_image_id`;
