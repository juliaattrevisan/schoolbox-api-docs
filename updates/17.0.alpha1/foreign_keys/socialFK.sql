ALTER TABLE `socialstream_post`
  CHANGE COLUMN `post_id` `post_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `parent_id` `parent_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `root_id` `root_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `homepage_id` `homepage_id` INT(11) UNSIGNED NULL DEFAULT NULL ;


DELETE socialstream_post FROM socialstream_post
  LEFT JOIN folder ON homepage_id = folder.id
WHERE folder.id IS NULL;


UPDATE socialstream_post
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;


ALTER TABLE `socialstream_post`
  ADD CONSTRAINT `fk_socialstream_post_folder_id`
FOREIGN KEY (`homepage_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_socialstream_post_user_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;