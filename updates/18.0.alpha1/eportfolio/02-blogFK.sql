DELETE eportfolio_component_blog FROM eportfolio_component_blog
  LEFT JOIN eportfolio_page ON eportfolio_page.page_id = eportfolio_component_blog.page_id
WHERE eportfolio_page.page_id IS NULL;

ALTER TABLE `eportfolio_component_blog`
  CHANGE COLUMN `page_id` `page_id` INT(11) NOT NULL ,
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NOT NULL ,
  ADD INDEX `fk_eportfolio_component_blog_page_id_idx` (`page_id` ASC);
ALTER TABLE `eportfolio_component_blog`
  ADD CONSTRAINT `fk_eportfolio_component_blog_page_id`
FOREIGN KEY (`page_id`)
REFERENCES `eportfolio_page` (`page_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE eportfolio_component_blog
  LEFT JOIN `user` ON `owner_id` = `user`.id
SET owner_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `eportfolio_component_blog`
  ADD INDEX `fk_eportfolio_component_blog_owner_id_idx` (`owner_id` ASC);
ALTER TABLE `eportfolio_component_blog`
  ADD CONSTRAINT `fk_eportfolio_component_blog_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;