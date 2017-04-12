DELETE news_items FROM news_items
  LEFT JOIN `newsTopic` ON `item_topic_id` = `newsTopic`.newsTopicID
WHERE `newsTopic`.newsTopicID is null;


ALTER TABLE `newsTopic`
  CHANGE COLUMN `newsTopicID` `newsTopicID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;

ALTER TABLE `schoolbox`.`news_items`
  CHANGE COLUMN `item_topic_id` `item_topic_id` INT(11) UNSIGNED NULL DEFAULT '0' ;


ALTER TABLE `schoolbox`.`news_items`
  ADD CONSTRAINT `fk_news_items_topic_id`
FOREIGN KEY (`item_topic_id`)
REFERENCES `schoolbox`.`newsTopic` (`newsTopicID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE news_items
  LEFT JOIN `user` ON `item_owner` = `user`.id
    SET item_owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `schoolbox`.`news_items`
  DROP COLUMN `item_mainpage`;

ALTER TABLE `schoolbox`.`news_items`
  ADD INDEX `fk_news_items_owner_id_idx` (`item_owner` ASC);
ALTER TABLE `schoolbox`.`news_items`
  ADD CONSTRAINT `fk_news_items_owner_id`
FOREIGN KEY (`item_owner`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `schoolbox`.`newsUser`
  ADD INDEX `fk_newsUser_user_id_idx` (`userID` ASC);
ALTER TABLE `schoolbox`.`newsUser`
  ADD CONSTRAINT `fk_newsUser_user_id`
FOREIGN KEY (`userID`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `schoolbox`.`newsUser`
  ADD CONSTRAINT `fk_newsUser_news_id`
FOREIGN KEY (`newsID`)
REFERENCES `schoolbox`.`news_items` (`item_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;