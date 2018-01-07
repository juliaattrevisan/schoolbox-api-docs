ALTER TABLE `poll`
  CHANGE COLUMN `categoryID` `folder_id` INT(11) UNSIGNED NOT NULL ;

DELETE poll FROM poll
 LEFT JOIN folder ON folder.id = folder_id
WHERE folder.id IS NULL;


ALTER TABLE `poll`
  ADD CONSTRAINT `fk_poll_folder_id`
FOREIGN KEY (`folder_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `poll`
  CHANGE COLUMN `created_by` `created_by` INT(11) UNSIGNED NULL DEFAULT NULL ;

UPDATE poll
  LEFT JOIN `user` ON `created_by` = `user`.id
SET created_by = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;


ALTER TABLE `poll`
  ADD CONSTRAINT `fk_poll_created_by`
FOREIGN KEY (`created_by`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `poll`
  CHANGE COLUMN `pollID` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ;

ALTER TABLE `pollLog`
  CHANGE COLUMN `pollID` `poll_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `userID` `user_id` INT(11) UNSIGNED NOT NULL ;

DELETE pollLog FROM pollLog
  LEFT JOIN poll ON poll.id = poll_id
WHERE poll.id IS NULL;

DELETE pollLog FROM pollLog
  LEFT JOIN user ON user.id = user_id
WHERE user.id IS NULL;

ALTER TABLE `pollLog`
  ADD INDEX `fk_pollLog_user_id_idx` (`user_id` ASC);
ALTER TABLE `pollLog`
  ADD CONSTRAINT `fk_pollLog_poll_id`
FOREIGN KEY (`poll_id`)
REFERENCES `poll` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pollLog_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
