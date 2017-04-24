
DELETE forumThread FROM forumThread
 LEFT JOIN folder ON categoryID = folder.id
WHERE folder.id IS NULL;

UPDATE forumThread
  LEFT JOIN `user` ON `userID` = `user`.id
SET userID = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `forumThread`
  CHANGE COLUMN `categoryID` `categoryID` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `userID` `userID` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `forumThread`
  ADD CONSTRAINT `fk_forumThread_folder_id`
FOREIGN KEY (`categoryID`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_forumThread_user_id`
FOREIGN KEY (`userID`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE forumPost FROM forumPost
  LEFT JOIN forumThread ON forumThread.threadID = forumPost.threadID
WHERE forumThread.threadID IS NULL;

UPDATE forumPost
  LEFT JOIN `user` ON `userID` = `user`.id
SET userID = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `forumPost`
  ADD CONSTRAINT `fk_forumPost_thread_id`
FOREIGN KEY (`threadID`)
REFERENCES `forumThread` (`threadID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `forumPost`
  CHANGE COLUMN `userID` `userID` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `forumPost`
  ADD CONSTRAINT `fk_forumPost_user_id`
FOREIGN KEY (`userID`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;