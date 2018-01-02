DELETE eportfolio_component_blog_image FROM eportfolio_component_blog_image
  LEFT JOIN eportfolio_component_blog ON eportfolio_component_blog.id = eportfolio_component_blog_image.blog_id
WHERE eportfolio_component_blog.id IS NULL;

ALTER TABLE `eportfolio_component_blog_image`
  CHANGE COLUMN `blog_id` `blog_id` INT(11) NOT NULL ,
  CHANGE COLUMN `hash` `hash` VARCHAR(100) CHARACTER SET 'ascii' NOT NULL ,
  ADD INDEX `fk_eportfolio_component_blog_image_blog_id_idx` (`blog_id` ASC),
  ADD INDEX `fk_eportfolio_component_blog_image_hash_idx` (`hash` ASC);
ALTER TABLE `eportfolio_component_blog_image`
  ADD CONSTRAINT `fk_eportfolio_component_blog_image_blog_id`
FOREIGN KEY (`blog_id`)
REFERENCES `eportfolio_component_blog` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_eportfolio_component_blog_image_hash`
FOREIGN KEY (`hash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;