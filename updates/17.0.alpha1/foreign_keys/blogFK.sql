DELETE blog FROM blog
  LEFT JOIN `folder` ON folder_id = `folder`.id
WHERE `folder`.id is null;

UPDATE blog
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `blog`
  ADD CONSTRAINT `fk_blog_folder_id`
FOREIGN KEY (`folder_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_blog_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


DELETE blog_comment FROM blog_comment
  LEFT JOIN `blog` ON blog_id = `blog`.id
WHERE `blog`.id is null;

UPDATE blog_comment
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `blog_comment`
  ADD CONSTRAINT `fk_blog_comment_blog_id`
FOREIGN KEY (`blog_id`)
REFERENCES `blog` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_blog_comment_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `blog_image`
  CHANGE COLUMN `blog_id` `blog_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `hash` `hash` VARCHAR(50) NOT NULL ;


ALTER TABLE `blog_image`
  ADD INDEX `fk_blog_image_blog_id_idx` (`blog_id` ASC),
  ADD INDEX `fk_blog_image_storage_hash_idx` (`hash` ASC);
ALTER TABLE `blog_image`
  ADD CONSTRAINT `fk_blog_image_blog_id`
FOREIGN KEY (`blog_id`)
REFERENCES `blog` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_blog_image_storage_hash`
FOREIGN KEY (`hash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;