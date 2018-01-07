ALTER TABLE `eportfolio_page`
  CHANGE COLUMN `uid` `uid` INT(11) UNSIGNED NULL DEFAULT NULL ;

DELETE eportfolio_page FROM eportfolio_page
  LEFT JOIN user ON user.id = uid
WHERE user.id IS NULL;

ALTER TABLE `eportfolio_page`
  ADD INDEX `fk_eportfolio_page_user_id_idx` (`uid` ASC);
ALTER TABLE `eportfolio_page`
  ADD CONSTRAINT `fk_eportfolio_page_user_id`
FOREIGN KEY (`uid`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

