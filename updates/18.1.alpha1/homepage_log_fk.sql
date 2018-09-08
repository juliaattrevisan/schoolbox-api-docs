ALTER TABLE `homepage_log`
  CHANGE COLUMN `user` `user_id` INT(6) UNSIGNED NOT NULL,
  CHANGE COLUMN `cid` `folder_id` INT(6) UNSIGNED NOT NULL ;

DELETE homepage_log FROM homepage_log
LEFT JOIN user ON user.id = homepage_log.user_id
WHERE user.id IS NULL;

DELETE homepage_log FROM homepage_log
LEFT JOIN folder ON folder.id = homepage_log.folder_id
WHERE folder.id IS NULL;


ALTER TABLE `schoolbox`.`homepage_log`
  ADD CONSTRAINT `fk_homepage_log_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_homepage_log_folder_id`
FOREIGN KEY (`folder_id`)
REFERENCES `schoolbox`.`folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
