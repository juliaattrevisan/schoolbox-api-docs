DELETE allowedFileExtension FROM allowedFileExtension
  LEFT JOIN `allowedFileTypes` ON `allowedFileTypes`.fileTypeID = `allowedFileExtension`.fileTypeID
WHERE `allowedFileTypes`.fileTypeID is null;

ALTER TABLE `allowedFileExtension`
  CHANGE COLUMN `fileTypeID` `fileTypeID` INT(11) NOT NULL ;

ALTER TABLE `allowedFileExtension`
  ADD CONSTRAINT `fk_allowedFileExtension_file_type_id`
FOREIGN KEY (`fileTypeID`)
REFERENCES `allowedFileTypes` (`fileTypeID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE submission_box_file FROM submission_box_file
 LEFT JOIN storage ON storage.hash = submission_box_file.storage_id
WHERE storage.hash is NULL;

ALTER TABLE `submission_box_file`
  CHANGE COLUMN `storage_id` `storage_id` VARCHAR(100) CHARACTER SET utf8 NOT NULL ;
ALTER TABLE `submission_box_file`
  ADD CONSTRAINT `fk_submission_box_file_storage_hash`
FOREIGN KEY (`storage_id`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE video_queue FROM video_queue
  LEFT JOIN `storage` ON `storage`.hash = `video_queue`.hash
WHERE `storage`.hash is null;

ALTER TABLE `video_queue`
  ADD CONSTRAINT `fk_video_queue_storage_hash`
FOREIGN KEY (`hash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


UPDATE tile
  LEFT JOIN `storage` ON `storage`.hash = `tile`.background_image_hash
    SET background_image_hash = NULL
WHERE `storage`.hash is null;

ALTER TABLE `tile`
  ADD CONSTRAINT `fk_tile_background_image_hash`
FOREIGN KEY (`background_image_hash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


UPDATE news_items
  LEFT JOIN `storage` ON `storage`.hash = news_items.item_image
SET item_image = NULL
WHERE `storage`.hash is null;


ALTER TABLE `news_items`
  CHANGE COLUMN `item_image` `item_image` VARCHAR(100) CHARACTER SET utf8 NULL;
ALTER TABLE `news_items`
  ADD INDEX `fk_news_items_image_hash_idx` (`item_image` ASC);
ALTER TABLE `news_items`
  ADD CONSTRAINT `fk_news_items_image_hash`
FOREIGN KEY (`item_image`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `file`
  CHANGE COLUMN `category` `category` INT(5) UNSIGNED NULL DEFAULT NULL,
  CHANGE COLUMN `hash` `hash` VARCHAR(100) CHARACTER SET utf8 NULL ;


DELETE file FROM file LEFT JOIN folder ON file.category = folder.id WHERE folder.id is null;

UPDATE file
  LEFT JOIN `storage` ON `storage`.hash = file.hash
SET file.hash = NULL
WHERE `storage`.hash is null;

UPDATE file
  LEFT JOIN `user` ON `owner` = `user`.id
SET owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;


ALTER TABLE `file`
  ADD CONSTRAINT `fk_file_folder_id`
FOREIGN KEY (`category`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_file_owner_id`
FOREIGN KEY (`owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_file_storage_hash`
FOREIGN KEY (`hash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE link FROM link LEFT JOIN folder ON link.category = folder.id WHERE folder.id is null;

UPDATE link
  LEFT JOIN `user` ON `owner` = `user`.id
SET owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `link`
  ADD CONSTRAINT `fk_link_folder_id`
FOREIGN KEY (`category`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_link_owner_id`
FOREIGN KEY (`owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
