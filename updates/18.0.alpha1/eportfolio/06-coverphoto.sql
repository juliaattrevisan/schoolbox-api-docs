DELETE eportfolio_coverphoto FROM eportfolio_coverphoto
  LEFT JOIN user ON user.id = eportfolio_coverphoto.uid
WHERE user.id IS NULL;

DELETE eportfolio_coverphoto FROM eportfolio_coverphoto
  LEFT JOIN storage ON storage.hash = eportfolio_coverphoto.imagehash
WHERE storage.hash IS NULL;

ALTER TABLE `eportfolio_coverphoto`
  CHANGE COLUMN `uid` `uid` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `eportfolio_coverphoto`
  ADD INDEX `fk_eportfolio_coverphoto_user_id_idx` (`uid` ASC),
  ADD INDEX `fk_eportfolio_coverphoto_hash_idx` (`imagehash` ASC);
ALTER TABLE `eportfolio_coverphoto`
  ADD CONSTRAINT `fk_eportfolio_coverphoto_user_id`
FOREIGN KEY (`uid`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_eportfolio_coverphoto_hash`
FOREIGN KEY (`imagehash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
