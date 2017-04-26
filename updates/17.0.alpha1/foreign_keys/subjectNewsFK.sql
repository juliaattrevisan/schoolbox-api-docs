DELETE subject_news FROM subject_news
  LEFT JOIN folder ON code = folder.id
WHERE folder.id IS NULL;

UPDATE subject_news
  LEFT JOIN `user` ON `owner` = `user`.id
SET owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `subject_news`
  ADD INDEX `fk_subject_news_user_id_idx` (`owner` ASC);
ALTER TABLE `subject_news`
  ADD CONSTRAINT `fk_subject_news_folder_id`
FOREIGN KEY (`code`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_subject_news_user_id`
FOREIGN KEY (`owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
