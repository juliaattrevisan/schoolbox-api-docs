ALTER TABLE `schoolbox`.`submission_box_file`
  ADD CONSTRAINT `fk_submission_box_file_storage_hash`
FOREIGN KEY (`storage_id`)
REFERENCES `schoolbox`.`storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE video_queue FROM video_queue
  LEFT JOIN `storage` ON `storage`.hash = `video_queue`.hash
WHERE `storage`.hash is null;

ALTER TABLE `schoolbox`.`video_queue`
  ADD CONSTRAINT `fk_video_queue_storage_hash`
FOREIGN KEY (`hash`)
REFERENCES `schoolbox`.`storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


UPDATE tile
  LEFT JOIN `storage` ON `storage`.hash = `tile`.background_image_hash
    SET background_image_hash = NULL
WHERE `storage`.hash is null;

ALTER TABLE `schoolbox`.`tile`
  ADD CONSTRAINT `fk_tile_background_image_hash`
FOREIGN KEY (`background_image_hash`)
REFERENCES `schoolbox`.`storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


UPDATE news_items
  LEFT JOIN `storage` ON `storage`.hash = news_items.item_image
SET item_image = NULL
WHERE `storage`.hash is null;


ALTER TABLE `schoolbox`.`news_items`
  ADD INDEX `fk_news_items_image_hash_idx` (`item_image` ASC);
ALTER TABLE `schoolbox`.`news_items`
  ADD CONSTRAINT `fk_news_items_image_hash`
FOREIGN KEY (`item_image`)
REFERENCES `schoolbox`.`storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `schoolbox`.`file`
  CHANGE COLUMN `category` `category` INT(5) UNSIGNED NULL DEFAULT NULL,
  CHANGE COLUMN `hash` `hash` VARCHAR(40) NULL ;

UPDATE file
  LEFT JOIN `storage` ON `storage`.hash = file.hash
SET file.hash = NULL
WHERE `storage`.hash is null;

UPDATE file
  LEFT JOIN `user` ON `owner` = `user`.id
SET owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `schoolbox`.`file`
  ADD CONSTRAINT `fk_file_folder_id`
FOREIGN KEY (`category`)
REFERENCES `schoolbox`.`folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_file_owner_id`
FOREIGN KEY (`owner`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_file_storage_hash`
FOREIGN KEY (`hash`)
REFERENCES `schoolbox`.`storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;