DELETE news_items FROM news_items
  LEFT JOIN `newsTopic` ON `item_topic_id` = `newsTopic`.newsTopicID
WHERE `newsTopic`.newsTopicID is null;


ALTER TABLE `newsTopic`
  CHANGE COLUMN `newsTopicID` `newsTopicID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;

ALTER TABLE `news_items`
  CHANGE COLUMN `item_topic_id` `item_topic_id` INT(11) UNSIGNED NULL;


ALTER TABLE `news_items`
  ADD CONSTRAINT `fk_news_items_topic_id`
FOREIGN KEY (`item_topic_id`)
REFERENCES `newsTopic` (`newsTopicID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE news_items
  LEFT JOIN `user` ON `item_owner` = `user`.id
    SET item_owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `news_items`
  DROP COLUMN `item_mainpage`;

ALTER TABLE `news_items`
  ADD INDEX `fk_news_items_owner_id_idx` (`item_owner` ASC);
ALTER TABLE `news_items`
  ADD CONSTRAINT `fk_news_items_owner_id`
FOREIGN KEY (`item_owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


DELETE newsUser FROM newsUser
  LEFT JOIN `user` ON `userID` = `user`.id
WHERE `user`.id is null;

DELETE newsUser FROM newsUser
  LEFT JOIN `news_items` ON `newsID` = `news_items`.item_id
WHERE `news_items`.item_id is null;

ALTER TABLE `newsUser`
  ADD INDEX `fk_newsUser_user_id_idx` (`userID` ASC);
ALTER TABLE `newsUser`
  ADD CONSTRAINT `fk_newsUser_user_id`
FOREIGN KEY (`userID`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `newsUser`
  ADD CONSTRAINT `fk_newsUser_news_id`
FOREIGN KEY (`newsID`)
REFERENCES `news_items` (`item_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE newsGroup FROM newsGroup
  LEFT JOIN `news_items` ON `item_id` = `newsGroup`.id
WHERE `news_items`.item_id is null;

DELETE newsGroup FROM newsGroup
  LEFT JOIN `groups` ON `groups`.`id` = `newsGroup`.gid
WHERE `groups`.id is null;


ALTER TABLE `newsGroup`
  ADD CONSTRAINT `fk_newsGroup_news_id`
FOREIGN KEY (`id`)
REFERENCES `news_items` (`item_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `newsGroup`
  ADD CONSTRAINT `fk_newsGroup_role_id`
FOREIGN KEY (`gid`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE newsCampus FROM newsCampus
  LEFT JOIN `news_items` ON `item_id` = `newsCampus`.id
WHERE `news_items`.item_id is null;

DELETE newsCampus FROM newsCampus
  LEFT JOIN `luCampus` ON `luCampus`.`id` = `newsCampus`.campus
WHERE `luCampus`.id is null;

ALTER TABLE `newsCampus`
  ADD CONSTRAINT `fk_newsCampus_news_id`
FOREIGN KEY (`id`)
REFERENCES `news_items` (`item_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `newsCampus`
  CHANGE COLUMN `campus` `campus` TINYINT(4) UNSIGNED NOT NULL;

ALTER TABLE `newsCampus`
  ADD CONSTRAINT `fk_newsCampus_campus_id`
FOREIGN KEY (`campus`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
