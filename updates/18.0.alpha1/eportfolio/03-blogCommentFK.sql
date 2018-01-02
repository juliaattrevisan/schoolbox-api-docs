
DELETE eportfolio_component_blog_comment FROM eportfolio_component_blog_comment
  LEFT JOIN eportfolio_component_blog ON eportfolio_component_blog.id = eportfolio_component_blog_comment.blog_id
WHERE eportfolio_component_blog.id IS NULL;


UPDATE eportfolio_component_blog_comment
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;


ALTER TABLE `schoolbox`.`eportfolio_component_blog_comment`
  CHANGE COLUMN `blog_id` `blog_id` INT(11) NOT NULL ,
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `schoolbox`.`eportfolio_component_blog_comment`
  ADD INDEX `fk_eportfolio_component_blog_comment_blog_id_idx` (`blog_id` ASC),
  ADD INDEX `fk_eportfolio_component_blog_comment_owner_id_idx` (`owner_id` ASC);
ALTER TABLE `schoolbox`.`eportfolio_component_blog_comment`
  ADD CONSTRAINT `fk_eportfolio_component_blog_comment_blog_id`
FOREIGN KEY (`blog_id`)
REFERENCES `schoolbox`.`eportfolio_component_blog` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_eportfolio_component_blog_comment_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;